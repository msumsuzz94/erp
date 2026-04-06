<?php
/**
 * Activity Log Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get filter parameters
$user_id = get_param('user_id', '');
$action = get_param('action', '');
$date_from = get_param('date_from', '');
$date_to = get_param('date_to', '');

// Build query
$is_super_admin = (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1);

$sql = "SELECT al.*, u.username 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE 1=1";

if (!$is_super_admin) {
    // Hide activity related specifically to Super Admin role
    $sql .= " AND (u.role_id != 1 OR u.role_id IS NULL)";
}

$params = [];

if (!empty($user_id)) {
    $sql .= " AND al.user_id = ?";
    $params[] = $user_id;
}

if (!empty($action)) {
    $sql .= " AND al.action LIKE ?";
    $params[] = "%$action%";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY al.created_at DESC LIMIT 500";
$logs = db_query($sql, $params);

// Get all users for filter
if ($is_super_admin) {
    $users = db_select('users', [], 'username ASC');
} else {
    $users = db_select('users', ['role_id !=' => 1], 'username ASC');
}

$page_title = 'Activity Log';
include __DIR__ . '/../../templates/header.php';
?>

<!-- Filter Section -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Activity Logs</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>User</label>
                        <select name="user_id" class="form-control">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $user_id == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Action</label>
                        <input type="text" name="action" class="form-control" placeholder="Search action..." value="<?= htmlspecialchars($action) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="activity-log.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Activity Logs Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Activity Logs (Last 500 records)</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="logsTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= format_datetime($log['created_at']) ?></td>
                                <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['description'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Enforce dark mode on the activity log table */
[data-theme="dark"] #logsTable {
    color: var(--text-primary);
}
[data-theme="dark"] #logsTable th,
[data-theme="dark"] #logsTable td {
    background-color: var(--card-bg);
    border-color: var(--border-color);
    color: var(--text-primary);
}
[data-theme="dark"] .dataTables_wrapper .dataTables_info,
[data-theme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button {
    color: var(--text-primary) !important;
}
[data-theme="dark"] .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
    color: var(--text-muted) !important;
}
[data-theme="dark"] .dataTables_wrapper .dataTables_length,
[data-theme="dark"] .dataTables_wrapper .dataTables_filter {
    color: var(--text-primary);
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#logsTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]]
    });
});
</script>
