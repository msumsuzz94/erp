<?php
/**
 * Damaged/Dead Stock Management
 * Track damaged and dead stock items
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Ensure tables exist
db_query("CREATE TABLE IF NOT EXISTS damaged_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity DECIMAL(15,2) NOT NULL,
    stock_type ENUM('Damaged', 'Dead') DEFAULT 'Damaged',
    reason TEXT,
    cost_value DECIMAL(15,2),
    date DATE NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

db_query("CREATE TABLE IF NOT EXISTS damaged_stock_serials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    damaged_stock_id INT NOT NULL,
    product_id INT NOT NULL,
    serial_id INT NOT NULL,
    serial_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$error = '';
$success = '';

// Handle form submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $product_id = (int) $_POST['product_id'];
        $quantity = (int) $_POST['quantity'];
        $stock_type = sanitize_input($_POST['stock_type']);
        $reason = sanitize_input($_POST['reason']);
        $cost_value = (float) $_POST['cost_value'];
        $date = sanitize_input($_POST['date']);
        $selected_serials = isset($_POST['selected_serials']) ? $_POST['selected_serials'] : [];

        if ($product_id && $quantity > 0 && in_array($stock_type, ['damaged', 'dead'])) {
            // Get current product stock
            $product = db_select_one('products', ['id' => $product_id]);

            if ($product) {
                // Check if enough stock is available
                if ($product['stock_quantity'] >= $quantity) {
                    // Reduce stock quantity
                    $new_quantity = $product['stock_quantity'] - $quantity;
                    db_update('products', ['stock_quantity' => $new_quantity], ['id' => $product_id]);

                    // Insert damaged stock entry
                    // Ensure reason is not empty (required field)
                    if (empty($reason)) {
                        $reason = 'No reason provided';
                    }
                    
                    $damaged_data = [
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'stock_type' => $stock_type,
                        'reason' => $reason,
                        'cost_value' => $cost_value,
                        'date' => $date,
                        'user_id' => get_current_user_id(),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    try {
                        $damaged_stock_id = db_insert('damaged_stock', $damaged_data);
                        if (!$damaged_stock_id) {
                            error_log('ERROR: Failed to insert damaged stock - no result returned');
                        }
                        
                        // If serials were selected, link them and update their status
                        if (!empty($selected_serials) && $damaged_stock_id) {
                            foreach ($selected_serials as $serial_id) {
                                $serial_id = (int) $serial_id;
                                
                                // Get serial details
                                $serial = db_select_one('product_serials', ['id' => $serial_id]);
                                if ($serial) {
                                    // Insert into damaged_stock_serials
                                    $serial_link_data = [
                                        'damaged_stock_id' => $damaged_stock_id,
                                        'product_id' => $product_id,
                                        'serial_id' => $serial_id,
                                        'serial_number' => $serial['serial_number'],
                                        'created_at' => date('Y-m-d H:i:s')
                                    ];
                                    db_insert('damaged_stock_serials', $serial_link_data);
                                    
                                    // Update serial status to 'defective'
                                    db_update('product_serials', 
                                        ['status' => 'defective'], 
                                        ['id' => $serial_id]
                                    );
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log('ERROR: Failed to insert damaged stock: ' . $e->getMessage());
                        // Continue anyway to save to stock_adjustments
                    }

                    // Also log as stock adjustment
                    $adjustment_data = [
                        'product_id' => $product_id,
                        'adjustment_type' => 'subtract',
                        'quantity' => $quantity,
                        'reason' => ucfirst($stock_type) . ' Stock: ' . $reason,
                        'date' => $date,
                        'user_id' => get_current_user_id(),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    db_insert('stock_adjustments', $adjustment_data);

                    $serial_info = !empty($selected_serials) ? ' (' . count($selected_serials) . ' serials)' : '';
                    log_activity(get_current_user_id(), 'damaged_stock', "Recorded $stock_type stock for product ID: $product_id$serial_info");
                    redirect_with_message($_SERVER['PHP_SELF'], ucfirst($stock_type) . ' stock recorded successfully', 'success');
                } else {
                    $error = 'Insufficient stock! Current stock: ' . $product['stock_quantity'];
                }
            } else {
                $error = 'Product not found';
            }
        } else {
            $error = 'Please fill in all required fields';
        }
    }
}

// Get all active products
$products = db_select('products', ['status' => 'active'], '*', 'name ASC');

// Get damaged/dead stock entries with statistics
$sql = "SELECT ds.*, p.name as product_name, p.code as product_code, u.username,
        GROUP_CONCAT(dss.serial_number ORDER BY dss.serial_number SEPARATOR ', ') as serial_numbers
        FROM damaged_stock ds
        LEFT JOIN products p ON ds.product_id = p.id
        LEFT JOIN users u ON ds.user_id = u.id
        LEFT JOIN damaged_stock_serials dss ON ds.id = dss.damaged_stock_id
        GROUP BY ds.id
        ORDER BY ds.created_at DESC
        LIMIT 100";
$entries = db_query($sql);
// DEBUG: Check query results
// error_log('Damaged Stock Query: ' . $sql);
// error_log('Entries Count: ' . count($entries));

// Calculate statistics
$stats_sql = "SELECT 
                stock_type,
                COUNT(*) as count,
                SUM(quantity) as total_quantity,
                SUM(cost_value) as total_cost
              FROM damaged_stock
              GROUP BY stock_type";
$stats_raw = db_query($stats_sql);

$stats = [
    'damaged' => ['count' => 0, 'quantity' => 0, 'cost' => 0],
    'dead' => ['count' => 0, 'quantity' => 0, 'cost' => 0]
];

foreach ($stats_raw as $stat) {
    $stats[$stat['stock_type']] = [
        'count' => $stat['count'],
        'quantity' => $stat['total_quantity'],
        'cost' => $stat['total_cost']
    ];
}

$page_title = 'Damaged/Dead Stock Management';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <!-- Statistics Cards -->
    <div class="col-md-6">
        <div class="card border-left-warning shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Damaged Stock (Repairable)
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= $stats['damaged']['quantity'] ?> Units
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <?= $stats['damaged']['count'] ?> Entries | Cost:
                            <?= format_currency($stats['damaged']['cost']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tools fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-left-danger shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Dead Stock (Unsellable)
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?= $stats['dead']['quantity'] ?> Units
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <?= $stats['dead']['count'] ?> Entries | Loss:
                            <?= format_currency($stats['dead']['cost']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Add Damaged/Dead Stock Form -->
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Record Damaged/Dead Stock</h6>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="form-group mb-3">
                        <label>Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-control select2" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>">
                                    <?= htmlspecialchars($product['name']) ?> (
                                    <?= htmlspecialchars($product['code']) ?>) - Stock:
                                    <?= $product['stock_quantity'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label>Stock Type <span class="text-danger">*</span></label>
                        <select name="stock_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="damaged">Damaged (Repairable)</option>
                            <option value="dead">Dead (Unsellable)</option>
                        </select>
                        <small class="text-muted">Damaged items may be repaired; Dead items are total loss</small>
                    </div>

                    <div class="form-group mb-3" id="quantity-group">
                        <label>Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                        <small class="text-muted" id="quantity-help">Enter the number of damaged items</small>
                    </div>

                    <!-- Serial Number Selection (scannable) -->
                    <div class="form-group mb-3" id="serial-selection" style="display:none;">
                        <label>Scan/Select Serial Numbers <span class="text-danger">*</span></label>
                        <div class="input-group mb-2">
                            <input type="text" id="serial-scanner" class="form-control" placeholder="Scan or type serial...">
                            <button class="btn btn-outline-secondary" type="button" id="add-serial-btn"><i class="fas fa-plus"></i></button>
                        </div>
                        <div id="selected-serials-container" class="border rounded p-2 bg-light d-flex flex-wrap gap-2 mb-2" style="min-height: 40px;">
                            <span class="text-muted w-100 text-center small">No serials scanned yet</span>
                        </div>
                        <div id="serial-data-holder"></div>
                        <small class="text-muted">Scan the specific serial numbers that are damaged</small>
                    </div>

                    <div class="form-group mb-3">
                        <label>Cost Value <span class="text-danger">*</span></label>
                        <input type="number" name="cost_value" class="form-control" step="0.01" min="0" value="0.00"
                            required>
                        <small class="text-muted">Financial impact (purchase cost or estimated value)</small>
                    </div>

                    <div class="form-group mb-3">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group mb-3">
                        <label>Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3"
                            placeholder="Describe the reason for damage/loss" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Record Stock
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Entries List -->
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Damaged/Dead Stock History</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="entriesTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>Serial Numbers</th>
                                <th>Cost</th>
                                <th>Reason</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td>
                                        <?= format_date($entry['date']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars((string)($entry['product_name'] ?? 'Unknown Product')) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars((string)($entry['product_code'] ?? '-')) ?>
                                    </td>
                                    <td>
                                        <?php if ($entry['stock_type'] === 'damaged'): ?>
                                            <span class="badge bg-warning text-dark">Damaged</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Dead</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $entry['quantity'] ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($entry['serial_numbers'])): ?>
                                            <span class="badge badge-info" title="<?= htmlspecialchars($entry['serial_numbers']) ?>">
                                                <?= count(explode(', ', $entry['serial_numbers'])) ?> serials
                                            </span>
                                            <small class="d-block text-muted" style="font-size: 0.75rem;">
                                                <?= htmlspecialchars(truncate($entry['serial_numbers'], 50)) ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= format_currency($entry['cost_value']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars((string)truncate($entry['reason'] ?? '', 50)) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars((string)($entry['username'] ?? 'N/A')) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    // Cache buster: 20260202000001
    $(document).ready(function () {
        $('.select2').select2({
            placeholder: 'Select Product',
            allowClear: true
        });

        $('#entriesTable').DataTable({
            "destroy": true,
            "pageLength": 25,
            "order": [[0, "desc"]],
            "columns": [
                { "orderable": true },  // Date
                { "orderable": true },  // Product
                { "orderable": true },  // Code
                { "orderable": true },  // Type
                { "orderable": true },  // Qty
                { "orderable": true },  // Serial Numbers
                { "orderable": true },  // Cost
                { "orderable": true },  // Reason
                { "orderable": true }   // By
            ]
        });

        // Global variable to store available serials for current product
        let availableSerials = [];
        let selectedSerials = [];

        // Handle product selection change
        $('select[name="product_id"]').on('change', function () {
            const productId = $(this).val();
            
            if (!productId) {
                hideSerialSelection();
                return;
            }

            // Fetch product details to check if it has serial tracking
            $.ajax({
                url: '../../api/products/get-product-serials.php',
                method: 'GET',
                data: { product_id: productId },
                dataType: 'json',
                success: function (response) {
                    if (response.status && response.serials && response.serials.length > 0) {
                        availableSerials = response.serials;
                        selectedSerials = [];
                        showSerialSelection();
                    } else {
                        availableSerials = [];
                        selectedSerials = [];
                        hideSerialSelection();
                    }
                },
                error: function () {
                    availableSerials = [];
                    hideSerialSelection();
                }
            });
        });

        function showSerialSelection() {
            $('#serial-selection').show();
            $('#quantity').val('0').prop('readonly', true).prop('required', false);
            $('#quantity-help').text('Quantity auto-calculated from scanned serials');
            updateSelectedSerialsUI();
            $('#serial-scanner').focus();
        }

        function hideSerialSelection() {
            $('#serial-selection').hide();
            availableSerials = [];
            selectedSerials = [];
            $('#quantity').prop('readonly', false).val('').prop('required', true);
            $('#quantity-help').text('Enter the number of damaged items');
            updateSelectedSerialsUI();
        }

        // Handle Serial Scanning
        $('#serial-scanner').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                addSerial();
            }
        });

        $('#add-serial-btn').click(function() {
            addSerial();
        });

        function addSerial() {
            const sn = $('#serial-scanner').val().trim();
            if (!sn) return;

            // Find serial in availableSerials
            const serial = availableSerials.find(s => s.serial_number === sn);
            
            if (!serial) {
                alert('Serial number "' + sn + '" not found in current stock for this product.');
                $('#serial-scanner').val('').focus();
                return;
            }

            // Check if already selected
            if (selectedSerials.some(s => s.id === serial.id)) {
                alert('Serial number already added.');
                $('#serial-scanner').val('').focus();
                return;
            }

            selectedSerials.push(serial);
            $('#serial-scanner').val('').focus();
            updateSelectedSerialsUI();
            updateQuantity();
        }

        function removeSerial(id) {
            selectedSerials = selectedSerials.filter(s => s.id != id);
            updateSelectedSerialsUI();
            updateQuantity();
        }

        function updateSelectedSerialsUI() {
            const container = $('#selected-serials-container');
            const dataHolder = $('#serial-data-holder');
            container.empty();
            dataHolder.empty();

            if (selectedSerials.length === 0) {
                container.html('<span class="text-muted w-100 text-center small">No serials scanned yet</span>');
                return;
            }

            selectedSerials.forEach(s => {
                const badge = $(`
                    <span class="badge bg-info text-dark p-2 d-flex align-items-center">
                        ${s.serial_number}
                        <i class="fas fa-times ms-2 pointer remove-serial" data-id="${s.id}" style="cursor:pointer;"></i>
                    </span>
                `);
                container.append(badge);

                // Add hidden input for form submission
                dataHolder.append(`<input type="hidden" name="selected_serials[]" value="${s.id}">`);
            });

            $('.remove-serial').click(function() {
                removeSerial($(this).data('id'));
            });
        }

        function updateQuantity() {
            $('#quantity').val(selectedSerials.length);
        }

        // Validate form before submission
        $('form').on('submit', function (e) {
            const serialSelectionVisible = $('#serial-selection').is(':visible');
            const quantity = parseInt($('#quantity').val()) || 0;

            if (serialSelectionVisible && selectedSerials.length === 0) {
                e.preventDefault();
                alert('Please scan/add at least one serial number for this product.');
                return false;
            }

            if (!serialSelectionVisible && quantity <= 0) {
                e.preventDefault();
                alert('Please enter a valid quantity.');
                return false;
            }
        });
    });
</script>
