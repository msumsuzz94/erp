<?php
/**
 * Customer View Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$customer_id = (int)get_param('id');
$customer = db_select_one('customers', ['id' => $customer_id]);

if (!$customer) {
    redirect_with_message('customers-list.php', 'Customer not found', 'error');
}

// Get recent sales
$sql = "SELECT * FROM sales WHERE customer_id = ? ORDER BY sale_date DESC LIMIT 10";
$recent_sales = db_query($sql, [$customer_id]);

// Get total sales
$sql = "SELECT COUNT(*) as total_sales, SUM(total_amount) as total_amount FROM sales WHERE customer_id = ? AND status = 'completed'";
$sales_summary = db_query_one($sql, [$customer_id]);

$page_title = 'Customer Details';
$page_actions = '<a href="customer-edit.php?id=' . $customer_id . '" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
                 <a href="customer-ledger.php?id=' . $customer_id . '" class="btn btn-info"><i class="fas fa-book"></i> Ledger</a>
                 <button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print</button>
                 <a href="customers-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>';
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
                <h6 class="m-0 font-weight-bold text-primary">Customer Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">Name</th>
                        <td><?= htmlspecialchars($customer['name']) ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?= htmlspecialchars($customer['phone'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= htmlspecialchars($customer['email'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Address</th>
                        <td><?= nl2br(htmlspecialchars($customer['address'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <th>Credit Limit</th>
                        <td><?= format_currency($customer['credit_limit']) ?></td>
                    </tr>
                    <tr>
                        <th>Current Balance</th>
                        <td>
                            <?php if ($customer['current_balance'] > 0): ?>
                                <span class="text-danger"><strong><?= format_currency($customer['current_balance']) ?></strong> (Due)</span>
                            <?php elseif ($customer['current_balance'] < 0): ?>
                                <span class="text-success"><strong><?= format_currency(abs($customer['current_balance'])) ?></strong> (Advance)</span>
                            <?php else: ?>
                                <?= format_currency(0) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?= $customer['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($customer['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td><?= format_datetime($customer['created_at']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Recent Sales -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Sales</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_sales)): ?>
                                <tr><td colspan="6" class="text-center">No sales found</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td><a href="../sales/sale-view.php?id=<?= $sale['id'] ?>"><?= htmlspecialchars($sale['invoice_number']) ?></a></td>
                                        <td><?= format_date($sale['sale_date']) ?></td>
                                        <td><?= format_currency($sale['total_amount']) ?></td>
                                        <td><?= format_currency($sale['paid_amount']) ?></td>
                                        <td><?= format_currency($sale['due_amount']) ?></td>
                                        <td><span class="badge bg-<?= ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger'][$sale['payment_status']] ?>"><?= ucfirst($sale['payment_status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Summary Cards -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Sales Summary</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted">Total Sales</small>
                    <h4><?= $sales_summary['total_sales'] ?? 0 ?></h4>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Total Amount</small>
                    <h4><?= format_currency($sales_summary['total_amount'] ?? 0) ?></h4>
                </div>
                <div>
                    <small class="text-muted">Current Due</small>
                    <h4 class="<?= $customer['current_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= format_currency(abs($customer['current_balance'])) ?>
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
                <a href="../sales/pos.php?customer_id=<?= $customer_id ?>" class="btn btn-success btn-block mb-2">
                    <i class="fas fa-shopping-cart"></i> New Sale
                </a>
                <a href="bill-collections.php?id=<?= $customer_id ?>" class="btn btn-primary btn-block mb-2">
                    <i class="fas fa-money-bill"></i> Customer Bill
                </a>
                <a href="customer-ledger.php?id=<?= $customer_id ?>" class="btn btn-info btn-block">
                    <i class="fas fa-book"></i> View Ledger
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
