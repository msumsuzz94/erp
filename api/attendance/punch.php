<?php

/**
 * Attendance Punch API
 * Records check-in / check-out from mobile or biometric
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$staff_id = (int)($input['staff_id'] ?? 0);
$punch_type = $input['punch_type'] ?? 'in';
$source = $input['source'] ?? 'web_biometric';
$gps_lat = $input['gps_latitude'] ?? null;
$gps_lng = $input['gps_longitude'] ?? null;
$device_info = $input['device_info'] ?? null;

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Staff ID required']);
    exit;
}

// Verify staff and shift exists
$staff = db_query_one("SELECT s.*, sh.start_time, sh.end_time, sh.early_checkin_time, sh.max_checkin_time, sh.late_mark_time 
    FROM staff s 
    LEFT JOIN shifts sh ON s.shift_id = sh.id 
    WHERE s.id = ?", [$staff_id]);

if (!$staff) {
    echo json_encode(['success' => false, 'message' => 'Staff not found']);
    exit;
}

// Verify settings
$settings_res = db_query("SELECT setting_key, setting_value FROM attendance_settings");
$settings = [];
foreach ($settings_res ?: [] as $r) $settings[$r['setting_key']] = $r['setting_value'];

// Check if source is allowed
if ($source === 'web_biometric' && ($settings['allow_web_biometric'] ?? '1') == '0') {
    echo json_encode(['success' => false, 'message' => 'WebAuthn is disabled in settings.']);
    exit;
}

// Prevent duplicate punch
$recent = db_query("SELECT id FROM attendance_logs WHERE staff_id = ? AND punch_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)", [$staff_id]);
if (!empty($recent)) {
    echo json_encode(['success' => false, 'message' => 'Already recorded within the last minute']);
    exit;
}

// Check max check-ins
$max_check_ins = (int)($settings['max_checkins_per_day'] ?? 0);
if ($max_check_ins > 0) {
    $today_punches = db_query("SELECT COUNT(*) as cnt FROM attendance_logs WHERE staff_id = ? AND DATE(punch_time) = CURDATE()", [$staff_id]);
    $punch_count = $today_punches[0]['cnt'] ?? 0;
    if ($punch_count >= $max_check_ins) {
        echo json_encode(['success' => false, 'message' => "Daily check-in limit ($max_check_ins) reached."]);
        exit;
    }
}

if ($punch_type === 'auto') {
    $last_punch = db_query("SELECT punch_type FROM attendance_logs WHERE staff_id = ? AND DATE(punch_time) = CURDATE() ORDER BY punch_time DESC LIMIT 1", [$staff_id]);
    if (!empty($last_punch)) {
        $last_type = $last_punch[0]['punch_type'];
        $punch_type = ($last_type === 'in' || $last_type === 'return_duty') ? 'out' : 'in';
    } else {
        $punch_type = 'in';
    }
}

// 1. Dynamic Shift Detection
$now_time = date('H:i:s');
$detected_shift = null;
$detected_segment = null;

if (!$staff['shift_id']) {
    echo json_encode(['success' => false, 'message' => 'No shift assigned to this staff.']);
    exit;
}

// Fetch the assigned shift and its segments
$shift = db_query_one("SELECT * FROM shifts WHERE id = ? AND is_active = 1", [$staff['shift_id']]);
if (!$shift) {
    echo json_encode(['success' => false, 'message' => 'Assigned shift is inactive or not found.']);
    exit;
}

$segments = json_decode($shift['shift_segments'], true);
if (empty($segments)) {
    // Fallback if no segments defined (use main start/end)
    $segments = [[
        'name' => $shift['name'],
        'start' => $shift['start_time'],
        'end' => $shift['end_time'],
        'early' => $shift['early_checkin_time'] ?? '',
        'late' => $shift['late_mark_time'] ?? '',
        'max' => $shift['max_checkin_time'] ?? ''
    ]];
}

// Check-out priority: If punching out, ALWAYS prioritize the currently open attendance record for today (if any)
if ($punch_type === 'out') {
    $open_att = db_query_one("SELECT shift_id, segment_name FROM attendance WHERE staff_id = ? AND date = CURDATE() AND time_in IS NOT NULL AND time_out IS NULL AND shift_id = ?", [$staff_id, $shift['id']]);
    if ($open_att) {
        $detected_shift = $shift;
        // Find the specific segment they are currently checked into
        foreach ($segments as $seg) {
            if (($seg['name'] ?? 'Default') === $open_att['segment_name']) {
                $detected_segment = $seg;
                break;
            }
        }
        if (!$detected_segment) {
            $detected_segment = $segments[0];
        }
    } else {
        // Forcefully reject checkout if there's no open check-in
        echo json_encode(['success' => false, 'message' => 'Cannot check-out. You have not checked-in for this shift yet.']);
        exit;
    }
}

// If not punching out from an active segment, detect by current time bounds
if (!$detected_segment) {
    foreach ($segments as $seg) {
        $early = !empty($seg['early']) ? $seg['early'] : $seg['start'];
        $max = !empty($seg['max']) ? $seg['max'] : $seg['end'];

        // Check if current time is within the allowed window for this segment
        if ($now_time >= $early && $now_time <= $max) {
            $detected_shift = $shift;
            $detected_segment = $seg;
            break;
        }
    }
}

if (!$detected_segment) {
    echo json_encode(['success' => false, 'message' => 'Not within any allowed shift time (Early/Max window).']);
    exit;
}

$shift_id = $detected_shift['id'];
$segment_name = $detected_segment['name'] ?? 'Default';
$late_mark = !empty($detected_segment['late']) ? $detected_segment['late'] : $detected_segment['start'];

// 2. Auto Check-Out for previous segments of DIFFERENT SHIFTS or different segments of SAME SHIFT
// Find any open "in" punches for this staff today that are NOT the current detected segment
$open_punches = db_query("SELECT * FROM attendance WHERE staff_id = ? AND date = CURDATE() AND time_out IS NULL AND (shift_id != ? OR segment_name != ?)", [$staff_id, $shift_id, $segment_name]);
foreach ($open_punches as $op) {
    // Try to find the end time for this specific segment
    $op_shift = db_query_one("SELECT shift_segments, end_time FROM shifts WHERE id = ?", [$op['shift_id']]);
    $auto_out_time = date('Y-m-d') . ' ';

    $found_seg_end = false;
    if ($op_shift && !empty($op['segment_name'])) {
        $op_segs = json_decode($op_shift['shift_segments'], true);
        foreach ($op_segs ?: [] as $os) {
            if ($os['name'] === $op['segment_name']) {
                $auto_out_time .= $os['end'];
                $found_seg_end = true;
                break;
            }
        }
    }

    if (!$found_seg_end) {
        $auto_out_time .= ($op_shift['end_time'] ?? '23:59:59');
    }

    db_update('attendance', ['time_out' => $auto_out_time, 'notes' => ($op['notes'] ? $op['notes'] . ' | ' : '') . 'Auto closed by system'], ['id' => $op['id']]);
}

// 3. Status Validation
$attendance_status = 'present';
if ($punch_type === 'in') {
    if ($now_time > $late_mark) {
        $attendance_status = 'late';
    }
}

// 4. Record/Update Attendance Row (Unique per Shift per Staff per Day)
try {
    $curr_time = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    // Check for existing record for THIS SPECIFIC SEGMENT today
    $main_att = db_select_one('attendance', ['staff_id' => $staff_id, 'date' => $today, 'segment_name' => $segment_name]);

    if ($main_att) {
        $update_data = [];
        if ($punch_type === 'in') {
            if (empty($main_att['time_in']) || strpos((string)$main_att['time_in'], '0000-00-00') !== false) {
                $update_data['time_in'] = $curr_time;
                $update_data['status'] = $attendance_status;
            }
        } else {
            $update_data['time_out'] = $curr_time;
        }
        if (!empty($update_data)) {
            db_update('attendance', $update_data, ['id' => $main_att['id']]);
        }
    } else {
        db_insert('attendance', [
            'staff_id' => $staff_id,
            'date' => $today,
            'status' => $attendance_status,
            'shift_id' => $shift_id,
            'segment_name' => $segment_name,
            'time_in' => ($punch_type === 'in' ? $curr_time : null),
            'time_out' => ($punch_type === 'out' ? $curr_time : null),
            'notes' => 'Recorded from ' . $source
        ]);
    }

    // Map source to verification method
    $method = 'manual';
    if ($source === 'web_biometric') $method = 'fingerprint';
    elseif ($source === 'web_card') $method = 'card';
    elseif ($source === 'web_face') $method = 'face';

    // Always log the raw punch
    db_insert('attendance_logs', [
        'staff_id' => $staff_id,
        'device_id' => null,
        'punch_type' => $punch_type,
        'punch_time' => $curr_time,
        'verification_method' => $method,
        'source' => $source,
        'device_info' => $device_info,
        'status' => 'valid'
    ]);

    $msg = ($punch_type === 'in' ? 'Check-in' : 'Check-out') . ' successful for ' . $detected_shift['name'] . ' (' . $segment_name . ')';
    echo json_encode([
        'success' => true,
        'message' => $msg,
        'time' => date('h:i A'),
        'shift' => $detected_shift['name']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to record: ' . $e->getMessage()]);
}
