<?php
/**
 * Expense Report Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$from_date = get_param('from_date', date('Y-m-01'));
$to_date = get_param('to_date', date('Y-m-d'));
$category_id = get_param('category', '');
$search = get_param('search', '');

$sql = "SELECT e.*, ec.name as category_name, 
        COALESCE(ca.account_name, ba.bank_name) as account_name,
        e.account_type,
        e.payment_method
        FROM expenses e
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN cash_accounts ca ON e.account_id = ca.id AND e.account_type = 'cash'
        LEFT JOIN bank_accounts ba ON e.account_id = ba.id AND e.account_type = 'bank'
        WHERE e.expense_date BETWEEN ? AND ?";

$params = [$from_date, $to_date];

if (!empty($category_id)) {
    $sql .= " AND e.category_id = ?";
    $params[] = $category_id;
}

if (!empty($search)) {
    $sql .= " AND (e.description LIKE ? OR e.reference_no LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY e.expense_date DESC";
$expenses = db_query($sql, $params);

$total_expenses = array_sum(array_column($expenses, 'amount'));

$categories = db_select('expense_categories', [], '*', 'name ASC');

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

$page_title = 'Expense Report';
$page_actions = '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>';

$additional_css = '
<style>
@media print {
    .btn, .sidebar, #sidebar, .topbar, .navbar, .card-header, .no-print, form { display: none !important; }
    .container-fluid { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
    #content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table-responsive { overflow: visible !important; }
    .table { width: 100% !important; border-collapse: collapse !important; }
    .table th, .table td { border: 1px solid #ddd !important; padding: 8px !important; }
    body { padding-top: 0 !important; background: white !important; }
    .text-gray-800 { color: black !important; }
    .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
    .card-body { padding: 0 !important; }
}
.print-header { display: none; }
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
        font-family: \'Segoe UI\', Arial, sans-serif; 
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

        <div class="report-main-title">Expense Report (<?= format_date($from_date) ?> to <?= format_date($to_date) ?>)</div>

        <!-- Report Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Payment Method</th>
                    <th>Account</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="7" style="text-align:center;">No expenses found for selected period</td></tr>
                <?php else: ?>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?= format_date($expense['expense_date']) ?></td>
                            <td><?= htmlspecialchars($expense['category_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($expense['description'] ?? '-') ?></td>
                            <td><?= ucfirst($expense['payment_method'] ?? 'cash') ?></td>
                            <td><?= htmlspecialchars($expense['account_name'] ?? 'N/A') ?></td>
                            <td class="text-end"><?= format_currency($expense['amount']) ?></td>
                            <td><?= ucfirst($expense['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f2f2f2;">
                    <th colspan="5">TOTAL (All Expenses)</th>
                    <th class="text-end"><?= format_currency($total_expenses) ?></th>
                    <th></th>
                </tr>
                <?php 
                $total_approved = array_sum(array_map(function($e){ 
                    return $e['status'] == 'approved' ? $e['amount'] : 0; 
                }, $expenses));
                ?>
                <tr style="background:#d4edda;">
                    <th colspan="5">TOTAL (Approved Only)</th>
                    <th class="text-end"><?= format_currency($total_approved) ?></th>
                    <th></th>
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

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Expenses</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date Range</label>
                        <div class="input-group">
                            <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>" required>
                            <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control select2">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Search Description</label>
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Expenses (<?= format_date($from_date) ?> to <?= format_date($to_date) ?>)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($total_expenses) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Expense Report Details</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="expensesTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Payment Method</th>
                        <th>Account</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?= format_date($expense['expense_date']) ?></td>
                            <td><?= htmlspecialchars($expense['category_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($expense['description'] ?? '-') ?></td>
                            <td>
                                <?php 
                                $pm = $expense['payment_method'] ?? 'cash';
                                $badge_class = $pm == 'cash' ? 'bg-success' : 'bg-info';
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= ucfirst($pm) ?></span>
                            </td>
                            <td><?= htmlspecialchars($expense['account_name'] ?? 'N/A') ?></td>
                            <td><?= format_currency($expense['amount']) ?></td>
                            <td>
                                <?php if($expense['status'] == 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php elseif($expense['status'] == 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th colspan="5">TOTAL (Approved Only)</th>
                        <?php 
                        $total_approved = array_sum(array_map(function($e){ 
                            return $e['status'] == 'approved' ? $e['amount'] : 0; 
                        }, $expenses));
                        ?>
                        <th><?= format_currency($total_approved) ?></th>
                        <th colspan="1"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#expensesTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]]
    });
});
</script>
