<?php
/**
 * Staff View Page
 * Display detailed information about a staff member
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get staff ID from URL
$staff_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get staff data with role information
$staff = db_query("SELECT s.*, sr.role_name, sr.description as role_description
    FROM staff s
    LEFT JOIN staff_roles sr ON s.role_id = sr.id
    WHERE s.id = ?", [$staff_id]);

if (empty($staff)) {
    header('Location: staff-list.php');
    exit;
}

$staff = $staff[0];

// Get attendance statistics
$attendance_stats = db_query("SELECT 
    COUNT(*) as total_days,
    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
    SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days,
    SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days
    FROM attendance 
    WHERE staff_id = ?", [$staff_id]);

$attendance = $attendance_stats[0] ?? [];

// Get recent salary payments
$recent_salaries = db_query("SELECT * FROM salaries 
    WHERE staff_id = ? 
    ORDER BY year DESC, month DESC 
    LIMIT 5", [$staff_id]);

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

// Get attendance filter settings
$attendance_start = get_param('attendance_start', date('Y-m-01'));
$attendance_end = get_param('attendance_end', date('Y-m-t'));

// Get detailed attendance for the filtered period
$attendance_records = db_query("SELECT * FROM attendance 
    WHERE staff_id = ? AND date BETWEEN ? AND ?
    ORDER BY date DESC", [$staff_id, $attendance_start, $attendance_end]);

$page_title = 'View Staff Details';
$page_title = 'View Staff Details';

$additional_css = '
<style>
/* Print Area Styles (Hidden on Screen) */
#print-area {
    display: none;
    padding: 20px;
    background: #fff;
    color: #000;
}

