<?php
/**
 * Stock Transfer - Edit Pending Transfer
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require login
require_login();

$transfer_id = $_GET['id'] ?? null;
if (!$transfer_id) {
    set_message('Invalid transfer ID', 'danger');
    redirect(BASE_URL . '/modules/stock-transfer/transfer-list.php');
}

// Get transfer details
$transfer = db_select_one('stock_transfers', ['id' => $transfer_id]);
if (!$transfer) {
    set_message('Transfer not found', 'danger');
    redirect(BASE_URL . '/modules/stock-transfer/transfer-list.php');
}

// Only pending transfers can be edited
if ($transfer['status'] !== 'pending') {
    set_message('Only pending transfers can be edited', 'warning');
    redirect(BASE_URL . '/modules/stock-transfer/transfer-view.php?id=' . $transfer_id);
}

// Get warehouses
$warehouses = db_select('warehouses', ['status' => 'active'], '*', 'name ASC');

// Get products with stock
$sql_products = "SELECT p.*, 
                (SELECT SUM(pws.quantity) FROM product_warehouse_stock pws WHERE pws.product_id = p.id) as total_stock
                FROM products p
                WHERE p.status = 'active'
                ORDER BY p.name ASC";
$products = db_query($sql_products);

// Get transfer items
$sql_items = "SELECT sti.*, p.name as product_name, p.code as product_code
              FROM stock_transfer_items sti
              INNER JOIN products p ON sti.product_id = p.id
              WHERE sti.transfer_id = ?";
$items = db_query($sql_items, [$transfer_id]);

// Get all users for approved_by
$users = db_select('users', ['status' => 'active'], 'id, username', 'username ASC');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        global $conn;
        $conn->beginTransaction();
        
        $transfer_id = $_POST['transfer_id'];
        $from_warehouse_id = clean_input($_POST['from_warehouse_id']);
        $to_warehouse_id = clean_input($_POST['to_warehouse_id']);
        
        if ($from_warehouse_id === $to_warehouse_id) {
            throw new Exception('Source and destination warehouse cannot be the same');
        }
        
        // Update main transfer record
        $transfer_data = [
            'transfer_date' => clean_input($_POST['transfer_date']),
            'from_warehouse_id' => $from_warehouse_id,
            'to_warehouse_id' => $to_warehouse_id,
            'reference_number' => clean_input($_POST['reference_number']),
            'challan_number' => clean_input($_POST['challan_number']),
            'transfer_type' => clean_input($_POST['transfer_type']),
            'notes' => clean_input($_POST['notes'])
        ];
        
        // Check if should be auto-approved
        if (!empty($_POST['approved_by'])) {
            $transfer_data['approved_by'] = clean_input($_POST['approved_by']);
            $transfer_data['approved_at'] = date('Y-m-d H:i:s');
            $transfer_data['status'] = 'approved';
        }
        
        db_update('stock_transfers', $transfer_data, ['id' => $transfer_id]);
        
        // Remove old items
        db_delete('stock_transfer_items', ['transfer_id' => $transfer_id]);
        
        // Insert new items
        $products_json = json_decode($_POST['products_json'], true);
        $total_items = 0;
        $total_quantity = 0;
        
        foreach ($products_json as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            
            // Verify stock availability
            $stock_check = db_query_one(
                "SELECT quantity FROM product_warehouse_stock WHERE product_id = ? AND warehouse_id = ?",
                [$product_id, $from_warehouse_id]
            );
            
            $available = $stock_check['quantity'] ?? 0;
            if ($quantity > $available) {
                throw new Exception("Insufficient stock for product ID $product_id. Available: $available");
            }
            
            // Get product cost
            $product = db_select_one('products', ['id' => $product_id]);
            $unit_cost = $product['cost_price'] ?? 0;
            
            // Insert transfer item
            $item_data = [
                'transfer_id' => $transfer_id,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_cost' => $unit_cost,
                'total_cost' => $unit_cost * $quantity,
                'notes' => $item['notes'] ?? ''
            ];
            
            db_insert('stock_transfer_items', $item_data);
            
            $total_items++;
            $total_quantity += $quantity;
            
            // If approved, deduct stock from source warehouse
            if (isset($transfer_data['status']) && $transfer_data['status'] === 'approved') {
                $from_wh = db_select_one('warehouses', ['id' => $from_warehouse_id]);
                
                db_query(
                    "UPDATE product_warehouse_stock 
                     SET quantity = quantity - ? 
                     WHERE product_id = ? AND warehouse_id = ?",
                    [$quantity, $product_id, $from_warehouse_id]
                );
                
                if ($from_wh['is_saleable']) {
                    db_query(
                        "UPDATE products 
                         SET stock_quantity = stock_quantity - ? 
                         WHERE id = ?",
                        [$quantity, $product_id]
                    );
                }
            }
        }
        
        // Update transfer totals
        db_update('stock_transfers', [
            'total_items' => $total_items,
            'total_quantity' => $total_quantity
        ], ['id' => $transfer_id]);
        
        log_activity(get_current_user_id(), 'stock_transfer_edit', 
                    "Updated stock transfer: {$transfer['transfer_number']}");
        
        $conn->commit();
        
        set_message('Stock transfer updated successfully', 'success');
        redirect(BASE_URL . '/modules/stock-transfer/transfer-view.php?id=' . $transfer_id);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        set_message('Error: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Edit Stock Transfer';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-edit"></i> Edit Stock Transfer: <?= htmlspecialchars($transfer['transfer_number']) ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="transferForm">
            <input type="hidden" name="transfer_id" value="<?= $transfer['id'] ?>">
            
            <div class="row">
                <!-- Transfer Information -->
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Transfer Information</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Transfer Date <span class="text-danger">*</span></label>
                        <input type="date" name="transfer_date" class="form-control" 
                               value="<?= $transfer['transfer_date'] ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">From Warehouse <span class="text-danger">*</span></label>
                        <select name="from_warehouse_id" id="fromWarehouse" class="form-select" required>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>" <?= $wh['id'] == $transfer['from_warehouse_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($wh['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">To Warehouse <span class="text-danger">*</span></label>
                        <select name="to_warehouse_id" id="toWarehouse" class="form-select" required>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>" <?= $wh['id'] == $transfer['to_warehouse_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($wh['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Transfer Type</label>
                        <select name="transfer_type" class="form-select">
                            <option value="normal" <?= $transfer['transfer_type'] == 'normal' ? 'selected' : '' ?>>Normal Transfer</option>
                            <option value="damaged_to_good" <?= $transfer['transfer_type'] == 'damaged_to_good' ? 'selected' : '' ?>>Damaged to Good Stock</option>
                            <option value="return_to_good" <?= $transfer['transfer_type'] == 'return_to_good' ? 'selected' : '' ?>>Return to Good Stock</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2 mb-3">Additional Details</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" name="reference_number" class="form-control" 
                               value="<?= htmlspecialchars($transfer['reference_number']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Challan Number</label>
                        <input type="text" name="challan_number" class="form-control" 
                               value="<?= htmlspecialchars($transfer['challan_number']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Approved By (Optional)</label>
                        <select name="approved_by" class="form-select">
                            <option value="">-- Leave as Pending --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">If selected, transfer will be approved immediately</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($transfer['notes']) ?></textarea>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <!-- Product Selection -->
            <h6 class="border-bottom pb-2 mb-3">Add Products</h6>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Product</label>
                    <select id="productSelect" class="form-select select2">
                        <option value="">Search and select product...</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id'] ?>" 
                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-code="<?= htmlspecialchars($product['code']) ?>"
                                    data-price="<?= $product['cost_price'] ?>">
                                <?= htmlspecialchars($product['code']) ?> - <?= htmlspecialchars($product['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Available Stock</label>
                    <input type="text" id="availableStock" class="form-control" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" id="productQuantity" class="form-control" min="0.01" step="0.01">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-primary w-100" onclick="addProduct()">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Code</th>
                            <th width="120">Available</th>
                            <th width="120">Transfer Qty</th>
                            <th width="100">Unit Cost</th>
                            <th width="120">Total Cost</th>
                            <th width="80">Action</th>
                        </tr>
                    </thead>
                    <tbody id="transferItemsBody">
                        <!-- Items rendered by JS -->
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">Totals:</th>
                            <th id="totalQuantity">0.00</th>
                            <th></th>
                            <th id="totalCost"><?= APP_CURRENCY_SYMBOL ?>0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <input type="hidden" name="products_json" id="productsJson">
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Update Transfer
                </button>
                <a href="<?= BASE_URL ?>/modules/stock-transfer/transfer-view.php?id=<?= $transfer_id ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
let transferItems = <?= json_encode($items) ?>;

$(document).ready(function() {
    $('#productSelect').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    $('#fromWarehouse, #productSelect').on('change', updateAvailableStock);
    
    // Initial render
    renderTransferItems();
});

function updateAvailableStock() {
    const warehouseId = $('#fromWarehouse').val();
    const productId = $('#productSelect').val();
    
    if (!warehouseId || !productId) return;
    
    fetch(`<?= BASE_URL ?>/api/stock-transfer/get-warehouse-stock.php?product_id=${productId}&warehouse_id=${warehouseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#availableStock').val(parseFloat(data.quantity).toFixed(2));
            } else {
                $('#availableStock').val('0.00');
            }
        });
}

function addProduct() {
    const productId = $('#productSelect').val();
    const productName = $('#productSelect option:selected').data('name');
    const productCode = $('#productSelect option:selected').data('code');
    const unitCost = parseFloat($('#productSelect option:selected').data('price')) || 0;
    const available = parseFloat($('#availableStock').val()) || 0;
    const quantity = parseFloat($('#productQuantity').val()) || 0;
    
    if (!productId || quantity <= 0) return;
    
    if (quantity > available) {
        alert('Insufficient stock');
        return;
    }
    
    const existing = transferItems.find(item => item.product_id == productId);
    if (existing) {
        alert('Product already added');
        return;
    }
    
    transferItems.push({
        product_id: productId,
        product_name: productName,
        product_code: productCode,
        quantity: quantity,
        unit_cost: unitCost,
        total_cost: quantity * unitCost,
        available: available
    });
    
    renderTransferItems();
    $('#productSelect').val('').trigger('change');
    $('#productQuantity').val('');
}

function removeProduct(index) {
    transferItems.splice(index, 1);
    renderTransferItems();
}

function renderTransferItems() {
    const tbody = $('#transferItemsBody');
    let html = '';
    let totalQty = 0;
    let totalCost = 0;
    
    if (transferItems.length === 0) {
        tbody.html('<tr><td colspan="7" class="text-center">No products added</td></tr>');
    } else {
        transferItems.forEach((item, index) => {
            const available = item.available !== undefined ? item.available : (parseFloat(item.quantity) || 0); // fallback for existing items
            html += `
                <tr>
                    <td>${item.product_name}</td>
                    <td>${item.product_code}</td>
                    <td>${parseFloat(available).toFixed(2)}</td>
                    <td><strong>${parseFloat(item.quantity).toFixed(2)}</strong></td>
                    <td>${parseFloat(item.unit_cost).toFixed(2)}</td>
                    <td>${parseFloat(item.total_cost).toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeProduct(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            totalQty += parseFloat(item.quantity);
            totalCost += parseFloat(item.total_cost);
        });
        tbody.html(html);
    }
    
    $('#totalQuantity').text(totalQty.toFixed(2));
    $('#totalCost').text('<?= APP_CURRENCY_SYMBOL ?>' + totalCost.toFixed(2));
}

$('#transferForm').on('submit', function() {
    if (transferItems.length === 0) {
        alert('Add products first');
        return false;
    }
    $('#productsJson').val(JSON.stringify(transferItems));
    $('#submitBtn').prop('disabled', true);
});
</script>
