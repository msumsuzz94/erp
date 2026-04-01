<?php
/**
 * Service Receipt / Token Print
 * Print-friendly receipt for customer
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$ticket_id = (int)get_param('id', 0);
$ticket = db_select_one('service_tickets', ['id' => $ticket_id]);
if (!$ticket) die('Ticket not found');

$business = db_select_one('business_settings', ['id' => 1]);
$bname = $business['business_name'] ?? (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'My Business');
$baddr = $business['business_address'] ?? (defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : '');
$bphone = $business['business_phone'] ?? (defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Service Receipt - <?= htmlspecialchars($ticket['ticket_number']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; }
        .receipt-container {
            max-width: 400px; margin: 20px auto; background: white; padding: 25px;
            border: 2px dashed #0d9488; border-radius: 8px;
        }
        .receipt-header { text-align: center; border-bottom: 2px solid #0d9488; padding-bottom: 15px; margin-bottom: 15px; }
        .receipt-header h2 { color: #0d9488; font-size: 1.3rem; }
        .receipt-header .biz-name { font-size: 1.1rem; font-weight: bold; }
        .receipt-header small { color: #666; display: block; }
        .ticket-number {
            text-align: center; background: #0d9488; color: white; padding: 12px;
            border-radius: 8px; margin: 15px 0; font-size: 1.5rem; font-weight: bold; letter-spacing: 2px;
        }
        .info-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dotted #ddd; font-size: 0.9rem; }
        .info-row .label { font-weight: 600; color: #555; }
        .section-title { font-weight: bold; color: #0d9488; margin: 12px 0 8px; font-size: 0.9rem; text-transform: uppercase; }
        .problem-box { background: #f0fdfa; padding: 10px; border-radius: 5px; font-size: 0.85rem; margin: 8px 0; }
        .receipt-footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px dashed #ccc; font-size: 0.8rem; color: #666; }
        .barcode-area { text-align: center; margin: 10px 0; font-family: monospace; font-size: 1.2rem; letter-spacing: 3px; }
        .no-print { text-align: center; margin: 20px; }
        @media print {
            @page {
                margin: 0.25cm;
                margin-bottom: 1.5cm;
            }
            body { background: white; }
            .no-print { display: none; }
            a[href]:after { content: none !important; }
            a { text-decoration: none !important; color: inherit !important; }
            .receipt-container { border: none; margin: 0; max-width: 100%; }
            .print-page-footer {
                display: block !important;
                position: fixed;
                bottom: 0;
                left: 0.5cm;
                right: 0.5cm;
                border-top: 1px solid #333;
                padding-top: 5px;
                font-size: 9px;
                color: #333;
            }
            .print-page-footer .pf-left { float: left; font-weight: bold; }
            .print-page-footer .pf-right { float: right; }
        }
    </style>
</head>
<body>
    <!-- Print Footer (hidden on screen, shown on print) -->
    <div class="print-page-footer" style="display:none;">
        <span class="pf-left">ERP Developed By : CITNBD | 01976-793351</span>
        <span class="pf-right">Date: <?= date('d-M-Y') ?>, Time: <?= date('h:i A') ?></span>
    </div>
    <div class="no-print">
        <button onclick="window.print()" style="padding:10px 30px; background:#0d9488; color:white; border:none; border-radius:5px; cursor:pointer; font-size:1rem;">
            🖨️ Print Receipt
        </button>
        <a href="service-view.php?id=<?= $ticket_id ?>" style="padding:10px 20px; margin-left:10px; text-decoration:none; color:#333;">← Back to Ticket</a>
    </div>

    <div class="receipt-container">
        <div class="receipt-header">
            <div class="biz-name"><?= htmlspecialchars($bname) ?></div>
            <?php if ($baddr): ?><small><?= htmlspecialchars($baddr) ?></small><?php endif; ?>
            <?php if ($bphone): ?><small>Tel: <?= htmlspecialchars($bphone) ?></small><?php endif; ?>
            <h2 style="margin-top:10px;">SERVICE RECEIPT</h2>
        </div>

        <div class="ticket-number"><?= htmlspecialchars($ticket['ticket_number']) ?></div>

        <div class="section-title">Customer Information</div>
        <div class="info-row"><span class="label">Name:</span><span><?= htmlspecialchars($ticket['customer_name']) ?></span></div>
        <div class="info-row"><span class="label">Phone:</span><span><?= htmlspecialchars($ticket['customer_phone']) ?></span></div>

        <div class="section-title">Device Information</div>
        <div class="info-row"><span class="label">Device:</span><span><?= htmlspecialchars($ticket['device_type']) ?></span></div>
        <?php if ($ticket['brand']): ?><div class="info-row"><span class="label">Brand/Model:</span><span><?= htmlspecialchars($ticket['brand'] . ' ' . $ticket['model']) ?></span></div><?php endif; ?>
        <?php if ($ticket['serial_number']): ?><div class="info-row"><span class="label">Serial/IMEI:</span><span><?= htmlspecialchars($ticket['serial_number']) ?></span></div><?php endif; ?>
        <div class="info-row"><span class="label">Warranty:</span><span><?= htmlspecialchars($ticket['warranty_status']) ?></span></div>

        <div class="section-title">Problem Description</div>
        <div class="problem-box"><?= nl2br(htmlspecialchars($ticket['problem_description'])) ?></div>

        <?php if ($ticket['physical_condition']): ?>
            <div class="section-title">Physical Condition</div>
            <div class="problem-box"><?= nl2br(htmlspecialchars($ticket['physical_condition'])) ?></div>
        <?php endif; ?>

        <div class="info-row"><span class="label">Received Date:</span><span><?= date('d M Y, h:i A', strtotime($ticket['created_at'])) ?></span></div>
        <?php if ($ticket['estimated_delivery_date']): ?>
            <div class="info-row"><span class="label">Est. Delivery:</span><span><?= date('d M Y', strtotime($ticket['estimated_delivery_date'])) ?></span></div>
        <?php endif; ?>
        <div class="info-row"><span class="label">Status:</span><span style="color:#0d9488; font-weight:bold;"><?= $ticket['status'] ?></span></div>

        <div class="barcode-area">* <?= $ticket['ticket_number'] ?> *</div>

        <div class="receipt-footer">
            <p>Please keep this receipt for tracking your service.</p>
            <p>Track with: <strong><?= $ticket['ticket_number'] ?></strong></p>
            <p style="margin-top:8px;">Thank you for choosing <?= htmlspecialchars($bname) ?></p>
        </div>
    </div>
</body>
</html>
