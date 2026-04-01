<?php
/**
 * Edit RMA Claim Details
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$id = (int)get_param('id');
if (!$id) {
    redirect_with_message('rma-list.php', 'Invalid RMA ID', 'danger');
}

// Fetch RMA Details
$sql = "SELECT r.*, c.name as customer_name, p.name as product_name, p.code as product_code, ps.serial_number
        FROM rma_requests r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN products p ON r.product_id = p.id
        LEFT JOIN product_serials ps ON r.serial_id = ps.id
        WHERE r.id = ?";
$rma = db_query_one($sql, [$id]);

if (!$rma) {
    redirect_with_message('rma-list.php', 'RMA Claim not found', 'danger');
}

// Fetch External Replacement if exists
$ext_repl = db_query_one("SELECT * FROM rma_external_replacements WHERE rma_id = ?", [$id]);

$errors = [];
$success = false;

// Handle Form Submission
if (is_post() && isset($_POST['update_rma'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $status = clean_input($_POST['status']);
        $service_center_id = !empty($_POST['service_center_id']) ? (int)$_POST['service_center_id'] : null;
        $problem_description = clean_input($_POST['problem_description']);
        $notes = clean_input($_POST['notes']);
        
        $testing_date = !empty($_POST['testing_date']) ? $_POST['testing_date'] : null;
        $testing_note = clean_input($_POST['testing_note']);
        
        $replace_out_number = clean_input($_POST['replace_out_number']);
        $replace_out_date = !empty($_POST['replace_out_date']) ? $_POST['replace_out_date'] : null;
        
        $replace_in_number = clean_input($_POST['replace_in_number']);
        $replace_in_date = !empty($_POST['replace_in_date']) ? $_POST['replace_in_date'] : null;
        $replace_in_type = $_POST['replace_in_type'] ?? 'repair';
        $new_serial_id = !empty($_POST['new_serial_id']) ? (int)$_POST['new_serial_id'] : null;
        
        $delivery_number = clean_input($_POST['delivery_number']);
        $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
        
        // New: Replacement product fields
        $replacement_product_id = !empty($_POST['replacement_product_id']) ? (int)$_POST['replacement_product_id'] : null;
        $replacement_serial_number = clean_input($_POST['replacement_serial_number']);
        
        // External replacement fields
        $is_external = isset($_POST['is_external_replacement']) && $_POST['is_external_replacement'] == '1';
        $ext_product_name = clean_input($_POST['ext_product_name']);
        $ext_brand_name = clean_input($_POST['ext_brand_name']);

        if (empty($problem_description)) $errors[] = "Problem description is required.";

        if (empty($errors)) {
            try {
                dbBeginTransaction();

                // Handle external replacement product creation BEFORE main update
                if ($replace_in_type === 'new' && $is_external && !empty($ext_product_name)) {
                    // Check if a product with this name already exists
                    $existing_product = db_query_one(
                        "SELECT id FROM products WHERE name = ? LIMIT 1",
                        [$ext_product_name]
                    );
                    
                    if (!$existing_product) {
                        // Create a new product with zero stock
                        $product_code = 'EXT-' . strtoupper(substr(md5($ext_product_name . time()), 0, 6));
                        $replacement_product_id = db_insert('products', [
                            'name' => $ext_product_name,
                            'code' => $product_code,
                            'description' => 'External Brand Replacement' . ($ext_brand_name ? " - Brand: $ext_brand_name" : ''),
                            'stock_quantity' => 0,
                            'reorder_level' => 0,
                            'purchase_price' => 0,
                            'selling_price' => 0,
                            'status' => 'active',
                            'has_serial' => 'Available',
                            'created_by' => get_current_user_id(),
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $replacement_product_id = $existing_product['id'];
                    }
                }

               $data = [
                    'status' => $status,
                    'service_center_id' => $service_center_id,
                    'problem_description' => $problem_description,
                    'notes' => $notes,
                    'testing_date' => $testing_date,
                    'testing_note' => $testing_note,
                    'replace_out_number' => $replace_out_number,
                    'replace_out_date' => $replace_out_date,
                    'replace_in_number' => $replace_in_number,
                    'replace_in_date' => $replace_in_date,
                    'replace_in_type' => $replace_in_type,
                    'new_serial_id' => $new_serial_id,
                    // NOTE: replacement_product_id removed - column doesn't exist in rma_requests table
                    // NOTE: replacement_serial_number removed - column doesn't exist in rma_requests table
                    'delivery_number' => $delivery_number,
                    'delivery_date' => $delivery_date,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                // Logic for New Serial Replacement
                if ($replace_in_type === 'new' && $delivery_date) {
                    // Automatic stock transfer upon delivery completion
                    if ($replacement_product_id && $replacement_serial_number) {
                        // Find the serial in product_serials
                        $serial = db_query_one(
                            "SELECT id, status, stock_type FROM product_serials WHERE serial_number = ? AND product_id = ?",
                            [$replacement_serial_number, $replacement_product_id]
                        );
                        
                        if ($serial) {
                            // Check if serial is in current stock
                            if ($serial['status'] === 'in_stock' && (!isset($serial['stock_type']) || $serial['stock_type'] === 'current')) {
                                // Transfer to RMA stock
                                db_update('product_serials', [
                                    'stock_type' => 'rma',
                                    'serial_status' => 'rma'
                                ], ['id' => $serial['id']]);
                                
                                // Update stock quantities
                                db_query(
                                    "UPDATE products SET stock_quantity = stock_quantity - 1 WHERE id = ?",
                                    [$replacement_product_id]
                                );
                                
                                // Add to RMA stock inventory
                                $existing_rma = db_query_one(
                                    "SELECT id, quantity FROM stock_type_inventory WHERE product_id = ? AND stock_type = 'rma'",
                                    [$replacement_product_id]
                                );
                                
                                if ($existing_rma) {
                                    db_query(
                                        "UPDATE stock_type_inventory SET quantity = quantity + 1 WHERE id = ?",
                                        [$existing_rma['id']]
                                    );
                                } else {
                                    db_insert('stock_type_inventory', [
                                        'product_id' => $replacement_product_id,
                                        'stock_type' => 'rma',
                                        'quantity' => 1
                                    ]);
                                }
                            }
                        }
                    }
                }

                if (db_update('rma_requests', $data, ['id' => $id])) {
                    // Handle replacement product tracking - Save ALL replacement products (external AND inventory)
                    if ($replace_in_type === 'new' && !empty($replacement_serial_number)) {
                        // Clear any previous replacements for this RMA
                        db_query("DELETE FROM rma_external_replacements WHERE rma_id = ?", [$id]);
                        
                        $product_name_to_save = '';
                        $brand_name_to_save = '';
                        
                        if ($is_external && !empty($ext_product_name)) {
                            // External brand replacement - use manually entered product name
                            $product_name_to_save = $ext_product_name;
                            $brand_name_to_save = $ext_brand_name;
                        } elseif ($replacement_product_id) {
                            // Inventory product - fetch product name from database
                            $product_info = db_select_one('products', ['id' => $replacement_product_id]);
                            if ($product_info) {
                                $product_name_to_save = $product_info['name'];
                                // Get brand name if available
                                if (!empty($product_info['brand_id'])) {
                                    $brand_info = db_select_one('brands', ['id' => $product_info['brand_id']]);
                                    $brand_name_to_save = $brand_info ? $brand_info['name'] : '';
                                }
                            }
                        }
                        
                        // Save replacement product record if we have a product name
                        if (!empty($product_name_to_save)) {
                            db_insert('rma_external_replacements', [
                                'rma_id' => $id,
                                'product_name' => $product_name_to_save,
                                'serial_number' => $replacement_serial_number,
                                'brand_name' => $brand_name_to_save
                            ]);
                        }
                    }

                    log_activity(get_current_user_id(), 'rma_update', "Updated RMA Claim #{$rma['rma_number']}");
                    dbCommit();
                    redirect_with_message("rma-view.php?id=$id", "RMA updated successfully.", 'success');
                } else {
                    // Get PDO error info from global connection
                    global $conn;
                    $error_info = $conn ? $conn->errorInfo() : null;
                    $error_msg = "Failed to update RMA record.";
                    if ($error_info && isset($error_info[2])) {
                        $error_msg .= " SQL Error: " . $error_info[2];
                    }
                    throw new Exception($error_msg);
                }
            } catch (Exception $e) {
                dbRollback();
                $errors[] = "Error: " . $e->getMessage();
            }
        }
    } else {
        $errors[] = "Invalid CSRF token.";
    }
}

// Fetch service centers
$service_centers = db_select('service_centers', [], '*', 'name ASC');

$page_title = 'Edit RMA: ' . $rma['rma_number'];
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Advanced Lifecycle Tracking - claim #<?= $rma['rma_number'] ?></h6>
                <a href="rma-view.php?id=<?= $id ?>" class="btn btn-sm btn-secondary">Cancel</a>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="update_rma" value="1">

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold">Current Status</label>
                            <select name="status" class="form-control" required>
                                <option value="pending" <?= $rma['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="sent" <?= $rma['status'] == 'sent' ? 'selected' : '' ?>>Sent to Service Center</option>
                                <option value="solved" <?= $rma['status'] == 'solved' ? 'selected' : '' ?>>Solved / Repaired</option>
                                <option value="delivered" <?= $rma['status'] == 'delivered' ? 'selected' : '' ?>>Delivered to Customer</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label font-weight-bold">Product Information</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($rma['product_name'] ?? '') ?> (<?= htmlspecialchars($rma['serial_number'] ?? 'No Serial') ?>)" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Left Column: Stages -->
                        <div class="col-md-6 border-right">
                            <h5 class="text-primary border-bottom pb-2">Internal & Brand Stages</h5>
                            
                            <!-- Testing Stage -->
                            <div class="p-3 bg-light border rounded mb-3">
                                <h6 class="font-weight-bold">1. Product Testing</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="small mb-1">Testing Date</label>
                                        <input type="date" name="testing_date" class="form-control form-control-sm" value="<?= $rma['testing_date'] ?>">
                                    </div>
                                    <div class="col-md-12 mt-2">
                                        <label class="small mb-1">Testing Notes</label>
                                        <textarea name="testing_note" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($rma['testing_note'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Replace Out Stage -->
                            <div class="p-3 bg-light border rounded mb-3">
                                <h6 class="font-weight-bold">2. Replace Product Out (To Brand)</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="small mb-1">Replace Out No</label>
                                        <input type="text" name="replace_out_number" class="form-control form-control-sm" value="<?= htmlspecialchars($rma['replace_out_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small mb-1">Date</label>
                                        <input type="date" name="replace_out_date" class="form-control form-control-sm" value="<?= $rma['replace_out_date'] ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="small mb-1">Service Center</label>
                                        <select name="service_center_id" class="form-control form-control-sm">
                                            <option value="">-- Internal / None --</option>
                                            <?php foreach ($service_centers as $sc): ?>
                                                <option value="<?= $sc['id'] ?>" <?= $rma['service_center_id'] == $sc['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($sc['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Receiving & Delivery -->
                        <div class="col-md-6">
                            <h5 class="text-success border-bottom pb-2">Resolution & Delivery</h5>

                            <!-- Replace In Stage -->
                            <div class="p-3 bg-light border rounded mb-3">
                                <h6 class="font-weight-bold">3. Replace Product In (From Brand)</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="small mb-1">Replace In No</label>
                                        <input type="text" name="replace_in_number" class="form-control form-control-sm" value="<?= htmlspecialchars($rma['replace_in_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small mb-1">Date</label>
                                        <input type="date" name="replace_in_date" class="form-control form-control-sm" value="<?= $rma['replace_in_date'] ?>">
                                    </div>
                                    <div class="col-md-12 mb-2">
                                        <label class="small mb-1 d-block">Replacement Type</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="replace_in_type" id="type_repair" value="repair" <?= $rma['replace_in_type'] != 'new' ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="type_repair">Repaired Item</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="replace_in_type" id="type_new" value="new" <?= $rma['replace_in_type'] == 'new' ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="type_new">New / Replacement</label>
                                        </div>
                                    </div>
                                    
                                    <div id="new_serial_section" class="col-md-12" style="<?= $rma['replace_in_type'] == 'new' ? '' : 'display:none;' ?>">
                                        <label class="small mb-1">Select Replacement Product/Serial</label>
                                        
                                        <!-- Hidden fields for form submission -->
                                        <input type="hidden" name="replacement_product_id" id="replacement_product_id" value="<?= $rma['replacement_product_id'] ?? '' ?>">
                                        <input type="hidden" name="replacement_serial_number" id="replacement_serial_number" value="<?= htmlspecialchars($rma['replacement_serial_number'] ?? '') ?>">
                                        <input type="hidden" name="new_serial_id" id="new_serial_id" value="<?= $rma['new_serial_id'] ?? '' ?>">
                                        
                                        <!-- External replacement hidden info -->
                                        <input type="hidden" name="is_external_replacement" id="is_external_replacement" value="0">
                                        <input type="hidden" name="ext_product_name" id="ext_product_name" value="">
                                        <input type="hidden" name="ext_brand_name" id="ext_brand_name" value="">
                                        
                                        <!-- Search input with scan support -->
                                        <div class="input-group mb-2">
                                            <input type="text" id="product_search_input" class="form-control form-control-sm" 
                                                   placeholder="Scan or type product name, code, or serial number..." 
                                                   value="<?= htmlspecialchars($rma['replacement_serial_number'] ?? '') ?>">
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="clearSelection()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Selected product display -->
                                        <div id="selected_product_display" style="<?= !empty($rma['replacement_serial_number']) ? '' : 'display:none;' ?>" class="alert alert-success py-2">
                                            <strong>Selected:</strong> <span id="selected_product_name">
                                                <?php 
                                                if (!empty($rma['replacement_product_id'])) {
                                                    $repl_prod = db_select_one('products', ['id' => $rma['replacement_product_id']]);
                                                    echo htmlspecialchars($repl_prod['name'] ?? 'Unknown');
                                                } elseif ($ext_repl) {
                                                    echo htmlspecialchars($ext_repl['product_name'] . ($ext_repl['brand_name'] ? " ({$ext_repl['brand_name']})" : ""));
                                                }
                                                ?>
                                            </span>
                                            <br><small>Serial: <span id="selected_serial_display"><?= htmlspecialchars($rma['replacement_serial_number'] ?? '') ?></span></small>
                                            <br><small>Stock: <span id="selected_stock_status" class="badge bg-<?= (!empty($rma['replacement_product_id']) ? 'info' : ($ext_repl ? 'secondary' : 'danger')) ?>">
                                                <?= (!empty($rma['replacement_product_id']) ? 'Inventory Stock' : ($ext_repl ? 'External Replacement' : 'N/A')) ?>
                                            </span></small>
                                        </div>
                                        
                                        <!-- Search results dropdown -->
                                        <div id="search_results" class="list-group" style="display:none; max-height: 200px; overflow-y: auto;"></div>
                                        
                                        <small class="text-info d-block mt-1">You can select ANY product, not just the same as the original.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Delivery Stage -->
                            <div class="p-3 bg-light border rounded mb-3">
                                <h6 class="font-weight-bold">4. Product Delivery (To Customer)</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="small mb-1">Delivery No</label>
                                        <input type="text" name="delivery_number" class="form-control form-control-sm" value="<?= htmlspecialchars($rma['delivery_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small mb-1">Date</label>
                                        <input type="date" name="delivery_date" class="form-control form-control-sm" value="<?= $rma['delivery_date'] ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold">Problem Description <span class="text-danger">*</span></label>
                            <textarea name="problem_description" class="form-control" rows="3" required><?= htmlspecialchars($rma['problem_description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold">Internal Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($rma['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="text-end border-top pt-3">
                        <button type="submit" class="btn btn-primary px-5">Save Advanced Tracking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- External Product Modal -->
<div class="modal fade" id="externalProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add External Brand Replacement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Product Name / Model <span class="text-danger">*</span></label>
                    <input type="text" id="modal_ext_name" class="form-control" placeholder="e.g. Logitech G502 Hero">
                </div>
                <div class="mb-3">
                    <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                    <input type="text" id="modal_ext_serial" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Brand Name</label>
                    <input type="text" id="modal_ext_brand" class="form-control" placeholder="e.g. Logitech">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmExternalProduct()">Apply Replacement</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Function to generate a random reference number
    function generateReferenceNumber(prefix) {
        const date = new Date();
        const year = date.getFullYear().toString().substr(-2);
        const month = ('0' + (date.getMonth() + 1)).slice(-2);
        const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
        return `${prefix}-${year}${month}-${random}`;
    }
    
    // Auto-fill reference numbers if empty
    const replaceOutField = $('input[name="replace_out_number"]');
    const replaceInField = $('input[name="replace_in_number"]');
    const deliveryField = $('input[name="delivery_number"]');
    
    if (replaceOutField.val().trim() === '') {
        replaceOutField.val(generateReferenceNumber('ROUT'));
    }
    
    if (replaceInField.val().trim() === '') {
        replaceInField.val(generateReferenceNumber('RIN'));
    }
    
    if (deliveryField.val().trim() === '') {
        deliveryField.val(generateReferenceNumber('DEL'));
    }
    
    let searchTimeout;
    
    // Universal product search with debouncing
    $('#product_search_input').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val().trim();
        
        if (query.length < 2) {
            $('#search_results').hide().empty();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            $.get('../../api/products/universal-search.php', { search: query }, function(response) {
                if (response.status && response.data.length > 0) {
                    displaySearchResults(response.data);
                } else {
                    // Show "No products found" AND the "Add External Product" option
                    const currentSearch = $('#product_search_input').val().trim();
                    let html = '<div class="list-group-item text-muted"><small>No products found</small></div>';
                    html += `<a href="#" class="list-group-item list-group-item-info py-2" onclick="openExternalProductModal('${currentSearch}'); return false;">
                                <i class="fas fa-plus-circle"></i> <strong>Add as External Brand Replacement</strong><br>
                                <small>Serial: ${currentSearch}</small>
                            </a>`;
                    $('#search_results').html(html).show();
                }
            });
        }, 300);
    });
    
    // Handle Enter key for scanning
    $('#product_search_input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const query = $(this).val().trim();
            $.get('../../api/products/universal-search.php', { search: query }, function(response) {
                if (response.status && response.data.length > 0) {
                    const firstResult = response.data[0];
                    if (firstResult.type === 'serial') {
                        selectProduct(firstResult);
                    }
                }
            });
        }
    });
    
    function displaySearchResults(results) {
        let html = '';
        results.forEach(item => {
            if (item.type === 'serial') {
                // Determine badge styling based on selectability
                let badgeClass = 'success';
                let badgeText = item.selection_message || item.stock_status;
                let itemClass = 'list-group-item-action search-result-item';
                let disabledClass = '';
                
                if (!item.is_selectable) {
                    badgeClass = 'danger';
                    itemClass = 'search-result-item-disabled';
                    disabledClass = 'disabled';
                } else if (item.stock_type === 'rma') {
                    badgeClass = 'success';
                } else if (item.stock_type === 'damaged') {
                    badgeClass = 'warning';
                } else {
                    badgeClass = 'info';
                }
                
                html += `<a href="#" class="list-group-item ${itemClass} py-2 ${disabledClass}" 
                            data-product-id="${item.product_id}"
                            data-serial="${item.serial_number}"
                            data-stock-status="${item.stock_status}"
                            data-stock-type="${item.stock_type || ''}"
                            data-serial-status="${item.serial_status}"
                            data-is-selectable="${item.is_selectable}"
                            data-name="${item.name}">
                            <strong>${item.name}</strong> (${item.code})<br>
                            <small>Serial: ${item.serial_number}</small>
                            <span class="badge bg-${badgeClass} float-end">${badgeText}</span>
                        </a>`;
            } else {
                // Product without serial
                const stockClass = item.stock_quantity > 0 ? 'success' : (item.stock_status.includes('RMA') ? 'warning' : 'secondary');
                html += `<div class="list-group-item text-muted py-2">
                            <strong>${item.name}</strong> (${item.code})<br>
                            <small>No serial - Please search by serial number</small>
                            <span class="badge bg-${stockClass} float-end">${item.stock_status}</span>
                        </div>`;
            }
        });

        // Add "Add New External Product" option
        const currentSearch = $('#product_search_input').val().trim();
        html += `<a href="#" class="list-group-item list-group-item-info py-2" onclick="openExternalProductModal('${currentSearch}')">
                    <i class="fas fa-plus-circle"></i> <strong>Add as External Brand Replacement</strong><br>
                    <small>Serial: ${currentSearch}</small>
                </a>`;

        $('#search_results').html(html).show();
    }
    
    $(document).on('click', '.search-result-item, .search-result-item-disabled', function(e) {
        e.preventDefault();
        
        // Check if this item is selectable
        const isSelectable = $(this).data('is-selectable');
        if (isSelectable === false || isSelectable === 'false') {
            alert('❌ Cannot select this serial - it is currently in Current Stock.\n\nPlease select a serial from RMA Stock or enter a new serial number manually.');
            return false;
        }
        
        selectProduct({
            product_id: $(this).data('product-id'),
            serial_number: $(this).data('serial'),
            stock_status: $(this).data('stock-status'),
            stock_type: $(this).data('stock-type'),
            serial_status: $(this).data('serial-status'),
            name: $(this).data('name')
        });
    });
    
    function selectProduct(item) {
        $('#replacement_product_id').val(item.product_id);
        $('#replacement_serial_number').val(item.serial_number);
        $('#selected_product_name').text(item.name);
        $('#selected_serial_display').text(item.serial_number);
        
        // Determine badge color based on stock type
        let stockClass = 'info';
        if (item.stock_type === 'rma') {
            stockClass = 'success';
        } else if (item.stock_type === 'current') {
            stockClass = 'primary';
        } else if (item.stock_type === 'damaged') {
            stockClass = 'warning';
        }
        
        $('#selected_stock_status').removeClass('bg-success bg-warning bg-secondary bg-info bg-primary bg-danger')
                                   .addClass('bg-' + stockClass)
                                   .text(item.stock_status);
        
        $('#selected_product_display').show();
        $('#search_results').hide().empty();
        $('#product_search_input').val(item.serial_number);

        // Reset external flag
        $('#is_external_replacement').val('0');
    }

    window.openExternalProductModal = function(serial) {
        $('#modal_ext_serial').val(serial);
        $('#modal_ext_name').val('');
        $('#modal_ext_brand').val('');
        var modal = new bootstrap.Modal(document.getElementById('externalProductModal'));
        modal.show();
    };

    window.confirmExternalProduct = function() {
        const name = $('#modal_ext_name').val().trim();
        const serial = $('#modal_ext_serial').val().trim();
        const brand = $('#modal_ext_brand').val().trim();

        if (!name) {
            alert('Please enter product name.');
            return;
        }

        // Set values
        $('#is_external_replacement').val('1');
        $('#ext_product_name').val(name);
        $('#replacement_serial_number').val(serial);
        $('#ext_brand_name').val(brand);
        
        // Clear inventory fields
        $('#replacement_product_id').val('');
        $('#new_serial_id').val('');

        // Update UI
        $('#selected_product_name').text(name + (brand ? ' (' + brand + ')' : ''));
        $('#selected_serial_display').text(serial);
        $('#selected_stock_status').removeClass('bg-success bg-warning bg-secondary bg-info bg-primary bg-danger')
                                   .addClass('bg-secondary')
                                   .text('External Replacement');
        
        $('#selected_product_display').show();
        $('#search_results').hide().empty();
        $('#product_search_input').val(serial);

        bootstrap.Modal.getInstance(document.getElementById('externalProductModal')).hide();
    };
    
    window.clearSelection = function() {
        $('#replacement_product_id').val('');
        $('#replacement_serial_number').val('');
        $('#product_search_input').val('');
        $('#selected_product_display').hide();
        $('#search_results').hide().empty();
    };
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#product_search_input, #search_results').length) {
            $('#search_results').hide();
        }
    });

    $('input[name="replace_in_type"]').change(function() {
        if ($(this).val() === 'new') {
            $('#new_serial_section').fadeIn();
        } else {
            $('#new_serial_section').fadeOut();
        }
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
