<?php
/**
 * Profit & Loss Report
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$from_date = get_param('from_date', date('Y-m-01'));
$to_date = get_param('to_date', date('Y-m-d'));

// Sales
$sql_sales = "SELECT SUM(total_amount) as revenue FROM sales 
              WHERE sale_date BETWEEN ? AND ? AND status = 'completed'";
$sales = db_query_one($sql_sales, [$from_date, $to_date]);

// Sales Returns
$sql_returns = "SELECT SUM(total_amount) as total FROM sales_returns 
               WHERE return_date BETWEEN ? AND ? AND status = 'completed'";
$returns = db_query_one($sql_returns, [$from_date, $to_date]);
$total_returns = $returns['total'] ?? 0;

// Cost of Goods Sold (Purchases)
$sql_cogs = "SELECT SUM(total_amount) as cogs FROM purchases 
             WHERE purchase_date BETWEEN ? AND ? AND status = 'completed'";
$cogs = db_query_one($sql_cogs, [$from_date, $to_date]);

// Expenses
$sql_expenses = "SELECT ec.name as category, SUM(e.amount) as amount
                 FROM expenses e
                 LEFT JOIN expense_categories ec ON e.category_id = ec.id
                 WHERE e.date BETWEEN ? AND ?
                 GROUP BY ec.id, ec.name";
$expenses = db_query($sql_expenses, [$from_date, $to_date]);

$total_expenses = array_sum(array_column($expenses, 'amount'));

// Calculations
$gross_revenue = $sales['revenue'] ?? 0;
$revenue = $gross_revenue - $total_returns;
$gross_profit = $revenue - ($cogs['cogs'] ?? 0);
$net_profit = $gross_profit - $total_expenses;

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

$page_title = 'Profit & Loss Report';
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

        <div class="report-main-title">Profit & Loss Statement (<?= format_date($from_date) ?> to <?= format_date($to_date) ?>)</div>

        <!-- Profit/Loss Table -->
        <table class="print-table">
            <thead>
                <tr style="background:#e9ecef;">
                    <th colspan="2">REVENUE</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Sales Revenue (Gross)</td>
                    <td class="text-end"><?= format_currency($gross_revenue) ?></td>
                </tr>
                <tr>
                    <td>Sales Returns (-)</td>
                    <td class="text-end"><?= format_currency($total_returns) ?></td>
                </tr>
                <tr style="background:#d1ecf1;">
                    <td><strong>Net Revenue</strong></td>
                    <td class="text-end"><strong><?= format_currency($revenue) ?></strong></td>
                </tr>
            </tbody>
            
            <thead>
                <tr style="background:#e9ecef;">
                    <th colspan="2">COST OF GOODS SOLD</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Purchases</td>
                    <td class="text-end"><?= format_currency($cogs['cogs'] ?? 0) ?></td>
                </tr>
                <tr style="background:#cce5ff;">
                    <td><strong>GROSS PROFIT</strong></td>
                    <td class="text-end"><strong><?= format_currency($gross_profit) ?></strong></td>
                </tr>
            </tbody>
            
            <thead>
                <tr style="background:#e9ecef;">
                    <th colspan="2">OPERATING EXPENSES</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td><?= htmlspecialchars($expense['category'] ?? 'Uncategorized') ?></td>
                        <td class="text-end"><?= format_currency($expense['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><strong>Total Expenses</strong></td>
                    <td class="text-end"><strong><?= format_currency($total_expenses) ?></strong></td>
                </tr>
            </tbody>
            
            <tfoot>
                <tr style="background:<?= $net_profit >= 0 ? '#d4edda' : '#f8d7da' ?>;">
                    <th>NET PROFIT / (LOSS)</th>
                    <th class="text-end"><?= format_currency($net_profit) ?></th>
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

<div class="card shadow mb-4 no-print">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Period</h6>
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
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Generate</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4 no-print">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Profit & Loss Statement</h6>
        <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="fas fa-print"></i> Print</button>
    </div>
    <div class="card-body">
        <h5 class="text-center mb-4">Period: <?= format_date($from_date) ?> to <?= format_date($to_date) ?></h5>
        
        <table class="table table-bordered">
            <tr class="table-light">
                <th colspan="2"><h6 class="mb-0">REVENUE</h6></th>
            </tr>
            <tr>
                <td>Sales Revenue (Gross)</td>
                <td class="text-end"><?= format_currency($gross_revenue) ?></td>
            </tr>
            <tr>
                <td>Sales Returns <span class="text-danger">(-)</span></td>
                <td class="text-end text-danger"><?= format_currency($total_returns) ?></td>
            </tr>
            <tr class="table-info">
                <td><strong>Net Revenue</strong></td>
                <td class="text-end"><strong><?= format_currency($revenue) ?></strong></td>
            </tr>
            
            <tr class="table-light">
                <th colspan="2"><h6 class="mb-0">COST OF GOODS SOLD</h6></th>
            </tr>
            <tr>
                <td>Purchases</td>
                <td class="text-end"><?= format_currency($cogs['cogs'] ?? 0) ?></td>
            </tr>
            <tr class="table-primary">
                <td><strong>GROSS PROFIT</strong></td>
                <td class="text-end"><strong><?= format_currency($gross_profit) ?></strong></td>
            </tr>
            
            <tr class="table-light">
                <th colspan="2"><h6 class="mb-0">OPERATING EXPENSES</h6></th>
            </tr>
            <?php foreach ($expenses as $expense): ?>
                <tr>
                    <td><?= htmlspecialchars($expense['category'] ?? 'Uncategorized') ?></td>
                    <td class="text-end"><?= format_currency($expense['amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td><strong>Total Expenses</strong></td>
                <td class="text-end"><strong><?= format_currency($total_expenses) ?></strong></td>
            </tr>
            
            <tr class="<?= $net_profit >= 0 ? 'table-success' : 'table-danger' ?>">
                <td><h5 class="mb-0">NET PROFIT / (LOSS)</h5></td>
                <td class="text-end"><h5 class="mb-0"><?= format_currency($net_profit) ?></h5></td>
            </tr>
        </table>
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card border-left-primary">
                    <div class="card-body">
                        <small class="text-muted">Gross Profit Margin</small>
                        <h4><?= $revenue > 0 ? number_format(($gross_profit / $revenue) * 100, 2) : 0 ?>%</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-success">
                    <div class="card-body">
                        <small class="text-muted">Net Profit Margin</small>
                        <h4><?= $revenue > 0 ? number_format(($net_profit / $revenue) * 100, 2) : 0 ?>%</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-info">
                    <div class="card-body">
                        <small class="text-muted">Expense Ratio</small>
                        <h4><?= $revenue > 0 ? number_format(($total_expenses / $revenue) * 100, 2) : 0 ?>%</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
