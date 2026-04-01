<?php
/**
 * AJAX Endpoint - Get Role Permissions
 * Returns menu permissions for a specific role
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/menu_functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get role_id from request
$role_id = isset($_GET['role_id']) ? (int) $_GET['role_id'] : 0;

if ($role_id <= 0) {
    echo json_encode(['error' => 'Invalid role ID']);
    exit;
}

try {
    // Get all menu items
    $all_menus = get_all_menu_items();

    // Get permissions for this role
    $permitted_ids = get_role_menu_permissions($role_id);

    // Return data
    echo json_encode([
        'success' => true,
        'menus' => $all_menus,
        'permissions' => $permitted_ids
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => 'Failed to fetch permissions: ' . $e->getMessage()
    ]);
}
?>
