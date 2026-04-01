<?php
/**
 * Barcode Generator Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get product for barcode generation
$product_id = get_param('id', 0);
$product = null;

if ($product_id) {
    $product = db_select_one('products', ['id' => $product_id]);
}

// Get all products for selection
$products = db_select('products', ['status' => 'active'], '*', 'name ASC');

$page_title = 'Barcode Generator';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <!-- Product Selection -->
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Select Product</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="form-group mb-3">
                        <label>Product <span class="text-danger">*</span></label>
                        <select name="id" class="form-control" required onchange="this.form.submit()">
                            <option value="">Select Product</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $product_id == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                
                <?php if ($product): ?>
                    <div class="mt-4">
                        <h6>Product Details:</h6>
                        <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($product['name']) ?></p>
                        <p class="mb-1"><strong>Code:</strong> <?= htmlspecialchars($product['code']) ?></p>
                        <p class="mb-1"><strong>Barcode:</strong> <?= htmlspecialchars(!empty($product['barcode']) ? $product['barcode'] : $product['code']) ?></p>
                        <p class="mb-1"><strong>Price:</strong> <?= format_currency($product['selling_price']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Barcode Display -->
    <div class="col-md-8">
        <?php if ($product): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Generated Barcode</h6>
                </div>
                <div class="card-body text-center">
                    <?php
                    $barcode_value = !empty($product['barcode']) ? $product['barcode'] : $product['code'];
                    ?>
                    
                    <!-- Barcode rendered as SVG for reliability -->
                    <div id="barcode-container" class="mb-4" style="background: #fff; padding: 15px; display: inline-block; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                        <svg id="barcode-svg"></svg>
                    </div>
                    
                    <div id="barcode-error" style="display:none;" class="alert alert-danger mb-3">
                        <i class="fas fa-exclamation-triangle"></i> Barcode library failed to load. Check internet connection.
                    </div>
                    
                    <div class="product-info mb-3">
                        <h5><?= htmlspecialchars($product['name']) ?></h5>
                        <p class="mb-1">Code: <?= htmlspecialchars($product['code']) ?></p>
                        <h4 class="text-primary"><?= format_currency($product['selling_price']) ?></h4>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-2 align-items-center flex-wrap">
                        <div class="input-group" style="width: 150px;">
                            <span class="input-group-text">Qty</span>
                            <input type="number" id="print-qty" class="form-control" value="1" min="1" max="100">
                        </div>
                        <button type="button" class="btn btn-primary" onclick="printBarcode()">
                            <i class="fas fa-print"></i> Print Barcode
                        </button>
                        <a href="barcode-print.php?id=<?= $product['id'] ?>" class="btn btn-success">
                            <i class="fas fa-tags"></i> Print Multiple Labels
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Please select a product to generate barcode.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<?php if ($product): ?>
<script>
// Load JsBarcode dynamically with error handling
(function() {
    var script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';
    script.onload = function() {
        generateBarcode();
    };
    script.onerror = function() {
        document.getElementById('barcode-error').style.display = 'block';
        document.getElementById('barcode-container').style.display = 'none';
    };
    document.head.appendChild(script);
})();

function generateBarcode() {
    try {
        JsBarcode("#barcode-svg", "<?= htmlspecialchars($barcode_value) ?>", {
            format: "CODE128",
            width: 2,
            height: 100,
            displayValue: true,
            fontSize: 18,
            margin: 10,
            background: "#ffffff",
            lineColor: "#000000",
            textMargin: 5,
            font: "monospace"
        });
    } catch(e) {
        console.error('Barcode error:', e);
        document.getElementById('barcode-error').style.display = 'block';
        document.getElementById('barcode-error').innerHTML = 
            '<i class="fas fa-exclamation-triangle"></i> Error: ' + e.message;
    }
}

function printBarcode() {
    var qty = parseInt(document.getElementById('print-qty').value) || 1;
    var barcodeEl = document.getElementById('barcode-container').innerHTML;
    var productInfo = document.querySelector('.product-info').innerHTML;
    
    var printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Print Barcode</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body{margin:0;padding:10px;font-family:Arial,sans-serif;}');
    printWindow.document.write('.barcode-item{display:inline-block;text-align:center;padding:10px;margin:5px;border:1px dashed #ccc;page-break-inside:avoid;}');
    printWindow.document.write('.barcode-item h5{margin:5px 0;font-size:12px;}');
    printWindow.document.write('.barcode-item p{margin:2px 0;font-size:10px;}');
    printWindow.document.write('.barcode-item h4{margin:3px 0;font-size:14px;color:#333;}');
    printWindow.document.write('svg{max-width:200px;height:auto;}');
    printWindow.document.write('</style></head><body>');
    
    for (var i = 0; i < qty; i++) {
        printWindow.document.write('<div class="barcode-item">');
        printWindow.document.write(barcodeEl);
        printWindow.document.write(productInfo);
        printWindow.document.write('</div>');
    }
    
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    setTimeout(function() { printWindow.print(); }, 500);
}
</script>
<?php endif; ?>
