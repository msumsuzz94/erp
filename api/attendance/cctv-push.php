<?php
/**
 * CCTV Push API
 * Receives present/away events from Python CCTV middleware
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';

header('Content-Type: application/json');

// Allow GET for status check
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'status';
    
    if ($action === 'status') {
        // Return current presence status for all staff
        $today = date('Y-m-d');
        $status = db_query("SELECT t.staff_id, s.name, t.event_type, t.created_at
            FROM cctv_tracking_logs t
            JOIN staff s ON t.staff_id = s.id
            WHERE t.date = ?
            ORDER BY t.created_at DESC", [$today]);
        
        // Get latest event per staff
        $latest = [];
        foreach ($status ?: [] as $row) {
            if (!isset($latest[$row['staff_id']])) {
                $latest[$row['staff_id']] = $row;
            }
        }
        echo json_encode(['success' => true, 'data' => array_values($latest)]);
        exit;
    }
    
    if ($action === 'cameras') {
        // Return active cameras for Python middleware
        $cameras = db_query("SELECT * FROM cctv_configurations WHERE is_active = 1");
        echo json_encode(['success' => true, 'cameras' => $cameras ?: []]);
        exit;
    }
    
    if ($action === 'faces') {
        // Return all face descriptors for Python middleware
        $faces = db_query("SELECT sb.staff_id, sb.face_photo, s.name, s.employee_code
            FROM staff_biometrics sb
            JOIN staff s ON sb.staff_id = s.id
            WHERE sb.biometric_type = 'face' AND sb.is_active = 1");
        echo json_encode(['success' => true, 'faces' => $faces ?: []]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$staff_id = (int)($input['staff_id'] ?? 0);
$camera_id = (int)($input['camera_id'] ?? 0);
$event_type = $input['event_type'] ?? ''; // present, away, return
$timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');

if (!$staff_id || !$event_type) {
    echo json_encode(['success' => false, 'message' => 'staff_id and event_type required']);
    exit;
}

// Check allowed sources
$settings_res = db_query("SELECT setting_key, setting_value FROM attendance_settings");
$settings = [];
foreach ($settings_res ?: [] as $r) $settings[$r['setting_key']] = $r['setting_value'];

// Wait, CCTV is not directly limited by web toggles, but we check if CCTV itself is allowed maybe?
// Actually if they don't want CCTV, the setting might just not exist or CCTV scripts won't run.
// We'll skip toggle check for CCTV specific here unless there's an 'allow_cctv' added.


$today = date('Y-m-d', strtotime($timestamp));

if ($event_type === 'present') {
    // Record attendance check-in via CCTV
    $existing = db_query("SELECT id FROM attendance_logs WHERE staff_id = ? AND DATE(punch_time) = ? AND source = 'cctv' AND punch_type = 'in'", [$staff_id, $today]);
    
    if (empty($existing)) {
        $punch_type = 'in';
        $last_punch = db_query("SELECT punch_type FROM attendance_logs WHERE staff_id = ? AND DATE(punch_time) = CURDATE() ORDER BY punch_time DESC LIMIT 1", [$staff_id]);
        if (!empty($last_punch)) {
            $last_type = $last_punch[0]['punch_type'];
            if ($last_type === 'in' || $last_type === 'return_duty') $punch_type = 'out';
            else $punch_type = 'in';
        }

        db_insert('attendance_logs', [
            'staff_id' => $staff_id,
            'punch_time' => $timestamp,
            'punch_type' => $punch_type,
            'source' => 'cctv',
            'verification_method' => 'face',
            'status' => 'valid',
            'device_info' => 'CCTV Camera #' . $camera_id
        ]);
        
        // Auto-sync
        $today_dt = date('Y-m-d', strtotime($timestamp));
        $main_att = db_select_one('attendance', ['staff_id' => $staff_id, 'date' => $today_dt]);
        if ($main_att) {
            $update_data = [];
            if ($punch_type === 'in') {
                if (empty($main_att['time_in']) || strpos((string)$main_att['time_in'], '0000-00-00') !== false) {
                    $update_data['time_in'] = $timestamp;
                } else if (empty($main_att['time_in_2']) || strpos((string)$main_att['time_in_2'], '0000-00-00') !== false) {
                    $update_data['time_in_2'] = $timestamp;
                }
            } elseif ($punch_type === 'out') {
                if (empty($main_att['time_out']) || strpos((string)$main_att['time_out'], '0000-00-00') !== false) {
                    $update_data['time_out'] = $timestamp;
                } else if (empty($main_att['time_out_2']) || strpos((string)$main_att['time_out_2'], '0000-00-00') !== false) {
                    $update_data['time_out_2'] = $timestamp;
                }
            }
            $update_data['status'] = 'present';
            db_update('attendance', $update_data, ['id' => $main_att['id']]);
        } else {
            $staff_record = db_select_one('staff', ['id' => $staff_id]);
            db_insert('attendance', [
                'staff_id' => $staff_id,
                'date' => $today_dt,
                'status' => 'present',
                'shift_id' => $staff_record['shift_id'] ?? null,
                'time_in' => ($punch_type === 'in' ? $timestamp : null),
                'time_out' => ($punch_type === 'out' ? $timestamp : null),
                'notes' => 'Auto recorded from CCTV Camera #' . $camera_id
            ]);
        }
    }
    
    db_insert('cctv_tracking_logs', [
        'staff_id' => $staff_id,
        'camera_id' => $camera_id,
        'event_type' => 'present',
        'date' => $today,
    ]);
    
} elseif ($event_type === 'away') {
    db_insert('cctv_tracking_logs', [
        'staff_id' => $staff_id,
        'camera_id' => $camera_id,
        'event_type' => 'away',
        'away_start_time' => $timestamp,
        'date' => $today,
    ]);
    
    // Record "out" in attendance
    $punch_type = 'out';
    $last_punch = db_query("SELECT punch_type FROM attendance_logs WHERE staff_id = ? AND DATE(punch_time) = CURDATE() ORDER BY punch_time DESC LIMIT 1", [$staff_id]);
    if (!empty($last_punch)) {
        $last_type = $last_punch[0]['punch_type'];
        if ($last_type === 'in' || $last_type === 'return_duty') $punch_type = 'out';
        else $punch_type = 'in';
    }

    db_insert('attendance_logs', [
        'staff_id' => $staff_id,
        'punch_time' => $timestamp,
        'punch_type' => $punch_type,
        'source' => 'cctv',
        'verification_method' => 'face',
        'status' => 'valid',
        'device_info' => 'CCTV Away - Camera #' . $camera_id
    ]);
    
    // Auto sync
    $today_dt = date('Y-m-d', strtotime($timestamp));
    $main_att = db_select_one('attendance', ['staff_id' => $staff_id, 'date' => $today_dt]);
    if ($main_att) {
        $update_data = [];
        if ($punch_type === 'in') {
            if (empty($main_att['time_in']) || strpos((string)$main_att['time_in'], '0000-00-00') !== false) {
                $update_data['time_in'] = $timestamp;
            } else if (empty($main_att['time_in_2']) || strpos((string)$main_att['time_in_2'], '0000-00-00') !== false) {
                $update_data['time_in_2'] = $timestamp;
            }
        } elseif ($punch_type === 'out') {
            if (empty($main_att['time_out']) || strpos((string)$main_att['time_out'], '0000-00-00') !== false) {
                $update_data['time_out'] = $timestamp;
            } else if (empty($main_att['time_out_2']) || strpos((string)$main_att['time_out_2'], '0000-00-00') !== false) {
                $update_data['time_out_2'] = $timestamp;
            }
        }
        db_update('attendance', $update_data, ['id' => $main_att['id']]);
    }
    
    // Send notification if enabled
    $notify = db_query("SELECT setting_value FROM attendance_settings WHERE setting_key = 'away_notify_enabled'");
    if (!empty($notify) && $notify[0]['setting_value'] === '1') {
        $staff = db_select_one('staff', ['id' => $staff_id]);
        // Trigger notification via existing notification system
        if (function_exists('send_notification')) {
            $msg = ($staff['name'] ?? 'Staff #'.$staff_id) . ' has left the desk area (Camera #' . $camera_id . ') at ' . date('h:i A', strtotime($timestamp));
            // send_notification('admin', 'CCTV Alert', $msg);
        }
    }
    
} elseif ($event_type === 'return') {
    // Find last away record to calculate duration
    $last_away = db_query("SELECT * FROM cctv_tracking_logs WHERE staff_id = ? AND camera_id = ? AND event_type = 'away' AND date = ? AND return_time IS NULL ORDER BY away_start_time DESC LIMIT 1",
        [$staff_id, $camera_id, $today]);
    
    if (!empty($last_away)) {
        $away_start = strtotime($last_away[0]['away_start_time']);
        $return = strtotime($timestamp);
        $duration = round(($return - $away_start) / 60, 2);
        
        db_query("UPDATE cctv_tracking_logs SET return_time = ?, duration_minutes = ? WHERE id = ?",
            [$timestamp, $duration, $last_away[0]['id']]);
    }
    
    db_insert('cctv_tracking_logs', [
        'staff_id' => $staff_id,
        'camera_id' => $camera_id,
        'event_type' => 'return',
        'return_time' => $timestamp,
        'date' => $today,
    ]);
    
    // Record "in" in attendance
    $punch_type = 'in';
    $last_punch = db_query("SELECT punch_type FROM attendance_logs WHERE staff_id = ? AND DATE(punch_time) = CURDATE() ORDER BY punch_time DESC LIMIT 1", [$staff_id]);
    if (!empty($last_punch)) {
        $last_type = $last_punch[0]['punch_type'];
        if ($last_type === 'in' || $last_type === 'return_duty') $punch_type = 'out';
        else $punch_type = 'in';
    }

    db_insert('attendance_logs', [
        'staff_id' => $staff_id,
        'punch_time' => $timestamp,
        'punch_type' => $punch_type,
        'source' => 'cctv',
        'verification_method' => 'face',
        'status' => 'valid',
        'device_info' => 'CCTV Return - Camera #' . $camera_id
    ]);
    
    // Auto sync
    $today_dt = date('Y-m-d', strtotime($timestamp));
    $main_att = db_select_one('attendance', ['staff_id' => $staff_id, 'date' => $today_dt]);
    if ($main_att) {
        $update_data = [];
        if ($punch_type === 'in') {
            if (empty($main_att['time_in']) || strpos((string)$main_att['time_in'], '0000-00-00') !== false) {
                $update_data['time_in'] = $timestamp;
            } else if (empty($main_att['time_in_2']) || strpos((string)$main_att['time_in_2'], '0000-00-00') !== false) {
                $update_data['time_in_2'] = $timestamp;
            }
        } elseif ($punch_type === 'out') {
            if (empty($main_att['time_out']) || strpos((string)$main_att['time_out'], '0000-00-00') !== false) {
                $update_data['time_out'] = $timestamp;
            } else if (empty($main_att['time_out_2']) || strpos((string)$main_att['time_out_2'], '0000-00-00') !== false) {
                $update_data['time_out_2'] = $timestamp;
            }
        }
        db_update('attendance', $update_data, ['id' => $main_att['id']]);
    }
}

// Update camera status
db_query("UPDATE cctv_configurations SET last_status = 'online', last_check = NOW() WHERE id = ?", [$camera_id]);

echo json_encode(['success' => true, 'event' => $event_type]);
