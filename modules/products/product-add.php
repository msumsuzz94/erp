<?php

/**
 * Add Product Page
 * Create new product with image upload
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
require_login();

// ============================================
// 2. HANDLE FORM SUBMISSION
// ============================================
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];

        // Validate required fields
        if (empty($_POST['name']))
            $errors[] = 'Product name is required';
        if (empty($_POST['selling_price']))
            $errors[] = 'Selling price is required';

        if (empty($errors)) {
            // Handle image upload
            // Handle image upload
            $image_filename = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Use PRODUCT_IMAGE_PATH constant
                $upload_result = upload_image($_FILES['image'], PRODUCT_IMAGE_PATH);
                if ($upload_result['status']) {
                    $image_filename = $upload_result['filename'];
                } else {
                    $errors[] = $upload_result['message'];
                }
            } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Capture other upload errors (like size limit)
                $errors[] = 'Image upload failed. Error code: ' . $_FILES['image']['error'];
            }

            if (empty($errors)) {
                // Generate product code if not provided
                $code = !empty($_POST['code']) ? $_POST['code'] : 'PRD' . time();

                // Prepare data
                $data = [
                    'name' => clean_input($_POST['name']),
                    'code' => $code,
                    'barcode' => clean_input($_POST['barcode']),
                    'brand_id' => !empty($_POST['brand_id']) ? (int) $_POST['brand_id'] : null,
                    'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
                    'unit_id' => !empty($_POST['unit_id']) ? (int) $_POST['unit_id'] : null,
                    'purchase_price' => (float) $_POST['purchase_price'],
                    'selling_price' => (float) $_POST['selling_price'],
                    'tax_rate' => (float) ($_POST['tax_rate'] ?? 0),
                    'image' => $image_filename,
                    'stock_quantity' => (int) ($_POST['stock_quantity'] ?? 0),
                    'reorder_level' => (int) ($_POST['reorder_level'] ?? 0),
                    'description' => clean_input($_POST['description']),
                    'status' => $_POST['status'] ?? 'active',
                    'is_service_product' => isset($_POST['is_service_product']) ? 1 : 0,
                    'has_serial' => $_POST['has_serial'] ?? 'Not Available',
                    'warranty_duration' => ($_POST['has_serial'] == 'Available') ? (int) ($_POST['warranty_duration'] ?? 0) : 0,
                    'warranty_period' => ($_POST['has_serial'] == 'Available') ? ($_POST['warranty_period'] ?? 'Month') : 'Month',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $product_id = db_insert('products', $data);

                if ($product_id) {
                    // Handle Serials if Available
                    $serial_inserted_count = 0;
                    if ($data['has_serial'] === 'Available' && !empty($_POST['serial_numbers'])) {
                        $serials = explode("\n", $_POST['serial_numbers']);
                        foreach ($serials as $serial) {
                            $serial = trim($serial);
                            if (!empty($serial)) {
                                $serial_data = [
                                    'product_id' => $product_id,
                                    'serial_number' => $serial,
                                    'status' => 'in_stock',
                                    'serial_status' => 'in_stock',
                                    'created_at' => date('Y-m-d H:i:s')
                                ];
                                db_insert('product_serials', $serial_data);
                                $serial_inserted_count++;
                            }
                        }

                        // Optionally update product stock quantity to match exact serials inserted just to be safe
                        if ($serial_inserted_count > 0 && $serial_inserted_count != $data['stock_quantity']) {
                            db_update('products', ['stock_quantity' => $serial_inserted_count], ['id' => $product_id]);
                        }
                    }

                    log_activity(get_current_user_id(), 'add_product', "Added product: {$data['name']}");
                    redirect_with_message('products-list.php', 'Product added successfully', 'success');
                } else {
                    $errors[] = 'Failed to add product';
                }
            }
        }
    } else {
        $errors[] = 'Invalid CSRF token';
    }
}

// ============================================
// 3. GET DROPDOWN DATA
// ============================================
$categories = db_select('categories', [], '*', 'name ASC');
$brands = db_select('brands', [], '*', 'name ASC');
$units = db_select('units', [], '*', 'name ASC');

// ============================================
// 4. FRONTEND HTML
// ============================================
$page_title = 'Add Product';
include __DIR__ . '/../../templates/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Product Information</h6>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Product Code</label>
                        <input type="text" name="code" class="form-control" placeholder="Auto-generated if empty"
                            value="<?= htmlspecialchars($_POST['code'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Barcode</label>
                        <input type="text" name="barcode" class="form-control"
                            value="<?= htmlspecialchars($_POST['barcode'] ?? '') ?>">
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
                                <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small><a href="category-add.php" target="_blank">+ Add New Category</a></small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Brand</label>
                        <select name="brand_id" class="form-control select2">
                            <option value="">Select Brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>" <?= (isset($_POST['brand_id']) && $_POST['brand_id'] == $brand['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($brand['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small><a href="brand-add.php" target="_blank">+ Add New Brand</a></small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Unit</label>
                        <select name="unit_id" class="form-control select2">
                            <option value="">Select Unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= $unit['id'] ?>" <?= (isset($_POST['unit_id']) && $_POST['unit_id'] == $unit['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($unit['name']) ?> (<?= htmlspecialchars($unit['short_name']) ?>)
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
                        <input type="number" name="purchase_price" class="form-control" step="0.01" min="0"
                            value="<?= htmlspecialchars($_POST['purchase_price'] ?? '0') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Selling Price <span class="text-danger">*</span></label>
                        <input type="number" name="selling_price" class="form-control" step="0.01" min="0" required
                            value="<?= htmlspecialchars($_POST['selling_price'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Tax Rate (%)</label>
                        <input type="number" name="tax_rate" class="form-control" step="0.01" min="0" max="100"
                            value="<?= htmlspecialchars($_POST['tax_rate'] ?? '0') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-3">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= (isset($_POST['status']) && $_POST['status'] === 'active') ? 'selected' : 'selected' ?>>Active</option>
                            <option value="inactive" <?= (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label><strong>Opening Stock Quantity</strong></label>
                        <input type="number" name="stock_quantity" id="stock_quantity" class="form-control" min="0"
                            value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '0') ?>">
                        <small class="text-muted">Initial stock when adding product</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-control" min="0"
                            value="<?= htmlspecialchars($_POST['reorder_level'] ?? '10') ?>">
                        <small class="text-muted">Alert when stock reaches this level</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Product Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Max size: 2MB</small>
                    </div>
                </div>
            </div>

            <!-- Paid Service Product -->
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_service_product" id="is_service_product" value="1" <?= !empty($_POST['is_service_product']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="is_service_product">
                                <i class="fas fa-tools text-info"></i> Paid Service Product
                            </label>
                        </div>
                        <small class="text-muted">Check this if this product is used as a service part/component in Paid Service tickets</small>
                    </div>
                </div>
            </div>

            <!-- Serial & Warranty Section -->
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label><strong>Serial Tracking</strong></label>
                        <select name="has_serial" id="has_serial" class="form-control">
                            <option value="Not Available" <?= (isset($_POST['has_serial']) && $_POST['has_serial'] === 'Not Available') ? 'selected' : 'selected' ?>>Not Available</option>
                            <option value="Available" <?= (isset($_POST['has_serial']) && $_POST['has_serial'] === 'Available') ? 'selected' : '' ?>>Available</option>
                        </select>
                        <small class="text-muted">Track serial numbers for this product</small>
                    </div>
                </div>
                <div class="col-md-4" id="warranty_duration_container" style="display: none;">
                    <div class="form-group mb-3">
                        <label><strong>Warranty Duration</strong></label>
                        <input type="number" name="warranty_duration" id="warranty_duration" class="form-control"
                            min="0" value="<?= htmlspecialchars($_POST['warranty_duration'] ?? '0') ?>">
                        <small class="text-muted">Enter warranty duration</small>
                    </div>
                </div>
                <div class="col-md-4" id="warranty_period_container" style="display: none;">
                    <div class="form-group mb-3">
                        <label><strong>Warranty Period</strong></label>
                        <select name="warranty_period" id="warranty_period" class="form-control">
                            <option value="Days" <?= (isset($_POST['warranty_period']) && $_POST['warranty_period'] === 'Days') ? 'selected' : '' ?>>Days</option>
                            <option value="Month" <?= (isset($_POST['warranty_period']) && $_POST['warranty_period'] === 'Month') ? 'selected' : 'selected' ?>>Month</option>
                            <option value="Year" <?= (isset($_POST['warranty_period']) && $_POST['warranty_period'] === 'Year') ? 'selected' : '' ?>>Year</option>
                        </select>
                        <small class="text-muted">Select period type</small>
                    </div>
                </div>
                <div class="col-md-12" id="serial_numbers_container" style="display: none;">
                    <div class="form-group mb-3">
                        <label><strong>Serial Numbers</strong> <span class="text-danger">*</span></label>
                        <textarea name="serial_numbers" id="serial_numbers" class="form-control" rows="4" placeholder="Enter one serial number per line..."><?= htmlspecialchars($_POST['serial_numbers'] ?? '') ?></textarea>
                        <small class="text-muted">Enter serial numbers exactly as they appear on the products. The <strong>Opening Stock Quantity</strong> will be automatically calculated based on the number of non-empty lines.</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control"
                            rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Product</button>
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
        const warrantyPeriod = document.getElementById('warranty_period');
        const serialNumbersContainer = document.getElementById('serial_numbers_container');
        const serialNumbersInput = document.getElementById('serial_numbers');
        const stockQuantityInput = document.getElementById('stock_quantity');

        function toggleWarrantyFields() {
            if (hasSerialSelect.value === 'Available') {
                warrantyDurationContainer.style.display = 'block';
                warrantyPeriodContainer.style.display = 'block';
                serialNumbersContainer.style.display = 'block';
                warrantyDuration.required = false;
                stockQuantityInput.readOnly = true;
                updateStockQuantityFromSerials();
            } else {
                warrantyDurationContainer.style.display = 'none';
                warrantyPeriodContainer.style.display = 'none';
                serialNumbersContainer.style.display = 'none';
                warrantyDuration.value = '0';
                warrantyDuration.required = false;
                stockQuantityInput.readOnly = false;
            }
        }

        function updateStockQuantityFromSerials() {
            if (hasSerialSelect.value === 'Available') {
                const serialsText = serialNumbersInput.value;
                const lines = serialsText.split('\n');
                let count = 0;
                for (let i = 0; i < lines.length; i++) {
                    if (lines[i].trim() !== '') {
                        count++;
                    }
                }
                stockQuantityInput.value = count;
            }
        }

        // Toggle on page load
        toggleWarrantyFields();

        // Toggle on change
        hasSerialSelect.addEventListener('change', toggleWarrantyFields);
        serialNumbersInput.addEventListener('input', updateStockQuantityFromSerials);

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