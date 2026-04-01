<?php
/**
 * Purchase Report Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get filter parameters
$date_from = get_param('date_from', date('Y-m-01'));
$date_to = get_param('date_to', date('Y-m-d'));
$supplier_id = get_param('supplier_id', '');
$status = get_param('status', '');

// Build query
$sql = "SELECT p.*, s.name as supplier_name 
        FROM purchases p 
        INNER JOIN suppliers s ON p.supplier_id = s.id 
        WHERE p.purchase_date BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if (!empty($supplier_id)) {
    $sql .= " AND p.supplier_id = ?";
    $params[] = $supplier_id;
}

if (!empty($status)) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY p.purchase_date DESC";
$purchases = db_query($sql, $params);

// Calculate totals
$total_amount = 0;
$total_paid = 0;
$total_due = 0;

foreach ($purchases as $purchase) {
    $total_amount += $purchase['total_amount'];
    $total_paid += $purchase['paid_amount'];
    $total_due += $purchase['due_amount'];
}

// Get all suppliers for filter
$suppliers = db_query("SELECT id, name FROM suppliers ORDER BY name ASC");

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

$page_title = 'Purchase Report';
include __DIR__ . '/../../templates/header.php';
?>

<style>
/* Print Area Styles (Hidden on Screen) */
#print-area { display: none; padding: 20px; background: #fff; color: #000; }

