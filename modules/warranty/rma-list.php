<?php
/**
 * RMA (Warranty) List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$sql = "SELECT r.*, c.name as customer_name, p.name as product_name
        FROM rma_requests r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN products p ON r.product_id = p.id
        ORDER BY r.created_at DESC";
$rma_list = db_query($sql);

$page_title = 'RMA / Warranty Claims';
$page_actions = '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <a href="rma-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add RMA</a>';

$additional_css = '
<style>
@media print {
    .btn, .sidebar, #sidebar, .topbar, .navbar, .card-header, .no-print, form { display: none !important; }
    .container-fluid { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
    #content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table-responsive { overflow: visible !important; }
    .table { width: 100% !important; border-collapse: collapse !important; }
    .table th, .table td { border: 1px solid #ddd !important; padding: 8px !important; }
    body { padding-top: 0 !important; background: white !important; }
    .text-gray-800 { color: black !important; }
    .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
    .card-body { padding: 0 !important; }
}
.print-header { display: none; }
</style>
';

include __DIR__ . '/../../templates/header.php';
?>

<div class="print-header">
    <h2><?= htmlspecialchars(BUSINESS_NAME) ?></h2>
    <h4>RMA / Warranty Claims Report</h4>
    <p>Date: <?= date('d M Y') ?></p>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">RMA List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="rmaTable">
                <thead>
                    <tr>
                        <th>RMA #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Issue</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rma_list)): ?>
                        <?php foreach ($rma_list as $rma): ?>
                            <tr>
                                <td><?= htmlspecialchars($rma['rma_number']) ?></td>
                                <td><?= format_date($rma['created_date']) ?></td>
                                <td><?= htmlspecialchars($rma['customer_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($rma['product_name'] ?? 'N/A') ?></td>
                                <td><?= truncate(htmlspecialchars($rma['problem_description']), 50) ?></td>
                                <td>
                                    <span class="badge bg-<?= ['pending' => 'warning', 'sent' => 'info', 'solved' => 'success', 'delivered' => 'primary'][$rma['status']] ?? 'secondary' ?>">
                                        <?= ucfirst($rma['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="rma-view.php?id=<?= $rma['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                    <a href="rma-edit.php?id=<?= $rma['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#rmaTable').DataTable({
        "pageLength": 25,
        "order": [[1, "desc"]]
    });
});
</script>
