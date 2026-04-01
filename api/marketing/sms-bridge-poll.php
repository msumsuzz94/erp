<?php
/**
 * API: SMS Bridge - Poll for SMS Tasks
 * Called by the phone to check if there are SMS messages to send
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/config.php';

$session = $_GET['session'] ?? '';
if (empty($session)) {
    echo json_encode(['tasks' => []]);
    exit;
}

$session_file = __DIR__ . '/../../storage/sms_sessions/' . preg_replace('/[^a-f0-9]/', '', $session) . '.json';

if (file_exists($session_file)) {
    $data = json_decode(file_get_contents($session_file), true);
    
    // Update last poll time
    $data['last_poll'] = date('Y-m-d H:i:s');
    
    // Get pending tasks and clear them
    $tasks = $data['tasks'] ?? [];
    $data['tasks'] = [];
    
    file_put_contents($session_file, json_encode($data));
    
    echo json_encode(['tasks' => $tasks]);
} else {
    echo json_encode(['tasks' => []]);
}
