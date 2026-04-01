<?php
/**
 * Backup Creation API Endpoint
 * Handles AJAX manual backup creation requests
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/backup_functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

$compress = isset($_POST['compress']);

// Create backup
try {
    $result = create_database_backup([
        'compress' => $compress,
        'type' => 'manual',
        'destinations' => ['local']
    ]);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => "Backup '{$result['backup_name']}' created successfully (" . format_bytes($result['file_size']) . ")."
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error occurred during backup creation.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
