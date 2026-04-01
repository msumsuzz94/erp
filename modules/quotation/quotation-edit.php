<?php
/**
 * Edit Quotation Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$quotation_id = (int)get_param('id');

// Get quotation details
$quotation = db_select_one('quotations', ['id' => $quotation_id]);

if (!$quotation) {
    redirect_with_message('quotations-list.php', 'Quotation not found', 'error');
}

$sql = "SELECT qi.*, p.name as product_name, p.code as product_code
        FROM quotation_items qi
        INNER JOIN products p ON qi.product_id = p.id
        WHERE qi.quotation_id = ?
        ORDER BY qi.id ASC";
$quotation_items = db_query($sql, [$quotation_id]);

// Handle quotation submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $quotation_date = clean_input($_POST['quotation_date']);
        $expiration_date = clean_input($_POST['expiration_date']);
        $discount = (float)($_POST['discount'] ?? 0);
        $tax_rate = (float)($_POST['tax_rate'] ?? 0);
        $notes = clean_input($_POST['notes']);
        $items = json_decode($_POST['items_json'], true);
        
        if (empty($items)) {
            $errors[] = 'Please add at least one item';
        }
        
        if (empty($errors)) {
            // Backend safeguard: Merge duplicate products in items array
            $merged_items = [];
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                if (isset($merged_items[$pid])) {
                    $merged_items[$pid]['quantity'] += (float)$item['quantity'];
                    if (!empty($item['description']) && strpos($merged_items[$pid]['description'], $item['description']) === false) {
                        $merged_items[$pid]['description'] .= "\n" . $item['description'];
                    }
                } else {
                    $merged_items[$pid] = $item;
                }
            }
            $items = array_values($merged_items);

            db_begin_transaction();
            
            try {
                // Calculate totals
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += $item['quantity'] * $item['unit_price'];
                }
                
                $discount_amount = $discount;
                $tax_amount = ($subtotal - $discount_amount) * ($tax_rate / 100);
                $total_amount = $subtotal - $discount_amount + $tax_amount;
                
                // Update quotation
                $quotation_data = [
                    'customer_id' => $customer_id,
                    'quotation_date' => $quotation_date,
                    'expiration_date' => $expiration_date,
                    'total_amount' => $total_amount,
                    'tax_amount' => $tax_amount,
                    'tax_rate' => $tax_rate,
                    'discount' => $discount_amount,
                    'notes' => $notes
                ];
                
                db_update('quotations', $quotation_data, ['id' => $quotation_id]);
                
                // Delete old items and insert new ones
                db_delete('quotation_items', ['quotation_id' => $quotation_id]);
                
                foreach ($items as $item) {
                    $item_subtotal = $item['quantity'] * $item['unit_price'];
                    
                    $item_data = [
                        'quotation_id' => $quotation_id,
                        'product_id' => $item['product_id'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'tax' => 0,
                        'discount' => 0,
                        'subtotal' => $item_subtotal
                    ];
                    
                    db_insert('quotation_items', $item_data);
                }
                
                db_commit();
                log_activity(get_current_user_id(), 'quotation_update', "Updated quotation #$quotation_id");
                redirect_with_message("quotation-view.php?id=$quotation_id", 'Quotation updated successfully', 'success');
                
            } catch (Exception $e) {
                db_rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Get customers
$customers = db_query("SELECT id, name, phone FROM customers ORDER BY name ASC");

// Get products
$products = db_query("SELECT id, name, code, selling_price, stock_quantity FROM products WHERE status = 'active' ORDER BY name ASC");

$page_title = 'Edit Quotation #' . $quotation['quotation_number'];
include __DIR__ . '/../../templates/header.php';
?>

<style>
.product-search-results {
    position: absolute;
    background: white;
    border: 1px solid #ddd;
    max-height: 300px;
    overflow-y: auto;
    width: 100%;
    z-index: 1000;
    display: none;
}
.product-search-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
.product-search-item:hover { background-color: #f8f9fa; }
.cart-table th { background-color: #4e73df; color: white; }
.total-section { background-color: #f8f9fc; padding: 20px; border-radius: 5px; }
</style>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" id="quotationForm">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="items_json" id="items_json">
    
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quotation Details</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Customer</label>
                                <select name="customer_id" class="form-control select2">
                                    <option value="">Walk-in Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>" <?= $quotation['customer_id'] == $customer['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($customer['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-3">
                                <label>Quotation Date</label>
                                <input type="date" name="quotation_date" class="form-control" value="<?= $quotation['quotation_date'] ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-3">
                                <label>Expiration Date</label>
                                <input type="date" name="expiration_date" class="form-control" value="<?= $quotation['expiration_date'] ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Search Product</label>
                        <div style="position: relative;">
                            <input type="text" id="product_search" class="form-control" placeholder="Search by name or code...">
                            <div id="product_results" class="product-search-results"></div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered cart-table">
                            <thead>
                                <tr>
                                    <th style="width: 35%">Product</th>
                                    <th style="width: 20%">Price</th>
                                    <th style="width: 15%">Quantity</th>
                                    <th style="width: 20%">Subtotal</th>
                                    <th style="width: 10%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="cart_items"></tbody>
                        </table>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($quotation['notes']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">Summary</h6>
                </div>
                <div class="card-body total-section">
                    <div class="form-group mb-3">
                        <label>Discount</label>
                        <input type="number" name="discount" id="discount" class="form-control" step="0.01" value="<?= $quotation['discount'] ?>">
                    </div>
                    <div class="form-group mb-3">
                        <input type="number" name="tax_rate" id="tax_rate" class="form-control" step="0.01" value="<?= $quotation['tax_rate'] ?>">
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2"><strong>Subtotal:</strong> <span id="display_subtotal">0</span></div>
                    <div class="d-flex justify-content-between mb-2"><strong>Discount:</strong> <span id="display_discount">0</span></div>
                    <div class="d-flex justify-content-between mb-2"><strong>Tax:</strong> <span id="display_tax">0</span></div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3"><h5><strong>Total:</strong></h5> <h5 class="text-primary"><strong id="display_total">0</strong></h5></div>
                    <button type="submit" class="btn btn-primary btn-block w-100">Update Quotation</button>
                    <a href="quotation-view.php?id=<?= $quotation_id ?>" class="btn btn-secondary btn-block w-100 mt-2">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
let cart = [];
let products = <?= json_encode($products) ?>;
const initialItems = <?= json_encode($quotation_items) ?>;

$(document).ready(function() {
    // Load initial items - Merge duplicates if any exist in DB
    initialItems.forEach(item => {
        const idx = cart.findIndex(i => Number(i.product_id) === Number(item.product_id));
        if (idx !== -1) {
            cart[idx].quantity += Number(item.quantity);
            if (item.description && !cart[idx].description.includes(item.description)) {
                cart[idx].description += "\n" + item.description;
            }
        } else {
            cart.push({
                product_id: Number(item.product_id),
                product_name: item.product_name,
                product_code: item.product_code,
                unit_price: parseFloat(item.unit_price),
                quantity: Number(item.quantity),
                description: item.description || ''
            });
        }
    });
    updateCart();

    $('#product_search').on('input', function() {
        const query = $(this).val().toLowerCase();
        if (query.length < 1) { $('#product_results').hide(); return; }
        const results = products.filter(p => p.name.toLowerCase().includes(query) || p.code.toLowerCase().includes(query));
        let html = '';
        results.slice(0, 10).forEach(product => {
            html += `<div class="product-search-item" data-product='${JSON.stringify(product)}'><strong>${product.name}</strong><br><small>${product.code}</small></div>`;
        });
        $('#product_results').html(html).show();
    });

    $(document).on('click', '.product-search-item', function() {
        addToCart($(this).data('product'));
        $('#product_search').val('');
        $('#product_results').hide();
    });

    $('#discount, #tax_rate').on('input', updateTotals);
    $('#quotationForm').submit(function() { $('#items_json').val(JSON.stringify(cart)); });
});
function addToCart(product) {
    const idx = cart.findIndex(i => Number(i.product_id) === Number(product.id));
    if (idx !== -1) cart[idx].quantity++;
    else cart.push({ 
        product_id: Number(product.id), 
        product_name: product.name, 
        product_code: product.code, 
        unit_price: parseFloat(product.selling_price), 
        quantity: 1,
        description: '' 
    });
    updateCart();
}

function updatePrice(index, price) {
    cart[index].unit_price = parseFloat(price) || 0;
    updateCart();
}

function updateItemDescription(index, description) {
    cart[index].description = description;
}

function updateQuantity(index, qty) { cart[index].quantity = parseInt(qty) || 1; updateCart(); }
function removeFromCart(index) { cart.splice(index, 1); updateCart(); }

function updateCart() {
    let html = '';
    cart.forEach((item, idx) => {
        const subtotal = item.quantity * item.unit_price;
        html += `
            <tr>
                <td>
                    <strong>${item.product_name}</strong><br>
                    <small class="text-muted">${item.product_code}</small>
                    <textarea class="form-control form-control-sm mt-1" 
                              placeholder="Item description..."
                              onchange="updateItemDescription(${idx}, this.value)">${item.description}</textarea>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                        <input type="number" class="form-control price-input" 
                               value="${item.unit_price.toFixed(2)}" step="0.01" min="0"
                               onchange="updatePrice(${idx}, this.value)">
                    </div>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm" 
                           value="${item.quantity}" min="1" 
                           onchange="updateQuantity(${idx}, this.value)">
                </td>
                <td><?= APP_CURRENCY_SYMBOL ?>${subtotal.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeFromCart(${idx})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    $('#cart_items').html(html);
    updateTotals();
}

function updateTotals() {
    let subtotal = 0;
    cart.forEach(i => subtotal += i.quantity * i.unit_price);
    const discount = parseFloat($('#discount').val()) || 0;
    const taxRate = parseFloat($('#tax_rate').val()) || 0;
    const tax = (subtotal - discount) * (taxRate / 100);
    const total = subtotal - discount + tax;
    $('#display_subtotal').text('<?= APP_CURRENCY_SYMBOL ?>' + subtotal.toFixed(2));
    $('#display_discount').text('<?= APP_CURRENCY_SYMBOL ?>' + discount.toFixed(2));
    $('#display_tax').text('<?= APP_CURRENCY_SYMBOL ?>' + tax.toFixed(2));
    $('#display_total').text('<?= APP_CURRENCY_SYMBOL ?>' + total.toFixed(2));
}
</script>