@media print {
    @page { margin: 0.25cm; size: auto; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; width: 100% !important; }
    
    /* Hide regular UI AND standard print header/footer */
    .no-print, .btn, .card, .navbar, .sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, .summary-cards, #standard-print-wrapper, #standard-print-footer {
        display: none !important;
    }
    
    #print-area { display: block !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    
    .print-container { 
        width: 100% !important; 
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        box-sizing: border-box !important; 
        font-family: "Segoe UI", Arial, sans-serif; 
        font-size: 11px; 
    }
    
    /* 3-Column Header */
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .header-table td { vertical-align: top; border: none !important; padding: 0; }
    .logo-cell { width: 10%; text-align: left; }
    .logo-cell img { max-width: 80px; height: auto; display: block; }
    
    .title-cell { width: 65%; text-align: center; padding: 0 10px; }
    .company-name { font-size: 22px; font-weight: 900; color: #000; margin: 0; text-transform: uppercase; border-bottom: 2px solid #000; display: inline-block; line-height: 1.2; padding-bottom: 2px; }
    .company-slogan { font-size: 11px; color: #000; font-weight: bold; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .info-cell { width: 25%; text-align: left; line-height: 1.4; font-size: 9px; border: 1px solid #000; padding: 5px 8px; box-sizing: border-box; }
    .info-cell p { margin: 0; margin-bottom: 2px; }
    .info-cell p:last-child { margin-bottom: 0; }
    
    .report-main-title { text-align: center; font-size: 16px; color: #000; margin: 12px 0; font-weight: bold; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 0; }

    /* Simple Bordered Table */
    .print-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .print-table th, .print-table td { border: 1px solid #333 !important; padding: 6px; text-align: left; }
    .print-table th { background: #f2f2f2 !important; font-weight: bold; text-transform: uppercase; }
    .text-end { text-align: right !important; }
    
    /* Fixed Footer at bottom of EVERY page */
    .print-footer { 
        position: fixed;
        bottom: 0.3cm;
        left: 0;
        right: 0;
        border-top: 1px solid #333; 
        padding-top: 10px; 
        display: block;
        width: 100%;
        font-size: 10px; 
        background: #fff;
        z-index: 9999;
    }
    .footer-left { float: left; width: 50%; font-weight: bold; text-align: left; padding-left: 10px; }
    .footer-right { float: right; width: 50%; text-align: right; padding-right: 10px; }
    .clearfix::after { content: ""; clear: both; display: table; }
}
</style>
';

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user"></i> Staff Details
        </h1>
        <div>
            <a href="staff-edit.php?id=<?= $staff_id ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="staff-list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Staff Profile Card -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-body text-center">
                    <?php if ($staff['photo']): ?>
                        <img src="../../<?= htmlspecialchars($staff['photo']) ?>" 
                             class="rounded-circle mb-3" 
                             width="150" height="150" 
                             alt="Staff Photo"
                             style="object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" 
                             style="width: 150px; height: 150px; font-size: 60px;">
                            <?= strtoupper(substr($staff['name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <h4 class="font-weight-bold"><?= htmlspecialchars($staff['name']) ?></h4>
                    
                    <?php if ($staff['role_name']): ?>
                        <p class="mb-1">
                            <span class="badge badge-info badge-lg"><?= htmlspecialchars($staff['role_name']) ?></span>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($staff['designation']): ?>
                        <p class="text-muted mb-3"><?= htmlspecialchars($staff['designation']) ?></p>
                    <?php endif; ?>
                    
                    <p class="mb-2">
                        <?php if ($staff['status'] === 'active'): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Inactive</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Stats</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">Total Attendance Records</small>
                        <h5 class="font-weight-bold"><?= $attendance['total_days'] ?? 0 ?> days</h5>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Present Days</small>
                        <h5 class="font-weight-bold text-success"><?= $attendance['present_days'] ?? 0 ?></h5>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Absent Days</small>
                        <h5 class="font-weight-bold text-danger"><?= $attendance['absent_days'] ?? 0 ?></h5>
                    </div>
                    <?php if (($attendance['total_days'] ?? 0) > 0): ?>
                        <hr>
                        <small class="text-muted">Attendance Rate</small>
                        <?php 
                            $rate = (($attendance['present_days'] + ($attendance['half_days'] * 0.5)) / $attendance['total_days']) * 100;
                        ?>
                        <div class="progress mt-2" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: <?= $rate ?>%">
                                <?= number_format($rate, 1) ?>%
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Staff Information -->
        <div class="col-lg-8 mb-4">
            <!-- Personal Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Personal Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-phone text-primary"></i> Phone:</strong>
                            <p class="mb-0"><?= htmlspecialchars($staff['phone'] ?? 'Not provided') ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-envelope text-primary"></i> Email:</strong>
                            <p class="mb-0"><?= htmlspecialchars($staff['email'] ?? 'Not provided') ?></p>
                        </div>
                        <div class="col-md-12 mb-3">
                            <strong><i class="fas fa-map-marker-alt text-primary"></i> Address:</strong>
                            <p class="mb-0"><?= htmlspecialchars($staff['address'] ?? 'Not provided') ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-calendar text-primary"></i> Joining Date:</strong>
                            <p class="mb-0"><?= $staff['joining_date'] ? date('d F Y', strtotime($staff['joining_date'])) : '-' ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><i class="fas fa-clock text-primary"></i> Time with Company:</strong>
                            <?php
                                $join = new DateTime($staff['joining_date']);
                                $now = new DateTime();
                                $diff = $join->diff($now);
                                $years = $diff->y;
                                $months = $diff->m;
                            ?>
                            <p class="mb-0">
                                <?= $years > 0 ? "$years year" . ($years > 1 ? 's' : '') : '' ?>
                                <?= $months > 0 ? " $months month" . ($months > 1 ? 's' : '') : '' ?>
                                <?= ($years == 0 && $months == 0) ? 'Less than a month' : '' ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Salary Payments -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Salary Payments</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_salaries)): ?>
                        <p class="text-center text-muted mb-0">No salary records found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Month/Year</th>
                                        <th>Basic Salary</th>
                                        <th>Allowances</th>
                                        <th>Deductions</th>
                                        <th>Net Salary</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_salaries as $salary): ?>
                                        <tr>
                                            <td><?= date('F Y', mktime(0, 0, 0, $salary['month'], 1, $salary['year'])) ?></td>
                                            <td><?= format_currency($salary['basic_salary']) ?></td>
                                            <td><?= format_currency($salary['allowances']) ?></td>
                                            <td><?= format_currency($salary['deductions']) ?></td>
                                            <td><strong><?= format_currency($salary['net_salary']) ?></strong></td>
                                            <td>
                                                <?php if ($salary['status'] == 'paid'): ?>
                                                    <span class="badge badge-success">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attendance Record Section -->
            <div class="card shadow mb-4" id="attendance-section">
                <div class="card-header py-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Attendance Report</h6>
                        <div class="no-print mt-2 mt-md-0">
                            <form method="GET" class="form-inline">
                                <input type="hidden" name="id" value="<?= $staff_id ?>">
                                <div class="input-group input-group-sm mr-2">
                                    <input type="date" name="attendance_start" class="form-control" value="<?= $attendance_start ?>">
                                    <span class="input-group-text">-</span>
                                    <input type="date" name="attendance_end" class="form-control" value="<?= $attendance_end ?>">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
                                </div>
                                <button type="button" onclick="window.print()" class="btn btn-success btn-sm">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="small mt-2 text-muted">
                        Period: <?= date('d M Y', strtotime($attendance_start)) ?> to <?= date('d M Y', strtotime($attendance_end)) ?>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Print Only Header -->
                    <div class="print-only-header">
                        <h3><?= htmlspecialchars($staff['name']) ?></h3>
                        <p class="mb-0"><?= htmlspecialchars($staff['designation']) ?></p>
                        <p class="small"><?= htmlspecialchars($staff['phone']) ?></p>
                    </div>

                    <?php if (empty($attendance_records)): ?>
                        <p class="text-center text-muted mb-0">No attendance records found for this period</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $att): ?>
                                        <tr>
                                            <td><?= date('d M Y (D)', strtotime($att['date'])) ?></td>
                                            <td>
                                                <?php
                                                    $badge_class = 'secondary';
                                                    if ($att['status'] == 'present') $badge_class = 'success';
                                                    elseif ($att['status'] == 'absent') $badge_class = 'danger';
                                                    elseif ($att['status'] == 'leave') $badge_class = 'warning';
                                                    elseif ($att['status'] == 'half_day') $badge_class = 'info';
                                                ?>
                                                <span class="badge badge-<?= $badge_class ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $att['status'])) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($att['notes'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Print Area -->
<div id="print-area">
    <div class="print-container">
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

        <div class="report-main-title">Staff Attendance Report (<?= format_date($attendance_start) ?> to <?= format_date($attendance_end) ?>)</div>

        <div style="margin-bottom: 20px;">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="width: 50%;">
                        <strong>Staff Name:</strong> <?= htmlspecialchars($staff['name']) ?><br>
                        <strong>Designation:</strong> <?= htmlspecialchars($staff['designation']) ?><br>
                        <strong>Phone:</strong> <?= htmlspecialchars($staff['phone']) ?>
                    </td>
                    <td style="width: 50%; text-align: right;">
                        <strong>Total Records:</strong> <?= count($attendance_records) ?><br>
                        <strong>Present:</strong> <?= $attendance['present_days'] ?? 0 ?><br>
                        <strong>Absent:</strong> <?= $attendance['absent_days'] ?? 0 ?>
                    </td>
                </tr>
            </table>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendance_records)): ?>
                    <tr><td colspan="3" class="text-center">No attendance records found</td></tr>
                <?php else: ?>
                    <?php foreach ($attendance_records as $att): ?>
                        <tr>
                            <td><?= date('d-M-Y (D)', strtotime($att['date'])) ?></td>
                            <td><?= ucfirst(str_replace('_', ' ', $att['status'])) ?></td>
                            <td><?= htmlspecialchars($att['notes'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
