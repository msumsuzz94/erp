<?php
/**
 * Leave Management
 * Handle staff leave applications, approvals, and notifications
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
                'staff_id' => (int)$_POST['staff_id'],
                'leave_type' => clean_input($_POST['leave_type']),
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'reason' => clean_input($_POST['reason']),
                'status' => 'pending'
            ];
            if (empty($data['staff_id']) || empty($data['start_date'])) {
                $error_message = 'Staff and dates are required';
            } else {
                db_insert('leaves', $data);
                redirect_with_message('leave-manage.php', 'Leave application submitted!', 'success');
            }
        } elseif ($action === 'approve' || $action === 'reject') {
            $leave_id = (int)$_POST['leave_id'];
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $admin_note = clean_input($_POST['admin_note'] ?? '');
            
            db_update('leaves', [
                'status' => $status,
                'approved_by' => get_current_user_id(),
                'admin_note' => $admin_note
            ], ['id' => $leave_id]);

            // Notify staff via Notification module
            $leave = db_select_one('leaves', ['id' => $leave_id]);
            if ($leave) {
                $staff = db_select_one('users', ['id' => $leave['staff_id']]);
                if ($staff && !empty($staff['phone'])) {
                    $status_text = $status === 'approved' ? 'APPROVED ✅' : 'REJECTED ❌';
                    $msg = "Dear {$staff['username']}, your leave request ({$leave['leave_type']}) from {$leave['start_date']} to {$leave['end_date']} has been {$status_text}.";
                    if ($admin_note) $msg .= " Note: $admin_note";

                    db_insert('message_log', [
                        'customer_id' => null, 'phone' => $staff['phone'],
                        'message' => $msg, 'type' => clean_input($_POST['channel'] ?? 'sms'), 'category' => 'custom',
                        'status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'),
                        'created_by' => get_current_user_id()
                    ]);
                }
            }
            redirect_with_message('leave-manage.php', "Leave {$status}!", 'success');
        } elseif ($action === 'delete') {
            db_query("DELETE FROM leaves WHERE id = ?", [(int)$_POST['leave_id']]);
            redirect_with_message('leave-manage.php', 'Leave record deleted!', 'success');
        }
    }
}

// Filters
$filter_status = $_GET['status'] ?? '';
$filter_staff = $_GET['staff_id'] ?? '';

$where = "1=1";
$params = [];
if ($filter_status) { $where .= " AND l.status = ?"; $params[] = $filter_status; }
if ($filter_staff) { $where .= " AND l.staff_id = ?"; $params[] = (int)$filter_staff; }

$leaves = db_query("SELECT l.*, u.username as staff_name, u.phone as staff_phone, 
    a.username as approver_name,
    DATEDIFF(l.end_date, l.start_date) + 1 as days
    FROM leaves l 
    LEFT JOIN users u ON l.staff_id = u.id
    LEFT JOIN users a ON l.approved_by = a.id
    WHERE {$where}
    ORDER BY l.created_at DESC", $params);

$staff_list = db_query("SELECT id, name as username FROM staff WHERE status = 'active' ORDER BY name");

// Stats
$pending_count = db_query_one("SELECT COUNT(*) as cnt FROM leaves WHERE status = 'pending'");
$approved_count = db_query_one("SELECT COUNT(*) as cnt FROM leaves WHERE status = 'approved' AND MONTH(start_date) = MONTH(CURDATE())");
$total_days = db_query_one("SELECT SUM(DATEDIFF(end_date, start_date) + 1) as total FROM leaves WHERE status = 'approved' AND MONTH(start_date) = MONTH(CURDATE())");

$type_colors = ['casual' => 'bg-info', 'sick' => 'bg-danger', 'annual' => 'bg-primary', 'unpaid' => 'bg-secondary'];
$status_colors = ['pending' => 'bg-warning text-dark', 'approved' => 'bg-success', 'rejected' => 'bg-danger'];

$page_title = 'Leave Management';
$flash = get_flash_message();
if ($flash['message']) { $success_message = $flash['message']; }
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-calendar-minus text-primary"></i> Leave Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveModal">
            <i class="fas fa-plus"></i> New Leave
        </button>
    </div>

    <!-- Stats -->
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="text-center p-3 rounded" style="background:rgba(255,193,7,0.1);">
                <h2 class="text-warning mb-0"><?= $pending_count['cnt'] ?? 0 ?></h2>
                <small class="text-muted">Pending Applications</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-center p-3 rounded" style="background:rgba(25,135,84,0.1);">
                <h2 class="text-success mb-0"><?= $approved_count['cnt'] ?? 0 ?></h2>
                <small class="text-muted">Approved This Month</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="text-center p-3 rounded" style="background:rgba(13,110,253,0.1);">
                <h2 class="text-primary mb-0"><?= $total_days['total'] ?? 0 ?></h2>
                <small class="text-muted">Total Leave Days (Month)</small>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="staff_id" class="form-select form-select-sm">
                        <option value="">All Staff</option>
                        <?php foreach ($staff_list as $st): ?>
                            <option value="<?= $st['id'] ?>" <?= $filter_staff == $st['id'] ? 'selected' : '' ?>><?= htmlspecialchars($st['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Leave Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Staff</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th width="180">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaves)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-calendar-check fa-2x mb-2 d-block"></i>No leave records</td></tr>
                        <?php else: ?>
                            <?php foreach ($leaves as $l): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($l['staff_name'] ?? 'N/A') ?></strong></td>
                                <td><span class="badge <?= $type_colors[$l['leave_type']] ?? 'bg-secondary' ?>"><?= ucfirst($l['leave_type']) ?></span></td>
                                <td><?= date('d-M-Y', strtotime($l['start_date'])) ?></td>
                                <td><?= date('d-M-Y', strtotime($l['end_date'])) ?></td>
                                <td><strong><?= $l['days'] ?></strong></td>
                                <td><small><?= htmlspecialchars(substr($l['reason'] ?? '', 0, 40)) ?></small></td>
                                <td><span class="badge <?= $status_colors[$l['status']] ?? 'bg-secondary' ?>"><?= ucfirst($l['status']) ?></span></td>
                                <td>
                                    <?php if ($l['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                            <input type="hidden" name="admin_note" value="">
                                            <select name="channel" class="form-select form-select-sm d-inline" style="width:85px;">
                                                <option value="sms">SMS</option>
                                                <option value="whatsapp">WA</option>
                                                <option value="telegram">TG</option>
                                            </select>
                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <small class="text-muted">by <?= htmlspecialchars($l['approver_name'] ?? '-') ?></small>
                                    <?php endif; ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
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

<!-- New Leave Modal -->
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-minus"></i> New Leave Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Staff</label>
                        <select name="staff_id" class="form-select" required>
                            <option value="">Select Staff</option>
                            <?php foreach ($staff_list as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Leave Type</label>
                        <select name="leave_type" class="form-select">
                            <option value="casual">Casual</option>
                            <option value="sick">Sick</option>
                            <option value="annual">Annual</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">From</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">To</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason</label>
                        <textarea name="reason" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
