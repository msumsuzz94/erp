<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
if (!is_admin() && !has_role(get_current_user_id(), 'Manager') && !has_role(get_current_user_id(), 'Admin')) {
    redirect_with_message('units-list.php', 'Permission denied', 'error');
}

$sql = "SELECT name, short_name, created_at FROM units ORDER BY name ASC";
$units = db_query($sql);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=units_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['Name', 'Short Name', 'Created At']);

foreach ($units as $unit) {
    fputcsv($output, [
        $unit['name'],
        $unit['short_name'],
        date('M d, Y', strtotime($unit['created_at']))
    ]);
}

fclose($output);
exit;
