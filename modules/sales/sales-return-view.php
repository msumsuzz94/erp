<?php
/**
 * Sales Return View Page
 * Display detailed information about a sales return
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get return ID
$return_id = get_param('id', 0);

if (!$return_id) {
    redirect_with_message('sales-return-list.php', 'Invalid return ID', 'error');
}

// Get return details with related information
$sql = "SELECT sr.*, 
               s.invoice_number, s.sale_date, s.total_amount as sale_total,
               c.name as customer_name, c.phone as customer_phone, 
               c.email as customer_email, c.address as customer_address,
               u.username as created_by_name
        FROM sales_returns sr
        LEFT JOIN sales s ON sr.sale_id = s.id
        LEFT JOIN customers c ON sr.customer_id = c.id
        LEFT JOIN users u ON sr.created_by = u.id
        WHERE sr.id = ?";
$return = db_query($sql, [$return_id]);

if (empty($return)) {
    redirect_with_message('sales-return-list.php', 'Return not found', 'error');
}

$return = $return[0];

// Get return items
$sql = "SELECT sri.*, p.name as product_name, p.code as product_code
        FROM sale_return_items sri
        LEFT JOIN products p ON sri.product_id = p.id
        WHERE sri.return_id = ?
        ORDER BY sri.id";
$items = db_query($sql, [$return_id]);

$page_title = 'Sales Return Details #' . $return['id'];
$page_actions = '
    <a href="sales-return-list.php" class="btn btn-secondary"><i class="fas fa-list"></i> Back to List</a>
    <a href="sale-view.php?id=' . $return['sale_id'] . '" class="btn btn-info"><i class="fas fa-file-invoice"></i> View Original Invoice</a>
    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
';
include __DIR__ . '/../../templates/header.php';
?>

<style media="print">
    .sidebar, .navbar, .page-actions, .footer, .btn, .no-print {
        display: none !important;
    }
    .card {
        border: 1px solid #ddd;
        box-shadow: none;
        page-break-inside: avoid;
    }
    body {
        background: white;
    }
</style>

<div class="row">
    <!-- Main Content -->
    <div class="col-md-8">
        <!-- Return Information -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-primary text-white">
                <h6 class="m-0 font-weight-bold">Sales Return Information</h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Return ID:</strong> #<?= $return['id'] ?></p>
                        <p><strong>Return Date:</strong> <?= format_date($return['return_date']) ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?= $return['status'] === 'completed' ? 'success' : 'warning' ?>">
                                <?= ucfirst($return['status']) ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Original Invoice:</strong> #<?= htmlspecialchars($return['invoice_number']) ?></p>
                        <p><strong>Sale Date:</strong> <?= format_date($return['sale_date']) ?></p>
                        <p><strong>Processed By:</strong> <?= htmlspecialchars($return['created_by_name'] ?? 'N/A') ?></p>
                    </div>
                </div>
                
                <?php if (!empty($return['reason'])): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-light border">
                            <strong><i class="fas fa-info-circle"></i> Return Reason:</strong><br>
                            <?= nl2br(htmlspecialchars($return['reason'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Returned Items -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 border-bottom">
                <h6 class="m-0 font-weight-bold text-primary">Returned Items</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Product</th>
                                <th>Code</th>
                                <th>Return To</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end pe-4">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No items found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <?= htmlspecialchars($item['product_name']) ?>
                                            <?php
                                            $serials = db_query("SELECT serial_number FROM sale_return_item_serials WHERE return_item_id = ?", [$item['id']]);
                                            if (!empty($serials)):
                                            ?>
                                                <div class="mt-1">
                                                    <small class="text-muted">Serial(s):</small>
                                                    <?php foreach($serials as $s): ?>
                                                        <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($s['serial_number']) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['product_code'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php if ($item['return_type'] === 'rma'): ?>
                                                <span class="badge bg-info text-dark">RMA Pool</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Regular Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= number_format($item['quantity'], 2) ?></td>
                                        <td class="text-end"><?= format_currency($item['unit_price']) ?></td>
                                        <td class="text-end pe-4"><?= format_currency($item['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="bg-light fw-bold">
                                    <td colspan="5" class="text-end">Total Return Amount:</td>
                                    <td class="text-end pe-4"><?= format_currency($return['total_amount']) ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Customer Details -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Customer Details</h6>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Name:</strong></p>
                <p><?= htmlspecialchars($return['customer_name'] ?? 'Walk-in Customer') ?></p>
                
                <?php if (!empty($return['customer_phone'])): ?>
                    <p class="mb-1"><strong>Phone:</strong></p>
                    <p><?= htmlspecialchars($return['customer_phone']) ?></p>
                <?php endif; ?>
                
                <?php if (!empty($return['customer_address'])): ?>
                    <p class="mb-1"><strong>Address:</strong></p>
                    <p><?= nl2br(htmlspecialchars($return['customer_address'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Return Impact Summary -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-info text-white">
                <h6 class="m-0 font-weight-bold">Process Summary</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted text-uppercase fw-bold">Stock Update</small>
                    <p class="small mb-0">Items have been returned to their specified destinations (Regular Stock or RMA Pool).</p>
                </div>
                <div class="mb-0">
                    <small class="text-muted text-uppercase fw-bold">Customer Balance</small>
                    <p class="small mb-0">Customer balance has been credited with <strong><?= format_currency($return['total_amount']) ?></strong>.</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card shadow mb-4 no-print">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="sale-view.php?id=<?= $return['sale_id'] ?>" class="btn btn-outline-info">
                    <i class="fas fa-file-invoice"></i> Original Invoice
                </a>
                <a href="sales-return-list.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list"></i> All Returns
                </a>
                <?php if ($return['customer_id']): ?>
                <a href="../customers/customer-view.php?id=<?= $return['customer_id'] ?>" class="btn btn-outline-primary">
                    <i class="fas fa-user"></i> Customer Profile
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
