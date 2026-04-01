<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';

$sql = "SELECT pp.*, p.invoice_number, p.total_amount as purchase_total 
        FROM purchase_payments pp
        JOIN purchases p ON pp.purchase_id = p.id
        WHERE pp.payment_method = 'cash'";
$payments = db_query($sql);

echo "CASH PURCHASE PAYMENTS:\n";
foreach ($payments as $p) {
    echo "ID: {$p['id']} | Purchase: {$p['purchase_id']} ({$p['invoice_number']}) | Amount: {$p['amount']} | Date: {$p['payment_date']} | Purchase Total: {$p['purchase_total']}\n";
}

$sql_all_purchases = "SELECT id, invoice_number, total_amount, paid_amount, due_amount FROM purchases";
$purchases = db_query($sql_all_purchases);
echo "\nALL PURCHASES:\n";
foreach ($purchases as $p) {
    echo "ID: {$p['id']} | Inv: {$p['invoice_number']} | Total: {$p['total_amount']} | Paid: {$p['paid_amount']} | Due: {$p['due_amount']}\n";
}
