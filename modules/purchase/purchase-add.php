<?php

/**
 * Add Purchase Page
 * Create new purchase order with multiple products
 */

// ============================================
// 1. INITIALIZATION
// ============================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

// Require login
require_login();

// ============================================
// 2. HANDLE FORM SUBMISSION
// ============================================
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];

        // Validate required fields
        if (empty($_POST['supplier_id'])) {
            $errors[] = 'Supplier is required';
        }
        if (empty($_POST['purchase_date'])) {
            $errors[] = 'Purchase date is required';
        }
        if (empty($_POST['products']) || count($_POST['products']) == 0) {
            $errors[] = 'At least one product is required';
        } else {
            // Track serials submitted in this form to prevent duplicates within the same form
            $submitted_serials = [];

            // Validate each product
            foreach ($_POST['products'] as $index => $product) {
                $rowNum = $index + 1;
                if (empty($product['product_id'])) {
                    $errors[] = "Row $rowNum: Please select a product";
                }
                if (empty($product['quantity']) || $product['quantity'] <= 0) {
                    $errors[] = "Row $rowNum: Please enter a valid quantity";
                }
                if (!isset($product['price']) || $product['price'] < 0) {
                    $errors[] = "Row $rowNum: Please enter a valid price";
                }

                // Pre-validate serial numbers
                if (!empty($product['serials'])) {
                    $serials_raw = preg_split('/[\n,]+/', $product['serials']);
                    $valid_serial_count = 0;
                    foreach ($serials_raw as $s) {
                        $s = trim($s);
                        if (!empty($s)) {
                            $valid_serial_count++;

                            // 1. Check if typed twice in the same form
                            if (in_array($s, $submitted_serials)) {
                                $errors[] = "Row $rowNum: Serial number '$s' is entered multiple times in this form.";
                            }
                            $submitted_serials[] = $s;

                            // 2. Check if already exists in database
                            $exists = db_select_one('product_serials', ['serial_number' => $s]);
                            if ($exists) {
                                $errors[] = "Row $rowNum: Serial number '$s' already exists in the system.";
                            }
                        }
                    }

                    // 3. Check count
                    if ($valid_serial_count != ceil($product['quantity'])) {
                        $errors[] = "Row $rowNum: Requires " . ceil($product['quantity']) . " serial numbers, but $valid_serial_count were provided.";
                    }
                }
            }
        }

        $subtotal = 0;
        $tax_amount = 0;
        foreach ($_POST['products'] as $product) {
            $qty = (float)$product['quantity'];
            $price = (float)$product['price'];
            $tax = (float)($product['tax'] ?? 0);
            $subtotal += ($qty * $price);
            $tax_amount += ($qty * $price) * ($tax / 100);
        }
        $total_amount = $subtotal + $tax_amount - (float)($_POST['discount'] ?? 0);
        $paid_amount = (float)($_POST['paid_amount'] ?? 0);

        if ($paid_amount > $total_amount) {
            $errors[] = 'Paid amount cannot exceed total purchase amount (' . format_currency($total_amount) . ')';
        }

        // Validate account if payment is being made
        if ($paid_amount > 0) {
            $payment_method = clean_input($_POST['payment_method'] ?? 'cash');
            $account_id = (int)($_POST['account_id'] ?? 0);

            if (empty($account_id)) {
                $errors[] = 'Please select an account for payment';
            } else {
                // Validate account exists and has sufficient balance
                $account_table = ($payment_method === 'cash') ? 'cash_accounts' : 'bank_accounts';
                $account = db_select_one($account_table, ['id' => $account_id]);

                if (!$account) {
                    $errors[] = 'Selected account not found';
                } else if ($account['current_balance'] < $paid_amount) {
                    $errors[] = 'Insufficient balance in selected account. Available: ' . format_currency($account['current_balance']);
                }
            }
        }

        // Only proceed if no validation errors
        if (empty($errors)) {
            try {
                // Start transaction
                db_query("START TRANSACTION");

                // Calculate totals
                $subtotal = 0;
                $tax_amount = 0;

                foreach ($_POST['products'] as $product) {
                    $qty = (float)$product['quantity'];
                    $price = (float)$product['price'];
                    $tax = (float)($product['tax'] ?? 0);

                    $line_total = $qty * $price;
                    $line_tax = $line_total * ($tax / 100);

                    $subtotal += $line_total;
                    $tax_amount += $line_tax;
                }

                $total_amount = $subtotal + $tax_amount;
                $discount = (float)($_POST['discount'] ?? 0);
                $total_amount -= $discount;

                $paid_amount = (float)($_POST['paid_amount'] ?? 0);
                $due_amount = $total_amount - $paid_amount;

                // Determine payment status
                if ($paid_amount >= $total_amount) {
                    $payment_status = 'paid';
                } elseif ($paid_amount > 0) {
                    $payment_status = 'partial';
                } else {
                    $payment_status = 'unpaid';
                }

                // Generate purchase number
                $invoice_prefix = defined('PURCHASE_PREFIX') ? PURCHASE_PREFIX : 'PUR-';
                $purchase_number = $invoice_prefix . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

                // Get next ID manually (in case auto_increment isn't set)
                $max_id_result = db_query("SELECT MAX(id) as max_id FROM purchases");
                $next_id = 1;
                if (!empty($max_id_result) && isset($max_id_result[0]['max_id'])) {
                    $next_id = (int)$max_id_result[0]['max_id'] + 1;
                }

                // Insert purchase with all required fields
                $purchase_data = [
                    'id' => $next_id,
                    'purchase_number' => $purchase_number,
                    'supplier_id' => (int)$_POST['supplier_id'],
                    'purchase_date' => $_POST['purchase_date'],
                    'total_amount' => $total_amount,
                    'tax_amount' => $tax_amount,
                    'discount' => $discount,
                    'paid_amount' => $paid_amount,
                    'due_amount' => $due_amount,
                    'payment_status' => $payment_status,
                    'status' => $_POST['status'] ?? 'completed',
                    'notes' => $_POST['notes'] ?? null,
                    'created_by' => get_current_user_id(),
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $purchase_id = db_insert('purchases', $purchase_data);

                // If insert returned 0, use our manually generated ID
                if (!$purchase_id) {
                    $purchase_id = $next_id;
                }

                // Insert purchase items and update stock
                foreach ($_POST['products'] as $product) {
                    $product_id = (int)$product['product_id'];
                    $quantity = (float)$product['quantity'];
                    $unit_price = (float)$product['price'];
                    $tax = (float)($product['tax'] ?? 0);

                    // Handle serial numbers if provided
                    $serial_list = [];
                    if (!empty($product['serials'])) {
                        // Split by newline or comma and clean up
                        $serials_raw = preg_split('/[\n,]+/', $product['serials']);
                        foreach ($serials_raw as $s) {
                            $s = trim($s);
                            if (!empty($s)) {
                                $serial_list[] = $s;
                            }
                        }
                    }

                    // Calculate line totals
                    $line_subtotal = $quantity * $unit_price;
                    $line_tax = $line_subtotal * ($tax / 100);

                    // Get product info for description and warranty
                    $prod_info = db_select_one('products', ['id' => $product_id]);

                    // Insert purchase item
                    $item_data = [
                        'purchase_id' => $purchase_id,
                        'product_id' => $product_id,
                        'description' => $prod_info['description'] ?? '',
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'tax' => $line_tax,
                        'subtotal' => $line_subtotal + $line_tax,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    db_insert('purchase_items', $item_data);

                    // Insert serials into product_serials table
                    if (!empty($serial_list)) {
                        // Get product warranty info
                        $prod_info = db_select_one('products', ['id' => $product_id]);
                        $warranty_months = 0;
                        if ($prod_info['has_serial'] == 'Available') {
                            $duration = (int)$prod_info['warranty_duration'];
                            $period = $prod_info['warranty_period'];
                            if ($period == 'Year') {
                                $warranty_months = $duration * 12;
                            } elseif ($period == 'Month') {
                                $warranty_months = $duration;
                            } elseif ($period == 'Days') {
                                $warranty_months = ceil($duration / 30);
                            }
                        }

                        foreach ($serial_list as $sn) {
                            // Validation is already done at the top of the file

                            $serial_data = [
                                'product_id' => $product_id,
                                'serial_number' => $sn,
                                'purchase_id' => $purchase_id,
                                'purchase_date' => $_POST['purchase_date'],
                                'warranty_months' => $warranty_months,
                                'status' => 'in_stock',
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                            db_insert('product_serials', $serial_data);
                        }
                    }

                    // Update product stock if purchase is completed
                    if ($purchase_data['status'] === 'completed') {
                        db_query(
                            "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?",
                            [$quantity, $product_id]
                        );
                    }
                }

                // Record payment if any
                if ($paid_amount > 0) {
                    $payment_method = clean_input($_POST['payment_method'] ?? 'cash');
                    $account_id = (int)($_POST['account_id'] ?? 0);

                    $payment_data = [
                        'purchase_id' => $purchase_id,
                        'amount' => $paid_amount,
                        'payment_method' => $payment_method,
                        'account_id' => $account_id,
                        'payment_date' => $_POST['purchase_date'],
                        'notes' => 'Initial payment',
                        'created_by' => get_current_user_id(),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $payment_id = db_insert('purchase_payments', $payment_data);

                    // Deduct from account balance
                    $account_table = ($payment_method === 'cash') ? 'cash_accounts' : 'bank_accounts';
                    $account = db_select_one($account_table, ['id' => $account_id]);
                    db_update($account_table, [
                        'current_balance' => $account['current_balance'] - $paid_amount
                    ], ['id' => $account_id]);

                    // Record transaction in account ledger
                    $trans_table = ($payment_method === 'cash') ? 'cash_transactions' : 'bank_transactions';
                    $supplier = db_select_one('suppliers', ['id' => (int)$_POST['supplier_id']]);
                    db_insert($trans_table, [
                        'account_id' => $account_id,
                        'transaction_type' => 'debit',
                        'amount' => $paid_amount,
                        'reference_type' => 'supplier_payment',
                        'reference_id' => $payment_id,
                        'description' => "Payment to {$supplier['name']} for Purchase #{$purchase_number}",
                        'transaction_date' => $_POST['purchase_date'],
                        'created_by' => get_current_user_id()
                    ]);
                }

                // Update supplier ledger
                // 1. Record Purchase (Debit)
                db_insert('supplier_ledger', [
                    'supplier_id' => (int)$_POST['supplier_id'],
                    'transaction_type' => 'purchase',
                    'reference_id' => $purchase_id,
                    'debit' => $total_amount,
                    'credit' => 0,
                    'description' => "Purchase #{$purchase_number}",
                    'date' => $_POST['purchase_date'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                // 2. Record Payment (Credit) if any
                if ($paid_amount > 0) {
                    db_insert('supplier_ledger', [
                        'supplier_id' => (int)$_POST['supplier_id'],
                        'transaction_type' => 'payment',
                        'reference_id' => $purchase_id,
                        'debit' => 0,
                        'credit' => $paid_amount,
                        'description' => "Initial payment for Purchase #{$purchase_number}",
                        'date' => $_POST['purchase_date'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }

                // Update supplier current balance: add new due
                db_query(
                    "UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?",
                    [$due_amount, (int)$_POST['supplier_id']]
                );

                // Commit transaction
                db_query("COMMIT");

                log_activity(get_current_user_id(), 'add_purchase', "Added purchase ID: {$purchase_id}");
                redirect_with_message('purchases-list.php', 'Purchase added successfully', 'success');
            } catch (Exception $e) {
                db_query("ROLLBACK");
                $errors[] = 'Failed to add purchase: ' . $e->getMessage();
            }
        }
    } else {
        $errors[] = 'Invalid CSRF token';
    }
}

// ============================================
// 3. GET DROPDOWN DATA
// ============================================
$suppliers = db_select('suppliers', ['status' => 'active'], '*', 'name ASC');
$products = db_query("SELECT id, name, code, purchase_price, tax_rate, stock_quantity, has_serial, warranty_duration, warranty_period, image FROM products WHERE status = 'active' ORDER BY name ASC");

// ============================================
// 4. FRONTEND HTML
// ============================================
$page_title = 'Create Purchase Order';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    /* Smart UI Enhancements */
    .search-wrapper {
        position: relative;
    }

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1050;
        background: var(--bg-primary, #fff);
        border: 1px solid var(--border-color, #ddd);
        border-radius: 0 0 8px 8px;
        max-height: 320px;
        overflow-y: auto;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        display: none;
    }

    .search-item {
        padding: 10px 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid var(--border-color, #f0f0f0);
        transition: background 0.15s;
    }

    .search-item:hover {
        background: rgba(78, 115, 223, 0.06);
    }

    /* Cart Table */
    .cart-table {
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 0;
    }

    .cart-table thead th {
        background-color: #f8f9fc;
        color: #5a5c69;
        border-bottom: 2px solid #e3e6f0;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px;
    }

    .cart-table tbody td {
        vertical-align: middle;
        padding: 10px 12px;
    }

    .cart-table .select2-container .select2-selection--single {
        height: auto !important;
        min-height: 38px;
    }

    .cart-table .select2-container--default .select2-selection--single .select2-selection__rendered {
        white-space: normal !important;
        word-break: break-word;
        line-height: normal !important;
        padding-top: 5px;
        padding-bottom: 5px;
    }

    /* Summary Card */
    .summary-card .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        font-size: 14px;
        border-bottom: 1px dashed var(--border-color, #eee);
    }

    .summary-card .summary-row:last-child {
        border-bottom: none;
    }

    .summary-card .total-row {
        font-size: 22px;
        font-weight: 700;
        color: #4e73df;
        border-top: 2px solid var(--border-color, #ddd);
        padding-top: 15px;
        margin-top: 10px;
        border-bottom: none;
    }

    /* Quick Products Grid */
    .quick-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
        max-height: 500px;
        overflow-y: auto;
        padding-right: 5px;
    }

    .quick-item {
        padding: 12px;
        border: 1px solid var(--border-color, #e3e6f0);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
        background-color: #ffffff;
        display: flex;
        flex-direction: column;
        height: 100%;
        text-align: center;
    }

    @keyframes rowHighlight {
        0% {
            background-color: #fff3cd !important;
        }

        100% {
            background-color: transparent !important;
        }
    }

    .highlight-row {
        animation: rowHighlight 1.5s ease-out;
    }

    .quick-item:hover {
        border-color: #4e73df;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(78, 115, 223, 0.15);
    }

    .quick-item .product-img {
        height: 60px;
        object-fit: contain;
        margin-bottom: 8px;
    }

    .quick-item .q-name {
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 5px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.2;
        flex-grow: 1;
    }

    .quick-item .q-code {
        font-size: 11px;
        color: #888;
        margin-bottom: 5px;
    }

    .quick-item .q-price {
        color: #4e73df;
        font-weight: 700;
        font-size: 14px;
    }

    .quick-item .q-stock {
        font-size: 11px;
        color: #1cc88a;
        margin-top: 4px;
        font-weight: bold;
    }

    .quick-item .q-stock.low {
        color: #f6c23e;
    }

    .quick-item .q-stock.out {
        color: #e74a3b;
    }

    [data-theme="dark"] .quick-item {
        border-color: #444;
        background: #2c2c2c;
    }

    [data-theme="dark"] .quick-item:hover {
        border-color: #4e73df;
        background: #333;
    }

    [data-theme="dark"] .quick-item .q-name {
        color: #e0e0e0;
    }

    .select2-container .select2-selection--single {
        height: 38px !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
</style>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="purchaseForm">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

    <div class="row">
        <!-- Left Column: Items & Grid -->
        <div class="col-lg-8">
            <!-- Purchase Items -->
            <div class="card shadow-sm mb-3">
                <div class="card-header py-3" style="border-left: 4px solid #4e73df;">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-shopping-cart"></i> Purchase Items</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover cart-table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th width="25%" style="min-width: 200px;">Product</th>
                                    <th width="10%" class="qty-header text-center" style="min-width: 80px;">Qty</th>
                                    <th width="25%" class="serial-header" style="display: none; min-width: 180px;">Serial Numbers</th>
                                    <th width="15%" class="text-right" style="min-width: 120px;">Price</th>
                                    <th width="10%" class="text-center" style="min-width: 80px;">Tax %</th>
                                    <th width="10%" class="text-right" style="min-width: 100px;">Subtotal</th>
                                    <th width="5%" class="text-center" style="min-width: 40px;"><i class="fas fa-cog"></i></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr class="item-row">
                                    <td>
                                        <select name="products[0][product_id]" class="form-control product-select select2" required>
                                            <option value="">Search & Select Product...</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?= $product['id'] ?>"
                                                    data-price="<?= $product['purchase_price'] ?>"
                                                    data-tax="<?= $product['tax_rate'] ?>"
                                                    data-has-serial="<?= $product['has_serial'] ?>"
                                                    data-warranty-duration="<?= $product['warranty_duration'] ?>"
                                                    data-warranty-period="<?= $product['warranty_period'] ?>">
                                                    <?= htmlspecialchars($product['name']) ?> (Stock: <?= $product['stock_quantity'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="qty-cell">
                                        <input type="number" name="products[0][quantity]" class="form-control text-center quantity" step="0.01" min="0.01" value="1" required>
                                    </td>
                                    <td class="serial-cell" style="display: none;">
                                        <div class="serial-container">
                                            <textarea name="products[0][serials]" class="form-control serials" rows="1" placeholder="Enter serials..."></textarea>
                                            <small class="text-muted serial-count-msg d-block mt-1">Need: <span class="qty-count fw-bold">0</span></small>
                                        </div>
                                    </td>
                                    <td><input type="number" name="products[0][price]" class="form-control text-right price" step="0.01" min="0" required></td>
                                    <td><input type="number" name="products[0][tax]" class="form-control text-center tax" step="0.01" min="0" max="100" value="0"></td>
                                    <td><input type="text" class="form-control text-right item-total font-weight-bold text-primary" readonly value="0.00"></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="Remove"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add Row Button above Quick Add Products -->
            <div class="mb-4 text-right">
                <button type="button" class="btn btn-sm btn-primary shadow-sm px-4" id="addRow"><i class="fas fa-plus"></i> Add Row</button>
            </div>

            <!-- Product Selection Grid -->
            <div class="card shadow-sm mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center" style="border-left: 4px solid #1cc88a;">
                    <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-bolt"></i> Quick Add Products</h6>
                    <div class="input-group input-group-sm" style="width: auto; min-width: 250px;">
                        <span class="input-group-text bg-success text-white border-0"><i class="fas fa-search"></i></span>
                        <input type="text" id="gridSearch" class="form-control" placeholder="Filter products...">
                    </div>
                </div>
                <div class="card-body bg-light">
                    <div class="quick-grid" id="productGrid">
                        <?php foreach (array_slice($products, 0, 24) as $p):
                            $stockNum = (float)$p['stock_quantity'];
                            $stockClass = $stockNum > 10 ? '' : ($stockNum > 0 ? 'low' : 'out');
                        ?>
                            <div class="quick-item product-card add-grid-item"
                                data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>"
                                data-code="<?= htmlspecialchars(strtolower($p['code'] ?? '')) ?>"
                                data-id="<?= $p['id'] ?>">
                                <?php if (!empty($p['image'])): ?>
                                    <img src="<?= BASE_URL ?>/uploads/products/<?= htmlspecialchars($p['image']) ?>" class="product-img">
                                <?php else: ?>
                                    <div class="text-muted d-flex align-items-center justify-content-center" style="height: 60px; margin-bottom: 8px;">
                                        <i class="fas fa-box fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="q-name" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></div>
                                <?php if (!empty($p['code'])): ?>
                                    <div class="q-code"><?= htmlspecialchars($p['code']) ?></div>
                                <?php endif; ?>
                                <div class="q-price"><?= format_currency($p['purchase_price']) ?></div>
                                <div class="q-stock <?= $stockClass ?>">Stock: <?= $stockNum ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($products) > 24): ?>
                        <div class="text-center mt-3 text-muted small">
                            Showing top 24 products. Use search to find more.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column (Invoice Details) -->
        <div class="col-lg-4">
            <!-- Purchase Details -->
            <div class="card shadow-sm mb-4 summary-card">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-file-invoice"></i> Purchase Details</h6>
                </div>
                <div class="card-body">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-control select2" required>
                            <option value="">Search Supplier...</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-dark">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold text-dark">Status</label>
                            <select name="status" class="form-control">
                                <option value="completed" selected>Completed</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                    </div>

                    <hr class="mt-2 mb-3">

                    <div class="summary-row">
                        <span class="text-muted">Subtotal</span>
                        <strong><?= APP_CURRENCY_SYMBOL ?><span id="display_subtotal">0.00</span></strong>
                        <input type="hidden" id="subtotal" name="subtotal" value="0.00">
                    </div>

                    <div class="summary-row">
                        <span class="text-muted">Tax</span>
                        <strong><?= APP_CURRENCY_SYMBOL ?><span id="display_tax">0.00</span></strong>
                        <input type="hidden" id="tax" name="tax" value="0.00">
                    </div>

                    <div class="summary-row align-items-center">
                        <span class="text-muted">Discount</span>
                        <div class="input-group input-group-sm" style="width: 130px;">
                            <div class="input-group-prepend"><span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span></div>
                            <input type="number" name="discount" id="discount" class="form-control text-right" step="0.01" min="0" value="0">
                        </div>
                    </div>

                    <div class="summary-row total-row">
                        <span>Net Total</span>
                        <span id="display_total"><?= APP_CURRENCY_SYMBOL ?>0.00</span>
                        <input type="hidden" id="totalAmount" name="total_amount" value="0.00">
                    </div>

                    <!-- Advanced Payment Toggle -->
                    <div class="mt-4 pt-3 border-top">
                        <button class="btn btn-outline-primary btn-sm mb-3 w-100" type="button" data-bs-toggle="collapse" data-bs-target="#paymentSection">
                            <i class="fas fa-money-bill-wave"></i> Add Direct Payment
                        </button>

                        <div class="collapse" id="paymentSection">
                            <div class="bg-light p-3 rounded mb-3 border">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Payment Method</label>
                                    <select name="payment_method" id="payment_method" class="form-control form-control-sm">
                                        <option value="cash">Cash</option>
                                        <option value="bank">Bank Transfer</option>
                                        <option value="card">Card</option>
                                        <option value="cheque">Cheque</option>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Withdraw From Account</label>
                                    <select name="account_id" id="account_id" class="form-control form-control-sm">
                                        <option value="">Select Account</option>
                                    </select>
                                    <small class="form-text text-muted">A debit entry will be created.</small>
                                </div>

                                <div class="row">
                                    <div class="col-6 form-group">
                                        <label class="small font-weight-bold">Paid Amount</label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span></div>
                                            <input type="number" name="paid_amount" id="paidAmount" class="form-control" step="0.01" min="0" value="0">
                                        </div>
                                    </div>
                                    <div class="col-6 form-group">
                                        <label class="small font-weight-bold text-danger">Due Amount</label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text bg-danger text-white border-danger"><?= APP_CURRENCY_SYMBOL ?></span></div>
                                            <input type="text" id="dueAmount" class="form-control text-right" readonly value="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="text-muted small"><i class="fas fa-pencil-alt"></i> Notes</label>
                            <textarea name="notes" class="form-control border-left-info" rows="2" placeholder="Private order notes..."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm"><i class="fas fa-check-circle"></i> Save Purchase</button>
                            <a href="purchases-list.php" class="btn btn-light border"><i class="fas fa-arrow-left"></i> Back to List</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    let rowIndex = 1;

    // Add new row
    $('#addRow').click(function() {
        const newRow = `
        <tr class="item-row">
            <td>
                <select name="products[${rowIndex}][product_id]" class="form-control product-select select2" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= $product['id'] ?>" 
                            data-price="<?= $product['purchase_price'] ?>"
                            data-tax="<?= $product['tax_rate'] ?>"
                            data-has-serial="<?= $product['has_serial'] ?>"
                            data-warranty-duration="<?= $product['warranty_duration'] ?>"
                            data-warranty-period="<?= $product['warranty_period'] ?>">
                            <?= htmlspecialchars($product['name']) ?> (Stock: <?= $product['stock_quantity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="qty-cell">
                <input type="number" name="products[${rowIndex}][quantity]" class="form-control quantity" step="0.01" min="0.01" value="1" required>
            </td>
            <td class="serial-cell" style="display: none;">
                <div class="serial-container">
                    <textarea name="products[${rowIndex}][serials]" class="form-control serials" rows="2" placeholder="One serial per line (min 3 digits)"></textarea>
                    <small class="text-muted serial-count-msg">Enter <span class="qty-count">0</span> serial(s)</small>
                </div>
            </td>
            <td><input type="number" name="products[${rowIndex}][price]" class="form-control price" step="0.01" min="0" required></td>
            <td><input type="number" name="products[${rowIndex}][tax]" class="form-control tax" step="0.01" min="0" max="100" value="0"></td>
            <td><input type="text" class="form-control item-total" readonly value="0.00"></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-row"><i class="fas fa-trash"></i></button></td>
        </tr>
    `;
        $('#itemsBody').append(newRow);
        rowIndex++;
    });

    // Remove row
    $(document).on('click', '.remove-row', function() {
        if ($('.item-row').length > 1) {
            $(this).closest('tr').remove();
            calculateTotals();
        } else {
            alert('At least one item is required');
        }
    });

    // Grid Search Filter
    $('#gridSearch').on('keyup', function() {
        let val = $(this).val().toLowerCase().trim();
        if (val === '') {
            $('.product-card').show();
        } else {
            $('.product-card').each(function() {
                let name = $(this).data('name') || '';
                let code = $(this).data('code') || '';
                if (name.includes(val) || code.includes(val)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    });

    // Click Grid Item to Auto-Add
    $(document).on('click', '.add-grid-item', function() {
        const productId = $(this).data('id');

        // Check if there is an empty row at the bottom we can use
        let $lastRow = $('.item-row').last();
        let lastSelectVal = $lastRow.find('.product-select').val();

        if (lastSelectVal) {
            // Last row has a product, click Add Row first
            $('#addRow').click();
            $lastRow = $('.item-row').last();
        }

        // Select the product and trigger change
        let $select = $lastRow.find('.product-select');
        $select.val(productId).trigger('change');

        // Slight flash animation to show it was added
        $lastRow.removeClass('highlight-row'); // Reset if clicked quickly again
        void $lastRow[0].offsetWidth; // Trigger reflow to restart animation
        $lastRow.addClass('highlight-row');
    });

    // When product is selected, fill price and tax, and toggle serials
    $(document).on('change', '.product-select', function() {
        const row = $(this).closest('tr');
        const selected = $(this).find(':selected');
        const price = selected.data('price');
        const tax = selected.data('tax');
        const hasSerial = selected.data('has-serial');

        if (selected.val()) {
            row.find('.price').val(price || 0);
            row.find('.tax').val(tax || 0);

            if (hasSerial === 'Available') {
                $('.serial-header').show();
                $('.qty-header').hide();
                row.find('.serial-cell').show();
                row.find('.serials').attr('required', true).val(''); // Clear serials on product change
                row.find('.qty-count').text(0);
                row.find('.qty-cell').hide();
                row.find('.quantity').val(0).prop('readonly', true);
            } else {
                row.find('.serial-cell').hide();
                row.find('.serials').attr('required', false).val('');
                row.find('.qty-cell').show();
                row.find('.quantity').val(1).prop('readonly', false);

                // Hide header if no other row has serials
                if ($('.serial-cell:visible').length === 0) {
                    $('.serial-header').hide();
                    $('.qty-header').show();
                }
            }
        } else {
            row.find('.price').val(0);
            row.find('.tax').val(0);
            row.find('.serial-container').hide();
            row.find('.serials').attr('required', false).val('');
            row.find('.quantity').parent().show();
        }

        calculateRowTotal(row);
    });

    // Calculate quantity from serials and check for duplicates
    let serialCheckTimeout;
    $(document).on('input propertychange paste blur change', '.serials', function() {
        const row = $(this).closest('tr');
        const serialsTextarea = $(this);
        const serials = $(this).val().split('\n').filter(s => s.trim().length > 0);

        // Validate min 3 digits for each serial
        let validCount = 0;
        serials.forEach(s => {
            if (s.trim().length >= 3) validCount++;
        });

        row.find('.quantity').val(validCount);
        row.find('.qty-count').text(validCount);
        calculateRowTotal(row);

        // Debounce the duplicate check
        clearTimeout(serialCheckTimeout);
        serialCheckTimeout = setTimeout(function() {
            checkSerialDuplicates(serialsTextarea, row);
        }, 200);
    });

    // Check for duplicate serials
    function checkSerialDuplicates(textarea, row) {
        const serialsValue = textarea.val().trim();
        $('.local-serial-warning').remove();
        row.find('.api-serial-warning').remove();

        if (!serialsValue) return;

        // 1. Check duplicates locally in the SAME FORM across all textareas
        let allCurrentSerials = [];
        let localDuplicates = new Set();

        $('#productTable tbody .serials').each(function() {
            let text = $(this).val().trim();
            if (text) {
                let splitList = text.split(/[\n,]+/).map(s => s.trim()).filter(s => s.length > 0);
                splitList.forEach(s => {
                    if (allCurrentSerials.includes(s)) {
                        localDuplicates.add(s);
                    } else {
                        allCurrentSerials.push(s);
                    }
                });
            }
        });

        if (localDuplicates.size > 0) {
            let dupArr = Array.from(localDuplicates);
            let warningMsg = `<div class="alert alert-danger mt-2 local-serial-warning py-2 mb-0">
            <strong><i class="fas fa-exclamation-triangle"></i> Duplicate Input:</strong> ${dupArr.length} serial(s) typed multiple times: 
            <strong>${dupArr.join(', ')}</strong>
        </div>`;
            row.find('.serial-container').after(warningMsg);

            // Disable submit button
            $('button[type="submit"]').prop('disabled', true);
            return; // Stop here if locally duplicated
        } else {
            if ($('.api-serial-warning').length === 0) {
                $('button[type="submit"]').prop('disabled', false);
            }
        }

        // 2. Check duplicates in Database via API
        let currentBoxSerials = serialsValue.split(/[\n,]+/).map(s => s.trim()).filter(s => s.length > 0);

        $.ajax({
            url: '<?= BASE_URL ?>/api/products/check-serial-duplicates.php',
            method: 'POST',
            data: {
                serials: JSON.stringify(currentBoxSerials)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.duplicates && response.duplicates.length > 0) {
                    // Show warning
                    let warningMsg = `<div class="alert alert-danger mt-2 api-serial-warning py-2 mb-0">
                    <strong><i class="fas fa-exclamation-triangle"></i> Error!</strong> ${response.duplicates.length} serial(s) already exist in database: 
                    <strong>${response.duplicates.join(', ')}</strong>
                </div>`;

                    row.find('.serial-container').after(warningMsg);

                    // Disable submit
                    $('button[type="submit"]').prop('disabled', true);
                } else {
                    row.find('.api-serial-warning').remove();
                    if ($('.local-serial-warning').length === 0 && $('.api-serial-warning').length === 0) {
                        $('button[type="submit"]').prop('disabled', false);
                    }
                }
            }
        });
    }

    // Update serial count message when quantity changes
    $(document).on('input', '.quantity', function() {
        const row = $(this).closest('tr');
        const qty = Math.ceil(parseFloat($(this).val()) || 0);
        row.find('.qty-count').text(qty);
    });

    // Calculate row total
    function calculateRowTotal(row) {
        const quantity = parseFloat(row.find('.quantity').val()) || 0;
        const price = parseFloat(row.find('.price').val()) || 0;
        const tax = parseFloat(row.find('.tax').val()) || 0;

        const subtotal = quantity * price;
        const taxAmount = subtotal * (tax / 100);
        const total = subtotal + taxAmount;

        row.find('.item-total').val(total.toFixed(2));
        calculateTotals();
    }

    // Calculate all totals
    function calculateTotals() {
        let subtotal = 0;
        let taxTotal = 0;

        $('.item-row').each(function() {
            const quantity = parseFloat($(this).find('.quantity').val()) || 0;
            const price = parseFloat($(this).find('.price').val()) || 0;
            const tax = parseFloat($(this).find('.tax').val()) || 0;

            const lineSubtotal = quantity * price;
            const lineTax = lineSubtotal * (tax / 100);

            subtotal += lineSubtotal;
            taxTotal += lineTax;
        });

        const discount = parseFloat($('#discount').val()) || 0;
        const total = subtotal + taxTotal - discount;
        const paid = parseFloat($('#paidAmount').val()) || 0;
        const due = total - paid;

        $('#subtotal').val(subtotal.toFixed(2));
        $('#tax').val(taxTotal.toFixed(2));
        $('#totalAmount').val(total.toFixed(2));
        $('#dueAmount').val(due.toFixed(2));

        // Update display spans
        $('#display_subtotal').text(subtotal.toFixed(2));
        $('#display_tax').text(taxTotal.toFixed(2));
        $('#display_total').text(total.toFixed(2));
    }

    // Recalculate on input change
    $(document).on('input', '.quantity, .price, .tax', function() {
        calculateRowTotal($(this).closest('tr'));
    });

    $(document).on('input', '#discount, #paidAmount', function() {
        calculateTotals();
    });

    // Initial calculation
    calculateTotals();

    // Load accounts when payment method changes
    $('#payment_method').change(function() {
        var method = $(this).val();
        loadAccounts(method);
    });

    // Load accounts on page load (default to cash)
    loadAccounts('cash');

    function loadAccounts(paymentMethod) {
        var accountSelect = $('#account_id');
        accountSelect.html('<option value="">Loading...</option>');

        $.get('../../api/accounts/get-accounts.php', {
            type: paymentMethod
        }, function(res) {
            if (res.status) {
                accountSelect.html('<option value="">Select Account</option>');
                if (res.data && res.data.length > 0) {
                    res.data.forEach(function(acc) {
                        var name = paymentMethod === 'cash' ? acc.account_name :
                            acc.bank_name + ' (' + acc.account_number + ')';
                        var balance = parseFloat(acc.current_balance).toFixed(2);
                        accountSelect.append(
                            '<option value="' + acc.id + '" data-balance="' + balance + '">' +
                            name + ' (Balance: <?= APP_CURRENCY_SYMBOL ?>' + balance + ')</option>'
                        );
                    });
                } else {
                    accountSelect.html('<option value="">No accounts available</option>');
                }
            } else {
                accountSelect.html('<option value="">Error loading accounts</option>');
            }
        }).fail(function() {
            accountSelect.html('<option value="">Error loading accounts</option>');
        });
    }

    // Validate account selection before form submission
    $('#purchaseForm').submit(function(e) {
        // 1. Synchronous strict local duplicate check
        let allSerials = [];
        let localDups = new Set();

        $('#itemsBody .serials').each(function() {
            let text = $(this).val();
            if (text && text.trim().length > 0) {
                let splitList = text.split(/[\n,]+/).map(s => s.trim()).filter(s => s.length > 0);
                splitList.forEach(s => {
                    if (allSerials.includes(s)) {
                        localDups.add(s);
                    } else {
                        allSerials.push(s);
                    }
                });
            }
        });

        if (localDups.size > 0) {
            e.preventDefault();
            alert('Duplicate Serial Numbers found in your form: ' + Array.from(localDups).join(', ') + '\nPlease remove the duplicates before submitting.');
            return false;
        }

        // 2. Check for active API warnings
        if ($('.api-serial-warning').length > 0) {
            e.preventDefault();
            alert('Please fix the duplicate serial numbers (already in database) before submitting.');
            return false;
        }

        var paidAmount = parseFloat($('#paidAmount').val()) || 0;
        var accountId = $('#account_id').val();

        if (paidAmount > 0 && !accountId) {
            e.preventDefault();
            alert('Please select an account for payment withdrawal');
            return false;
        }

        return true;
    });

    // Initialize Select2 on page load for existing product dropdowns
    $(document).ready(function() {
        $('.product-select').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // Re-initialize Select2 after adding new rows
        const originalAddRow = $('#addRow').get(0);
        if (originalAddRow) {
            $('#addRow').on('click', function() {
                // Small delay to ensure DOM is updated
                setTimeout(function() {
                    $('.product-select').not('.select2-hidden-accessible').select2({
                        theme: 'bootstrap-5',
                        width: '100%'
                    });
                }, 100);
            });
        }
    });
</script>