@media print {
    @page { margin: 0.25cm; size: landscape; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; width: 100% !important; max-width: 100% !important; color: #000 !important; }
    
    .btn, .sidebar, #sidebar, .topbar, .navbar, .card-header, .no-print, form, #standard-print-wrapper, #standard-print-footer, footer, .footer, .summary-cards { display: none !important; }
    
    /* Strong overrides for global print.css padding */
    body #content, body .container-fluid, body .wrapper { 
        padding: 0 !important; 
        margin: 0 !important; 
        width: 100% !important; 
        max-width: 100% !important; 
        box-sizing: border-box !important; 
        display: block !important;
    }
    
    .card { border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
    .card-body { padding: 0 !important; }
    
    .table-responsive { overflow: visible !important; width: 100% !important; margin: 0 !important; }
    .table, .print-table { width: 100% !important; max-width: 100% !important; border-collapse: collapse !important; margin-bottom: 20px !important; }
    
    /* Hard override table styles */
    body .table-bordered th, body .table-bordered td, .table th, .table td, .print-table th, .print-table td { 
        border: 1px solid #000 !important; 
        padding: 5px 8px !important; 
        color: #000 !important; 
        font-size: 13px !important; 
    }
    body .table thead th, .table th, .print-table th { 
        background-color: #e0e0e0 !important; 
        font-weight: bold !important; 
        font-size: 14px !important; 
        text-transform: uppercase !important; 
        color: #000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .text-gray-800, .text-muted, .text-primary, .text-success, .text-danger, .text-warning, .text-info { color: #000 !important; }
    .badge { border: 1px solid #000 !important; color: #000 !important; background: transparent !important; box-shadow: none !important; padding: 4px 6px !important; font-size: 12px !important; font-weight: bold !important; display: inline-block; }
    
    /* Show print-specific area */
    #print-area { display: block !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    #print-area * { box-sizing: border-box !important; color: #000 !important; }
    
    .print-header-table, .header-table { width: 100% !important; border-collapse: collapse; margin-bottom: 15px; font-family: "Segoe UI", Arial, sans-serif; font-size: 13px; }
    .print-header-table td, .header-table td { vertical-align: top; border: none !important; padding: 0 !important; }
    .logo-cell { width: 10%; text-align: left; }
    .logo-cell img { max-width: 90px; height: auto; display: block; }
    .title-cell { width: 65%; text-align: center; padding: 0 10px; }
    .company-name { font-size: 26px; font-weight: 900; color: #000; margin: 0; text-transform: uppercase; border-bottom: 2px solid #000; display: inline-block; line-height: 1.2; padding-bottom: 4px; }
    .company-slogan { font-size: 14px; color: #000; font-weight: bold; margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-cell { width: 25%; text-align: left; line-height: 1.5; font-size: 12px; border: 1px solid #000 !important; padding: 6px 10px !important; }
    .info-cell p { margin: 0; margin-bottom: 3px; font-weight: bold; }
    .report-main-title { text-align: center; font-size: 20px; color: #000; margin: 15px 0; font-weight: bold; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 8px 0; text-transform: uppercase; }
    
    .print-footer { 
        position: fixed; bottom: 0.25cm; left: 0; right: 0; border-top: 2px solid #000 !important; padding-top: 10px !important; 
        display: block !important; width: 100% !important; font-size: 12px !important; background: #fff !important; z-index: 9999; color: #000 !important;
    }
    .footer-left { float: left; width: 50%; font-weight: bold; text-align: left; padding-left: 10px; }
    .footer-right { float: right; width: 50%; text-align: right; padding-right: 10px; }
    .clearfix::after { content: ""; clear: both; display: table; }
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

        <div class="report-main-title">Purchase Report (<?= format_date($date_from) ?> to <?= format_date($date_to) ?>)</div>

        <!-- Ledger Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Purchase #</th>
                    <th>Supplier</th>
                    <th class="text-end">Total Amount</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Due</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $purchase): ?>
                    <tr>
                        <td><?= format_date($purchase['purchase_date']) ?></td>
                        <td><?= htmlspecialchars($purchase['purchase_number']) ?></td>
                        <td><?= htmlspecialchars($purchase['supplier_name']) ?></td>
                        <td class="text-end"><?= format_currency($purchase['total_amount']) ?></td>
                        <td class="text-end"><?= format_currency($purchase['paid_amount']) ?></td>
                        <td class="text-end"><?= format_currency($purchase['due_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f2f2f2;">
                    <th colspan="3" class="text-end">TOTAL</th>
                    <th class="text-end"><?= format_currency($total_amount) ?></th>
                    <th class="text-end"><?= format_currency($total_paid) ?></th>
                    <th class="text-end"><?= format_currency($total_due) ?></th>
                </tr>
            </tfoot>
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
        <h6 class="m-0 font-weight-bold text-primary">Filter Report</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>" <?= $supplier_id == $supplier['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($supplier['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generate Report</button>
            <a href="purchase-report.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
            <button type="button" class="btn btn-success" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row">
    <div class="col-md-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Purchases</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($total_amount) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Paid</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($total_paid) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Due</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($total_due) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<br>

<!-- Purchase Report Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Purchase Details</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="reportTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Purchase #</th>
                        <th>Supplier</th>
                        <th>Total Amount</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Payment Status</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $purchase): ?>
                        <tr>
                            <td><?= format_date($purchase['purchase_date']) ?></td>
                            <td><?= htmlspecialchars($purchase['purchase_number']) ?></td>
                            <td><?= htmlspecialchars($purchase['supplier_name']) ?></td>
                            <td><?= format_currency($purchase['total_amount']) ?></td>
                            <td><?= format_currency($purchase['paid_amount']) ?></td>
                            <td class="text-danger"><?= format_currency($purchase['due_amount']) ?></td>
                            <td>
                                <span class="badge bg-<?= $purchase['payment_status'] === 'paid' ? 'success' : ($purchase['payment_status'] === 'partial' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($purchase['payment_status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $purchase['status'] === 'completed' ? 'success' : ($purchase['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                    <?= ucfirst($purchase['status']) ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <a href="../purchase/purchase-view.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-info" title="View Details" target="_blank">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="../purchase/purchase-print.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-success" title="Print Invoice" target="_blank">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-active">
                        <th colspan="3" class="text-end">Total:</th>
                        <th><?= format_currency($total_amount) ?></th>
                        <th><?= format_currency($total_paid) ?></th>
                        <th class="text-danger"><?= format_currency($total_due) ?></th>
                        <th colspan="3"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#reportTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]]
    });
});
</script>
