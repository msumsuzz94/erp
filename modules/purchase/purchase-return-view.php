<?php
/**
 * Purchase Return View Page
 * Display detailed information about a purchase return
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Get return ID
$return_id = get_param('id', 0);

if (!$return_id) {
    redirect_with_message('purchase-return-list.php', 'Invalid return ID', 'error');
}

// Get return details with related information
$sql = "SELECT pr.*, 
               p.purchase_number, p.purchase_date, p.total_amount as purchase_total,
               s.name as supplier_name, s.phone as supplier_phone, 
               s.email as supplier_email, s.address as supplier_address,
               u.username as created_by_name
        FROM purchase_returns pr
        LEFT JOIN purchases p ON pr.purchase_id = p.id
        LEFT JOIN suppliers s ON pr.supplier_id = s.id
        LEFT JOIN users u ON pr.created_by = u.id
        WHERE pr.id = ?";
$return = db_query($sql, [$return_id]);

if (empty($return)) {
    redirect_with_message('purchase-return-list.php', 'Return not found', 'error');
}

$return = $return[0];

// Get return items
$sql = "SELECT pri.*, p.name as product_name, p.code as product_code
        FROM purchase_return_items pri
        LEFT JOIN products p ON pri.product_id = p.id
        WHERE pri.return_id = ?
        ORDER BY pri.id";
$items = db_query($sql, [$return_id]);

$page_title = 'Purchase Return Details #' . $return['id'];
$page_actions = '
    <a href="purchase-return-list.php" class="btn btn-secondary"><i class="fas fa-list"></i> Back to List</a>
    <a href="purchase-view.php?id=' . $return['purchase_id'] . '" class="btn btn-info"><i class="fas fa-file-invoice"></i> View Original Purchase</a>
    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
';
include __DIR__ . '/../../templates/header.php';
?>

<style media="print">
    .sidebar, .navbar, .page-actions, .footer, .btn {
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
            <div class="card-header py-3 bg-danger text-white">
                <h6 class="m-0 font-weight-bold">Purchase Return Information</h6>
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
                        <p><strong>Original Purchase:</strong> #<?= htmlspecialchars($return['purchase_number']) ?></p>
                        <p><strong>Purchase Date:</strong> <?= format_date($return['purchase_date']) ?></p>
                        <p><strong>Created By:</strong> <?= htmlspecialchars($return['created_by_name'] ?? 'N/A') ?></p>
                    </div>
                </div>
                
                <?php if (!empty($return['reason'])): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-info">
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
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Returned Items</h6>
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
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No items found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= htmlspecialchars($item['product_code'] ?? 'N/A') ?></td>
                                        <td class="text-right"><?= number_format($item['quantity'], 2) ?></td>
                                        <td class="text-right"><?= format_currency($item['unit_price']) ?></td>
                                        <td class="text-right"><?= format_currency($item['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-secondary font-weight-bold">
                                    <td colspan="4" class="text-right">Total Return Amount:</td>
                                    <td class="text-right"><?= format_currency($return['total_amount']) ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Impact Summary -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-info text-white">
                <h6 class="m-0 font-weight-bold">Return Impact</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert alert-warning mb-0">
                            <h6><i class="fas fa-boxes"></i> Stock Impact</h6>
                            <p class="mb-0">Product stock quantities have been reduced by the returned amounts.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-success mb-0">
                            <h6><i class="fas fa-dollar-sign"></i> Financial Impact</h6>
                            <p class="mb-0">Supplier balance reduced by <strong><?= format_currency($return['total_amount']) ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-md-4">
        <!-- Supplier Details -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Supplier Details</h6>
            </div>
            <div class="card-body">
                <p><strong>Name:</strong><br><?= htmlspecialchars($return['supplier_name']) ?></p>
                <?php if (!empty($return['supplier_phone'])): ?>
                    <p><strong>Phone:</strong><br><?= htmlspecialchars($return['supplier_phone']) ?></p>
                <?php endif; ?>
                <?php if (!empty($return['supplier_email'])): ?>
                    <p><strong>Email:</strong><br><?= htmlspecialchars($return['supplier_email']) ?></p>
                <?php endif; ?>
                <?php if (!empty($return['supplier_address'])): ?>
                    <p><strong>Address:</strong><br><?= nl2br(htmlspecialchars($return['supplier_address'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Return Summary -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-danger text-white">
                <h6 class="m-0 font-weight-bold">Return Summary</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <td><strong>Original Purchase:</strong></td>
                        <td class="text-right"><?= format_currency($return['purchase_total']) ?></td>
                    </tr>
                    <tr class="border-top">
                        <td><strong>Return Amount:</strong></td>
                        <td class="text-right text-danger"><strong><?= format_currency($return['total_amount']) ?></strong></td>
                    </tr>
                    <tr>
                        <td><strong>Items Returned:</strong></td>
                        <td class="text-right"><?= count($items) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Return %:</strong></td>
                        <td class="text-right">
                            <?php 
                            $percentage = $return['purchase_total'] > 0 
                                ? ($return['total_amount'] / $return['purchase_total']) * 100 
                                : 0;
                            echo number_format($percentage, 2) . '%';
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Actions -->
        <div class="card shadow mb-4 no-print">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <a href="purchase-view.php?id=<?= $return['purchase_id'] ?>" class="btn btn-info btn-block mb-2">
                    <i class="fas fa-file-invoice"></i> View Original Purchase
                </a>
                <a href="purchase-return-list.php" class="btn btn-secondary btn-block mb-2">
                    <i class="fas fa-list"></i> All Returns
                </a>
                <a href="../suppliers/supplier-view.php?id=<?= $return['supplier_id'] ?>" class="btn btn-primary btn-block">
                    <i class="fas fa-user"></i> View Supplier
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
