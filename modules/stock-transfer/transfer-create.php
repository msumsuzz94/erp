<?php
/**
 * Stock Transfer - Create New Transfer
 * Enhanced to support RMA and Damaged stock transfers with serial tracking
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require login
require_login();

// Get products with stock
$sql_products = "SELECT p.*, 
                COALESCE(p.stock_quantity, 0) as total_stock
                FROM products p
                WHERE p.status = 'active'
                ORDER BY p.name ASC";
$products = db_query($sql_products);

// Get all warehouses for warehouse transfers
$warehouses = db_select('warehouses', ['status' => 'active'], '*', 'name ASC');

// Get all users for approved_by
$users = db_select('users', ['status' => 'active'], 'id, username, email', 'username ASC');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        dbBeginTransaction();
        
        // Generate transfer number
        $transfer_number = 'TRF-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Determine transfer mode
        $transfer_mode = clean_input($_POST['transfer_mode']); // 'warehouse' or 'stock_type'
        $transfer_type = 'normal';
        $from_warehouse_id = null;
        $to_warehouse_id = null;
        
        if ($transfer_mode === 'warehouse') {
            // Warehouse-based transfer
            $from_warehouse_id = clean_input($_POST['from_warehouse_id']);
            $to_warehouse_id = clean_input($_POST['to_warehouse_id']);
            
            if (empty($from_warehouse_id) || empty($to_warehouse_id)) {
                throw new Exception('Please select both source and destination warehouses');
            }
            
            if ($from_warehouse_id === $to_warehouse_id) {
                throw new Exception('Source and destination warehouse cannot be the same');
            }
            
            $transfer_type = 'normal';
            
        } else if ($transfer_mode === 'stock_type') {
            // Stock-type transfer
            $from_stock_type = clean_input($_POST['from_stock_type']);
            $to_stock_type = clean_input($_POST['to_stock_type']);
            
            if ($from_stock_type === $to_stock_type) {
                throw new Exception('Source and destination stock type cannot be the same');
            }
            
            // Determine transfer type from combination
            $transfer_type = $from_stock_type . '_to_' . $to_stock_type;
            $valid_types = ['current_to_rma', 'rma_to_current', 'rma_to_damaged', 'damaged_to_rma'];
            
            if (!in_array($transfer_type, $valid_types)) {
                throw new Exception('Invalid stock type combination');
            }
        } else {
            throw new Exception('Invalid transfer mode');
        }
        
        // Insert main transfer record
        $transfer_data = [
            'transfer_number' => $transfer_number,
            'transfer_date' => clean_input($_POST['transfer_date']),
            'from_warehouse_id' => $from_warehouse_id,
            'to_warehouse_id' => $to_warehouse_id,
            'reference_number' => clean_input($_POST['reference_number']),
            'challan_number' => clean_input($_POST['challan_number']),
            'transfer_type' => $transfer_type,
            'status' => 'pending',
            'notes' => clean_input($_POST['notes']),
            'created_by' => get_current_user_id()
        ];
        
        // Check if should be auto-approved
        if (!empty($_POST['approved_by'])) {
            $transfer_data['approved_by'] = clean_input($_POST['approved_by']);
            $transfer_data['approved_at'] = date('Y-m-d H:i:s');
            $transfer_data['status'] = 'approved';
        }
        
        $transfer_id = db_insert('stock_transfers', $transfer_data);
        
        if (!$transfer_id) {
            throw new Exception('Failed to create transfer');
        }
        
        // Insert transfer items
        $products_json = json_decode($_POST['products_json'], true);
        $total_items = 0;
        $total_quantity = 0;
        
        foreach ($products_json as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            $serial_numbers = $item['serial_numbers'] ?? [];
            
            // Verify stock availability based on transfer mode
            if ($transfer_mode === 'warehouse') {
                // Check warehouse stock
                $stock_check = db_query_one(
                    "SELECT quantity FROM product_warehouse_stock WHERE product_id = ? AND warehouse_id = ?",
                    [$product_id, $from_warehouse_id]
                );
                $available = $stock_check['quantity'] ?? 0;
            } else {
                // Check stock type inventory
                if ($from_stock_type === 'current') {
                    $stock_check = db_select_one('products', ['id' => $product_id]);
                    $available = $stock_check['stock_quantity'] ?? 0;
                } else {
                    $stock_check = db_query_one(
                        "SELECT quantity FROM stock_type_inventory WHERE product_id = ? AND stock_type = ?",
                        [$product_id, $from_stock_type]
                    );
                    $available = $stock_check['quantity'] ?? 0;
                }
            }
            
            if ($quantity > $available) {
                throw new Exception("Insufficient $from_stock_type stock for product ID $product_id. Available: $available");
            }
            
            // Get product cost
            $product = db_select_one('products', ['id' => $product_id]);
            $unit_cost = $product['cost_price'] ?? 0;
            
            // Verify serial numbers if provided
            if (!empty($serial_numbers) && $product['has_serial']) {
                if (count($serial_numbers) != $quantity) {
                    throw new Exception("Serial number count must match quantity for product ID $product_id");
                }
                
                // Verify all serials exist and belong to correct stock type
                foreach ($serial_numbers as $serial_id) {
                    $serial = db_select_one('product_serials', ['id' => $serial_id]);
                    if (!$serial || $serial['product_id'] != $product_id) {
                        throw new Exception("Invalid serial number ID: $serial_id");
                    }
                    if ($serial['stock_type'] != $from_stock_type) {
                        throw new Exception("Serial {$serial['serial_number']} is not in $from_stock_type stock");
                    }
                }
            }
            
            // Insert transfer item
            $item_data = [
                'transfer_id' => $transfer_id,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_cost' => $unit_cost,
                'total_cost' => $unit_cost * $quantity,
                'serial_numbers' => !empty($serial_numbers) ? json_encode($serial_numbers) : null,
                'notes' => $item['notes'] ?? ''
            ];
            
            db_insert('stock_transfer_items', $item_data);
            
            $total_items++;
            $total_quantity += $quantity;
            
            // If approved, update stock quantities
            if ($transfer_data['status'] === 'approved') {
                if ($transfer_mode === 'warehouse') {
                    // Warehouse transfer - update product_warehouse_stock
                    // Deduct from source warehouse
                    db_query(
                        "UPDATE product_warehouse_stock SET quantity = quantity - ? 
                         WHERE product_id = ? AND warehouse_id = ?",
                        [$quantity, $product_id, $from_warehouse_id]
                    );
                    
                    // Add to destination warehouse
                    db_query(
                        "INSERT INTO product_warehouse_stock (product_id, warehouse_id, quantity) 
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE quantity = quantity + ?",
                        [$product_id, $to_warehouse_id, $quantity, $quantity]
                    );
                } else {
                    // Stock type transfer
                    // Deduct from source
                    if ($from_stock_type === 'current') {
                        db_query(
                            "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?",
                            [$quantity, $product_id]
                        );
                    } else {
                        // Update or insert in stock_type_inventory
                        $existing = db_query_one(
                            "SELECT id, quantity FROM stock_type_inventory WHERE product_id = ? AND stock_type = ?",
                            [$product_id, $from_stock_type]
                        );
                        
                        if ($existing) {
                            db_query(
                                "UPDATE stock_type_inventory SET quantity = quantity - ? WHERE id = ?",
                                [$quantity, $existing['id']]
                            );
                        }
                    }
                    
                    // Add to destination
                    if ($to_stock_type === 'current') {
                        db_query(
                            "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?",
                            [$quantity, $product_id]
                        );
                    } else {
                        // Insert or update in stock_type_inventory
                        db_query(
                            "INSERT INTO stock_type_inventory (product_id, stock_type, quantity) 
                             VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE quantity = quantity + ?",
                            [$product_id, $to_stock_type, $quantity, $quantity]
                        );
                    }
                }
                
                // Update serial numbers stock_type if provided
                if (!empty($serial_numbers)) {
                    $serial_ids_str = implode(',', array_map('intval', $serial_numbers));
                    db_query(
                        "UPDATE product_serials 
                         SET stock_type = ?, status = ? 
                         WHERE id IN ($serial_ids_str)",
                        [$to_stock_type, 'in_stock']
                    );
                }
            }
        }
        
        // Update transfer totals
        db_update('stock_transfers', [
            'total_items' => $total_items,
            'total_quantity' => $total_quantity
        ], ['id' => $transfer_id]);
        
        // Log activity
        log_activity(get_current_user_id(), 'stock_transfer_create', 
                    "Created stock transfer: $transfer_number ($transfer_type)");
        
        dbCommit();
        
        set_message('Stock transfer created successfully', 'success');
        redirect(BASE_URL . '/modules/stock-transfer/transfer-view.php?id=' . $transfer_id);
        
    } catch (Exception $e) {
        if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
            dbRollback();
        }
        set_message('Error: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Create Stock Transfer';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-exchange-alt"></i> Create New Stock Transfer
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="transferForm">
            <div class="row">
                <!-- Transfer Information -->
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Transfer Details</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Transfer Date <span class="text-danger">*</span></label>
                        <input type="date" name="transfer_date" class="form-control" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Transfer Mode <span class="text-danger">*</span></label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="transfer_mode" id="modeWarehouse" value="warehouse" checked>
                                <label class="form-check-label" for="modeWarehouse">
                                    <i class="fas fa-warehouse"></i> Warehouse Transfer
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="transfer_mode" id="modeStockType" value="stock_type">
                                <label class="form-check-label" for="modeStockType">
                                    <i class="fas fa-exchange-alt"></i> Stock Type Transfer
                                </label>
                            </div>
                        </div>
                        <small class="text-muted">Choose between warehouse location transfer or stock status change</small>
                    </div>
                    
                    <!-- Warehouse Mode Fields -->
                    <div id="warehouseFields">
                        <div class="mb-3">
                            <label class="form-label">From Warehouse <span class="text-danger">*</span></label>
                            <select name="from_warehouse_id" id="fromWarehouse" class="form-select">
                                <option value="">Select Source Warehouse</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?= $warehouse['id'] ?>">
                                        <?= htmlspecialchars($warehouse['name']) ?> (<?= htmlspecialchars($warehouse['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Warehouse where stock is located</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">To Warehouse <span class="text-danger">*</span></label>
                            <select name="to_warehouse_id" id="toWarehouse" class="form-select">
                                <option value="">Select Destination Warehouse</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?= $warehouse['id'] ?>">
                                        <?= htmlspecialchars($warehouse['name']) ?> (<?= htmlspecialchars($warehouse['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Warehouse to transfer stock to</small>
                        </div>
                    </div>
                    
                    <!-- Stock Type Mode Fields -->
                    <div id="stockTypeFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Transfer From <span class="text-danger">*</span></label>
                            <select name="from_stock_type" id="fromStockType" class="form-select">
                                <option value="">Select Source Type</option>
                                <option value="current">Current Stock</option>
                                <option value="rma">RMA</option>
                                <option value="damaged">Damaged</option>
                            </select>
                            <small class="text-muted">Where the stock is coming from</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Transfer To <span class="text-danger">*</span></label>
                            <select name="to_stock_type" id="toStockType" class="form-select">
                                <option value="">Select Destination Type</option>
                                <option value="rma">RMA</option>
                                <option value="current">Current Stock</option>
                                <option value="damaged">Damaged</option>
                            </select>
                            <small class="text-muted">Where the stock is going to</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Additional Details</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_number" class="form-control" 
                               placeholder="Optional reference number">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Challan Number</label>
                        <input type="text" name="challan_number" class="form-control" 
                               placeholder="Optional challan number">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Approved By (Optional)</label>
                        <select name="approved_by" class="form-select">
                            <option value="">-- Save as Pending --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">If selected, transfer will be auto-approved</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes"></textarea>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <!-- Product Selection -->
            <h6 class="border-bottom pb-2 mb-3">Select Products to Transfer</h6>
            
            <div class="row mb-3">
                <div class="col-md-5">
                    <label class="form-label">Product</label>
                    <select id="productSelect" class="form-select select2">
                        <option value="">Search and select product...</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>" 
                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-code="<?= htmlspecialchars($product['code']) ?>"
                                    data-price="<?= $product['purchase_price'] ?? $product['cost_price'] ?? 0 ?>"
                                    data-has-serial="<?= $product['has_serial'] ?? 'Not Available' ?>">
                                <?= htmlspecialchars($product['code']) ?> - <?= htmlspecialchars($product['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Available</label>
                    <input type="text" id="availableStock" class="form-control" readonly 
                           placeholder="N/A">
                </div>
                
                <!-- Quantity box for non-serial products -->
                <div class="col-md-2" id="quantityBox">
                    <label class="form-label">Quantity</label>
                    <input type="number" id="productQuantity" class="form-control" 
                           min="1" step="1" placeholder="0">
                </div>
                
                <!-- Serial selection for serial products (hidden by default) -->
                <div class="col-md-4" id="serialBox" style="display: none;">
                    <label class="form-label">
                        <i class="fas fa-barcode"></i> Select Serials 
                        <span class="badge bg-info" id="inlineSerialCount">0 selected</span>
                    </label>
                    <div class="input-group">
                        <input type="text" id="serialScanInput" class="form-control" 
                               placeholder="Scan or type serial number...">
                        <select id="inlineSerialSelect" class="form-select" multiple size="1" 
                                style="max-height: 38px; overflow-y: auto;">
                            <!-- Serials will be loaded dynamically -->
                        </select>
                    </div>
                    <small class="text-muted">Scan or select from dropdown. Hold Ctrl/Cmd for multiple.</small>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-primary w-100" onclick="addProduct()">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
            </div>
            
            <!-- Old Serial Number Selection Section (removed as it's now inline) -->
            <div class="row mb-3" id="serialSelectionRow" style="display: none;">
                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-barcode"></i> Select Serial Numbers 
                        <span class="badge bg-info" id="serialCount">0 selected</span>
                    </label>
                    <select id="serialSelect" class="form-select" multiple size="5">
                        <!-- Serials will be loaded dynamically -->
                    </select>
                    <small class="text-muted">Hold Ctrl/Cmd to select multiple serial numbers. Must match quantity.</small>
                </div>
            </div>
            
            <!-- Transfer Items Table -->
            <div class="table-responsive">
                <table class="table table-bordered" id="transferItemsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Code</th>
                            <th width="100">Available</th>
                            <th width="100">Transfer Qty</th>
                            <th width="100">Unit Cost</th>
                            <th width="120">Total Cost</th>
                            <th width="100">Serials</th>
                            <th width="80">Action</th>
                        </tr>
                    </thead>
                    <tbody id="transferItemsBody">
                        <tr class="text-center text-muted">
                            <td colspan="8">No products added yet</td>
                        </tr>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">Totals:</th>
                            <th id="totalQuantity">0</th>
                            <th></th>
                            <th id="totalCost"><?= APP_CURRENCY_SYMBOL ?>0.00</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <input type="hidden" name="products_json" id="productsJson">
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Create Transfer
                </button>
                <a href="<?= BASE_URL ?>/modules/stock-transfer/transfer-list.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
let transferItems = [];
let availableSerials = [];
let currentProduct = null;

// Initialize Select2
$(document).ready(function(){
    $('#productSelect').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search and select product...'
    });
    
    // Transfer mode toggle
    $('input[name="transfer_mode"]').on('change', function() {
        const mode = $(this).val();
        if (mode === 'warehouse') {
            $('#warehouseFields').show();
            $('#stockTypeFields').hide();
            $('#fromWarehouse, #toWarehouse').prop('required', true);
            $('#fromStockType, #toStockType').prop('required', false);
        } else {
            $('#warehouseFields').hide();
            $('#stockTypeFields').show();
            $('#fromWarehouse, #toWarehouse').prop('required', false);
            $('#fromStockType, #toStockType').prop('required', true);
        }
        // Clear and update stock when mode changes
        transferItems = [];
        renderTransferItems();
        updateAvailableStock();
    });
    
    // Update available stock when warehouse/stock type or product changes
    $('#fromWarehouse, #fromStockType, #productSelect').on('change', function() {
        updateAvailableStock();
        loadAvailableSerials();
    });
    
    // Validate stock type combination
    $('#fromStockType, #toStockType').on('change', validateStockTypes);
    
    // Validate warehouse combination
    $('#fromWarehouse, #toWarehouse').on('change', validateWarehouses);
});

function validateWarehouses() {
    const from = $('#fromWarehouse').val();
    const to = $('#toWarehouse').val();
    
    if (!from || !to) return;
    
    if (from === to) {
        alert('Source and destination warehouse cannot be the same');
        $('#toWarehouse').val('');
    }
}

function validateStockTypes() {
    const from = $('#fromStockType').val();
    const to = $('#toStockType').val();
    
    if (!from || !to) return;
    
    if (from === to) {
        alert('Source and destination cannot be the same');
        $('#toStockType').val('');
        return;
    }
    
    const validCombinations = [
        'current-rma', 'rma-current', 'rma-damaged', 'damaged-rma'
    ];
    
    const combination = from + '-' + to;
    if (!validCombinations.includes(combination)) {
        alert('Invalid stock type combination. Valid: Current↔RMA, RMA↔Damaged');
        $('#toStockType').val('');
    }
}

function updateAvailableStock() {
    const productId = $('#productSelect').val();
    const transferMode = $('input[name="transfer_mode"]:checked').val();
    
    if (!productId) {
        $('#availableStock').val('');
        return;
    }
    
    $('#availableStock').val('Loading...');
    
    let url;
    if (transferMode === 'warehouse') {
        const warehouseId = $('#fromWarehouse').val();
        if (!warehouseId) {
            $('#availableStock').val('Select warehouse');
            return;
        }
        url = `<?= BASE_URL ?>/api/stock-transfer/get-stock-by-type.php?product_id=${productId}&warehouse_id=${warehouseId}`;
    } else {
        const stockType = $('#fromStockType').val();
        if (!stockType) {
            $('#availableStock').val('Select stock type');
            return;
        }
        url = `<?= BASE_URL ?>/api/stock-transfer/get-stock-by-type.php?product_id=${productId}&stock_type=${stockType}`;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#availableStock').val(parseFloat(data.quantity).toFixed(0));
            } else {
                $('#availableStock').val('0');
            }
        })
        .catch(() => {
            $('#availableStock').val('Error');
        });
}

function loadAvailableSerials() {
    const productId = $('#productSelect').val();
    const transferMode = $('input[name="transfer_mode"]:checked').val();
    const hasSerial = $('#productSelect option:selected').data('has-serial') === 'Available';
    
    if (!hasSerial) {
        $('#quantityBox').show();
        $('#serialBox').hide();
        availableSerials = [];
        return;
    }
    
    if (!productId) {
        $('#quantityBox').hide();
        $('#serialBox').hide();
        availableSerials = [];
        return;
    }
    
    $('#quantityBox').hide();
    $('#serialBox').show();
    
    let url;
    if (transferMode === 'warehouse') {
        const warehouseId = $('#fromWarehouse').val();
        if (!warehouseId) {
            availableSerials = [];
            $('#inlineSerialSelect').empty();
            return;
        }
        url = `<?= BASE_URL ?>/api/stock-transfer/get-serials-by-type.php?product_id=${productId}&warehouse_id=${warehouseId}`;
    } else {
        const stockType = $('#fromStockType').val();
        if (!stockType) {
            availableSerials = [];
            $('#inlineSerialSelect').empty();
            return;
        }
        url = `<?= BASE_URL ?>/api/stock-transfer/get-serials-by-type.php?product_id=${productId}&stock_type=${stockType}`;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.has_serial && data.serials.length > 0) {
                availableSerials = data.serials;
                $('#inlineSerialSelect').empty();
                data.serials.forEach(serial => {
                    // Store ID as value (backend expects IDs), display serial_number as text
                    $('#inlineSerialSelect').append(
                        `<option value="${serial.id}" data-serial="${serial.serial_number}">${serial.serial_number}</option>`
                    );
                });
                $('#inlineSerialCount').text('0 selected');
                $('#serialScanInput').val('').focus();
            } else {
                availableSerials = [];
                $('#inlineSerialSelect').empty();
                const stockType = $('#fromStockType').val() || 'selected';
                const source = transferMode === 'warehouse' ? 'warehouse' : (stockType + ' stock');
                alert('No serial numbers available for this product in ' + source + '.');
            }
        })
        .catch(error => {
            console.error('Error loading serials:', error);
            alert('Failed to load serial numbers');
        });
}

// Update serial count when selection changes (inline)
$('#inlineSerialSelect').on('change', function() {
    const count = $(this).val()?.length || 0;
    $('#inlineSerialCount').text(count + ' selected');
});

// Serial scan input handler
$('#serialScanInput').on('keypress', function(e) {
    if (e.which === 13) { // Enter key
        e.preventDefault();
        const scannedSerial = $(this).val().trim();
        
        if (!scannedSerial) return;
        
        // Find option by data-serial attribute (serial number)
        const option = $(`#inlineSerialSelect option[data-serial="${scannedSerial}"]`);
        
        if (option.length > 0) {
            // Select the option
            option.prop('selected', true);
            $('#inlineSerialSelect').trigger('change');
            $(this).val('').removeClass('is-invalid').addClass('is-valid');
            
            setTimeout(() => {
                $(this).removeClass('is-valid');
            }, 1000);
        } else {
            // Serial not found
            $(this).addClass('is-invalid');
            alert('Serial number not found in available stock: ' + scannedSerial);
        }
    }
});

function addProduct() {
    const productId = $('#productSelect').val();
    const productName = $('#productSelect option:selected').data('name');
    const productCode = $('#productSelect option:selected').data('code');
    const unitCost = parseFloat($('#productSelect option:selected').data('price')) || 0;
    const hasSerial = $('#productSelect option:selected').data('has-serial') === 'Available';
    const available = parseFloat($('#availableStock').val()) || 0;
    const fromStockType = $('#fromStockType').val();
    
    let quantity = 0;
    let selectedSerials = [];
    
    // Get quantity and serials based on product type
    if (hasSerial) {
        selectedSerials = $('#inlineSerialSelect').val() || [];
        quantity = selectedSerials.length;
    } else {
        quantity = parseInt($('#productQuantity').val()) || 0;
    }
    
    // Validation
    if (!fromStockType) {
        alert('Please select source stock type first');
        return;
    }
    
    if (!productId) {
        alert('Please select a product');
        return;
    }
    
    if (quantity <= 0) {
        alert('Please enter a valid quantity');
        return;
    }
    
    if (quantity > available) {
        alert(`Insufficient stock! Available: ${available}`);
        return;
    }
    
    // Check if product already added
    if (transferItems.find(item => item.product_id === productId)) {
        alert('Product already added to transfer');
        return;
    }
    
    // Get serial numbers for display (map IDs to serial numbers)
    let serialDisplay = '-';
    if (selectedSerials.length > 0) {
        const selectedSerialNumbers = selectedSerials.map(id => {
            return $('#inlineSerialSelect option[value="' + id + '"]').data('serial');
        }).filter(s => s); // Remove undefined values
        serialDisplay = selectedSerialNumbers.join(', ');
    }
    
    // Add to array
    const item = {
        product_id: productId,
        product_name: productName,
        product_code: productCode,
        available: available,
        quantity: quantity,
        unit_cost: unitCost,
        total_cost: quantity * unitCost,
        serial_numbers: selectedSerials,  // IDs for backend
        serial_display: serialDisplay      // Numbers for display
    };
    
    transferItems.push(item);
    renderTransferItems();
    
    // Reset form
    $('#productSelect').val('').trigger('change');
    $('#availableStock').val('');
    $('#productQuantity').val('');
    $('#inlineSerialSelect').empty();
    $('#serialScanInput').val('');
    $('#quantityBox').show();
    $('#serialBox').hide();
    availableSerials = [];
}

function removeProduct(index) {
    transferItems.splice(index, 1);
    renderTransferItems();
}

function renderTransferItems() {
    const tbody = $('#transferItemsBody');
    
    if (transferItems.length === 0) {
        tbody.html('<tr class="text-center text-muted"><td colspan="8">No products added yet</td></tr>');
        $('#totalQuantity').text('0');
        $('#totalCost').text('<?= APP_CURRENCY_SYMBOL ?>0.00');
        return;
    }
    
    let html = '';
    let totalQty = 0;
    let totalCost = 0;
    
    transferItems.forEach((item, index) => {
        html += `
            <tr>
                <td>${item.product_name}</td>
                <td>${item.product_code}</td>
                <td>${item.available.toFixed(0)}</td>
                <td><strong>${item.quantity}</strong></td>
                <td>${item.unit_cost.toFixed(2)}</td>
                <td>${item.total_cost.toFixed(2)}</td>
                <td><small class="text-muted">${item.serial_display}</small></td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeProduct(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        totalQty += item.quantity;
        totalCost += item.total_cost;
    });
    
    tbody.html(html);
    $('#totalQuantity').text(totalQty);
    $('#totalCost').text('<?= APP_CURRENCY_SYMBOL ?>' + totalCost.toFixed(2));
}

// Form submission
$('#transferForm').on('submit', function(e) {
    if (transferItems.length === 0) {
        e.preventDefault();
        alert('Please add at least one product to the transfer');
        return false;
    }
    
    // Validate stock types are different
    if ($('#fromStockType').val() === $('#toStockType').val()) {
        e.preventDefault();
        alert('Source and destination stock type must be different');
        return false;
    }
    
    // Set JSON data
    $('#productsJson').val(JSON.stringify(transferItems));
    $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating...');
});
</script>
