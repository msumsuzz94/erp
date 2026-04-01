<?php
/**
 * Service List - View All Service Tickets
 * Paid Service Module
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get filter params
$status_filter = get_param('status', '');
$search = get_param('search', '');

$sql = "SELECT st.*, u.username as created_by_name 
        FROM service_tickets st 
        LEFT JOIN users u ON st.created_by = u.id";
$params = [];
$where = [];

if ($status_filter) {
    $where[] = "st.status = ?";
    $params[] = $status_filter;
}
if ($search) {
    $where[] = "(st.ticket_number LIKE ? OR st.customer_name LIKE ? OR st.customer_phone LIKE ? OR st.serial_number LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY st.id DESC";

$tickets = db_query($sql, $params);

// Status counts
$counts = db_query("SELECT status, COUNT(*) as cnt FROM service_tickets GROUP BY status");
$status_counts = [];
foreach ($counts as $c) $status_counts[$c['status']] = $c['cnt'];
$total = array_sum($status_counts);

$page_title = 'Service List';
$page_actions = '<a href="service-intake.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Service Ticket</a>';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .status-badge { font-size: 0.8rem; padding: 4px 10px; border-radius: 12px; }
    .status-Pending { background: #fef3c7; color: #92400e; }
    .status-Inspection { background: #dbeafe; color: #1e40af; }
    .status-Waiting\ for\ Parts { background: #fce7f3; color: #9d174d; }
    .status-In\ Progress { background: #e0e7ff; color: #3730a3; }
    .status-Ready\ to\ Deliver { background: #d1fae5; color: #065f46; }
    .status-Delivered { background: #6ee7b7; color: #064e3b; }
    .status-Cannot\ be\ Fixed { background: #fecaca; color: #991b1b; }
    .stat-card { text-align: center; padding: 12px; border-radius: 8px; cursor: pointer; transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-2px); }
    .stat-card .count { font-size: 1.8rem; font-weight: bold; }
    .stat-card .label { font-size: 0.75rem; text-transform: uppercase; }
</style>

<!-- Status Summary Cards -->
<div class="row mb-3">
    <div class="col">
        <a href="?status=" class="text-decoration-none">
            <div class="stat-card card shadow-sm">
                <div class="count text-primary"><?= $total ?></div>
                <div class="label text-muted">All</div>
            </div>
        </a>
    </div>
    <?php 
    $status_colors = [
        'Pending' => 'warning', 'Inspection' => 'info', 'Waiting for Parts' => 'pink',
        'In Progress' => 'primary', 'Ready to Deliver' => 'success', 'Delivered' => 'success', 'Cannot be Fixed' => 'danger'
    ];
    foreach ($status_colors as $st => $color): ?>
    <div class="col">
        <a href="?status=<?= urlencode($st) ?>" class="text-decoration-none">
            <div class="stat-card card shadow-sm <?= $status_filter === $st ? 'border-'.$color.' border-2' : '' ?>">
                <div class="count text-<?= $color ?>"><?= $status_counts[$st] ?? 0 ?></div>
                <div class="label text-muted"><?= $st ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Main Table -->
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="serviceTable">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Device</th>
                        <th>Problem</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tickets)): ?>
                        <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['ticket_number']) ?></strong></td>
                                <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($t['customer_name']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($t['customer_phone']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($t['device_type']) ?>
                                    <?php if ($t['brand']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($t['brand'] . ' ' . $t['model']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= htmlspecialchars(mb_substr($t['problem_description'], 0, 50)) ?>...</small></td>
                                <td><span class="status-badge status-<?= $t['status'] ?>"><?= $t['status'] ?></span></td>
                                <td>
                                    <?php if ($t['total_amount'] > 0): ?>
                                        <strong><?= format_currency($t['total_amount']) ?></strong>
                                        <?php if ($t['payment_status'] === 'Paid'): ?>
                                            <br><small class="text-success">Paid</small>
                                        <?php elseif ($t['payment_status'] === 'Partial'): ?>
                                            <br><small class="text-warning">Partial</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="service-view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-info" title="View/Edit">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="service-receipt.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-secondary" title="Print Receipt" target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php if ($t['status'] === 'Delivered' || $t['total_amount'] > 0): ?>
                                        <a href="service-invoice.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-success" title="Invoice" target="_blank">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </a>
                                    <?php endif; ?>
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
    $('#serviceTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 25
    });
});
</script>
