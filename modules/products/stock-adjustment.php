<?php
/**
 * Stock Adjustment Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        if (empty($_POST['product_id'])) $errors[] = 'Product is required';
        if (empty($_POST['adjustment_type'])) $errors[] = 'Adjustment type is required';
        if (empty($_POST['quantity']) || $_POST['quantity'] <= 0) $errors[] = 'Quantity must be greater than 0';
        
        if (empty($errors)) {
            /** @var PDO $conn */
            global $conn;
            
            try {
                $conn->beginTransaction();
                
                $product_id = (int)$_POST['product_id'];
                $adjustment_type = $_POST['adjustment_type'] ?? '';
                $quantity = (int)($_POST['quantity'] ?? 0);
                $serials_input = $_POST['serials'] ?? '';
                $selected_serials = $_POST['selected_serials'] ?? [];
                
                $product = db_select_one('products', ['id' => $product_id]);
                $has_serial = ($product['has_serial'] === 'Available');

                if ($has_serial) {
                    if ($adjustment_type === 'add') {
                        $serials = array_filter(array_map('trim', explode("\n", $serials_input)));
                        if (count($serials) != $quantity) {
                            throw new Exception("Please provide exactly $quantity serial numbers (one per line).");
                        }
                        // Check for existing serials
                        foreach ($serials as $sn) {
                            $exists = db_select_one('product_serials', ['serial_number' => $sn, 'product_id' => $product_id]);
                            if ($exists) throw new Exception("Serial number '$sn' already exists for this product.");
                        }
                    } else if ($adjustment_type === 'subtract') {
                        if (count($selected_serials) != $quantity) {
                            throw new Exception("Please select exactly $quantity serial numbers.");
                        }
                    }
                }

                // Insert adjustment record
                $adjustment_id = db_insert('stock_adjustments', [
                    'product_id' => $product_id,
                    'adjustment_type' => $adjustment_type,
                    'quantity' => $quantity,
                    'reason' => clean_input($_POST['reason'] ?? ''),
                    'date' => date('Y-m-d'),
                    'user_id' => get_current_user_id(),
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                if ($has_serial) {
                    if ($adjustment_type === 'add') {
                        foreach ($serials as $sn) {
                            // Insert serial into product_serials table
                            $serial_id = db_insert('product_serials', [
                                'product_id' => $product_id,
                                'serial_number' => $sn,
                                'status' => 'in_stock',
                                'stock_type' => NULL, // NULL means regular/current stock for POS
                                'created_at' => date('Y-m-d H:i:s')
                            ]);

                            // Link serial to this stock adjustment for tracking
                            db_insert('stock_adjustment_serials', [
                                'adjustment_id' => $adjustment_id,
                                'product_id' => $product_id,
                                'serial_id' => $serial_id,
                                'serial_number' => $sn,
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    } else if ($adjustment_type === 'subtract') {
                        foreach ($selected_serials as $sn) {
                            // Get the serial record
                            $serial_record = db_select_one('product_serials', ['serial_number' => $sn, 'product_id' => $product_id]);

                            if ($serial_record) {
                                // Mark as removed via adjustment (no longer available for sale)
                                db_query("UPDATE product_serials SET status = 'removed', sale_date = NOW() WHERE serial_number = ? AND product_id = ?", [$sn, $product_id]);

                                // Link to adjustment for audit trail
                                db_insert('stock_adjustment_serials', [
                                    'adjustment_id' => $adjustment_id,
                                    'product_id' => $product_id,
                                    'serial_id' => $serial_record['id'],
                                    'serial_number' => $sn,
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                        }
                    }
                }

                // Update product stock
                if ($adjustment_type === 'add') {
                    $sql = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?";
                } else {
                    $sql = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
                }

                $stmt = $conn->prepare($sql);
                $stmt->execute([$quantity, $product_id]);

                $conn->commit();

                log_activity(get_current_user_id(), 'stock_adjustment', "Adjusted stock for product ID: $product_id");
                redirect_with_message('products-list.php', 'Stock adjusted successfully', 'success');
            } catch (Exception $e) {
                $conn->rollBack();
                $errors[] = 'Failed to adjust stock: ' . $e->getMessage();
            }
        }
    }
}

$product_id = (int)get_param('product_id', 0);
$product = null;
if ($product_id > 0) {
    $product = db_select_one('products', ['id' => $product_id]);
}

$products = db_select('products', ['status' => 'active'], '*', 'name ASC');

$page_title = 'Stock Adjustment';
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
        <h6 class="m-0 font-weight-bold text-primary">Adjust Stock Quantity</h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="productSelect" class="form-control select2" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock_quantity'] ?>" data-has-serial="<?= $p['has_serial'] === 'Available' ? '1' : '0' ?>" <?= $product && $product['id'] == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?> (<?= $p['code'] ?>) - Stock: <?= $p['stock_quantity'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Current Stock</label>
                        <input type="text" id="currentStock" class="form-control" readonly value="<?= $product ? $product['stock_quantity'] : '0' ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Adjustment Type <span class="text-danger">*</span></label>
                        <select name="adjustment_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="add">Add Stock</option>
                            <option value="subtract">Subtract Stock</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" min="1" required>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12" id="serialSection" style="display: none;">
                    <div class="form-group mb-3">
                        <label id="serialLabel">Serial Numbers</label>
                        <div id="serialAddContainer">
                            <textarea name="serials" id="serialsTextarea" class="form-control" rows="4" placeholder="Enter serial numbers, one per line"></textarea>
                            <small class="text-muted">Enter exactly <span class="qty-count">0</span> serial numbers.</small>
                        </div>
                        <div id="serialSubtractContainer" style="display: none;">
                            <select name="selected_serials[]" id="serialsSelect" class="form-control select2-multiple" multiple="multiple" style="width: 100%;">
                                <!-- Populate via AJAX -->
                            </select>
                            <small class="text-muted">Select exactly <span class="qty-count">0</span> serial numbers.</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label>Reason</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Enter reason for adjustment"></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Adjust Stock</button>
            <a href="products-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap-5'
        });

        $('.select2-multiple').select2({
            theme: 'bootstrap-5',
            placeholder: 'Select Serial Numbers'
        });

        function updateSerialSection() {
            var product = $('#productSelect').find(':selected');
            var stock = product.data('stock');
            var hasSerial = product.data('has-serial') == '1';
            var adjType = $('select[name="adjustment_type"]').val();
            var quantity = parseInt($('input[name="quantity"]').val()) || 0;

            $('#currentStock').val(stock || 0);
            $('.qty-count').text(quantity);

            if (hasSerial && adjType) {
                $('#serialSection').show();
                // Lock quantity field for serial tracked items
                $('input[name="quantity"]').prop('readonly', true).attr('placeholder', 'Auto-calculated');
                if ($('#serialNote').length === 0) {
                    $('input[name="quantity"]').after('<small id="serialNote" class="text-info fw-bold d-block mt-1">Quantity is locked. Please use the Serial Numbers section below.</small>');
                }

                if (adjType === 'add') {
                    $('#serialAddContainer').show();
                    $('#serialSubtractContainer').hide();
                    $('#serialLabel').text('Enter New Serial Numbers');
                } else {
                    $('#serialAddContainer').hide();
                    $('#serialSubtractContainer').show();
                    $('#serialLabel').text('Select Existing Serial Numbers');

                    // Fetch available serials if product changed or type changed to subtract
                    var productId = $('#productSelect').val();
                    if (productId) {
                        $.ajax({
                            url: '../../api/products/get-serials.php',
                            data: {
                                product_id: productId,
                                status: 'in_stock',
                                raw: 1
                            },
                            dataType: 'json',
                            success: function(data) {
                                var select = $('#serialsSelect');
                                select.empty();
                                if (data && data.length > 0) {
                                    data.forEach(function(item) {
                                        select.append(new Option(item.serial_number, item.serial_number));
                                    });
                                }
                                select.trigger('change');
                            }
                        });
                    }
                }
            } else {
                $('#serialSection').hide();
                // Unlock quantity field
                $('input[name="quantity"]').prop('readonly', false).attr('placeholder', '');
                $('#serialNote').remove();
            }
        }

        $('#productSelect, select[name="adjustment_type"], input[name="quantity"]').on('change input', updateSerialSection);

        // Auto-count serials when adding stock
        $('#serialsTextarea').on('input keyup', function() {
            var adjType = $('select[name="adjustment_type"]').val();
            if (adjType === 'add') {
                var serials = $(this).val().split('\n').filter(function(line) {
                    return line.trim().length > 0;
                });
                var count = serials.length;
                $('input[name="quantity"]').val(count);
                $('.qty-count').text(count);
            }
        });

        // Auto-count selected serials when subtracting stock
        $('#serialsSelect').on('change', function() {
            var adjType = $('select[name="adjustment_type"]').val();
            if (adjType === 'subtract') {
                var count = $(this).val() ? $(this).val().length : 0;
                $('input[name="quantity"]').val(count);
                $('.qty-count').text(count);
            }
        });

        // Initial call
        updateSerialSection();
    });
</script>