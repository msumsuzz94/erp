<?php
/**
 * Barcode Generate Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$products = db_select('products', ['status' => 'active'], '*', 'name ASC');

$page_title = 'Generate Barcode';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Products for Barcode</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="print.php" target="_blank">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <div class="form-group mb-3">
                <label>Select Products</label>
                <select name="products[]" class="form-control select2" multiple required>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= $product['id'] ?>">
                            <?= htmlspecialchars($product['name']) ?> (<?= $product['code'] ?>) - <?= htmlspecialchars($product['barcode'] ?? 'No Barcode') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Hold Ctrl to select multiple products</small>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Barcode Type</label>
                        <select name="barcode_type" class="form-control">
                            <option value="code128">Code 128</option>
                            <option value="ean13">EAN-13</option>
                            <option value="qr">QR Code</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Quantity per Product</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1" max="100">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Show Product Name</label>
                        <select name="show_name" class="form-control">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Show Price</label>
                        <select name="show_price" class="form-control">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-print"></i> Generate & Print</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$('.select2').select2({
    theme: 'bootstrap-5',
    placeholder: 'Select products'
});
</script>
