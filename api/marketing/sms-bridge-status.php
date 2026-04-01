<?php
/**
 * API: SMS Bridge - Check Status
 * Called by the ERP web UI to check if phone is connected
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';

$session = $_GET['session'] ?? '';
if (empty($session)) {
    echo json_encode(['connected' => false]);
    exit;
}

$session_file = __DIR__ . '/../../storage/sms_sessions/' . preg_replace('/[^a-f0-9]/', '', $session) . '.json';

if (file_exists($session_file)) {
    $data = json_decode(file_get_contents($session_file), true);
    
    // Check if phone has polled in the last 30 seconds
    $last_poll = strtotime($data['last_poll'] ?? '2000-01-01');
    $is_active = (time() - $last_poll) < 30;
    
    echo json_encode([
        'connected' => $data['connected'] && $is_active,
        'phone_model' => $data['phone_model'] ?? 'Phone',
        'connected_at' => $data['connected_at'] ?? null
    ]);
} else {
    echo json_encode(['connected' => false]);
}
