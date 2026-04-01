<?php

/**
 * Staff List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get all staff with role and department information
$staff = db_query("SELECT s.*, sr.role_name, sd.name as department_name 
    FROM staff s
    LEFT JOIN staff_roles sr ON s.role_id = sr.id
    LEFT JOIN staff_departments sd ON s.department_id = sd.id
    ORDER BY s.name ASC");

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

$page_title = 'Staff Management';
$page_actions = '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <a href="staff-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Staff</a>';

include __DIR__ . '/../../templates/header.php';
?>

<style>
    /* Print Area Styles (Hidden on Screen) */
    #print-area {
        display: none;
        padding: 20px;
        background: #fff;
        color: #000;
    }

    @media print {
        @page {
            margin: 0.25cm;
            size: auto;
        }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            width: 100% !important;
        }

        /* Hide regular UI AND standard print header/footer */
        .no-print,
        .btn,
        .card,
        .navbar,
        .sidebar,
        #accordionSidebar,
        .topbar,
        footer,
        .footer,
        #footer,
        .summary-cards,
        #standard-print-wrapper,
        #standard-print-footer {
            display: none !important;
        }

        #print-area {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .print-container {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
        }

        /* 3-Column Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .header-table td {
            vertical-align: top;
            border: none !important;
            padding: 0;
        }

        .logo-cell {
            width: 10%;
            text-align: left;
        }

        .logo-cell img {
            max-width: 80px;
            height: auto;
            display: block;
        }

        .title-cell {
            width: 65%;
            text-align: center;
            padding: 0 10px;
        }

        .company-name {
            font-size: 22px;
            font-weight: 900;
            color: #000;
            margin: 0;
            text-transform: uppercase;
            border-bottom: 2px solid #000;
            display: inline-block;
            line-height: 1.2;
            padding-bottom: 2px;
        }

        .company-slogan {
            font-size: 11px;
            color: #000;
            font-weight: bold;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-cell {
            width: 25%;
            text-align: left;
            line-height: 1.4;
            font-size: 9px;
            border: 1px solid #000;
            padding: 5px 8px;
            box-sizing: border-box;
        }

        .info-cell p {
            margin: 0;
            margin-bottom: 2px;
        }

        .info-cell p:last-child {
            margin-bottom: 0;
        }

        .report-main-title {
            text-align: center;
            font-size: 16px;
            color: #000;
            margin: 12px 0;
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 6px 0;
        }

        /* Simple Bordered Table */
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .print-table th,
        .print-table td {
            border: 1px solid #333 !important;
            padding: 6px;
            text-align: left;
        }

        .print-table th {
            background: #f2f2f2 !important;
            font-weight: bold;
            text-transform: uppercase;
        }

        .text-end {
            text-align: right !important;
        }

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

        .footer-left {
            float: left;
            width: 50%;
            font-weight: bold;
            text-align: left;
            padding-left: 10px;
        }

        .footer-right {
            float: right;
            width: 50%;
            text-align: right;
            padding-right: 10px;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    }
</style>

<!-- Hidden Print Area -->
<div id="print-area">
    <div class="print-container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <?php
                    $logo_url = !empty($invoice_settings['company_logo']) ? $invoice_settings['company_logo'] : 'assets/images/logo.png';
                    // Ensure internal images use absolute path or base64 if needed for some PDF generators, but for browser print relative usually works or full URL.
                    // Let's use the logic from sales-report
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

        <div class="report-main-title">Staff List Report (<?= date('d M Y') ?>)</div>

        <!-- Print Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Designation</th>
                    <th>Phone</th>
                    <th>Joining Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($staff)): ?>
                    <tr>
                        <td colspan="8" class="text-center">No staff found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($staff as $member): ?>
                        <tr>
                            <td><?= htmlspecialchars($member['employee_id'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($member['name']) ?></td>
                            <td><?= htmlspecialchars($member['department_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($member['role_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($member['designation'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($member['phone'] ?? '-') ?></td>
                            <td><?= $member['joining_date'] ? date('d-M-Y', strtotime($member['joining_date'])) : '-' ?></td>
                            <td><?= ucfirst($member['status']) ?></td>
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

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Staff List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="staffTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Designation</th>
                        <th>Phone</th>
                        <th>Joining Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $member): ?>
                        <tr>
                            <td><?= htmlspecialchars($member['employee_id'] ?? '-') ?></td>
                            <td>
                                <?php if ($member['photo']): ?>
                                    <img src="../../<?= htmlspecialchars($member['photo']) ?>"
                                        class="rounded-circle mr-2"
                                        width="30" height="30"
                                        alt="Photo"
                                        style="object-fit: cover;">
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($member['name']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($member['department_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($member['role_name']): ?>
                                    <span class="badge badge-primary"><?= htmlspecialchars($member['role_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($member['designation'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($member['phone'] ?? '-') ?></td>
                            <td><?= $member['joining_date'] ? date('d M Y', strtotime($member['joining_date'])) : '-' ?>
                            </td>
                            <td>
                                <?php if ($member['status'] === 'active'): ?>
                                    <span class="badge badge-pill badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-pill badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="../reports/staff-performance.php?staff_id=<?= $member['id'] ?>" class="btn btn-sm btn-success" title="Performance">
                                        <i class="fas fa-chart-line"></i>
                                    </a>
                                    <a href="staff-view.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="staff-edit.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="staff-delete.php?id=<?= $member['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this staff member?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#staffTable').DataTable({
            order: [
                [1, 'asc']
            ], // Order by Name
            pageLength: 25
        });
    });
</script>