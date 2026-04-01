<?php

/**
 * Products List Page
 * Display all products with search and filter functionality
 */

// ============================================
// 1. INITIALIZATION
// ============================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

// Require login
// Require login
require_login();

// Check if SR
$is_sr = is_sr();

// Check if User can Import/Export CSV
$can_import_export = is_admin() || has_role(get_current_user_id(), 'Manager') || has_role(get_current_user_id(), 'Admin');

// ============================================
// 2. HANDLE DELETE REQUEST
// ============================================
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $product_id = (int)$_POST['delete_id'];

        // Get product details for image deletion
        $product = db_select_one('products', ['id' => $product_id]);

        if (db_delete('products', ['id' => $product_id])) {
            // Delete product image if exists
            if (!empty($product['image'])) {
                delete_file(UPLOAD_PATH . '/products/' . $product['image']);
            }

            log_activity(get_current_user_id(), 'delete_product', "Deleted product: {$product['name']}");
            redirect_with_message($_SERVER['PHP_SELF'], 'Product deleted successfully', 'success');
        } else {
            redirect_with_message($_SERVER['PHP_SELF'], 'Failed to delete product', 'error');
        }
    }
}

// ============================================
// 3. DATA RETRIEVAL
// ============================================

// Get filter parameters
$search = get_param('search', '');
$category_id = get_param('category', '');
$brand_id = get_param('brand', '');
$status = get_param('status', '');

// Build query
$sql = "SELECT p.*, 
        c.name as category_name, 
        b.name as brand_name,
        u.short_name as unit_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN units u ON p.unit_id = u.id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($category_id)) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if (!empty($brand_id)) {
    $sql .= " AND p.brand_id = ?";
    $params[] = $brand_id;
}

if (!empty($status)) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY p.created_at DESC";

$products = db_query($sql, $params);

// Get categories for filter
$categories = db_select('categories', [], '*', 'name ASC');

// Get brands for filter
$brands = db_select('brands', [], '*', 'name ASC');

// ============================================
// 4. FRONTEND HTML
// ============================================
$page_title = 'Products';

$page_actions = '';
if ($can_import_export) {
    $page_actions .= '
    <a href="demo_products.csv" class="btn btn-info me-2"><i class="fas fa-download"></i> Demo CSV</a>
    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-file-import"></i> Import CSV</button>
    <a href="export-products.php" class="btn btn-warning me-2"><i class="fas fa-file-export"></i> Export CSV</a>';
}
$page_actions .= '
    <a href="product-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>';

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
    .card-body { padding: 0 !important; }
}
</style>
';

include __DIR__ . '/../../templates/header.php';

// Include centralized print header
include __DIR__ . '/../../templates/print-header.php';
?>

<!-- Filter Section -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Products</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, Code, Barcode" value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
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
                        <label>Brand</label>
                        <select name="brand" class="form-control select2">
                            <option value="">All Brands</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>" <?= $brand_id == $brand['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($brand['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <a href="products-list.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Products List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="productsTable">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th>Stock</th>
                        <th>RMA Stock</th>
                        <?php if (!$is_sr): ?>
                            <th>Purchase Price</th>
                        <?php endif; ?>
                        <th>Selling Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="<?= BASE_URL ?>/uploads/products/<?= $product['image'] ?>" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: #e9ecef; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product['code']) ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($product['brand_name'] ?? '-') ?></td>
                                <td>
                                    <?php if ($product['stock_quantity'] <= $product['reorder_level']): ?>
                                        <span class="badge bg-danger"><?= $product['stock_quantity'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= $product['stock_quantity'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark"><?= $product['rma_quantity'] ?></span>
                                </td>
                                <?php if (!$is_sr): ?>
                                    <td><?= format_currency($product['purchase_price']) ?></td>
                                <?php endif; ?>
                                <td><?= format_currency($product['selling_price']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $product['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($product['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="product-view.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="product-edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger delete-product" data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($can_import_export): ?>
    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Products from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="import-products.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <div class="form-group mb-3">
                            <label for="csv_file" class="form-label">Select CSV File</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        <div class="alert alert-info small">
                            <strong>Instructions:</strong>
                            <ul class="mb-0">
                                <li>Ensure the file is in CSV format.</li>
                                <li>Columns: Code, Name, Category, Brand, Unit, Purchase Price, Selling Price, Tax Rate, Stock, Reorder Level, Status, Description.</li>
                                <li>Codes must be unique. Existing products will be updated.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="import_csv" class="btn btn-success">Start Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Delete Form (Hidden) -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<?php
ob_start();
?>
<script>
    // Initialize DataTable
    $(document).ready(function() {
        console.log('Products List Script Initialized');

        // Initialize Select2 if available
        if ($.fn.select2) {
            $('.select2').select2({ width: '100%' });
        }

        if ($.fn.DataTable) {
            $('#productsTable').DataTable({
                "pageLength": 25,
                "order": [
                    [1, "desc"]
                ],
                "columnDefs": [{
                    "orderable": false,
                    "targets": [0, 10]
                }]
            });
        }

        // Delete product function with event delegation
        $(document).on('click', '.delete-product', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const name = $(this).data('name');

            console.log('Delete button clicked for product:', name, 'ID:', id);

            if (confirm('Are you sure you want to delete product "' + name + '"?')) {
                const form = document.getElementById('deleteForm');
                const input = document.getElementById('delete_id');
                if (form && input) {
                    input.value = id;
                    console.log('Submitting delete form for ID:', id);
                    form.submit();
                } else {
                    console.error('Delete form or input not found');
                }
            }
        });
    });
</script>
<?php
$additional_js = ob_get_clean();
include __DIR__ . '/../../templates/footer.php';
?>