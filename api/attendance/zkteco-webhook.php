<?php
/**
 * ZKTeco / ADMS Push Webhook
 * Receives attendance data from physical biometric machines
 * Supports: ZKTeco ADMS push, Dahua, Hikvision
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';

header('Content-Type: application/json');

// Allow both GET (heartbeat) and POST (punch data)
$method = $_SERVER['REQUEST_METHOD'];

// ADMS Protocol: GET request for commands
if ($method === 'GET') {
    $sn = $_GET['SN'] ?? $_GET['sn'] ?? '';
    
    if (!empty($sn)) {
        // Update device heartbeat
        db_query("UPDATE attendance_devices SET last_heartbeat = NOW() WHERE device_sn = ?", [$sn]);
        
        // ADMS expects specific responses
        echo "OK";
    } else {
        echo json_encode(['success' => true, 'message' => 'ZKTeco webhook ready']);
    }
    exit;
}

if ($method === 'POST') {
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // Handle ADMS push data (form-encoded)
    if (strpos($content_type, 'application/x-www-form-urlencoded') !== false || !empty($_POST)) {
        $sn = $_POST['SN'] ?? $_GET['SN'] ?? '';
        $table = $_POST['table'] ?? '';
        
        if ($table === 'ATTLOG' || !empty($_POST['PIN'])) {
            // ADMS Attendance Log Format
            $employee_code = $_POST['PIN'] ?? '';
            $punch_time = $_POST['AttTime'] ?? $_POST['TimeStr'] ?? date('Y-m-d H:i:s');
            $punch_status = $_POST['Status'] ?? '0'; // 0=in, 1=out
            $verify_type = $_POST['Verify'] ?? '0'; // 0=password, 1=fingerprint, 2=card, 15=face
            
            processZKPunch($sn, $employee_code, $punch_time, $punch_status, $verify_type);
        }
        
        echo "OK";
        exit;
    }
    
    // Handle JSON push (generic/Dahua/Hikvision)
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $sn = $input['device_sn'] ?? $input['SN'] ?? '';
        $employee_code = $input['employee_code'] ?? $input['employeeNo'] ?? $input['PIN'] ?? '';
        $punch_time = $input['punch_time'] ?? $input['time'] ?? date('Y-m-d H:i:s');
        $punch_type = $input['punch_type'] ?? ($input['type'] ?? 'auto');
        $verify_method = $input['method'] ?? 'machine';
        $temperature = $input['temperature'] ?? null;
        
        if (empty($employee_code)) {
            echo json_encode(['success' => false, 'message' => 'Employee code required']);
            exit;
        }
        
        // Find staff by employee_code
        $staff = db_query("SELECT id FROM staff WHERE employee_code = ? OR id = ?", [$employee_code, $employee_code]);
        if (empty($staff)) {
            echo json_encode(['success' => false, 'message' => 'Staff not found: ' . $employee_code]);
            exit;
        }
        
        $staff_id = $staff[0]['id'];
        
        // Find device
        $device = db_select_one('attendance_devices', ['device_sn' => $sn]);
        $device_id = $device['id'] ?? null;
        
        // Auto-toggle punch logic
        if ($punch_type === 'auto') {
            $last_punch = db_query("SELECT punch_type FROM attendance_logs WHERE staff_id = ? AND DATE(punch_time) = CURDATE() ORDER BY punch_time DESC LIMIT 1", [$staff_id]);
            if (!empty($last_punch)) {
                $last_type = $last_punch[0]['punch_type'];
                if ($last_type === 'in' || $last_type === 'return_duty') {
                    $punch_type = 'out';
                } else {
                    $punch_type = 'in';
                }
            } else {
                $punch_type = 'in';
            }
        }
        
        // Record
        $data = [
            'staff_id' => $staff_id,
            'device_id' => $device_id,
            'punch_time' => $punch_time,
            'punch_type' => $punch_type,
            'verification_method' => $verify_method,
            'source' => 'machine',
            'status' => 'valid',
            'temperature' => $temperature,
            'device_info' => 'Machine: ' . ($device['device_name'] ?? $sn)
        ];
        
        // Duplicate check (same staff, same minute)
        $dup = db_query("SELECT id FROM attendance_logs WHERE staff_id = ? AND punch_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 MINUTE)", 
            [$staff_id, $punch_time, $punch_time]);
        if (!empty($dup)) {
            $data['status'] = 'duplicate';
        }
        
        db_insert('attendance_logs', $data);
        
        if ($data['status'] !== 'duplicate') {
             // Auto sync
            $today_dt = date('Y-m-d', strtotime($punch_time));
            $main_att = db_select_one('attendance', ['staff_id' => $staff_id, 'date' => $today_dt]);
            if ($main_att) {
                $update_data = [];
                if ($punch_type === 'in') {
                    if (empty($main_att['time_in']) || strpos((string)$main_att['time_in'], '0000-00-00') !== false) {
                        $update_data['time_in'] = $punch_time;
                    } else if (empty($main_att['time_in_2']) || strpos((string)$main_att['time_in_2'], '0000-00-00') !== false) {
                        $update_data['time_in_2'] = $punch_time;
                    }
                } elseif ($punch_type === 'out') {
                    if (empty($main_att['time_out']) || strpos((string)$main_att['time_out'], '0000-00-00') !== false) {
                        $update_data['time_out'] = $punch_time;
                    } else if (empty($main_att['time_out_2']) || strpos((string)$main_att['time_out_2'], '0000-00-00') !== false) {
                        $update_data['time_out_2'] = $punch_time;
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
                    'time_in' => ($punch_type === 'in' ? $punch_time : null),
                    'time_out' => ($punch_type === 'out' ? $punch_time : null),
                    'notes' => 'Auto recorded from Machine: ' . $sn
                ]);
            }
        }
        
        // Update device heartbeat
        if ($device) {
            db_query("UPDATE attendance_devices SET last_heartbeat = NOW() WHERE id = ?", [$device['id']]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Recorded']);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'No data received']);
}

function processZKPunch($sn, $pin, $time, $status, $verify) {
    // Find staff
    $staff = db_query("SELECT id FROM staff WHERE employee_code = ? OR id = ?", [$pin, $pin]);
    if (empty($staff)) return;
    
    $staff_id = $staff[0]['id'];
    $device = db_select_one('attendance_devices', ['device_sn' => $sn]);
    
    $verify_methods = ['0'=>'password', '1'=>'fingerprint', '2'=>'card', '3'=>'password', '4'=>'card', '15'=>'face'];
    
    // ZKTeco sets 0 = Check-in, 1 = Check-out, 2 = Break-out, but often just sends 0 or 255 depending on mode.
    // If status is 255 or unknown, we auto toggle.
    $punch_type = 'auto'; 
    if ($status == '0' || $status == '4') $punch_type = 'in';
    elseif ($status == '1' || $status == '5') $punch_type = 'out';
    
    if ($punch_type === 'auto') {
        $last_punch = db_query("SELECT punch_type FROM attendance_logs WHERE staff_id = ? AND DATE(punch_time) = CURDATE() ORDER BY punch_time DESC LIMIT 1", [$staff_id]);
        if (!empty($last_punch)) {
            $last_type = $last_punch[0]['punch_type'];
            if ($last_type === 'in' || $last_type === 'return_duty') $punch_type = 'out';
            else $punch_type = 'in';
        } else {
            $punch_type = 'in';
        }
    }
    
    $data = [
        'staff_id' => $staff_id,
        'device_id' => $device['id'] ?? null,
        'punch_time' => $time,
        'punch_type' => $punch_type,
        'verification_method' => $verify_methods[$verify] ?? 'unknown',
        'source' => 'machine',
        'status' => 'valid',
        'device_info' => 'ZKTeco: ' . $sn
    ];
    
    $dup = db_query("SELECT id FROM attendance_logs WHERE staff_id = ? AND punch_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 MINUTE)", 
        [$staff_id, $time, $time]);
        
    if (!empty($dup)) $data['status'] = 'duplicate';
    
    db_insert('attendance_logs', $data);
    
    if ($data['status'] !== 'duplicate') {
        // Auto sync
        $today_dt = date('Y-m-d', strtotime($time));
        $main_att = db_select_one('attendance', ['staff_id' => $staff_id, 'date' => $today_dt]);
        if ($main_att) {
            $update_data = [];
            if ($punch_type === 'in') {
                if (empty($main_att['time_in']) || strpos((string)$main_att['time_in'], '0000-00-00') !== false) {
                    $update_data['time_in'] = $time;
                } else if (empty($main_att['time_in_2']) || strpos((string)$main_att['time_in_2'], '0000-00-00') !== false) {
                    $update_data['time_in_2'] = $time;
                }
            } elseif ($punch_type === 'out') {
                if (empty($main_att['time_out']) || strpos((string)$main_att['time_out'], '0000-00-00') !== false) {
                    $update_data['time_out'] = $time;
                } else if (empty($main_att['time_out_2']) || strpos((string)$main_att['time_out_2'], '0000-00-00') !== false) {
                    $update_data['time_out_2'] = $time;
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
                'time_in' => ($punch_type === 'in' ? $time : null),
                'time_out' => ($punch_type === 'out' ? $time : null),
                'notes' => 'Auto recorded from ZKTeco: ' . $sn
            ]);
        }
    }
    
    if ($device) {
        db_query("UPDATE attendance_devices SET last_heartbeat = NOW() WHERE id = ?", [$device['id']]);
    }
}
