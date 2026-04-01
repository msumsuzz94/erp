<?php
/**
 * Quotations List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$sql = "SELECT q.*, c.name as customer_name
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        ORDER BY q.created_at DESC";
$quotations = db_query($sql);

$page_title = 'Quotations';
$page_actions = '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <a href="quotation-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Quotation</a>';

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
    <h4>Quotations List Report</h4>
    <p>Date: <?= date('d M Y') ?></p>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Quotations List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="quotationsTable">
                <thead>
                    <tr>
                        <th>Quotation #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Total Amount</th>
                        <th>Valid Until</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                        <?php foreach ($quotations as $quotation): ?>
                            <tr>
                                <td><?= htmlspecialchars($quotation['quotation_number']) ?></td>
                                <td><?= format_date($quotation['quotation_date']) ?></td>
                                <td><?= htmlspecialchars($quotation['customer_name'] ?? 'N/A') ?></td>
                                <td><?= format_currency($quotation['total_amount']) ?></td>
                                <td><?= format_date($quotation['expiration_date']) ?></td>
                                <td>
                                    <?php $q_status = $quotation['status'] ?? 'draft'; ?>
                                    <span class="badge bg-<?= ['draft' => 'secondary', 'pending' => 'warning', 'sent' => 'info', 'accepted' => 'success', 'rejected' => 'danger', 'converted' => 'primary', 'sales_complete' => 'primary'][$q_status] ?? 'secondary' ?>">
                                        <?= ucwords(str_replace('_', ' ', $q_status)) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="quotation-view.php?id=<?= $quotation['id'] ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="quotation-edit.php?id=<?= $quotation['id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php if (($quotation['status'] ?? '') === 'accepted' || ($quotation['status'] ?? '') === 'pending'): ?>
                                        <a href="../sales/pos.php?quotation_id=<?= $quotation['id'] ?>" class="btn btn-sm btn-success" title="Convert to Invoice (POS)"><i class="fas fa-exchange-alt"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#quotationsTable').DataTable({
            "pageLength": 25,
            "order": [[1, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": 6 }
            ]
        });
    }
});
</script>
