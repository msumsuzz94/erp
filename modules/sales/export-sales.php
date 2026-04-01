<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
if (!is_admin() && !has_role(get_current_user_id(), 'Manager') && !has_role(get_current_user_id(), 'Admin')) {
    redirect_with_message('sales-list.php', 'Permission denied', 'error');
}

$search = get_param('search', '');
$customer_id = get_param('customer_id', '');
$sold_by = get_param('sold_by', '');
$status = get_param('status', '');
$payment_status = get_param('payment_status', '');
$from_date = get_param('from_date', '');
$to_date = get_param('to_date', '');

$sql = "SELECT s.invoice_number, s.sale_date, c.name as customer_name, s.sold_by, s.total_amount, s.paid_amount, s.due_amount, s.payment_status, s.status, s.created_at
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (s.invoice_number LIKE ? OR c.name LIKE ? OR s.sold_by LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($customer_id)) {
    $sql .= " AND s.customer_id = ?";
    $params[] = $customer_id;
}

if (!empty($sold_by)) {
    $sql .= " AND s.sold_by = ?";
    $params[] = $sold_by;
}

if (!empty($status)) {
    $sql .= " AND s.status = ?";
    $params[] = $status;
}

if (!empty($payment_status)) {
    $sql .= " AND s.payment_status = ?";
    $params[] = $payment_status;
}

if (!empty($from_date)) {
    $sql .= " AND s.sale_date >= ?";
    $params[] = $from_date;
}

if (!empty($to_date)) {
    $sql .= " AND s.sale_date <= ?";
    $params[] = $to_date;
}

$sql .= " ORDER BY s.created_at DESC";
$sales = db_query($sql, $params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sales_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['Invoice Number', 'Sale Date', 'Customer', 'Sales By', 'Total Amount', 'Paid Amount', 'Due Amount', 'Payment Status', 'Status', 'Created At']);

foreach ($sales as $sale) {
    fputcsv($output, [
        $sale['invoice_number'],
        $sale['sale_date'],
        $sale['customer_name'] ?? 'Walk-in',
        $sale['sold_by'] ?? 'N/A',
        $sale['total_amount'],
        $sale['paid_amount'],
        $sale['due_amount'],
        ucfirst($sale['payment_status'] ?? 'unpaid'),
        ucfirst($sale['status'] ?? 'pending'),
        date('M d, Y', strtotime($sale['created_at']))
    ]);
}

fclose($output);
exit;
