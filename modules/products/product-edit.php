<?php
/**
 * Product Edit Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$product_id = (int)get_param('id');
$product = db_select_one('products', ['id' => $product_id]);

if (!$product) {
    redirect_with_message('products-list.php', 'Product not found', 'error');
}

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        if (empty($_POST['name'])) $errors[] = 'Product name is required';
        if (empty($_POST['selling_price'])) $errors[] = 'Selling price is required';
        
        if (empty($errors)) {
            $image_filename = $product['image'];
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Use PRODUCT_IMAGE_PATH constant
                $upload_result = upload_image($_FILES['image'], PRODUCT_IMAGE_PATH);
                if ($upload_result['status']) {
                    if (!empty($product['image'])) {
                        delete_file(PRODUCT_IMAGE_PATH . $product['image']);
                    }
                    $image_filename = $upload_result['filename'];
                } else {
                    $errors[] = $upload_result['message'];
                }
            } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                 // Capture other upload errors
                 $errors[] = 'Image upload failed. Error code: ' . $_FILES['image']['error'];
            }
            
            $data = [
                'name' => clean_input($_POST['name']),
                'code' => clean_input($_POST['code']),
                'barcode' => clean_input($_POST['barcode']),
                'brand_id' => !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null,
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'unit_id' => !empty($_POST['unit_id']) ? (int)$_POST['unit_id'] : null,
                'purchase_price' => (float)$_POST['purchase_price'],
                'selling_price' => (float)$_POST['selling_price'],
                'tax_rate' => (float)($_POST['tax_rate'] ?? 0),
                'image' => $image_filename,
                'reorder_level' => (int)($_POST['reorder_level'] ?? 0),
                'description' => clean_input($_POST['description']),
                'status' => $_POST['status'] ?? 'active',
                'has_serial' => $_POST['has_serial'] ?? 'Not Available',
                'warranty_duration' => ($_POST['has_serial'] == 'Available') ? (int)($_POST['warranty_duration'] ?? 0) : 0,
                'warranty_period' => ($_POST['has_serial'] == 'Available') ? ($_POST['warranty_period'] ?? 'Month') : 'Month',
                'is_service_product' => isset($_POST['is_service_product']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (db_update('products', $data, ['id' => $product_id])) {
                log_activity(get_current_user_id(), 'edit_product', "Updated product: {$data['name']}");
                redirect_with_message('products-list.php', 'Product updated successfully', 'success');
            } else {
                $errors[] = 'Failed to update product';
            }
        }
    }
}

$categories = db_select('categories', [], '*', 'name ASC');
$brands = db_select('brands', [], '*', 'name ASC');
$units = db_select('units', [], '*', 'name ASC');

$page_title = 'Edit Product';
include __DIR__ . '/../../templates/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Edit Product Information</h6>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($product['name']) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Product Code</label>
                        <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($product['code']) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Barcode</label>
                        <input type="text" name="barcode" class="form-control" value="<?= htmlspecialchars($product['barcode'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Category</label>
                        <select name="category_id" class="form-control select2">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Brand</label>
                        <select name="brand_id" class="form-control select2">
                            <option value="">Select Brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>" <?= $product['brand_id'] == $brand['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($brand['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Unit</label>
                        <select name="unit_id" class="form-control select2">
                            <option value="">Select Unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= $unit['id'] ?>" <?= $product['unit_id'] == $unit['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($unit['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Purchase Price</label>
                        <input type="number" name="purchase_price" class="form-control" step="0.01" value="<?= $product['purchase_price'] ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Selling Price <span class="text-danger">*</span></label>
                        <input type="number" name="selling_price" class="form-control" step="0.01" required value="<?= $product['selling_price'] ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Tax Rate (%)</label>
                        <input type="number" name="tax_rate" class="form-control" step="0.01" value="<?= $product['tax_rate'] ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Current Stock</label>
                        <input type="text" class="form-control" value="<?= $product['stock_quantity'] ?>" readonly>
                        <small class="text-muted">Use Stock Adjustment to modify</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-control" value="<?= $product['reorder_level'] ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Product Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?= BASE_URL ?>/uploads/products/<?= $product['image'] ?>" alt="Current" class="mt-2" style="max-width: 100px;">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Serial & Warranty Section -->
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label><strong>Serial Tracking</strong></label>
                        <select name="has_serial" id="has_serial" class="form-control">
                            <option value="Not Available" <?= $product['has_serial'] === 'Not Available' ? 'selected' : '' ?>>Not Available</option>
                            <option value="Available" <?= $product['has_serial'] === 'Available' ? 'selected' : '' ?>>Available</option>
                        </select>
                        <small class="text-muted">Track serial numbers for this product</small>
                    </div>
                </div>
                <div class="col-md-4" id="warranty_duration_container" style="display: <?= $product['has_serial'] === 'Available' ? 'block' : 'none' ?>;">
                    <div class="form-group mb-3">
                        <label><strong>Warranty Duration</strong></label>
                        <input type="number" name="warranty_duration" id="warranty_duration" class="form-control" 
                            min="0" value="<?= htmlspecialchars($product['warranty_duration'] ?? '0') ?>">
                        <small class="text-muted">Enter warranty duration</small>
                    </div>
                </div>
                <div class="col-md-4" id="warranty_period_container" style="display: <?= $product['has_serial'] === 'Available' ? 'block' : 'none' ?>;">
                    <div class="form-group mb-3">
                        <label><strong>Warranty Period</strong></label>
                        <select name="warranty_period" id="warranty_period" class="form-control">
                            <option value="Days" <?= ($product['warranty_period'] ?? '') === 'Days' ? 'selected' : '' ?>>Days</option>
                            <option value="Month" <?= ($product['warranty_period'] ?? 'Month') === 'Month' ? 'selected' : '' ?>>Month</option>
                            <option value="Year" <?= ($product['warranty_period'] ?? '') === 'Year' ? 'selected' : '' ?>>Year</option>
                        </select>
                        <small class="text-muted">Select period type</small>
                    </div>
                </div>
            </div>
            
            <!-- Paid Service Product -->
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_service_product" id="is_service_product" value="1"
                                <?= (!empty($product['is_service_product'])) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_service_product">
                                <i class="fas fa-tools text-info"></i> <strong>Paid Service Product</strong>
                                <small class="text-muted d-block">Enable if this product is used as a service part/component</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Product</button>
                    <a href="products-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for conditional warranty fields -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const hasSerialSelect = document.getElementById('has_serial');
    const warrantyDurationContainer = document.getElementById('warranty_duration_container');
    const warrantyPeriodContainer = document.getElementById('warranty_period_container');
    const warrantyDuration = document.getElementById('warranty_duration');

    function toggleWarrantyFields() {
        if (hasSerialSelect.value === 'Available') {
            warrantyDurationContainer.style.display = 'block';
            warrantyPeriodContainer.style.display = 'block';
        } else {
            warrantyDurationContainer.style.display = 'none';
            warrantyPeriodContainer.style.display = 'none';
            warrantyDuration.value = '0';
        }
    }

    // Toggle on change
    hasSerialSelect.addEventListener('change', toggleWarrantyFields);
    
    // Initialize Select2 if available
    if (typeof jQuery !== 'undefined' && $.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Select an option'
        });
    }
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
