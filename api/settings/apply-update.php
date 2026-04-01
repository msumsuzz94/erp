<?php
/**
 * API: Apply System Update (OTA)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/update_functions.php';

// Extend execution time as updates can be slow
set_time_limit(300);
header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$version = $_POST['version'] ?? '';
$download_url = $_POST['download_url'] ?? '';

if (!$version || !$download_url) {
    echo json_encode(['status' => false, 'message' => 'Missing update parameters']);
    exit;
}

try {
    // Optional: Set system into maintenance mode in DB here
    
    $result = apply_update($version, $download_url);
    
    if ($result['success']) {
        // Log the update
        db_insert('system_updates', [
            'version' => $version,
            'applied_by' => get_current_user_id(),
            'status' => 'success'
        ]);
        
        echo json_encode(['status' => true, 'message' => $result['message']]);
    } else {
        db_insert('system_updates', [
            'version' => $version,
            'applied_by' => get_current_user_id(),
            'status' => 'failed',
            'log' => $result['message']
        ]);
        echo json_encode(['status' => false, 'message' => $result['message']]);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Update Exception: ' . $e->getMessage()]);
}
