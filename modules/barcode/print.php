<?php
/**
 * Bulk Barcode Print Page
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Allow either POST from bulk generate, or GET from direct product links
$product_ids = [];
$barcode_type = 'code128';
$quantity = 1;
$show_name = 1;
$show_price = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid request or CSRF token expired.';
        header('Location: generate.php');
        exit;
    }
    
    $product_ids = $_POST['products'] ?? [];
    $barcode_type = $_POST['barcode_type'] ?? 'code128';
    $quantity = (int)($_POST['quantity'] ?? 1);
    $show_name = (int)($_POST['show_name'] ?? 1);
    $show_price = (int)($_POST['show_price'] ?? 1);
} else {
    // Check for direct GET product ID
    $pid = $_GET['id'] ?? $_GET['product_id'] ?? null;
    if ($pid) {
        $product_ids = [(int)$pid];
    }
}

if (empty($product_ids)) {
    $_SESSION['error'] = 'No products selected.';
    header('Location: generate.php');
    exit;
}

if ($quantity < 1) $quantity = 1;

// Fetch products
$placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
$products = db_query("SELECT * FROM products WHERE id IN ($placeholders)", $product_ids);

if (empty($products)) {
    $_SESSION['error'] = 'Selected products not found in the database.';
    header('Location: generate.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Barcodes</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <!-- Include QRCode.js if needed (JsBarcode doesn't do QR natively by default, but we'll try to handle it or fallback) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #fff;
        }
        .barcode-labels {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-start;
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
            font-size: 14px;
            margin-bottom: 5px;
            color: #000;
        }
        .product-price {
            font-size: 14px;
            font-weight: bold;
            color: #000;
            margin-top: 5px;
        }
        .barcode-svg-container {
            display: inline-block;
            margin-bottom: 5px;
        }
        .barcode-svg-container img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }
        .qr-code {
            display: flex;
            justify-content: center;
            margin: 5px 0;
        }
        
        .no-print {
            margin-bottom: 20px;
        }
        .btn {
            background: #4e73df;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .barcode-label { border: 1px solid #000; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn" onclick="window.print()"><i class="fas fa-print"></i> Print Labels</button>
    <button class="btn" onclick="window.close()" style="background: #e74a3b; margin-left: 10px;">Close Window</button>
</div>

<div class="barcode-labels">
    <?php foreach ($products as $product): ?>
        <?php for ($i = 0; $i < $quantity; $i++): ?>
            <div class="barcode-label">
                <?php if ($show_name): ?>
                    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                <?php endif; ?>
                
                <?php
                // Determine barcode value with fallback
                $code = '';
                foreach (['barcode', 'code', 'sku', 'product_code'] as $f) {
                    if (!empty($product[$f])) { $code = $product[$f]; break; }
                }
                if (empty($code)) {
                    $code = 'BMS' . str_pad($product['id'], 6, '0', STR_PAD_LEFT);
                }
                if ($barcode_type === 'qr'): ?>
                    <div class="qr-code" data-code="<?= htmlspecialchars($code) ?>"></div>
                    <div class="product-name" style="font-size: 11px; margin-top:2px;"><?= htmlspecialchars($code) ?></div>
                <?php else: ?>
                    <div class="barcode-svg-container">
                        <svg class="barcode" data-code="<?= htmlspecialchars($code) ?>" data-format="<?= htmlspecialchars(strtoupper($barcode_type)) ?>"></svg>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_price): ?>
                    <div class="product-price"><?= format_currency($product['selling_price']) ?></div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    <?php endforeach; ?>
</div>

<script>
$(document).ready(function() {
    // Generate JSBarcodes
    document.querySelectorAll('.barcode').forEach(function(el) {
        var code = el.getAttribute('data-code');
        var format = el.getAttribute('data-format') || 'CODE128';
        if (!code || code.trim() === '') return;
        
        // Ensure EAN13 validity
        if(format === 'EAN13' && code.length !== 12 && code.length !== 13) {
            format = 'CODE128';
        }

        try {
            JsBarcode(el, code, {
                format: format,
                width: 1.5,
                height: 50,
                displayValue: true,
                fontSize: 12,
                margin: 5,
                background: "#ffffff",
                lineColor: "#000000"
            });
        } catch(e) {
            try {
                JsBarcode(el, code, { format: 'CODE128', width: 1.5, height: 50, displayValue: true, fontSize: 12, margin: 5, background: '#ffffff', lineColor: '#000000' });
            } catch(e2) {
                el.innerHTML = '<text x="50%" y="50%" text-anchor="middle" fill="red" font-size="10">Invalid</text>';
            }
        }
    });
    
    // Generate QR Codes
    $('.qr-code').each(function() {
        var code = $(this).data('code');
        new QRCode(this, {
            text: code,
            width: 80,
            height: 80,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
    });
});
</script>

</body>
</html>
