<?php
/**
 * Ajax API: Get Customer Balance
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$customer_id = (int)($_GET['id'] ?? 0);

if ($customer_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Invalid customer ID']);
    exit;
}

$customer = db_query_one("SELECT address FROM customers WHERE id = ?", [$customer_id]);
$ledger = db_query_one("SELECT (SUM(debit) - SUM(credit)) as balance FROM customer_ledger WHERE customer_id = ?", [$customer_id]);

if ($customer) {
    echo json_encode([
        'status' => true,
        'balance' => (float)($ledger['balance'] ?? 0),
        'address' => $customer['address']
    ]);
} else {
    echo json_encode(['status' => false, 'message' => 'Customer not found']);
}
