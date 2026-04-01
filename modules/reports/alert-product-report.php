<?php
/**
 * Alert Product Report - Low stock items
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$sql = "SELECT p.*, c.name as category_name, b.name as brand_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.stock_quantity <= p.reorder_level AND p.status = 'active'
        ORDER BY p.stock_quantity ASC";
$alert_products = db_query($sql);

$page_title = 'Alert Product Report';
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
        font-family: 'Segoe UI', Arial, sans-serif; 
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

<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> <strong><?= count($alert_products) ?></strong> products need restocking!
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-warning">Low Stock Products</h6></div>
    <div class="card-body">
        <table class="table table-bordered table-hover" id="alertTable">
            <thead>
                <tr><th>Code</th><th>Product</th><th>Category</th><th>Brand</th><th>Current Stock</th><th>Reorder Level</th></tr>
            </thead>
            <tbody>
                <?php foreach ($alert_products as $prod): ?>
                    <tr class="<?= $prod['stock_quantity'] == 0 ? 'table-danger' : 'table-warning' ?>">
                        <td><?= htmlspecialchars($prod['code']) ?></td>
                        <td><strong><?= htmlspecialchars($prod['name']) ?></strong></td>
                        <td><?= htmlspecialchars($prod['category_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($prod['brand_name'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= $prod['stock_quantity'] == 0 ? 'danger' : 'warning' ?>"><?= $prod['stock_quantity'] ?></span></td>
                        <td><?= $prod['reorder_level'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
<script>$('#alertTable').DataTable();</script>
