<?php
/**
 * Supplier View Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$supplier_id = (int)get_param('id');
$supplier = db_select_one('suppliers', ['id' => $supplier_id]);

if (!$supplier) {
    redirect_with_message('suppliers-list.php', 'Supplier not found', 'error');
}

// Get recent purchases
$sql = "SELECT * FROM purchases WHERE supplier_id = ? ORDER BY purchase_date DESC LIMIT 10";
$recent_purchases = db_query($sql, [$supplier_id]);

// Get total purchases
$sql = "SELECT COUNT(*) as total_purchases, SUM(total_amount) as total_amount FROM purchases WHERE supplier_id = ? AND status = 'completed'";
$purchases_summary = db_query_one($sql, [$supplier_id]);

$page_title = 'Supplier Details';
$page_actions = '<a href="supplier-edit.php?id=' . $supplier_id . '" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
                 <a href="supplier-ledger.php?id=' . $supplier_id . '" class="btn btn-info"><i class="fas fa-book"></i> Ledger</a>
                 <button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print</button>
                 <a href="suppliers-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>';
include __DIR__ . '/../../templates/header.php';
?>

<style>
@media print {
    .no-print, .btn, .card-header, .navbar, .sidebar, #accordionSidebar, .topbar, footer, #standard-print-wrapper, #standard-print-footer, .d-sm-flex {
        display: none !important;
    }
    .table { font-size: 12px; }
    tr { page-break-inside: avoid; }
    h6 { font-size: 18px; font-weight: bold; margin-top: 20px; }
}
</style>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Supplier Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">Name</th>
                        <td><?= htmlspecialchars($supplier['name']) ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?= htmlspecialchars($supplier['phone'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= htmlspecialchars($supplier['email'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><?= nl2br(htmlspecialchars($supplier['address'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <th>Payment Terms</th>
                        <td><?= htmlspecialchars($supplier['payment_terms'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Current Balance</th>
                        <td>
                            <?php if ($supplier['current_balance'] > 0): ?>
                                <span class="text-danger"><strong><?= format_currency($supplier['current_balance']) ?></strong> (Payable)</span>
                            <?php elseif ($supplier['current_balance'] < 0): ?>
                                <span class="text-success"><strong><?= format_currency(abs($supplier['current_balance'])) ?></strong> (Advance)</span>
                            <?php else: ?>
                                <?= format_currency(0) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?= $supplier['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($supplier['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td><?= format_datetime($supplier['created_at']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Recent Purchases -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Purchases</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Purchase #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_purchases)): ?>
                                <tr><td colspan="6" class="text-center">No purchases found</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_purchases as $purchase): ?>
                                    <tr>
                                        <td><a href="../purchase/purchase-view.php?id=<?= $purchase['id'] ?>"><?= htmlspecialchars($purchase['purchase_number']) ?></a></td>
                                        <td><?= format_date($purchase['purchase_date']) ?></td>
                                        <td><?= format_currency($purchase['total_amount']) ?></td>
                                        <td><?= format_currency($purchase['paid_amount']) ?></td>
                                        <td><?= format_currency($purchase['due_amount']) ?></td>
                                        <td><span class="badge bg-<?= ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger'][$purchase['payment_status']] ?>"><?= ucfirst($purchase['payment_status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 no-print">
        <!-- Summary Cards -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Purchase Summary</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted">Total Purchases</small>
                    <h4><?= $purchases_summary['total_purchases'] ?? 0 ?></h4>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Total Amount</small>
                    <h4><?= format_currency($purchases_summary['total_amount'] ?? 0) ?></h4>
                </div>
                <div>
                    <small class="text-muted">Current Payable</small>
                    <h4 class="<?= $supplier['current_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= format_currency(abs($supplier['current_balance'])) ?>
                    </h4>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <a href="../purchase/purchase-add.php?supplier_id=<?= $supplier_id ?>" class="btn btn-success btn-block mb-2">
                    <i class="fas fa-shopping-bag"></i> New Purchase
                </a>
                <a href="supplier-payments.php?id=<?= $supplier_id ?>" class="btn btn-primary btn-block mb-2">
                    <i class="fas fa-money-bill"></i> Add Payment
                </a>
                <a href="supplier-ledger.php?id=<?= $supplier_id ?>" class="btn btn-info btn-block">
                    <i class="fas fa-book"></i> View Ledger
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
