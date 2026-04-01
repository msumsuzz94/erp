<?php
/**
 * API: SMS Bridge - Send Tasks
 * Adds SMS tasks to a specific session's queue for the phone to pick up
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login(); // Ensure user is logged in

$data = json_decode(file_get_contents('php://input'), true);
$session = $data['session'] ?? '';
$tasks = $data['tasks'] ?? [];

if (empty($session) || empty($tasks)) {
    echo json_encode(['status' => false, 'error' => 'Invalid request']);
    exit;
}

$session_file = __DIR__ . '/../../storage/sms_sessions/' . preg_replace('/[^a-f0-9]/', '', $session) . '.json';

if (file_exists($session_file)) {
    $session_data = json_decode(file_get_contents($session_file), true);
    
    // Ensure tasks array exists
    if (!isset($session_data['tasks'])) {
        $session_data['tasks'] = [];
    }
    
    // Add new tasks to the queue
    foreach ($tasks as $task) {
        $session_data['tasks'][] = [
            'phone' => $task['phone'],
            'message' => $task['message'],
            'added_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Save updated session data
    if (file_put_contents($session_file, json_encode($session_data))) {
        echo json_encode(['status' => true, 'queued' => count($tasks)]);
    } else {
        echo json_encode(['status' => false, 'error' => 'Failed to write to session file']);
    }
} else {
    echo json_encode(['status' => false, 'error' => 'Session not found. Phone may be disconnected.']);
}
