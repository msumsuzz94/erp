<?php

/**
 * Opening Stock Management
 * Set initial stock quantities for products
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Handle form submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $product_id = (int)$_POST['product_id'];
        $quantity = (float)$_POST['quantity'];
        $notes = sanitize_input($_POST['notes']); // Fixed bug: was previously $_POST['reason']

        if ($product_id && $quantity > 0) {
            // Get current stock
            $product = db_select_one('products', ['id' => $product_id]);

            if ($product) {
                // Verify serials if product uses serials
                $serial_count = 0;
                $serials_to_insert = [];
                if ($product['has_serial'] === 'Available' && !empty($_POST['serial_numbers'])) {
                    $posted_serials = explode("\n", $_POST['serial_numbers']);
                    foreach ($posted_serials as $serial) {
                        $serial = trim($serial);
                        if (!empty($serial)) {
                            // Check if serial already exists
                            $existing = db_select_one('product_serials', ['serial_number' => $serial]);
                            if ($existing) {
                                $error = "Serial number '$serial' already exists in the system.";
                                break; // Break out of foreach
                            }
                            $serials_to_insert[] = $serial;
                            $serial_count++;
                        }
                    }

                    if ($serial_count != $quantity) {
                        $error = "Quantity ($quantity) does not match the number of serials entered ($serial_count).";
                    }
                }

                if (!isset($error)) {
                    // Update product stock
                    $new_quantity = $product['stock_quantity'] + $quantity;
                    db_update('products', ['stock_quantity' => $new_quantity], ['id' => $product_id]);

                    // Insert serials
                    if ($product['has_serial'] === 'Available' && !empty($serials_to_insert)) {
                        foreach ($serials_to_insert as $serial) {
                            $serial_data = [
                                'product_id' => $product_id,
                                'serial_number' => $serial,
                                'status' => 'in_stock',
                                'serial_status' => 'in_stock',
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                            db_insert('product_serials', $serial_data);
                        }
                    }

                    // Log the opening stock entry
                    $log_data = [
                        'product_id' => $product_id,
                        'adjustment_type' => 'opening_stock',
                        'quantity' => $quantity,
                        'reason' => $notes,
                        'date' => date('Y-m-d'),
                        'user_id' => get_current_user_id(),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    db_insert('stock_adjustments', $log_data);

                    log_activity(get_current_user_id(), 'opening_stock', "Added opening stock for product ID: $product_id");
                    redirect_with_message($_SERVER['PHP_SELF'], 'Opening stock added successfully', 'success');
                }
            } else {
                $error = 'Product not found';
            }
        } else {
            $error = 'Please select a product and enter a valid quantity';
        }
    }
}

// Get all products
$products = db_select('products', ['status' => 'active'], '*', 'name ASC');

// Get recent opening stock entries
$sql = "SELECT sa.*, p.name as product_name, p.code as product_code, u.username 
        FROM stock_adjustments sa
        LEFT JOIN products p ON sa.product_id = p.id
        LEFT JOIN users u ON sa.user_id = u.id
        WHERE sa.adjustment_type = 'opening_stock'
        ORDER BY sa.created_at DESC
        LIMIT 50";
$recent_entries = db_query($sql);

$page_title = 'Opening Stock Management';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <!-- Add Opening Stock Form -->
    <div class="col-md-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Add Opening Stock</h6>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="form-group mb-3">
                        <label>Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-control select2" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>" data-has-serial="<?= htmlspecialchars($product['has_serial'] ?? 'Not Available') ?>">
                                    <?= htmlspecialchars($product['name']) ?> (<?= htmlspecialchars($product['code']) ?>) - Current: <?= $product['stock_quantity'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-3" id="serial_numbers_container" style="display: none;">
                        <label><strong>Serial Numbers</strong> <span class="text-danger">*</span></label>
                        <textarea name="serial_numbers" id="serial_numbers" class="form-control" rows="4" placeholder="Enter one serial number per line..."></textarea>
                        <small class="text-muted">Enter serial numbers exactly as they appear on the products. The <strong>Quantity</strong> will be automatically calculated based on the number of non-empty lines.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label>Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="stock_quantity" class="form-control" step="0.01" min="0.01" required>
                        <small class="text-muted">This will be added to current stock</small>
                    </div>

                    <div class="form-group mb-3">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes about this opening stock entry"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-plus"></i> Add Opening Stock
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Entries -->
    <div class="col-md-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Opening Stock Entries</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="entriesTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Code</th>
                                <th>Quantity</th>
                                <th>Added By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_entries)): ?>
                                <?php foreach ($recent_entries as $entry): ?>
                                    <tr>
                                        <td><?= format_datetime($entry['created_at']) ?></td>
                                        <td><?= htmlspecialchars($entry['product_name']) ?></td>
                                        <td><?= htmlspecialchars($entry['product_code']) ?></td>
                                        <td><span class="badge bg-success">+<?= $entry['quantity'] ?></span></td>
                                        <td><?= htmlspecialchars($entry['username'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($entry['reason'] ?: '-') ?></td>
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
    // Cache buster: 20260127090017
    $(document).ready(function() {
        var $productSelect = $('#product_id');
        var $serialContainer = $('#serial_numbers_container');
        var $serialInput = $('#serial_numbers');
        var $quantityInput = $('#stock_quantity');
        var $select2Elem = $('.select2').select2({
            placeholder: 'Select Product',
            allowClear: true
        });

        // Toggle serial input field based on selected product
        $select2Elem.on('change', function() {
            checkSerialTracking();
        });

        function checkSerialTracking() {
            var selectedOption = $productSelect.find('option:selected');
            if (!selectedOption.length || !selectedOption.val()) {
                $serialContainer.hide();
                $quantityInput.prop('readonly', false);
                $serialInput.val('');
                return;
            }

            var hasSerial = selectedOption.data('has-serial');
            if (hasSerial === 'Available') {
                $serialContainer.show();
                // $serialInput.prop('required', true); // Temporarily remove required as user might input 0 quantity
                $quantityInput.prop('readonly', true);
                updateQuantityFromSerials();
            } else {
                $serialContainer.hide();
                // $serialInput.prop('required', false);
                $serialInput.val('');
                $quantityInput.prop('readonly', false);
            }
        }

        // Update quantity when typing serials
        $serialInput.on('input', function() {
            updateQuantityFromSerials();
        });

        function updateQuantityFromSerials() {
            var serialsText = $serialInput.val();
            var lines = serialsText.split('\n');
            var count = 0;
            for (var i = 0; i < lines.length; i++) {
                if (lines[i].trim() !== '') {
                    count++;
                }
            }

            // Prevent 0 quantity from being valid if user typed no serials but expected to
            if (count > 0 || $quantityInput.val() > 0) {
                $quantityInput.val(count);
            }
        }

        $('#entriesTable').DataTable({
            // Check if DataTable already exists and destroy it
            "destroy": true,
            "pageLength": 25,
            "order": [
                [0, "desc"]
            ],
            "columns": [{
                    "orderable": true
                }, // Date
                {
                    "orderable": true
                }, // Product
                {
                    "orderable": true
                }, // Code
                {
                    "orderable": true
                }, // Quantity
                {
                    "orderable": true
                }, // Added By
                {
                    "orderable": true
                } // Notes
            ]
        });
    });
</script>