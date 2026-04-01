<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';

echo "PURCHASES SCHEMA:\n";
$cols = db_query("SHOW COLUMNS FROM purchases");
foreach ($cols as $c) echo " - {$c['Field']} ({$c['Type']})\n";

echo "\nPURCHASE PAYMENTS SCHEMA:\n";
$cols = db_query("SHOW COLUMNS FROM purchase_payments");
foreach ($cols as $c) echo " - {$c['Field']} ({$c['Type']})\n";

// Get all purchase payments
$payments = db_query("SELECT * FROM purchase_payments");
echo "\nALL PURCHASE PAYMENTS:\n";
foreach ($payments as $p) {
    echo "ID: {$p['id']} | PurchaseID: {$p['purchase_id']} | Method: {$p['payment_method']} | Amount: {$p['amount']} | Date: {$p['payment_date']}\n";
}

// Get all purchases
$purchases = db_query("SELECT * FROM purchases");
echo "\nALL PURCHASES:\n";
foreach ($purchases as $p) {
    $inv = $p['invoice_no'] ?? $p['purchase_no'] ?? $p['reference_no'] ?? 'N/A';
    echo "ID: {$p['id']} | Inv: $inv | Total: {$p['total_amount']} | Paid: {$p['paid_amount']} | Status: {$p['status']}\n";
}
