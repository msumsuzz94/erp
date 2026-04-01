<?php

/**
 * Customers List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
$can_import_export = is_admin() || has_role(get_current_user_id(), 'Manager') || has_role(get_current_user_id(), 'Admin');

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $customer_id = (int)$_POST['delete_id'];
        $customer = db_select_one('customers', ['id' => $customer_id]);

        if (db_delete('customers', ['id' => $customer_id])) {
            log_activity(get_current_user_id(), 'delete_customer', "Deleted customer: {$customer['name']}");
            redirect_with_message($_SERVER['PHP_SELF'], 'Customer deleted successfully', 'success');
        } else {
            redirect_with_message($_SERVER['PHP_SELF'], 'Failed to delete customer', 'error');
        }
    }
}

// Get filter parameters
$search = get_param('search', '');
$status = get_param('status', '');

$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY created_at DESC";
$customers = db_query($sql, $params);

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

$page_title = 'Customers';

$page_actions = '';
if ($can_import_export) {
    $page_actions .= '
        <a href="demo_customers.csv" class="btn btn-info me-2"><i class="fas fa-download"></i> Demo CSV</a>
        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-file-import"></i> Import CSV</button>
        <a href="export-customers.php" class="btn btn-warning me-2"><i class="fas fa-file-export"></i> Export CSV</a>';
}
$page_actions .= '
    <a href="customer-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Customer</a>
    <button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print List</button>';

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
        }

        /* Hide regular UI */
        .no-print,
        .btn,
        .card,
        .navbar,
        .sidebar,
        #accordionSidebar,
        .topbar,
        footer,
        #standard-print-wrapper,
        #standard-print-footer,
        .d-sm-flex {
            display: none !important;
        }

        #print-area {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .print-container {
            width: 100%;
            padding: 0;
            margin: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
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

        <div class="report-main-title">Customer List</div>

        <table class="print-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?= htmlspecialchars($customer['name']) ?></td>
                        <td><?= htmlspecialchars($customer['phone'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($customer['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($customer['address'] ?? '-') ?></td>
                        <td class="text-end"><?= format_currency($customer['current_balance'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="card shadow mb-4 no-print">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Customers</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, Phone, Email" value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="customers-list.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Customers List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="customersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $customer): ?>

                            <tr>
                                <td><?= htmlspecialchars($customer['name']) ?></td>
                                <td><?= htmlspecialchars($customer['phone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($customer['email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($customer['address'] ?? '-') ?></td>
                                <td class="no-print">
                                    <a href="customer-view.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="customer-edit.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="customer-ledger.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-primary" title="Ledger">
                                        <i class="fas fa-book"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger delete-customer" data-id="<?= $customer['id'] ?>" data-name="<?= htmlspecialchars($customer['name']) ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($can_import_export): ?>
    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Customers from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="import-customers.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <div class="form-group mb-3">
                            <label for="csv_file" class="form-label">Select CSV File</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        <div class="alert alert-info small">
                            <strong>Instructions:</strong>
                            <ul class="mb-0">
                                <li>Ensure the file is in CSV format.</li>
                                <li>Columns: Name, Phone, Email, Address, Opening Balance.</li>
                                <li>Phone or Email is used to identify existing customers.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="import_csv" class="btn btn-success">Start Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<?php
ob_start();
?>
<script>
    $(document).ready(function() {
        console.log('Customers List Script Initialized');

        // Initialize DataTable
        if ($.fn.DataTable) {
            $('#customersTable').DataTable({
                "pageLength": 25,
                "order": [
                    [0, "asc"]
                ],
                "columnDefs": [{
                    "orderable": false,
                    "targets": 4
                }],
                "responsive": true
            });
        }

        // Use event delegation for delete button
        $(document).on('click', '.delete-customer', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const name = $(this).data('name');

            console.log('Delete button clicked for customer:', name, 'ID:', id);

            if (confirm('Are you sure you want to delete customer "' + name + '"?')) {
                const form = document.getElementById('deleteForm');
                const input = document.getElementById('delete_id');
                if (form && input) {
                    input.value = id;
                    console.log('Submitting delete form for ID:', id);
                    form.submit();
                } else {
                    console.error('Delete form or input not found');
                }
            }
        });
    });
</script>
<?php
$additional_js = ob_get_clean();
include __DIR__ . '/../../templates/footer.php';
?>