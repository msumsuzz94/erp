<?php
/**
 * Create Sales Return
 * Create return for a specific sale/invoice
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$error = '';
$success = '';

// Handle form submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $sale_id = (int)$_POST['sale_id'];
        $return_date = sanitize_input($_POST['return_date']);
        $reason = sanitize_input($_POST['reason']);
        $refund_method = sanitize_input($_POST['refund_method'] ?? 'balance');
        $products = $_POST['products'] ?? [];
        
        if ($sale_id && $return_date && !empty($products)) {
            try {
                db_begin_transaction();
                
                // Get sale details
                $sale = db_select_one('sales', ['id' => $sale_id]);
                
                if ($sale) {
                    // Calculate total return amount
                    $total_amount = 0;
                    $selected_items_count = 0;
                    foreach ($products as $product) {
                        if (isset($product['selected']) && $product['selected'] == '1') {
                            $quantity = (float)$product['quantity'];
                            $price = (float)$product['price'];
                            $total_amount += $quantity * $price;
                            $selected_items_count++;
                        }
                    }
                    
                    if ($selected_items_count === 0) {
                        throw new Exception("Please select at least one item to return.");
                    }

                    // Insert sales return
                    $return_data = [
                        'sale_id' => $sale_id,
                        'customer_id' => $sale['customer_id'],
                        'return_date' => $return_date,
                        'total_amount' => $total_amount,
                        'reason' => $reason,
                        'status' => 'completed',
                        'created_by' => get_current_user_id(),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $return_id = db_insert('sales_returns', $return_data);
                    if (!$return_id) throw new Exception("Failed to insert into sales_returns");
                    
                    // Insert return items and update stock
                    foreach ($products as $product) {
                        if (isset($product['selected']) && $product['selected'] == '1') {
                            $product_id = (int)$product['product_id'];
                            $quantity = (float)$product['quantity'];
                            $unit_price = (float)$product['price'];
                            $subtotal = $quantity * $unit_price;
                            $return_type = sanitize_input($product['return_type'] ?? 'stock'); // 'stock' or 'rma'
                            
                            // Insert return item
                            $item_data = [
                                'return_id' => $return_id,
                                'product_id' => $product_id,
                                'quantity' => $quantity,
                                'unit_price' => $unit_price,
                                'subtotal' => $subtotal,
                                'return_type' => $return_type,
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                            $return_item_id = db_insert('sale_return_items', $item_data);
                            
                            // Update stock based on return type
                            if ($return_type === 'rma') {
                                // Increase RMA quantity
                                db_query(
                                    "UPDATE products SET rma_quantity = rma_quantity + ? WHERE id = ?",
                                    [$quantity, $product_id]
                                );
                            } else {
                                // Increase regular stock quantity
                                db_query(
                                    "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?",
                                    [$quantity, $product_id]
                                );
                                
                                // Also update warehouse stock if possible
                                // For simplicity, we assume main warehouse (ID: 1) for returns to stock
                                db_query(
                                    "INSERT INTO product_warehouse_stock (product_id, warehouse_id, quantity) 
                                     VALUES (?, 1, ?) 
                                     ON DUPLICATE KEY UPDATE quantity = quantity + ?",
                                     [$product_id, $quantity, $quantity]
                                );
                            }

                            // Handle serial numbers if returned
                            if (!empty($product['selected_serials'])) {
                                foreach ($product['selected_serials'] as $sn) {
                                    $new_status = ($return_type === 'rma') ? 'rma' : 'in_stock';
                                    $new_stock_type = ($return_type === 'rma') ? 'rma' : NULL;

                                    db_query(
                                        "UPDATE product_serials 
                                         SET status = ?, 
                                             serial_status = 'in_stock',
                                             stock_type = ?,
                                             sale_id = NULL, 
                                             sale_item_id = NULL, 
                                             sale_date = NULL 
                                         WHERE serial_number = ? AND product_id = ?",
                                        [$new_status, $new_stock_type, $sn, $product_id]
                                    );

                                    // Store returned serial history
                                    if (isset($return_item_id)) {
                                        db_query(
                                            "INSERT INTO sale_return_item_serials (return_item_id, product_id, serial_number) VALUES (?, ?, ?)",
                                            [$return_item_id, $product_id, $sn]
                                        );
                                    }
                                }
                            }

                            // REDUCE QUANTITY IN sale_items
                            $remaining_return_qty = $quantity;
                            $si_rows = db_query("SELECT * FROM sale_items WHERE sale_id = ? AND product_id = ? ORDER BY id", [$sale_id, $product_id]);
                            foreach ($si_rows as $si) {
                                if ($remaining_return_qty <= 0) break;
                                
                                if ($si['quantity'] <= $remaining_return_qty) {
                                    // Full return of this row
                                    db_delete('sale_items', ['id' => $si['id']]);
                                    $remaining_return_qty -= $si['quantity'];
                                } else {
                                    // Partial return
                                    $new_qty = $si['quantity'] - $remaining_return_qty;
                                    $new_subtotal = $new_qty * $si['unit_price'];
                                    
                                    // Pro-rate tax and discount
                                    $new_tax = ($si['tax'] > 0) ? ($si['tax'] / $si['quantity']) * $new_qty : 0;
                                    $new_discount = ($si['discount'] > 0) ? ($si['discount'] / $si['quantity']) * $new_qty : 0;
                                    
                                    db_update('sale_items', [
                                        'quantity' => $new_qty,
                                        'subtotal' => $new_subtotal,
                                        'tax' => $new_tax,
                                        'discount' => $new_discount
                                    ], ['id' => $si['id']]);
                                    
                                    $remaining_return_qty = 0;
                                }
                            }
                        }
                    }
                    
                    // Update customer balance/due and Sales record
                    if ($sale['customer_id']) {
                        // 1. The Return Credit (Customer's debt reduces)
                        db_query(
                            "UPDATE customers SET current_balance = current_balance - ? WHERE id = ?",
                            [$total_amount, $sale['customer_id']]
                        );
                        
                        // Log Return to customer ledger
                        $ledger_data_return = [
                            'customer_id' => $sale['customer_id'],
                            'transaction_type' => 'return',
                            'reference_id' => $return_id,
                            'credit' => $total_amount,
                            'balance' => db_query_one("SELECT current_balance FROM customers WHERE id = ?", [$sale['customer_id']])['current_balance'],
                            'description' => "Sales Return (Credit) for Invoice #{$sale['invoice_number']}",
                            'date' => $return_date
                        ];
                        db_insert('customer_ledger', $ledger_data_return);

                        // 2. The Refund Debit (If cash/bank is returned to customer)
                        $new_paid = $sale['paid_amount'];
                        if ($refund_method === 'cash' || $refund_method === 'bank') {
                            // Customer receives money back, so their debt increases back to offset the return credit
                            db_query(
                                "UPDATE customers SET current_balance = current_balance + ? WHERE id = ?",
                                [$total_amount, $sale['customer_id']]
                            );

                            $ledger_data_refund = [
                                'customer_id' => $sale['customer_id'],
                                'transaction_type' => 'refund',
                                'reference_id' => $return_id,
                                'debit' => $total_amount,
                                'balance' => db_query_one("SELECT current_balance FROM customers WHERE id = ?", [$sale['customer_id']])['current_balance'],
                                'description' => "Refund Payment for Return #{$return_id} ({$refund_method})",
                                'date' => $return_date
                            ];
                            db_insert('customer_ledger', $ledger_data_refund);

                            // Decrease cash/bank balance
                            $account_table = $refund_method === 'cash' ? 'cash_accounts' : 'bank_accounts';
                            $acc = db_query("SELECT id FROM $account_table WHERE status = 'active' LIMIT 1");
                            $account_id = $acc[0]['id'] ?? 1;

                            db_query("UPDATE $account_table SET current_balance = current_balance - ? WHERE id = ?", [$total_amount, $account_id]);

                            $trans_table = $refund_method === 'cash' ? 'cash_transactions' : 'bank_transactions';
                            db_insert($trans_table, [
                                'account_id' => $account_id,
                                'transaction_type' => 'debit',
                                'amount' => $total_amount,
                                'reference_type' => 'sales_return',
                                'reference_id' => $return_id,
                                'description' => "Refund to Customer for Return #{$return_id}",
                                'transaction_date' => $return_date,
                                'created_by' => get_current_user_id()
                            ]);
                            
                            $new_paid = max(0, $sale['paid_amount'] - $total_amount);
                        }
                        
                        // 3. Update the Sales table to reflect the returned inventory and potential refund
                        $res = db_query_one("SELECT COALESCE(SUM(subtotal), 0) as st, COALESCE(SUM(tax), 0) as tx, COALESCE(SUM(discount), 0) as ds FROM sale_items WHERE sale_id = ?", [$sale_id]);
                        $new_total = $res['st'] + $res['tx'] - $res['ds'];
                        $new_tax = $res['tx'];
                        $new_discount = $res['ds'];

                        $new_due = $new_total - $new_paid;
                        $payment_status = $new_due <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');
                        
                        db_query(
                            "UPDATE sales SET total_amount = ?, tax_amount = ?, discount = ?, paid_amount = ?, due_amount = ?, payment_status = ? WHERE id = ?", 
                            [$new_total, $new_tax, $new_discount, $new_paid, max(0, $new_due), $payment_status, $sale_id]
                        );
                    }
                    
                    db_commit();
                    
                    log_activity(get_current_user_id(), 'sales_return', "Created sales return ID: $return_id");
                    redirect_with_message('sales-return-list.php', 'Sales return created successfully', 'success');
                }
            } catch (Exception $e) {
                db_rollback();
                $error = 'Failed to create return: ' . $e->getMessage();
            }
        } else {
            $error = 'Please fill all required fields and select at least one product';
        }
    } else {
        $error = 'Invalid CSRF token';
    }
}

// Get sale ID from URL
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;

// Get sale details and items
$sale = null;
$sale_items = [];

if ($sale_id) {
    $sale = db_select_one('sales', ['id' => $sale_id]);
    
    if ($sale) {
        // Get sale items with product details
        $sql = "SELECT si.*, p.name as product_name, p.code as product_code, p.has_serial
                FROM sale_items si
                LEFT JOIN products p ON si.product_id = p.id
                WHERE si.sale_id = ?
                ORDER BY si.id";
        $raw_items = db_query($sql, [$sale_id]);
        
        $aggregated_items = [];
        foreach ($raw_items as $item) {
            $pid = $item['product_id'];
            if (isset($aggregated_items[$pid])) {
                $aggregated_items[$pid]['quantity'] += (float)$item['quantity'];
                $aggregated_items[$pid]['subtotal'] += (float)$item['subtotal'];
            } else {
                $aggregated_items[$pid] = $item;
                $aggregated_items[$pid]['serials'] = [];
            }
        }

        // Fetch serial numbers for aggregated items
        foreach ($aggregated_items as &$item) {
            if ($item['has_serial'] === 'Available') {
                $si_ids = [];
                foreach ($raw_items as $ri) {
                    if ($ri['product_id'] == $item['product_id']) {
                        $si_ids[] = $ri['id'];
                    }
                }
                
                if (!empty($si_ids)) {
                    $placeholders = implode(',', array_fill(0, count($si_ids), '?'));
                    $serials = db_query("SELECT id, serial_number FROM product_serials WHERE sale_item_id IN ($placeholders)", $si_ids);
                    $item['serials'] = $serials ?: [];
                }
            }
        }
        $sale_items = array_values($aggregated_items);
        
        // Get customer name
        $customer = db_select_one('customers', ['id' => $sale['customer_id']]);
        $sale['customer_name'] = $customer['name'] ?? 'Walk-in Customer';
    }
}

// Get all sales for dropdown
$sales = db_query("SELECT s.id, s.invoice_number, s.sale_date, c.name as customer_name
                    FROM sales s
                    LEFT JOIN customers c ON s.customer_id = c.id
                    WHERE s.status = 'completed'
                    ORDER BY s.created_at DESC
                    LIMIT 100");

$page_title = 'Create Sales Return';
$page_actions = '<a href="sales-return-list.php" class="btn btn-secondary"><i class="fas fa-list"></i> Returns List</a>';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="returnForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="sale_id" value="<?= $sale_id ?>">
        
        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Select Sale -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Select Invoice</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Invoice <span class="text-danger">*</span></label>
                            <select class="form-control" id="saleSelect" <?= $sale_id ? 'disabled' : '' ?>>
                                <option value="">Select Invoice to Return Items From</option>
                                <?php foreach ($sales as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $sale_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['invoice_number'] ?? '') ?> - <?= htmlspecialchars($s['customer_name'] ?? '') ?> (<?= $s['sale_date'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$sale_id): ?>
                                <small class="text-muted">Select an invoice to see items available for return</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($sale): ?>
                <!-- Return Items -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Items to Return</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="5%"><input type="checkbox" id="selectAll"></th>
                                        <th width="25%">Product</th>
                                        <th width="10%">Qty</th>
                                        <th width="15%">Return Qty</th>
                                        <th width="20%">Return To</th>
                                        <th width="12%">Price</th>
                                        <th width="13%">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sale_items as $index => $item): ?>
                                    <tr class="item-row">
                                        <td>
                                            <input type="checkbox" class="item-checkbox" name="products[<?= $index ?>][selected]" value="1">
                                            <input type="hidden" name="products[<?= $index ?>][product_id]" value="<?= $item['product_id'] ?>">
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                            <small class="text-muted">Code: <?= htmlspecialchars($item['product_code']) ?></small>
                                        </td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>
                                            <?php if ($item['has_serial'] === 'Available' && !empty($item['serials'])): ?>
                                                <select name="products[<?= $index ?>][selected_serials][]" 
                                                        class="form-control return-serials select2-serials" 
                                                        multiple="multiple" disabled>
                                                    <?php foreach ($item['serials'] as $sn): ?>
                                                        <option value="<?= htmlspecialchars($sn['serial_number']) ?>"><?= htmlspecialchars($sn['serial_number']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" name="products[<?= $index ?>][quantity]" class="return-qty" value="0">
                                            <?php else: ?>
                                                <input type="number" name="products[<?= $index ?>][quantity]" 
                                                       class="form-control return-qty" 
                                                       step="0.01" min="0.01" max="<?= $item['quantity'] ?>" 
                                                       value="<?= $item['quantity'] ?>" disabled>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select name="products[<?= $index ?>][return_type]" class="form-control return-type" disabled>
                                                <option value="stock">Regular Stock</option>
                                                <option value="rma">RMA pool</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="products[<?= $index ?>][price]" 
                                                   class="form-control unit-price" 
                                                   step="0.01" min="0" 
                                                   value="<?= $item['unit_price'] ?>" disabled>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control item-subtotal" 
                                                   readonly value="<?= number_format($item['subtotal'], 2) ?>">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Column -->
            <div class="col-md-4">
                <?php if ($sale): ?>
                <!-- Sale Info -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Invoice Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Invoice #:</strong> <?= htmlspecialchars($sale['invoice_number']) ?></p>
                        <p><strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?></p>
                        <p><strong>Date:</strong> <?= format_date($sale['sale_date']) ?></p>
                        <p><strong>Total:</strong> <?= format_currency($sale['total_amount']) ?></p>
                        <p><strong>Due:</strong> <?= format_currency($sale['due_amount']) ?></p>
                    </div>
                </div>
                
                <!-- Return Details -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Return Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>Return Date <span class="text-danger">*</span></label>
                            <input type="date" name="return_date" class="form-control" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label>Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" 
                                      placeholder="Enter reason for return" required></textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label>Refund Method <span class="text-danger">*</span></label>
                            <select name="refund_method" class="form-control" required>
                                <option value="balance">Adjust with Customer Balance</option>
                                <option value="cash">Refund via Petty Cash</option>
                                <option value="bank">Refund via Bank</option>
                            </select>
                            <small class="text-muted">If cash/bank is selected, money will be deducted from company accounts and returned to the customer.</small>
                        </div>
                        
                        <hr>
                        
                        <div class="form-group mb-3">
                            <label><strong>Total Return Amount</strong></label>
                            <input type="text" id="returnTotal" class="form-control font-weight-bold text-primary" 
                                   readonly value="0.00" style="font-size: 1.2rem;">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <i class="fas fa-undo"></i> Create Return
                            </button>
                            <a href="sales-return-list.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize Select2 for searchable invoice dropdown
    $('#saleSelect').select2({
        placeholder: 'Search by Invoice Number or Customer Name',
        allowClear: true,
        width: '100%'
    });

    // Initialize Select2 for serial numbers
    $('.select2-serials').select2({
        placeholder: 'Select Serials',
        width: '100%'
    });
    
    // Redirect to page with sale ID when selected
    $('#saleSelect').change(function() {
        const saleId = $(this).val();
        if (saleId) {
            window.location.href = 'sales-return-create.php?sale_id=' + saleId;
        }
    });

    // Select all checkbox
    $('#selectAll').change(function() {
        $('.item-checkbox').prop('checked', this.checked);
        $('.item-checkbox').each(function() {
            toggleRowInputs($(this).closest('tr'), this.checked);
        });
        calculateTotal();
    });

    // Toggle row inputs based on checkbox
    function toggleRowInputs(row, enabled) {
        row.find('.return-qty, .unit-price, .return-type, .return-serials').prop('disabled', !enabled);
        if (!enabled) {
            row.find('.item-subtotal').val('0.00');
            // Clear serial selection if disabled
            if (row.find('.select2-serials').length) {
                row.find('.select2-serials').val(null).trigger('change');
            }
        } else {
            calculateRowSubtotal(row);
        }
    }

    // Individual checkbox toggle
    $('.item-checkbox').change(function() {
        toggleRowInputs($(this).closest('tr'), this.checked);
        calculateTotal();
    });

    // Recalculate when serials are selected
    $('.select2-serials').on('change', function() {
        const row = $(this).closest('tr');
        const selectedCount = $(this).val() ? $(this).val().length : 0;
        row.find('.return-qty').val(selectedCount);
        calculateRowSubtotal(row);
        calculateTotal();
    });

    // Calculate row subtotal
    function calculateRowSubtotal(row) {
        const qty = parseFloat(row.find('.return-qty').val()) || 0;
        const price = parseFloat(row.find('.unit-price').val()) || 0;
        const subtotal = qty * price;
        row.find('.item-subtotal').val(subtotal.toFixed(2));
    }

    // Calculate total
    function calculateTotal() {
        let total = 0;
        let hasSelected = false;
        
        $('.item-row').each(function() {
            const checkbox = $(this).find('.item-checkbox');
            if (checkbox.is(':checked')) {
                hasSelected = true;
                const qty = parseFloat($(this).find('.return-qty').val()) || 0;
                const price = parseFloat($(this).find('.unit-price').val()) || 0;
                total += qty * price;
            }
        });
        
        $('#returnTotal').val(total.toFixed(2));
        $('#submitBtn').prop('disabled', !hasSelected);
    }

    // Recalculate on input change
    $(document).on('input', '.return-qty, .unit-price', function() {
        calculateRowSubtotal($(this).closest('tr'));
        calculateTotal();
    });

    // Initial calculation
    calculateTotal();
});
</script>
