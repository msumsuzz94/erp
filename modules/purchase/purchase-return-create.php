<?php
/**
 * Create Purchase Return
 * Create return for a specific purchase
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
        $purchase_id = (int)$_POST['purchase_id'];
        $return_date = sanitize_input($_POST['return_date']);
        $reason = sanitize_input($_POST['reason']);
        $products = $_POST['products'] ?? [];
        
        if ($purchase_id && $return_date && !empty($products)) {
            try {
                db_begin_transaction();
                
                // Get purchase details
                $purchase = db_select_one('purchases', ['id' => $purchase_id]);
                
                if ($purchase) {
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

                    // Insert purchase return
                    $return_data = [
                        'purchase_id' => $purchase_id,
                        'supplier_id' => $purchase['supplier_id'],
                        'return_date' => $return_date,
                        'total_amount' => $total_amount,
                        'reason' => $reason,
                        'status' => 'completed',
                        'created_by' => get_current_user_id(),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $return_id = db_insert('purchase_returns', $return_data);
                    if (!$return_id) throw new Exception("Failed to insert into purchase_returns");
                    
                    // Insert return items and update stock
                    foreach ($products as $product) {
                        if (isset($product['selected']) && $product['selected'] == '1') {
                            $product_id = (int)$product['product_id'];
                            $quantity = (float)$product['quantity'];
                            $unit_price = (float)$product['price'];
                            $subtotal = $quantity * $unit_price;
                            $selected_serials = $product['serials'] ?? [];
                            
                            if (!empty($selected_serials)) {
                                // For serial items, insert row per serial
                                foreach ($selected_serials as $serial_id) {
                                    $item_data = [
                                        'return_id' => $return_id,
                                        'product_id' => $product_id,
                                        'serial_id' => (int)$serial_id,
                                        'quantity' => 1,
                                        'unit_price' => $unit_price,
                                        'subtotal' => $unit_price,
                                        'created_at' => date('Y-m-d H:i:s')
                                    ];
                                    db_insert('purchase_return_items', $item_data);
                                    
                                    // Update serial status
                                    db_update('product_serials', ['status' => 'returned'], ['id' => $serial_id]);
                                }
                            } else {
                                // Insert return item (non-serial or batch)
                                $item_data = [
                                    'return_id' => $return_id,
                                    'product_id' => $product_id,
                                    'quantity' => $quantity,
                                    'unit_price' => $unit_price,
                                    'subtotal' => $subtotal,
                                    'created_at' => date('Y-m-d H:i:s')
                                ];
                                db_insert('purchase_return_items', $item_data);
                            }
                            
                            // Reduce product stock
                            db_query(
                                "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
                                [$quantity, $product_id]
                            );
                            
                            // Also update warehouse stock if possible
                            // Assuming main warehouse (ID: 1) for returns to supplier
                            db_query(
                                "UPDATE product_warehouse_stock SET quantity = quantity - ? 
                                 WHERE product_id = ? AND warehouse_id = 1",
                                [$quantity, $product_id]
                            );
                        }
                    }
                    
                    // Adjust original Purchase record
                    $new_purchase_total = $purchase['total_amount'] - $total_amount;
                    $new_due_amount = $new_purchase_total - $purchase['paid_amount'];
                    
                    $payment_status = 'unpaid';
                    if ($new_due_amount <= 0) {
                        $payment_status = 'paid';
                    } elseif ($purchase['paid_amount'] > 0) {
                        $payment_status = 'partial';
                    }
                    
                    db_query(
                        "UPDATE purchases SET total_amount = ?, due_amount = ?, payment_status = ? WHERE id = ?",
                        [$new_purchase_total, $new_due_amount, $payment_status, $purchase_id]
                    );
                    
                    // Update supplier balance - Return amount deducts from what we owe them (Payable)
                    db_query(
                        "UPDATE suppliers SET current_balance = current_balance - ? WHERE id = ?",
                        [$total_amount, $purchase['supplier_id']]
                    );
                    
                    // Get new balance for logging
                    $new_balance = db_query_one("SELECT current_balance FROM suppliers WHERE id = ?", [$purchase['supplier_id']])['current_balance'];
                    
                    // Log to supplier ledger
                    // Credit = Our payable decreases (we owe them less)
                    $ledger_data = [
                        'supplier_id' => $purchase['supplier_id'],
                        'transaction_type' => 'return',
                        'reference_id' => $return_id,
                        'debit' => 0,
                        'credit' => $total_amount, // Return amount deducts from balance
                        'balance' => $new_balance,
                        'description' => "Purchase Return for Purchase #{$purchase['purchase_number']}",
                        'date' => $return_date
                    ];
                    db_insert('supplier_ledger', $ledger_data);
                    
                    db_commit();
                    
                    log_activity(get_current_user_id(), 'purchase_return', "Created purchase return ID: $return_id");
                    redirect_with_message('purchase-return-list.php', 'Purchase return created successfully', 'success');
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

// Get purchase ID from URL
$purchase_id = isset($_GET['purchase_id']) ? (int)$_GET['purchase_id'] : 0;

// Get purchase details and items
$purchase = null;
$purchase_items = [];

if ($purchase_id) {
    $purchase = db_select_one('purchases', ['id' => $purchase_id]);
    
    if ($purchase) {
        // Get purchase items with product details
        $sql = "SELECT pi.*, p.name as product_name, p.code as product_code, p.has_serial
                FROM purchase_items pi
                LEFT JOIN products p ON pi.product_id = p.id
                WHERE pi.purchase_id = ?
                ORDER BY pi.id";
        $purchase_items = db_query($sql, [$purchase_id]);
        
        // Enhance items with serials if they have serial tracking
        foreach ($purchase_items as &$item) {
            if ($item['has_serial']) {
                $item['available_serials'] = db_query(
                    "SELECT id, serial_number, imei FROM product_serials 
                     WHERE product_id = ? AND purchase_id = ? AND status = 'in_stock'",
                    [$item['product_id'], $purchase_id]
                );
            } else {
                $item['available_serials'] = [];
            }
        }
        
        // Get supplier name
        $supplier = db_select_one('suppliers', ['id' => $purchase['supplier_id']]);
        $purchase['supplier_name'] = $supplier['name'] ?? 'Unknown';
    }
}

// Get all purchases for dropdown
$purchases = db_query("SELECT p.id, p.purchase_number, p.purchase_date, s.name as supplier_name
                       FROM purchases p
                       LEFT JOIN suppliers s ON p.supplier_id = s.id
                       WHERE p.status = 'completed'
                       ORDER BY p.created_at DESC
                       LIMIT 100");

$page_title = 'Create Purchase Return';
$page_actions = '<a href="purchase-return-list.php" class="btn btn-secondary"><i class="fas fa-list"></i> Returns List</a>';
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
        <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
        
        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Select Purchase -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Select Purchase</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Purchase <span class="text-danger">*</span></label>
                            <select class="form-control" id="purchaseSelect" <?= $purchase_id ? 'disabled' : '' ?>>
                                <option value="">Select Purchase to Return</option>
                                <?php foreach ($purchases as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $p['id'] == $purchase_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['purchase_number']) ?> - <?= htmlspecialchars($p['supplier_name']) ?> (<?= $p['purchase_date'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$purchase_id): ?>
                                <small class="text-muted">Select a purchase to see items available for return</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($purchase): ?>
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
                                        <th width="35%">Product</th>
                                        <th width="10%">Purchased Qty</th>
                                        <th width="15%">Return Qty</th>
                                        <th width="15%">Unit Price</th>
                                        <th width="15%">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($purchase_items as $index => $item): ?>
                                    <tr class="item-row">
                                        <td>
                                            <input type="checkbox" class="item-checkbox" name="products[<?= $index ?>][selected]" value="1">
                                            <input type="hidden" name="products[<?= $index ?>][product_id]" value="<?= $item['product_id'] ?>">
                                            <input type="hidden" class="has-serial" value="<?= ($item['has_serial'] == 'Available' || $item['has_serial'] == '1') ? '1' : '0' ?>">
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                            <small class="text-muted">Code: <?= htmlspecialchars($item['product_code']) ?></small>
                                            
                                            <?php if ($item['has_serial']): ?>
                                                <div class="mt-2 serial-container" style="display: none;">
                                                    <label class="small font-weight-bold">Select Serials to Return:</label>
                                                    <select name="products[<?= $index ?>][serials][]" class="form-control select2 serial-select" multiple disabled>
                                                        <?php foreach ($item['available_serials'] as $serial): ?>
                                                            <option value="<?= $serial['id'] ?>">
                                                                <?= htmlspecialchars($serial['serial_number'] ?: $serial['imei']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="text-info d-block">Available: <?= count($item['available_serials']) ?> serial(s)</small>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>
                                            <input type="number" name="products[<?= $index ?>][quantity]" 
                                                   class="form-control return-qty" 
                                                   step="0.01" min="0.01" max="<?= $item['quantity'] ?>" 
                                                   value="<?= $item['quantity'] ?>" 
                                                   <?= $item['has_serial'] ? 'readonly' : '' ?>>
                                        </td>
                                        <td>
                                            <input type="number" name="products[<?= $index ?>][price]" 
                                                   class="form-control unit-price" 
                                                   step="0.01" min="0" 
                                                   value="<?= $item['unit_price'] ?>">
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
                <?php if ($purchase): ?>
                <!-- Purchase Info -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Purchase Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Purchase #:</strong> <?= htmlspecialchars($purchase['purchase_number']) ?></p>
                        <p><strong>Supplier:</strong> <?= htmlspecialchars($purchase['supplier_name']) ?></p>
                        <p><strong>Date:</strong> <?= format_date($purchase['purchase_date']) ?></p>
                        <p><strong>Total:</strong> <?= format_currency($purchase['total_amount']) ?></p>
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
                            <textarea name="reason" class="form-control" rows="3" 
                                      placeholder="Enter reason for return" required></textarea>
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
                            <a href="purchase-return-list.php" class="btn btn-secondary">
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
    // Initialize Select2
    if($('.select2').length) {
        $('.select2').select2({
            placeholder: "Select serial numbers",
            width: '100%'
        });
    }

    // Redirect to page with purchase ID when selected
    $('#purchaseSelect').change(function() {
        const purchaseId = $(this).val();
        if (purchaseId) {
            window.location.href = 'purchase-return-create.php?purchase_id=' + purchaseId;
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

    // Individual checkbox toggle
    $('.item-checkbox').change(function() {
        toggleRowInputs($(this).closest('tr'), this.checked);
        calculateTotal();
    });

        // Toggle row inputs based on checkbox
    function toggleRowInputs(row, enabled) {
        const hasSerialVal = row.find('.has-serial').val();
        const hasSerial = hasSerialVal == '1';
        
        row.find('.unit-price').prop('disabled', !enabled);
        
        if (hasSerial) {
            row.find('.serial-container').toggle(enabled);
            row.find('.serial-select').prop('disabled', !enabled);
            if (!enabled) {
                row.find('.serial-select').val(null).trigger('change');
                row.find('.return-qty').val(0);
            }
        } else {
            // For non-serial we leave the quantity input editable at all times.
        }

        if (!enabled) {
            row.find('.item-subtotal').val('0.00');
        } else {
            calculateRowSubtotal(row);
        }
    }

    // Update quantity when serials are selected
    $(document).on('change', '.serial-select', function() {
        const row = $(this).closest('tr');
        const count = $(this).val() ? $(this).val().length : 0;
        row.find('.return-qty').val(count);
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
        const row = $(this).closest('tr');
        const hasSerial = row.find('.has-serial').val() == '1';
        
        // Auto-check if qty entered and not serial
        if (!hasSerial) {
            const qty = parseFloat(row.find('.return-qty').val()) || 0;
            const cb = row.find('.item-checkbox');
            if (qty > 0 && !cb.is(':checked')) {
                cb.prop('checked', true); // Auto check without triggering change again
            } else if (qty <= 0 && cb.is(':checked')) {
                cb.prop('checked', false);
            }
        }
        
        calculateRowSubtotal(row);
        calculateTotal();
    });

    // Initial calculation
    calculateTotal();
});
</script>

