<?php

/**
 * Service View/Edit - Manage Individual Service Ticket
 * Status updates, AJAX parts management, billing, delivery with accounting
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$ticket_id = (int)get_param('id', 0);
if (!$ticket_id) redirect_with_message('service-list.php', 'Invalid ticket', 'error');

$ticket = db_select_one('service_tickets', ['id' => $ticket_id]);
if (!$ticket) redirect_with_message('service-list.php', 'Ticket not found', 'error');

// Get distinct device types for dropdown
$default_devices = ['Laptop', 'Desktop PC', 'Printer', 'Monitor', 'UPS', 'Router', 'Other'];
$existing_devices = db_query("SELECT DISTINCT device_type FROM service_tickets WHERE device_type IS NOT NULL AND device_type != ''");
$db_devices = array_column($existing_devices, 'device_type');
$all_devices = array_unique(array_merge($default_devices, $db_devices));
sort($all_devices);

$errors = [];

// Handle Edit Ticket Info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $customer_name = clean_input($_POST['customer_name']);
        $customer_phone = clean_input($_POST['customer_phone']);
        $device_type = clean_input($_POST['device_type']);
        $brand = clean_input($_POST['brand']);
        $model = clean_input($_POST['model']);
        $serial_number = clean_input($_POST['serial_number']);
        $warranty_status = clean_input($_POST['warranty_status']);
        $estimated_delivery_date = !empty($_POST['estimated_delivery_date']) ? clean_input($_POST['estimated_delivery_date']) : null;
        $problem_description = clean_input($_POST['problem_description']);
        $physical_condition = clean_input($_POST['physical_condition']);

        if (empty($customer_name) || empty($device_type) || empty($problem_description)) {
            $errors[] = "Customer name, device type, and problem description are required.";
        } else {
            db_update('service_tickets', [
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'device_type' => $device_type,
                'brand' => $brand,
                'model' => $model,
                'serial_number' => $serial_number,
                'warranty_status' => $warranty_status,
                'estimated_delivery_date' => $estimated_delivery_date,
                'problem_description' => $problem_description,
                'physical_condition' => $physical_condition,
            ], ['id' => $ticket_id]);

            log_activity(get_current_user_id(), 'service_edit', "Updated ticket details for {$ticket['ticket_number']}");
            redirect_with_message("service-view.php?id=$ticket_id", "Ticket information updated", 'success');
        }
    }
}

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $new_status = clean_input($_POST['new_status']);
        $status_notes = clean_input($_POST['status_notes']);
        $old_status = $ticket['status'];

        if ($new_status !== $old_status) {
            db_update('service_tickets', ['status' => $new_status], ['id' => $ticket_id]);
            db_insert('service_status_log', [
                'ticket_id' => $ticket_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'notes' => $status_notes,
                'changed_by' => get_current_user_id()
            ]);
            log_activity(get_current_user_id(), 'service_status', "Updated {$ticket['ticket_number']} from $old_status to $new_status");
            redirect_with_message("service-view.php?id=$ticket_id", "Status updated to $new_status", 'success');
        }
    }
}

// Handle Update Service Charge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_charges'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $service_charge = (float)$_POST['service_charge'];
        $discount = (float)$_POST['discount'];
        $new_total = $service_charge + $ticket['total_parts_cost'] - $discount;
        db_update('service_tickets', [
            'service_charge' => $service_charge,
            'discount' => $discount,
            'total_amount' => $new_total,
            'technician_notes' => clean_input($_POST['technician_notes'])
        ], ['id' => $ticket_id]);
        redirect_with_message("service-view.php?id=$ticket_id", "Charges updated", 'success');
    }
}

// Handle Delivery & Payment (with proper accounting)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deliver_ticket'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $paid_amount = (float)$_POST['paid_amount'];
        $payment_method = clean_input($_POST['payment_method']);
        $payment_account_id = !empty($_POST['payment_account_id']) ? (int)$_POST['payment_account_id'] : null;

        $payment_status = 'Paid';
        if ($paid_amount < $ticket['total_amount']) $payment_status = 'Partial';
        if ($paid_amount <= 0) $payment_status = 'Unpaid';

        db_begin_transaction();
        try {
            // Update ticket
            db_update('service_tickets', [
                'status' => 'Delivered',
                'payment_status' => $payment_status,
                'paid_amount' => $paid_amount,
                'payment_method' => $payment_method,
                'payment_account_id' => $payment_account_id,
                'delivered_at' => date('Y-m-d H:i:s')
            ], ['id' => $ticket_id]);

            // Log status change
            db_insert('service_status_log', [
                'ticket_id' => $ticket_id,
                'old_status' => $ticket['status'],
                'new_status' => 'Delivered',
                'notes' => "Delivered. Payment: $payment_status ($payment_method) - " . format_currency($paid_amount),
                'changed_by' => get_current_user_id()
            ]);

            // === Financial Logic ===
            if ($paid_amount > 0 && $payment_account_id) {
                $is_cash = ($payment_method === 'Cash');
                $acc_table = $is_cash ? 'cash_accounts' : 'bank_accounts';
                $trans_table = $is_cash ? 'cash_transactions' : 'bank_transactions';

                $account = db_select_one($acc_table, ['id' => $payment_account_id]);
                if ($account) {
                    db_update($acc_table, [
                        'current_balance' => $account['current_balance'] + $paid_amount
                    ], ['id' => $payment_account_id]);

                    db_insert($trans_table, [
                        'account_id' => $payment_account_id,
                        'transaction_type' => 'credit',
                        'amount' => $paid_amount,
                        'reference_type' => 'service_payment',
                        'reference_id' => $ticket_id,
                        'description' => "Service Payment: {$ticket['ticket_number']} ($payment_method)",
                        'transaction_date' => date('Y-m-d'),
                        'created_by' => get_current_user_id()
                    ]);

                    // Petty Cash sync (cash only)
                    if ($is_cash) {
                        $acc_name_lower = strtolower($account['account_name']);
                        if (strpos($acc_name_lower, 'cash in hand') !== false || strpos($acc_name_lower, 'petty cash') !== false) {
                            $counterpart_name = (strpos($acc_name_lower, 'cash in hand') !== false) ? 'petty cash' : 'cash in hand';
                            $counterpart = db_query_one("SELECT id, current_balance FROM cash_accounts WHERE LOWER(account_name) LIKE ? AND id != ?", ["%$counterpart_name%", $payment_account_id]);
                            if ($counterpart) {
                                db_update('cash_accounts', [
                                    'current_balance' => $counterpart['current_balance'] + $paid_amount
                                ], ['id' => $counterpart['id']]);
                                db_insert('cash_transactions', [
                                    'account_id' => $counterpart['id'],
                                    'transaction_type' => 'credit',
                                    'amount' => $paid_amount,
                                    'reference_type' => 'service_sync',
                                    'reference_id' => $ticket_id,
                                    'description' => "Auto-sync: {$ticket['ticket_number']}",
                                    'transaction_date' => date('Y-m-d'),
                                    'created_by' => get_current_user_id()
                                ]);
                            }
                        }
                    }
                }
            }

            log_activity(get_current_user_id(), 'service_deliver', "Delivered {$ticket['ticket_number']}, Payment: " . format_currency($paid_amount) . " via $payment_method");
            db_commit();
            redirect_with_message("service-view.php?id=$ticket_id", "Ticket delivered and payment recorded!", 'success');
        } catch (Exception $e) {
            db_rollback();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Refresh ticket data
$ticket = db_select_one('service_tickets', ['id' => $ticket_id]);
$parts = db_query("SELECT * FROM service_ticket_parts WHERE ticket_id = ? ORDER BY id", [$ticket_id]);
$status_log = db_query("SELECT sl.*, u.username as changed_by_name FROM service_status_log sl LEFT JOIN users u ON sl.changed_by = u.id WHERE sl.ticket_id = ? ORDER BY sl.id DESC", [$ticket_id]);
$cash_accounts = db_select('cash_accounts', [], '*', 'account_name ASC');
$bank_accounts = db_select('bank_accounts', [], '*', 'bank_name ASC');

$statuses = ['Pending', 'Inspection', 'Waiting for Parts', 'In Progress', 'Ready to Deliver', 'Delivered', 'Cannot be Fixed'];

// Service products (products marked as service parts)
$service_products = db_query("SELECT id, name, code, selling_price, stock_quantity, has_serial FROM products WHERE is_service_product = 1 AND status = 'active' ORDER BY name ASC") ?: [];

// Currency symbol
$APP_CURRENCY_SYMBOL = '৳';

$page_title = 'Service Ticket: ' . $ticket['ticket_number'];
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .ticket-header {
        background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
        color: white;
        padding: 20px 25px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .ticket-header .ticket-no {
        font-size: 1.8rem;
        font-weight: bold;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .info-grid .info-item label {
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 2px;
        display: block;
    }

    .info-grid .info-item span {
        font-size: 0.95rem;
    }

    .status-timeline {
        position: relative;
        padding-left: 25px;
    }

    .status-timeline::before {
        content: '';
        position: absolute;
        left: 8px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #d1d5db;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 15px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -21px;
        top: 5px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #14b8a6;
        border: 2px solid white;
    }

    .parts-table th {
        font-size: 0.8rem;
        text-transform: uppercase;
    }

    [data-theme="dark"] .info-grid .info-item label {
        color: #94a3b8;
    }

    [data-theme="dark"] .info-grid .info-item span {
        color: #e2e8f0;
    }

    /* AJAX feedback */
    .ajax-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: 8px;
        display: none;
    }

    .ajax-overlay.active {
        display: flex;
    }

    .stock-badge {
        font-size: 0.75rem;
        padding: 2px 6px;
        border-radius: 4px;
    }

    .stock-ok {
        background: #d1fae5;
        color: #065f46;
    }

    .stock-low {
        background: #fef3c7;
        color: #92400e;
    }

    .stock-out {
        background: #fecaca;
        color: #991b1b;
    }

    #productSuggestions .list-group-item {
        cursor: pointer;
        font-size: 0.85rem;
    }

    #productSuggestions .list-group-item:hover {
        background-color: #f0fdfa;
    }

    [data-theme="dark"] #productSuggestions .list-group-item:hover {
        background-color: #1e293b;
    }

    [data-theme="dark"] #productSuggestions .list-group-item {
        background-color: #1e293b;
        color: #e2e8f0;
        border-color: #334155;
    }

    .toast-msg {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
    }

    /* Service Products Grid */
    .svc-product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 8px;
    }

    .svc-product-item {
        padding: 8px 10px;
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 12px;
    }

    .svc-product-item:hover {
        border-color: #0d9488;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(13, 148, 136, 0.15);
    }

    .svc-product-item .sp-name {
        font-weight: 600;
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .svc-product-item .sp-price {
        color: #0d9488;
        font-weight: 700;
    }

    .svc-product-item .sp-stock {
        font-size: 10px;
    }

    /* Print: hide links */
        @media print {
            a[href]:after {
                content: none !important;
            }
        }

        /* Serial Selection Modal Styles */
        .serial-selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 8px;
            background: #f8fafc;
        }

        [data-theme="dark"] .serial-selection-grid {
            background: #0f172a;
            border-color: #334155;
        }

        .serial-item {
            display: flex;
            align-items: center;
            background: white;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }

        [data-theme="dark"] .serial-item {
            background: #1e293b;
            border-color: #334155;
            color: #f1f5f9;
        }

        .serial-item:hover {
            border-color: #0d9488;
            background: #f0fdfa;
        }

        [data-theme="dark"] .serial-item:hover {
            background: #115e59;
        }

        .serial-item.selected {
            background: #0d9488;
            color: white;
            border-color: #0d9488;
        }

        .serial-item input {
            display: none;
        }

        /* Specific Dark Mode overrides for the modal layout */
        [data-theme="dark"] #serialSelectionModal .bg-light {
            background-color: #0f172a !important;
        }
        [data-theme="dark"] #serialSelectionModal .bg-white {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        [data-theme="dark"] #serialSelectionModal .text-muted {
            color: #94a3b8 !important;
        }
        [data-theme="dark"] #serialSelectionModal .border-bottom {
            border-bottom-color: #334155 !important;
        }
    </style>

