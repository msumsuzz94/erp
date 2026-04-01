<?php

/**
 * CCTV Configuration Page
 * Manage RTSP cameras for attendance/away tracking
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('hr.cctv_config');

// Handle CRUD
if (is_post() && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $data = [
            'camera_name' => clean_input($_POST['camera_name']),
            'rtsp_url' => clean_input($_POST['rtsp_url']),
            'track_function' => clean_input($_POST['track_function']),
            'location' => clean_input($_POST['location'] ?? ''),
            'away_timeout_minutes' => (int)($_POST['away_timeout'] ?? 5),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($action === 'add') {
            db_insert('cctv_configurations', $data);
            redirect_with_message('cctv-config.php', 'Camera added successfully', 'success');
        } else {
            db_update('cctv_configurations', $data, ['id' => (int)$_POST['camera_id']]);
            redirect_with_message('cctv-config.php', 'Camera updated successfully', 'success');
        }
    }

    if ($action === 'delete') {
        db_query("DELETE FROM cctv_configurations WHERE id = ?", [(int)$_POST['camera_id']]);
        redirect_with_message('cctv-config.php', 'Camera deleted', 'success');
    }
}

$cameras = db_query("SELECT * FROM cctv_configurations ORDER BY created_at DESC");

// Get Python middleware status
$att_settings = [];
$sr = db_query("SELECT setting_key, setting_value FROM attendance_settings");
foreach ($sr ?: [] as $r) $att_settings[$r['setting_key']] = $r['setting_value'];
$python_url = $att_settings['cctv_python_url'] ?? 'http://localhost:5050';

$page_title = 'CCTV Configuration';
$page_actions = '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCameraModal"><i class="fas fa-plus"></i> Add Camera</button>';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-left-info">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Python Service</div>
                <div class="h5 mb-0 font-weight-bold" id="pyStatus">
                    <span class="badge bg-warning">Checking...</span>
                </div>
                <small class="text-muted"><?= htmlspecialchars($python_url) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-success">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Cameras</div>
                <div class="h5 mb-0 font-weight-bold"><?= count(array_filter($cameras ?: [], fn($c) => $c['is_active'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-primary">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Cameras</div>
                <div class="h5 mb-0 font-weight-bold"><?= count($cameras ?: []) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-video"></i> Configured Cameras</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Camera Name</th>
                        <th>RTSP URL</th>
                        <th>Function</th>
                        <th>Location</th>
                        <th>Away Timeout</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cameras ?: [] as $cam): ?>
                        <tr>
                            <td>
                                <?= $cam['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?>
                                <br><small class="text-muted"><?= $cam['last_status'] ?></small>
                            </td>
                            <td><i class="fas fa-camera"></i> <?= htmlspecialchars($cam['camera_name']) ?></td>
                            <td><code style="font-size:0.75rem;"><?= htmlspecialchars($cam['rtsp_url']) ?></code></td>
                            <td>
                                <?php if ($cam['track_function'] === 'entry_tracking'): ?>
                                    <span class="badge bg-primary">Entry Tracking</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Away Tracking</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($cam['location'] ?? '-') ?></td>
                            <td><?= $cam['away_timeout_minutes'] ?> min</td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick='editCamera(<?= json_encode($cam) ?>)'><i class="fas fa-edit"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this camera?');">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="camera_id" value="<?= $cam['id'] ?>">
                                    <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Camera Modal -->
<div class="modal fade" id="addCameraModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="camera_id" id="formCameraId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-video"></i> Add Camera</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Camera Name <span class="text-danger">*</span></label>
                        <input type="text" name="camera_name" id="fCameraName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">RTSP URL <span class="text-danger">*</span></label>
                        <input type="text" name="rtsp_url" id="fRtspUrl" class="form-control" required placeholder="rtsp://user:pass@ip:port/cam/realmonitor?channel=1&subtype=0">
                        <small class="text-muted">Dahua: rtsp://user:pass@ip:554/cam/realmonitor?channel=1&subtype=0</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Track Function <span class="text-danger">*</span></label>
                        <select name="track_function" id="fTrackFunc" class="form-select" required>
                            <option value="entry_tracking">🚪 Entry Tracking (Attendance)</option>
                            <option value="away_tracking">🔍 Away Tracking (Desk Monitoring)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" id="fLocation" class="form-control" placeholder="e.g., Main Gate, Floor 2">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Away Timeout (minutes)</label>
                        <input type="number" name="away_timeout" id="fTimeout" class="form-control" value="5" min="1">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="fActive" checked>
                        <label class="form-check-label" for="fActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editCamera(cam) {
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formCameraId').value = cam.id;
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Camera';
        document.getElementById('fCameraName').value = cam.camera_name;
        document.getElementById('fRtspUrl').value = cam.rtsp_url;
        document.getElementById('fTrackFunc').value = cam.track_function;
        document.getElementById('fLocation').value = cam.location || '';
        document.getElementById('fTimeout').value = cam.away_timeout_minutes;
        document.getElementById('fActive').checked = cam.is_active == 1;
        new bootstrap.Modal(document.getElementById('addCameraModal')).show();
    }

    // Check Python service status
    fetch('<?= $python_url ?>/status').then(r => r.json()).then(d => {
        document.getElementById('pyStatus').innerHTML = '<span class="badge bg-success">Online</span>';
    }).catch(() => {
        document.getElementById('pyStatus').innerHTML = '<span class="badge bg-danger">Offline</span>';
    });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>