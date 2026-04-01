<?php

/**
 * Professional Quotation Print Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$quotation_id = (int)get_param('id');

// Get quotation details
$sql = "SELECT q.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        WHERE q.id = ?";
$quotation = db_query_one($sql, [$quotation_id]);

if (!$quotation) {
    die('Quotation not found');
}

// Get quotation items
$sql = "SELECT qi.*, p.name as product_name, p.code as product_code, p.description as product_description, p.warranty_duration, p.warranty_period
        FROM quotation_items qi
        INNER JOIN products p ON qi.product_id = p.id
        WHERE qi.quotation_id = ?";
$quotation_items = db_query($sql, [$quotation_id]);

// Get invoice settings
$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$invoice_settings) {
    $invoice_settings = [
        'company_name' => BUSINESS_NAME,
        'company_address' => BUSINESS_ADDRESS,
        'company_phone' => BUSINESS_PHONE,
        'company_email' => BUSINESS_EMAIL,
        'header_color' => '#f39c12'
    ];
}

// Generate Amount in Words
$amount_in_words = '';
if (($invoice_settings['show_amount_in_words'] ?? 1) && function_exists('convert_number_to_words')) {
    $amount_in_words = convert_number_to_words($quotation['total_amount'], $invoice_settings['amount_in_words_prefix'] ?? 'BDT');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Quotation - <?= htmlspecialchars($quotation['quotation_number']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            line-height: 1.2;
            color: #333;
            margin: 0;
            padding: 40px;
            background: #fff;
        }

        .row {
            display: flex;
            width: 100%;
        }

        .col-6 {
            width: 50%;
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

        .mb-4 {
            margin-bottom: 20px;
        }

        /* Header section */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .logo-area {
            width: 10%;
        }

        .logo-area img {
            max-width: 100%;
            height: auto;
        }

        .company-title-area {
            width: 65%;
            text-align: center;
        }

        .company-name {
            font-size: <?= $invoice_settings['company_name_font_size'] ?? 26 ?>px;
            font-weight: 900;
            color: <?= $invoice_settings['header_color'] ?? '#2C3E50' ?>;
            margin: 0;
            text-transform: uppercase;
            border-bottom: 2px solid <?= $invoice_settings['header_color'] ?? '#333' ?>;
            display: inline-block;
            padding-bottom: 2px;
        }

        .company-slogan {
            font-size: <?= $invoice_settings['company_slogan_font_size'] ?? 13 ?>px;
            color: #E67E22;
            font-weight: bold;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .contact-info {
            width: 25%;
            text-align: right;
            font-size: 11px;
        }

        /* Info row */
        .info-section {
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            margin-top: 5px;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: stretch;
        }

        .to-section {
            width: 68%;
        }

        .no-date-section {
            width: 30%;
        }

        .meta-box {
            border: 1px solid #333;
            padding: 4px 8px;
            margin-bottom: 2px;
            display: flex;
            justify-content: space-between;
            border-radius: 4px;
        }

        /* Quotation badge */
        .quotation-badge-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .quotation-badge {
            border: 3px solid #f39c12;
            color: #333;
            font-weight: 900;
            font-size: 20px;
            padding: 5px 40px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border: 1px solid #999;
        }

        .items-table th {
            border: 1px solid #999;
            padding: 3px 6px;
            font-weight: bold;
            text-transform: uppercase;
            background: #fafafa;
            color: #333;
            font-size: 12px;
            line-height: 1;
        }

        .items-table td {
            border: 1px solid #999;
            padding: 1px 6px;
            vertical-align: top;
            font-size: 13px;
            line-height: 1.1;
        }

        .item-desc {
            font-weight: bold;
            font-size: 13px;
            display: block;
            margin-bottom: 0px;
        }

        /* Totals */
        .summary-wrapper {
            display: flex;
            border: 1px solid #999;
            border-top: none;
        }

        .words-col {
            width: 65%;
            padding: 6px 8px;
            border-right: 1px solid #999;
            font-size: 12px;
            display: flex;
            align-items: center;
        }

        .total-label-col {
            width: 15%;
            padding: 6px 8px;
            border-right: 1px solid #999;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafafa;
        }

        .total-val-col {
            width: 20%;
            padding: 6px 8px;
            font-weight: bold;
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        /* Footer */
        .terms-section {
            margin-top: 30px;
        }

        .signature-row {
            margin-top: 100px;
            display: flex;
            justify-content: flex-end;
        }

        .signature-box {
            border-top: 2px solid #333;
            padding-top: 5px;
            width: 200px;
            text-align: center;
            font-weight: bold;
        }


        @media print {
            @page {
                margin: 0.25cm;
                size: auto;
            }

            .print-btn,
            .back-btn {
                display: none;
            }

            body {
                padding: 0 !important;
                margin: 0 !important;
            }

            .quotation-container {
                padding: 0;
                width: 100%;
                box-sizing: border-box;
            }

            .items-table td {
                padding: 1px 6px;
            }

            .items-table tr:not(:has(.item-desc + div:not(:empty))) td {
                padding: 3px 6px;
            }

            .developed-by-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 9999;
                background: #fff;
                width: 100%;
                border-top: 1px dashed #ccc;
                padding-top: 5px;
            }
        }

        .quotation-container {
            padding: 40px;
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
            z-index: 1000;
            font-weight: bold;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            right: 200px;
            background: #2196F3;
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
            z-index: 1000;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .back-btn:hover {
            background: #1976D2;
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">PRINT QUOTATION</button>
    <button class="back-btn" onclick="window.location.href='<?= BASE_URL ?>/modules/quotation/quotations-list.php'" title="Back to Quotations List">← BACK</button>

    <div class="quotation-container">

        <div class="header">
            <div class="logo-area">
                <?php
                $logo_path = !empty($invoice_settings['company_logo']) ? $invoice_settings['company_logo'] : (defined('BUSINESS_LOGO') ? BUSINESS_LOGO : 'assets/images/logo.png');
                if (($invoice_settings['show_logo'] ?? 1)):
                ?>
                    <img src="<?= BASE_URL ?>/<?= $logo_path ?>" alt="Logo">
                <?php else: ?>
                    <h2 class="font-bold">Logo</h2>
                <?php endif; ?>
            </div>
            <div class="company-title-area">
                <h1 class="company-name"><?= htmlspecialchars($invoice_settings['company_name']) ?></h1><br>
                <?php if (($invoice_settings['show_slogan'] ?? 0) && !empty($invoice_settings['company_slogan'])): ?>
                    <div class="company-slogan"><?= htmlspecialchars($invoice_settings['company_slogan']) ?></div>
                <?php endif; ?>
            </div>
            <div class="contact-info">
                <span class="font-bold">Address:</span> <?= htmlspecialchars($invoice_settings['company_address']) ?><br>
                <span class="font-bold">Tel:</span> <?= htmlspecialchars($invoice_settings['company_phone'] ?? 'N/A') ?><br>
                <span class="font-bold">Email:</span> <?= htmlspecialchars($invoice_settings['company_email'] ?? 'N/A') ?>,<br>
                <span class="font-bold">Website:</span> <?= htmlspecialchars($invoice_settings['company_website'] ?? 'N/A') ?>
            </div>
        </div>

        <div class="info-section">
            <div class="to-section">
                <div class="font-bold" style="font-size: 16px;">TO</div>
                <div style="font-size: 15px; font-weight: bold;"><?= htmlspecialchars($quotation['customer_name'] ?: 'Walk-in Customer') ?></div>
                <div><?= nl2br(htmlspecialchars($quotation['customer_address'] ?? '')) ?></div>
            </div>
            <div class="no-date-section">
                <div class="meta-box">
                    <span class="font-bold">NO:</span>
                    <span><?= htmlspecialchars($quotation['quotation_number']) ?></span>
                </div>
                <div class="meta-box">
                    <span class="font-bold">DATE:</span>
                    <span><?= date('d-M-Y', strtotime($quotation['quotation_date'])) ?></span>
                </div>
            </div>
        </div>

        <div class="quotation-badge-container">
            <div class="quotation-badge">QUOTATION</div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50px;">SL.</th>
                    <th>PRODUCT DESCRIPTION</th>
                    <th style="width: 60px;">QTY</th>
                    <th style="width: 100px;">PRICE</th>
                    <th style="width: 120px;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $counter = 1;
                foreach ($quotation_items as $item):
                    $row_total = $item['quantity'] * $item['unit_price'];
                ?>
                    <tr>
                        <td class="text-center"><?= sprintf("%02d", $counter++) ?></td>
                        <td>
                            <span class="item-desc"><?= htmlspecialchars($item['product_name']) ?></span>
                            <div style="font-size: 12px; color: #444; white-space: pre-line; margin: 0; line-height: 1.1;">
                                <?= htmlspecialchars($item['description'] ?: $item['product_description']) ?>
                            </div>
                            <?php if (!empty($item['warranty_duration']) && $item['warranty_duration'] > 0): ?>
                                <div class="mt-1 font-bold" style="line-height: 1;"><?= $item['warranty_duration'] ?> <?= ucfirst($item['warranty_period']) ?> Warranty</div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $item['quantity'] ?></td>
                        <td class="text-right"><?= number_format($item['unit_price'], 2) ?></td>
                        <td class="text-right"><?= number_format($row_total, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-wrapper">
            <div class="words-col">
                <span class="font-bold">Amount In Words:</span>&nbsp;<?= $amount_in_words ?>
            </div>
            <?php if ($quotation['tax_rate'] > 0): ?>
                <div class="total-label-col">Subtotal (A)</div>
                <div class="total-val-col"><?= number_format($quotation['total_amount'] - $quotation['tax_amount'], 2) ?></div>
            <?php else: ?>
                <div class="total-label-col">TOTAL (A)</div>
                <div class="total-val-col"><?= number_format($quotation['total_amount'], 2) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($quotation['tax_rate'] > 0): ?>
            <div class="summary-wrapper" style="border-top: none;">
                <div class="words-col"></div>
                <div class="total-label-col">Tax Rate (<?= number_format($quotation['tax_rate'], 0) ?>%)</div>
                <div class="total-val-col"><?= number_format($quotation['tax_amount'], 2) ?></div>
            </div>
            <div class="summary-wrapper" style="border-top: 1px solid #999; background: #f0f0f0;">
                <div class="words-col"></div>
                <div class="total-label-col font-bold">Grand Total</div>
                <div class="total-val-col font-bold"><?= number_format($quotation['total_amount'], 2) ?></div>
            </div>
        <?php endif; ?>

        <div class="terms-section">
            <h4 class="font-bold mb-2">Terms & Conditions</h4>
            <div style="white-space: pre-line;"><?= htmlspecialchars($quotation['notes'] ?: $invoice_settings['terms_and_conditions'] ?: '1. Valid for 15 days.') ?></div>
        </div>

        <div class="signature-row">
            <div class="signature-box">Author Signature</div>
        </div>

        <div class="developed-by-footer">
            <div style="float: left; font-weight: bold; font-size: 12px;">
                ERP Developed By : CITNBD | 01976793351
            </div>
            <div style="float: right; text-align: right;">
                Print Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i:s A') ?>
            </div>
        </div>
    </div> <!-- End quotation-container -->

</body>

</html>