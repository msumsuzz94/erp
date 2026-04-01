<?php
/**
 * API: SMS Bridge - Register Phone
 * Called by the phone after scanning QR code
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';

$input = json_decode(file_get_contents('php://input'), true);
$session = $input['session'] ?? '';
$device = $input['device'] ?? '';
$phone_model = $input['phone_model'] ?? 'Unknown';

if (empty($session)) {
    echo json_encode(['status' => false, 'message' => 'Missing session']);
    exit;
}

// Store session in file-based storage (simple approach, no extra DB table needed)
$session_dir = __DIR__ . '/../../storage/sms_sessions';
if (!is_dir($session_dir)) {
    mkdir($session_dir, 0755, true);
}

$session_file = $session_dir . '/' . preg_replace('/[^a-f0-9]/', '', $session) . '.json';
file_put_contents($session_file, json_encode([
    'connected' => true,
    'phone_model' => $phone_model,
    'device' => $device,
    'connected_at' => date('Y-m-d H:i:s'),
    'last_poll' => date('Y-m-d H:i:s'),
    'tasks' => []
]));

echo json_encode(['status' => true, 'message' => 'Phone registered']);
