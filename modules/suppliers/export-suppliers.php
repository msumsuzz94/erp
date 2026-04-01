<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
if (!is_admin() && !has_role(get_current_user_id(), 'Manager') && !has_role(get_current_user_id(), 'Admin')) {
    redirect_with_message('suppliers-list.php', 'Permission denied', 'error');
}

// Get filter parameters
$search = get_param('search', '');
$status = get_param('status', '');

$sql = "SELECT name, phone, email, address, payment_terms, opening_balance, current_balance, status, created_at FROM suppliers WHERE 1=1";
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
$suppliers = db_query($sql, $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=suppliers_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['Name', 'Phone', 'Email', 'Address', 'Payment Terms', 'Opening Balance', 'Current Balance', 'Status', 'Created At']);

foreach ($suppliers as $supplier) {
    fputcsv($output, [
        $supplier['name'],
        $supplier['phone'],
        $supplier['email'],
        $supplier['address'],
        $supplier['payment_terms'],
        $supplier['opening_balance'],
        $supplier['current_balance'],
        ucfirst($supplier['status']),
        date('M d, Y', strtotime($supplier['created_at']))
    ]);
}

fclose($output);
exit;
