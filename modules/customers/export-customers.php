<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
if (!is_admin() && !has_role(get_current_user_id(), 'Manager') && !has_role(get_current_user_id(), 'Admin')) {
    redirect_with_message('customers-list.php', 'Permission denied', 'error');
}

// Get filter parameters
$search = get_param('search', '');
$status = get_param('status', '');

$sql = "SELECT name, phone, email, address, opening_balance, current_balance, status, created_at FROM customers WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY created_at DESC";
$customers = db_query($sql, $params);

// Generate CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=customers_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
// UTF-8 BOM for Excel
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Write headers
fputcsv($output, ['Name', 'Phone', 'Email', 'Address', 'Opening Balance', 'Current Balance', 'Status', 'Created At']);

foreach ($customers as $customer) {
    fputcsv($output, [
        $customer['name'],
        $customer['phone'],
        $customer['email'],
        $customer['address'],
        $customer['opening_balance'],
        $customer['current_balance'],
        ucfirst($customer['status']),
        date('M d, Y', strtotime($customer['created_at']))
    ]);
}

fclose($output);
exit;
