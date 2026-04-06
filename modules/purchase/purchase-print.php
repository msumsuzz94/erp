<?php
/**
 * Purchase Invoice Page
 * Printable invoice for purchase
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$purchase_id = get_param('id', 0);

if (!$purchase_id) {
    die('Invalid purchase ID');
}

// Get purchase details
$sql = "SELECT p.*, s.name as supplier_name, s.phone as supplier_phone, 
               s.email as supplier_email, s.address as supplier_address
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.id = ?";
$purchase = db_query($sql, [$purchase_id]);

if (empty($purchase)) {
    die('Purchase not found');
}

$purchase = $purchase[0];

// Get purchase items
$sql = "SELECT pi.*, p.name as product_name, p.code as product_code, p.description as product_description,
               p.warranty_duration, p.warranty_period, p.has_serial
        FROM purchase_items pi
        LEFT JOIN products p ON pi.product_id = p.id
        WHERE pi.purchase_id = ?
        ORDER BY pi.id";
$items = db_query($sql, [$purchase_id]);

// Get serial numbers for this purchase
$serials_raw = db_query("SELECT * FROM product_serials WHERE purchase_id = ?", [$purchase_id]);
$product_serials = [];
foreach ($serials_raw as $s) {
    $product_serials[$s['product_id']][] = $s['serial_number'];
}

// Get company settings
$settings = [];
$settings_raw = db_query("SELECT setting_key, setting_value FROM settings");
foreach ($settings_raw as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get payment history
$sql = "SELECT * FROM purchase_payments 
        WHERE purchase_id = ?
        ORDER BY payment_date ASC";
$payments = db_query($sql, [$purchase_id]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Invoice #<?= htmlspecialchars($purchase['purchase_number']) ?></title>
    <?php
    // Get invoice settings for standardized branding
    $invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
    if (!$invoice_settings) {
        $invoice_settings = [
            'company_name' => $settings['company_name'] ?? '',
            'company_address' => $settings['company_address'] ?? '',
            'company_phone' => $settings['company_phone'] ?? '',
            'company_email' => $settings['company_email'] ?? '',
            'company_website' => '',
            'company_logo' => 'assets/images/logo.png',
            'company_slogan' => ''
        ];
    }
    ?>
    <style>
        @media print {
            @page { margin: 0.25cm; size: auto; }
            html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
            .no-print { display: none !important; }
            
            .print-container { 
                width: 100%; 
                padding: 0;
                margin: 0;
                box-sizing: border-box; 
                font-family: 'Segoe UI', Arial, sans-serif; 
                font-size: 11px; 
            }
            
            /* 3-Column Header */
            .header-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
            .header-table td { vertical-align: middle; border: none !important; }
            .logo-cell { width: 10%; text-align: left; }
            .logo-cell img { max-width: 120px; height: auto; display: block; }
            
            .title-cell { width: 65%; text-align: center; }
            .company-name { font-size: <?= $invoice_settings['company_name_font_size'] ?? 24 ?>px; font-weight: 900; color: #2C3E50; margin: 0; text-transform: uppercase; border-bottom: 2px solid #333; display: inline-block; line-height: 1.1; }
            .company-slogan { font-size: <?= $invoice_settings['company_slogan_font_size'] ?? 12 ?>px; color: #E67E22; font-weight: bold; margin-top: 2px; text-transform: uppercase; letter-spacing: 1px; }
            
            .info-cell { width: 25%; text-align: right; line-height: 1.3; font-size: 10px; }
            .info-cell p { margin: 0; }
            
            .invoice-main-title { text-align: center; font-size: 18px; color: #333; margin: 15px 0; font-weight: bold; border-top: 2px solid #333; border-bottom: 2px solid #333; padding: 5px 0; }

            /* Simple Bordered Table */
            .print-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .print-table th, .print-table td { border: 1px solid #333 !important; padding: 6px; text-align: left; }
            .print-table th { background: #f2f2f2 !important; font-weight: bold; text-transform: uppercase; }
            .text-end { text-align: right !important; }
            
            /* Fixed Footer at bottom of EVERY page */
            .print-footer { 
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                border-top: 1px solid #333; 
                padding-top: 5px; 
                display: block;
                width: 100%;
                font-size: 10px; 
                background: #fff;
                z-index: 9999;
            }
            .footer-left { float: left; width: 50%; font-weight: bold; text-align: left; }
            .footer-right { float: right; width: 50%; text-align: right; }
            .clearfix::after { content: ""; clear: both; display: table; }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .invoice-header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .invoice-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .info-block {
            width: 48%;
        }
        .info-block h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #555;
        }
        .info-block p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        table th {
            background-color: #f4f4f4;
            font-weight: bold;
            text-align: left;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary-table {
            margin-left: auto;
            width: 300px;
            margin-top: 20px;
        }
        .summary-table td {
            padding: 5px 10px;
        }
        .total-row {
            font-weight: bold;
            font-size: 14px;
            background-color: #f4f4f4;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #777;
        }
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            z-index: 10000;
        }
        .print-btn:hover {
            background: #45a049;
        }
        .item-description {
            font-size: 11px;
            color: #555;
            margin-top: 4px;
            white-space: pre-line;
            line-height: 1.3;
        }
        .payment-history {
            margin-top: 30px;
        }
        .payment-history h3 {
            font-size: 14px;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print Invoice</button>

    <div class="print-container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <?php 
                    $logo_url = !empty($invoice_settings['company_logo']) ? $invoice_settings['company_logo'] : 'assets/images/logo.png';
                    if (!preg_match('~^(?:f|ht)tps?://~i', $logo_url)) {
                        $logo_url = BASE_URL . '/' . ltrim($logo_url, '/');
                    }
                    ?>
                    <img src="<?= $logo_url ?>" alt="Logo">
                </td>
                <td class="title-cell">
                    <h1 class="company-name"><?= htmlspecialchars($invoice_settings['company_name'] ?? '') ?></h1><br>
                    <div class="company-slogan"><?= htmlspecialchars($invoice_settings['company_slogan'] ?? '') ?></div>
                </td>
                <td class="info-cell">
                    <p>Address: <?= htmlspecialchars($invoice_settings['company_address'] ?? '') ?></p>
                    <p>Tel: <?= htmlspecialchars($invoice_settings['company_phone'] ?? '') ?></p>
                    <p>Email: <?= htmlspecialchars($invoice_settings['company_email'] ?? '') ?></p>
                    <p>Website: <?= htmlspecialchars($invoice_settings['company_website'] ?? '') ?></p>
                </td>
            </tr>
        </table>

        <div class="invoice-main-title">PURCHASE INVOICE</div>

        <div class="invoice-info">
            <div class="info-block">
                <h3>Supplier Information</h3>
                <p><strong><?= htmlspecialchars($purchase['supplier_name']) ?></strong></p>
                <?php if (!empty($purchase['supplier_phone'])): ?>
                    <p>Phone: <?= htmlspecialchars($purchase['supplier_phone']) ?></p>
                <?php endif; ?>
                <?php if (!empty($purchase['supplier_email'])): ?>
                    <p>Email: <?= htmlspecialchars($purchase['supplier_email']) ?></p>
                <?php endif; ?>
                <?php if (!empty($purchase['supplier_address'])): ?>
                    <p>Address: <?= htmlspecialchars($purchase['supplier_address']) ?></p>
                <?php endif; ?>
            </div>

            <div class="info-block text-right">
                <h3>Purchase Details</h3>
                <p><strong>Invoice #:</strong> <?= htmlspecialchars($purchase['purchase_number']) ?></p>
                <p><strong>Date:</strong> <?= format_date($purchase['purchase_date']) ?></p>
                <p><strong>Status:</strong> <?= ucfirst($purchase['status']) ?></p>
                <p><strong>Payment Status:</strong> <?= ucfirst($purchase['payment_status']) ?></p>
            </div>
        </div>

        <table class="print-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 35%;">Product</th>
                    <th style="width: 15%;">Code</th>
                    <th class="text-end" style="width: 10%;">Qty</th>
                    <th class="text-end" style="width: 15%;">Unit Price</th>
                    <th class="text-end" style="width: 10%;">Tax</th>
                    <th class="text-end" style="width: 15%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No items found</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <div style="font-weight: bold;"><?= htmlspecialchars($item['product_name']) ?></div>
                                
                                <?php if (!empty($product_serials[$item['product_id']])): ?>
                                    <div class="item-description" style="margin-top: 0; color: #000; font-weight: bold;">Serials: <?= implode(', ', array_map('htmlspecialchars', $product_serials[$item['product_id']])) ?></div>
                                <?php endif; ?>

                                <?php if (!empty($item['warranty_duration'])): ?>
                                    <div class="item-description" style="margin-top: 0; color: #000; font-weight: bold;">Warranty: <?= htmlspecialchars($item['warranty_duration'] . ' ' . $item['warranty_period']) ?></div>
                                <?php endif; ?>

                                <?php 
                                $description = !empty($item['description']) ? $item['description'] : ($item['product_description'] ?? '');
                                if (!empty($description)): 
                                ?>
                                    <div class="item-description"><?= htmlspecialchars($description) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['product_code'] ?? 'N/A') ?></td>
                            <td class="text-end"><?= number_format($item['quantity'], 2) ?></td>
                            <td class="text-end"><?= format_currency($item['unit_price']) ?></td>
                            <td class="text-end"><?= format_currency($item['tax']) ?></td>
                            <td class="text-end"><?= format_currency($item['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="summary-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right"><?= format_currency($purchase['total_amount'] - $purchase['tax_amount'] + $purchase['discount']) ?></td>
            </tr>
            <tr>
                <td>Tax:</td>
                <td class="text-right"><?= format_currency($purchase['tax_amount']) ?></td>
            </tr>
            <?php if ($purchase['discount'] > 0): ?>
            <tr>
                <td>Discount:</td>
                <td class="text-right">-<?= format_currency($purchase['discount']) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td>Total Amount:</td>
                <td class="text-right"><?= format_currency($purchase['total_amount']) ?></td>
            </tr>
            <tr>
                <td>Paid Amount:</td>
                <td class="text-right" style="color: green;"><?= format_currency($purchase['paid_amount']) ?></td>
            </tr>
            <tr class="total-row">
                <td>Due Amount:</td>
                <td class="text-right" style="color: red;"><?= format_currency($purchase['due_amount']) ?></td>
            </tr>
        </table>

        <?php if (!empty($payments)): ?>
        <div class="payment-history">
            <h3>Payment History</h3>
            <table class="print-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Date</th>
                        <th style="width: 25%;">Method</th>
                        <th style="width: 25%;">Reference</th>
                        <th class="text-right" style="width: 25%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= format_date($payment['payment_date']) ?></td>
                            <td><?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?></td>
                            <td><?= htmlspecialchars($payment['reference'] ?? '-') ?></td>
                            <td class="text-right"><?= format_currency($payment['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($purchase['notes'])): ?>
        <div style="margin-top: 30px;">
            <h3>Notes:</h3>
            <p><?= nl2br(htmlspecialchars($purchase['notes'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>

    <script>
        // Auto-open print dialog
        // window.print();
    </script>
</body>
</html>
