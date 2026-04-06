<?php
/**
 * Stock Transfer - List All Transfers
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require login
require_login();

// Get all stock transfers with warehouse and user information
$sql = "SELECT st.*,
        fw.name as from_warehouse_name,
        tw.name as to_warehouse_name,
        u.username as created_by_name,
        a.username as approved_by_name
        FROM stock_transfers st
        LEFT JOIN warehouses fw ON st.from_warehouse_id = fw.id
        LEFT JOIN warehouses tw ON st.to_warehouse_id = tw.id
        LEFT JOIN users u ON st.created_by = u.id
        LEFT JOIN users a ON st.approved_by = a.id
        ORDER BY st.created_at DESC";

$transfers = db_query($sql);

$page_title = 'Stock Transfers';
$page_actions = '<a href="' . BASE_URL . '/modules/stock-transfer/transfer-create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Transfer
                </a>';

include __DIR__ . '/../../templates/header.php';
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-exchange-alt"></i> Stock Transfers
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Transfer #</th>
                        <th>Date</th>
                        <th>From Warehouse</th>
                        <th>To Warehouse</th>
                        <th>Type</th>
                        <th>Items</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transfers)): ?>
                        <?php foreach ($transfers as $transfer): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($transfer['transfer_number']) ?></strong>
                                    <?php if ($transfer['reference_number']): ?>
                                        <br><small class="text-muted">Ref: <?= htmlspecialchars($transfer['reference_number']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($transfer['transfer_date'])) ?></td>
                                <td>
                                    <?php if ($transfer['from_warehouse_name']): ?>
                                        <i class="fas fa-warehouse text-primary"></i>
                                        <?= htmlspecialchars($transfer['from_warehouse_name']) ?>
                                    <?php else: ?>
                                        <i class="fas fa-box text-primary"></i>
                                        <?php
                                        // Parse transfer type for source
                                        $parts = explode('_to_', $transfer['transfer_type']);
                                        echo ucfirst($parts[0] ?? 'Unknown') . ' Stock';
                                        ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($transfer['to_warehouse_name']): ?>
                                        <i class="fas fa-warehouse text-success"></i>
                                        <?= htmlspecialchars($transfer['to_warehouse_name']) ?>
                                    <?php else: ?>
                                        <i class="fas fa-box text-success"></i>
                                        <?php
                                        // Parse transfer type for destination
                                        $parts = explode('_to_', $transfer['transfer_type']);
                                        echo ucfirst($parts[1] ?? 'Unknown') . ' Stock';
                                        ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $type_badges = [
                                        'normal' => '<span class="badge bg-secondary">Normal</span>',
                                        'current_to_damaged' => '<span class="badge bg-primary">ধাপ ১: Current→Damaged</span>',
                                        'damaged_to_rma' => '<span class="badge bg-warning text-dark">ধাপ ২: Damaged→RMA</span>',
                                        'rma_to_current' => '<span class="badge bg-success">ধাপ ৩: RMA→Current</span>',
                                        'rma_to_loss' => '<span class="badge bg-danger">ধাপ ৪: RMA→Loss/Scrap</span>',
                                        // Legacy types
                                        'current_to_rma' => '<span class="badge bg-warning">Current→RMA</span>',
                                        'rma_to_damaged' => '<span class="badge bg-danger">RMA→Damaged</span>',
                                        'damaged_to_good' => '<span class="badge bg-warning">Damaged→Good</span>',
                                        'return_to_good' => '<span class="badge bg-info">Return→Good</span>'
                                    ];
                                    echo $type_badges[$transfer['transfer_type']] ?? '<span class="badge bg-secondary">Normal</span>';
                                    ?>
                                </td>
                                <td><?= number_format($transfer['total_items']) ?></td>
                                <td><?= number_format($transfer['total_quantity'], 2) ?></td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'pending' => '<span class="badge bg-warning">Pending</span>',
                                        'approved' => '<span class="badge bg-info">Approved</span>',
                                        'in_transit' => '<span class="badge bg-primary">In Transit</span>',
                                        'completed' => '<span class="badge bg-success">Completed</span>',
                                        'rejected' => '<span class="badge bg-danger">Rejected</span>',
                                        'cancelled' => '<span class="badge bg-secondary">Cancelled</span>'
                                    ];
                                    echo $status_badges[$transfer['status']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                    ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($transfer['created_by_name']) ?>
                                    <br><small class="text-muted"><?= date('M d, Y', strtotime($transfer['created_at'])) ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_URL ?>/modules/stock-transfer/transfer-view.php?id=<?= $transfer['id'] ?>" 
                                           class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($transfer['status'] === 'pending'): ?>
                                            <button onclick="approveTransfer(<?= $transfer['id'] ?>)" 
                                                    class="btn btn-sm btn-success" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <a href="<?= BASE_URL ?>/modules/stock-transfer/transfer-delete.php?id=<?= $transfer['id'] ?>" 
                                               class="btn btn-sm btn-danger btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php elseif ($transfer['status'] === 'approved'): ?>
                                            <button onclick="completeTransfer(<?= $transfer['id'] ?>)" 
                                                    class="btn btn-sm btn-success" title="Mark Completed">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                            <a href="<?= BASE_URL ?>/modules/stock-transfer/transfer-delete.php?id=<?= $transfer['id'] ?>" 
                                               class="btn btn-sm btn-danger btn-delete" title="Cancel Transfer">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
function approveTransfer(id) {
    if (confirm('Are you sure you want to approve this transfer?')) {
        fetch('<?= BASE_URL ?>/api/stock-transfer/approve-transfer.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({transfer_id: id, action: 'approve'})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Transfer approved successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to approve transfer', 'error');
            }
        })
        .catch(error => {
            showAlert('Error: ' + error.message, 'error');
        });
    }
}

function markInTransit(id) {
    if (confirm('Mark this transfer as in transit?')) {
        fetch('<?= BASE_URL ?>/api/stock-transfer/approve-transfer.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({transfer_id: id, action: 'in_transit'})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Transfer marked as in transit', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to update transfer', 'error');
            }
        });
    }
}

function completeTransfer(id) {
    if (confirm('Mark this transfer as completed? Stock will be added to destination warehouse.')) {
        fetch('<?= BASE_URL ?>/api/stock-transfer/approve-transfer.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({transfer_id: id, action: 'complete'})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Transfer completed successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to complete transfer', 'error');
            }
        });
    }
}
</script>
