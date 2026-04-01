<?php
/**
 * API: Check for Updates (OTA)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/update_functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $update_info = check_for_updates();
    
    if ($update_info) {
        echo json_encode([
            'status' => true,
            'update_available' => true,
            'current_version' => APP_VERSION,
            'new_version' => $update_info['version'],
            'changelog' => nl2br(htmlspecialchars($update_info['description'])),
            'data' => $update_info
        ]);
    } else {
        echo json_encode([
            'status' => true,
            'update_available' => false,
            'current_version' => APP_VERSION,
            'message' => 'Your system is up to date.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
