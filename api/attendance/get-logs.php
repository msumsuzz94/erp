<?php
/**
 * Get Attendance Logs API
 * Fetch attendance punch logs with filtering
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

try {
    // Get filter parameters
    $staff_id = $_GET['staff_id'] ?? null;
    $device_id = $_GET['device_id'] ?? null;
    $date_from = $_GET['date_from'] ?? date('Y-m-d');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $status = $_GET['status'] ?? null;
    $punch_type = $_GET['punch_type'] ?? null;
    $limit = min((int)($_GET['limit'] ?? 100), 1000); // Max 1000 records
    
    // Build query
    $sql = "SELECT al.*, 
            s.name as staff_name, 
            s.employee_id as employee_code,
            s.designation,
            d.device_name,
            d.device_type,
            d.location as device_location
            FROM attendance_logs al
            INNER JOIN staff s ON al.staff_id = s.id
            LEFT JOIN attendance_devices d ON al.device_id = d.id
            WHERE al.punch_time BETWEEN ? AND ?";
    
    $params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    
    if ($staff_id) {
        $sql .= " AND al.staff_id = ?";
        $params[] = $staff_id;
    }
    
    if ($device_id) {
        $sql .= " AND al.device_id = ?";
        $params[] = $device_id;
    }
    
    if ($status) {
        $sql .= " AND al.status = ?";
        $params[] = $status;
    }
    
    if ($punch_type) {
        $sql .= " AND al.punch_type = ?";
        $params[] = $punch_type;
    }
    
    $sql .= " ORDER BY al.punch_time DESC LIMIT ?";
    $params[] = $limit;
    
    $logs = db_query($sql, $params);
    
    // Get statistics
    $stats_sql = "SELECT 
                  COUNT(*) as total_punches,
                  COUNT(DISTINCT staff_id) as unique_staff,
                  SUM(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) as valid_punches,
                  SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) as duplicate_punches
                  FROM attendance_logs
                  WHERE punch_time BETWEEN ? AND ?";
    
    $stats_params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    $stats_result = db_query($stats_sql, $stats_params);
    $stats = !empty($stats_result) ? $stats_result[0] : [
        'total_punches' => 0, 'unique_staff' => 0, 'valid_punches' => 0, 'duplicate_punches' => 0
    ];
    
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'statistics' => $stats,
        'filters' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'staff_id' => $staff_id,
            'device_id' => $device_id,
            'status' => $status,
            'punch_type' => $punch_type
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
