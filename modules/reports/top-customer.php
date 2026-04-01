<?php
/**
 * Top Customer Report
 * Shows customers ranked by total purchase amount
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Get filter parameters
$from_date = get_param('from_date', date('Y-m-01'));
$to_date = get_param('to_date', date('Y-m-d'));
$limit = get_param('limit', 20);

// Get top customers by sales amount
$sql = "SELECT c.id, c.name, c.phone, c.email, c.address,
        COUNT(DISTINCT s.id) as total_orders,
        SUM(s.total_amount) as total_purchase,
        SUM(s.paid_amount) as total_paid,
        SUM(s.due_amount) as total_due
        FROM customers c
        LEFT JOIN sales s ON c.id = s.customer_id AND s.status = 'completed'
        WHERE s.sale_date BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY total_purchase DESC
        LIMIT ?";

$top_customers = db_query($sql, [$from_date, $to_date, (int)$limit]);

// Get invoice settings for print header
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

$page_title = 'Top Customer Report';
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

        <div class="report-main-title">Top <?= $limit ?> Customers (<?= format_date($from_date) ?> to <?= format_date($to_date) ?>)</div>

        <!-- Report Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Total Orders</th>
                    <th>Total Purchase</th>
                    <th>Total Paid</th>
                    <th class="text-end">Total Due</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($top_customers)): ?>
                    <tr><td colspan="7" style="text-align:center;">No customers found for selected period</td></tr>
                <?php else: ?>
                    <?php foreach ($top_customers as $index => $customer): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= htmlspecialchars($customer['name']) ?></strong></td>
                            <td><?= htmlspecialchars($customer['phone'] ?? '-') ?></td>
                            <td><?= $customer['total_orders'] ?></td>
                            <td><strong><?= format_currency($customer['total_purchase']) ?></strong></td>
                            <td><?= format_currency($customer['total_paid']) ?></td>
                            <td class="text-end"><?= format_currency($customer['total_due']) ?></td>
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
        <h6 class="m-0 font-weight-bold text-primary">Filter Report</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Top N Customers</label>
                        <select name="limit" class="form-control">
                            <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>Top 10</option>
                            <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>Top 20</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>Top 50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>Top 100</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generate Report</button>
            <button type="button" onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print</button>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            Top <?= $limit ?> Customers (<?= format_date($from_date) ?> to <?= format_date($to_date) ?>)
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="topCustomerTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Total Orders</th>
                        <th>Total Purchase</th>
                        <th>Total Paid</th>
                        <th>Total Due</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_customers as $index => $customer): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= htmlspecialchars($customer['name']) ?></strong></td>
                            <td><?= htmlspecialchars($customer['phone'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($customer['email'] ?? '-') ?></td>
                            <td><?= $customer['total_orders'] ?></td>
                            <td><strong><?= format_currency($customer['total_purchase']) ?></strong></td>
                            <td><?= format_currency($customer['total_paid']) ?></td>
                            <td><?= format_currency($customer['total_due']) ?></td>
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
    $('#topCustomerTable').DataTable({
        "paging": false,
        "searching": false,
        "info": false
    });
});
</script>
