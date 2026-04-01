<?php

/**
 * Sales List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
$can_import_export = is_admin() || has_role(get_current_user_id(), 'Manager') || has_role(get_current_user_id(), 'Admin');

$search = get_param('search', '');
$customer_id = get_param('customer_id', '');
$sold_by = get_param('sold_by', '');
$status = get_param('status', '');
$payment_status = get_param('payment_status', '');
$from_date = get_param('from_date', '');
$to_date = get_param('to_date', '');

$sql = "SELECT s.*, c.name as customer_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (s.invoice_number LIKE ? OR c.name LIKE ? OR s.sold_by LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($customer_id)) {
    $sql .= " AND s.customer_id = ?";
    $params[] = $customer_id;
}

if (!empty($sold_by)) {
    $sql .= " AND s.sold_by = ?";
    $params[] = $sold_by;
}

if (!empty($status)) {
    $sql .= " AND s.status = ?";
    $params[] = $status;
}

if (!empty($payment_status)) {
    $sql .= " AND s.payment_status = ?";
    $params[] = $payment_status;
}

if (!empty($from_date)) {
    $sql .= " AND s.sale_date >= ?";
    $params[] = $from_date;
}

if (!empty($to_date)) {
    $sql .= " AND s.sale_date <= ?";
    $params[] = $to_date;
}

$sql .= " ORDER BY s.created_at DESC";
$sales = db_query($sql, $params);

// Get dependencies for filters
$customers_list = db_query("SELECT id, name FROM customers ORDER BY name ASC");
$salespeople_list = db_query("SELECT DISTINCT sold_by FROM sales WHERE sold_by IS NOT NULL AND sold_by != '' ORDER BY sold_by ASC");

// Get business settings for print header
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

$page_title = 'Sales List';

$page_actions = '';
if ($can_import_export) {
    // Build query string for export to maintain filters
    $export_params = http_build_query([
        'search' => $search,
        'customer_id' => $customer_id,
        'sold_by' => $sold_by,
        'status' => $status,
        'payment_status' => $payment_status,
        'from_date' => $from_date,
        'to_date' => $to_date
    ]);

    $page_actions .= '
        <a href="export-sales.php?' . $export_params . '" class="btn btn-warning me-2"><i class="fas fa-file-export"></i> Export CSV</a>';
}
$page_actions .= '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <a href="pos.php" class="btn btn-primary"><i class="fas fa-cash-register"></i> POS</a>';

$additional_css = '
<style>
/* Print Area Styles (Hidden on Screen) */
#print-area { display: none; }

@media print {
@media print {
    @page { margin: 0.25cm; size: landscape; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; width: 100% !important; max-width: 100% !important; color: #000 !important; }
    
    .btn, .sidebar, #sidebar, .topbar, .navbar, .card-header, .no-print, form, #standard-print-wrapper, #standard-print-footer, footer, .footer { display: none !important; }
    
    /* Strong overrides for global print.css which adds 1.5cm padding */
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
    .table { width: 100% !important; max-width: 100% !important; border-collapse: collapse !important; margin-bottom: 20px !important; }
    
    /* Hard override table styles */
    body .table-bordered th, body .table-bordered td, .table th, .table td { 
        border: 1px solid #000 !important; 
        padding: 8px !important; 
        color: #000 !important; 
        font-size: 13px !important; 
    }
    body .table thead th, .table th { 
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
    
    .print-header-table { width: 100% !important; border-collapse: collapse; margin-bottom: 15px; font-family: "Segoe UI", Arial, sans-serif; font-size: 13px; }
    .print-header-table td { vertical-align: top; border: none !important; padding: 0 !important; }
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
';

include __DIR__ . '/../../templates/header.php';
?>

<div id="print-area">
    <table class="print-header-table">
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
            </td>
        </tr>
    </table>
    <div class="report-main-title">Sales List Report</div>
</div>

<!-- Filter Section -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Sales</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Invoice, Name..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control select2">
                            <option value="">All Customers</option>
                            <?php foreach ($customers_list as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (string)$customer_id === (string)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Sales By</label>
                        <select name="sold_by" class="form-control select2">
                            <option value="">All Salespeople</option>
                            <?php foreach ($salespeople_list as $sp): ?>
                                <option value="<?= htmlspecialchars($sp['sold_by']) ?>" <?= $sold_by === $sp['sold_by'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sp['sold_by']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Status</label>
                        <div class="d-flex gap-1">
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <select name="payment_status" class="form-control">
                                <option value="">Payment</option>
                                <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="partial" <?= $payment_status === 'partial' ? 'selected' : '' ?>>Partial</option>
                                <option value="unpaid" <?= $payment_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date Range</label>
                        <div class="input-group">
                            <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                            <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Sales Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Sales Invoices</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="salesTable">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Sales By</th>
                        <th>Total Amount</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Payment Status</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?= htmlspecialchars($sale['invoice_number']) ?></td>
                            <td><?= format_date($sale['sale_date']) ?></td>
                            <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($sale['sold_by'] ?: 'N/A') ?></span></td>
                            <td><?= format_currency($sale['total_amount']) ?></td>
                            <td><?= format_currency($sale['paid_amount']) ?></td>
                            <td><?= format_currency($sale['due_amount']) ?></td>
                            <td>
                                <?php
                                $badge_class = [
                                    'paid' => 'success',
                                    'partial' => 'warning',
                                    'unpaid' => 'danger'
                                ];
                                $p_status = $sale['payment_status'] ?? 'unpaid';
                                ?>
                                <span class="badge bg-<?= $badge_class[$p_status] ?? 'secondary' ?>">
                                    <?= ucfirst($p_status) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $s_status = $sale['status'] ?? 'pending';
                                $s_badge_class = 'secondary';
                                if ($s_status === 'completed') $s_badge_class = 'success';
                                elseif ($s_status === 'cancelled') $s_badge_class = 'danger';
                                ?>
                                <span class="badge bg-<?= $s_badge_class ?>">
                                    <?= ucfirst($s_status) ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <a href="sale-view.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($sale['payment_status'] !== 'paid'): ?>
                                    <a href="pos.php?edit_id=<?= $sale['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" title="Cannot edit fully paid invoice" disabled>
                                        <i class="fas fa-lock"></i>
                                    </button>
                                <?php endif; ?>
                                <a href="invoice-print.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-primary" title="Print Invoice" target="_blank">
                                    <i class="fas fa-print"></i>
                                </a>
                                <?php if ($sale['payment_status'] !== 'paid' && $sale['status'] === 'completed'): ?>
                                    <a href="sale-payments.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-success" title="Add Payment">
                                        <i class="fas fa-money-bill"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="print-area-footer" class="print-footer clearfix no-print" style="display:none;">
    <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
    <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
</div>

<?php
ob_start();
?>
<script>
    $(document).ready(function() {
        console.log('Sales List Script Initialized');

        // Initialize DataTable
        if ($.fn.DataTable) {
            $('#salesTable').DataTable({
                "pageLength": 25,
                "order": [
                    [1, "desc"]
                ],
                "columnDefs": [{
                    "orderable": false,
                    "targets": 9
                }]
            });
        }
    });
</script>
<?php
$additional_js = ob_get_clean();
include __DIR__ . '/../../templates/footer.php';
?>