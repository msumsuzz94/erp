<?php
/**
 * Product Sales Report
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$from_date = get_param('from_date', date('Y-m-01'));
$to_date = get_param('to_date', date('Y-m-d'));
$category_filter = get_param('category_id', '');
$brand_filter = get_param('brand_id', '');
$product_filter = get_param('product_name', '');

// Get all categories and brands for dropdowns
$categories = db_query("SELECT id, name FROM categories ORDER BY name");
$brands = db_query("SELECT id, name FROM brands ORDER BY name");

// Build dynamic SQL with filters - Detailed view with customers
$sql = "SELECT s.invoice_number, s.sale_date, p.name, p.code, c.name as category_name, b.name as brand_name,
        COALESCE(cust.name, 'Walk-in Customer') as customer_name,
        si.quantity as gross_qty, si.subtotal as gross_amount,
        (SELECT GROUP_CONCAT(serial_number SEPARATOR ', ') FROM product_serials WHERE sale_item_id = si.id) as serial_numbers,
        (SELECT SUM(sri.quantity) FROM sale_return_items sri JOIN sales_returns sr ON sri.return_id = sr.id WHERE sr.sale_id = s.id AND sri.product_id = si.product_id AND sr.status = 'completed') as returned_qty,
        (SELECT SUM(sri.subtotal) FROM sale_return_items sri JOIN sales_returns sr ON sri.return_id = sr.id WHERE sr.sale_id = s.id AND sri.product_id = si.product_id AND sr.status = 'completed') as returned_subtotal,
        s.id as sale_id, s.total_amount, s.paid_amount, s.due_amount
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN customers cust ON s.customer_id = cust.id
        WHERE s.sale_date BETWEEN ? AND ? AND s.status = 'completed'";

$params = [$from_date, $to_date];

// Add category filter
if (!empty($category_filter)) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

// Add brand filter
if (!empty($brand_filter)) {
    $sql .= " AND p.brand_id = ?";
    $params[] = $brand_filter;
}

// Add product name filter
if (!empty($product_filter)) {
    $sql .= " AND p.name LIKE ?";
    $params[] = "%$product_filter%";
}

$sql .= " ORDER BY s.sale_date DESC, p.name ASC";
$products = db_query($sql, $params);

// Calculate Summary Metrics
$summary = [
    'total_sales' => 0,
    'total_paid' => 0,
    'total_due' => 0,
    'invoice_count' => 0,
    'top_product' => 'N/A',
];

$product_qty_map = [];
$processed_invoices = [];

foreach ($products as $prod) {
    // Unique invoice calculations
    if (!in_array($prod['sale_id'], $processed_invoices)) {
        $processed_invoices[] = $prod['sale_id'];
        $summary['total_sales'] += $prod['total_amount'];
        $summary['total_paid'] += $prod['paid_amount'];
        $summary['total_due'] += $prod['due_amount'];
        $summary['invoice_count']++;
    }
    
    // Top Product tracking (Net Qty)
    $net_qty = $prod['gross_qty'] - ($prod['returned_qty'] ?? 0);
    if (!isset($product_qty_map[$prod['name']])) {
        $product_qty_map[$prod['name']] = 0;
    }
    $product_qty_map[$prod['name']] += $net_qty;
}

if (!empty($product_qty_map)) {
    arsort($product_qty_map);
    $top_prod_name = array_key_first($product_qty_map);
    $summary['top_product'] = $top_prod_name . ' (' . $product_qty_map[$top_prod_name] . ' qty)';
}

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

$page_title = 'Product Sales Report';
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
    @page { margin: 0.25cm; size: landscape; }
    html, body { 
        margin: 0 !important; 
        padding: 0 !important; 
        background: #fff !important; 
        width: 100% !important; 
        max-width: 100% !important;
    }
    
    /* Hide regular UI AND standard print header/footer */
    .no-print, .btn, .card, .navbar, .sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, .summary-cards, #standard-print-wrapper, #standard-print-footer {
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
    
    /* Aggressively strip margins/paddings from all elements inside print area */
    #print-area * {
        box-sizing: border-box !important;
    }
    
    /* Ensure the body doesn't have Bootstrap padding */
    body {
        padding: 0 !important;
        margin: 0 !important;
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

        <div class="report-main-title">Product Sales Report (<?= format_date($from_date) ?> to <?= format_date($to_date) ?>)</div>

        <!-- Summary Print Cards -->
        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px;">
            <div style="width: 24%;"><strong>Total Sales</strong><br><?= format_currency($summary['total_sales']) ?></div>
            <div style="width: 24%;"><strong>Paid</strong><br><?= format_currency($summary['total_paid']) ?></div>
            <div style="width: 24%;"><strong>Due</strong><br><?= format_currency($summary['total_due']) ?></div>
            <div style="width: 24%;"><strong>Invoices</strong><br><?= $summary['invoice_count'] ?></div>
        </div>

        <!-- Ledger Table (Now matches Product Sales Details) -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>Invoice#</th>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Code / Serial</th>
                    <th>Category</th>
                    <th>Brand</th>
                    <th>Customer</th>
                    <th>Gross Qty</th>
                    <th>Ret Qty</th>
                    <th>Net Qty</th>
                    <th>Gross Amt</th>
                    <th>Ret Amt</th>
                    <th>Net Amt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $prod): 
                    $net_qty = $prod['gross_qty'] - ($prod['returned_qty'] ?? 0);
                    $net_amt = $prod['gross_amount'] - ($prod['returned_subtotal'] ?? 0);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($prod['invoice_number']) ?></td>
                        <td><?= date('d-m-Y', strtotime($prod['sale_date'])) ?></td>
                        <td><?= htmlspecialchars($prod['name']) ?></td>
                        <td>
                            <?= htmlspecialchars($prod['code']) ?>
                            <?php if (!empty($prod['serial_numbers'])): ?>
                                <br><small class="text-muted">SN: <?= htmlspecialchars($prod['serial_numbers']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($prod['category_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($prod['brand_name'] ?? '-') ?></td>
                        <td><strong><?= htmlspecialchars($prod['customer_name']) ?></strong></td>
                        <td><?= $prod['gross_qty'] ?></td>
                        <td><?= $prod['returned_qty'] ?? 0 ?></td>
                        <td><strong><?= $net_qty ?></strong></td>
                        <td><?= format_currency($prod['gross_amount']) ?></td>
                        <td><?= format_currency($prod['returned_subtotal'] ?? 0) ?></td>
                        <td><strong><?= format_currency($net_amt) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php 
                $g_qty = array_sum(array_column($products, 'gross_qty'));
                $r_qty = array_sum(array_column($products, 'returned_qty'));
                $g_amt = array_sum(array_column($products, 'gross_amount'));
                $r_amt = array_sum(array_column($products, 'returned_subtotal'));
                ?>
                <tr style="background:#f2f2f2; font-weight:bold;">
                    <td colspan="7" class="text-right">TOTAL:</td>
                    <td><?= $g_qty ?></td>
                    <td><?= $r_qty ?></td>
                    <td><?= $g_qty - $r_qty ?></td>
                    <td><?= format_currency($g_amt) ?></td>
                    <td><?= format_currency($r_amt) ?></td>
                    <td><?= format_currency($g_amt - $r_amt) ?></td>
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
        <h6 class="m-0 font-weight-bold text-primary">Filter</h6>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>" required>
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>" required>
                </div>
                <div class="col-md-2">
                    <label>Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Brand</label>
                    <select name="brand_id" class="form-control">
                        <option value="">All Brands</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= $brand['id'] ?>" <?= $brand_filter == $brand['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($brand['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Product Name</label>
                    <input type="text" name="product_name" class="form-control" placeholder="Search product..." value="<?= htmlspecialchars($product_filter) ?>">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Generate Report
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-success ml-2">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <a href="?" class="btn btn-secondary ml-2">
                        <i class="fas fa-redo"></i> Reset Filters
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row mb-4 no-print">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sales</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($summary['total_sales']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Paid</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($summary['total_paid']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Due</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($summary['total_due']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Invoices</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $summary['invoice_count'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Product Sales Details (<?= format_date($from_date) ?> to <?= format_date($to_date) ?>)</h6>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-sm" id="productTable">
            <thead>
                <tr>
                    <th>Invoice#</th>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Code / Serial</th>
                    <th>Category</th>
                    <th>Brand</th>
                    <th>Customer</th>
                    <th>Gross Qty</th>
                    <th>Ret Qty</th>
                    <th>Net Qty</th>
                    <th>Gross Amt</th>
                    <th>Ret Amt</th>
                    <th>Net Amt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $prod): 
                    $net_qty = $prod['gross_qty'] - ($prod['returned_qty'] ?? 0);
                    $net_amt = $prod['gross_amount'] - ($prod['returned_subtotal'] ?? 0);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($prod['invoice_number']) ?></td>
                        <td><?= date('d M Y', strtotime($prod['sale_date'])) ?></td>
                        <td><?= htmlspecialchars($prod['name']) ?></td>
                        <td>
                            <?= htmlspecialchars($prod['code']) ?>
                            <?php if (!empty($prod['serial_numbers'])): ?>
                                <br><small class="text-muted">SN: <?= htmlspecialchars($prod['serial_numbers']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($prod['category_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($prod['brand_name'] ?? '-') ?></td>
                        <td><strong><?= htmlspecialchars($prod['customer_name']) ?></strong></td>
                        <td><?= $prod['gross_qty'] ?></td>
                        <td class="text-danger"><?= $prod['returned_qty'] ?? 0 ?></td>
                        <td class="font-weight-bold"><?= $net_qty ?></td>
                        <td><?= format_currency($prod['gross_amount']) ?></td>
                        <td class="text-danger"><?= format_currency($prod['returned_subtotal'] ?? 0) ?></td>
                        <td class="font-weight-bold"><?= format_currency($net_amt) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php 
                $g_qty = array_sum(array_column($products, 'gross_qty'));
                $r_qty = array_sum(array_column($products, 'returned_qty'));
                $g_amt = array_sum(array_column($products, 'gross_amount'));
                $r_amt = array_sum(array_column($products, 'returned_subtotal'));
                ?>
                <tr class="table-info font-weight-bold">
                    <td colspan="7" class="text-right">TOTAL:</td>
                    <td><?= $g_qty ?></td>
                    <td class="text-danger"><?= $r_qty ?></td>
                    <td><?= $g_qty - $r_qty ?></td>
                    <td><?= format_currency($g_amt) ?></td>
                    <td class="text-danger"><?= format_currency($r_amt) ?></td>
                    <td><?= format_currency($g_amt - $r_amt) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
<script>
$('#productTable').DataTable({
    "order": [[1, "desc"]], // Sort by date descending
    "pageLength": 25
});
</script>
