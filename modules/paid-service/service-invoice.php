<?php
/**
 * Service Invoice - Print-friendly bill
 * Shows parts breakdown + service charge
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$ticket_id = (int)get_param('id', 0);
$ticket = db_select_one('service_tickets', ['id' => $ticket_id]);
if (!$ticket) die('Ticket not found');

$parts = db_query("SELECT * FROM service_ticket_parts WHERE ticket_id = ? ORDER BY id", [$ticket_id]);
$business = db_select_one('business_settings', ['id' => 1]);
$bname = $business['business_name'] ?? (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'My Business');
$baddr = $business['business_address'] ?? (defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : '');
$bphone = $business['business_phone'] ?? (defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '');
$bemail = $business['business_email'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Service Invoice - <?= htmlspecialchars($ticket['ticket_number']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; color: #333; }
        .invoice-container { max-width: 800px; margin: 20px auto; background: white; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0d9488; padding-bottom: 20px; margin-bottom: 25px; }
        .company-info h1 { color: #0d9488; font-size: 1.5rem; margin-bottom: 5px; }
        .company-info p { font-size: 0.85rem; color: #666; }
        .invoice-title { text-align: right; }
        .invoice-title h2 { color: #0d9488; font-size: 1.8rem; text-transform: uppercase; letter-spacing: 3px; }
        .invoice-title .ticket-num { font-size: 1.1rem; margin-top: 5px; color: #555; }
        .invoice-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
        .meta-box { padding: 15px; background: #f0fdfa; border-radius: 6px; border-left: 4px solid #0d9488; }
        .meta-box h4 { color: #0d9488; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 8px; }
        .meta-box p { font-size: 0.9rem; margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        thead th { background: #0d9488; color: white; padding: 10px 12px; text-align: left; font-size: 0.85rem; text-transform: uppercase; }
        tbody td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; font-size: 0.9rem; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .totals-section { display: flex; justify-content: flex-end; margin-top: 20px; }
        .totals-table { width: 320px; }
        .totals-table td { padding: 6px 12px; font-size: 0.95rem; }
        .totals-table .grand-total td { font-size: 1.2rem; font-weight: bold; border-top: 2px solid #0d9488; color: #0d9488; padding-top: 10px; }
        .payment-info { margin-top: 25px; padding: 15px; background: #f0fdfa; border-radius: 6px; font-size: 0.9rem; }
        .payment-info .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-unpaid { background: #fecaca; color: #991b1b; }
        .badge-partial { background: #fef3c7; color: #92400e; }
        .invoice-footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; }
        .signatures { display: flex; justify-content: space-between; margin-top: 50px; }
        .sig-box { text-align: center; width: 200px; }
        .sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; font-size: 0.85rem; color: #666; }
        .no-print { text-align: center; margin: 20px; }
        @media print {
            body { background: white; }
            .no-print { display: none; }
            .invoice-container { box-shadow: none; margin: 0; padding: 20px; }
            a[href]:after { content: none !important; }
            a { text-decoration: none !important; color: inherit !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding:10px 30px; background:#0d9488; color:white; border:none; border-radius:5px; cursor:pointer; font-size:1rem;">🖨️ Print Invoice</button>
        <a href="service-view.php?id=<?= $ticket_id ?>" style="padding:10px 20px; margin-left:10px; text-decoration:none; color:#333;">← Back to Ticket</a>
    </div>

    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info">
                <h1><?= htmlspecialchars($bname) ?></h1>
                <?php if ($baddr): ?><p><?= htmlspecialchars($baddr) ?></p><?php endif; ?>
                <?php if ($bphone): ?><p>Tel: <?= htmlspecialchars($bphone) ?></p><?php endif; ?>
                <?php if ($bemail): ?><p>Email: <?= htmlspecialchars($bemail) ?></p><?php endif; ?>
            </div>
            <div class="invoice-title">
                <h2>Service Invoice</h2>
                <div class="ticket-num"><?= htmlspecialchars($ticket['ticket_number']) ?></div>
                <p style="font-size:0.85rem; color:#666;">Date: <?= date('d M Y') ?></p>
            </div>
        </div>

        <div class="invoice-meta">
            <div class="meta-box">
                <h4>Customer</h4>
                <p><strong><?= htmlspecialchars($ticket['customer_name']) ?></strong></p>
                <p>Phone: <?= htmlspecialchars($ticket['customer_phone']) ?></p>
            </div>
            <div class="meta-box">
                <h4>Device Details</h4>
                <p><?= htmlspecialchars($ticket['device_type']) ?> - <?= htmlspecialchars($ticket['brand'] . ' ' . $ticket['model']) ?></p>
                <?php if ($ticket['serial_number']): ?><p>S/N: <?= htmlspecialchars($ticket['serial_number']) ?></p><?php endif; ?>
                <p>Received: <?= date('d M Y', strtotime($ticket['created_at'])) ?></p>
            </div>
        </div>

        <h4 style="color:#0d9488; margin-bottom:10px;">Service Details</h4>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $sl = 1; ?>
                <?php if ($ticket['service_charge'] > 0): ?>
                    <tr>
                        <td><?= $sl++ ?></td>
                        <td><strong>Service Charge</strong><br><small style="color:#666;"><?= htmlspecialchars($ticket['problem_description']) ?></small></td>
                        <td style="text-align:center;">1</td>
                        <td style="text-align:right;"><?= format_currency($ticket['service_charge']) ?></td>
                        <td style="text-align:right;"><?= format_currency($ticket['service_charge']) ?></td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($parts as $p): ?>
                    <tr>
                        <td><?= $sl++ ?></td>
                        <td><?= htmlspecialchars($p['product_name']) ?></td>
                        <td style="text-align:center;"><?= $p['quantity'] ?></td>
                        <td style="text-align:right;"><?= format_currency($p['unit_price']) ?></td>
                        <td style="text-align:right;"><?= format_currency($p['total_price']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($sl === 1): ?>
                    <tr><td colspan="5" style="text-align:center; color:#999;">No items</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr><td>Parts Cost:</td><td style="text-align:right;"><?= format_currency($ticket['total_parts_cost']) ?></td></tr>
                <tr><td>Service Charge:</td><td style="text-align:right;"><?= format_currency($ticket['service_charge']) ?></td></tr>
                <?php if ($ticket['discount'] > 0): ?>
                    <tr><td>Discount:</td><td style="text-align:right; color:red;">-<?= format_currency($ticket['discount']) ?></td></tr>
                <?php endif; ?>
                <tr class="grand-total"><td>Grand Total:</td><td style="text-align:right;"><?= format_currency($ticket['total_amount']) ?></td></tr>
                <?php if ($ticket['paid_amount'] > 0): ?>
                    <tr><td>Paid:</td><td style="text-align:right;"><?= format_currency($ticket['paid_amount']) ?></td></tr>
                    <?php $due = $ticket['total_amount'] - $ticket['paid_amount']; if ($due > 0): ?>
                        <tr><td>Due:</td><td style="text-align:right; color:red; font-weight:bold;"><?= format_currency($due) ?></td></tr>
                    <?php endif; ?>
                <?php endif; ?>
            </table>
        </div>

        <div class="payment-info">
            <strong>Payment Status:</strong>
            <span class="badge badge-<?= strtolower($ticket['payment_status'] ?: 'unpaid') ?>"><?= $ticket['payment_status'] ?: 'Unpaid' ?></span>
            <?php if ($ticket['payment_method']): ?> | <strong>Method:</strong> <?= htmlspecialchars($ticket['payment_method']) ?><?php endif; ?>
        </div>

        <div class="signatures">
            <div class="sig-box"><div class="sig-line">Customer Signature</div></div>
            <div class="sig-box"><div class="sig-line">Authorized Signature</div></div>
        </div>

        <div class="invoice-footer">
            <p style="color:#666; font-size:0.85rem;">Thank you for your business! | <?= htmlspecialchars($bname) ?></p>
        </div>
    </div>
</body>
</html>
