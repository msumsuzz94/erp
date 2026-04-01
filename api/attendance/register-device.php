<?php
/**
 * Device Registration API
 * Register new attendance devices
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require admin authentication for device registration
require_login();

if (!is_admin(get_current_user_id())) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        // Register new device
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['device_name', 'device_type', 'device_sn'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Check if device_sn already exists
        $existing = db_select_one('attendance_devices', ['device_sn' => $input['device_sn']]);
        if ($existing) {
            throw new Exception('Device serial number already registered');
        }
        
        // Generate API key
        $api_key = bin2hex(random_bytes(32));
        
        $device_data = [
            'device_name' => clean_input($input['device_name']),
            'device_type' => $input['device_type'],
            'device_ip' => $input['device_ip'] ?? null,
            'device_port' => $input['device_port'] ?? null,
            'device_sn' => clean_input($input['device_sn']),
            'api_key' => $api_key,
            'location' => clean_input($input['location'] ?? ''),
            'status' => 'active',
            'settings' => json_encode($input['settings'] ?? [])
        ];
        
        $device_id = db_insert('attendance_devices', $device_data);
        
        log_activity(get_current_user_id(), 'device_register', "Registered attendance device: {$input['device_name']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Device registered successfully',
            'device_id' => $device_id,
            'api_key' => $api_key
        ]);
        
    } elseif ($method === 'GET') {
        // Get all devices
        $devices = db_select('attendance_devices', [], '*', 'created_at DESC');
        
        // Add online status indicator
        foreach ($devices as &$device) {
            $last_heartbeat = strtotime($device['last_heartbeat']);
            $now = time();
            $device['is_online'] = ($now - $last_heartbeat) < 300; // Online if heartbeat within 5 minutes
            $device['settings'] = json_decode($device['settings'], true);
        }
        
        echo json_encode([
            'success' => true,
            'devices' => $devices
        ]);
        
    } elseif ($method === 'PUT') {
        // Update device
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['device_id'])) {
            throw new Exception('Device ID required');
        }
        
        $update_data = [];
        $allowed_fields = ['device_name', 'device_ip', 'device_port', 'location', 'status', 'settings'];
        
        foreach ($allowed_fields as $field) {
            if (isset($input[$field])) {
                $update_data[$field] = $field === 'settings' ? json_encode($input[$field]) : clean_input($input[$field]);
            }
        }
        
        db_update('attendance_devices', $update_data, ['id' => $input['device_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Device updated successfully'
        ]);
        
    } elseif ($method === 'DELETE') {
        // Delete device
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['device_id'])) {
            throw new Exception('Device ID required');
        }
        
        db_delete('attendance_devices', ['id' => $input['device_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Device deleted successfully'
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
