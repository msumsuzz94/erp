<?php

/**
 * Create Sales Request (SR Workflow)
 * Smart, polished interface for sales representatives
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle request submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];

        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $customer_name = clean_input($_POST['customer_name']) ?: 'Walk-in';
        $customer_phone = clean_input($_POST['customer_phone']);
        $discount = (float)($_POST['discount'] ?? 0);
        $notes = clean_input($_POST['notes'] ?? '');
        $items = json_decode($_POST['items_json'] ?? '[]', true);

        if (empty($items)) {
            $errors[] = 'Please add at least one item';
        }

        if (empty($errors)) {
            // Merge duplicate products
            $merged_items = [];
            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                if (isset($merged_items[$pid])) {
                    $merged_items[$pid]['quantity'] += (float)$item['quantity'];
                } else {
                    $merged_items[$pid] = $item;
                }
            }
            $items = array_values($merged_items);

            db_begin_transaction();

            try {
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += $item['quantity'] * $item['unit_price'];
                }

                $total_amount = $subtotal - $discount;

                $last_req = db_query_one("SELECT MAX(id) as max_id FROM sales_requests");
                $last_id = $last_req['max_id'] ?? 0;
                $request_number = 'SRQ-' . str_pad($last_id + 1, 6, '0', STR_PAD_LEFT);

                if ($customer_id) {
                    $cust = db_select_one('customers', ['id' => $customer_id]);
                    if ($cust) {
                        $customer_name = $cust['name'];
                        $customer_phone = $cust['phone'];
                    }
                } else if ($customer_name !== 'Walk-in') {
                    // It's a brand new customer explicitly typed out!
                    // Check if a customer with this phone number already exists
                    if (!empty($customer_phone)) {
                        $existing_cust = db_select_one('customers', ['phone' => $customer_phone]);
                        if ($existing_cust) {
                            $customer_id = $existing_cust['id'];
                            $customer_name = $existing_cust['name'];
                            $customer_phone = $existing_cust['phone'];
                        }
                    }

                    if (!$customer_id) {
                        // Create the new customer!
                        $customer_data = [
                            'name' => $customer_name,
                            'phone' => $customer_phone,
                            'status' => 'active',
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        $customer_id = db_insert('customers', $customer_data);
                    }
                }

                $request_data = [
                    'request_number' => $request_number,
                    'sr_user_id' => get_current_user_id(),
                    'customer_id' => $customer_id,
                    'customer_name' => $customer_name,
                    'customer_phone' => $customer_phone,
                    'items' => json_encode($items),
                    'total_amount' => $subtotal,
                    'discount' => $discount,
                    'net_amount' => $total_amount,
                    'status' => 'pending',
                    'notes' => $notes
                ];

                $request_id = db_insert('sales_requests', $request_data);

                if (!$request_id) {
                    throw new Exception('Failed to create sales request');
                }

                db_commit();
                log_activity(get_current_user_id(), 'sales_request_create', "Created Sales Request $request_number");
                redirect_with_message('request-list.php', 'Sales Request created successfully!', 'success');
            } catch (Exception $e) {
                db_rollback();
                $errors[] = $e->getMessage();
            }
        }
    } else {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    }
}

// Get data
$customers = db_query("SELECT id, name, phone FROM customers ORDER BY name ASC") ?: [];
$products = db_query("SELECT id, name, code, selling_price, stock_quantity FROM products WHERE status = 'active' ORDER BY name ASC") ?: [];

$page_title = 'Create Sales Request';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    /* Product Search */
    .search-wrapper {
        position: relative;
    }

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1050;
        background: var(--bg-primary, #fff);
        border: 1px solid var(--border-color, #ddd);
        border-radius: 0 0 8px 8px;
        max-height: 320px;
        overflow-y: auto;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        display: none;
    }

    .search-item {
        padding: 10px 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        border-bottom: 1px solid var(--border-color, #f0f0f0);
        transition: background 0.15s;
    }

    .search-item:hover {
        background: rgba(78, 115, 223, 0.06);
    }

    .search-item .product-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: linear-gradient(135deg, #4e73df, #224abe);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }

    .search-item .product-info {
        flex: 1;
    }

    .search-item .product-info .name {
        font-weight: 600;
        font-size: 13px;
        color: #1a1a2e;
    }

    .search-item .product-info .meta {
        font-size: 11px;
        color: #555;
    }

    /* Cart Table */
    .cart-table {
        border-radius: 8px;
        overflow: hidden;
    }

    .cart-table thead th {
        background: linear-gradient(135deg, #4e73df, #224abe);
        color: #fff;
        border: none;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 10px 12px;
    }

    .cart-table tbody td {
        vertical-align: middle;
        padding: 8px 12px;
    }

    .cart-table .qty-control {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .cart-table .qty-control button {
        width: 28px;
        height: 28px;
        padding: 0;
        font-size: 12px;
        border-radius: 6px;
    }

    .cart-table .qty-control input {
        width: 50px;
        text-align: center;
        font-weight: 600;
    }

    /* Summary Card */
    .summary-card .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 14px;
    }

    .summary-card .total-row {
        font-size: 20px;
        font-weight: 700;
        color: #4e73df;
        border-top: 2px solid var(--border-color);
        padding-top: 12px;
        margin-top: 8px;
    }

    /* Quick Products Grid */
    .quick-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }

    .quick-item {
        padding: 10px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 12px;
        position: relative;
    }

    .quick-item:hover {
        border-color: #4e73df;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(78, 115, 223, 0.15);
    }

    .quick-item .q-name {
        font-weight: 600;
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .quick-item .q-price {
        color: #4e73df;
        font-weight: 700;
    }


    /* Empty Cart */
    .empty-cart {
        text-align: center;
        padding: 40px 20px;
    }

    .empty-cart i {
        font-size: 48px;
        color: #ddd;
        margin-bottom: 10px;
    }

    /* Item count badge */
    .item-count {
        background: #4e73df;
        color: #fff;
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: 6px;
    }
</style>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <strong>Error!</strong>
        <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="requestForm" accept-charset="UTF-8">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="items_json" id="items_json">

    <div class="row">
        <!-- Left Column: Main Content -->
        <div class="col-lg-8">

            <!-- Customer Section -->
            <div class="card shadow-sm mb-3">
                <div class="card-header py-3" style="border-left: 4px solid #1cc88a;">
                    <h6 class="m-0 fw-bold text-success"><i class="fas fa-user-tag"></i> Customer Info</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Select Customer</label>
                            <div class="input-group">
                                <select name="customer_id" id="customer_id" class="form-select select2">
                                    <option value="">Walk-in Customer</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-phone="<?= htmlspecialchars($c['phone']) ?>">
                                            <?= htmlspecialchars($c['name']) ?><?= $c['phone'] ? ' - ' . $c['phone'] : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal" title="Add New Customer">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Customer Name</label>
                            <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="Walk-in" readonly tabindex="-1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Phone</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="text" name="customer_phone" id="customer_phone" class="form-control" placeholder="N/A" readonly tabindex="-1">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Search -->
            <div class="card shadow-sm mb-3">
                <div class="card-header py-3" style="border-left: 4px solid #4e73df;">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-search"></i> Search & Add Products</h6>
                </div>
                <div class="card-body">
                    <div class="search-wrapper">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-primary text-white"><i class="fas fa-barcode"></i></span>
                            <input type="text" id="product_search" class="form-control"
                                placeholder="Search by name, code, or scan barcode..." autofocus autocomplete="off">
                        </div>
                        <div id="search_results" class="search-results"></div>
                    </div>
                </div>
            </div>

            <!-- Cart -->
            <div class="card shadow-sm mb-3">
                <div class="card-header py-3 d-flex justify-content-between align-items-center" style="border-left: 4px solid #f6c23e;">
                    <h6 class="m-0 fw-bold text-warning"><i class="fas fa-shopping-cart"></i> Cart <span class="item-count" id="cartCount">0</span></h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="clearCartBtn" style="display:none;">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover cart-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width:5%">#</th>
                                    <th style="width:35%">Product</th>
                                    <th style="width:18%">Price</th>
                                    <th style="width:18%">Quantity</th>
                                    <th style="width:14%">Subtotal</th>
                                    <th style="width:10%"></th>
                                </tr>
                            </thead>
                            <tbody id="cart_items">
                                <tr id="empty_cart">
                                    <td colspan="6">
                                        <div class="empty-cart">
                                            <i class="fas fa-cart-plus d-block"></i>
                                            <p class="text-muted mb-0">No items added yet.<br>Search above or use Quick Select.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <label class="form-label fw-bold"><i class="fas fa-sticky-note text-muted"></i> Notes for Admin/Cashier</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional: Special instructions, delivery notes, etc."></textarea>
                </div>
            </div>
        </div>

        <!-- Right Column: Summary & Quick Products -->
        <div class="col-lg-4">
            <!-- Summary -->
            <div class="card shadow-sm mb-3 summary-card">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 fw-bold"><i class="fas fa-calculator"></i> Order Summary</h6>
                </div>
                <div class="card-body">
                    <div class="summary-row">
                        <span>Items</span>
                        <strong id="totalItems">0</strong>
                    </div>
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span id="display_subtotal"><?= APP_CURRENCY_SYMBOL ?>0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Discount</span>
                        <div class="input-group input-group-sm" style="width: 140px;">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" name="discount" id="discount" class="form-control" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    <div class="summary-row total-row">
                        <span>Net Total</span>
                        <span id="display_total"><?= APP_CURRENCY_SYMBOL ?>0.00</span>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                        <a href="request-list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Select -->
            <div class="card shadow-sm">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-bolt"></i> Quick Select</h6>
                </div>
                <div class="card-body" style="max-height: 350px; overflow-y: auto; padding: 10px;">
                    <div class="quick-grid">
                        <?php foreach (array_slice($products, 0, 12) as $p): ?>
                            <div class="quick-item"
                                data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>"
                                data-code="<?= htmlspecialchars($p['code']) ?>"
                                data-price="<?= $p['selling_price'] ?>" data-stock="<?= $p['stock_quantity'] ?>">
                                <div class="q-name" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="q-price"><?= APP_CURRENCY_SYMBOL ?><?= number_format($p['selling_price'], 0) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCustomerForm">
                <div class="modal-body">
                    <div id="customerModalError" class="alert alert-danger" style="display:none;"></div>

                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea name="address" class="form-control" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveCustomerBtn">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let cart = [];
    let products = <?= json_encode($products) ?>;
    const CS = '<?= APP_CURRENCY_SYMBOL ?>';

    $(document).ready(function() {
        // Customer select auto-fill
        $('#customer_id').change(function() {
            const opt = $(this).find(':selected');
            if (opt.val()) {
                $('#customer_name').val(opt.data('name'));
                $('#customer_phone').val(opt.data('phone'));
            } else {
                $('#customer_name').val('');
                $('#customer_phone').val('');
            }
        });

        // Product search with debounce
        let searchTimer;
        $('#product_search').on('input', function() {
            clearTimeout(searchTimer);
            const q = $(this).val().toLowerCase().trim();
            if (q.length < 1) {
                $('#search_results').hide();
                return;
            }
            searchTimer = setTimeout(() => searchProducts(q), 150);
        }).on('keydown', function(e) {
            // Enter key to add first result
            if (e.key === 'Enter') {
                e.preventDefault();
                const first = $('#search_results .search-item:first');
                if (first.length) first.click();
            }
        });

        // Click product result
        $(document).on('click', '.search-item', function() {
            const p = $(this).data('product');
            if (p) {
                addToCart(p);
                $('#product_search').val('').focus();
                $('#search_results').hide();
            }
        });

        // Quick add
        $('.quick-item').click(function() {
            const p = {
                id: $(this).data('id'),
                name: $(this).data('name'),
                code: $(this).data('code'),
                selling_price: $(this).data('price'),
                stock_quantity: 9999
            };
            addToCart(p);
        });

        // Close search on outside click
        $(document).click(function(e) {
            if (!$(e.target).closest('.search-wrapper').length) $('#search_results').hide();
        });

        // Discount change
        $('#discount').on('input', updateTotals);

        // Clear cart
        $('#clearCartBtn').click(function() {
            if (confirm('Clear all items from cart?')) {
                cart = [];
                updateCart();
            }
        });

        // Select2
        if ($.fn.select2) $('#customer_id').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Search customer...'
        });

        // Form submit
        $('#requestForm').submit(function(e) {
            if (cart.length === 0) {
                e.preventDefault();
                alert('অনুগ্রহ করে কমপক্ষে একটি পণ্য যুক্ত করুন!');
                return false;
            }
            // Set items JSON
            var itemsJson = JSON.stringify(cart);
            $('#items_json').val(itemsJson);
            console.log('Submitting cart:', itemsJson);

            // Set default customer name if empty
            if (!$('#customer_name').val().trim()) {
                $('#customer_name').val('Walk-in');
            }

            // Disable button to prevent double submit
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');
            return true;
        });

        // Quick Add Customer AJAX
        $('#addCustomerForm').submit(function(e) {
            e.preventDefault();
            const $btn = $('#saveCustomerBtn');
            const $error = $('#customerModalError');

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            $error.hide();

            $.ajax({
                url: '../../api/customers/add.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.status) {
                        // Success: close modal to add option to dropdown
                        $('#addCustomerModal').modal('hide');
                        $('#addCustomerForm')[0].reset();

                        // Create and append the new option
                        const customName = res.customer.name;
                        const customPhone = res.customer.phone || '';
                        const optText = customName + (customPhone ? ' - ' + customPhone : '');

                        const newOption = new Option(optText, res.customer.id, true, true);
                        $(newOption).attr('data-name', customName);
                        $(newOption).attr('data-phone', customPhone);

                        $('#customer_id').append(newOption).trigger('change');

                        // Fire a success toast if you have a toast library, else alert
                        alert('Customer added successfully!');
                    } else {
                        $error.text(res.message).show();
                    }
                },
                error: function() {
                    $error.text('An error occurred communicating with the server.').show();
                },
                complete: function() {
                    $btn.prop('disabled', false).html('Save Customer');
                }
            });
        });
    });

    function searchProducts(q) {
        const results = products.filter(p =>
            (p.name && p.name.toLowerCase().includes(q)) ||
            (p.code && p.code.toLowerCase().includes(q))
        );

        if (results.length === 0) {
            $('#search_results').html('<div class="search-item"><span class="text-muted">No products found</span></div>').show();
            return;
        }

        let html = '';
        results.slice(0, 8).forEach(p => {
            html += `<div class="search-item" data-product='${JSON.stringify(p).replace(/'/g, "&#39;")}'>
            <div class="product-icon"><i class="fas fa-box"></i></div>
            <div class="product-info">
                <div class="name">${p.name}</div>
                <div class="meta">Code: ${p.code} &bull; ${CS}${parseFloat(p.selling_price).toLocaleString()}</div>
            </div>
        </div>`;
        });

        if (results.length > 8) {
            html += `<div class="search-item text-muted text-center"><small>+${results.length - 8} more results...</small></div>`;
        }

        $('#search_results').html(html).show();
    }

    function addToCart(product) {
        const idx = cart.findIndex(i => Number(i.product_id) === Number(product.id));

        if (idx !== -1) {
            cart[idx].quantity++;
        } else {
            cart.push({
                product_id: Number(product.id),
                product_name: product.name,
                product_code: product.code,
                unit_price: parseFloat(product.selling_price),
                quantity: 1
            });
        }
        updateCart();
    }

    function removeFromCart(index) {
        cart.splice(index, 1);
        updateCart();
    }

    function changeQty(index, delta) {
        let newQty = cart[index].quantity + delta;
        if (newQty <= 0) {
            removeFromCart(index);
            return;
        }
        cart[index].quantity = newQty;
        updateCart();
    }

    function setQty(index, val) {
        val = parseInt(val) || 0;
        if (val <= 0) {
            removeFromCart(index);
            return;
        }
        cart[index].quantity = val;
        updateCart();
    }

    function setPrice(index, val) {
        cart[index].unit_price = parseFloat(val) || 0;
        updateCart();
    }

    function updateCart() {
        const body = $('#cart_items');
        const clearBtn = $('#clearCartBtn');

        if (cart.length === 0) {
            body.html(`<tr><td colspan="6"><div class="empty-cart"><i class="fas fa-cart-plus d-block"></i><p class="text-muted mb-0">No items added yet.<br>Search above or use Quick Select.</p></div></td></tr>`);
            clearBtn.hide();
        } else {
            let html = '';
            cart.forEach((item, i) => {
                const sub = item.quantity * item.unit_price;
                html += `<tr>
                <td class="text-center fw-bold text-muted">${i + 1}</td>
                <td>
                    <strong>${item.product_name}</strong><br>
                    <small class="text-muted"><i class="fas fa-barcode"></i> ${item.product_code}</small>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">${CS}</span>
                        <input type="number" class="form-control" value="${item.unit_price.toFixed(2)}" step="0.01" min="0" onchange="setPrice(${i}, this.value)">
                    </div>
                </td>
                <td>
                    <div class="qty-control">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="changeQty(${i}, -1)"><i class="fas fa-minus"></i></button>
                        <input type="number" class="form-control form-control-sm" value="${item.quantity}" min="1" onchange="setQty(${i}, this.value)">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="changeQty(${i}, 1)"><i class="fas fa-plus"></i></button>
                    </div>
                </td>
                <td class="fw-bold">${CS}${sub.toFixed(2)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${i})" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>`;
            });
            body.html(html);
            clearBtn.show();
        }

        $('#cartCount').text(cart.length);
        updateTotals();
    }

    function updateTotals() {
        let subtotal = 0;
        let totalItems = 0;
        cart.forEach(item => {
            subtotal += item.quantity * item.unit_price;
            totalItems += item.quantity;
        });
        const discount = parseFloat($('#discount').val()) || 0;
        const total = Math.max(0, subtotal - discount);

        $('#totalItems').text(totalItems);
        $('#display_subtotal').text(CS + subtotal.toFixed(2));
        $('#display_total').text(CS + total.toFixed(2));
    }
</script>
