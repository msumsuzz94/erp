<?php
/**
 * Purchase View Page
 * Display detailed information about a purchase
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
// 2. GET PURCHASE DATA
// ============================================
$purchase_id = get_param('id', 0);

if (!$purchase_id) {
    redirect_with_message('purchases-list.php', 'Invalid purchase ID', 'error');
}

// Get purchase details with supplier information
$sql = "SELECT p.*, s.name as supplier_name, s.phone as supplier_phone, 
               s.email as supplier_email, s.address as supplier_address,
               u.username as created_by_name
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = ?";
$purchase = db_query($sql, [$purchase_id]);

if (empty($purchase)) {
    redirect_with_message('purchases-list.php', 'Purchase not found', 'error');
}

$purchase = $purchase[0];

// Get purchase items
$sql = "SELECT pi.*, p.name as product_name, p.code as product_code, p.has_serial
        FROM purchase_items pi
        LEFT JOIN products p ON pi.product_id = p.id
        WHERE pi.purchase_id = ?
        ORDER BY pi.id";
$items = db_query($sql, [$purchase_id]);

// Get serial numbers for this purchase
$serials_raw = db_query("SELECT * FROM product_serials WHERE purchase_id = ?", [$purchase_id]);
$product_serials = [];
foreach ($serials_raw as $s) {
    $product_serials[$s['product_id']][] = $s['serial_number'];
}

// Get payment history
$sql = "SELECT * FROM purchase_payments 
        WHERE purchase_id = ?
        ORDER BY payment_date DESC";
$payments = db_query($sql, [$purchase_id]);

// ============================================
// 3. FRONTEND HTML
// ============================================
$page_title = 'Purchase Details #' . $purchase['id'];
$page_actions = '
    <a href="purchases-list.php" class="btn btn-secondary"><i class="fas fa-list"></i> Back to List</a>
    <a href="purchase-print.php?id=' . $purchase_id . '" target="_blank" class="btn btn-success"><i class="fas fa-file-invoice"></i> Print Invoice</a>
    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Page</button>
';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <!-- Purchase Information -->
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Purchase Information</h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Purchase Number:</strong> <?= htmlspecialchars($purchase['purchase_number'] ?? 'N/A') ?></p>
                        <p><strong>Purchase Date:</strong> <?= format_date($purchase['purchase_date']) ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?= $purchase['status'] === 'completed' ? 'success' : ($purchase['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($purchase['status']) ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Payment Status:</strong> 
                            <span class="badge bg-<?= $purchase['payment_status'] === 'paid' ? 'success' : ($purchase['payment_status'] === 'partial' ? 'warning' : 'danger') ?>">
                                <?= ucfirst($purchase['payment_status']) ?>
                            </span>
                        </p>
                        <p><strong>Created By:</strong> <?= htmlspecialchars($purchase['created_by_name'] ?? 'N/A') ?></p>
                        <p><strong>Created At:</strong> <?= format_datetime($purchase['created_at']) ?></p>
                    </div>
                </div>
                
                <?php if (!empty($purchase['notes'])): ?>
                <div class="row">
                    <div class="col-md-12">
                        <p><strong>Notes:</strong></p>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($purchase['notes'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Purchase Items -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Purchase Items</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Code</th>
                                <th class="text-right">Quantity</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Tax</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No items found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($item['product_name']) ?>
                                            <?php if (!empty($product_serials[$item['product_id']])): ?>
                                                <div class="mt-1">
                                                    <small class="text-muted"><strong>Serials:</strong> 
                                                        <?= implode(', ', array_map('htmlspecialchars', $product_serials[$item['product_id']])) ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['product_code'] ?? 'N/A') ?></td>
                                        <td class="text-right"><?= number_format($item['quantity'], 2) ?></td>
                                        <td class="text-right"><?= format_currency($item['unit_price']) ?></td>
                                        <td class="text-right"><?= format_currency($item['tax']) ?></td>
                                        <td class="text-right"><?= format_currency($item['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Payment History</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= format_date($payment['payment_date']) ?></td>
                                    <td><?= format_currency($payment['amount']) ?></td>
                                    <td><?= ucfirst($payment['payment_method']) ?></td>
                                    <td><?= htmlspecialchars($payment['notes'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Summary Card -->
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Supplier Details</h6>
            </div>
            <div class="card-body">
                <p><strong>Name:</strong><br><?= htmlspecialchars($purchase['supplier_name']) ?></p>
                <?php if (!empty($purchase['supplier_phone'])): ?>
                    <p><strong>Phone:</strong><br><?= htmlspecialchars($purchase['supplier_phone']) ?></p>
                <?php endif; ?>
                <?php if (!empty($purchase['supplier_email'])): ?>
                    <p><strong>Email:</strong><br><?= htmlspecialchars($purchase['supplier_email']) ?></p>
                <?php endif; ?>
                <?php if (!empty($purchase['supplier_address'])): ?>
                    <p><strong>Address:</strong><br><?= nl2br(htmlspecialchars($purchase['supplier_address'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Purchase Summary</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td class="text-right"><?= format_currency($purchase['total_amount'] - $purchase['tax_amount'] + $purchase['discount']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tax:</strong></td>
                        <td class="text-right"><?= format_currency($purchase['tax_amount']) ?></td>
                    </tr>
                    <?php if ($purchase['discount'] > 0): ?>
                    <tr>
                        <td><strong>Discount:</strong></td>
                        <td class="text-right">-<?= format_currency($purchase['discount']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="border-top">
                        <td><strong>Total Amount:</strong></td>
                        <td class="text-right"><strong><?= format_currency($purchase['total_amount']) ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Paid Amount:</strong></td>
                        <td class="text-right text-success"><?= format_currency($purchase['paid_amount']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Due Amount:</strong></td>
                        <td class="text-right text-danger"><strong><?= format_currency($purchase['due_amount']) ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<style media="print">
    .sidebar, .navbar, .page-actions, .footer, .btn {
        display: none !important;
    }
    .card {
        border: 1px solid #ddd;
        box-shadow: none;
    }
</style>
