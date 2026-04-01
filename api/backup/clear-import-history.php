<?php
/**
 * API: Clear Import History
 * Permitted only for Super Admin (Role 1) with Password Verification
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Init session and check login
init_session();
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// 1. Verify CSRF
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

// 2. Check if user is Super Admin (Role 1)
$user_id = get_current_user_id();
$user = db_select_one('users', ['id' => $user_id]);

if (!$user || $user['role_id'] != 1) {
    echo json_encode(['success' => false, 'error' => 'Permission denied. Only Super Admin can clear history.']);
    exit;
}

// 3. Verify Password
$password = $_POST['password'] ?? '';
if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Password is required']);
    exit;
}

// Fetch full user data including hash for verification
$sql = "SELECT password_hash FROM users WHERE id = ? LIMIT 1";
$full_user = db_query_one($sql, [$user_id]);

if (!$full_user || !password_verify($password, $full_user['password_hash'])) {
    echo json_encode(['success' => false, 'error' => 'Incorrect password. Verification failed.']);
    exit;
}

// 4. Perform Cleanup
try {
    // We use DELETE instead of TRUNCATE to avoid DDL implicit commit issues in some setups
    $sql_delete = "DELETE FROM import_history";
    db_query($sql_delete);
    
    log_activity($user_id, 'clear_import_history', 'Import history cleared by Super Admin after password verification');
    
    echo json_encode([
        'success' => true,
        'message' => 'Import history has been cleared successfully.'
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
