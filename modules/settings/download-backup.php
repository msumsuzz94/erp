<?php
/**
 * Backup Download Script
 * Securely download backup files
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$backup_id = (int)($_GET['id'] ?? 0);

if ($backup_id <= 0) {
    die('Invalid backup ID');
}

$backup = db_select_one('backup_history', ['id' => $backup_id]);

if (!$backup) {
    die('Backup not found');
}

if (!file_exists($backup['file_path'])) {
    die('Backup file  not found on disk');
}

// Log download
log_activity(get_current_user_id(), 'download_backup', "Downloaded backup: {$backup['backup_name']}");

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $backup['backup_name'] . '"');
header('Content-Length: ' . filesize($backup['file_path']));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Output file
readfile($backup['file_path']);
exit;
?>
