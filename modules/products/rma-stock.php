<?php
/**
 * RMA Stock Management
 * Track and manage products in RMA (Return Merchandise Authorization) stock
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$error = '';
$success = '';

// Get RMA stock statistics
$stats_sql = "SELECT 
                COUNT(DISTINCT sti.product_id) as product_count,
                SUM(sti.quantity) as total_quantity
              FROM stock_type_inventory sti
              WHERE sti.stock_type = 'rma' AND sti.quantity > 0";
$stats_result = db_query_one($stats_sql);

$stats = [
    'product_count' => $stats_result['product_count'] ?? 0,
    'total_quantity' => $stats_result['total_quantity'] ?? 0
];

// Get products with RMA stock
$sql = "SELECT 
            p.id,
            p.name as product_name,
            p.code as product_code,
            p.image,
            b.name as brand_name,
            c.name as category_name,
            SUM(sti.quantity) as rma_quantity,
            GROUP_CONCAT(DISTINCT w.name SEPARATOR ', ') as warehouses
        FROM stock_type_inventory sti
        INNER JOIN products p ON sti.product_id = p.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN warehouses w ON sti.warehouse_id = w.id
        WHERE sti.stock_type = 'rma' AND sti.quantity > 0
        GROUP BY p.id, p.name, p.code, p.image, b.name, c.name
        ORDER BY p.name ASC";

$rma_products = db_query($sql);

// Get count of serial numbers in RMA
$serial_count_sql = "SELECT COUNT(*) as count 
                     FROM product_serials 
                     WHERE stock_type = 'rma' AND status = 'in_stock'";
$serial_count = db_query_one($serial_count_sql)['count'] ?? 0;

$page_title = 'RMA Stock Management';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <!-- Statistics Cards -->
    <div class="col-md-4">
        <div class="card border-left-info shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total RMA Products
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= $stats['product_count'] ?> Products
                        </div>
                        <div class="text-xs text-muted mt-1">
                            Unique products in RMA stock
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-undo fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-left-primary shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total RMA Quantity
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= number_format($stats['total_quantity'], 2) ?> Units
                        </div>
                        <div class="text-xs text-muted mt-1">
                            Total units across all products
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-boxes fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-left-success shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Serialized Items
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= $serial_count ?> Serials
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <a href="serial-imei-list.php?stock_type=rma">View all RMA serials</a>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-barcode fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- RMA Products List -->
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">RMA Stock Inventory</h6>
                <div>
                    <a href="../stock-transfer/transfer-create.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-exchange-alt"></i> Transfer Stock
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>RMA Stock:</strong> Products returned from customers for warranty claims, repairs, or replacements. 
                    Use stock transfers to move items between Current Stock ↔ RMA ↔ Damaged stock types.
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="rmaTable">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Product Code</th>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>RMA Qty</th>
                                <th>Warehouses</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rma_products)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No products in RMA stock</p>
                                            <small>Products will appear here when transferred to RMA stock type</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rma_products as $product): ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if ($product['image']): ?>
                                                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($product['image']) ?>" 
                                                     alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                                     style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="product-view.php?id=<?= $product['id'] ?>">
                                                <?= htmlspecialchars($product['product_name']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($product['product_code']) ?></td>
                                        <td><?= htmlspecialchars($product['brand_name'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= number_format($product['rma_quantity'], 2) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($product['warehouses'] ?? 'Default') ?></td>
                                        <td>
                                            <a href="product-view.php?id=<?= $product['id'] ?>" 
                                               class="btn btn-sm btn-info" 
                                               title="View Product">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../stock-transfer/transfer-create.php?product_id=<?= $product['id'] ?>" 
                                               class="btn btn-sm btn-primary" 
                                               title="Transfer Stock">
                                                <i class="fas fa-exchange-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    // Cache buster: <?= time() ?>
    $(document).ready(function () {
        $('#rmaTable').DataTable({
            "destroy": true,
            "pageLength": 25,
            "order": [[1, "asc"]], // Sort by product name
            "columns": [
                { "orderable": false },  // Image
                { "orderable": true },   // Product Name
                { "orderable": true },   // Product Code
                { "orderable": true },   // Brand
                { "orderable": true },   // Category
                { "orderable": true },   // RMA Qty
                { "orderable": true },   // Warehouses
                { "orderable": false }   // Actions
            ]
        });
    });
</script>
