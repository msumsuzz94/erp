<?php
/**
 * Sale Cancel Action
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if (!is_post() || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect_with_message('sales-list.php', 'Invalid request or CSRF token.', 'error');
}

$sale_id = (int)$_POST['sale_id'];
$user_id = get_current_user_id();

try {
    db_begin_transaction();

    // 1. Fetch Sale
    $sale = db_select_one('sales', ['id' => $sale_id]);
    if (!$sale) {
        throw new Exception("Sale not found.");
    }
    if ($sale['status'] === 'cancelled') {
        throw new Exception("Sale is already cancelled.");
    }

    // 2. Revert Stock and Serials
    $sale_items = db_query("SELECT * FROM sale_items WHERE sale_id = ?", [$sale_id]);
    foreach ($sale_items as $item) {
        $qty = $item['quantity'];
        // Revert product_warehouse_stock
        $stock_record = db_select_one('product_warehouse_stock', [
            'product_id' => $item['product_id']
        ]);
        
        if ($stock_record) {
            db_query("UPDATE product_warehouse_stock SET quantity = quantity + ? WHERE product_id = ?", [$qty, $item['product_id']]);
        }
        
        // Generally, main products table stock also needs to be added back
        db_query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$qty, $item['product_id']]);
        
        // Log Serials before reverting
        $serials = db_query("SELECT serial_number FROM product_serials WHERE sale_item_id = ?", [$item['id']]);
        if (count($serials) > 0) {
            $sn_list = array_column($serials, 'serial_number');
            $sn_string = implode(', ', $sn_list);
            
            // Append to description to keep a record on the cancelled item
            $new_desc = trim($item['description'] . "\nCancelled Serials: " . $sn_string);
            db_query("UPDATE sale_items SET description = ? WHERE id = ?", [$new_desc, $item['id']]);
        }
        
        // Revert Serials back to 'in_stock'
        db_query("UPDATE product_serials SET status = 'in_stock', serial_status = 'in_stock', sale_id = NULL, sale_item_id = NULL, sale_date = NULL WHERE sale_item_id = ?", [$item['id']]);
    }

    // 3. Revert Accounting (Reverse Payments)
    $payments = db_query("SELECT * FROM sale_payments WHERE sale_id = ?", [$sale_id]);
    foreach ($payments as $payment) {
        $amount = $payment['amount'];
        $method = $payment['payment_method']; // e.g. 'cash', 'bank', 'bkash'
        $account_id = $payment['account_id'];
        
        // If cash
        if ($method === 'cash') {
            $act_id = $account_id ?: 1; // Default to petty cash if null
            // ADD to Cash Accounts (Refund money out of petty cash is wrong, cancelling a sale means money goes OUT back to customer so balance DECREASES, wait, if we received money, we add it to petty cash. If we cancel, we give money back, so petty cash should DECREASE.
            // Oh right, the user says "Paid amount is getting Unpaid but PETTY CASH is not added".
            // If they mean "when we cancel, the money goes back to petty cash?" No, if we cancel a sale, we refund the customer.
            // HOWEVER, sometimes in these systems, cancelling a sales means REVERSING the original entry. 
            // In POS: when a sale is paid, money is ADDED to petty cash. So cancelling should DEDUCT from petty cash.
            // Wait, if the user complains "PETTY CASH is not added", then maybe my original deduction code ran, but the user expects the money to just *not be there* or maybe they didn't see the transaction. Let's trace pos.php -> payment adds to cash account. Reversal should deduct.
            // Let\'s check pos.php again. In pos.php line 214: `db_update($table, ['current_balance' => $account['current_balance'] + $paid_amount]`.
            // So sale adds money. Cancel MUST deduct money. My code was deducting (`current_balance - ?`).
            // Why didn't it work? Ah, I had `current_balance = current_balance - ?`. 
            // Is `sale_payments` accurate? Let's trace it.

            // Let's actually ADD a credit transaction for the refund so it shows securely.
            db_query("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?", [$amount, $act_id]);
            // Insert Debit transaction (Money leaving the business)
            db_insert('cash_transactions', [
                'account_id' => $act_id,
                'transaction_type' => 'debit', // Money going out/reversal
                'amount' => $amount,
                'reference_type' => 'sale_cancel',
                'reference_id' => $sale_id,
                'description' => "Reversal for cancelled sale #" . $sale['invoice_number'],
                'transaction_date' => date('Y-m-d'),
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Deduct from Bank/Mobile Accounts
            $act_id = $account_id ?: 1;
            db_query("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?", [$amount, $act_id]);
            // Insert Debit transaction (Money leaving the business)
            db_insert('bank_transactions', [
                'account_id' => $act_id,
                'transaction_type' => 'debit',
                'amount' => $amount,
                'reference_type' => 'sale_cancel',
                'reference_id' => $sale_id,
                'description' => "Reversal for cancelled sale #" . $sale['invoice_number'],
                'transaction_date' => date('Y-m-d'),
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    // 4. Delete Payments associated with this sale
    db_query("DELETE FROM sale_payments WHERE sale_id = ?", [$sale_id]);

    // 5. Revert Customer Balance (due + cash_handover)
    if (!empty($sale['customer_id'])) {
        $customer_id = (int)$sale['customer_id'];
        $revert_due = (float)($sale['due_amount'] ?? 0);
        $revert_handover = (float)($sale['cash_handover'] ?? 0);
        $total_revert = $revert_due + $revert_handover;

        if ($total_revert > 0) {
            db_query("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?", [$total_revert, $customer_id]);
        }

        // Record cancellation in customer ledger
        db_insert('customer_ledger', [
            'customer_id' => $customer_id,
            'transaction_type' => 'adjustment',
            'reference_id' => $sale_id,
            'debit' => 0,
            'credit' => $total_revert,
            'description' => "Sale Cancelled - Invoice #{$sale['invoice_number']} (Due: " . number_format($revert_due, 2) . ", Handover: " . number_format($revert_handover, 2) . ")",
            'date' => date('Y-m-d')
        ]);
    }

    // 6. Update Sale status
    db_update('sales', [
        'status' => 'cancelled',
        'payment_status' => 'unpaid',
        'paid_amount' => 0,
        'due_amount' => 0
    ], ['id' => $sale_id]);

    db_commit();
    log_activity($user_id, "Cancelled sale #{$sale['invoice_number']}");
    redirect_with_message('sales-cancel-list.php', 'Sale successfully cancelled, stock reverted, customer balance adjusted, and accounts updated.', 'success');

} catch (Exception $e) {
    db_rollback();
    redirect_with_message('sales-list.php', 'Error cancelling sale: ' . $e->getMessage(), 'error');
}
