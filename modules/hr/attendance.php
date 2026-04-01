<?php

/**
 * Attendance Management Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle attendance submission
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $current_user_role = db_select_one('users', ['id' => $_SESSION['user_id']]);
    if ($current_user_role) {
        $role_info = db_select_one('roles', ['id' => $current_user_role['role_id']]);
        if ($role_info && in_array($role_info['name'], ['super_admin', 'admin', 'Super Admin', 'Admin'])) {
            $is_admin = true;
        }
    }
}

if (is_post() && $is_admin) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $date = clean_input($_POST['date']);
        $attendance_data = $_POST['att'] ?? []; // Format: [staff_id][segment_name][field]

        foreach ($attendance_data as $staff_id => $segments) {
            foreach ($segments as $seg_name => $fields) {
                // Check if attendance already exists for this date and segment
                $existing = db_select_one('attendance', [
                    'staff_id' => $staff_id,
                    'date' => $date,
                    'segment_name' => $seg_name
                ]);

                $format_time = function ($time_str, $date) {
                    if (empty($time_str)) return null;
                    if (strpos($time_str, ' ') !== false) return $time_str;
                    return $date . ' ' . $time_str . ':00';
                };

                $data = [
                    'staff_id' => (int)$staff_id,
                    'date' => $date,
                    'segment_name' => $seg_name,
                    'status' => $fields['status'] ?? 'absent',
                    'time_in' => $format_time($fields['time_in'] ?? '', $date),
                    'time_out' => $format_time($fields['time_out'] ?? '', $date),
                    'notes' => clean_input($_POST['notes'][$staff_id] ?? '')
                ];

                // Get shift_id for this staff
                $staff_shift = db_query_one("SELECT shift_id FROM staff WHERE id = ?", [$staff_id]);
                if ($staff_shift) {
                    $data['shift_id'] = $staff_shift['shift_id'];
                }

                if ($existing) {
                    db_update('attendance', $data, ['id' => $existing['id']]);
                } else {
                    // Only insert if there's some actual data (not just an empty absent row)
                    if (!empty($data['time_in']) || !empty($data['time_out']) || $data['status'] !== 'absent') {
                        db_insert('attendance', $data);
                    }
                }
            }
        }

        log_activity(get_current_user_id(), 'attendance_record', "Recorded attendance for $date");
        redirect_with_message($_SERVER['PHP_SELF'] . "?date=$date", 'Attendance recorded successfully', 'success');
    }
}

// Get filters
$selected_date = get_param('date', date('Y-m-d'));
$dept_id = get_param('dept_id', '');

// Get staff_departments for filter
$departments = db_select('staff_departments', ['status' => 'active'], '*', 'name ASC');

// Get all active staff with department filter
$staff_where = "status = 'active'";
$staff_params = [];
if (!empty($dept_id)) {
    $staff_where .= " AND department_id = ?";
    $staff_params[] = $dept_id;
}
$staff_list = db_query("SELECT * FROM staff WHERE $staff_where ORDER BY name ASC", $staff_params);

// Get shifts
$shifts = db_query("SELECT * FROM shifts WHERE is_active = 1 ORDER BY start_time");

// Get attendance for selected date
$sql = "SELECT a.*, s.name as shift_name, s.start_time as shift_start, s.late_after_minutes 
        FROM attendance a 
        LEFT JOIN shifts s ON a.shift_id = s.id 
        WHERE a.date = ?";
$attendance_records = db_query($sql, [$selected_date]);

// Get approved leaves for the selected date
$leaves_query = db_query("SELECT staff_id FROM leaves WHERE status = 'approved' AND ? BETWEEN start_date AND end_date", [$selected_date]);
$staff_on_leave = [];
foreach ($leaves_query as $l) {
    $staff_on_leave[] = $l['staff_id'];
}

// Calculate segments config for each staff member
foreach ($staff_list as $idx => $staff) {
    // Get the staff's assigned shift and its segments
    $staff_shift = null;
    if ($staff['shift_id']) {
        $staff_shift = db_query_one("SELECT * FROM shifts WHERE id = ?", [$staff['shift_id']]);
    }

    $segs = [];
    if ($staff_shift) {
        $segs = json_decode($staff_shift['shift_segments'], true) ?: [];
    }

    // If no segments, create a default one from main shift times
    if (empty($segs) && $staff_shift) {
        $segs = [[
            'name' => 'Default',
            'start' => $staff_shift['start_time'],
            'end' => $staff_shift['end_time']
        ]];
    }

    $staff_list[$idx]['segments_config'] = $segs;
}

// Group attendance records by staff and segment name
// ALSO handle fallback for NULL segment names
$attendance_grouped = [];
foreach ($attendance_records as $record) {
    $s_id = $record['staff_id'];
    $seg_name = $record['segment_name'];

    // If segment_name is NULL, try to find the staff's segments
    if (empty($seg_name)) {
        // Find staff's segments config
        $target_staff = null;
        foreach ($staff_list as $sl) if ($sl['id'] == $s_id) {
            $target_staff = $sl;
            break;
        }

        if ($target_staff && !empty($target_staff['segments_config'])) {
            // Fallback to the first segment name
            $seg_name = $target_staff['segments_config'][0]['name'] ?? 'Default';
        } else {
            $seg_name = 'Default';
        }
    }

    $attendance_grouped[$s_id][$seg_name] = $record;
}

// Get business settings for header
$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$invoice_settings) {
    $invoice_settings = [
        'company_name' => defined('BUSINESS_NAME') ? BUSINESS_NAME : '',
        'company_address' => defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : '',
        'company_phone' => defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '',
        'company_email' => defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : '',
        'company_website' => '',
        'company_logo' => 'assets/images/logo.png',
        'company_slogan' => ''
    ];
}


$page_title = 'Attendance Management';
$additional_css = '
<style>
    .print-only { display: none; }
    @media print {
        .no-print { display: none !important; }
        .print-only { display: block !important; }
        body { padding: 0; background: white; }
        .container-fluid { width: 100%; padding: 0; }
        .card { border: none !important; box-shadow: none !important; }
    }
    .shift-col { min-width: 150px; font-size: 0.85rem; }
    .status-badge { width: 85px; text-align: center; }
</style>
';
include __DIR__ . '/../../templates/header.php';
?>

<div class="print-only">
    <div style="text-align: center; margin-bottom: 20px;">
        <h2 style="margin: 0;"><?= BUSINESS_NAME ?></h2>
        <p style="margin: 5px 0;">Daily Attendance Report - <?= format_date($selected_date) ?></p>
        <hr>
    </div>
</div>

<div class="card shadow mb-4 no-print">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Attendance Filter</h6>
        <button type="button" onclick="printReport()" class="btn btn-success btn-sm">
            <i class="fas fa-print"></i> Print Professional Report
        </button>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Select Date</label>
                <input type="date" name="date" class="form-control" value="<?= $selected_date ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <label class="form-label">Department</label>
                <select name="dept_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $dept_id == $dept['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Mark Attendance - <?= format_date($selected_date) ?></h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="date" value="<?= $selected_date ?>">

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Staff Detail</th>
                            <th class="text-center">Shift Sessions</th>
                            <th class="text-center" style="width:120px;">Daily Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

                        foreach ($staff_list as $staff):
                            $staff_atts = $attendance_grouped[$staff['id']] ?? [];
                            $segs = $staff['segments_config'];

                            $total_segments = count($segs);
                            $present_segments = 0;
                            $late_segments = 0;
                            $absent_segments = 0;

                            foreach ($segs as $s) {
                                $att = $staff_atts[$s['name'] ?? 'Default'] ?? null;
                                if ($att) {
                                    if ($att['status'] === 'present') $present_segments++;
                                    elseif ($att['status'] === 'late') $late_segments++;
                                    else $absent_segments++;
                                } else {
                                    $absent_segments++;
                                }
                            }

                            // Calculate Daily Status
                            $display_status = 'absent';
                            $on_leave = in_array($staff['id'], $staff_on_leave);
                            foreach ($staff_atts as $sa) if ($sa['status'] === 'leave') {
                                $on_leave = true;
                                break;
                            }

                            if ($on_leave) {
                                $display_status = 'leave';
                            } elseif ($present_segments + $late_segments === $total_segments && $total_segments > 0) {
                                $display_status = ($late_segments > 0) ? 'late' : 'present';
                            } elseif ($present_segments + $late_segments > 0) {
                                $display_status = 'half_day';
                            }
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($staff['name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($staff['designation'] ?? '-') ?></small>
                                </td>
                                <td class="p-0">
                                    <div class="d-flex flex-wrap">
                                        <?php if (empty($segs)): ?>
                                            <div class="p-2 w-100 text-center text-muted small">No session assigned</div>
                                        <?php else: ?>
                                            <?php foreach ($segs as $s):
                                                $s_name = $s['name'] ?? 'Default';
                                                $att = $staff_atts[$s_name] ?? null;

                                                // Get values for inputs
                                                $val_in = $att['time_in'] ? date('H:i', strtotime($att['time_in'])) : '';
                                                $val_out = $att['time_out'] ? date('H:i', strtotime($att['time_out'])) : '';
                                                $val_status = $att['status'] ?? ($on_leave ? 'leave' : 'absent');

                                                $badge_class = 'bg-light text-dark';
                                                if ($val_status === 'present') $badge_class = 'bg-success text-white';
                                                elseif ($val_status === 'late') $badge_class = 'bg-warning text-dark';
                                                elseif ($val_status === 'absent') $badge_class = 'bg-danger text-white';
                                                elseif ($val_status === 'leave') $badge_class = 'bg-primary text-white';
                                            ?>
                                                <div class="p-2 border-end border-bottom flex-fill" style="min-width: 220px;">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="small fw-bold text-primary"><?= htmlspecialchars($s_name) ?></span>
                                                        <span class="badge <?= $badge_class ?> rounded-pill" style="font-size: 0.65rem;">
                                                            <?= ucfirst($val_status) ?>
                                                        </span>
                                                    </div>

                                                    <div class="row g-1 mb-1 no-print">
                                                        <div class="col-6">
                                                            <input type="time" name="att[<?= $staff['id'] ?>][<?= $s_name ?>][time_in]"
                                                                value="<?= $val_in ?>" class="form-control form-control-sm" title="Time In">
                                                        </div>
                                                        <div class="col-6">
                                                            <input type="time" name="att[<?= $staff['id'] ?>][<?= $s_name ?>][time_out]"
                                                                value="<?= $val_out ?>" class="form-control form-control-sm" title="Time Out">
                                                        </div>
                                                    </div>

                                                    <select name="att[<?= $staff['id'] ?>][<?= $s_name ?>][status]" class="form-select form-select-sm no-print">
                                                        <option value="absent" <?= $val_status == 'absent' ? 'selected' : '' ?>>Absent</option>
                                                        <option value="present" <?= $val_status == 'present' ? 'selected' : '' ?>>Present</option>
                                                        <option value="late" <?= $val_status == 'late' ? 'selected' : '' ?>>Late</option>
                                                        <option value="leave" <?= $val_status == 'leave' ? 'selected' : '' ?>>Leave</option>
                                                    </select>

                                                    <div class="print-only small mt-1">
                                                        In: <?= $val_in ?: '-' ?> | Out: <?= $val_out ?: '-' ?> | <?= ucfirst($val_status) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $final_badge = 'bg-secondary';
                                    if ($display_status === 'present') $final_badge = 'bg-success';
                                    elseif ($display_status === 'late') $final_badge = 'bg-warning text-dark';
                                    elseif ($display_status === 'half_day') $final_badge = 'bg-info';
                                    elseif ($display_status === 'leave') $final_badge = 'bg-primary';
                                    elseif ($display_status === 'absent') $final_badge = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $final_badge ?> status-badge py-2"><?= ucfirst(str_replace('_', ' ', $display_status)) ?></span>
                                </td>
                                <td>
                                    <input type="text" name="notes[<?= $staff['id'] ?>]" class="form-control form-control-sm no-print"
                                        value="<?= htmlspecialchars(reset($staff_atts)['notes'] ?? '') ?>" placeholder="Notes...">
                                    <span class="print-only small"><?= htmlspecialchars(reset($staff_atts)['notes'] ?? '') ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3 no-print">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Manual Notes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Quick Statistics -->
<div class="row no-print">
    <?php
    $stats = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'half_day' => 0];
    foreach ($staff_list as $staff) {
        $staff_atts = $attendance_grouped[$staff['id']] ?? [];
        $segs = $staff['segments_config'];

        $total_segments = count($segs);
        $present_segments = 0;
        $late_segments = 0;

        foreach ($segs as $s) {
            $att = $staff_atts[$s['name'] ?? 'Default'] ?? null;
            if ($att) {
                if ($att['status'] === 'present') $present_segments++;
                elseif ($att['status'] === 'late') $late_segments++;
            }
        }

        $ds = 'absent';
        $on_leave = in_array($staff['id'], $staff_on_leave);
        foreach ($staff_atts as $sa) if ($sa['status'] === 'leave') {
            $on_leave = true;
            break;
        }

        if ($on_leave) {
            $ds = 'leave';
        } elseif ($present_segments + $late_segments === $total_segments && $total_segments > 0) {
            $ds = ($late_segments > 0) ? 'late' : 'present';
        } elseif ($present_segments + $late_segments > 0) {
            $ds = 'half_day';
        }
        $stats[$ds]++;
    }
    ?>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Present</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['present'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Late</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['late'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Half Day</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['half_day'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Absent</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['absent'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Leave</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['leave'] ?></div>
            </div>
        </div>
    </div>
</div>

<script>
    function printReport() {
        const date = '<?= $selected_date ?>';
        const dept = '<?= $dept_id ?>';
        window.open(`attendance-report-print.php?date=${date}&dept_id=${dept}`, '_blank');
    }
</script>
<?php include __DIR__ . '/../../templates/footer.php'; ?>