<?php
/**
 * Quotation to Invoice Conversion Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$quotation_id = (int)get_param('id');

// Get quotation details
$quotation = db_select_one('quotations', ['id' => $quotation_id]);

if (!$quotation) {
    redirect_with_message('quotations-list.php', 'Quotation not found', 'error');
}

if ($quotation['status'] === 'converted') {
    redirect_with_message('quotations-list.php', 'Quotation has already been converted to an invoice', 'warning');
}

// Get quotation items
$quotation_items = db_select('quotation_items', ['quotation_id' => $quotation_id]);

if (empty($quotation_items)) {
    redirect_with_message('quotations-list.php', 'Quotation has no items to convert', 'error');
}

// Perform conversion in a transaction
db_begin_transaction();

try {
    global $conn;
    
    // 1. Generate new invoice number
    $last_sale = db_query_one("SELECT MAX(id) as last_id FROM sales");
    $invoice_number = generate_invoice_number(null, $last_sale['last_id'] ?? 0);
    
    // 2. Insert into sales table
    $sale_data = [
        'invoice_number' => $invoice_number,
        'customer_id' => $quotation['customer_id'],
        'sale_date' => date('Y-m-d'),
        'total_amount' => $quotation['total_amount'],
        'tax_amount' => $quotation['tax_amount'],
        'discount' => $quotation['discount'],
        'paid_amount' => 0, // Dues by default upon conversion
        'due_amount' => $quotation['total_amount'],
        'payment_status' => 'unpaid',
        'status' => 'completed',
        'notes' => "Converted from Quotation #" . $quotation['quotation_number'] . ". " . $quotation['notes'],
        'created_by' => get_current_user_id()
    ];
    
    $sale_id = db_insert('sales', $sale_data);
    
    if (!$sale_id) {
        throw new Exception("Failed to create sale record from quotation");
    }
    
    // 3. Insert items and update stock
    foreach ($quotation_items as $item) {
        $sale_item_data = [
            'sale_id' => $sale_id,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'tax' => $item['tax'],
            'discount' => $item['discount'],
            'subtotal' => $item['subtotal']
        ];
        
        $sale_item_id = db_insert('sale_items', $sale_item_data);
        
        if (!$sale_item_id) {
            throw new Exception("Failed to create sale item for product ID: " . $item['product_id']);
        }
        
        // Update product stock
        db_query("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
    }
    
    // 4. Update customer ledger if customer exists
    if ($quotation['customer_id']) {
        db_insert('customer_ledger', [
            'customer_id' => $quotation['customer_id'],
            'transaction_type' => 'sale',
            'reference_id' => $sale_id,
            'debit' => $quotation['total_amount'],
            'credit' => 0,
            'description' => "Sale from Quotation #" . $quotation['quotation_number'],
            'date' => date('Y-m-d')
        ]);
        
        // Update customer current balance
        db_query("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?", [$quotation['total_amount'], $quotation['customer_id']]);
    }
    
    // 5. Update quotation status
    db_update('quotations', ['status' => 'converted'], ['id' => $quotation_id]);
    
    db_commit();
    
    log_activity(get_current_user_id(), 'quotation_convert', "Converted Quotation #" . $quotation['quotation_number'] . " to Sale ID: $sale_id");
    
    redirect_with_message("../sales/sale-view.php?id=$sale_id", "Quotation converted to invoice successfully", "success");
    
} catch (Exception $e) {
    db_rollback();
    log_activity(get_current_user_id(), 'quotation_convert_error', "Error converting quotation: " . $e->getMessage());
    redirect_with_message("quotation-view.php?id=$quotation_id", "Error converting quotation: " . $e->getMessage(), "error");
}
?>
