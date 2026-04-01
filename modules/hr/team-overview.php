<?php
/**
 * Team Overview Page
 * Visual dashboard showing team structure and statistics
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get statistics
$total_staff = db_query("SELECT COUNT(*) as count FROM staff")[0]['count'];
$active_staff = db_query("SELECT COUNT(*) as count FROM staff WHERE status = 'active'")[0]['count'];
$total_roles = db_query("SELECT COUNT(*) as count FROM staff_roles")[0]['count'];

// Calculate average attendance for current month
$current_month = date('Y-m');
$attendance_sql = "SELECT 
    COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
    COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_day,
    COUNT(*) as total
    FROM attendance 
    WHERE DATE_FORMAT(date, '%Y-%m') = '$current_month'";
$att_data = db_query($attendance_sql)[0];
$avg_attendance = $att_data['total'] > 0 ?
    (($att_data['present'] + ($att_data['half_day'] * 0.5)) / $att_data['total']) * 100 : 0;

// Get staff grouped by role
$staff_by_role = db_query("SELECT 
    sr.id, sr.role_name, sr.description,
    COUNT(s.id) as staff_count
    FROM staff_roles sr
    LEFT JOIN staff s ON sr.id = s.role_id AND s.status = 'active'
    GROUP BY sr.id, sr.role_name, sr.description
    ORDER BY staff_count DESC, sr.role_name ASC");

// Get all active staff with role information
$all_staff = db_query("SELECT 
    s.*, 
    sr.role_name
    FROM staff s
    LEFT JOIN staff_roles sr ON s.role_id = sr.id
    ORDER BY s.name ASC");

// Get recent hires (last 30 days)
$recent_hires = db_query("SELECT name, joining_date 
    FROM staff 
    WHERE joining_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY joining_date DESC
    LIMIT 5");

$page_title = 'Team Overview';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Team Members
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_staff ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Staff</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $active_staff ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Roles</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_roles ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-briefcase fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Avg Attendance (This
                                Month)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($avg_attendance, 1) ?>%</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Team by Role -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Team Structure by Role</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($staff_by_role as $role): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-left-primary h-100">
                                    <div class="card-body p-3">
                                        <h5 class="font-weight-bold text-primary mb-1">
                                            <?= htmlspecialchars($role['role_name']) ?>
                                        </h5>
                                        <p class="text-muted small mb-2">
                                            <?= htmlspecialchars($role['description']) ?>
                                        </p>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-users text-gray-400 mr-2"></i>
                                            <span class="h4 mb-0"><?= $role['staff_count'] ?></span>
                                            <span class="ml-2 text-muted small">members</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Staff Directory -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Staff Directory</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="staffTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Designation</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Joining Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_staff)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No staff members found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_staff as $member): ?>
                                        <tr>
                                            <td>
                                                <?php if ($member['photo']): ?>
                                                    <img src="../../<?= htmlspecialchars($member['photo']) ?>"
                                                        class="rounded-circle mr-2" width="30" height="30" alt="Photo"
                                                        style="object-fit: cover;">
                                                <?php endif; ?>
                                                <strong><?= htmlspecialchars($member['name']) ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($member['role_name']): ?>
                                                    <span
                                                        class="badge badge-info"><?= htmlspecialchars($member['role_name']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($member['designation'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($member['phone'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($member['email'] ?? '-') ?></td>
                                            <td>
                                                <?php if ($member['status'] == 'active'): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $member['joining_date'] ? date('d M Y', strtotime($member['joining_date'])) : '-' ?>
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

        <!-- Recent Activity Sidebar -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Hires</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_hires)): ?>
                        <p class="text-center text-muted">No recent hires</p>
                    <?php else: ?>
                        <ul class="list-unstyled">
                            <?php foreach ($recent_hires as $hire): ?>
                                <li class="mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="icon-circle bg-success text-white mr-3">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div>
                                            <div class="font-weight-bold"><?= htmlspecialchars($hire['name']) ?></div>
                                            <div class="text-muted small">
                                                Joined: <?= date('d M Y', strtotime($hire['joining_date'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Stats</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="small text-muted">Active/Total Staff Ratio</div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success"
                                style="width: <?= $total_staff > 0 ? ($active_staff / $total_staff * 100) : 0 ?>%">
                                <?= $total_staff > 0 ? number_format(($active_staff / $total_staff * 100), 0) : 0 ?>%
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="small text-muted">Attendance Rate (This Month)</div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-warning" style="width: <?= $avg_attendance ?>%">
                                <?= number_format($avg_attendance, 1) ?>%
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="text-center">
                        <a href="staff-list.php" class="btn btn-primary btn-sm btn-block">
                            <i class="fas fa-list"></i> View Full Staff List
                        </a>
                        <a href="staff-roles.php" class="btn btn-info btn-sm btn-block mt-2">
                            <i class="fas fa-briefcase"></i> Manage Roles
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function () {
        $('#staffTable').DataTable({
            order: [[0, 'asc']],
            pageLength: 25
        });
    });
</script>

<style>
    .icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
</style>
