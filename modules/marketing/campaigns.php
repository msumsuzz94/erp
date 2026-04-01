<?php
/**
 * Marketing Campaigns List
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

// Optional: action handling like retry or cancel draft
if (is_post() && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $cid = (int)$_POST['campaign_id'];
        $action = $_POST['action'];
        
        if ($action === 'delete') {
            db_query("DELETE FROM marketing_campaign_logs WHERE campaign_id = ?", [$cid]);
            db_query("DELETE FROM marketing_campaigns WHERE id = ?", [$cid]);
            redirect_with_message('campaigns.php', 'Campaign deleted', 'success');
        }
    }
}

$sql = "SELECT c.*, t.name as template_name, u.username as creator
        FROM marketing_campaigns c
        LEFT JOIN marketing_templates t ON c.template_id = t.id
        LEFT JOIN users u ON c.created_by = u.id
        ORDER BY c.created_at DESC";

$campaigns = db_query($sql);

$page_title = 'Marketing Campaigns';
$page_actions = '<a href="campaign-create.php" class="btn btn-primary"><i class="fas fa-paper-plane"></i> New Campaign</a>';

include __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <!-- Quick Stats -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Campaigns</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($campaigns) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-bullhorn fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Campaign History</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="campTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Campaign Name</th>
                        <th>Template</th>
                        <th>Channel</th>
                        <th>Target Audience</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $c): ?>
                        <tr>
                            <td><?= date('d M Y, h:i A', strtotime($c['created_at'])) ?></td>
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= htmlspecialchars($c['template_name'] ?? 'Custom/Deleted') ?></td>
                            <td>
                                <?php
                                $channel_icons = [
                                    'sms' => '<i class="fas fa-sms text-info"></i> SMS',
                                    'email' => '<i class="fas fa-envelope text-warning"></i> Email',
                                    'whatsapp' => '<i class="fab fa-whatsapp text-success"></i> WhatsApp',
                                    'telegram' => '<i class="fab fa-telegram text-primary"></i> Telegram'
                                ];
                                echo $channel_icons[$c['channel']] ?? $c['channel'];
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($c['target_type'] === 'all_customers') echo "All Customers";
                                elseif ($c['target_type'] === 'lead_category') echo "Lead Category #" . $c['target_category_id'];
                                else echo "Custom Audience";
                                ?>
                            </td>
                            <td>
                                <small>
                                    Sent: <span class="text-success"><?= $c['sent_count'] ?></span> | 
                                    Failed: <span class="text-danger"><?= $c['failed_count'] ?></span> | 
                                    Total: <?= $c['total_recipients'] ?>
                                </small>
                                <?php
                                $pct = $c['total_recipients'] > 0 ? (($c['sent_count'] + $c['failed_count']) / $c['total_recipients']) * 100 : 0;
                                ?>
                                <div class="progress progress-sm mb-2">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $pct ?>%"></div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'draft' => 'secondary',
                                    'sending' => 'warning',
                                    'completed' => 'success',
                                    'failed' => 'danger'
                                ];
                                $color = $status_colors[$c['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $color ?>"><?= ucfirst($c['status']) ?></span>
                            </td>
                            <td>
                                <a href="campaign-view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-info" title="View Report">
                                    <i class="fas fa-chart-pie"></i>
                                </a>
                                <?php if ($c['status'] === 'draft' || $c['status'] === 'failed' || $c['status'] === 'completed'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this campaign log?');">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="campaign_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
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
    $('#campTable').DataTable({ order: [[0, 'desc']] });
});
</script>
