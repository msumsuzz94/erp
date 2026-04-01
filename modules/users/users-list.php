<?php

/**
 * Users List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('users.list');

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $user_id = (int)$_POST['delete_id'];

        if ($user_id == get_current_user_id()) {
            redirect_with_message($_SERVER['PHP_SELF'], 'Cannot delete your own account', 'error');
        }

        $user = db_select_one('users', ['id' => $user_id]);

        if (db_delete('users', ['id' => $user_id])) {
            log_activity(get_current_user_id(), 'delete_user', "Deleted user: {$user['username']}");
            redirect_with_message($_SERVER['PHP_SELF'], 'User deleted successfully', 'success');
        } else {
            redirect_with_message($_SERVER['PHP_SELF'], 'Failed to delete user', 'error');
        }
    }
}

$current_user_role = $_SESSION['user_role_id'];
$where_clause = "";
if ($current_user_role != 1) {
    $where_clause = " WHERE u.role_id != 1 ";
}

$sql = "SELECT u.*, r.name as role_name,
        (SELECT COUNT(id) FROM sales WHERE created_by = u.id AND status != 'cancelled') as total_bills
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        $where_clause
        ORDER BY u.created_at DESC";
$users = db_query($sql);

$page_title = 'Users & Access';
$page_actions = '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <a href="user-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add User</a>';

$additional_css = '
<style>
@media print {
    .btn, .sidebar, #sidebar, .topbar, .navbar, .card-header, .no-print, form { display: none !important; }
    .container-fluid { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
    #content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table-responsive { overflow: visible !important; }
    .table { width: 100% !important; border-collapse: collapse !important; }
    .table th, .table td { border: 1px solid #ddd !important; padding: 8px !important; }
    body { padding-top: 0 !important; background: white !important; }
    .text-gray-800 { color: black !important; }
    .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
    .card-body { padding: 0 !important; }
}
.print-header { display: none; }
</style>
';

include __DIR__ . '/../../templates/header.php';
?>

<div class="print-header">
    <h2><?= htmlspecialchars(BUSINESS_NAME) ?></h2>
    <h4>Users List Report</h4>
    <p>Date: <?= date('d M Y') ?></p>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Users List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="usersTable">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Bills Created</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['role_name'] ?? '-') ?></td>
                            <td><span class="badge bg-info"><?= number_format($user['total_bills'] ?? 0) ?></span></td>
                            <td>
                                <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </td>
                            <td><?= $user['last_login'] ? format_datetime($user['last_login']) : 'Never' ?></td>
                            <td><?= format_date($user['created_at']) ?></td>
                            <td>
                                <a href="user-view.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                <a href="user-edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <?php if ($user['id'] != get_current_user_id()): ?>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#usersTable').DataTable();
    });

    function deleteUser(id, username) {
        if (confirm('Are you sure you want to delete user "' + username + '"?')) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
</script>