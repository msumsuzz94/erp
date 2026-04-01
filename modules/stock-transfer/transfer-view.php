<?php
/**
 * Stock Transfer - View Transfer Details
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require login
require_login();

$transfer_id = $_GET['id'] ?? null;
if (!$transfer_id) {
    set_message('Invalid transfer ID', 'danger');
    redirect(BASE_URL . '/modules/stock-transfer/transfer-list.php');
}

// Get transfer details
$sql = "SELECT st.*,
        fw.name as from_warehouse_name, fw.code as from_warehouse_code,
        tw.name as to_warehouse_name, tw.code as to_warehouse_code,
        u.username as created_by_name,
        a.username as approved_by_name
        FROM stock_transfers st
        LEFT JOIN warehouses fw ON st.from_warehouse_id = fw.id
        LEFT JOIN warehouses tw ON st.to_warehouse_id = tw.id
        LEFT JOIN users u ON st.created_by = u.id
        LEFT JOIN users a ON st.approved_by = a.id
        WHERE st.id = ?";

$transfer = db_query_one($sql, [$transfer_id]);

if (!$transfer) {
    set_message('Transfer not found', 'danger');
    redirect(BASE_URL . '/modules/stock-transfer/transfer-list.php');
}

// Get transfer items
$sql_items = "SELECT sti.*, p.name as product_name, p.code as product_code, p.image
              FROM stock_transfer_items sti
              INNER JOIN products p ON sti.product_id = p.id
              WHERE sti.transfer_id = ?
              ORDER BY p.name ASC";

$items = db_query($sql_items, [$transfer_id]);

// For each item, fetch the actual serial numbers if serial_ids are stored
foreach ($items as &$item) {
    if (!empty($item['serial_numbers'])) {
        $serial_ids = json_decode($item['serial_numbers'], true);
        if (is_array($serial_ids) && count($serial_ids) > 0) {
            // Fetch actual serial numbers from product_serials table
            $placeholders = implode(',', array_fill(0, count($serial_ids), '?'));
            $sql_serials = "SELECT serial_number, imei FROM product_serials WHERE id IN ($placeholders)";
            $serial_records = db_query($sql_serials, $serial_ids);
            
            // Create array of actual serial numbers
            $actual_serials = [];
            foreach ($serial_records as $sr) {
                if (!empty($sr['serial_number'])) {
                    $actual_serials[] = $sr['serial_number'];
                } elseif (!empty($sr['imei'])) {
                    $actual_serials[] = $sr['imei'];
                }
            }
            $item['actual_serials'] = $actual_serials;
        }
    }
}

$page_title = 'Transfer Details: ' . $transfer['transfer_number'];
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <!-- Header Card -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-exchange-alt"></i> Transfer #<?= htmlspecialchars($transfer['transfer_number']) ?>
                    </h5>
                    <div>
                        <?php
                        $status_badges = [
                            'pending' => '<span class="badge bg-warning">Pending</span>',
                            'approved' => '<span class="badge bg-info">Approved</span>',
                            'in_transit' => '<span class="badge bg-primary">In Transit</span>',
                            'completed' => '<span class="badge bg-success">Completed</span>',
                            'rejected' => '<span class="badge bg-danger">Rejected</span>',
                            'cancelled' => '<span class="badge bg-secondary">Cancelled</span>'
                        ];
                        echo $status_badges[$transfer['status']] ?? '';
                        ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Transfer Information</h6>
                        
                        <table class="table table-sm">
                            <tr>
                                <th width="150">Transfer Date:</th>
                                <td><?= date('F d, Y', strtotime($transfer['transfer_date'])) ?></td>
                            </tr>
                            <tr>
                                <th>From:</th>
                                <td>
                                    <?php if ($transfer['from_warehouse_name']): ?>
                                        <i class="fas fa-warehouse text-primary"></i>
                                        <strong><?= htmlspecialchars($transfer['from_warehouse_name']) ?></strong>
                                        <span class="badge bg-secondary"><?= $transfer['from_warehouse_code'] ?></span>
                                    <?php else: ?>
                                        <i class="fas fa-box text-primary"></i>
                                        <?php
                                        // Parse transfer type for source
                                        $parts = explode('_to_', $transfer['transfer_type']);
                                        ?>
                                        <strong><?= ucfirst($parts[0] ?? 'Unknown') ?> Stock</strong>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>To:</th>
                                <td>
                                    <?php if ($transfer['to_warehouse_name']): ?>
                                        <i class="fas fa-warehouse text-success"></i>
                                        <strong><?= htmlspecialchars($transfer['to_warehouse_name']) ?></strong>
                                        <span class="badge bg-secondary"><?= $transfer['to_warehouse_code'] ?></span>
                                    <?php else: ?>
                                        <i class="fas fa-box text-success"></i>
                                        <?php
                                        // Parse transfer type for destination
                                        $parts = explode('_to_', $transfer['transfer_type']);
                                        ?>
                                        <strong><?= ucfirst($parts[1] ?? 'Unknown') ?> Stock</strong>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Transfer Type:</th>
                                <td><?= ucwords(str_replace('_', ' ', $transfer['transfer_type'])) ?></td>
                            </tr>
                            <?php if ($transfer['reference_number']): ?>
                            <tr>
                                <th>Reference #:</th>
                                <td><?= htmlspecialchars($transfer['reference_number']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($transfer['challan_number']): ?>
                            <tr>
                                <th>Challan #:</th>
                                <td><?= htmlspecialchars($transfer['challan_number']) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Status & Tracking</h6>
                        
                        <table class="table table-sm">
                            <tr>
                                <th width="150">Created By:</th>
                                <td><?= htmlspecialchars($transfer['created_by_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Created At:</th>
                                <td><?= date('F d, Y H:i', strtotime($transfer['created_at'])) ?></td>
                            </tr>
                            <?php if ($transfer['approved_by']): ?>
                            <tr>
                                <th>Approved By:</th>
                                <td><?= htmlspecialchars($transfer['approved_by_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Approved At:</th>
                                <td><?= date('F d, Y H:i', strtotime($transfer['approved_at'])) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($transfer['completed_at']): ?>
                            <tr>
                                <th>Completed At:</th>
                                <td><?= date('F d, Y H:i', strtotime($transfer['completed_at'])) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        
                        <?php if ($transfer['notes']): ?>
                        <div class="mt-3">
                            <strong>Notes:</strong>
                            <p class="text-muted"><?= nl2br(htmlspecialchars($transfer['notes'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="mt-3">
                    <?php if ($transfer['status'] === 'pending'): ?>
                        <button onclick="approveTransfer(<?= $transfer_id ?>)" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve Transfer
                        </button>
                        <a href="<?= BASE_URL ?>/modules/stock-transfer/transfer-delete.php?id=<?= $transfer_id ?>" 
                           class="btn btn-danger btn-delete">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    <?php elseif ($transfer['status'] === 'approved'): ?>
                        <button onclick="completeTransfer(<?= $transfer_id ?>)" class="btn btn-success">
                            <i class="fas fa-check-double"></i> Mark Completed
                        </button>
                        <a href="<?= BASE_URL ?>/modules/stock-transfer/transfer-delete.php?id=<?= $transfer_id ?>" 
                           class="btn btn-danger btn-delete">
                            <i class="fas fa-times"></i> Cancel Transfer
                        </a>
                    <?php endif; ?>
                    
                    <a href="<?= BASE_URL ?>/modules/stock-transfer/transfer-list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Transfer Items -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Transfer Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th width="60">#</th>
                                <th>Product</th>
                                <th>Code</th>
                                <th width="120">Quantity</th>
                                <th>Serial Numbers</th>
                                <th width="120">Unit Cost</th>
                                <th width="120">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $row_num = 1;
                            $total_cost = 0;
                            foreach ($items as $item): 
                                $total_cost += $item['total_cost'];
                            ?>
                                <tr>
                                    <td><?= $row_num++ ?></td>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td><?= htmlspecialchars($item['product_code']) ?></td>
                                    <td><?= number_format($item['quantity'], 2) ?></td>
                                    <td>
                                        <?php
                                        if (!empty($item['actual_serials'])) {
                                            echo '<div class="badge-container">';
                                            foreach ($item['actual_serials'] as $serial) {
                                                echo '<span class="badge bg-info me-1 mb-1">' . htmlspecialchars($serial) . '</span>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?= format_currency($item['unit_cost']) ?></td>
                                    <td><?= format_currency($item['total_cost']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="text-end">Totals:</th>
                                <th><?= number_format($transfer['total_quantity'], 2) ?></th>
                                <th></th>
                                <th></th>
                                <th><?= format_currency($total_cost) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6 offset-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6>Summary</h6>
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <th>Total Items:</th>
                                        <td class="text-end"><?= number_format($transfer['total_items']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Quantity:</th>
                                        <td class="text-end"><?= number_format($transfer['total_quantity'], 2) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Value:</th>
                                        <td class="text-end"><strong><?= format_currency($total_cost) ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
function approveTransfer(id) {
    if (confirm('Are you sure you want to approve this transfer?')) {
        updateTransferStatus(id, 'approve');
    }
}

function completeTransfer(id) {
    if (confirm('Mark this transfer as completed? Stock will be updated in destination inventory.')) {
        updateTransferStatus(id, 'complete');
    }
}

function updateTransferStatus(id, action) {
    fetch('<?= BASE_URL ?>/api/stock-transfer/approve-transfer.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({transfer_id: id, action: action})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message || 'Operation failed', 'error');
        }
    })
    .catch(error => {
        showAlert('Error: ' + error.message, 'error');
    });
}
</script>
