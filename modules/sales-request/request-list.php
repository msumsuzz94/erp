<?php
/**
 * Sales Request List
 * View, filter, and process sales requests
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$user_id = get_current_user_id();
$is_sr = is_sr($user_id);

// Handle Status Updates (Reject / Approve) right here if admin
if (is_post() && isset($_POST['action']) && !$is_sr) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $req_id = (int)$_POST['request_id'];
        $action = $_POST['action'];
        
        if ($action === 'reject') {
            db_update('sales_requests', 
                ['status' => 'rejected', 'notes' => $_POST['notes']], 
                ['id' => $req_id]
            );
            redirect_with_message('request-list.php', 'Request rejected', 'success');
        } elseif ($action === 'approve') {
            db_update('sales_requests', 
                ['status' => 'approved', 'approved_by' => $user_id], 
                ['id' => $req_id]
            );
            redirect_with_message('request-list.php', 'Request approved. You can now process it as a sale.', 'success');
        }
    }
}

// Fetch requests
$where = "1=1";
$params = [];

if ($is_sr) {
    $where .= " AND r.sr_user_id = ?";
    $params[] = $user_id;
} else {
    // Admin filtering
    if (!empty($_GET['status'])) {
        $where .= " AND r.status = ?";
        $params[] = $_GET['status'];
    }
}

$sql = "SELECT r.*, u.username as sr_name 
        FROM sales_requests r 
        LEFT JOIN users u ON r.sr_user_id = u.id 
        WHERE $where 
        ORDER BY r.created_at DESC";

$requests = db_query($sql, $params);

$page_title = 'Sales Requests';
$page_actions = '';
if ($is_sr || is_admin($user_id)) {
    $page_actions .= '<a href="request-create.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Request</a>';
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Sales Requests</h6>
        <?php if (!$is_sr): ?>
            <form method="GET" class="form-inline">
                <select name="status" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : '' ?>>Approved</option>
                    <option value="completed" <?= (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : '' ?>>Completed</option>
                    <option value="rejected" <?= (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : '' ?>>Rejected</option>
                </select>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="requestTable">
                <thead>
                    <tr>
                        <th>Req Number</th>
                        <th>Date</th>
                        <?php if (!$is_sr): ?>
                        <th>SR Name</th>
                        <?php endif; ?>
                        <th>Customer</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($requests)): ?>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><?= htmlspecialchars($req['request_number']) ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($req['created_at'])) ?></td>
                                <?php if (!$is_sr): ?>
                                <td><?= htmlspecialchars($req['sr_name'] ?? 'Unknown') ?></td>
                                <?php endif; ?>
                                <td>
                                    <?= htmlspecialchars($req['customer_name'] ?? 'Walk-in') ?>
                                    <?php if ($req['customer_phone']) echo '<br><small>'.$req['customer_phone'].'</small>'; ?>
                                </td>
                                <td><?= format_currency($req['net_amount']) ?></td>
                                <td>
                                    <?php
                                    $badges = [
                                        'pending' => 'warning',
                                        'approved' => 'info',
                                        'processing' => 'primary',
                                        'completed' => 'success',
                                        'rejected' => 'danger'
                                    ];
                                    $badge = $badges[$req['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= ucfirst($req['status']) ?></span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info view-btn" data-id="<?= $req['id'] ?>" data-details="<?= htmlspecialchars(json_encode($req)) ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="request-invoice.php?id=<?= $req['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="Print Invoice">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    
                                    <?php if (!$is_sr): ?>
                                        <?php if ($req['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Approve this request?');">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-sm btn-danger reject-btn" data-id="<?= $req['id'] ?>">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($req['status'] === 'approved'): ?>
                                            <a href="../sales/pos.php?from_request=<?= $req['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-cash-register"></i> Process Sale
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (empty($requests)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                    No sales requests found
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: View Details -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewDetailsContent">
                <!-- Filled via JS -->
            </div>
        </div>
    </div>
</div>

<!-- Modal: Reject -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="request_id" id="reject_request_id">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="form-group">
                        <label>Reason for Rejection / Notes</label>
                        <textarea name="notes" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    if ($('#requestTable tbody tr').length > 0) {
        $('#requestTable').DataTable({
            pageLength: 25,
            order: [[1, 'desc']]
        });
    }
    
    $('.view-btn').click(function() {
        const req = $(this).data('details');
        const items = JSON.parse(req.items);
        
        let html = `
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Request No:</strong> ${req.request_number}<br>
                    <strong>Date:</strong> ${req.created_at}<br>
                    <strong>Status:</strong> <span class="badge bg-secondary">${req.status.toUpperCase()}</span>
                </div>
                <div class="col-md-6">
                    <strong>Customer:</strong> ${req.customer_name || 'Walk-in'}<br>
                    <strong>Phone:</strong> ${req.customer_phone || 'N/A'}<br>
                    <strong>SR User:</strong> ${req.sr_name || 'N/A'}
                </div>
            </div>
            
            <table class="table table-sm table-bordered">
                <thead>
                    <tr class="table-light">
                        <th>Product</th>
                        <th>Code</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        items.forEach(item => {
            const subtotal = item.unit_price * item.quantity;
            html += `
                <tr>
                    <td>${item.product_name}</td>
                    <td>${item.product_code}</td>
                    <td class="text-end">${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td class="text-center">${item.quantity}</td>
                    <td class="text-end">${subtotal.toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += `
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Discount:</th>
                        <th class="text-end">${parseFloat(req.discount).toFixed(2)}</th>
                    </tr>
                    <tr>
                        <th colspan="4" class="text-end">Total Amount:</th>
                        <th class="text-end">${parseFloat(req.net_amount).toFixed(2)}</th>
                    </tr>
                </tfoot>
            </table>
        `;
        
        if (req.notes) {
            html += `
                <div class="mt-3">
                    <strong>Notes:</strong><br>
                    <p class="text-muted border p-2 bg-light">${req.notes}</p>
                </div>
            `;
        }
        
        $('#viewDetailsContent').html(html);
        $('#viewModal').modal('show');
    });
    
    $('.reject-btn').click(function() {
        $('#reject_request_id').val($(this).data('id'));
        $('#rejectModal').modal('show');
    });
});
</script>
