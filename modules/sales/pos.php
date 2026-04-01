<?php

/**
 * Professional Point of Sale (POS) Page
 * Redesigned to match provide design layout
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle sale submission
if (is_post() && isset($_POST['submit_sale'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        $raw_items = json_decode($_POST['cart_items'], true);
        if (empty($raw_items)) {
            $errors[] = 'Cart is empty';
        }

        // Merge duplicates if any (Backend Safeguard)
        $items = [];
        foreach ($raw_items as $ri) {
            $pid = (int)$ri['product_id'];
            if (isset($items[$pid])) {
                $items[$pid]['quantity'] += $ri['quantity'];
                if (!empty($ri['selected_serials'])) {
                    $items[$pid]['selected_serials'] = array_unique(array_merge($items[$pid]['selected_serials'], $ri['selected_serials']));
                    $items[$pid]['quantity'] = count($items[$pid]['selected_serials']);
                }
                // Update total based on new quantity and first encountered price
                $items[$pid]['total'] = $items[$pid]['quantity'] * $items[$pid]['price'];
            } else {
                $items[$pid] = $ri;
            }
        }
        $items = array_values($items);

        if (empty($errors)) {
            /** @var PDO $conn */
            global $conn;
            try {
                if ($conn === null) {
                    throw new Exception("Database connection error.");
                }
                $conn->beginTransaction();

                // 1. Validate Stock Availability (Backend Enforcement)
                foreach ($items as $item) {
                    $pid = (int)$item['product_id'];
                    $qty = (float)$item['quantity'];
                    $p = db_query_one("SELECT name, stock_quantity FROM products WHERE id = ?", [$pid]);

                    if (!$p) throw new Exception("Product not found (ID: $pid)");

                    if ($p['stock_quantity'] < $qty) {
                        throw new Exception("Stock not available for \"{$p['name']}\". Available: " . (float)$p['stock_quantity'] . ", Requested: $qty");
                    }
                }

                $last_sale = db_query_one("SELECT MAX(id) as last_id FROM sales");
                $invoice_number = generate_invoice_number(null, $last_sale ? ($last_sale['last_id'] ?? 0) : 0);

                // Use overridden values from POST if available, otherwise calculate
                $discount = (float)($_POST['discount'] ?? 0);
                $tax_rate = (float)($_POST['tax_rate'] ?? 0);
                $cash_handover = (float)($_POST['cash_handover'] ?? 0);
                $total_amount = (float)($_POST['grand_total'] ?? 0);
                $paid_amount = (float)($_POST['paid_amount'] ?? 0);
                $due_amount = $total_amount - $paid_amount;
                // If grand_total was not provided, calculate it
                if ($total_amount <= 0) {
                    $subtotal = 0;
                    foreach ($items as $item) {
                        $subtotal += $item['quantity'] * $item['price'];
                    }
                    $tax_amount = ($subtotal * $tax_rate) / 100;
                    $total_amount = $subtotal + $tax_amount - $discount;
                    $due_amount = $total_amount - $paid_amount;
                } else {
                    // Back-calculate tax amount for records if grand_total is overridden
                    $subtotal = 0;
                    foreach ($items as $item) {
                        $subtotal += $item['quantity'] * $item['price'];
                    }
                    $tax_amount = ($subtotal * $tax_rate) / 100;
                }

                if ($paid_amount > $total_amount) {
                    throw new Exception("Payment amount (" . format_currency($paid_amount) . ") cannot exceed the total amount (" . format_currency($total_amount) . ")");
                }

                $payment_status = ($paid_amount >= $total_amount) ? 'paid' : ($paid_amount > 0 ? 'partial' : 'unpaid');

                $edit_id = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
                if ($edit_id) {
                    $old_sale = db_query_one("SELECT * FROM sales WHERE id = ?", [$edit_id]);
                    if (!$old_sale) throw new Exception("Sale to edit not found.");

                    $invoice_number = $old_sale['invoice_number']; // Retain original invoice number

                    // 1. Revert stock and serials
                    $old_items = db_query("SELECT * FROM sale_items WHERE sale_id = ?", [$edit_id]);
                    foreach ($old_items as $oi) {
                        db_query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$oi['quantity'], $oi['product_id']]);
                    }
                    db_query("UPDATE product_serials SET status = 'available', sale_id = NULL, sale_item_id = NULL, sale_date = NULL WHERE sale_id = ?", [$edit_id]);

                    // REVERT FINANCIAL TRANSACTION FOR HANDOVER (IF ANY)
                    if ($old_sale['cash_handover'] > 0) {
                        // Find the transaction to know which account it was from
                        // Note: We search both cash and bank just in case
                        $c_trans = db_select_one('cash_transactions', ['reference_type' => 'cash_handover', 'reference_id' => $edit_id]);
                        if ($c_trans) {
                            $acc = db_select_one('cash_accounts', ['id' => $c_trans['account_id']]);
                            if ($acc) {
                                db_update('cash_accounts', ['current_balance' => $acc['current_balance'] + $c_trans['amount']], ['id' => $c_trans['account_id']]);
                            }
                            db_query("DELETE FROM cash_transactions WHERE id = ?", [$c_trans['id']]);
                        } else {
                            $b_trans = db_select_one('bank_transactions', ['reference_type' => 'cash_handover', 'reference_id' => $edit_id]);
                            if ($b_trans) {
                                $acc = db_select_one('bank_accounts', ['id' => $b_trans['account_id']]);
                                if ($acc) {
                                    db_update('bank_accounts', ['current_balance' => $acc['current_balance'] + $b_trans['amount']], ['id' => $b_trans['account_id']]);
                                }
                                db_query("DELETE FROM bank_transactions WHERE id = ?", [$b_trans['id']]);
                            }
                        }
                    }

                    // 2. Adjust customer balance (revert old due and handover)
                    if ($old_sale['customer_id']) {
                        $revert_amount = $old_sale['due_amount'] + ($old_sale['cash_handover'] ?? 0);
                        db_query("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?", [$revert_amount, $old_sale['customer_id']]);
                    }

                    // 3. Delete old items
                    db_query("DELETE FROM sale_items WHERE sale_id = ?", [$edit_id]);

                    // 4. Update sales table
                    db_update('sales', [
                        'customer_id' => !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null,
                        'total_amount' => $total_amount,
                        'tax_amount' => $tax_amount,
                        'discount' => $discount,
                        'paid_amount' => $paid_amount,
                        'due_amount' => $due_amount,
                        'payment_status' => $payment_status,
                        'payment_method' => clean_input($_POST['payment_method'] ?? 'cash'),
                        'notes' => clean_input($_POST['notes'] ?? ''),
                        'cash_handover' => $cash_handover,
                        'staff_id' => !empty($_POST['staff_id']) ? (int)$_POST['staff_id'] : null,
                        'sold_by' => clean_input($_POST['sold_by'] ?? '')
                    ], ['id' => $edit_id]);

                    $sale_id = $edit_id;
                    log_activity(get_current_user_id(), 'sale_update', "Updated Sale #$invoice_number");
                } else {
                    $sale_id = db_insert('sales', [
                        'invoice_number' => $invoice_number,
                        'customer_id' => !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null,
                        'sale_date' => date('Y-m-d'),
                        'total_amount' => $total_amount,
                        'tax_amount' => $tax_amount,
                        'discount' => $discount,
                        'paid_amount' => $paid_amount,
                        'due_amount' => $due_amount,
                        'payment_status' => $payment_status,
                        'status' => 'completed',
                        'payment_method' => clean_input($_POST['payment_method'] ?? 'cash'),
                        'notes' => clean_input($_POST['notes'] ?? ''),
                        'cash_handover' => $cash_handover,
                        'staff_id' => !empty($_POST['staff_id']) ? (int)$_POST['staff_id'] : null,
                        'sold_by' => clean_input($_POST['sold_by'] ?? ''),
                        'created_by' => get_current_user_id()
                    ]);

                    if (!$sale_id) {
                        throw new Exception("Failed to insert sale record. Please ensure your database is up-to-date (check update.sql).");
                    }

                    log_activity(get_current_user_id(), 'sale_add', "Created Sale #$invoice_number");
                }

                $payment_method = clean_input($_POST['payment_method'] ?? 'cash');
                $account_id = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;

                foreach ($items as $item) {
                    $item_total = $item['custom_total'] ?? ($item['quantity'] * $item['price']);
                    $sale_item_id = db_insert('sale_items', [
                        'sale_id' => $sale_id,
                        'product_id' => $item['product_id'],
                        'description' => $item['description'] ?? '',
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'tax' => ($item['price'] * $item['quantity'] * ($item['tax_rate'] ?? $tax_rate)) / 100,
                        'subtotal' => $item_total
                    ]);

                    if (!empty($item['selected_serials'])) {
                        foreach ($item['selected_serials'] as $sn) {
                            db_query(
                                "UPDATE product_serials SET sale_id = ?, sale_item_id = ?, sale_date = ?, status = 'sold' WHERE serial_number = ? AND product_id = ?",
                                [$sale_id, $sale_item_id, date('Y-m-d'), $sn, $item['product_id']]
                            );
                        }
                    }
                    db_query("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
                }

                if (!$edit_id && $paid_amount > 0) {
                    db_insert('sale_payments', [
                        'sale_id' => $sale_id,
                        'payment_date' => date('Y-m-d'),
                        'amount' => $paid_amount,
                        'payment_method' => $payment_method,
                        'account_id' => $account_id
                    ]);

                    // Update Financial Account Balance
                    if ($account_id) {
                        $table = $payment_method === 'cash' ? 'cash_accounts' : 'bank_accounts';
                        $account = db_select_one($table, ['id' => $account_id]);
                        if ($account) {
                            db_update($table, ['current_balance' => $account['current_balance'] + $paid_amount], ['id' => $account_id]);

                            // Record Transaction
                            $trans_table = $payment_method === 'cash' ? 'cash_transactions' : 'bank_transactions';
                            db_insert($trans_table, [
                                'account_id' => $account_id,
                                'transaction_type' => 'credit',
                                'amount' => $paid_amount,
                                'reference_type' => 'sale_payment',
                                'reference_id' => $sale_id,
                                'description' => "Sale Payment: #$invoice_number",
                                'transaction_date' => date('Y-m-d'),
                                'created_by' => get_current_user_id()
                            ]);
                        }
                    }
                }

                if (!empty($_POST['customer_id'])) {
                    $customer_id = (int)$_POST['customer_id'];

                    // Update customer info if manually changed
                    if (!empty($_POST['customer_address'])) {
                        db_update('customers', ['address' => $_POST['customer_address']], ['id' => $customer_id]);
                    }

                    // Clear existing ledger entries for this sale (if editing)
                    if ($edit_id) {
                        db_query("DELETE FROM customer_ledger WHERE customer_id = ? AND reference_id = ? AND transaction_type IN ('sale', 'payment', 'cash_handover')", [$customer_id, $sale_id]);
                    }

                    // 1. Record Sale (Debit)
                    db_insert('customer_ledger', [
                        'customer_id' => $customer_id,
                        'transaction_type' => 'sale',
                        'reference_id' => $sale_id,
                        'debit' => $total_amount,
                        'credit' => 0,
                        'description' => "Sale Invoice: $invoice_number",
                        'date' => date('Y-m-d')
                    ]);

                    // 2. Record Payment (Credit) if any
                    if ($paid_amount > 0) {
                        db_insert('customer_ledger', [
                            'customer_id' => $customer_id,
                            'transaction_type' => 'payment',
                            'reference_id' => $sale_id,
                            'debit' => 0,
                            'credit' => $paid_amount,
                            'description' => "Payment for Invoice: $invoice_number",
                            'date' => date('Y-m-d')
                        ]);
                    }

                    // Update customer balance: add new due
                    db_query("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?", [$due_amount, $customer_id]);

                    // 3. Record Cash Handover (Debit) if any
                    if ($cash_handover > 0) {
                        db_insert('customer_ledger', [
                            'customer_id' => $customer_id,
                            'transaction_type' => 'cash_handover',
                            'reference_id' => $sale_id,
                            'debit' => $cash_handover,
                            'credit' => 0,
                            'description' => "Cash Handover for Invoice: $invoice_number",
                            'date' => date('Y-m-d')
                        ]);

                        // Also update customer balance for handover
                        db_query("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?", [$cash_handover, $customer_id]);

                        // 4. Record Financial Transaction (Outflow/Debit)
                        if ($account_id) {
                            $table = $payment_method === 'cash' ? 'cash_accounts' : 'bank_accounts';
                            $account = db_select_one($table, ['id' => $account_id]);
                            if ($account) {
                                db_update($table, ['current_balance' => $account['current_balance'] - $cash_handover], ['id' => $account_id]);

                                $trans_table = $payment_method === 'cash' ? 'cash_transactions' : 'bank_transactions';
                                db_insert($trans_table, [
                                    'account_id' => $account_id,
                                    'transaction_type' => 'debit',
                                    'amount' => $cash_handover,
                                    'reference_type' => 'cash_handover',
                                    'reference_id' => $sale_id,
                                    'description' => "Cash Handover: #$invoice_number",
                                    'transaction_date' => date('Y-m-d'),
                                    'created_by' => get_current_user_id()
                                ]);
                            }
                        }
                    }
                }
                $conn->commit();

                // Trigger SMS Notification if enabled
                require_once __DIR__ . '/../../includes/sms_functions.php';
                send_sale_sms($sale_id);

                // If this sale was generated from a Sales Request, mark it completed
                if (!empty($_POST['from_request_id'])) {
                    $req_id = (int)$_POST['from_request_id'];
                    db_update('sales_requests', [
                        'status' => 'completed',
                        'completed_sale_id' => $sale_id
                    ], ['id' => $req_id]);
                }

                if (!empty($_POST['from_quotation_id'])) {
                    $q_id = (int)$_POST['from_quotation_id'];
                    db_update('quotations', [
                        'status' => 'sales_complete'
                    ], ['id' => $q_id]);
                    log_activity(get_current_user_id(), 'quotation_convert', "Quotation converted to Sale via POS");
                }

                redirect_with_message("invoice-print.php?id=$sale_id", 'Sale completed successfully', 'success');
            } catch (Exception $e) {
                if ($conn !== null && $conn->inTransaction()) $conn->rollBack();
                $errors[] = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Load existing sale for "Edit" (loading into cart)
$edit_sale = null;
$edit_items_json = '[]';
$from_request_id = '';
$from_quotation_id = '';
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $edit_sale = db_query_one("SELECT * FROM sales WHERE id = ?", [$edit_id]);
    if ($edit_sale) {
        $items_raw = db_query("SELECT si.*, p.name as product_name, p.code as product_code 
                             FROM sale_items si 
                             INNER JOIN products p ON si.product_id = p.id 
                             WHERE si.sale_id = ?", [$edit_id]);
        $cart_items = [];
        foreach ($items_raw as $item) {
            // Fetch selected serials for this item
            $serials_raw = db_query("SELECT serial_number FROM product_serials WHERE sale_item_id = ?", [$item['id']]);
            $selected_serials = array_column($serials_raw, 'serial_number');

            $cart_items[] = [
                'product_id' => (int)$item['product_id'],
                'name' => $item['product_name'],
                'price' => (float)$item['unit_price'],
                'quantity' => (int)$item['quantity'],
                'total' => (float)$item['subtotal'],
                'selected_serials' => $selected_serials
            ];
        }
        $edit_items_json = json_encode($cart_items);
    }
} elseif (isset($_GET['from_request'])) {
    $req_id = (int)$_GET['from_request'];
    $req = db_query_one("SELECT sr.*, u.username as sr_username 
                         FROM sales_requests sr 
                         LEFT JOIN users u ON sr.sr_user_id = u.id 
                         WHERE sr.id = ?", [$req_id]);
    if ($req && $req['status'] === 'approved') {
        $from_request_id = $req_id;
        $req_items = json_decode($req['items'], true);
        $cart_items = [];
        foreach ($req_items as $item) {
            $cart_items[] = [
                'product_id' => (int)$item['product_id'],
                'name' => $item['product_name'],
                'price' => (float)$item['unit_price'],
                'quantity' => (int)$item['quantity'],
                'total' => (float)$item['unit_price'] * (int)$item['quantity'],
                'selected_serials' => [] // Admin needs to scan these
            ];
        }
        $edit_items_json = json_encode($cart_items);
        $edit_sale = [
            'id' => '', // New sale
            'invoice_number' => '(From Req: ' . $req['request_number'] . ')',
            'customer_id' => $req['customer_id'],
            'sale_date' => date('Y-m-d'),
            'sold_by' => $req['sr_username'] ?? '' // Capture SR Creator Name
        ];
    }
} elseif (isset($_GET['quotation_id'])) {
    $q_id = (int)$_GET['quotation_id'];
    $quot = db_query_one("SELECT * FROM quotations WHERE id = ?", [$q_id]);
    if ($quot && $quot['status'] !== 'converted' && $quot['status'] !== 'sales_complete') {
        $from_quotation_id = $q_id;
        $q_items = db_query("SELECT qi.*, p.name as product_name, p.code as product_code 
                             FROM quotation_items qi 
                             INNER JOIN products p ON qi.product_id = p.id 
                             WHERE qi.quotation_id = ?", [$q_id]);
        $cart_items = [];
        foreach ($q_items as $item) {
            $cart_items[] = [
                'product_id' => (int)$item['product_id'],
                'name' => $item['product_name'],
                'price' => (float)$item['unit_price'],
                'quantity' => (int)$item['quantity'],
                'total' => (float)$item['subtotal'],
                'selected_serials' => [] // POS admin needs to scan serials
            ];
        }
        $edit_items_json = json_encode($cart_items);
        $edit_sale = [
            'id' => '',
            'invoice_number' => '(From Quotation: ' . $quot['quotation_number'] . ')',
            'customer_id' => $quot['customer_id'],
            'sale_date' => date('Y-m-d'),
            'discount' => $quot['discount'],
            'paid_amount' => 0,
            'tax_amount' => $quot['tax_amount'],
            'total_amount' => $quot['total_amount'],
            'sold_by' => '',
            'notes' => $quot['notes']
        ];
    }
}

$customers = db_select('customers', ['status' => 'active'], '*', 'name ASC');

// Fetch accounts for Payment Method dropdown
$cash_accounts = db_select('cash_accounts', []);
$bank_accounts = db_select('bank_accounts', []);

// Fetch necessary lists
$categories = db_select('categories', [], '*', 'name ASC');
$brands = db_select('brands', [], '*', 'name ASC');
$staff_list = db_select('staff', ['status' => 'active'], '*', 'name ASC');

$active_staff_id = null; // Initialize the active_staff_id variable to prevent undefined warning

$page_title = $edit_sale ? 'Edit Invoice #' . $edit_sale['invoice_number'] : 'POS Terminal';
$invoice_number = "INV-" . date('ymdHis') . rand(10, 99);

// Custom header include to add pos CSS
include __DIR__ . '/../../templates/header.php';
?>

<!-- Include custom POS CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pos-custom.css?v=<?= APP_VERSION ?>">

<!-- Custom POS Header for Fullscreen Mode -->
<div class="pos-top-header">
    <div class="pos-logo">
        <i class="fas fa-store"></i> <span class="fw-bold">Smart POS</span> Terminal
    </div>
    <div class="pos-clock" id="posClock">Loading time...</div>
    <div class="pos-top-actions">
        <button type="button" class="btn btn-sm btn-outline-light" onclick="toggleFullScreen()"><i class="fas fa-expand"></i> Fullscreen <span class="keyboard-shortcut text-dark">F11</span></button>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-sm btn-danger"><i class="fas fa-sign-out-alt"></i> Exit POS</a>
    </div>
</div>

<div class="pos-container">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>

    <form method="POST" id="saleForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="cart_items" id="cartItemsInput">
        <input type="hidden" name="submit_sale" value="1">
        <input type="hidden" name="edit_id" value="<?= $edit_sale ? $edit_sale['id'] : '' ?>">
        <input type="hidden" name="from_request_id" value="<?= $from_request_id ?>">
        <input type="hidden" name="from_quotation_id" value="<?= $from_quotation_id ?>">

        <div class="row">
            <!-- Left Column: Products -->
            <div class="col-md-5">
                <div class="pos-card">
                    <div class="pos-header-title"><i class="fas fa-boxes"></i> Product Selection <span class="keyboard-shortcut">F2</span></div>

                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="productSearch" class="search-input-rounded" placeholder="Search by name, code or serial... (F2)" autofocus>
                    </div>

                    <div class="view-icons">
                        <i class="fas fa-th-large view-icon-btn active" id="viewGrid" title="Grid View"></i>
                        <i class="fas fa-list view-icon-btn" id="viewList" title="List View"></i>
                    </div>

                    <div class="product-box-container" id="productsContainer">
                        <!-- Products loaded via AJAX -->
                    </div>
                </div>
            </div>

            <!-- Right Column: Form & Table -->
            <div class="col-md-7">
                <div class="pos-card pos-right-form">
                    <div class="pos-header-title"><i class="fas fa-file-invoice-dollar"></i> Invoice Details</div>
                    <div class="row gx-3">
                        <div class="col-md-6">
                            <div class="form-group-row">
                                <label>Invoice Number</label>
                                <input type="text" class="form-control-custom" value="<?= $edit_sale ? htmlspecialchars($edit_sale['invoice_number']) : '(New)' ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-row">
                                <label>Sale Date</label>
                                <input type="text" class="form-control-custom" value="<?= $edit_sale ? format_date($edit_sale['sale_date']) : date('d-M-Y') ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6">
                            <div class="form-group-row">
                                <label>Buyer Name</label>
                                <select name="customer_id" id="customerSelect" class="form-control-custom select2">
                                    <option value="">Walk-in Customer</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-row">
                                <label>Previous Due</label>
                                <input type="text" name="customer_due" id="customerDue" class="form-control-custom" value="0.00" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-row">
                        <label>Customer Address</label>
                        <input type="text" name="customer_address" id="customerAddress" class="form-control-custom" readonly>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-12">
                            <div class="form-group-row">
                                <label><i class="fas fa-tag"></i> Active Product</label>
                                <input type="text" id="activeProductName" class="form-control-custom font-weight-bold" placeholder="Select a product..." readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row gx-2">
                        <div class="col-4">
                            <div class="form-group-row">
                                <label>Cost</label>
                                <input type="text" id="activeProductCost" class="form-control-custom" readonly>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group-row">
                                <label>Price</label>
                                <input type="text" id="activeProductPrice" class="form-control-custom">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group-row">
                                <label>Stock</label>
                                <input type="text" id="currentStock" class="form-control-custom" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-row mt-2">
                        <label><i class="fas fa-barcode"></i> Scan Serial Number</label>
                        <input type="text" id="serialInput" class="form-control-custom" placeholder="Type serial and press Enter...">
                    </div>

                    <div class="serial-list-box" id="serialListBox">
                        <!-- Serials will show here -->
                    </div>

                    <div class="pos-table-container">
                        <table class="pos-table">
                            <thead>
                                <tr>
                                    <th class="col-desc">P. Description</th>
                                    <th class="col-serial">Serial No.</th>
                                    <th class="col-qty text-center">QTY</th>
                                    <th class="col-price text-right">Price</th>
                                    <th class="col-total text-right">Total</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cartTableBody">
                                <!-- Cart items -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Summary Bar -->
        <div class="pos-summary-bar">
            <div class="row">
                <div class="col-md-4">
                    <div class="summary-item">Items: <input type="number" id="itemTotalQty" class="summary-input" value="0" readonly></div>
                </div>
                <div class="col-md-4">
                    <div class="summary-item">Types: <input type="number" id="productTotalQty" class="summary-input" value="0" readonly></div>
                </div>
                <div class="col-md-4">
                    <!-- Removed current stock from here as it's now in active product box -->
                </div>
            </div>

            <div class="row mt-4 pos-footer-inputs">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Tax (%)</label>
                        <input type="number" name="tax_rate" id="taxRate" class="form-control" value="0" step="0.01">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Discount (A)</label>
                        <input type="number" name="discount" id="discount" class="form-control" value="0" step="0.01">
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="summary-item mt-4">Total (A): <input type="number" name="grand_total" id="grandTotal" class="total-input-large" value="0.00" step="0.01" readonly></div>
                </div>
                <div class="col-md-3 text-end">
                    <button type="submit" class="btn btn-complete-sale mt-3" id="completeSaleBtn" disabled>Complete Sale</button>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Payment Method: <span class="text-danger">*</span></label>
                        <select name="payment_method" id="paymentMethod" class="form-control" required>
                            <option value="">Select Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="card">Card</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Deposit To: <span class="text-danger">*</span></label>
                        <select name="account_id" id="paymentAccount" class="form-control" required>
                            <option value="">Select Account</option>
                        </select>
                    </div>
                </div>
                <!-- Adjusted columns layout -->
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Paid Amount:</label>
                        <input type="number" name="paid_amount" id="paidAmount" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="summary-item mt-4">Change/Due: <span id="changeDue" class="font-weight-bold">0.00</span></div>
                </div>
            </div>

            <div class="row mt-2">
                <div class="col-md-5">
                    <div class="form-group">
                        <label>Notes:</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes...">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Cash Handover:</label>
                        <input type="number" name="cash_handover" id="cashHandover" class="form-control" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div class="col-md-4 text-end pt-4">
                    <?php
                    $current_user = get_logged_in_user();
                    $sold_by_val = $edit_sale['sold_by'] ?? ($current_user['username'] ?? 'System');
                    
                    // Attempt to find the staff record for the current user to pre-select
                    if (!$edit_sale && !$active_staff_id) {
                        $user_staff = db_select_one('staff', ['user_id' => $current_user['id']]);
                        if ($user_staff) {
                            $active_staff_id = $user_staff['id'];
                        }
                    }

                    if ($from_request_id) {
                        // Preserving the exact string of the sales request originator
                        echo "<input type='hidden' name='sold_by' value='" . htmlspecialchars($sold_by_val) . "'>";
                        echo "<small>Sales By: <input type='text' class='form-control d-inline-block w-auto' value='" . htmlspecialchars($sold_by_val) . "' readonly tabindex='-1'></small>";
                    } else {
                    ?>
                        <small>Sales By:
                            <select name="staff_id" class="form-control d-inline-block w-auto" required onchange="$(this).next('input').val($(this).find('option:selected').text())">
                                <option value="">Select Staff</option>
                                <?php foreach ($staff_list as $st): ?>
                                    <option value="<?= $st['id'] ?>" <?= ($active_staff_id == $st['id']) ? 'selected' : '' ?>><?= htmlspecialchars($st['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="sold_by" value="<?= htmlspecialchars($sold_by_val) ?>">
                        </small>
                    <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>



<script>
    let cart = [];
    let activeProduct = null;
    let currentProducts = [];
    let currentView = 'grid'; // 'grid' or 'list'
    let manualOverride = false;

    // Inject Accounts Data
    const cashAccounts = <?= json_encode($cash_accounts) ?>;
    const bankAccounts = <?= json_encode($bank_accounts) ?>;


    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap-5'
        });

        // Initial load
        loadProducts();

        // Debounce search
        let searchTimeout;

        // Prevent form submission on Enter in search box
        $('#productSearch').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
            }
        });

        $('#productSearch').on('keyup', function(e) {
            clearTimeout(searchTimeout);
            const val = $(this).val();

            // If Enter key is pressed (by barcode scanner), search immediately
            if (e.which === 13) {
                $('#productsContainer').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading...</p></div>');
                loadProducts(val);
                return;
            }

            // Show loading indicator
            $('#productsContainer').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading products...</p></div>');

            searchTimeout = setTimeout(() => {
                loadProducts(val);
            }, 400); // 400ms debounce
        });

        $('#serialInput').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                const sn = $(this).val().trim();
                if (sn) {
                    // If a product is active, try to find the serial in its list
                    if (activeProduct) {
                        toggleSerial(sn);
                    } else {
                        // Try to find product by serial
                        $.get('../../api/products/search.php', {
                            search: sn,
                            limit: 1
                        }, function(res) {
                            if (res.status && res.data.length > 0) {
                                const p = res.data[0];
                                selectProduct(p);
                                addToCart(p);
                                if (p.has_serial === 'Available') {
                                    toggleSerial(sn);
                                }
                            }
                        });
                    }
                    $(this).val('').focus();
                }
            }
        });

        $('#customerSelect').on('change', function() {
            const id = $(this).val();
            if (id) {
                $.get('../../api/customers/get-balance.php?id=' + id, function(res) {
                    if (res.status) {
                        $('#customerDue').val(res.balance.toFixed(2));
                        $('#customerAddress').val(res.address || '');
                    }
                });
            } else {
                $('#customerDue').val('0.00');
                $('#customerAddress').val('');
            }

        });

        // Payment Method Change Handler
        $('#paymentMethod').change(function() {
            populateAccounts($(this).val());
        });

        // Trigger initial population
        populateAccounts($('#paymentMethod').val());

        $('#discount, #paidAmount, #taxRate, #grandTotal').on('input change', function() {
            if ($(this).attr('id') === 'grandTotal') manualOverride = true;
            updateTotals();
        });

        // Form Submission Validation
        $('#saleForm').on('submit', function(e) {
            if (cart.length === 0) {
                showAlert('Cart is empty', 'error');
                e.preventDefault();
                return false;
            }

            // Final Stock Check
            for (let item of cart) {
                if (item.stock_limit !== undefined && item.quantity > item.stock_limit && item.has_serial !== 'Available') {
                    showAlert('Stock not available for "' + item.name + '" (Max: ' + item.stock_limit + ')', 'error');
                    e.preventDefault();
                    return false;
                }
                if (item.has_serial === 'Available' && item.quantity === 0) {
                    showAlert('Please select serial numbers for "' + item.name + '"', 'error');
                    e.preventDefault();
                    return false;
                }
            }

            return true;
        });

        // Linked Price Synchronization
        $('#activeProductPrice').on('input', function() {
            if (!activeProduct) return;
            const price = parseFloat($(this).val()) || 0;
            let item = cart.find(i => i.product_id === activeProduct.id);
            if (item) {
                item.price = price;
                renderCart();
            }
        });

        // Allow manual editing of item totals
        $(document).on('input', '.item-price, .item-qty, .item-total', function() {
            const row = $(this).closest('tr');
            const productId = row.data('product-id');
            const item = cart.find(i => i.product_id === productId);

            if (item) {
                if ($(this).hasClass('item-price')) item.price = parseFloat($(this).val()) || 0;
                if ($(this).hasClass('item-qty')) {
                    const newQty = parseInt($(this).val()) || 0;
                    // Check against stock (Stored in item from p.stock_quantity)
                    if (item.stock_limit !== undefined && newQty > item.stock_limit && item.has_serial !== 'Available') {
                        showAlert('Cannot exceed available stock (' + item.stock_limit + ')', 'warning');
                        $(this).val(item.stock_limit);
                        item.quantity = item.stock_limit;
                    } else {
                        item.quantity = newQty;
                    }
                }
                if ($(this).hasClass('item-total')) {
                    item.custom_total = parseFloat($(this).val()) || 0;
                } else {
                    delete item.custom_total;
                }
            }
            renderCart();
        });
        // View Toggle Handlers
        $('#viewGrid').on('click', function() {
            currentView = 'grid';
            $('.view-icon-btn').removeClass('active text-primary');
            $(this).addClass('active text-primary');
            displayProducts(currentProducts);
        });

        $('#viewList').on('click', function() {
            currentView = 'list';
            $('.view-icon-btn').removeClass('active text-primary');
            $(this).addClass('active text-primary');
            displayProducts(currentProducts);
        });
    });

    function loadProducts(search = '') {
        $.get('../../api/products/search.php', {
            search: search,
            limit: 12
        }, function(response) {
            if (response.status) {
                currentProducts = response.data;
                displayProducts(currentProducts);
                if (search.length >= 3 && response.data.length === 1) {
                    const p = response.data[0];
                    if (p.barcode === search || p.matched_serial === search) {
                        selectProduct(p);
                        addToCart(p, p.matched_serial);
                        $('#productSearch').val('').focus();
                    }
                }
            }
        });
    }

    function displayProducts(products) {
        const container = $('#productsContainer');
        container.removeClass('grid-view list-view').addClass(currentView === 'grid' ? 'grid-view' : 'list-view');
        let html = '';
        products.forEach(p => {
            if (currentView === 'grid') {
                let stockNum = parseFloat(p.stock_quantity) || 0;
                let stockClass = stockNum > 10 ? '' : (stockNum > 0 ? 'low' : 'out');
                let imgHtml = p.image 
                    ? `<img src="<?= BASE_URL ?>/uploads/products/${p.image}" class="grid-product-img" alt="${p.name}">`
                    : `<div class="grid-product-icon"><i class="fas fa-box"></i></div>`;
                html += `
                <div class="product-card-pos pointer" data-product='${JSON.stringify(p)}' onclick='handleProductClick(this)'>
                    <span class="grid-badge badge ${p.has_serial === 'Available' ? 'bg-info text-white' : 'bg-secondary text-white'}">${p.has_serial === 'Available' ? 'SN' : 'Box'}</span>
                    ${imgHtml}
                    <div class="grid-product-name" title="${p.name}">${p.name}</div>
                    <div class="grid-product-code">${p.code}</div>
                    <div class="grid-price">৳${p.selling_price}</div>
                    <div class="grid-stock ${stockClass}">Stock: ${stockNum}</div>
                </div>
            `;
            } else {
                html += `
                <div class="product-list-pos pointer" data-product='${JSON.stringify(p)}' onclick='handleProductClick(this)'>
                    <div class="list-product-name" title="${p.name}">${p.name} <span class="list-product-code">(${p.code})</span></div>
                    <span class="list-price">৳${p.selling_price}</span>
                    <span class="list-stock">Stock: ${p.stock_quantity}</span>
                    <span class="badge bg-light text-dark border" style="font-size:0.65rem">${p.has_serial === 'Available' ? 'SN' : 'Box'}</span>
                </div>
            `;
            }
        });
        $('#productsContainer').html(html || '<div class="alert alert-light text-center">No products found</div>');
    }

    function handleProductClick(el) {
        const p = JSON.parse($(el).attr('data-product'));
        selectProduct(p);
        addToCart(p);
    }

    function selectProduct(p) {
        activeProduct = p;
        $('#activeProductName').val(p.name);
        $('#activeProductCost').val(p.purchase_price || '0.00');
        $('#activeProductPrice').val(p.selling_price);
        $('#currentStock').val(p.stock_quantity);

        if (p.has_serial === 'Available') {
            $('#serialInput').prop('disabled', false).focus();
            loadSerials(p.id);
        } else {
            $('#serialInput').prop('disabled', true).val('');
            $('#serialListBox').html('');
        }
    }

    function loadSerials(productId) {
        $.get('../../api/products/get-serials.php', {
            product_id: productId
        }, function(res) {
            if (res.status) {
                let html = '';
                let item = cart.find(i => Number(i.product_id) === Number(productId));
                let selectedSerials = item ? (item.selected_serials || []) : [];

                res.data.forEach(s => {
                    const selectedClass = selectedSerials.includes(s.serial_number) ? 'selected' : '';
                    html += `<div class="serial-badge ${selectedClass}" onclick="toggleSerial('${s.serial_number}')">${s.serial_number}</div>`;
                });
                $('#serialListBox').html(html || '<div class="text-muted text-center p-3">No serials available for this product in stock</div>');
            }
        });
    }

    function toggleSerial(sn) {
        if (!activeProduct) return;

        let item = cart.find(i => Number(i.product_id) === Number(activeProduct.id));
        if (!item) {
            // If not in cart, add it first
            addToCart(activeProduct);
            item = cart.find(i => Number(i.product_id) === Number(activeProduct.id));
        }

        if (item) {
            if (!item.selected_serials) item.selected_serials = [];

            const index = item.selected_serials.indexOf(sn);
            if (index > -1) {
                item.selected_serials.splice(index, 1);
            } else {
                item.selected_serials.push(sn);
            }

            // Update quantity to match serial count
            item.quantity = item.selected_serials.length;
            if (item.quantity === 0 && item.has_serial === 'Available') item.quantity = 0;
            else if (item.quantity === 0) item.quantity = 1;

            renderCart();
            loadSerials(activeProduct.id); // Refresh badges
        }
    }

    function addToCart(p, serial = null) {
        if (parseFloat(p.stock_quantity) <= 0 && p.has_serial !== 'Available') {
            showAlert('Stock not available for this product', 'error');
            return;
        }

        let item = cart.find(i => Number(i.product_id) === Number(p.id));
        if (item) {
            if (p.has_serial !== 'Available') {
                if (item.quantity + 1 > parseFloat(p.stock_quantity)) {
                    showAlert('Cannot exceed available stock (' + p.stock_quantity + ')', 'warning');
                    return;
                }
                item.quantity++;
            }
            if (serial && !item.selected_serials.includes(serial)) {
                item.selected_serials.push(serial);
                item.quantity = item.selected_serials.length;
            }
        } else {
            let selectedSerials = serial ? [serial] : (p.matched_serial ? [p.matched_serial] : []);
            cart.push({
                product_id: Number(p.id),
                name: p.name,
                description: p.description || '',
                price: parseFloat(p.selling_price),
                tax_rate: parseFloat(p.tax_rate),
                quantity: p.has_serial === 'Available' ? (selectedSerials.length || 0) : 1,
                has_serial: p.has_serial,
                selected_serials: selectedSerials,
                stock_limit: parseFloat(p.stock_quantity)
            });
        }
        renderCart();
    }

    function removeFromCart(id) {
        cart = cart.filter(i => Number(i.product_id) !== Number(id));
        renderCart();
    }

    function removeSerialFromCart(productId, serialNumber) {
        let item = cart.find(i => Number(i.product_id) === Number(productId));
        if (item && item.selected_serials) {
            // Remove the serial from the array
            const index = item.selected_serials.indexOf(serialNumber);
            if (index > -1) {
                item.selected_serials.splice(index, 1);
            }

            // Update quantity to match serial count
            item.quantity = item.selected_serials.length;

            // If no serials left and product has serial tracking, remove from cart
            if (item.quantity === 0 && item.has_serial === 'Available') {
                cart = cart.filter(i => i.product_id !== productId);
            }

            renderCart();

            // If there's an active product and it matches, reload its serials
            if (activeProduct && activeProduct.id === productId) {
                loadSerials(productId);
            }
        }
    }

    function renderCart() {
        let html = '';
        let totalItems = 0;
        cart.forEach((item, index) => {
            totalItems += item.quantity;
            const total = item.custom_total !== undefined ? item.custom_total : (item.price * item.quantity);
            html += `
            <tr data-product-id="${item.product_id}">
                <td class="col-desc"><input type="text" class="form-control form-control-sm" value="${item.name}" readonly></td>
                <td class="col-serial">
                    ${item.selected_serials.length > 0 
                        ? item.selected_serials.map(sn => 
                            `<span class="badge bg-primary me-1" 
                                   style="cursor: pointer; transition: all 0.2s ease;" 
                                   onmouseover="this.style.backgroundColor='#dc3545'" 
                                   onmouseout="this.style.backgroundColor='#0d6efd'" 
                                   onclick="removeSerialFromCart(${item.product_id}, '${sn}')" 
                                   title="Click to deselect">
                              ${sn} <i class="fas fa-times-circle ms-1"></i>
                            </span>`
                          ).join(' ') 
                        : '-'
                    }
                </td>
                <td class="col-qty text-center">
                    <input type="number" class="form-control form-control-sm text-center item-qty" value="${item.quantity}" min="1" ${item.has_serial === 'Available' ? 'readonly' : ''}>
                </td>
                <td class="col-price text-right">
                    <input type="number" class="form-control form-control-sm text-right item-price" value="${item.price.toFixed(2)}" step="0.01" readonly>
                </td>
                <td class="col-total text-right">
                    <input type="number" class="form-control form-control-sm text-right item-total" value="${total.toFixed(2)}" step="0.01">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm text-danger" onclick="removeFromCart(${item.product_id})">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
        });
        $('#cartTableBody').html(html);
        $('#itemTotalQty').val(totalItems);
        $('#productTotalQty').val(cart.length);
        updateTotals();
    }

    function updateItemName(id, name) {
        let item = cart.find(i => i.product_id === id);
        if (item) item.name = name;
    }

    function populateAccounts(type) {
        const select = $('#paymentAccount');
        select.html('<option value="">Select Account</option>');

        const accounts = type === 'cash' ? cashAccounts : bankAccounts;

        accounts.forEach(acc => {
            // Filter Logic for Bank Accounts
            if (type !== 'cash') {
                const accType = acc.account_type || 'bank';
                if (accType !== type && (type !== 'bank' || accType !== 'bank')) {
                    if (accType !== type) return;
                }
            }

            const name = type === 'cash' ? acc.account_name : `${acc.bank_name} (${acc.account_number})`;
            select.append(`<option value="${acc.id}">${name}</option>`);
        });

        // Auto-select first if only one
        if (accounts.length === 1 && select.children('option').length === 2) {
            select.val(accounts[0].id);
        }
    }


    function updateTotals() {
        let subtotal = 0;
        let taxAmount = 0;
        const globalTaxRate = parseFloat($('#taxRate').val()) || 0;

        cart.forEach(item => {
            const total = item.custom_total !== undefined ? item.custom_total : (item.price * item.quantity);
            subtotal += total;
        });

        taxAmount = (subtotal * globalTaxRate) / 100;
        let discount = parseFloat($('#discount').val()) || 0;
        let calculatedTotal = subtotal + taxAmount - discount;

        if (!manualOverride) {
            $('#grandTotal').val(calculatedTotal.toFixed(2));
        }

        let currentTotal = parseFloat($('#grandTotal').val()) || 0;
        let paid = parseFloat($('#paidAmount').val()) || 0;
        let change = paid - currentTotal;

        $('#changeDue').text(change.toFixed(2));
        $('#cartItemsInput').val(JSON.stringify(cart));
        $('#completeSaleBtn').prop('disabled', cart.length === 0);
    }

    $(document).ready(function() {
        // Load edit items if present
        var editItems = <?= $edit_items_json ?>;
        if (editItems.length > 0) {
            cart = editItems;
            renderCart();

            // Set customer if present
            <?php if ($edit_sale && $edit_sale['customer_id']): ?>
                $('#customerSelect').val('<?= $edit_sale['customer_id'] ?>').trigger('change');
            <?php endif; ?>

            // Set other fields
            <?php if ($edit_sale): ?>
                $('input[name="sold_by"]').val('<?= addslashes($edit_sale['sold_by']) ?>');
                $('textarea[name="notes"]').val('<?= addslashes($edit_sale['notes']) ?>');
                $('#discount').val('<?= $edit_sale['discount'] ?>');
                $('#paidAmount').val('<?= $edit_sale['paid_amount'] ?>');
                $('#taxRate').val('<?= $edit_sale['tax_amount'] > 0 ? ($edit_sale['tax_amount'] / ($edit_sale['total_amount'] - $edit_sale['tax_amount'] + $edit_sale['discount'])) * 100 : 0 ?>');
                updateTotals();
            <?php endif; ?>
        }
    });

    // Smart POS specific JS
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        let ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        let minutes = now.getMinutes().toString().padStart(2, '0');
        let seconds = now.getSeconds().toString().padStart(2, '0');
        let strTime = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        if (document.getElementById('posClock')) {
            document.getElementById('posClock').innerText = strTime;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    function toggleFullScreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log(`Error attempting to enable full-screen mode: ${err.message}`);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }

    // Global Keyboard Shortcuts
    $(document).on('keydown', function(e) {
        // F2: Focus Search
        if (e.key === 'F2') {
            e.preventDefault();
            $('#productSearch').focus();
        }
        // F11: Fullscreen is handled natively
    });
</script>