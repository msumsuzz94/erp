<?php
/**
 * Product View Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$product_id = (int)get_param('id');

$sql = "SELECT p.*, c.name as category_name, b.name as brand_name, u.name as unit_name, u.short_name as unit_short
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN units u ON p.unit_id = u.id
        WHERE p.id = ?";
$product = db_query_one($sql, [$product_id]);

if (!$product) {
    redirect_with_message('products-list.php', 'Product not found', 'error');
}

// Fetch in-stock serial numbers if applicable (all types)
$serials = [];
$serials_by_type = ['current' => [], 'damaged' => [], 'rma' => [], 'loss' => []];
if ($product['has_serial'] === 'Available') {
    $sql_serials = "SELECT serial_number, stock_type, status FROM product_serials 
                    WHERE product_id = ? AND status IN ('in_stock','defective') ORDER BY created_at ASC";
    $serials_data = db_query($sql_serials, [$product_id]);
    foreach ($serials_data as $s) {
        if (empty($s['serial_number'])) continue;
        $sn = $s['serial_number'];
        $type = !empty($s['stock_type']) ? $s['stock_type'] : 'current';
        $serials_by_type[$type][] = $sn;
        if ($type === 'current') $serials[] = $sn; // keep backward compat
    }
}

// Fetch damaged & RMA quantities from stock_type_inventory
$damaged_qty = 0;
$rma_qty     = $product['rma_quantity'] ?? 0;
$damaged_row = db_query_one(
    "SELECT SUM(quantity) as qty FROM stock_type_inventory WHERE product_id = ? AND stock_type = 'damaged'",
    [$product_id]
);
if ($damaged_row) $damaged_qty = (int)$damaged_row['qty'];

$rma_row = db_query_one(
    "SELECT SUM(quantity) as qty FROM stock_type_inventory WHERE product_id = ? AND stock_type = 'rma'",
    [$product_id]
);
if ($rma_row && $rma_row['qty'] !== null) $rma_qty = (int)$rma_row['qty'];

$total_qty = ($product['stock_quantity'] ?? 0) + $damaged_qty + $rma_qty;

// Get invoice settings for print header
$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$invoice_settings) {
    $invoice_settings = [
        'company_name' => BUSINESS_NAME,
        'company_address' => BUSINESS_ADDRESS,
        'company_phone' => BUSINESS_PHONE,
        'company_email' => BUSINESS_EMAIL,
        'company_website' => '',
        'company_logo' => 'assets/images/logo.png',
        'company_slogan' => ''
    ];
}

$page_title = 'Product Details';
$page_actions = '<button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print View</button>
                 <a href="product-edit.php?id=' . $product_id . '" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
                 <a href="products-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>';
include __DIR__ . '/../../templates/header.php';
?>

<style>
/* Print Area Hidden on Screen */
#print-area { display: none; }

