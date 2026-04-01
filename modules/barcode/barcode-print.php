<?php
/**
 * Barcode Print Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get product and quantity
$product_id = get_param('id', 0);
$quantity = get_param('quantity', 1);

$product = null;
if ($product_id) {
    $product = db_select_one('products', ['id' => $product_id]);
}

// Get all products for selection
$products = db_select('products', ['status' => 'active'], '*', 'name ASC');

$page_title = 'Print Barcode Labels';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Product and Quantity</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group mb-3">
                        <label>Product <span class="text-danger">*</span></label>
                        <select name="id" class="form-control" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $product_id == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label>Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" min="1" max="100" 
                               value="<?= htmlspecialchars($quantity) ?>" required>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sync"></i> Generate Labels
            </button>
            <?php if ($product): ?>
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Labels
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($product): 
    // Determine barcode value - fallback chain: barcode > code > sku > product_code > auto-generated
    $barcode_value = '';
    foreach (['barcode', 'code', 'sku', 'product_code'] as $field) {
        if (!empty($product[$field])) { $barcode_value = $product[$field]; break; }
    }
    if (empty($barcode_value)) {
        $barcode_value = 'BMS' . str_pad($product['id'], 6, '0', STR_PAD_LEFT); // Auto: BMS000063
    }
?>
    <div class="card shadow mb-4" id="printArea">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Barcode Labels Preview</h6>
        </div>
        <div class="card-body">
            <div class="barcode-labels">
                <?php for ($i = 0; $i < $quantity; $i++): ?>
                    <div class="barcode-label">
                        <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                        <svg class="barcode" data-code="<?= htmlspecialchars($barcode_value) ?>"></svg>
                        <div class="barcode-code"><?= htmlspecialchars($barcode_value) ?></div>
                        <div class="product-price"><?= format_currency($product['selling_price']) ?></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<style>
.barcode-labels {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.barcode-label {
    width: 250px;
    border: 1px dashed #ccc;
    padding: 10px;
    text-align: center;
    page-break-inside: avoid;
    background-color: #ffffff;
    color: #000000;
}

.product-name {
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 5px;
}

.product-price {
    font-size: 14px;
    font-weight: bold;
    color: #333;
    margin-top: 5px;
}



@media print {
    body * {
        visibility: hidden;
    }
    
    #printArea, #printArea * {
        visibility: visible;
    }
    
    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    .card-header {
        display: none;
    }
    
    .barcode-label {
        border: 1px solid #000;
    }
}

.barcode-code {
    font-size: 10px;
    color: #666;
    letter-spacing: 1px;
    font-family: 'Courier New', monospace;
}

/* Force correct barcode rendering in Dark Mode */
.barcode-labels svg.barcode { 
    background-color: #ffffff;
    max-width: 100%;
    height: auto;
    display: block;
    margin: 5px auto;
}
</style>

<?php if ($product): ?>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.barcode').forEach(function(el) {
        var code = el.getAttribute('data-code');
        if (!code || code.trim() === '') {
            el.innerHTML = '<text x="50%" y="50%" text-anchor="middle" fill="red" font-size="12">No barcode</text>';
            return;
        }
        try {
            JsBarcode(el, code, {
                format: "CODE128",
                width: 1.5,
                height: 50,
                displayValue: true,
                fontSize: 12,
                margin: 5,
                background: "#ffffff",
                lineColor: "#000000"
            });
        } catch(e) {
            console.error('Barcode error for code: ' + code, e);
            try {
                JsBarcode(el, code, {
                    format: "CODE39",
                    width: 1.5,
                    height: 50,
                    displayValue: true,
                    fontSize: 12,
                    margin: 5,
                    background: "#ffffff",
                    lineColor: "#000000"
                });
            } catch(e2) {
                el.innerHTML = '<text x="50%" y="50%" text-anchor="middle" fill="red" font-size="10">Invalid code</text>';
            }
        }
    });
});
</script>
<?php endif; ?>
