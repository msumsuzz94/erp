<?php

/**
 * Professional Invoice Print/View Page
 * Matches provide design and includes serial tracking
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$sale_id = (int)get_param('id');

// Get sale details
$sale = db_select_one('sales', ['id' => $sale_id]);
if (!$sale) {
    if (empty($sale_id)) {
        // Typically indicative of a failed insert redirect
        redirect_with_message('../../modules/sales/pos.php', 'System was unable to find or create the invoice. Please ensure "update.sql" has been run in phpMyAdmin.', 'error');
    }
    // Standard fail case
    die('Sale not found. It may have been deleted or the ID is invalid.');
}

// Get customer details
$customer = null;
if ($sale['customer_id']) {
    $customer = db_select_one('customers', ['id' => $sale['customer_id']]);
}

// Get creator details
$creator = db_select_one('users', ['id' => $sale['created_by']]);

// Get sale items with product details
$sql = "SELECT si.*, si.description as item_description, p.name as product_name, p.code as product_code, p.description as product_description, p.warranty_duration, p.warranty_period
        FROM sale_items si
        INNER JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?";
$sale_items = db_query($sql, [$sale_id]);

// Get serial numbers for each item
foreach ($sale_items as &$item) {
    $serials = db_query("SELECT serial_number FROM product_serials WHERE sale_item_id = ?", [$item['id']]);
    $item['serials'] = array_column($serials, 'serial_number');
}

// Get invoice settings
$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$invoice_settings) {
    $invoice_settings = [
        'company_name' => BUSINESS_NAME,
        'company_slogan' => '',
        'company_address' => BUSINESS_ADDRESS,
        'company_phone' => BUSINESS_PHONE,
        'company_email' => BUSINESS_EMAIL,
        'header_color' => '#f39c12',
        'text_color' => '#000000',
        'font_size' => 'medium',
        'template_style' => 'classic',
        'show_print_datetime' => 1,
        'developed_by' => 'CITNBD',
        'show_logo' => 1,
        'show_terms' => 1,
        'terms_and_conditions' => '1. Return within 7 days with original packaging.',
        'invoice_note' => 'Thank you for your business!',
        'footer_text' => 'This is a computer generated invoice'
    ];
}

$header_color = $invoice_settings['header_color'] ?? '#f39c12';
$text_color = $invoice_settings['text_color'] ?? '#000000';
$slogan_color = $invoice_settings['company_slogan_color'] ?? '#E67E22';
$template = $invoice_settings['template_style'] ?? 'classic';
$invoice_margin = $invoice_settings['invoice_margin'] ?? '1cm';
$font_size_map = ['small' => '11px', 'medium' => '13px', 'large' => '15px'];
$body_font_size = $font_size_map[$invoice_settings['font_size'] ?? 'medium'] ?? '13px';

// Get payments for this sale
$payments = db_query("SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY payment_date", [$sale_id]);

// Generate Amount in Words
$amount_in_words = '';
if (($invoice_settings['show_amount_in_words'] ?? 1) && function_exists('convert_number_to_words')) {
    $amount_in_words = convert_number_to_words($sale['total_amount'], $invoice_settings['amount_in_words_prefix'] ?? 'BDT');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars($sale['invoice_number']) ?></title>
    <style>
        @page {
            size: A4;
            margin: <?= $invoice_margin ?>;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #fff !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: <?= $body_font_size ?>;
            line-height: 1.4;
            color: <?= $text_color ?>;
        }

        .invoice-container {
            padding: <?= $invoice_margin ?>;
        }

        .note-editor,
        .note-toolbar,
        .note-statusbar,
        .note-popover {
            display: none !important;
        }

        /* Layout Helpers */
        .row {
            display: flex;
            width: 100%;
        }

        .col-4 {
            width: 33.33%;
        }

        .col-6 {
            width: 50%;
        }

        .col-8 {
            width: 66.66%;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }

        .mb-2 {
            margin-bottom: 10px;
        }

        .mb-4 {
            margin-bottom: 20px;
        }

        /* Header Section */
        .invoice-header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 5px;
            text-align: center;
        }

        .logo-area {
            width: 15%;
            text-align: center;
        }

        .logo-area img {
            max-width: 100%;
            height: auto;
        }

        .company-title-area {
            width: 55%;
            text-align: center;
        }

        .company-name {
            font-size: <?= $invoice_settings['company_name_font_size'] ?? 28 ?>px;
            font-weight: 900;
            color: <?= $header_color ?>;
            margin: 0;
            text-transform: uppercase;
            border-bottom: 2px solid <?= $header_color ?>;
            display: inline-block;
            padding-bottom: 2px;
        }

        .company-slogan {
            font-size: <?= $invoice_settings['company_slogan_font_size'] ?? 14 ?>px;
            color: <?= $slogan_color ?>;
            font-weight: bold;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .invoice-type-area {
            width: 30%;
            text-align: right;
        }

        .invoice-type-label {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .meta-box {
            border: 1px solid #333;
            padding: 5px 10px;
            margin-bottom: 2px;
            display: flex;
            justify-content: space-between;
        }

        .meta-label {
            font-weight: bold;
        }

        /* Info Block */
        .company-info-block {
            border: 1px solid #333;
            padding: 10px;
            margin-bottom: 15px;
            width: 65%;
        }

        .info-row {
            margin-bottom: 2px;
        }

        /* Customer Details */
        .customer-bar {
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            padding: 8px 0;
            margin-bottom: 15px;
            display: flex;
            flex-wrap: wrap;
        }

        .customer-item {
            margin-right: 20px;
            font-size: 14px;
        }

        .customer-label {
            font-weight: bold;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border: 1px solid #333;
        }

        .items-table th {
            border: 1px solid #333;
            padding: 8px;
            font-weight: bold;
            text-transform: uppercase;
            background: <?= $header_color ?>;
            color: #fff;
        }

        .items-table td {
            border: 1px solid #333;
            padding: 8px;
            vertical-align: top;
        }

        .item-desc {
            font-weight: bold;
            font-size: 14px;
            display: block;
            margin-bottom: 5px;
        }

        .item-warranty {
            font-size: 11px;
            color: #444;
        }

        /* Summary Section */
        .summary-wrapper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .amount-words-area {
            width: 65%;
        }

        .totals-area {
            width: 30%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #333;
        }

        .totals-table td {
            padding: 5px 10px;
            border: 1px solid #333;
        }

        .grand-total-row {
            font-weight: bold;
            background: #eee;
            font-size: 15px;
        }

        /* Payment History Table */
        .history-section h4 {
            margin-bottom: 5px;
            font-size: 15px;
            border-bottom: 1px solid #333;
            display: inline-block;
            padding-right: 50px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #333;
            text-align: center;
        }

        .history-table th,
        .history-table td {
            border: 1px solid #333;
            padding: 8px;
        }

        /* Footer Area */
        .invoice-footer {
            margin-top: 30px;
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: end;
            margin-top: 80px;
        }

        .signature-area {
            border-top: 2px solid #333;
            padding-top: 5px;
            min-width: 150px;
            text-align: center;
            font-weight: bold;
            position: relative;
        }

        .signature-img {
            position: absolute;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            max-height: 50px;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #333;
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .back-btn {
            position: fixed;
            top: 20px;
            right: 180px;
            background: #2196F3;
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .back-btn:hover {
            background: #1976D2;
        }

        @media print {
            @page {
                margin: <?= $invoice_margin ?>;
            }

            .print-btn,
            .back-btn {
                display: none;
            }

            body {
                padding: 0 !important;
                margin: 0 !important;
            }

            .invoice-container {
                padding: <?= $invoice_margin ?>;
                padding-bottom: 2.5cm;
                width: 100%;
                box-sizing: border-box;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .items-table th {
                background: <?= $header_color ?> !important;
                color: #fff !important;
            }

            .totals-table .grand-total-row {
                background: <?= $header_color ?> !important;
                color: #fff !important;
            }

            .developed-by-footer {
                position: fixed;
                bottom: 1cm;
                left: 1cm;
                right: 1cm;
                z-index: 9999;
                background: #fff;
                width: auto;
                border-top: 1px dashed #ccc;
                padding-top: 10px;
            }
        }

        .developed-by-footer {
            margin-top: 15px;
            border-top: 1px dashed #ccc;
            padding-top: 5px;
            font-size: 11px;
            color: #666;
            overflow: hidden;
            clear: both;
        }

        /* ===== TEMPLATE STYLE OVERRIDES ===== */
        <?php if ($template === 'modern'): ?>.invoice-header {
            background: linear-gradient(135deg, <?= $header_color ?>, <?= $header_color ?>99);
            padding: 15px;
            border-radius: 10px;
            color: #fff;
        }

        .company-name {
            color: #fff !important;
            border-bottom-color: rgba(255, 255, 255, 0.5) !important;
        }

        .company-slogan {
            color: rgba(255, 255, 255, 0.9) !important;
        }

        .meta-box {
            border-color: rgba(255, 255, 255, 0.5);
            color: #fff;
        }

        .meta-label {
            color: rgba(255, 255, 255, 0.8);
        }

        .invoice-type-label {
            color: #fff;
        }

        .items-table {
            border-radius: 8px;
            overflow: hidden;
            border: none;
        }

        .items-table th {
            border: none;
        }

        .items-table td {
            border-color: #eee;
        }

        .company-info-block {
            border-radius: 8px;
            border-color: #ddd;
        }

        .customer-bar {
            border-radius: 8px;
            padding: 8px 15px;
            background: #f8f9fa;
            border: none;
        }

        <?php elseif ($template === 'minimal'): ?>.company-name {
            border-bottom: none !important;
            font-size: 24px !important;
        }

        .items-table,
        .items-table th,
        .items-table td {
            border: none !important;
        }

        .items-table th {
            background: transparent !important;
            color: <?= $header_color ?> !important;
            border-bottom: 2px solid <?= $header_color ?> !important;
        }

        .items-table td {
            border-bottom: 1px solid #eee !important;
        }

        .company-info-block {
            border: none;
            border-left: 3px solid <?= $header_color ?>;
        }

        .customer-bar {
            border: none;
            border-bottom: 1px solid #eee;
            border-top: 1px solid #eee;
        }

        .meta-box {
            border: none;
            border-bottom: 1px solid #ddd;
        }

        .totals-table,
        .totals-table td {
            border: none !important;
        }

        .totals-table td {
            border-bottom: 1px solid #eee !important;
        }

        .history-table,
        .history-table th,
        .history-table td {
            border: none !important;
            border-bottom: 1px solid #eee !important;
        }

        <?php elseif ($template === 'corporate'): ?>.invoice-header {
            border-bottom: 4px solid <?= $header_color ?>;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 26px !important;
            letter-spacing: 2px;
        }

        .items-table th {
            background: #1a2332 !important;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .totals-table .grand-total-row {
            background: #1a2332 !important;
            color: #fff;
        }

        .company-info-block {
            background: #f8f9fa;
            border: none;
        }

        .customer-bar {
            background: #f0f4f8;
            border: none;
            padding: 12px 15px;
        }

        .meta-box {
            background: #f0f4f8;
            border: none;
        }

        <?php elseif ($template === 'elegant'): ?>.invoice-header {
            border-bottom: 2px solid #c9a84c;
        }

        .company-name {
            color: #8b6914 !important;
            border-bottom: 2px solid #c9a84c !important;
        }

        .company-slogan {
            color: #c9a84c !important;
        }

        .items-table th {
            background: linear-gradient(135deg, #8b6914, #c9a84c) !important;
        }

        .totals-table .grand-total-row {
            background: linear-gradient(135deg, #8b6914, #c9a84c) !important;
            color: #fff;
        }

        .company-info-block {
            border-color: #c9a84c;
        }

        .customer-bar {
            border-color: #c9a84c;
        }

        .meta-box {
            border-color: #c9a84c;
        }

        .invoice-type-label {
            color: #8b6914;
        }

        <?php elseif ($template === 'professional'): ?>.invoice-container {
            border-left: 5px solid #2563eb;
        }

        .invoice-header {
            border-bottom: 3px solid #2563eb;
            padding-bottom: 12px;
        }

        .company-name {
            color: #1e40af !important;
            border-bottom: none !important;
            font-size: 24px !important;
        }

        .company-slogan {
            color: #3b82f6 !important;
            font-style: italic;
        }

        .items-table {
            border-radius: 8px;
            overflow: hidden;
            border: none;
        }

        .items-table th {
            background: #2563eb !important;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .items-table td {
            border-color: #dbeafe !important;
        }

        .items-table tr:nth-child(even) td {
            background: #eff6ff;
        }

        .totals-table .grand-total-row {
            background: #2563eb !important;
            color: #fff;
            border-radius: 4px;
        }

        .company-info-block {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 10px;
        }

        .customer-bar {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 10px 15px;
        }

        .meta-box {
            border: 1px solid #2563eb;
            border-radius: 4px;
        }

        .invoice-type-label {
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .history-table {
            border-radius: 8px;
            overflow: hidden;
        }

        .history-table th {
            background: #2563eb !important;
            color: #fff;
        }

        .signature-area {
            border-top-color: #2563eb;
        }

        <?php elseif ($template === 'receipt'): ?>.invoice-container {
            max-width: 80mm;
            margin: 0 auto;
            padding: 5mm !important;
            font-size: 11px;
        }

        .invoice-header {
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            flex-direction: column;
            align-items: center;
        }

        .logo-area {
            width: auto;
            margin-bottom: 5px;
        }

        .logo-area img {
            max-height: 40px;
        }

        .company-title-area {
            width: 100%;
        }

        .company-name {
            font-size: 16px !important;
            border-bottom: none !important;
            color: #000 !important;
        }

        .company-slogan {
            font-size: 10px !important;
            color: #555 !important;
        }

        .invoice-type-area {
            width: 100%;
            text-align: center;
            margin-top: 5px;
        }

        .invoice-type-label {
            font-size: 14px;
        }

        .meta-box {
            border: none;
            border-bottom: 1px dashed #999;
            font-size: 11px;
            justify-content: center;
            gap: 10px;
            padding: 3px 0;
        }

        .company-info-block {
            border: none;
            font-size: 10px;
            padding: 3px 0;
            text-align: center;
        }

        .customer-bar {
            border: none;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            font-size: 11px;
            padding: 5px 0;
        }

        .items-table {
            border: none !important;
            font-size: 11px;
        }

        .items-table th {
            background: #000 !important;
            font-size: 10px;
            padding: 3px 5px !important;
        }

        .items-table td {
            font-size: 10px;
            padding: 3px 5px !important;
            border-color: #ddd !important;
        }

        .totals-table {
            font-size: 11px;
        }

        .totals-table td {
            border: none !important;
            border-bottom: 1px dashed #ccc !important;
            padding: 3px 5px !important;
        }

        .totals-table .grand-total-row {
            background: #000 !important;
            color: #fff;
        }

        .totals-table .grand-total-row td {
            border: none !important;
        }

        .history-table,
        .history-table th,
        .history-table td {
            font-size: 10px;
        }

        .history-table th {
            background: #000 !important;
            color: #fff;
        }

        .footer-bottom {
            margin-top: 30px;
        }

        .signature-area {
            font-size: 10px;
            min-width: 80px;
        }

        .developed-by-footer {
            font-size: 9px !important;
        }

        <?php endif; ?>
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">PRINT INVOICE</button>
    <button class="back-btn" onclick="window.location.href='<?= BASE_URL ?>/modules/sales/sales-list.php'" title="Back to Sales List">← BACK</button>

    <div class="invoice-container">
        <!-- Top Header -->
        <div class="invoice-header">
            <div class="logo-area">
                <?php
                $logo_path = !empty($invoice_settings['company_logo']) ? $invoice_settings['company_logo'] : (defined('BUSINESS_LOGO') ? BUSINESS_LOGO : 'assets/images/logo.png');
                if (($invoice_settings['show_logo'] ?? 1)):
                ?>
                    <img src="<?= BASE_URL ?>/<?= $logo_path ?>" alt="Logo">
                <?php endif; ?>
            </div>
            <div class="company-title-area">
                <h1 class="company-name"><?= htmlspecialchars($invoice_settings['company_name']) ?></h1><br>
                <?php if (($invoice_settings['show_slogan'] ?? 1) && !empty($invoice_settings['company_slogan'])): ?>
                    <div class="company-slogan"><?= htmlspecialchars($invoice_settings['company_slogan']) ?></div>
                <?php endif; ?>
            </div>
            <div class="invoice-type-area">
                <div class="invoice-type-label">Invoice/Bill</div>
                <div class="meta-box">
                    <span class="meta-label">INVOICE NO:</span>
                    <span><?= htmlspecialchars($sale['invoice_number']) ?></span>
                </div>
                <div class="meta-box">
                    <span class="meta-label">DATE:</span>
                    <span><?= date('d-M-Y', strtotime($sale['sale_date'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Company Info Block -->
        <div class="company-info-block">
            <div class="info-row"><span class="font-bold">Address:</span> <?= htmlspecialchars($invoice_settings['company_address']) ?></div>
            <div class="info-row"><span class="font-bold">Tel:</span> <?= htmlspecialchars($invoice_settings['company_phone'] ?? 'N/A') ?><?php if (!empty($invoice_settings['tax_number'])): ?> &nbsp;|&nbsp; <span class="font-bold">Tax No:</span> <?= htmlspecialchars($invoice_settings['tax_number']) ?><?php endif; ?></div>
            <div class="info-row"><span class="font-bold">Email:</span> <?= htmlspecialchars($invoice_settings['company_email'] ?? 'N/A') ?>, <span class="font-bold">Website:</span> <?= htmlspecialchars($invoice_settings['company_website'] ?? 'N/A') ?></div>
        </div>

        <!-- Customer Bar -->
        <div class="customer-bar">
            <div class="customer-item"><span class="customer-label">Customer:</span> <?= htmlspecialchars($customer['name'] ?? 'Walk-in Customer') ?></div>
            <?php if ($customer): ?>
                <div class="customer-item"><span class="customer-label">ID:</span> <?= htmlspecialchars($customer['id']) ?></div>
                <div class="customer-item"><span class="customer-label">Phone:</span> <?= htmlspecialchars($customer['phone'] ?? 'N/A') ?></div>
                <div class="customer-item"><span class="customer-label">Email:</span> <?= htmlspecialchars($customer['email'] ?? 'N/A') ?></div>
                <div class="customer-item"><span class="customer-label">Address:</span> <?= htmlspecialchars($customer['address'] ?? 'N/A') ?></div>
            <?php endif; ?>
            <div class="customer-item"><span class="customer-label">Sales By:</span> <?= htmlspecialchars($sale['sold_by'] ?: ($creator['username'] ?? 'N/A')) ?></div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50px;">SL.</th>
                    <th>ITEM</th>
                    <th style="width: 60px;">QTY</th>
                    <th style="width: 100px;">PRICE</th>
                    <th style="width: 120px;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $counter = 1;
                foreach ($sale_items as $item):
                    // Calculate subtotal for row
                    $row_total = $item['quantity'] * $item['unit_price'];
                ?>
                    <tr>
                        <td class="text-center"><?= sprintf("%02d", $counter++) ?>.</td>
                        <td>
                            <span class="item-desc"><?= htmlspecialchars($item['product_name']) ?></span>

                            <?php
                            $cancelled_serials = '';
                            if (!empty($item['item_description']) && strpos($item['item_description'], 'Cancelled Serials:') !== false) {
                                $parts = explode('Cancelled Serials:', $item['item_description']);
                                if (isset($parts[1])) {
                                    $cancelled_serials = trim($parts[1]);
                                }
                            }
                            ?>
                            <?php if (!empty($item['serials'])): ?>
                                <div class="item-serials" style="font-size: 11px; margin-top: 2px;">
                                    <span class="font-bold">Serial No:</span> <?= implode(', ', array_map('htmlspecialchars', $item['serials'])) ?>
                                </div>
                            <?php elseif (!empty($cancelled_serials)): ?>
                                <div class="item-serials" style="font-size: 11px; margin-top: 2px; color: #d9534f;">
                                    <span class="font-bold">Cancelled Serials:</span> <?= htmlspecialchars($cancelled_serials) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($item['warranty_duration']) && $item['warranty_duration'] > 0): ?>
                                <div class="item-warranty" style="font-size: 11px; margin-top: 2px;">
                                    <span class="font-bold">Warranty:</span> <?= htmlspecialchars($item['warranty_duration'] . ' ' . $item['warranty_period']) ?>
                                </div>
                            <?php endif; ?>

                            <?php
                            $display_desc = !empty($item['item_description']) ? $item['item_description'] : $item['product_description'];
                            if (!empty($display_desc)):
                            ?>
                                <div class="mt-1" style="white-space: pre-line; font-size: 11px; color: #555;"><?= htmlspecialchars($display_desc) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $item['quantity'] ?></td>
                        <td class="text-right"><?= number_format($item['unit_price'], 2) ?></td>
                        <td class="text-right"><?= number_format($row_total, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Summary & Words -->
        <div class="summary-wrapper">
            <div class="amount-words-area">
                <div class="mb-2">
                    <span class="font-bold">Amount In Words:</span> <?= $amount_in_words ?>
                </div>
                <?php if (!empty($sale['notes'])): ?>
                    <div class="invoice-notes" style="margin-top: 10px; border: 1px dashed #ccc; padding: 5px;">
                        <span class="font-bold">Note:</span> <?= nl2br(htmlspecialchars($sale['notes'])) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="totals-area">
                <table class="totals-table">
                    <tr>
                        <td class="text-right">Total</td>
                        <td class="text-right"><?= number_format($sale['total_amount'] + $sale['discount'] - $sale['tax_amount'], 2) ?></td>
                    </tr>
                    <?php if ($sale['discount'] > 0): ?>
                    <tr>
                        <td class="text-right">Discount</td>
                        <td class="text-right">-<?= number_format($sale['discount'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($sale['tax_amount'] > 0): 
                        $subtotal = $sale['total_amount'] + $sale['discount'] - $sale['tax_amount'];
                        $calc_rate = ($subtotal > 0 && $sale['tax_amount'] > 0) ? ($sale['tax_amount'] / $subtotal * 100) : 0;
                    ?>
                        <tr>
                            <td class="text-right">Tax (<?= number_format($calc_rate, 2) ?>%)</td>
                            <td class="text-right"><?= number_format($sale['tax_amount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="grand-total-row">
                        <td class="text-right">Grand Total</td>
                        <td class="text-right"><?= number_format($sale['total_amount'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                &nbsp;
            </div>
            <div class="col-6 text-right">
                <span class="font-bold">Paid:</span> <?= number_format($sale['paid_amount'], 2) ?> Tk &nbsp;&nbsp;
                <span class="font-bold">Due:</span> <?= number_format($sale['due_amount'], 2) ?> Tk
            </div>
        </div>

        <!-- Payment History -->
        <div class="history-section mb-4">
            <h4>Payment History</h4>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Payment ID</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payments)): ?>
                        <?php foreach ($payments as $i => $payment): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= date('d-M-Y', strtotime($payment['payment_date'])) ?></td>
                                <td><?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?></td>
                                <td><?= htmlspecialchars($payment['reference'] ?: 'P-' . $payment['id']) ?></td>
                                <td><?= number_format($payment['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No payment history found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Terms & Conditions -->
        <div class="invoice-footer">
            <h4 class="font-bold mb-2">Terms & Conditions:</h4>
            <div style="white-space: pre-line;"><?= htmlspecialchars($invoice_settings['terms_and_conditions'] ?? '1. Return within 7 days with original packaging.') ?></div>

            <div class="footer-bottom">
                <div class="signature-area">
                    Customer Signature
                </div>
                <?php if (($invoice_settings['show_signature_on_invoice'] ?? 1)): ?>
                    <div class="signature-area">
                        <?php if (!empty($invoice_settings['invoice_signature'])): ?>
                            <img src="<?= BASE_URL ?>/<?= $invoice_settings['invoice_signature'] ?>" class="signature-img" alt="Authorized Signature">
                        <?php endif; ?>
                        <?= htmlspecialchars($invoice_settings['author_signature_label'] ?? 'Authorized Signature') ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($invoice_settings['invoice_note'])): ?>
                <div style="text-align:center; margin:10px 0; padding:10px 15px; font-weight:bold; font-size:14px; color:#000; box-shadow: 0 2px 8px rgba(0,0,0,0.12); border-radius:4px; background:#fff;">
                    <?= htmlspecialchars($invoice_settings['invoice_note']) ?>
                </div>
            <?php endif; ?>

            <?php if ($invoice_settings['show_print_datetime'] ?? 1): ?>
                <div class="developed-by-footer">
                    <div style="float: left; font-weight: bold; font-size: 12px;">
                        Developed By <?= htmlspecialchars($invoice_settings['software_developed_by'] ?? 'CITNBD | 01976-793351') ?>
                    </div>
                    <div style="float: right; text-align: right;">
                        Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i:s A') ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>