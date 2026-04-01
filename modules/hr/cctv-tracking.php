<?php

/**
 * CCTV Tracking Logs
 * Shows in/out, away/present events from CCTV system
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('hr.cctv_tracking');

$selected_date = get_param('date', date('Y-m-d'));

$logs = db_query("SELECT t.*, s.name as staff_name, s.designation, c.camera_name, c.location as camera_location
    FROM cctv_tracking_logs t 
    JOIN staff s ON t.staff_id = s.id 
    LEFT JOIN cctv_configurations c ON t.camera_id = c.id
    WHERE t.date = ?
    ORDER BY t.created_at DESC", [$selected_date]);

// Stats
$total_away = 0;
$total_duration = 0;
foreach ($logs ?: [] as $log) {
    if ($log['event_type'] === 'away') $total_away++;
    $total_duration += (float)($log['duration_minutes'] ?? 0);
}

$page_title = 'CCTV Tracking';
$page_actions = '<button class="btn btn-success" onclick="window.print()"><i class="fas fa-print"></i> Print</button>';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3">
        <label class="form-label">Date</label>
        <input type="date" class="form-control" value="<?= $selected_date ?>" onchange="location.href='?date='+this.value">
    </div>
    <div class="col-md-3">
        <div class="card border-left-warning h-100">
            <div class="card-body py-2">
                <div class="text-xs font-weight-bold text-warning text-uppercase">Away Events</div>
                <div class="h5 mb-0 font-weight-bold"><?= $total_away ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-danger h-100">
            <div class="card-body py-2">
                <div class="text-xs font-weight-bold text-danger text-uppercase">Total Away Time</div>
                <div class="h5 mb-0 font-weight-bold"><?= round($total_duration, 1) ?> min</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-eye"></i> Tracking Logs — <?= date('d M Y', strtotime($selected_date)) ?></h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Camera</th>
                        <th>Event</th>
                        <th>Away Start</th>
                        <th>Return</th>
                        <th>Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs ?: [] as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['staff_name']) ?><br><small class="text-muted"><?= htmlspecialchars($log['designation'] ?? '') ?></small></td>
                            <td><i class="fas fa-video"></i> <?= htmlspecialchars($log['camera_name'] ?? '-') ?><br><small class="text-muted"><?= htmlspecialchars($log['camera_location'] ?? '') ?></small></td>
                            <td>
                                <?php
                                $colors = ['present' => 'success', 'away' => 'warning', 'return' => 'info'];
                                $icons = ['present' => 'check-circle', 'away' => 'walking', 'return' => 'undo'];
                                $c = $colors[$log['event_type']] ?? 'secondary';
                                $i = $icons[$log['event_type']] ?? 'circle';
                                ?>
                                <span class="badge bg-<?= $c ?>"><i class="fas fa-<?= $i ?>"></i> <?= ucfirst($log['event_type']) ?></span>
                            </td>
                            <td><?= $log['away_start_time'] ? date('h:i:s A', strtotime($log['away_start_time'])) : '-' ?></td>
                            <td><?= $log['return_time'] ? date('h:i:s A', strtotime($log['return_time'])) : '<span class="text-danger">Still Away</span>' ?></td>
                            <td>
                                <?php if ($log['duration_minutes']): ?>
                                    <strong><?= round($log['duration_minutes'], 1) ?></strong> min
                                <?php else: ?>
                                    -
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