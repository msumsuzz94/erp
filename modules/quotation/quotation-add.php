<?php
/**
 * Add Quotation Page
 * Create quotations for customers
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

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
                    // Logic for description: append if different
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
                
                // Generate quotation number
                $last_quotation = db_query_one("SELECT MAX(id) as max_id FROM quotations");
                $last_id = $last_quotation['max_id'] ?? 0;
                $quotation_number = generate_quotation_number($last_id);
                
                // Insert quotation
                $quotation_data = [
                    'quotation_number' => $quotation_number,
                    'customer_id' => $customer_id,
                    'quotation_date' => $quotation_date,
                    'expiration_date' => $expiration_date,
                    'total_amount' => $total_amount,
                    'tax_amount' => $tax_amount,
                    'tax_rate' => $tax_rate,
                    'discount' => $discount_amount,
                    'status' => 'pending',
                    'notes' => $notes,
                    'created_by' => get_current_user_id()
                ];
                
                $quotation_id = db_insert('quotations', $quotation_data);
                
                if (!$quotation_id) {
                    throw new Exception('Failed to create quotation');
                }
                
                // Insert quotation items
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
                    
                    $item_id = db_insert('quotation_items', $item_data);
                    
                    if (!$item_id) {
                        throw new Exception('Failed to add quotation item');
                    }
                }
                
                db_commit();
                log_activity(get_current_user_id(), 'quotation_create', "Created quotation $quotation_number");
                redirect_with_message('quotations-list.php', 'Quotation created successfully', 'success');
                
            } catch (Exception $e) {
                db_rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Get customers
$customers = db_query("SELECT id, name, phone FROM customers ORDER BY name ASC");

// Direct database test for products
global $conn;
error_log("Connection object: " . ($conn ? 'exists' : 'NULL'));

// Get products - load all products with correct column names
$products_sql = "SELECT id, name, code, selling_price, stock_quantity FROM products ORDER BY name ASC";
$products = db_query($products_sql);

// Debug: Check what we got
error_log("Products SQL: " . $products_sql);
error_log("Products result type: " . gettype($products));
error_log("Products result: " . print_r($products, true));
error_log("Products count: " . (is_array($products) ? count($products) : 'not an array'));

// Try direct query as a test
try {
    $stmt = $conn->prepare("SELECT id, name, code FROM products LIMIT 3");
    $stmt->execute();
    $direct_products = $stmt->fetchAll();
    error_log("Direct query result: " . print_r($direct_products, true));
    error_log("Direct query count: " . count($direct_products));
} catch (Exception $e) {
    error_log("Direct query error: " . $e->getMessage());
}

// Ensure products is an array
if (!$products || !is_array($products)) {
    error_log("WARNING: Products is empty or not an array!");
    $products = [];
}

$page_title = 'Add Quotation';
include __DIR__ . '/../../templates/header.php';
?>

<!-- Debug output for testing -->
<script>
console.log('PHP Products Count:', <?= count($products) ?>);
console.log('PHP Products Data:', <?= json_encode($products) ?>);
</script>


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

.product-search-item {
    padding: 10px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
}

.product-search-item:hover {
    background-color: #f8f9fa;
}

[data-theme="dark"] .product-search-item {
    color: #e0e0e0;
    border-bottom-color: #444;
}

[data-theme="dark"] .product-search-results {
    background-color: #2c2c2c;
    border-color: #444;
}

[data-theme="dark"] .product-search-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.cart-table th {
    background-color: #4e73df;
    color: white;
}

.total-section {
    background-color: #f8f9fc;
    padding: 20px;
    border-radius: 5px;
}
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
        <!-- Quotation Details -->
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quotation Details</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer</label>
                                <select name="customer_id" class="form-control select2">
                                    <option value="">Walk-in Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>">
                                            <?= htmlspecialchars($customer['name']) ?> 
                                            <?php if ($customer['phone']): ?>
                                                - <?= htmlspecialchars($customer['phone']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Quotation Date <span class="text-danger">*</span></label>
                                <input type="date" name="quotation_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Expiration Date</label>
                                <input type="date" name="expiration_date" class="form-control" 
                                       value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Search Product</label>
                        <div style="position: relative;">
                            <input type="text" id="product_search" class="form-control" 
                                   placeholder="Search by product name or code...">
                            <div id="product_results" class="product-search-results"></div>
                        </div>
                    </div>
                    
                    <!-- Cart Table -->
                    <div class="table-responsive mt-4">
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
                            <tbody id="cart_items">
                                <tr id="empty_cart">
                                    <td colspan="5" class="text-center text-muted">
                                        No items added. Search and select products above.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes or terms..."></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Totals & Actions -->
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">Quotation Summary</h6>
                </div>
                <div class="card-body total-section">
                    <div class="form-group">
                        <label>Discount</label>
                        <input type="number" name="discount" id="discount" class="form-control" 
                               step="0.01" min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Tax Rate (%)</label>
                        <input type="number" name="tax_rate" id="tax_rate" class="form-control" 
                               step="0.01" min="0" value="0">
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <strong>Subtotal:</strong>
                        <span id="display_subtotal"><?= format_currency(0) ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <strong>Discount:</strong>
                        <span id="display_discount"><?= format_currency(0) ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <strong>Tax:</strong>
                        <span id="display_tax"><?= format_currency(0) ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <h5><strong>Total:</strong></h5>
                        <h5 class="text-primary"><strong id="display_total"><?= format_currency(0) ?></strong></h5>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-file-alt"></i> Create Quotation
                    </button>
                    <a href="quotations-list.php" class="btn btn-secondary btn-block">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
            
            <!-- Quick Add Products -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Select</h6>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach (array_slice($products, 0, 10) as $product): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <small>
                                <strong><?= htmlspecialchars($product['name']) ?></strong><br>
                                <span class="text-muted"><?= htmlspecialchars($product['code']) ?></span><br>
                                <span class="text-primary"><?= format_currency($product['selling_price']) ?></span>
                                <button type="button" class="btn btn-sm btn-success float-right quick-add-btn"
                                        data-id="<?= $product['id'] ?>"
                                        data-name="<?= htmlspecialchars($product['name']) ?>"
                                        data-code="<?= htmlspecialchars($product['code']) ?>"
                                        data-price="<?= $product['selling_price'] ?>">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
// Debug: Check if script is loading
console.log('=== QUOTATION SCRIPT LOADING ===');

// Initialize variables
let cart = [];
let products = [];

// Document ready
$(document).ready(function() {
    console.log('=== DOCUMENT READY ===');
    console.log('jQuery loaded:', typeof $ !== 'undefined');
    
    // Load products from PHP
    try {
        products = <?= json_encode($products) ?>;
        console.log('Products loaded from PHP:', products);
        console.log('Total products:', products ? products.length : 0);
        
        if (!products || products.length === 0) {
            console.error('WARNING: No products loaded!');
            alert('No products available. Please add products first.');
        }
    } catch (e) {
        console.error('Error loading products:', e);
    }
    
    // Product search
    $('#product_search').on('input', function() {
        const query = $(this).val().toLowerCase();
        
        console.log('Search query:', query);
        console.log('Products available:', products.length);
        
        if (query.length < 1) {
            $('#product_results').hide();
            return;
        }
        
        const results = products.filter(p => {
            const nameMatch = p.name && p.name.toLowerCase().includes(query);
            const codeMatch = p.code && p.code.toLowerCase().includes(query);
            console.log('Checking product:', p.name, 'Name match:', nameMatch, 'Code match:', codeMatch);
            return nameMatch || codeMatch;
        });
        
        console.log('Search results found:', results.length);
        console.log('Results:', results);
        
        let html = '';
        results.slice(0, 10).forEach(product => {
            html += `
                <div class="product-search-item" data-product='${JSON.stringify(product)}'>
                    <strong>${product.name}</strong><br>
                    <small>Code: ${product.code} | Price: <?= APP_CURRENCY_SYMBOL ?>${parseFloat(product.selling_price).toFixed(2)}</small>
                </div>
            `;
        });
        
        if (html) {
            $('#product_results').html(html).show();
        } else {
            $('#product_results').html('<div class="product-search-item">No products found</div>').show();
        }
    });

    // Add product to cart
    $(document).on('click', '.product-search-item', function() {
        const product = $(this).data('product');
        if (product) {
            addToCart(product);
            $('#product_search').val('');
            $('#product_results').hide();
        }
    });

    // Quick add button
    $('.quick-add-btn').click(function() {
        const product = {
            id: $(this).data('id'),
            name: $(this).data('name'),
            code: $(this).data('code'),
            selling_price: $(this).data('price'),
            description: ''
        };
        addToCart(product);
    });

    // Hide search results when clicking outside
    $(document).click(function(e) {
        if (!$(e.target).closest('#product_search, #product_results').length) {
            $('#product_results').hide();
        }
    });

    // Update totals when discount or tax changes
    $('#discount, #tax_rate').on('input', updateTotals);

    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    // Form submission
    $('#quotationForm').submit(function(e) {
        if (cart.length === 0) {
            e.preventDefault();
            alert('Please add at least one item to the quotation');
            return false;
        }
        
        // Set items JSON
        $('#items_json').val(JSON.stringify(cart));
    });
}); // End document.ready

// Helper functions (outside document.ready so they're globally accessible)


function addToCart(product) {
    // Check if product already in cart
    const existingIndex = cart.findIndex(item => Number(item.product_id) === Number(product.id));
    
    if (existingIndex !== -1) {
        cart[existingIndex].quantity++;
    } else {
        cart.push({
            product_id: Number(product.id),
            product_name: product.name,
            product_code: product.code,
            unit_price: parseFloat(product.selling_price),
            quantity: 1,
            description: product.description || ''
        });
    }
    
    updateCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateCart();
}

function updateQuantity(index, quantity) {
    quantity = parseInt(quantity);
    if (quantity > 0) {
        cart[index].quantity = quantity;
    } else {
        removeFromCart(index);
    }
    updateCart();
}

function updatePrice(index, price) {
    cart[index].unit_price = parseFloat(price) || 0;
    updateCart();
}

function updateItemDescription(index, description) {
    cart[index].description = description;
    // No need to updateCart() as it renders the whole thing, which would lose focus
    // We update the JSON when submitting anyway.
}

function updateCart() {
    // Update cart display
    if (cart.length === 0) {
        $('#cart_items').html(`
            <tr id="empty_cart">
                <td colspan="5" class="text-center text-muted">
                    No items added. Search and select products above.
                </td>
            </tr>
        `);
    } else {
        let html = '';
        cart.forEach((item, index) => {
            const subtotal = item.quantity * item.unit_price;
            html += `
                <tr>
                    <td>
                        <strong>${item.product_name}</strong><br>
                        <small class="text-muted">${item.product_code}</small>
                        <textarea class="form-control form-control-sm mt-1" 
                                  placeholder="Item description..."
                                  onchange="updateItemDescription(${index}, this.value)">${item.description}</textarea>
                    </td>
                    <td>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control price-input" 
                                   value="${item.unit_price.toFixed(2)}" step="0.01" min="0"
                                   onchange="updatePrice(${index}, this.value)">
                        </div>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm quantity-input" 
                               value="${item.quantity}" min="1" 
                               onchange="updateQuantity(${index}, this.value)">
                    </td>
                    <td><?= APP_CURRENCY_SYMBOL ?>${subtotal.toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#cart_items').html(html);
    }
    
    updateTotals();
}

function updateTotals() {
    // Calculate subtotal
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.quantity * item.unit_price;
    });
    
    // Get discount and tax
    const discount = parseFloat($('#discount').val()) || 0;
    const taxRate = parseFloat($('#tax_rate').val()) || 0;
    
    // Calculate tax and total
    const taxAmount = (subtotal - discount) * (taxRate / 100);
    const total = subtotal - discount + taxAmount;
    
    // Update displays
    $('#display_subtotal').text('<?= APP_CURRENCY_SYMBOL ?>' + subtotal.toFixed(2));
    $('#display_discount').text('<?= APP_CURRENCY_SYMBOL ?>' + discount.toFixed(2));
    $('#display_tax').text('<?= APP_CURRENCY_SYMBOL ?>' + taxAmount.toFixed(2));
    $('#display_total').text('<?= APP_CURRENCY_SYMBOL ?>' + total.toFixed(2));
}

</script>

