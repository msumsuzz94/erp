<?php
/**
 * Service Status - Track service ticket status
 * Paid Service Module
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$search = get_param('q', '');
$ticket = null;
$parts = [];
$status_log = [];

if ($search) {
    $ticket = db_query_one("SELECT * FROM service_tickets WHERE ticket_number = ? OR customer_phone = ? OR id = ?", [$search, $search, (int)$search]);
    if ($ticket) {
        $parts = db_query("SELECT * FROM service_ticket_parts WHERE ticket_id = ? ORDER BY id", [$ticket['id']]);
        $status_log = db_query("SELECT sl.*, u.username as changed_by_name FROM service_status_log sl LEFT JOIN users u ON sl.changed_by = u.id WHERE sl.ticket_id = ? ORDER BY sl.id DESC", [$ticket['id']]);
    }
}

// All active (non-delivered) tickets
$active_tickets = db_query("SELECT id, ticket_number, customer_name, customer_phone, device_type, brand, model, status, created_at, estimated_delivery_date, total_amount, payment_status FROM service_tickets WHERE status != 'Delivered' ORDER BY FIELD(status, 'Ready to Deliver','In Progress','Waiting for Parts','Inspection','Pending','Cannot be Fixed'), id DESC");

$page_title = 'Service Status';
$page_actions = '<a href="service-intake.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Ticket</a>';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .search-hero {
        background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
        color: white; padding: 30px; border-radius: 10px; margin-bottom: 25px; text-align: center;
    }
    .search-hero h2 { margin-bottom: 15px; }
    .search-box { max-width: 500px; margin: 0 auto; }
    .search-box .input-group { background: rgba(255,255,255,0.15); border-radius: 8px; overflow: hidden; }
    .search-box input { background: white; border: none; padding: 12px 20px; font-size: 1.1rem; }
    .search-box button { padding: 12px 25px; }

    /* Status stepper */
    .status-stepper { display: flex; justify-content: space-between; margin: 25px 0; position: relative; }
    .status-stepper::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 3px; background: #e5e7eb; z-index: 0; }
    .step { text-align: center; position: relative; z-index: 1; flex: 1; }
    .step .dot {
        width: 40px; height: 40px; border-radius: 50%; margin: 0 auto 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem; font-weight: bold; transition: all 0.3s;
    }
    .step .dot.inactive { background: #e5e7eb; color: #9ca3af; }
    .step .dot.active { background: #0d9488; color: white; box-shadow: 0 0 0 4px rgba(13,148,136,0.2); }
    .step .dot.completed { background: #10b981; color: white; }
    .step .dot.failed { background: #ef4444; color: white; }
    .step .step-label { font-size: 0.7rem; color: #6b7280; text-transform: uppercase; }
    .step .step-label.active { color: #0d9488; font-weight: bold; }

    /* Ticket result card */
    .result-card { border-left: 5px solid #0d9488; }
    .result-card .ticket-big { font-size: 2rem; font-weight: bold; color: #0d9488; }

    /* Active tickets table */
    .status-pending { color: #f59e0b; }
    .status-inspection { color: #3b82f6; }
    .status-waiting { color: #ec4899; }
    .status-progress { color: #6366f1; }
    .status-ready { color: #10b981; font-weight: bold; }
    .status-cannot { color: #ef4444; }

    [data-theme="dark"] .step .dot.inactive { background: #374151; color: #6b7280; }
    [data-theme="dark"] .step .step-label { color: #9ca3af; }
    [data-theme="dark"] .status-stepper::before { background: #374151; }
</style>

<!-- Search Hero -->
<div class="search-hero shadow">
    <h2><i class="fas fa-search-location"></i> Track Service Status</h2>
    <p>Enter your Ticket Number (SRV-XXXX) or Phone Number to check status</p>
    <div class="search-box">
        <form method="GET" class="input-group">
            <input type="text" name="q" class="form-control" placeholder="SRV-0001 or 01XXXXXXXXX" value="<?= htmlspecialchars($search) ?>" autofocus>
            <button type="submit" class="btn btn-warning"><i class="fas fa-search"></i> Track</button>
        </form>
    </div>
</div>

<?php if ($search && $ticket): ?>
<!-- Search Result -->
<div class="card shadow mb-4 result-card">
    <div class="card-body">
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <div class="ticket-big"><?= htmlspecialchars($ticket['ticket_number']) ?></div>
                <p class="mb-0"><?= htmlspecialchars($ticket['customer_name']) ?> | <?= htmlspecialchars($ticket['customer_phone']) ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="badge bg-<?= $ticket['status'] === 'Ready to Deliver' ? 'success' : ($ticket['status'] === 'Cannot be Fixed' ? 'danger' : ($ticket['status'] === 'Delivered' ? 'success' : 'info')) ?> fs-5 px-3 py-2">
                    <?= $ticket['status'] ?>
                </span>
                <br><small class="text-muted">Received: <?= date('d M Y', strtotime($ticket['created_at'])) ?></small>
                <?php if ($ticket['estimated_delivery_date']): ?>
                    <br><small class="text-muted">Est. Delivery: <?= date('d M Y', strtotime($ticket['estimated_delivery_date'])) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status Progress Stepper -->
        <?php
        $all_steps = ['Pending', 'Inspection', 'Waiting for Parts', 'In Progress', 'Ready to Deliver', 'Delivered'];
        $step_icons = ['📥', '🔍', '⏳', '🔧', '✅', '🚚'];
        $current_idx = array_search($ticket['status'], $all_steps);
        $is_failed = ($ticket['status'] === 'Cannot be Fixed');
        ?>
        <div class="status-stepper">
            <?php foreach ($all_steps as $i => $step): ?>
                <?php
                if ($is_failed) {
                    $dot_class = 'inactive';
                } elseif ($i < $current_idx) {
                    $dot_class = 'completed';
                } elseif ($i === $current_idx) {
                    $dot_class = 'active';
                } else {
                    $dot_class = 'inactive';
                }
                ?>
                <div class="step">
                    <div class="dot <?= $dot_class ?>"><?= $step_icons[$i] ?></div>
                    <div class="step-label <?= $dot_class === 'active' ? 'active' : '' ?>"><?= $step ?></div>
                </div>
            <?php endforeach; ?>
            <?php if ($is_failed): ?>
                <div class="step">
                    <div class="dot failed">❌</div>
                    <div class="step-label" style="color:#ef4444; font-weight:bold;">Cannot Fix</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Device & Problem Info -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted mb-3"><i class="fas fa-laptop"></i> Device Info</h6>
                        <p><strong>Type:</strong> <?= htmlspecialchars($ticket['device_type']) ?></p>
                        <p><strong>Brand/Model:</strong> <?= htmlspecialchars(($ticket['brand'] ?? '') . ' ' . ($ticket['model'] ?? '')) ?></p>
                        <?php if ($ticket['serial_number']): ?><p><strong>Serial:</strong> <?= htmlspecialchars($ticket['serial_number']) ?></p><?php endif; ?>
                        <p><strong>Warranty:</strong> <?= htmlspecialchars($ticket['warranty_status']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted mb-3"><i class="fas fa-tools"></i> Problem & Notes</h6>
                        <p><?= nl2br(htmlspecialchars($ticket['problem_description'])) ?></p>
                        <?php if ($ticket['technician_notes']): ?>
                            <hr><p><strong>Tech Notes:</strong> <?= nl2br(htmlspecialchars($ticket['technician_notes'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing Summary (if any) -->
        <?php if ($ticket['total_amount'] > 0): ?>
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="fas fa-receipt"></i> Billing Summary</h6>
                <div class="row">
                    <div class="col-md-3"><strong>Service Charge:</strong> <?= format_currency($ticket['service_charge']) ?></div>
                    <div class="col-md-3"><strong>Parts Cost:</strong> <?= format_currency($ticket['total_parts_cost']) ?></div>
                    <?php if ($ticket['discount'] > 0): ?><div class="col-md-2"><strong>Discount:</strong> -<?= format_currency($ticket['discount']) ?></div><?php endif; ?>
                    <div class="col-md-2"><strong>Total:</strong> <span class="text-success fs-5"><?= format_currency($ticket['total_amount']) ?></span></div>
                    <div class="col-md-2"><strong>Status:</strong> <?= $ticket['payment_status'] ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Status History Timeline -->
        <?php if (!empty($status_log)): ?>
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="text-muted mb-3"><i class="fas fa-history"></i> Status History</h6>
                <table class="table table-sm">
                    <thead><tr><th>Date</th><th>Status</th><th>Notes</th><th>By</th></tr></thead>
                    <tbody>
                        <?php foreach ($status_log as $log): ?>
                        <tr>
                            <td><?= date('d M Y, h:i A', strtotime($log['changed_at'])) ?></td>
                            <td><strong><?= htmlspecialchars($log['new_status']) ?></strong></td>
                            <td><?= htmlspecialchars($log['notes'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($log['changed_by_name'] ?? 'System') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="mt-3 d-flex gap-2">
            <a href="service-view.php?id=<?= $ticket['id'] ?>" class="btn btn-info"><i class="fas fa-edit"></i> Manage Ticket</a>
            <a href="service-receipt.php?id=<?= $ticket['id'] ?>" class="btn btn-secondary" target="_blank"><i class="fas fa-print"></i> Print Receipt</a>
            <?php if ($ticket['total_amount'] > 0): ?>
                <a href="service-invoice.php?id=<?= $ticket['id'] ?>" class="btn btn-success" target="_blank"><i class="fas fa-file-invoice"></i> Print Invoice</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($search && !$ticket): ?>
<div class="alert alert-warning text-center">
    <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>
    <strong>No ticket found</strong> for "<strong><?= htmlspecialchars($search) ?></strong>".<br>
    Please check the ticket number and try again.
</div>
<?php endif; ?>

<!-- Active Tickets Overview -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-clock"></i> Active Service Tickets (<?= count($active_tickets) ?>)</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="activeTable">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Customer</th>
                        <th>Device</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Est. Delivery</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_tickets as $at): ?>
                    <?php
                        $sc = '';
                        switch($at['status']) {
                            case 'Pending': $sc = 'status-pending'; break;
                            case 'Inspection': $sc = 'status-inspection'; break;
                            case 'Waiting for Parts': $sc = 'status-waiting'; break;
                            case 'In Progress': $sc = 'status-progress'; break;
                            case 'Ready to Deliver': $sc = 'status-ready'; break;
                            case 'Cannot be Fixed': $sc = 'status-cannot'; break;
                        }
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($at['ticket_number']) ?></strong></td>
                        <td><?= htmlspecialchars($at['customer_name']) ?><br><small class="text-muted"><?= htmlspecialchars($at['customer_phone']) ?></small></td>
                        <td><?= htmlspecialchars($at['device_type']) ?> <?= $at['brand'] ? '<br><small class="text-muted">'.htmlspecialchars($at['brand'].' '.$at['model']).'</small>' : '' ?></td>
                        <td class="<?= $sc ?>"><strong><?= $at['status'] ?></strong></td>
                        <td><?= date('d M Y', strtotime($at['created_at'])) ?></td>
                        <td><?= $at['estimated_delivery_date'] ? date('d M Y', strtotime($at['estimated_delivery_date'])) : '-' ?></td>
                        <td>
                            <a href="?q=<?= htmlspecialchars($at['ticket_number']) ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                            <a href="service-view.php?id=<?= $at['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
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
    $('#activeTable').DataTable({ "order": [[4, "desc"]], "pageLength": 15 });
});
</script>
