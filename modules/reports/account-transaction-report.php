<?php
/**
 * Account Transaction Report
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$from_date = get_param('from_date', date('Y-m-01'));
$to_date = get_param('to_date', date('Y-m-d'));

// Combine sales, purchases, and expenses
$transactions = [];

// Sales
$sales = db_query("SELECT sale_date as date, invoice_number as reference, 'Sales' as type, total_amount as amount, 'Credit' as transaction_type 
                   FROM sales WHERE sale_date BETWEEN ? AND ? AND status = 'completed'", [$from_date, $to_date]);
$transactions = array_merge($transactions, $sales);

// Purchases
$purchases = db_query("SELECT purchase_date as date, invoice_number as reference, 'Purchase' as type, total_amount as amount, 'Debit' as transaction_type 
                       FROM purchases WHERE purchase_date BETWEEN ? AND ? AND status = 'completed'", [$from_date, $to_date]);
$transactions = array_merge($transactions, $purchases);

// Expenses
$expenses = db_query("SELECT date, description as reference, 'Expense' as type, amount, 'Debit' as transaction_type 
                      FROM expenses WHERE date BETWEEN ? AND ?", [$from_date, $to_date]);
$transactions = array_merge($transactions, $expenses);

// Sort by date
usort($transactions, function($a, $b) { return strcmp($a['date'], $b['date']); });

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

$page_title = 'Account Transaction Report';
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

<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Filter</h6></div>
    <div class="card-body">
        <form method="GET">
            <div class="row">
                <div class="col-md-5"><input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>" required></div>
                <div class="col-md-5"><input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>" required></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generate</button></div>
            </div>
        </form>
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

        <div class="report-main-title">Account Transaction Report (<?= format_date($from_date) ?> to <?= format_date($to_date) ?>)</div>

        <!-- Report Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="5" style="text-align:center;">No transactions found for selected period</td></tr>
                <?php else: ?>
                    <?php foreach ($transactions as $trans): ?>
                        <tr>
                            <td><?= format_date($trans['date']) ?></td>
                            <td><?= htmlspecialchars($trans['type']) ?></td>
                            <td><?= htmlspecialchars($trans['reference']) ?></td>
                            <td class="text-end"><?= $trans['transaction_type'] == 'Debit' ? format_currency($trans['amount']) : '-' ?></td>
                            <td class="text-end"><?= $trans['transaction_type'] == 'Credit' ? format_currency($trans['amount']) : '-' ?></td>
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
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Transactions (<?= format_date($from_date) ?> to <?= format_date($to_date) ?>)</h6>
        <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="fas fa-print"></i> Print</button>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-sm" id="transTable">
            <thead>
                <tr><th>Date</th><th>Type</th><th>Reference</th><th>Debit</th><th>Credit</th></tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trans): ?>
                    <tr>
                        <td><?= format_date($trans['date']) ?></td>
                        <td><span class="badge bg-<?= $trans['type'] == 'Sales' ? 'success' : 'warning' ?>"><?= $trans['type'] ?></span></td>
                        <td><?= htmlspecialchars($trans['reference']) ?></td>
                        <td><?= $trans['transaction_type'] == 'Debit' ? format_currency($trans['amount']) : '-' ?></td>
                        <td><?= $trans['transaction_type'] == 'Credit' ? format_currency($trans['amount']) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
<script>$('#transTable').DataTable({"order": [[0, "desc"]]});</script>
