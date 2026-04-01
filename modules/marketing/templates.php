<?php
/**
 * Marketing Templates List
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_once __DIR__ . '/../../includes/permissions.php';

// Require Admin/Marketing role
if (!is_admin() && !has_role(get_current_user_id(), 'Manager')) {
    redirect_with_message('../../index.php', 'Insufficient permissions.', 'danger');
}

if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $del_id = (int)$_POST['delete_id'];
        
        $count = db_query_one("SELECT COUNT(*) as count FROM marketing_campaigns WHERE template_id = ?", [$del_id])['count'];
        if ($count > 0) {
            redirect_with_message('templates.php', "Cannot delete template used in $count campaigns.", 'danger');
        } else {
            db_query("DELETE FROM marketing_templates WHERE id = ?", [$del_id]);
            redirect_with_message('templates.php', 'Template deleted', 'success');
        }
    }
}

$templates = db_query("SELECT * FROM marketing_templates ORDER BY created_at DESC");

$page_title = 'Marketing Templates';
$page_actions = '<a href="template-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Template</a>';

include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Message Templates</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="dataTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Channel</th>
                        <th>Subject</th>
                        <th>Body Preview</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['name']) ?></td>
                            <td>
                                <?php
                                $channel_icons = [
                                    'sms' => '<i class="fas fa-sms text-info"></i> SMS',
                                    'email' => '<i class="fas fa-envelope text-warning"></i> Email',
                                    'whatsapp' => '<i class="fab fa-whatsapp text-success"></i> WhatsApp',
                                    'telegram' => '<i class="fab fa-telegram text-primary"></i> Telegram'
                                ];
                                echo $channel_icons[$t['channel']] ?? $t['channel'];
                                ?>
                            </td>
                            <td><?= htmlspecialchars($t['subject'] ?: '-') ?></td>
                            <td>
                                <span class="d-inline-block text-truncate" style="max-width: 250px;">
                                    <?= htmlspecialchars($t['body']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $t['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($t['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info view-btn" data-template='<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>'>
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this template?');">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="delete_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTitle">Template Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Channel:</strong> <span id="viewChannel"></span>
                </div>
                <div class="mb-3" id="viewSubjectContainer" style="display:none;">
                    <strong>Subject:</strong> <div id="viewSubject" class="mt-1 border p-2 bg-light"></div>
                </div>
                <div class="mb-3">
                    <strong>Message Body:</strong>
                    <div id="viewBody" class="mt-1 border p-3 bg-light" style="white-space: pre-wrap;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#dataTable').DataTable();
    
    $('.view-btn').click(function() {
        const t = $(this).data('template');
        $('#viewTitle').text(t.name);
        $('#viewChannel').text(t.channel.toUpperCase());
        
        if (t.channel === 'email' && t.subject) {
            $('#viewSubject').text(t.subject);
            $('#viewSubjectContainer').show();
        } else {
            $('#viewSubjectContainer').hide();
        }
        
        $('#viewBody').text(t.body);
        $('#viewModal').modal('show');
    });
});
</script>
