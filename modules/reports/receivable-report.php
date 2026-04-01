<?php
/**
 * Receivable Report
 * Shows outstanding amounts from customers
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Get customers with outstanding balances (using current_balance as source of truth)
$sql = "SELECT c.id, c.name, c.phone, c.email, c.current_balance,
        COALESCE(SUM(s.total_amount), 0) as total_sales,
        COALESCE(SUM(s.paid_amount), 0) as total_paid,
        c.current_balance as total_due,
        (SELECT cl.date FROM customer_ledger cl 
         WHERE cl.customer_id = c.id AND cl.transaction_type = 'payment' 
         ORDER BY cl.date DESC, cl.created_at DESC LIMIT 1) as last_payment_date,
        (SELECT cl.credit FROM customer_ledger cl 
         WHERE cl.customer_id = c.id AND cl.transaction_type = 'payment' 
         ORDER BY cl.date DESC, cl.created_at DESC LIMIT 1) as last_payment_amount
        FROM customers c
        LEFT JOIN sales s ON c.id = s.customer_id AND s.status = 'completed'
        WHERE c.current_balance > 0
        GROUP BY c.id
        ORDER BY c.current_balance DESC";

$receivables = db_query($sql);

// Calculate totals
$total_receivable = 0;
foreach ($receivables as $item) {
    $total_receivable += $item['total_due'];
}

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

$page_title = 'Receivable Report';
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

        <div class="report-main-title">Customer Receivables Report</div>

        <!-- Report Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Total Sales</th>
                    <th>Total Paid</th>
                    <th class="text-end">Total Due</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($receivables)): ?>
                    <tr><td colspan="6" style="text-align:center;">No receivables found</td></tr>
                <?php else: ?>
                    <?php foreach ($receivables as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['phone'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($item['email'] ?? '-') ?></td>
                            <td><?= format_currency($item['total_sales']) ?></td>
                            <td><?= format_currency($item['total_paid']) ?></td>
                            <td class="text-end"><strong><?= format_currency($item['total_due']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f2f2f2;">
                    <th colspan="5" class="text-end">TOTAL RECEIVABLE</th>
                    <th class="text-end"><?= format_currency($total_receivable) ?></th>
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


<div class="row mb-4 no-print">
    <div class="col-md-12">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Receivables</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($total_receivable) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 no-print">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Customer Receivables</h6>
        <button onclick="window.print()" class="btn btn-success no-print"><i class="fas fa-print"></i> Print Report</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="receivableTable">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Total Sales</th>
                        <th>Total Paid</th>
                        <th>Total Due</th>
                        <th>Last Collection</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receivables as $item): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                            <td><?= htmlspecialchars($item['phone'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($item['email'] ?? '-') ?></td>
                            <td><?= format_currency($item['total_sales']) ?></td>
                            <td><?= format_currency($item['total_paid']) ?></td>
                            <td><strong class="text-danger"><?= format_currency($item['total_due']) ?></strong></td>
                            <td>
                                <?php if ($item['last_payment_date']): ?>
                                    <?= format_date($item['last_payment_date']) ?><br>
                                    <small class="text-success"><?= format_currency($item['last_payment_amount']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="no-print">
                                <a href="../customers/bill-collections.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-primary" title="Collect Payment">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </a>
                                <a href="../customers/customer-view.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-info" title="View Customer" target="_blank">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="receivable-statement.php?customer_id=<?= $item['id'] ?>" class="btn btn-sm btn-success" title="Print Statement" target="_blank">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th colspan="5">TOTAL</th>
                        <th><?= format_currency($total_receivable) ?></th>
                        <th></th>
                        <th class="no-print"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#receivableTable').DataTable({
        "order": [[5, "desc"]]
    });
});
</script>
