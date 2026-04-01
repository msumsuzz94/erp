<?php
/**
 * Backup Import API Endpoint
 * Handles AJAX backup import requests
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

if (!isset($_FILES['backup_file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['backup_file'];
$import_mode = $_POST['import_mode'] ?? 'skip_duplicates';

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'File upload error']);
    exit;
}

// Check file extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['sql', 'zip'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only .sql and .zip allowed.']);
    exit;
}

// Check file size (max 100MB)
if ($file['size'] > 100 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum 100MB.']);
    exit;
}

// Move uploaded file to temp location
$upload_dir = get_backup_directory() . 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$temp_file = $upload_dir . time() . '_' . $file['name'];
move_uploaded_file($file['tmp_name'], $temp_file);

// Import backup
$result = import_database_backup($temp_file, $import_mode);

// Clean up temp file
if (file_exists($temp_file)) {
    unlink($temp_file);
}

echo json_encode($result);
?>
