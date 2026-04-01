<?php
/**
 * Staff Broadcast
 * Send announcements to all or selected staff via SMS/Email
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

        if ($action === 'broadcast') {
            $title = clean_input($_POST['title']);
            $message = $_POST['message'];
            $channel = clean_input($_POST['channel']);
            $staff_ids = $_POST['staff_ids'] ?? [];

            if (empty($title) || empty($message)) {
                $error_message = 'Title and message are required';
            } elseif (empty($staff_ids)) {
                $error_message = 'Select at least one staff member';
            } else {
                // Log broadcast
                db_insert('staff_broadcasts', [
                    'title' => $title,
                    'message' => $message,
                    'sent_to' => json_encode($staff_ids),
                    'channel' => $channel,
                    'sent_by' => get_current_user_id()
                ]);

                // Load notification functions
                if (!function_exists('send_notification')) {
                    require_once __DIR__ . '/../../includes/sms_functions.php';
                }
                
                // Send to each staff
                $sent = 0;
                $failed = 0;
                foreach ($staff_ids as $sid) {
                    $staff = db_query_one("SELECT id, name as username, phone, email FROM staff WHERE id = ?", [(int)$sid]);
                    if ($staff && (!empty($staff['phone']) || !empty($staff['email']))) {
                        $personalized = str_replace('{staff_name}', $staff['username'], $message);
                        
                        // Actually send the message via gateway
                        $result = ['status' => false, 'message' => 'System only'];
                        if ($channel !== 'system') {
                            $recipient = ($channel === 'email') ? ($staff['email'] ?? '') : $staff['phone'];
                            if (!empty($recipient)) {
                                $result = send_notification($channel, $recipient, $personalized, $title);
                            }
                        } else {
                            $result = ['status' => true, 'message' => 'System notification logged'];
                        }
                        
                        db_insert('message_log', [
                            'phone' => $staff['phone'],
                            'message' => $personalized,
                            'type' => $channel,
                            'category' => 'broadcast',
                            'status' => $result['status'] ? 'sent' : 'failed',
                            'sent_at' => date('Y-m-d H:i:s'),
                            'created_by' => get_current_user_id()
                        ]);
                        
                        if ($result['status']) $sent++;
                        else $failed++;
                    }
                }
                if ($failed > 0) {
                    $success_message = "Broadcast: Sent {$sent}, Failed {$failed}. Check Channel Configuration.";
                } else {
                    $success_message = "Broadcast sent to {$sent} staff member(s)!";
                }
            }
        } elseif ($action === 'delete') {
            db_query("DELETE FROM staff_broadcasts WHERE id = ?", [(int)$_POST['broadcast_id']]);
            $success_message = 'Broadcast record deleted!';
        }
    }
}

$staff_list = db_query("SELECT id, name as username, phone, shift_id FROM staff WHERE status = 'active' ORDER BY name");
$broadcasts = db_query("SELECT b.*, u.username as sender_name FROM staff_broadcasts b LEFT JOIN users u ON b.sent_by = u.id ORDER BY b.created_at DESC LIMIT 50");

// Quick templates
$quick_templates = [
    ['🏢 Meeting Notice', "Dear {staff_name}, there will be a meeting on [DATE] at [TIME]. Please be present. Thank you."],
    ['🎉 Holiday Notice', "Dear {staff_name}, please note that [OCCASION] holiday will be from [DATE] to [DATE]. Happy holidays!"],
    ['💰 Bonus Announcement', "Dear {staff_name}, congratulations! You have received a bonus of [AMOUNT] Tk this month."],
    ['⚠️ Important Reminder', "Dear {staff_name}, this is an important reminder: [MESSAGE]. Please take action. Thank you."],
    ['🏪 Store Closing', "Dear {staff_name}, the store will be closed on [DATE] due to [REASON]. Thank you for your understanding."],
];

$page_title = 'Staff Broadcast';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.broadcast-card { border: 1px solid var(--border-color); border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 4px solid var(--primary-color); }
.quick-tpl { cursor: pointer; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 6px; transition: all 0.2s; font-size: 13px; }
.quick-tpl:hover { background: rgba(78,115,223,0.08); border-color: var(--primary-color); }
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

    <h4 class="mb-4"><i class="fas fa-bullhorn text-primary"></i> Staff Broadcast</h4>

    <form method="POST" id="broadcastForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="action" value="broadcast">
        <div class="row">
            <!-- Compose -->
            <div class="col-lg-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-pen"></i> Compose Broadcast</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold">Title / Subject</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Eid Holiday Notice" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Channel</label>
                                <select name="channel" class="form-select">
                                    <option value="sms">📱 SMS</option>
                                    <option value="whatsapp">💬 WhatsApp</option>
                                    <option value="telegram">✈️ Telegram</option>
                                    <option value="email">📧 Email</option>
                                    <option value="system">🖥️ System Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Message</label>
                            <textarea name="message" id="broadcastMsg" class="form-control" rows="5" 
                                      placeholder="Dear {staff_name}, ..." required></textarea>
                            <small class="text-muted">Use <code>{staff_name}</code> for personalization</small>
                        </div>

                        <!-- Quick Templates -->
                        <h6 class="text-muted mb-2"><i class="fas fa-magic"></i> Quick Templates</h6>
                        <?php foreach ($quick_templates as $qt): ?>
                            <div class="quick-tpl" onclick="document.getElementById('broadcastMsg').value='<?= addslashes($qt[1]) ?>'">
                                <?= $qt[0] ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Staff Selection -->
            <div class="col-lg-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 d-flex justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users"></i> Select Staff</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllStaff()">All</button>
                    </div>
                    <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-hover mb-0">
                            <tbody>
                                <?php foreach ($staff_list as $st): ?>
                                <tr>
                                    <td width="30">
                                        <input type="checkbox" name="staff_ids[]" value="<?= $st['id'] ?>" class="staff-chk" onchange="updateStaffCount()">
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($st['username']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($st['phone'] ?? 'No phone') ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-4">
                    <i class="fas fa-bullhorn"></i> Send Broadcast (<span id="staffCount">0</span> selected)
                </button>
            </div>
        </div>
    </form>

    <!-- Broadcast History -->
    <div class="card shadow-sm">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> Recent Broadcasts</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Channel</th>
                            <th>Recipients</th>
                            <th>Sent By</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($broadcasts)): ?>
                            <tr><td colspan="6" class="text-center py-3 text-muted">No broadcasts yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($broadcasts as $b): 
                                $recipients = json_decode($b['sent_to'] ?? '[]', true);
                            ?>
                            <tr>
                                <td><small><?= date('d-M-Y H:i', strtotime($b['created_at'])) ?></small></td>
                                <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
                                <td><span class="badge bg-info"><?= ucfirst($b['channel']) ?></span></td>
                                <td><span class="badge bg-primary"><?= count($recipients) ?> staff</span></td>
                                <td><?= htmlspecialchars($b['sender_name'] ?? '-') ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="broadcast_id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function selectAllStaff() {
    document.querySelectorAll('.staff-chk').forEach(c => c.checked = true);
    updateStaffCount();
}
function updateStaffCount() {
    document.getElementById('staffCount').textContent = document.querySelectorAll('.staff-chk:checked').length;
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
