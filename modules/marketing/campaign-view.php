<?php
/**
 * Marketing Campaign View (Report)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_once __DIR__ . '/../../includes/permissions.php';

if (!is_admin() && !has_role(get_current_user_id(), 'Manager')) {
    redirect_with_message('../../index.php', 'Insufficient permissions.', 'danger');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$campaign = db_query_one("SELECT c.*, t.name as template_name, t.body as template_body, u.username as creator
                          FROM marketing_campaigns c
                          LEFT JOIN marketing_templates t ON c.template_id = t.id
                          LEFT JOIN users u ON c.created_by = u.id
                          WHERE c.id = ?", [$id]);

if (!$campaign) {
    redirect_with_message('campaigns.php', 'Campaign not found.', 'danger');
}

$logs = db_query("SELECT * FROM marketing_campaign_logs WHERE campaign_id = ? ORDER BY sent_at DESC", [$id]);

$page_title = 'Campaign Report: ' . $campaign['name'];
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <!-- Campaign Details summary -->
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Overview</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr><th>Name:</th><td><?= htmlspecialchars($campaign['name']) ?></td></tr>
                    <tr><th>Date:</th><td><?= date('d M Y, h:i A', strtotime($campaign['created_at'])) ?></td></tr>
                    <tr><th>Channel:</th><td><span class="badge bg-info text-uppercase"><?= $campaign['channel'] ?></span></td></tr>
                    <tr><th>Status:</th>
                        <td>
                            <?php
                            $colors = ['draft'=>'secondary', 'sending'=>'warning', 'completed'=>'success', 'failed'=>'danger'];
                            $color = $colors[$campaign['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= ucfirst($campaign['status']) ?></span>
                        </td>
                    </tr>
                    <tr><th>Template:</th><td><?= htmlspecialchars($campaign['template_name'] ?? 'N/A') ?></td></tr>
                    <tr><th>Created By:</th><td><?= htmlspecialchars($campaign['creator'] ?? 'Sys') ?></td></tr>
                </table>
                <hr>
                <div class="text-center">
                    <h5 class="mb-0 text-gray-800">Recipients</h5>
                    <div class="row mt-2">
                        <div class="col-4">
                            <div class="text-xs text-muted">Total</div>
                            <div class="h5 font-weight-bold"><?= $campaign['total_recipients'] ?></div>
                        </div>
                        <div class="col-4 border-left">
                            <div class="text-xs text-success">Sent</div>
                            <div class="h5 font-weight-bold text-success"><?= $campaign['sent_count'] ?></div>
                        </div>
                        <div class="col-4 border-left">
                            <div class="text-xs text-danger">Failed</div>
                            <div class="h5 font-weight-bold text-danger"><?= $campaign['failed_count'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card shadow mb-4">
             <div class="card-header py-3">
                 <h6 class="m-0 font-weight-bold text-primary">Original Template Preview</h6>
             </div>
             <div class="card-body bg-light" style="white-space:pre-wrap; font-size: 0.9em;">
<?= htmlspecialchars($campaign['template_body'] ?? 'Template contents unavailable') ?>
             </div>
        </div>
    </div>
    
    <!-- Delivery Logs -->
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3 text-right d-flex justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary mt-1">Delivery Logs</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="logTable">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Time</th>
                                <th>Errors (If any)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['recipient_name']) ?></td>
                                    <td><?= htmlspecialchars($log['recipient_contact']) ?></td>
                                    <td>
                                        <?php if ($log['status'] === 'sent'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Sent</span>
                                        <?php elseif ($log['status'] === 'failed'): ?>
                                            <span class="badge bg-danger"><i class="fas fa-times"></i> Failed</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= ucfirst($log['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('h:i:s A', strtotime($log['sent_at'])) ?></td>
                                    <td class="text-danger"><small><?= htmlspecialchars($log['error_message'] ?? '') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#logTable').DataTable({
        pageLength: 25,
        order: [[3, 'desc']]
    });
});
</script>
