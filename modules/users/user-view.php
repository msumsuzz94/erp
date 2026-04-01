<?php

/**
 * User View Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('users.list');

$user_id = (int)get_param('id');

// Get user data with role information
$sql = "SELECT u.*, r.name as role_name, r.description as role_description 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?";
$user = db_query_one($sql, [$user_id]);

if (!$user) {
    redirect_with_message('users-list.php', 'User not found', 'error');
}

// Security: Prevent non-Super Admins from viewing a Super Admin user detail
if ($user['role_id'] == 1 && $_SESSION['user_role_id'] != 1) {
    redirect_with_message('users-list.php', 'Permission denied. You cannot view Super Admin details.', 'error');
}

// Get user's menu permissions
$permissions_sql = "SELECT m.* FROM menu_items m
                    INNER JOIN role_permissions rp ON m.id = rp.menu_id
                    WHERE rp.role_id = ?
                    ORDER BY m.parent_id, m.sort_order";
$permissions = db_query($permissions_sql, [$user['role_id']]);

// Get unique actions for filter dropdown
$actions_sql = "SELECT DISTINCT action FROM activity_logs WHERE user_id = ? ORDER BY action ASC";
$available_actions = db_query($actions_sql, [$user_id]);

// Get active filter
$filter_action = get_param('activity_action', '');

// Get user's activity log (last 50 activities)
$activity_params = [$user_id];
$activity_sql = "SELECT * FROM activity_logs WHERE user_id = ?";

if (!empty($filter_action)) {
    $activity_sql .= " AND action = ?";
    $activity_params[] = $filter_action;
}

$activity_sql .= " ORDER BY created_at DESC LIMIT 50";
$activities = db_query($activity_sql, $activity_params);

$page_title = 'View User - ' . htmlspecialchars($user['username']);
$page_actions = '<a href="user-edit.php?id=' . $user['id'] . '" class="btn btn-warning"><i class="fas fa-edit"></i> Edit User</a>
                 <a href="users-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .info-label {
        font-weight: 600;
        color: #5a5c69;
        margin-bottom: 5px;
    }

    .info-value {
        color: #3a3b45;
        font-size: 1rem;
        margin-bottom: 15px;
    }

    .user-avatar {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        font-weight: bold;
        margin: 0 auto 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .activity-item {
        padding: 10px;
        border-left: 3px solid #4e73df;
        margin-bottom: 10px;
        background-color: #f8f9fc;
        border-radius: 0 5px 5px 0;
    }

    .permission-badge {
        display: inline-block;
        padding: 5px 10px;
        margin: 5px;
        background-color: #e3f2fd;
        border-radius: 20px;
        font-size: 0.875rem;
        color: #1976d2;
        border: 1px solid #bbdefb;
    }

    .stat-card {
        text-align: center;
        padding: 20px;
        border-radius: 8px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        margin-bottom: 20px;
    }

    .stat-card h3 {
        margin: 0;
        font-size: 2rem;
        font-weight: bold;
    }

    .stat-card p {
        margin: 5px 0 0;
        opacity: 0.9;
    }

    /* Dark Mode Overrides */
    [data-theme="dark"] .info-label {
        color: #a0aec0;
    }

    [data-theme="dark"] .info-value {
        color: #e2e8f0;
    }

    [data-theme="dark"] .activity-item {
        background-color: rgba(255, 255, 255, 0.05);
        border-left-color: #4e73df;
    }

    [data-theme="dark"] .permission-badge {
        background-color: rgba(78, 115, 223, 0.2);
        border-color: rgba(78, 115, 223, 0.3);
        color: #90cdf4;
    }

    [data-theme="dark"] .card {
        background-color: #1e293b;
        border-color: #334155;
    }
</style>

<div class="row">
    <!-- Left Column -->
    <div class="col-lg-4">
        <!-- User Profile Card -->
        <div class="card shadow mb-4">
            <div class="card-body text-center">
                <?php if ($user['photo']): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['photo']) ?>"
                        alt="User Photo"
                        style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin: 0 auto 20px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <?php else: ?>
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['username'], 0, 2)) ?>
                    </div>
                <?php endif; ?>
                <h4 class="mb-1"><?= htmlspecialchars($user['username']) ?></h4>
                <p class="text-muted mb-3"><?= htmlspecialchars($user['email']) ?></p>
                <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?> mb-3" style="font-size: 1rem; padding: 8px 15px;">
                    <?= ucfirst($user['status']) ?>
                </span>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Statistics</h6>
            </div>
            <div class="card-body">
                <div class="info-label">Last Login</div>
                <div class="info-value">
                    <?php if ($user['last_login']): ?>
                        <i class="fas fa-clock text-primary"></i>
                        <?= format_datetime($user['last_login']) ?>
                    <?php else: ?>
                        <span class="text-muted">Never logged in</span>
                    <?php endif; ?>
                </div>

                <div class="info-label">Account Created</div>
                <div class="info-value">
                    <i class="fas fa-calendar text-success"></i>
                    <?= format_datetime($user['created_at']) ?>
                </div>

                <?php if (isset($user['updated_at']) && $user['updated_at']): ?>
                    <div class="info-label">Last Updated</div>
                    <div class="info-value">
                        <i class="fas fa-edit text-warning"></i>
                        <?= format_datetime($user['updated_at']) ?>
                    </div>
                <?php endif; ?>

                <div class="info-label">Total Activities</div>
                <div class="info-value">
                    <i class="fas fa-chart-line text-info"></i>
                    <?= count($activities) ?> recent activities
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="col-lg-8">
        <!-- User Information Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-label">User ID</div>
                        <div class="info-value">#<?= $user['id'] ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Username</div>
                        <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="info-label">Email Address</div>
                        <div class="info-value">
                            <a href="mailto:<?= htmlspecialchars($user['email']) ?>">
                                <?= htmlspecialchars($user['email']) ?>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Role</div>
                        <div class="info-value">
                            <span class="badge bg-primary" style="font-size: 0.9rem; padding: 5px 12px;">
                                <?= htmlspecialchars($user['role_name'] ?? 'No Role') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($user['role_description'])): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="info-label">Role Description</div>
                            <div class="info-value text-muted">
                                <?= htmlspecialchars($user['role_description']) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="info-label">Account Status</div>
                        <div class="info-value">
                            <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>" style="font-size: 0.9rem; padding: 5px 12px;">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Permissions Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-lock"></i> Menu Access Permissions
                </h6>
            </div>
            <div class="card-body">
                <?php if (count($permissions) > 0): ?>
                    <div class="permissions-list">
                        <?php foreach ($permissions as $permission): ?>
                            <span class="permission-badge">
                                <i class="<?= htmlspecialchars($permission['icon'] ?? 'fas fa-circle') ?>"></i>
                                <?= htmlspecialchars($permission['name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No menu permissions assigned to this user's role.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-history"></i> Recent Activity
                </h6>
                <form method="GET" action="" class="form-inline m-0">
                    <input type="hidden" name="id" value="<?= $user_id ?>">
                    <select name="activity_action" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 150px; border-color: #d1d3e2;">
                        <option value="">All Activities</option>
                        <?php foreach ($available_actions as $act): ?>
                            <option value="<?= htmlspecialchars($act['action']) ?>" <?= $filter_action === $act['action'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $act['action']))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body">
                <?php if (count($activities) > 0): ?>
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $activity['action']))) ?></strong>
                                    <?php if (!empty($activity['description'])): ?>
                                        <p class="mb-0 text-muted small">
                                            <?= htmlspecialchars($activity['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?= format_datetime($activity['created_at']) ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">No recent activity to display.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>