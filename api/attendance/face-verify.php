<?php

/**
 * Face Verify API
 * Receives face photo + descriptor, matches against stored, records attendance
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
$descriptor = $input['descriptor'] ?? null;
$punch_type = $input['punch_type'] ?? 'in';
$gps = $input['gps'] ?? null;
$threshold = (float)($input['threshold'] ?? 0.5);
$photo = $input['photo'] ?? '';

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Staff ID required']);
    exit;
}

// Verify settings
$settings_res = db_query("SELECT setting_key, setting_value FROM attendance_settings");
$settings = [];
foreach ($settings_res ?: [] as $r) $settings[$r['setting_key']] = $r['setting_value'];

// Check if source is allowed
if (($settings['allow_web_face'] ?? '1') == '0') {
    echo json_encode(['success' => false, 'message' => 'Web Face Login is disabled in settings.']);
    exit;
}

// Fetch staff and shift info
$staff_info = db_query_one("SELECT s.*, sh.start_time, sh.end_time, sh.early_checkin_time, sh.max_checkin_time, sh.late_mark_time 
    FROM staff s 
    LEFT JOIN shifts sh ON s.shift_id = sh.id 
    WHERE s.id = ?", [$staff_id]);

if (!$staff_info) {
    echo json_encode(['success' => false, 'message' => 'Staff not found.']);
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

// If descriptor provided, verify against stored
$confidence = null;
if ($descriptor && is_array($descriptor)) {
    $stored = db_query("SELECT face_descriptor FROM staff_biometrics WHERE staff_id = ? AND biometric_type = 'face' AND is_active = 1 ORDER BY created_at DESC LIMIT 1", [$staff_id]);

    if (!empty($stored) && !empty($stored[0]['face_descriptor'])) {
        $stored_desc = json_decode($stored[0]['face_descriptor'], true);
        if ($stored_desc) {
            $distance = 0;
            for ($i = 0; $i < min(count($descriptor), count($stored_desc)); $i++) {
                $distance += pow($descriptor[$i] - $stored_desc[$i], 2);
            }
            $distance = sqrt($distance);
            $confidence = max(0, 1 - $distance);

            if ($distance > (1 - $threshold)) {
                echo json_encode(['success' => false, 'message' => 'Face does not match.']);
                exit;
            }
        }
    }
}

// Photo saving skipped as per user request to save storage
$photo_path = '';

// Auto-toggle punch type
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

if (!$staff_info['shift_id']) {
    echo json_encode(['success' => false, 'message' => 'No shift assigned to this staff.']);
    exit;
}

// Fetch the assigned shift and its segments
$shift = db_query_one("SELECT * FROM shifts WHERE id = ? AND is_active = 1", [$staff_info['shift_id']]);
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

// 2. Auto Check-Out for previous segments
$open_punches = db_query("SELECT * FROM attendance WHERE staff_id = ? AND date = CURDATE() AND time_out IS NULL AND (shift_id != ? OR segment_name != ?)", [$staff_id, $shift_id, $segment_name]);
foreach ($open_punches as $op) {
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

// 4. Record/Update Attendance Row
$curr_time = date('Y-m-d H:i:s');
$today = date('Y-m-d');

try {
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
            'notes' => 'Face verification check-' . $punch_type
        ]);
    }

    // Always log the raw punch
    $log_data = [
        'staff_id' => $staff_id,
        'device_id' => null,
        'punch_time' => $curr_time,
        'punch_type' => $punch_type,
        'verification_method' => 'face',
        'source' => 'web_face',
        'status' => 'valid',
        'photo_path' => '',
        'face_confidence' => $confidence,
        'gps_latitude' => $gps['lat'] ?? null,
        'gps_longitude' => $gps['lng'] ?? null,
        'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    db_insert('attendance_logs', $log_data);

    $msg = ($punch_type === 'in' ? 'Check-in' : 'Check-out') . ' successful for ' . $detected_shift['name'] . ' (' . $segment_name . ')';
    echo json_encode([
        'success' => true,
        'message' => $msg,
        'confidence' => $confidence,
        'shift' => $detected_shift['name']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
