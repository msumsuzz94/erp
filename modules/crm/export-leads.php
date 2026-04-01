<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
$user_id = get_current_user_id();
if (!is_admin() && !has_role($user_id, 'Manager') && !has_role($user_id, 'Admin')) {
    redirect_with_message('lead-list.php', 'Permission denied', 'error');
}

$where = "1=1";
$params = [];

if (!is_admin($user_id) && !has_role($user_id, 'Manager') && !has_role($user_id, 'Admin')) {
    $where .= " AND l.collected_by = ?";
    $params[] = $user_id;
}

$sql = "SELECT l.*, c.name as category_name, u.username as staff_name
        FROM leads l
        LEFT JOIN lead_categories c ON l.category_id = c.id
        LEFT JOIN users u ON l.collected_by = u.id
        WHERE $where
        ORDER BY l.created_at DESC";

$leads = db_query($sql, $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=leads_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['Date', 'Organization', 'Contact Person', 'Mobile', 'Email', 'Address', 'Category', 'Status', 'Staff', 'Remarks', 'Latitude', 'Longitude']);

foreach ($leads as $lead) {
    fputcsv($output, [
        date('d M Y', strtotime($lead['created_at'])),
        $lead['organization_name'],
        $lead['contact_person'],
        $lead['mobile'],
        $lead['email'],
        $lead['address'],
        $lead['category_name'],
        ucfirst($lead['status']),
        $lead['staff_name'],
        $lead['remarks'],
        $lead['gps_latitude'],
        $lead['gps_longitude']
    ]);
}

fclose($output);
exit;
