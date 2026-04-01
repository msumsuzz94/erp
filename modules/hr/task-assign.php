<?php
/**
 * Task Assignment
 * Assign, track and manage staff tasks with notification
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
                'description' => clean_input($_POST['description']),
                'assigned_to' => (int)$_POST['assigned_to'],
                'assigned_by' => get_current_user_id(),
                'service_ticket_id' => !empty($_POST['service_ticket_id']) ? (int)$_POST['service_ticket_id'] : null,
                'priority' => clean_input($_POST['priority']),
                'deadline' => !empty($_POST['deadline']) ? $_POST['deadline'] : null,
                'status' => 'pending'
            ];
            if (empty($data['title'])) { $error_message = 'Task title is required'; }
            else {
                db_insert('staff_tasks', $data);
                
                // Notify staff
                $staff = db_select_one('users', ['id' => $data['assigned_to']]);
                if ($staff && !empty($staff['phone'])) {
                    $msg = "New Task: {$data['title']}. Priority: " . ucfirst($data['priority']) . ".";
                    if ($data['deadline']) $msg .= " Deadline: " . date('d-M-Y', strtotime($data['deadline'])) . ".";
                    if ($data['description']) $msg .= " Details: " . substr($data['description'], 0, 80);
                    
                    db_insert('message_log', [
                        'phone' => $staff['phone'], 'message' => $msg,
                        'type' => clean_input($_POST['channel'] ?? 'sms'), 'category' => 'custom',
                        'status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'),
                        'created_by' => get_current_user_id()
                    ]);
                }
                $success_message = 'Task assigned & staff notified!';
            }
        } elseif ($action === 'update_status') {
            $task_id = (int)$_POST['task_id'];
            $new_status = clean_input($_POST['new_status']);
            $update = ['status' => $new_status];
            if ($new_status === 'completed') $update['completed_at'] = date('Y-m-d H:i:s');
            db_update('staff_tasks', $update, ['id' => $task_id]);
            $success_message = 'Task status updated!';
        } elseif ($action === 'delete') {
            db_query("DELETE FROM staff_tasks WHERE id = ?", [(int)$_POST['task_id']]);
            $success_message = 'Task deleted!';
        }
    }
}

$filter = $_GET['filter'] ?? 'active';
$where = $filter === 'completed' ? "t.status = 'completed'" : ($filter === 'all' ? "1=1" : "t.status IN ('pending','in_progress')");

$tasks = db_query("SELECT t.*, u.username as assigned_name, a.username as assigner_name,
    st.ticket_number
    FROM staff_tasks t 
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users a ON t.assigned_by = a.id
    LEFT JOIN service_tickets st ON t.service_ticket_id = st.id
    WHERE {$where}
    ORDER BY FIELD(t.priority,'urgent','high','medium','low'), t.created_at DESC");

$staff_list = db_query("SELECT id, name as username FROM staff WHERE status = 'active' ORDER BY name");
$service_tickets = db_query("SELECT id, ticket_number, customer_name, device_name FROM service_tickets WHERE status != 'delivered' ORDER BY created_at DESC LIMIT 20");

$priority_colors = ['low' => 'bg-secondary', 'medium' => 'bg-info', 'high' => 'bg-warning text-dark', 'urgent' => 'bg-danger'];
$status_colors = ['pending' => 'bg-warning text-dark', 'in_progress' => 'bg-primary', 'completed' => 'bg-success', 'cancelled' => 'bg-secondary'];

$page_title = 'Task Assignment';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.task-item { border: 1px solid var(--border-color); border-radius: 10px; padding: 15px; margin-bottom: 10px; transition: all 0.2s; }
.task-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
.task-item.urgent { border-left: 4px solid #dc3545; }
.task-item.high { border-left: 4px solid #ffc107; }
.task-item.medium { border-left: 4px solid #0dcaf0; }
.task-item.low { border-left: 4px solid #6c757d; }
.deadline-badge { font-size: 11px; }
</style>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-tasks text-primary"></i> Task Assignment</h4>
        <div>
            <div class="btn-group btn-group-sm me-2">
                <a href="?filter=active" class="btn btn-<?= $filter === 'active' ? 'primary' : 'outline-primary' ?>">Active</a>
                <a href="?filter=completed" class="btn btn-<?= $filter === 'completed' ? 'primary' : 'outline-primary' ?>">Completed</a>
                <a href="?filter=all" class="btn btn-<?= $filter === 'all' ? 'primary' : 'outline-primary' ?>">All</a>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal">
                <i class="fas fa-plus"></i> New Task
            </button>
        </div>
    </div>

    <!-- Stats -->
    <?php
    $pending = db_query_one("SELECT COUNT(*) as c FROM staff_tasks WHERE status='pending'");
    $in_prog = db_query_one("SELECT COUNT(*) as c FROM staff_tasks WHERE status='in_progress'");
    $comp_today = db_query_one("SELECT COUNT(*) as c FROM staff_tasks WHERE status='completed' AND DATE(completed_at)=CURDATE()");
    $overdue = db_query_one("SELECT COUNT(*) as c FROM staff_tasks WHERE status IN ('pending','in_progress') AND deadline < NOW() AND deadline IS NOT NULL");
    ?>
    <div class="row mb-4 g-2">
        <div class="col-3"><div class="text-center p-2 rounded" style="background:rgba(255,193,7,0.1);"><strong class="text-warning"><?= $pending['c'] ?></strong><br><small>Pending</small></div></div>
        <div class="col-3"><div class="text-center p-2 rounded" style="background:rgba(13,110,253,0.1);"><strong class="text-primary"><?= $in_prog['c'] ?></strong><br><small>In Progress</small></div></div>
        <div class="col-3"><div class="text-center p-2 rounded" style="background:rgba(25,135,84,0.1);"><strong class="text-success"><?= $comp_today['c'] ?></strong><br><small>Done Today</small></div></div>
        <div class="col-3"><div class="text-center p-2 rounded" style="background:rgba(220,53,69,0.1);"><strong class="text-danger"><?= $overdue['c'] ?></strong><br><small>Overdue</small></div></div>
    </div>

    <!-- Task List -->
    <?php if (empty($tasks)): ?>
        <div class="text-center py-5 text-muted"><i class="fas fa-clipboard-check fa-3x mb-3 d-block"></i>No tasks found</div>
    <?php else: ?>
        <?php foreach ($tasks as $t): 
            $is_overdue = $t['deadline'] && $t['status'] !== 'completed' && strtotime($t['deadline']) < time();
        ?>
        <div class="task-item <?= $t['priority'] ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge <?= $priority_colors[$t['priority']] ?>"><?= ucfirst($t['priority']) ?></span>
                        <span class="badge <?= $status_colors[$t['status']] ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span>
                        <?php if ($t['ticket_number']): ?>
                            <span class="badge bg-light text-dark"><i class="fas fa-tools"></i> <?= htmlspecialchars($t['ticket_number']) ?></span>
                        <?php endif; ?>
                        <?php if ($is_overdue): ?>
                            <span class="badge bg-danger deadline-badge"><i class="fas fa-exclamation-triangle"></i> OVERDUE</span>
                        <?php endif; ?>
                    </div>
                    <h6 class="mb-1"><?= htmlspecialchars($t['title']) ?></h6>
                    <?php if ($t['description']): ?>
                        <small class="text-muted"><?= htmlspecialchars(substr($t['description'], 0, 100)) ?></small>
                    <?php endif; ?>
                    <div class="mt-1">
                        <small class="text-muted">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($t['assigned_name'] ?? '-') ?>
                            <?php if ($t['deadline']): ?>
                                | <i class="fas fa-clock"></i> <?= date('d-M-Y h:i A', strtotime($t['deadline'])) ?>
                            <?php endif; ?>
                            | <small>by <?= htmlspecialchars($t['assigner_name'] ?? '-') ?></small>
                        </small>
                    </div>
                </div>
                <div class="d-flex gap-1">
                    <?php if ($t['status'] !== 'completed'): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                            <?php if ($t['status'] === 'pending'): ?>
                                <input type="hidden" name="new_status" value="in_progress">
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Start"><i class="fas fa-play"></i></button>
                            <?php else: ?>
                                <input type="hidden" name="new_status" value="completed">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Complete"><i class="fas fa-check"></i></button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Task Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Repair customer laptop" required>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Assign To</label>
                            <select name="assigned_to" class="form-select" required>
                                <option value="">Select Staff</option>
                                <?php foreach ($staff_list as $st): ?>
                                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Notify Via</label>
                            <select name="channel" class="form-select">
                                <option value="sms">📱 SMS</option>
                                <option value="whatsapp">💬 WhatsApp</option>
                                <option value="telegram" selected>✈️ Telegram</option>
                                <option value="email">📧 Email</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Deadline</label>
                            <input type="datetime-local" name="deadline" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Link to Service Ticket (optional)</label>
                        <select name="service_ticket_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($service_tickets as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['ticket_number']) ?> - <?= htmlspecialchars($st['customer_name'] ?? '') ?> (<?= htmlspecialchars($st['device_name'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Task details, customer info, problem description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Assign Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