<!-- Toast notification -->
<div id="toastContainer" class="toast-msg"></div>

<!-- Header -->
<div class="ticket-header shadow">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <div class="ticket-no"><?= htmlspecialchars($ticket['ticket_number']) ?></div>
            <small><?= htmlspecialchars($ticket['customer_name']) ?> | <?= htmlspecialchars($ticket['customer_phone']) ?></small>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark fs-6"><?= $ticket['status'] ?></span><br>
            <small>Created: <?= date('d M Y, h:i A', strtotime($ticket['created_at'])) ?></small>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Left: Ticket Info + Parts -->
    <div class="col-lg-8">
        <!-- Ticket Details Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle"></i> Ticket Details</h6>
                <?php if ($ticket['status'] !== 'Delivered'): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editInfoModal"><i class="fas fa-edit"></i> Edit</button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item"><label>Device Type</label><span><?= htmlspecialchars($ticket['device_type']) ?></span></div>
                    <div class="info-item"><label>Brand / Model</label><span><?= htmlspecialchars(($ticket['brand'] ?? '-') . ' ' . ($ticket['model'] ?? '')) ?></span></div>
                    <div class="info-item"><label>Serial / MAC / IMEI</label><span><?= htmlspecialchars($ticket['serial_number'] ?? '-') ?></span></div>
                    <div class="info-item"><label>Warranty Status</label><span><?= htmlspecialchars($ticket['warranty_status']) ?></span></div>
                    <div class="info-item"><label>Est. Delivery</label><span><?= $ticket['estimated_delivery_date'] ? date('d M Y', strtotime($ticket['estimated_delivery_date'])) : '-' ?></span></div>
                    <div class="info-item"><label>Created By</label><span><?= htmlspecialchars($ticket['created_by']) ?></span></div>
                </div>
                <hr>
                <div class="mb-3"><label class="fw-bold text-muted small">PROBLEM DESCRIPTION</label>
                    <p><?= nl2br(htmlspecialchars($ticket['problem_description'])) ?></p>
                </div>
                <?php if ($ticket['physical_condition']): ?>
                    <div><label class="fw-bold text-muted small">PHYSICAL CONDITION</label>
                        <p><?= nl2br(htmlspecialchars($ticket['physical_condition'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Parts Card (AJAX) -->
        <?php if (!in_array($ticket['status'], ['Pending', 'Ready to Deliver', 'Delivered', 'Cannot be Fixed'])): ?>
            <div class="card shadow mb-4 position-relative">
                <div class="ajax-overlay" id="partsOverlay">
                    <div class="spinner-border text-primary"></div>
                </div>
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-cogs"></i> Parts & Components</h6>
                </div>
                <div class="card-body">
                    <!-- AJAX Add Part Form -->
                    <div class="mb-3 p-3 rounded border" id="addPartForm">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4 position-relative">
                                <label class="form-label small">Product / Part Name</label>
                                <input type="text" id="part_name" class="form-control form-control-sm" placeholder="Search from inventory..." autocomplete="off">
                                <input type="hidden" id="part_product_id">
                                <div id="productSuggestions" class="list-group position-absolute w-100" style="z-index:1050; max-height:250px; overflow-y:auto; display:none;"></div>
                            </div>
                            <div class="col-md-2" id="qty_col">
                                <label class="form-label small">Qty</label>
                                <input type="number" id="part_qty" class="form-control form-control-sm" value="1" min="1">
                            </div>
                            <div class="col-md-3" id="serial_col" style="display:none;">
                                <label class="form-label small">Serial(s)</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="part_serials" class="form-control" placeholder="Comma separated...">
                                    <button type="button" id="btnSelectSerials" class="btn btn-outline-info"><i class="fas fa-list"></i> Select</button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Unit Price</label>
                                <input type="number" id="part_price" class="form-control form-control-sm" step="0.01" value="0">
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="btnAddPart" class="btn btn-primary btn-sm w-100"><i class="fas fa-plus"></i> Add Part</button>
                            </div>
                        </div>
                        <div id="stockInfo" class="mt-2" style="display:none;"></div>
                    </div>

                    <!-- Parts Table (dynamically updated) -->
                    <div id="partsTableContainer">
                        <?php include __DIR__ . '/../../templates/_service_parts_table.php'; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Service Products Grid -->
        <?php if (!empty($service_products) && !in_array($ticket['status'], ['Pending', 'Ready to Deliver', 'Delivered', 'Cannot be Fixed'])): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tools"></i> Service Products</h6>
                </div>
                <div class="card-body">
                    <div class="svc-product-grid">
                        <?php foreach ($service_products as $sp): ?>
                            <div class="svc-product-item"
                                data-id="<?= $sp['id'] ?>" data-name="<?= htmlspecialchars($sp['name']) ?>"
                                data-price="<?= $sp['selling_price'] ?>" data-stock="<?= $sp['stock_quantity'] ?>"
                                data-serial="<?= $sp['has_serial'] === 'Available' ? 'true' : 'false' ?>"
                                title="Click to add as part">
                                <div class="sp-name"><?= htmlspecialchars($sp['name']) ?></div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span class="sp-price"><?= APP_CURRENCY_SYMBOL ?><?= number_format($sp['selling_price'], 0) ?></span>
                                    <span class="sp-stock badge <?= $sp['stock_quantity'] > 5 ? 'bg-success' : ($sp['stock_quantity'] > 0 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                        <?= $sp['stock_quantity'] ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle"></i> Click a product to auto-fill the Parts form above</small>
                </div>
            </div>
        <?php endif; ?>

        <!-- Billing Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calculator"></i> Billing</h6>
            </div>
            <div class="card-body">
                <?php if (!in_array($ticket['status'], ['Pending', 'Cannot be Fixed', 'Delivered'])): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="update_charges" value="1">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Service Charge</label>
                                <input type="number" name="service_charge" class="form-control" step="0.01" value="<?= $ticket['service_charge'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Discount</label>
                                <input type="number" name="discount" class="form-control" step="0.01" value="<?= $ticket['discount'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Technician Notes</label>
                                <input type="text" name="technician_notes" class="form-control" value="<?= htmlspecialchars($ticket['technician_notes'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mt-3"><button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update Charges</button></div>
                    </form>
                    <hr>
                <?php endif; ?>
                <div id="billingSummary">
                    <table class="table table-sm">
                        <tr>
                            <td>Parts Cost:</td>
                            <td class="text-end" id="billPartsCost"><?= format_currency($ticket['total_parts_cost']) ?></td>
                        </tr>
                        <tr>
                            <td>Service Charge:</td>
                            <td class="text-end"><?= format_currency($ticket['service_charge']) ?></td>
                        </tr>
                        <?php if ($ticket['discount'] > 0): ?><tr>
                                <td>Discount:</td>
                                <td class="text-end text-danger">-<?= format_currency($ticket['discount']) ?></td>
                            </tr><?php endif; ?>
                        <tr class="fw-bold fs-5">
                            <td>Grand Total:</td>
                            <td class="text-end text-success" id="billGrandTotal"><?= format_currency($ticket['total_amount']) ?></td>
                        </tr>
                        <?php if ($ticket['paid_amount'] > 0): ?>
                            <tr>
                                <td>Paid:</td>
                                <td class="text-end"><?= format_currency($ticket['paid_amount']) ?></td>
                            </tr>
                            <tr>
                                <td>Due:</td>
                                <td class="text-end text-danger"><?= format_currency($ticket['total_amount'] - $ticket['paid_amount']) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Status + Actions -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-bolt"></i> Actions</h6>
            </div>
            <div class="card-body">
                <a href="service-receipt.php?id=<?= $ticket_id ?>" target="_blank" class="btn btn-outline-secondary btn-sm w-100 mb-2"><i class="fas fa-print"></i> Print Receipt/Token</a>
                <?php if ($ticket['total_amount'] > 0): ?>
                    <a href="service-invoice.php?id=<?= $ticket_id ?>" target="_blank" class="btn btn-outline-success btn-sm w-100 mb-2"><i class="fas fa-file-invoice-dollar"></i> Print Invoice</a>
                <?php endif; ?>
                <a href="service-list.php" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-list"></i> Back to List</a>
            </div>
        </div>

        <!-- Status Update -->
        <?php if ($ticket['status'] !== 'Delivered'): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-sync-alt"></i> Update Status</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="update_status" value="1">
                        <div class="mb-3">
                            <select name="new_status" id="statusSelect" class="form-control" required>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <textarea name="status_notes" class="form-control" rows="2" placeholder="Notes (optional)"></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning w-100"><i class="fas fa-sync"></i> Update Status</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Deliver & Payment (hidden by default, shown when status=Delivered) -->
        <?php if ($ticket['status'] !== 'Delivered' && $ticket['status'] !== 'Cannot be Fixed'): ?>
            <div class="card shadow mb-4 border-success" id="deliverSection" style="display:none;">
                <div class="card-header py-3 bg-success text-white">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-truck"></i> Deliver & Collect Payment</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="deliverForm">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="deliver_ticket" value="1">
                        <div class="mb-2">
                            <label class="form-label fw-bold">Total: <?= format_currency($ticket['total_amount']) ?></label>
                            <div class="small text-muted">
                                Service Charge: <?= format_currency($ticket['service_charge']) ?> → <span class="text-info">Service Revenue</span><br>
                                Parts Cost: <?= format_currency($ticket['total_parts_cost']) ?> → <span class="text-info">Product Sales</span>
                                <?php if ($ticket['discount'] > 0): ?><br>Discount: -<?= format_currency($ticket['discount']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount Received</label>
                            <input type="number" name="paid_amount" class="form-control" step="0.01" value="<?= $ticket['total_amount'] ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" id="svcPaymentMethod" class="form-control" required>
                                <option value="">-- Select Method --</option>
                                <option value="Cash">Cash</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Card">Card</option>
                                <option value="Rocket">Rocket</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deposit To <span class="text-danger">*</span></label>
                            <select name="payment_account_id" id="svcPaymentAccount" class="form-control" required>
                                <option value="">-- আগে Payment Method সিলেক্ট করুন --</option>
                            </select>
                            <small class="text-muted mt-1 d-block" id="svcAccountNote"></small>
                        </div>
                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Confirm delivery and payment?\nStatus will change to Delivered.')">
                            <i class="fas fa-check-double"></i> Deliver & Collect Payment
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Status History -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-history"></i> Status History</h6>
            </div>
            <div class="card-body">
                <div class="status-timeline">
                    <?php foreach ($status_log as $log): ?>
                        <div class="timeline-item">
                            <strong><?= htmlspecialchars($log['new_status']) ?></strong>
                            <?php if ($log['old_status']): ?><small class="text-muted"> (from <?= $log['old_status'] ?>)</small><?php endif; ?>
                            <br><small class="text-muted"><?= date('d M Y, h:i A', strtotime($log['changed_at'])) ?></small>
                            <?php if ($log['notes']): ?><br><small><?= htmlspecialchars($log['notes']) ?></small><?php endif; ?>
                            <br><small class="text-muted">By: <?= htmlspecialchars($log['changed_by_name'] ?? 'System') ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Info Modal -->
<div class="modal fade" id="editInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="update_info" value="1">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Ticket Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($ticket['customer_name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" name="customer_phone" class="form-control" value="<?= htmlspecialchars($ticket['customer_phone']) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Device Type <span class="text-danger">*</span></label>
                            <select name="device_type" id="edit_device_type" class="form-control" required style="width:100%;">
                                <option value="">-- Select or Type New --</option>
                                <?php foreach ($all_devices as $dev): ?>
                                    <option value="<?= htmlspecialchars($dev) ?>" <?= ($ticket['device_type'] == $dev) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dev) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($ticket['brand'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" class="form-control" value="<?= htmlspecialchars($ticket['model'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Serial / MAC / IMEI</label>
                            <input type="text" name="serial_number" class="form-control" value="<?= htmlspecialchars($ticket['serial_number'] ?? '') ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Warranty Status</label>
                            <select name="warranty_status" class="form-control">
                                <option value="Out of Warranty" <?= ($ticket['warranty_status'] === 'Out of Warranty') ? 'selected' : '' ?>>Out of Warranty</option>
                                <option value="External Product" <?= ($ticket['warranty_status'] === 'External Product') ? 'selected' : '' ?>>External Product</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estimated Delivery Date</label>
                            <input type="date" name="estimated_delivery_date" class="form-control" value="<?= htmlspecialchars($ticket['estimated_delivery_date'] ?? '') ?>">
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Problem Description <span class="text-danger">*</span></label>
                            <textarea name="problem_description" class="form-control" rows="3" required><?= htmlspecialchars($ticket['problem_description']) ?></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Physical Condition</label>
                            <textarea name="physical_condition" class="form-control" rows="2"><?= htmlspecialchars($ticket['physical_condition'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    const TICKET_ID = <?= $ticket_id ?>;
    const BASE = '<?= BASE_URL ?>';
    const CURRENCY = '<?= $APP_CURRENCY_SYMBOL ?>';

    function showToast(message, type) {
        const id = 'toast_' + Date.now();
        const bgClass = type === 'success' ? 'bg-success' : (type === 'error' ? 'bg-danger' : 'bg-info');
        const html = `<div id="${id}" class="alert ${bgClass} text-white shadow-lg" style="animation: fadeIn 0.3s;">
        <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')}"></i> ${message}
    </div>`;
        $('#toastContainer').append(html);
        setTimeout(() => {
            $(`#${id}`).fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    function formatCurrency(amount) {
        return CURRENCY + parseFloat(amount).toFixed(2);
    }

    function renderPartsTable(parts, totalPartsCost, totalAmount) {
        let html = '<table class="table table-bordered table-sm parts-table"><thead><tr>';
        html += '<th>Part Name</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th>';
        <?php if ($ticket['status'] !== 'Delivered'): ?>html += '<th></th>';
    <?php endif; ?>
    html += '</tr></thead><tbody>';

    if (parts.length === 0) {
        html += '<tr><td colspan="5" class="text-center text-muted">No parts added yet</td></tr>';
    } else {
        parts.forEach(function(p) {
            html += '<tr>';
            html += `<td>${p.product_name} ${p.product_id ? '<small class="text-muted">(Inventory)</small>' : ''}`;
            if (p.serial_numbers) html += `<br><small class="text-info"><i class="fas fa-barcode"></i> ${p.serial_numbers}</small>`;
            html += `</td>`;
            html += `<td class="text-center">${p.quantity}</td>`;
            html += `<td class="text-end">${formatCurrency(p.unit_price)}</td>`;
            html += `<td class="text-end">${formatCurrency(p.total_price)}</td>`;
            <?php if ($ticket['status'] !== 'Delivered'): ?>
                html += `<td class="text-center">
                <button class="btn btn-sm btn-danger btn-remove-part" data-part-id="${p.id}" title="Remove"><i class="fas fa-trash"></i></button>
            </td>`;
            <?php endif; ?>
            html += '</tr>';
        });
    }

    html += '</tbody><tfoot><tr>';
    html += `<td colspan="3" class="text-end fw-bold">Parts Total:</td><td class="text-end fw-bold">${formatCurrency(totalPartsCost)}</td><td></td>`;
    html += '</tr></tfoot></table>';

    $('#partsTableContainer').html(html);
    $('#billPartsCost').text(formatCurrency(totalPartsCost));
    $('#billGrandTotal').text(formatCurrency(totalAmount));
    }

    // Product search auto-suggest
    let searchTimeout;
    $('#part_name').on('input', function() {
        clearTimeout(searchTimeout);
        const q = $(this).val();
        $('#part_product_id').val(''); // reset product ID
        $('#stockInfo').hide();
        if (q.length < 2) {
            $('#productSuggestions').hide();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.get(BASE + '/api/service/search-products.php', {
                q: q
            }, function(data) {
                if (data.length === 0) {
                    $('#productSuggestions').html('<div class="list-group-item text-muted text-center py-2"><i class="fas fa-exclamation-triangle"></i> No products found in stock</div>').show();
                    return;
                }
                let html = '';
                data.forEach(function(p) {
                    let stockClass = p.stock_quantity > 5 ? 'stock-ok' : (p.stock_quantity > 0 ? 'stock-low' : 'stock-out');
                    let stockText = p.stock_quantity > 0 ? `Stock: ${p.stock_quantity}` : 'Out of Stock';
                    let hasSerialAttr = p.has_serial === 'Available' ? 'true' : 'false';
                    html += `<a href="#" class="list-group-item list-group-item-action py-2 px-3" 
                           data-id="${p.id}" data-name="${p.name}" data-price="${p.selling_price}" data-stock="${p.stock_quantity}" data-serial="${hasSerialAttr}">
                           <div class="d-flex justify-content-between align-items-center">
                             <strong>${p.name}</strong>
                             <div>
                               ${p.has_serial === 'Available' ? '<span class="badge bg-secondary me-1"><i class="fas fa-barcode"></i> Serials</span>' : ''}
                               <span class="stock-badge ${stockClass}">${stockText}</span>
                               <span class="ms-2">${CURRENCY}${p.selling_price}</span>
                             </div>
                           </div>
                         </a>`;
                });
                $('#productSuggestions').html(html).show();
            });
        }, 300);
    });

    // Select product from suggestions
    $(document).on('click', '#productSuggestions a', function(e) {
        e.preventDefault();
        const $this = $(this);
        const stock = parseInt($this.data('stock'));

        if (stock <= 0) {
            showToast('This product is out of stock! Please purchase stock first.', 'error');
            return;
        }

        $('#part_product_id').val($this.data('id'));
        $('#part_name').val($this.data('name'));
        $('#part_price').val($this.data('price'));
        $('#productSuggestions').hide();

        // Show stock info
        let stockClass = stock > 5 ? 'text-success' : 'text-warning';
        $('#stockInfo').html(`<small class="${stockClass}"><i class="fas fa-boxes"></i> Available Stock: <strong>${stock}</strong> units</small>`).show();

        // Serial UI
        if ($this.data('serial') === true) {
            resetSerialSelection();
            $('#qty_col').hide();
            $('#part_qty').val(1).prop('readonly', true);
            $('#serial_col').show();
            $('#part_serials').attr('required', true).val('');
        } else {
            resetSerialSelection();
            $('#qty_col').show();
            $('#part_qty').val(1).prop('readonly', false);
            $('#serial_col').hide();
            $('#part_serials').attr('required', false).val('');
        }
    });

    $(document).click(function(e) {
        if (!$(e.target).closest('#part_name, #productSuggestions').length) {
            $('#productSuggestions').hide();
        }
    });

    // Quick add from service products grid
    $(document).on('click', '.svc-product-item', function() {
        const $el = $(this);
        const stock = parseInt($el.data('stock'));
        if (stock <= 0) {
            showToast('Out of stock!', 'error');
            return;
        }
        $('#part_product_id').val($el.data('id'));
        $('#part_name').val($el.data('name'));
        $('#part_price').val($el.data('price'));

        // Serials for Quick Add
        if ($el.data('serial') === true) {
            resetSerialSelection();
            $('#qty_col').hide();
            $('#part_qty').val(1).prop('readonly', true);
            $('#serial_col').show();
            $('#part_serials').attr('required', true).val('');
        } else {
            resetSerialSelection();
            $('#qty_col').show();
            $('#part_qty').val(1).prop('readonly', false);
            $('#serial_col').hide();
            $('#part_serials').attr('required', false).val('');
        }

        let stockClass = stock > 5 ? 'text-success' : 'text-warning';
        $('#stockInfo').html(`<small class="${stockClass}"><i class="fas fa-boxes"></i> Available Stock: <strong>${stock}</strong> units</small>`).show();
        // Scroll to part form
        $('html, body').animate({
            scrollTop: $('#addPartForm').offset().top - 80
        }, 300);
    });

    // Auto-update quantity based on commas and spaces if serial
    $('#part_serials').on('input propertychange paste blur', function() {
        const serials = $(this).val().split(/[\n,]+/).filter(s => s.trim().length > 0);
        if (serials.length > 0) {
            $('#part_qty').val(serials.length);
        } else {
            $('#part_qty').val(1);
        }
    });

    // AJAX Add Part
    $('#btnAddPart').on('click', function() {
        const partName = $('#part_name').val().trim();
        const partQty = parseInt($('#part_qty').val());
        const partPrice = parseFloat($('#part_price').val());
        const productId = $('#part_product_id').val();
        const serials = $('#part_serials').val().trim();

        if (!partName) {
            showToast('Please enter a part name', 'error');
            return;
        }
        if (partQty < 1) {
            showToast('Quantity must be at least 1', 'error');
            return;
        }
        if ($('#serial_col').is(':visible') && serials.length < 3) {
            showToast('Please provide valid serial numbers separated by comma', 'error');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adding...');
        $('#partsOverlay').addClass('active');

        $.post(BASE + '/api/service/add-part.php', {
            ticket_id: TICKET_ID,
            product_id: productId,
            part_name: partName,
            quantity: partQty,
            unit_price: partPrice,
            serials: serials
        }, function(resp) {
            $btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Add Part');
            $('#partsOverlay').removeClass('active');

            if (resp.success) {
                showToast(resp.message, 'success');
                renderPartsTable(resp.parts, resp.total_parts_cost, resp.total_amount);

                // Reset form
                $('#part_name').val('');
                $('#part_product_id').val('');
                $('#qty_col').show();
                $('#part_qty').val('1').prop('readonly', false);
                $('#part_price').val('0');
                $('#serial_col').hide();
                $('#part_serials').val('');
                $('#stockInfo').hide();

                if (resp.remaining_stock !== null) {
                    showToast(`Remaining stock: ${resp.remaining_stock} units`, 'info');
                }
            } else {
                showToast(resp.error, 'error');
            }
        }, 'json').fail(function() {
            $btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Add Part');
            $('#partsOverlay').removeClass('active');
            showToast('Failed to add part. Please try again.', 'error');
        });
    });

    // AJAX Remove Part
    $(document).on('click', '.btn-remove-part', function() {
        if (!confirm('Remove this part? Stock will be restored.')) return;

        const partId = $(this).data('part-id');
        const $btn = $(this);
        $btn.prop('disabled', true);
        $('#partsOverlay').addClass('active');

        $.post(BASE + '/api/service/remove-part.php', {
            part_id: partId,
            ticket_id: TICKET_ID
        }, function(resp) {
            $('#partsOverlay').removeClass('active');

            if (resp.success) {
                showToast(resp.message, 'success');
                renderPartsTable(resp.parts, resp.total_parts_cost, resp.total_amount);
            } else {
                showToast(resp.error, 'error');
                $btn.prop('disabled', false);
            }
        }, 'json').fail(function() {
            $('#partsOverlay').removeClass('active');
            $btn.prop('disabled', false);
            showToast('Failed to remove part.', 'error');
        });
    });

    // === Status dropdown → toggle Deliver section ===
    $('#statusSelect').on('change', function() {
        if ($(this).val() === 'Delivered') {
            $('#deliverSection').slideDown(300);
        } else {
            $('#deliverSection').slideUp(200);
        }
    });

    // === Dynamic Account Population (POS style) ===
    const svcCashAccounts = <?= json_encode($cash_accounts ?: []) ?>;
    const svcBankAccounts = <?= json_encode($bank_accounts ?: []) ?>;

    $('#svcPaymentMethod').on('change', function() {
        const method = $(this).val();
        const select = $('#svcPaymentAccount');
        select.html('<option value="">-- Select Account --</option>');
        $('#svcAccountNote').html('');

        if (!method) {
            select.html('<option value="">-- আগে Payment Method সিলেক্ট করুন --</option>');
            return;
        }

        if (method === 'Cash') {
            svcCashAccounts.forEach(function(acc) {
                select.append(`<option value="${acc.id}">${acc.account_name} (${CURRENCY}${parseFloat(acc.current_balance).toFixed(2)})</option>`);
            });
            $('#svcAccountNote').html('<i class="fas fa-info-circle"></i> Cash In Hand ও Petty Cash একসাথে আপডেট হবে');
        } else {
            const typeMap = {
                'bKash': 'bkash',
                'Nagad': 'nagad',
                'Rocket': 'rocket',
                'Bank Transfer': 'bank',
                'Card': 'bank'
            };
            const filterType = typeMap[method] || 'bank';
            let added = 0;
            svcBankAccounts.forEach(function(acc) {
                const accType = (acc.account_type || 'bank').toLowerCase();
                if (accType === filterType) {
                    select.append(`<option value="${acc.id}">${acc.bank_name} (${acc.account_number})</option>`);
                    added++;
                }
            });
            if (added === 0) {
                svcBankAccounts.forEach(function(acc) {
                    select.append(`<option value="${acc.id}">${acc.bank_name} (${acc.account_number})</option>`);
                });
            }
        }

        if (select.children('option').length === 2) {
            select.val(select.children('option').eq(1).val());
        }
    });

    // Form validation
    $('#deliverForm').on('submit', function(e) {
        if (!$('#svcPaymentMethod').val()) {
            alert('Please select a payment method');
            e.preventDefault();
            return false;
        }
        if (!$('#svcPaymentAccount').val()) {
            alert('Please select an account to deposit to');
            e.preventDefault();
            return false;
        }
    });

    // Initialize Select2 in edit modal
    $(document).ready(function() {
        if ($('#edit_device_type').length > 0) {
            $('#edit_device_type').select2({
                theme: 'bootstrap-5',
                tags: true,
                dropdownParent: $('#editInfoModal'),
                width: '100%'
            });

            // Re-initialize when modal is shown
            $('#editInfoModal').on('shown.bs.modal', function() {
                $('#edit_device_type').select2({
                    theme: 'bootstrap-5',
                    tags: true,
                    dropdownParent: $('#editInfoModal'),
                    width: '100%'
                });
            });
        }
    });

    // === Serial Selection Logic ===
    let selectedSerials = [];
    let modalAvailableStock = 0;

    $(document).ready(function() {
        $('#btnSelectSerials').on('click', function() {
            const productId = $('#part_product_id').val();
            if (!productId) {
                showToast('Please select a product first', 'error');
                return;
            }

            // Get current available stock from UI
            const stockText = $('#stockInfo').text();
            const match = stockText.match(/Available Stock: (\d+)/);
            modalAvailableStock = match ? parseInt(match[1]) : 999;

            $('#serialSelectionModal').modal('show');
            $('#serialGrid').html('<div class="text-center w-100 py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading available serials...</div>');
            $('#btnConfirmSerials').prop('disabled', true);
            $('#stockLimitWarning').hide();

            // Fetch available serials
            $.get(BASE + '/api/products/get-serials.php', {
                product_id: productId,
                status: 'in_stock'
            }, function(resp) {
                if (resp.status && resp.data.length > 0) {
                    let html = '';
                    resp.data.forEach(function(s) {
                        const isSelected = selectedSerials.includes(s.serial_number);
                        html += `
                            <div class="serial-item ${isSelected ? 'selected' : ''}" data-serial="${s.serial_number}">
                                <i class="fas ${isSelected ? 'fa-check-circle' : 'fa-circle'} me-2"></i>
                                <span>${isSelected ? '<strong>' + s.serial_number + '</strong>' : s.serial_number}</span>
                            </div>`;
                    });
                    $('#serialGrid').html(html);
                    updateSelectedCount();
                } else {
                    $('#serialGrid').html('<div class="text-center w-100 py-4 text-muted"><i class="fas fa-exclamation-triangle"></i> No available serials found for this product.</div>');
                }
            });
        });

        // Toggle serial selection
        $(document).on('click', '.serial-item', function() {
            const serial = $(this).data('serial');
            
            if (!$(this).hasClass('selected')) {
                // Try to select
                if (selectedSerials.length >= modalAvailableStock) {
                    $('#stockLimitWarning').fadeIn(200).delay(2000).fadeOut(500);
                    showToast(`Cannot select more than available stock (${modalAvailableStock})`, 'warning');
                    return;
                }
                $(this).addClass('selected');
                if (!selectedSerials.includes(serial)) selectedSerials.push(serial);
                $(this).find('i').removeClass('fa-circle').addClass('fa-check-circle');
                $(this).find('span').html('<strong>' + serial + '</strong>');
            } else {
                // Deselect
                $(this).removeClass('selected');
                selectedSerials = selectedSerials.filter(s => s !== serial);
                $(this).find('i').removeClass('fa-check-circle').addClass('fa-circle');
                $(this).find('span').html(serial);
            }
            updateSelectedCount();
        });

        $(document).on('click', '#btnConfirmSerials', function() {
            console.log('Confirm button clicked, serials:', selectedSerials);
            $('#part_serials').val(selectedSerials.join(', '));
            $('#part_serials').trigger('change'); 
            $('#part_qty').val(selectedSerials.length);
            
            // Comprehensive modal hide
            $('#serialSelectionModal').modal('hide');
            try {
                let modalEl = document.getElementById('serialSelectionModal');
                let modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();
            } catch(e) { console.log('Bootstrap hide helper:', e); }

            if (typeof showToast === 'function') {
                showToast(`${selectedSerials.length} serials selected`, 'info');
            } else {
                alert(`${selectedSerials.length} serials selected`);
            }
        });

        // Synchronize selectedSerials with manual edits in the text input
        $('#part_serials').on('change', function() {
            const val = $(this).val().trim();
            if (val === '') {
                selectedSerials = [];
            } else {
                selectedSerials = val.split(/[\n,]+/).map(s => s.trim()).filter(s => s.length > 0);
            }
            updateSelectedCount();
        });
    });

    function updateSelectedCount() {
        $('#selectedSerialCount').text(selectedSerials.length);
        $('#btnConfirmSerials').prop('disabled', selectedSerials.length === 0);
    }

    function resetSerialSelection() {
        selectedSerials = [];
        $('#part_serials').val('');
        $('#part_qty').val(1);
        $('#selectedSerialCount').text(0);
    }
</script>

<!-- Serial Selection Modal -->
<div class="modal fade" id="serialSelectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-bottom-0">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-barcode"></i> Select Serial Numbers</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <div class="px-4 py-3 bg-white border-bottom">
                    <p class="small text-muted mb-0"><i class="fas fa-info-circle text-primary"></i> Available serial numbers in stock. Select the ones you are using for this service.</p>
                </div>
                <div id="serialGrid" class="serial-selection-grid p-4" style="max-height: 430px; overflow-y: auto;">
                    <!-- Dynamic content -->
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between bg-white border-top-0 py-3">
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill">Selected: <span id="selectedSerialCount">0</span></span>
                    <span id="stockLimitWarning" class="text-danger small ms-3" style="display:none;"><i class="fas fa-exclamation-circle"></i> Stock limit reached!</span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="btnConfirmSerials" class="btn btn-success px-4 rounded-pill font-weight-bold" disabled><i class="fas fa-check"></i> Confirm Selection</button>
                </div>
            </div>
        </div>
    </div>
</div>
