<?php

/**
 * Professional Attendance Report Print Template
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// 1. Get Filters
$selected_date = get_param('date', date('Y-m-d'));
$dept_id = get_param('dept_id', '');

// 2. Get Data
$all_shifts = db_query("SELECT * FROM shifts WHERE is_active = 1 ORDER BY start_time");

$staff_sql = "SELECT s.*, d.name as dept_name FROM staff s LEFT JOIN staff_departments d ON s.department_id = d.id WHERE s.status = 'active'";
$staff_params = [];
if ($dept_id) {
    $staff_sql .= " AND s.department_id = ?";
    $staff_params[] = $dept_id;
}
$staff_list = db_query($staff_sql, $staff_params);

$att_sql = "SELECT a.* FROM attendance a WHERE a.date = ?";
$attendance_records = db_query($att_sql, [$selected_date]);

// Get approved leaves for the selected date
$leaves_query = db_query("SELECT staff_id FROM leaves WHERE status = 'approved' AND ? BETWEEN start_date AND end_date", [$selected_date]);
$staff_on_leave = [];
foreach ($leaves_query as $l) {
    $staff_on_leave[] = $l['staff_id'];
}

// 2a. Augment staff list with their assigned shift segments FIRST
foreach ($staff_list as $idx => $staff) {
    $staff_shift = null;
    if ($staff['shift_id']) {
        $staff_shift = db_query_one("SELECT * FROM shifts WHERE id = ?", [$staff['shift_id']]);
    }

    $segs = [];
    if ($staff_shift) {
        $segs = json_decode($staff_shift['shift_segments'], true) ?: [];
        if (empty($segs)) {
            $segs = [[
                'name' => 'Default',
                'start' => $staff_shift['start_time'],
                'end' => $staff_shift['end_time']
            ]];
        }
    }
    $staff_list[$idx]['segments_config'] = $segs;
}

// 2b. Group attendance records by staff and segment name (depends on segments_config for fallback)
$attendance_grouped = [];
foreach ($attendance_records as $record) {
    $s_id = $record['staff_id'];
    $seg_name = $record['segment_name'];

    if (empty($seg_name)) {
        // Find staff's segments config from augmented list
        $target_staff = null;
        foreach ($staff_list as $sl) if ($sl['id'] == $s_id) {
            $target_staff = $sl;
            break;
        }

        if ($target_staff && !empty($target_staff['segments_config'])) {
            $seg_name = $target_staff['segments_config'][0]['name'] ?? 'Default';
        } else {
            $seg_name = 'Default';
        }
    }
    $attendance_grouped[$s_id][$seg_name] = $record;
}

$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Attendance Report - <?= $selected_date ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            color: #333;
        }

        /* 3-Column Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .header-table td {
            vertical-align: middle;
            border: none !important;
        }

        .logo-cell {
            width: 10%;
            text-align: left;
        }

        .logo-cell img {
            max-width: 120px;
            height: auto;
            display: block;
        }

        .title-cell {
            width: 65%;
            text-align: center;
        }

        .company-name {
            font-size: 24px;
            font-weight: 900;
            color: #2C3E50;
            margin: 0;
            text-transform: uppercase;
            border-bottom: 2px solid #333;
            display: inline-block;
            line-height: 1.1;
        }

        .company-slogan {
            font-size: 12px;
            color: #E67E22;
            font-weight: bold;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .info-cell {
            width: 25%;
            text-align: right;
            line-height: 1.3;
            font-size: 10px;
        }

        .info-cell p {
            margin: 0;
        }

        .report-main-title {
            text-align: center;
            font-size: 18px;
            color: #333;
            margin: 15px 0;
            font-weight: bold;
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            padding: 5px 0;
        }

        /* Fixed Footer at bottom of EVERY page */
        .print-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #333;
            padding-top: 5px;
            display: block;
            width: 100%;
            font-size: 10px;
            background: #fff;
            z-index: 9999;
        }

        .footer-left {
            float: left;
            width: 50%;
            font-weight: bold;
            text-align: left;
        }

        .footer-right {
            float: right;
            width: 50%;
            text-align: right;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 6px;
            text-align: left;
        }

        th {
            background: #f2f2f2;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #fff;
        }

        .bg-present {
            background: #27ae60;
        }

        .bg-late {
            background: #f1c40f;
            color: #333;
        }

        .bg-half_day {
            background: #3498db;
        }

        .bg-absent {
            background: #e74c3c;
        }

        .bg-leave {
            background: #9b59b6;
        }

        .bg-pending {
            background: #95a5a6;
        }

        .text-center {
            text-align: center;
        }

        .text-bold {
            font-weight: bold;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 0.25cm;
            }

            .no-print {
                display: none;
            }
            
            body { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>

<body>

    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #27ae60; color: #fff; border: none; cursor: pointer; border-radius: 5px;">Print Report</button>
    </div>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <?php
                $logo_url = !empty($invoice_settings['company_logo']) ? $invoice_settings['company_logo'] : 'assets/images/logo.png';
                if (!preg_match('~^(?:f|ht)tps?://~i', $logo_url)) {
                    $logo_url = BASE_URL . '/' . ltrim($logo_url, '/');
                }
                ?>
                <img src="<?= $logo_url ?>" alt="Logo">
            </td>
            <td class="title-cell">
                <h1 class="company-name"><?= htmlspecialchars($invoice_settings['company_name'] ?? '') ?></h1><br>
                <div class="company-slogan"><?= htmlspecialchars($invoice_settings['company_slogan'] ?? '') ?></div>
            </td>
            <td class="info-cell">
                <p>Address: <?= htmlspecialchars($invoice_settings['company_address'] ?? '') ?></p>
                <p>Tel: <?= htmlspecialchars($invoice_settings['company_phone'] ?? '') ?></p>
                <p>Email: <?= htmlspecialchars($invoice_settings['company_email'] ?? '') ?></p>
                <p>Website: <?= htmlspecialchars($invoice_settings['company_website'] ?? '') ?></p>
            </td>
        </tr>
    </table>

    <div class="report-main-title">Daily Attendance Report - <?= format_date($selected_date) ?></div>

    <div style="font-size: 11px; margin-bottom: 10px; font-weight: bold;">
        Department: <?= $dept_id ? db_query_one("SELECT name FROM staff_departments WHERE id = ?", [$dept_id])['name'] : 'All' ?>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 150px;">Employee</th>
                <th class="text-center">Shift Sessions & Timings</th>
                <th class="text-center" style="width: 100px;">Final Status</th>
                <th class="text-center" style="width: 100px;">Total Hours</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($staff_list as $staff):
                $staff_atts = $attendance_grouped[$staff['id']] ?? [];
                $present_segments = 0;
                $late_segments = 0;
                $absent_segments = 0;
                $total_work_seconds = 0;

                $on_leave = in_array($staff['id'], $staff_on_leave);
                foreach ($staff_atts as $sa) if ($sa['status'] === 'leave') {
                    $on_leave = true; break;
                }
            ?>
                <tr>
                    <td>
                        <div class="text-bold"><?= htmlspecialchars($staff['name']) ?></div>
                        <div style="font-size: 10px; color: #666;"><?= htmlspecialchars($staff['designation'] ?? '-') ?></div>
                    </td>
                    <td class="p-0">
                        <div style="display: flex; flex-wrap: wrap;">
                            <?php
                            $segs = $staff['segments_config'];
                            if (empty($segs)): ?>
                                <div style="padding: 10px; flex: 1; text-align: center; color: #999;">No Shift Assigned</div>
                            <?php else: ?>
                                <?php foreach ($segs as $s):
                                    $s_name = $s['name'] ?? 'Default';
                                    $att = $staff_atts[$s_name] ?? null;
                                    $time_in = $att['time_in'] ? date('h:i A', strtotime($att['time_in'])) : '-';
                                    $time_out = $att['time_out'] ? date('h:i A', strtotime($att['time_out'])) : '-';

                                    if ($att) {
                                        if ($att['status'] === 'present') $present_segments++;
                                        elseif ($att['status'] === 'late') $late_segments++;
                                        else $absent_segments++;

                                        if ($att['time_in'] && $att['time_out']) {
                                            $total_work_seconds += (strtotime($att['time_out']) - strtotime($att['time_in']));
                                        }
                                    } else {
                                        $absent_segments++;
                                    }
                                ?>
                                    <div style="padding: 5px; border-right: 1px solid #999; flex: 1; min-width: 120px; text-align: center;">
                                        <div style="font-weight: bold; font-size: 10px; color: #555;"><?= htmlspecialchars($s_name) ?></div>
                                        <div><?= $time_in ?> - <?= $time_out ?></div>
                                        <?php if ($att): ?>
                                            <span class="status-badge bg-<?= $att['status'] ?>" style="font-size: 9px;"><?= $att['status'] ?></span>
                                        <?php else: ?>
                                            <?php if ($on_leave): ?>
                                            <span class="status-badge bg-leave" style="font-size: 9px;">Leave</span>
                                            <?php else: ?>
                                            <span class="status-badge bg-pending" style="font-size: 9px;">Pending</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php
                    $total_segments = count($segs);
                    $display_status = 'absent';
                    if ($on_leave) {
                        $display_status = 'leave';
                    } elseif ($present_segments + $late_segments === $total_segments && $total_segments > 0) {
                        $display_status = ($late_segments > 0) ? 'late' : 'present';
                    } elseif ($present_segments + $late_segments > 0) {
                        $display_status = 'half_day';
                    }

                    $hours = floor($total_work_seconds / 3600);
                    $mins = floor(($total_work_seconds % 3600) / 60);
                    ?>
                    <td class="text-center">
                        <span class="status-badge bg-<?= $display_status ?>"><?= ucfirst(str_replace('_', ' ', $display_status)) ?></span>
                    </td>
                    <td class="text-center text-bold">
                        <?= $hours ?>h <?= $mins ?>m
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Print Footer -->
    <div class="print-footer clearfix">
        <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
        <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
    </div>

</body>

</html>