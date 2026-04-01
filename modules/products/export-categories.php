<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
if (!is_admin() && !has_role(get_current_user_id(), 'Manager') && !has_role(get_current_user_id(), 'Admin')) {
    redirect_with_message('categories-list.php', 'Permission denied', 'error');
}

$search = get_param('search', '');

$sql = "SELECT name, description, created_at FROM categories WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY name ASC";
$categories = db_query($sql, $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=categories_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['Name', 'Description', 'Created At']);

foreach ($categories as $category) {
    fputcsv($output, [
        $category['name'],
        $category['description'],
        date('M d, Y', strtotime($category['created_at']))
    ]);
}

fclose($output);
exit;
