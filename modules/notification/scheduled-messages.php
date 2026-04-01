<?php
/**
 * Scheduled Messages
 * Schedule messages for specific dates (festivals, birthdays)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$success_message = '';
$error_message = '';

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $data = [
                'title' => clean_input($_POST['title']),
                'template_id' => !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null,
                'message' => $_POST['message'],
                'target_type' => clean_input($_POST['target_type']),
                'target_data' => !empty($_POST['customer_ids']) ? json_encode($_POST['customer_ids']) : null,
                'scheduled_date' => $_POST['scheduled_date'],
                'scheduled_time' => $_POST['scheduled_time'] ?: '09:00:00',
                'status' => 'pending',
                'created_by' => get_current_user_id()
            ];
            
            if (empty($data['title']) || empty($data['scheduled_date'])) {
                $error_message = 'Title and date are required';
            } else {
                db_insert('scheduled_messages', $data);
                $success_message = 'Message scheduled successfully!';
            }
        } elseif ($action === 'cancel') {
            db_update('scheduled_messages', ['status' => 'cancelled'], ['id' => (int)$_POST['schedule_id']]);
            $success_message = 'Schedule cancelled!';
        } elseif ($action === 'delete') {
            db_query("DELETE FROM scheduled_messages WHERE id = ?", [(int)$_POST['schedule_id']]);
            $success_message = 'Schedule deleted!';
        }
    }
}

$scheduled = db_query("SELECT sm.*, mt.name as template_name FROM scheduled_messages sm LEFT JOIN message_templates mt ON sm.template_id = mt.id ORDER BY sm.scheduled_date ASC, sm.scheduled_time ASC");
$templates = db_query("SELECT * FROM message_templates WHERE is_active = 1 ORDER BY name");
$customers = db_query("SELECT id, name, phone FROM customers WHERE phone IS NOT NULL AND phone != '' ORDER BY name");

$page_title = 'Scheduled Messages';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.schedule-card { border-left: 4px solid var(--primary-color); border-radius: 10px; padding: 15px; margin-bottom: 12px; background: var(--card-bg); border: 1px solid var(--border-color); border-left: 4px solid; }
.schedule-card.pending { border-left-color: #0d6efd; }
.schedule-card.sent { border-left-color: #198754; }
.schedule-card.cancelled { border-left-color: #6c757d; opacity: 0.6; }
.date-badge { font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 20px; }
</style>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-clock text-primary"></i> Scheduled Messages</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
            <i class="fas fa-plus"></i> Schedule New
        </button>
    </div>

    <!-- Upcoming -->
    <div class="row">
        <div class="col-lg-8">
            <h6 class="text-muted mb-3"><i class="fas fa-calendar-alt"></i> Schedule Queue</h6>
            <?php if (empty($scheduled)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-calendar-plus fa-3x mb-3 d-block"></i>
                    No scheduled messages. Click "Schedule New" to create one.
                </div>
            <?php else: ?>
                <?php foreach ($scheduled as $s): ?>
                <div class="schedule-card <?= $s['status'] ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1"><?= htmlspecialchars($s['title']) ?></h6>
                            <small class="text-muted"><?= htmlspecialchars(substr($s['message'], 0, 100)) ?>...</small>
                        </div>
                        <div class="text-end">
                            <span class="date-badge bg-<?= $s['status'] === 'pending' ? 'primary' : ($s['status'] === 'sent' ? 'success' : 'secondary') ?> text-white">
                                <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($s['scheduled_date'])) ?>
                            </span>
                            <br><small class="text-muted"><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($s['scheduled_time'])) ?></small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div>
                            <span class="badge bg-<?= $s['status'] === 'pending' ? 'warning' : ($s['status'] === 'sent' ? 'success' : 'secondary') ?>">
                                <?= ucfirst($s['status']) ?>
                            </span>
                            <span class="badge bg-light text-dark">
                                <?= ucfirst($s['target_type']) ?>
                                <?php if ($s['template_name']): ?> | <?= htmlspecialchars($s['template_name']) ?><?php endif; ?>
                            </span>
                        </div>
                        <?php if ($s['status'] === 'pending'): ?>
                        <div>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-ban"></i> Cancel</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-star"></i> Quick Ideas</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="fas fa-moon text-success"></i> <strong>Eid Mubarak</strong> — Schedule for Eid day</li>
                        <li class="mb-2"><i class="fas fa-fire text-warning"></i> <strong>Pohela Boishakh</strong> — Bengali New Year</li>
                        <li class="mb-2"><i class="fas fa-gift text-info"></i> <strong>Customer Birthday</strong> — Special discount</li>
                        <li class="mb-2"><i class="fas fa-calendar-check text-primary"></i> <strong>Independence Day</strong> — National day</li>
                        <li class="mb-0"><i class="fas fa-snowflake text-danger"></i> <strong>Holiday Season</strong> — Year end offers</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clock"></i> Schedule Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Title / Occasion</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Eid Mubarak Greeting" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Target</label>
                            <select name="target_type" class="form-select">
                                <option value="all">All Customers</option>
                                <option value="individual">Selected</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Channel</label>
                            <select name="channel" class="form-select">
                                <option value="sms">📱 SMS</option>
                                <option value="whatsapp">💬 WhatsApp</option>
                                <option value="telegram">✈️ Telegram</option>
                                <option value="email">📧 Email</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Date</label>
                            <input type="date" name="scheduled_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Time</label>
                            <input type="time" name="scheduled_time" class="form-control" value="09:00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Load Template</label>
                        <select name="template_id" class="form-select" onchange="loadTpl(this)">
                            <option value="">— Write custom —</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= $t['id'] ?>" data-content="<?= htmlspecialchars($t['content']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message</label>
                        <textarea name="message" id="schedMsg" class="form-control" rows="4" placeholder="Dear {customer_name}, ..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-clock"></i> Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadTpl(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt.dataset.content) document.getElementById('schedMsg').value = opt.dataset.content;
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
