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

// Fetch in-stock serial numbers if applicable
$serials = [];
if ($product['has_serial'] === 'Available') {
    $sql_serials = "SELECT serial_number FROM product_serials WHERE product_id = ? AND status = 'in_stock' ORDER BY created_at ASC";
    $serials_data = db_query($sql_serials, [$product_id]);
    foreach ($serials_data as $s) {
        if (!empty($s['serial_number'])) {
            $serials[] = $s['serial_number'];
        }
    }
}

$page_title = 'Product Details';
$page_actions = '<button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print View</button>
                 <a href="product-edit.php?id=' . $product_id . '" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
                 <a href="products-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>';
include __DIR__ . '/../../templates/header.php';
?>

<style>
@media print {
    @page { margin: 0.25cm; size: auto; }
    html, body { 
        margin: 0 !important; 
        padding: 0 !important; 
        background: #fff !important; 
        width: 100% !important; 
        color: #000 !important; 
    }
    .wrapper { width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .content-page, .content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    .container-fluid { padding: 0 !important; }
    .row { margin: 0 !important; display: block !important; }
    .no-print, .btn, .navbar, .sidebar, #accordionSidebar, .topbar, footer, .main-header { display: none !important; }
    .card { border: none !important; box-shadow: none !important; margin: 0 0 20px 0 !important; width: 100% !important; }
    .card-header { display: none !important; }
    .card-body { padding: 0 !important; }
    .badge { border: 1px solid #000 !important; color: #000 !important; background: transparent !important; page-break-inside: avoid; }
    .col-md-8, .col-md-4, .col-md-12 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; display: block !important; }
    table { width: 100% !important; border-collapse: collapse; }
    table th, table td { padding: 8px; border: 1px solid #ddd; }
}
</style>

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
                        <th>Stock Quantity</th>
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
                        <th>RMA Stock</th>
                        <td>
                            <span class="badge bg-info text-dark"><?= $product['rma_quantity'] ?> <?= $product['unit_short'] ?? '' ?></span>
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
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Available Stock Serial Numbers (Total: <?= count($serials) ?>)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($serials)): ?>
                    <p class="text-muted">No serial numbers currently in stock.</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-2" style="gap: 10px;">
                        <?php foreach($serials as $sn): ?>
                            <span class="badge badge-light border border-secondary p-2 text-wrap" style="font-size:14px;"><?= htmlspecialchars($sn) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
