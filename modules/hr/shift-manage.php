<?php

/**
 * Shift Management
 * Create and manage work shifts for staff attendance
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

        if ($action === 'create' || $action === 'update') {
            $segments = [];
            $names = $_POST['seg_name'] ?? [];
            $starts = $_POST['seg_start'] ?? [];
            $ends = $_POST['seg_end'] ?? [];
            $earlies = $_POST['seg_early'] ?? [];
            $lates = $_POST['seg_late'] ?? [];
            $maxes = $_POST['seg_max'] ?? [];

            for ($i = 0; $i < count($starts); $i++) {
                if (!empty($starts[$i]) && !empty($ends[$i])) {
                    $segments[] = [
                        'name' => clean_input($names[$i] ?? 'Segment ' . ($i + 1)),
                        'start' => $starts[$i],
                        'end' => $ends[$i],
                        'early' => $earlies[$i] ?? null,
                        'late' => $lates[$i] ?? null,
                        'max' => $maxes[$i] ?? null
                    ];
                }
            }
            usort($segments, function ($a, $b) {
                return strcmp($a['start'], $b['start']);
            });

            $start_time = !empty($segments) ? $segments[0]['start'] : ($_POST['start_time'] ?? '00:00');
            $end_time = !empty($segments) ? end($segments)['end'] : ($_POST['end_time'] ?? '23:59');

            $data = [
                'name' => clean_input($_POST['name']),
                'start_time' => $start_time,
                'end_time' => $end_time,
                'late_after_minutes' => (int)$_POST['late_after_minutes'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'shift_segments' => json_encode($segments)
            ];
            if (empty($data['name'])) {
                $error_message = 'Shift name is required';
            } else {
                if ($action === 'create') {
                    db_insert('shifts', $data);
                    redirect_with_message($_SERVER['PHP_SELF'], 'Shift created successfully', 'success');
                } else {
                    db_update('shifts', $data, ['id' => (int)$_POST['shift_id']]);
                    redirect_with_message($_SERVER['PHP_SELF'], 'Shift updated successfully', 'success');
                }
            }
        } elseif ($action === 'delete') {
            db_query("DELETE FROM shifts WHERE id = ?", [(int)$_POST['shift_id']]);
            redirect_with_message($_SERVER['PHP_SELF'], 'Shift deleted successfully', 'success');
        } elseif ($action === 'assign_shift') {
            $staff_id = (int)$_POST['staff_id'];
            $shift_id = (int)$_POST['shift_id'];
            db_update('staff', ['shift_id' => $shift_id], ['id' => $staff_id]);
            redirect_with_message($_SERVER['PHP_SELF'], 'Shift assigned to staff successfully', 'success');
        }
    }
}

$shifts = db_query("SELECT * FROM shifts ORDER BY start_time");
$staff = db_query("SELECT st.*, sr.role_name, sd.name as department_name, s.name as shift_name 
    FROM staff st 
    LEFT JOIN staff_roles sr ON st.role_id = sr.id
    LEFT JOIN staff_departments sd ON st.department_id = sd.id
    LEFT JOIN shifts s ON st.shift_id = s.id 
    WHERE st.status = 'active' ORDER BY st.name ASC");

$page_title = 'Shift Management';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .shift-card {
        border-radius: 12px;
        border: 1px solid var(--border-color);
        padding: 20px;
        margin-bottom: 15px;
        transition: transform 0.2s;
    }

    .shift-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    }

    .shift-time {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary-color);
    }

    .shift-badge {
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 20px;
    }
</style>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-business-time text-primary"></i> Shift Management</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shiftModal" onclick="openCreate()">
            <i class="fas fa-plus"></i> New Shift
        </button>
    </div>

    <div class="row">
        <!-- Shifts Grid -->
        <div class="col-lg-7">
            <h6 class="text-muted mb-3">Work Shifts</h6>
            <div class="row">
                <?php foreach ($shifts as $s):
                    $colors = ['#4e73df', '#1cc88a', '#e74a3b', '#f6c23e', '#36b9cc', '#858796'];
                    $color = $colors[$s['id'] % count($colors)];
                ?>
                    <div class="col-md-6">
                        <div class="shift-card" style="border-left: 4px solid <?= $color ?>;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($s['name']) ?></h5>
                                    <span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?> shift-badge">
                                        <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" onclick="openEdit(<?= htmlspecialchars(json_encode($s)) ?>)"><i class="fas fa-edit"></i> Edit</a></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteShift(<?= $s['id'] ?>)"><i class="fas fa-trash"></i> Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="mt-3">
                                <?php
                                $segs = json_decode($s['shift_segments'], true);
                                if (!empty($segs)):
                                    foreach ($segs as $idx => $seg): ?>
                                        <div class="mb-2 p-2 border rounded bg-light">
                                            <div class="fw-bold small mb-1"><?= htmlspecialchars($seg['name'] ?? 'Segment ' . ($idx + 1)) ?></div>
                                            <div class="shift-time" style="font-size:16px;">
                                                <?= date('h:i A', strtotime($seg['start'])) ?> — <?= date('h:i A', strtotime($seg['end'])) ?>
                                            </div>
                                            <div class="mt-1 small d-flex flex-wrap gap-2">
                                                <?php if (!empty($seg['early'])): ?>
                                                    <span class="text-info"><i class="fas fa-sign-in-alt"></i> Early: <?= date('h:i A', strtotime($seg['early'])) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($seg['late'])): ?>
                                                    <span class="text-warning"><i class="fas fa-hourglass-half"></i> Late: <?= date('h:i A', strtotime($seg['late'])) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($seg['max'])): ?>
                                                    <span class="text-danger"><i class="fas fa-user-slash"></i> Max: <?= date('h:i A', strtotime($seg['max'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                else: ?>
                                    <div class="shift-time">
                                        <?= date('h:i A', strtotime($s['start_time'])) ?> — <?= date('h:i A', strtotime($s['end_time'])) ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted"><i class="fas fa-clock"></i> Grace: <?= $s['late_after_minutes'] ?> min</small>
                            </div>
                            <?php
                            $staff_count = db_query_one("SELECT COUNT(*) as cnt FROM staff WHERE shift_id = ? AND status='active'", [$s['id']]);
                            ?>
                            <div class="mt-2">
                                <small class="text-muted"><i class="fas fa-users"></i> <?= $staff_count['cnt'] ?? 0 ?> staff assigned</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Staff Shift Assignment -->
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-clock"></i> Staff Shift Assignment</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff</th>
                                    <th>Current Shift</th>
                                    <th width="140">Assign</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff as $st): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($st['name']) ?></strong><br>
                                            <small class="text-muted text-uppercase"><?= htmlspecialchars($st['employee_id'] ?? '') ?></small> <small class="text-primary"><?= htmlspecialchars($st['designation'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($st['shift_name']): ?>
                                                <span class="badge bg-primary"><?= htmlspecialchars($st['shift_name']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-flex gap-1">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="assign_shift">
                                                <input type="hidden" name="staff_id" value="<?= $st['id'] ?>">
                                                <select name="shift_id" class="form-select form-select-sm" style="width:90px;">
                                                    <?php foreach ($shifts as $sh): ?>
                                                        <option value="<?= $sh['id'] ?>" <?= $st['shift_id'] == $sh['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sh['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-check"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shift Modal -->
<div class="modal fade" id="shiftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="shift_id" id="shiftId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus"></i> New Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Shift Name</label>
                        <input type="text" name="name" id="sName" class="form-control" placeholder="e.g. Morning" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Shift Segments (Time Blocks)</label>
                        <div id="segmentContainer"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addSegment()"><i class="fas fa-plus"></i> Add Time Block</button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Late Grace Period (minutes)</label>
                        <input type="number" name="late_after_minutes" id="sGrace" class="form-control" value="15" min="0" max="120">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="sActive" checked>
                        <label class="form-check-label" for="sActive">Active</label>
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

<form method="POST" id="deleteForm" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="shift_id" id="deleteId">
</form>

<script>
    function renderSegments(segs) {
        const container = document.getElementById('segmentContainer');
        container.innerHTML = '';
        if (!segs || segs.length === 0) {
            addSegment('Morning', '09:00', '13:00', '08:30', '09:05', '10:00');
            return;
        }
        segs.forEach((s, i) => addSegment(s.name || ('Segment ' + (i + 1)), s.start, s.end, s.early, s.late, s.max));
    }

    function addSegment(name = '', start = '09:00', end = '17:00', early = '', late = '', max = '') {
        const div = document.createElement('div');
        div.className = 'p-3 border rounded mb-3 bg-light segment-row position-relative';
        div.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        <div class="row g-2">
            <div class="col-12 mb-2">
                <input type="text" name="seg_name[]" class="form-control form-control-sm" value="${name}" placeholder="Segment Name (e.g. Morning)" required>
            </div>
            <div class="col-md-6 mb-2">
                <label class="small fw-bold">Start Time</label>
                <input type="time" name="seg_start[]" class="form-control form-control-sm" value="${start}" required>
            </div>
            <div class="col-md-6 mb-2">
                <label class="small fw-bold">End Time</label>
                <input type="time" name="seg_end[]" class="form-control form-control-sm" value="${end}" required>
            </div>
            <div class="col-md-4">
                <label class="small text-info fw-bold">Early</label>
                <input type="time" name="seg_early[]" class="form-control form-control-sm" value="${early || ''}">
            </div>
            <div class="col-md-4">
                <label class="small text-warning fw-bold">Late</label>
                <input type="time" name="seg_late[]" class="form-control form-control-sm" value="${late || ''}">
            </div>
            <div class="col-md-4">
                <label class="small text-danger fw-bold">Max</label>
                <input type="time" name="seg_max[]" class="form-control form-control-sm" value="${max || ''}">
            </div>
        </div>
    `;
        document.getElementById('segmentContainer').appendChild(div);
    }

    function openCreate() {
        document.getElementById('formAction').value = 'create';
        document.getElementById('shiftId').value = '';
        document.getElementById('sName').value = '';
        document.getElementById('sGrace').value = '15';
        document.getElementById('sActive').checked = true;
        renderSegments([]);
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> New Shift';
    }

    function openEdit(s) {
        document.getElementById('formAction').value = 'update';
        document.getElementById('shiftId').value = s.id;
        document.getElementById('sName').value = s.name;
        document.getElementById('sGrace').value = s.late_after_minutes;
        document.getElementById('sActive').checked = s.is_active == 1;
        let segs = [];
        try {
            segs = JSON.parse(s.shift_segments);
        } catch (e) {}
        if (!segs || segs.length === 0) segs = [{
            name: 'Default',
            start: s.start_time,
            end: s.end_time,
            early: '',
            late: '',
            max: ''
        }];
        renderSegments(segs);
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Shift';
        new bootstrap.Modal(document.getElementById('shiftModal')).show();
    }

    function deleteShift(id) {
        if (confirm('Delete this shift?')) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>