@media print {
    @page { margin: 0.25cm; size: auto; }
    html, body {
        margin: 0 !important; padding: 0 !important;
        background: #fff !important; color: #000 !important;
        width: 100% !important;
    }

    /* Hide everything from main UI */
    .no-print, .btn, .card, .card-header, .card-body, .navbar, .sidebar,
    #accordionSidebar, .topbar, footer, .footer, #footer,
    .main-header, #wrapper, #content-wrapper, .row {
        display: none !important;
    }

    /* Show only print area */
    #print-area {
        display: block !important;
        width: 100% !important;
        margin: 0 !important; padding: 0 !important;
        position: absolute; top: 0; left: 0;
        background-color: #fff !important;
    }

    #print-area, #print-area * { color: #000 !important; }

    .print-container {
        width: 100% !important; max-width: 100% !important;
        padding: 10px !important; margin: 0 !important;
        box-sizing: border-box !important;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 12px;
    }

    /* 3-Column Header */
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .header-table td { vertical-align: top; border: none !important; padding: 0; }
    .logo-cell { width: 10%; text-align: left; }
    .logo-cell img { max-width: 80px; height: auto; display: block; }
    .title-cell { width: 65%; text-align: center; padding: 0 10px; }
    .company-name { font-size: 22px; font-weight: 900; color: #000 !important; margin: 0; text-transform: uppercase; border-bottom: 2px solid #000; display: inline-block; line-height: 1.2; padding-bottom: 2px; }
    .company-slogan { font-size: 11px; color: #000 !important; font-weight: bold; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-cell { width: 25%; text-align: left; line-height: 1.4; font-size: 9px; border: 1px solid #000; padding: 5px 8px; box-sizing: border-box; }
    .info-cell p { margin: 0 0 2px 0; }

    /* Section Title */
    .page-main-title { text-align: center; font-size: 16px; color: #000 !important; margin: 15px 0 10px 0; font-weight: bold; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 5px 0; }

    /* Product Info Table */
    .print-product-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .print-product-table th, .print-product-table td { border: 1px solid #333 !important; padding: 6px 10px; text-align: left; color: #000 !important; font-size: 12px; }
    .print-product-table th { background-color: #f2f2f2 !important; font-weight: bold; width: 30%; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    /* Serial Badges */
    .print-serial-list { margin-top: 5px; }
    .print-serial-badge { display: inline-block; border: 1px solid #333; padding: 3px 8px; margin: 3px; font-size: 11px; font-weight: bold; background: #f9f9f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    /* Footer */
    .print-footer {
        position: fixed; bottom: 0.3cm; left: 0; right: 0;
        border-top: 1px solid #333; padding-top: 10px;
        display: block; width: 100%; font-size: 10px;
        background: #fff !important; color: #000 !important; z-index: 9999;
    }
    .footer-left { float: left; width: 50%; font-weight: bold; text-align: left; padding-left: 10px; }
    .footer-right { float: right; width: 50%; text-align: right; padding-right: 10px; }
    .clearfix::after { content: ""; clear: both; display: table; }
}
</style>

<!-- ========== HIDDEN PRINT AREA ========== -->
<div id="print-area">
    <div class="print-container">
        <!-- Company Header -->
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

        <!-- Title -->
        <div class="page-main-title">Product Information</div>

        <!-- Product Details Table -->
        <table class="print-product-table">
            <tr><th>Product Name</th><td><?= htmlspecialchars($product['name']) ?></td></tr>
            <tr><th>Product Code</th><td><?= htmlspecialchars($product['code']) ?></td></tr>
            <tr><th>Barcode</th><td><?= htmlspecialchars($product['barcode'] ?? '-') ?></td></tr>
            <tr><th>Category</th><td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td></tr>
            <tr><th>Brand</th><td><?= htmlspecialchars($product['brand_name'] ?? '-') ?></td></tr>
            <tr><th>Unit</th><td><?= htmlspecialchars($product['unit_name'] ?? '-') ?></td></tr>
            <tr><th>Purchase Price</th><td><?= format_currency($product['purchase_price']) ?></td></tr>
            <tr><th>Selling Price</th><td><strong><?= format_currency($product['selling_price']) ?></strong></td></tr>
            <tr><th>Tax Rate</th><td><?= $product['tax_rate'] ?>%</td></tr>
            <tr><th>Current Stock</th><td><?= $product['stock_quantity'] ?> <?= $product['unit_short'] ?? '' ?></td></tr>
            <tr><th>Damaged Stock</th><td><?= $damaged_qty ?> <?= $product['unit_short'] ?? '' ?></td></tr>
            <tr><th>RMA Stock</th><td><?= $rma_qty ?> <?= $product['unit_short'] ?? '' ?></td></tr>
            <tr><th>Reorder Level</th><td><?= $product['reorder_level'] ?></td></tr>
            <tr><th>Status</th><td><?= ucfirst($product['status']) ?></td></tr>
            <?php if (!empty($product['description'])): ?>
            <tr><th>Description</th><td><?= nl2br(htmlspecialchars($product['description'])) ?></td></tr>
            <?php endif; ?>
        </table>

        <?php if ($product['has_serial'] === 'Available'): ?>
            <div class="page-main-title">Serials by Stock Type (Total: <?= $total_qty ?>)</div>
            
            <?php if (!empty($serials_by_type['current'])): ?>
                <strong>Current Stock (<?= count($serials_by_type['current']) ?>)</strong>
                <div class="print-serial-list" style="margin-bottom: 10px;">
                    <?php foreach ($serials_by_type['current'] as $sn): ?>
                        <span class="print-serial-badge"><?= htmlspecialchars($sn) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($serials_by_type['damaged'])): ?>
                <strong>Damaged Stock (<?= count($serials_by_type['damaged']) ?>)</strong>
                <div class="print-serial-list" style="margin-bottom: 10px;">
                    <?php foreach ($serials_by_type['damaged'] as $sn): ?>
                        <span class="print-serial-badge"><?= htmlspecialchars($sn) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($serials_by_type['rma'])): ?>
                <strong>RMA Stock (<?= count($serials_by_type['rma']) ?>)</strong>
                <div class="print-serial-list" style="margin-bottom: 10px;">
                    <?php foreach ($serials_by_type['rma'] as $sn): ?>
                        <span class="print-serial-badge"><?= htmlspecialchars($sn) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<!-- ========== SCREEN UI ========== -->
<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Product Information</h6>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="30%">Product Name</th>
                        <td><?= htmlspecialchars($product['name']) ?></td>
                    </tr>
                    <tr>
                        <th>Product Code</th>
                        <td><?= htmlspecialchars($product['code']) ?></td>
                    </tr>
                    <tr>
                        <th>Barcode</th>
                        <td><?= htmlspecialchars($product['barcode'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Brand</th>
                        <td><?= htmlspecialchars($product['brand_name'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Unit</th>
                        <td><?= htmlspecialchars($product['unit_name'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Purchase Price</th>
                        <td><?= format_currency($product['purchase_price']) ?></td>
                    </tr>
                    <tr>
                        <th>Selling Price</th>
                        <td><strong><?= format_currency($product['selling_price']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Tax Rate</th>
                        <td><?= $product['tax_rate'] ?>%</td>
                    </tr>
                    <tr>
                        <th>Current Stock</th>
                        <td>
                            <?php if ($product['stock_quantity'] <= $product['reorder_level']): ?>
                                <span class="badge bg-danger"><?= $product['stock_quantity'] ?> <?= $product['unit_short'] ?? '' ?></span>
                                <small class="text-danger">Low Stock!</small>
                            <?php else: ?>
                                <span class="badge bg-success"><?= $product['stock_quantity'] ?> <?= $product['unit_short'] ?? '' ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Damaged Stock</th>
                        <td>
                            <span class="badge bg-warning text-dark"><?= $damaged_qty ?> <?= $product['unit_short'] ?? '' ?></span>
                            <?php if ($damaged_qty > 0): ?>
                                <small class="text-muted ms-1">— awaiting RMA/service</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>RMA Stock</th>
                        <td>
                            <span class="badge bg-info text-dark"><?= $rma_qty ?> <?= $product['unit_short'] ?? '' ?></span>
                            <?php if ($rma_qty > 0): ?>
                                <small class="text-muted ms-1">— at service center</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Reorder Level</th>
                        <td><?= $product['reorder_level'] ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?= $product['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($product['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><?= nl2br(htmlspecialchars($product['description'] ?? '-')) ?></td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td><?= format_datetime($product['created_at']) ?></td>
                    </tr>
                    <?php if ($product['updated_at']): ?>
                    <tr>
                        <th>Last Updated</th>
                        <td><?= format_datetime($product['updated_at']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Product Image</h6>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($product['image'])): ?>
                    <img src="<?= BASE_URL ?>/uploads/products/<?= $product['image'] ?>" alt="Product" class="img-fluid" style="max-width: 100%;">
                <?php else: ?>
                    <div style="padding: 50px; background: #e9ecef;">
                        <i class="fas fa-image fa-5x text-muted"></i>
                        <p class="mt-3 text-muted">No image available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <a href="product-edit.php?id=<?= $product_id ?>" class="btn btn-warning btn-block mb-2">
                    <i class="fas fa-edit"></i> Edit Product
                </a>
                <a href="../barcode/generate.php?product_id=<?= $product_id ?>" class="btn btn-info btn-block mb-2">
                    <i class="fas fa-barcode"></i> Generate Barcode
                </a>
                <a href="stock-adjustment.php?product_id=<?= $product_id ?>" class="btn btn-secondary btn-block">
                    <i class="fas fa-boxes"></i> Adjust Stock
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($product['has_serial'] === 'Available'): ?>

<style>
/* Stock lifecycle cards — dark mode safe */
.slc-card { border-radius: 8px; overflow: hidden; height: 100%; }
.slc-header { padding: 10px 12px; font-weight: 600; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
.slc-header .slc-icon { margin-right: 6px; }
.slc-body  { padding: 10px; min-height: 60px; }
.slc-badge { display: inline-block; padding: 3px 10px; margin: 3px 4px 3px 0;
             border-radius: 20px; font-size: 12px; font-weight: 600; letter-spacing: 0.3px; }
/* Current */
.slc-current .slc-header  { background: #198754; color: #fff; }
.slc-current              { border: 2px solid #198754; }
.slc-current .slc-badge   { background: rgba(25,135,84,.12); color: #146c43; border: 1px solid #198754; }
/* Damaged */
.slc-damaged .slc-header  { background: #fd7e14; color: #fff; }
.slc-damaged              { border: 2px solid #fd7e14; }
.slc-damaged .slc-badge   { background: rgba(253,126,20,.12); color: #a0520c; border: 1px solid #fd7e14; }
/* RMA */
.slc-rma    .slc-header   { background: #0dcaf0; color: #000; }
.slc-rma                  { border: 2px solid #0dcaf0; }
.slc-rma    .slc-badge    { background: rgba(13,202,240,.12); color: #055160; border: 1px solid #0dcaf0; }
/* Loss */
.slc-loss   .slc-header   { background: #dc3545; color: #fff; }
.slc-loss                 { border: 2px solid #dc3545; }
.slc-loss   .slc-badge    { background: rgba(220,53,69,.12); color: #842029; border: 1px solid #dc3545; }
/* qty pill */
.slc-count { font-size: 12px; font-weight: 700; padding: 2px 9px; border-radius: 12px; background: rgba(255,255,255,.25); }
/* dark mode overrides */
@media (prefers-color-scheme: dark) {
    .slc-current .slc-badge { color: #6dffb3 !important; }
    .slc-damaged .slc-badge { color: #ffb76b !important; }
    .slc-rma    .slc-badge  { color: #7eeaf8 !important; }
    .slc-loss   .slc-badge  { color: #ff8a93 !important; }
}
</style>

<!-- Stock Lifecycle Breakdown -->
<div class="row mt-2">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-layer-group me-2"></i>Stock Lifecycle Breakdown</h6>
                <span class="badge bg-secondary">Total: <?= $total_qty ?> units across all states</span>
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <!-- Current Stock -->
                    <div class="col-md-4">
                        <div class="slc-card slc-current">
                            <div class="slc-header">
                                <span><i class="fas fa-check-circle slc-icon"></i>Current Stock</span>
                                <span class="slc-count"><?= count($serials_by_type['current']) ?: $product['stock_quantity'] ?></span>
                            </div>
                            <div class="slc-body">
                                <?php if (!empty($serials_by_type['current'])): ?>
                                    <?php foreach ($serials_by_type['current'] as $sn): ?>
                                        <span class="slc-badge"><?= htmlspecialchars($sn) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <small class="text-muted">Qty: <?= $product['stock_quantity'] ?> <?= $product['unit_short'] ?? '' ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Damaged Stock -->
                    <div class="col-md-4">
                        <div class="slc-card slc-damaged">
                            <div class="slc-header">
                                <span><i class="fas fa-tools slc-icon"></i>Damaged Stock</span>
                                <span class="slc-count"><?= count($serials_by_type['damaged']) ?: $damaged_qty ?></span>
                            </div>
                            <div class="slc-body">
                                <?php if (!empty($serials_by_type['damaged'])): ?>
                                    <?php foreach ($serials_by_type['damaged'] as $sn): ?>
                                        <span class="slc-badge"><?= htmlspecialchars($sn) ?></span>
                                    <?php endforeach; ?>
                                <?php elseif ($damaged_qty > 0): ?>
                                    <small class="text-muted">Qty: <?= $damaged_qty ?> <?= $product['unit_short'] ?? '' ?></small>
                                <?php else: ?>
                                    <small class="text-muted">No damaged stock</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- RMA Stock -->
                    <div class="col-md-4">
                        <div class="slc-card slc-rma">
                            <div class="slc-header">
                                <span><i class="fas fa-undo-alt slc-icon"></i>RMA Stock</span>
                                <span class="slc-count"><?= count($serials_by_type['rma']) ?: $rma_qty ?></span>
                            </div>
                            <div class="slc-body">
                                <?php if (!empty($serials_by_type['rma'])): ?>
                                    <?php foreach ($serials_by_type['rma'] as $sn): ?>
                                        <span class="slc-badge"><?= htmlspecialchars($sn) ?></span>
                                    <?php endforeach; ?>
                                <?php elseif ($rma_qty > 0): ?>
                                    <small class="text-muted">Qty: <?= $rma_qty ?> <?= $product['unit_short'] ?? '' ?></small>
                                <?php else: ?>
                                    <small class="text-muted">No RMA stock</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
                <?php if (!empty($serials_by_type['loss'])): ?>
                <div class="mt-3">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white py-2">
                            <i class="fas fa-times-circle me-1"></i> <strong>Loss / Scrapped</strong>
                            <span class="badge bg-white text-danger float-end border border-danger"><?= count($serials_by_type['loss']) ?></span>
                        </div>
                        <div class="card-body p-2">
                            <?php foreach ($serials_by_type['loss'] as $sn): ?>
                                <span class="badge border border-danger text-danger me-1 mb-1" style="font-size:12px;"><?= htmlspecialchars($sn) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

