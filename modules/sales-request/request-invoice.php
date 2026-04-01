<?php
/**
 * Sales Request Invoice - Print-friendly
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$request_id = (int)get_param('id', 0);
$request = db_select_one('sales_requests', ['id' => $request_id]);
if (!$request) die('Request not found');

$items = json_decode($request['items'], true) ?: [];

// SR info
$sr = null;
if ($request['sr_user_id']) {
    $sr = db_select_one('users', ['id' => $request['sr_user_id']]);
}

// Approved by
$approver = null;
if (!empty($request['approved_by'])) {
    $approver = db_select_one('users', ['id' => $request['approved_by']]);
}

$bname = BUSINESS_NAME;
$baddr = BUSINESS_ADDRESS;
$bphone = BUSINESS_PHONE;
$bemail = BUSINESS_EMAIL;
$cs = APP_CURRENCY_SYMBOL;
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?= htmlspecialchars($request['request_number']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f0f0; color: #333; font-size: 14px; }

        .invoice-wrap { max-width: 800px; margin: 20px auto; background: #fff; padding: 36px 40px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }

        /* Header */
        .inv-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #4e73df; padding-bottom: 18px; margin-bottom: 22px; }
        .inv-header .company h1 { color: #4e73df; font-size: 1.5rem; margin-bottom: 4px; }
        .inv-header .company p { font-size: 0.82rem; color: #666; line-height: 1.5; }
        .inv-header .inv-title { text-align: right; }
        .inv-header .inv-title h2 { color: #4e73df; font-size: 1.7rem; text-transform: uppercase; letter-spacing: 3px; }
        .inv-header .inv-title .req-num { font-size: 1rem; color: #555; margin-top: 4px; font-weight: 600; }
        .inv-header .inv-title .inv-date { font-size: 0.82rem; color: #888; margin-top: 2px; }

        /* Meta boxes */
        .inv-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 22px; }
        .meta-box { padding: 14px 16px; background: #f0f4ff; border-radius: 6px; border-left: 4px solid #4e73df; }
        .meta-box h4 { color: #4e73df; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
        .meta-box p { font-size: 0.88rem; margin: 2px 0; }
        .meta-box strong { color: #222; }

        /* Status Badge */
        .status-badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin: 18px 0; }
        thead th { background: #4e73df; color: #fff; padding: 10px 12px; text-align: left; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; }
        thead th:last-child, thead th:nth-child(3), thead th:nth-child(4) { text-align: right; }
        tbody td { padding: 9px 12px; border-bottom: 1px solid #e8e8e8; font-size: 0.88rem; }
        tbody td:last-child, tbody td:nth-child(3), tbody td:nth-child(4) { text-align: right; }
        tbody tr:nth-child(even) { background: #fafbff; }

        /* Totals */
        .totals-section { display: flex; justify-content: flex-end; margin-top: 18px; }
        .totals-table { width: 320px; }
        .totals-table td { padding: 5px 12px; font-size: 0.92rem; }
        .totals-table .grand-total td { font-size: 1.15rem; font-weight: 700; border-top: 2px solid #4e73df; color: #4e73df; padding-top: 10px; }

        /* Notes */
        .notes-box { margin-top: 20px; padding: 12px 14px; background: #f8f9fc; border-radius: 6px; border: 1px solid #e3e6f0; font-size: 0.85rem; }
        .notes-box .label { font-weight: 700; color: #4e73df; margin-bottom: 4px; }

        /* Signatures */
        .signatures { display: flex; justify-content: space-between; margin-top: 50px; }
        .sig-box { text-align: center; width: 200px; }
        .sig-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; font-size: 0.82rem; color: #666; }

        /* Footer */
        .inv-footer { text-align: center; margin-top: 28px; padding-top: 14px; border-top: 2px solid #e5e7eb; font-size: 0.82rem; color: #888; }

        /* No print elements */
        .no-print { text-align: center; margin: 20px; }
        .no-print button { padding: 10px 30px; background: #4e73df; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        .no-print button:hover { background: #2e59d9; }
        .no-print a { padding: 10px 20px; margin-left: 10px; text-decoration: none; color: #333; }

        /* Print */
        @media print {
            @page { margin: 0.25cm; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .invoice-wrap { box-shadow: none; margin: 0; padding: 16px; max-width: 100%; }
            a[href]:after { content: none !important; }
            a { text-decoration: none !important; color: inherit !important; }
            .print-footer {
                display: block !important;
                position: fixed;
                bottom: 0;
                left: 0.25cm;
                right: 0.25cm;
                border-top: 1px solid #333;
                padding-top: 4px;
                font-size: 8px;
                color: #333;
            }
            .print-footer .pf-left { float: left; font-weight: bold; }
            .print-footer .pf-right { float: right; }
        }
    </style>
</head>
<body>
    <!-- Print Footer (hidden on screen) -->
    <div class="print-footer" style="display:none;">
        <span class="pf-left">ERP Developed By : CITNBD | 01976-793351</span>
        <span class="pf-right">Printed: <?= date('d-M-Y, h:i A') ?></span>
    </div>

    <div class="no-print">
        <button onclick="window.print()">🖨️ Print Invoice</button>
        <a href="request-list.php">← Back to List</a>
    </div>

    <div class="invoice-wrap">
        <!-- Header -->
        <div class="inv-header">
            <div class="company">
                <h1><?= htmlspecialchars($bname) ?></h1>
                <?php if ($baddr): ?><p><?= htmlspecialchars($baddr) ?></p><?php endif; ?>
                <?php if ($bphone): ?><p>Tel: <?= htmlspecialchars($bphone) ?></p><?php endif; ?>
                <?php if ($bemail): ?><p>Email: <?= htmlspecialchars($bemail) ?></p><?php endif; ?>
            </div>
            <div class="inv-title">
                <h2>Invoice</h2>
                <div class="req-num"><?= htmlspecialchars($request['request_number']) ?></div>
                <div class="inv-date">Date: <?= date('d M Y', strtotime($request['created_at'])) ?></div>
                <div style="margin-top:6px;">
                    <?php
                    $sc = $request['status'];
                    $scl = 'status-' . $sc;
                    ?>
                    <span class="status-badge <?= $scl ?>"><?= ucfirst($sc) ?></span>
                </div>
            </div>
        </div>

        <!-- Meta -->
        <div class="inv-meta">
            <div class="meta-box">
                <h4>Customer</h4>
                <p><strong><?= htmlspecialchars($request['customer_name'] ?: 'Walk-in') ?></strong></p>
                <?php if ($request['customer_phone']): ?>
                    <p>Phone: <?= htmlspecialchars($request['customer_phone']) ?></p>
                <?php endif; ?>
            </div>
            <div class="meta-box">
                <h4>Sales Representative</h4>
                <p><strong><?= htmlspecialchars($sr['username'] ?? 'N/A') ?></strong></p>
                <p>Request Date: <?= date('d M Y, h:i A', strtotime($request['created_at'])) ?></p>
                <?php if ($approver): ?>
                    <p>Approved By: <?= htmlspecialchars($approver['username']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th style="width:6%">#</th>
                    <th style="width:44%">Product</th>
                    <th style="width:16%">Unit Price</th>
                    <th style="width:10%">Qty</th>
                    <th style="width:24%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $sl = 1; foreach ($items as $item):
                    $qty = (float)($item['quantity'] ?? 0);
                    $price = (float)($item['unit_price'] ?? 0);
                    $sub = $qty * $price;
                ?>
                <tr>
                    <td><?= $sl++ ?></td>
                    <td>
                        <strong><?= htmlspecialchars($item['product_name'] ?? '') ?></strong>
                        <?php if (!empty($item['product_code'])): ?>
                            <br><small style="color:#888;">Code: <?= htmlspecialchars($item['product_code']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= $cs . number_format($price, 2) ?></td>
                    <td style="text-align:center;"><?= (int)$qty ?></td>
                    <td><?= $cs . number_format($sub, 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#999;">No items</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align:right;"><?= $cs . number_format((float)$request['total_amount'], 2) ?></td>
                </tr>
                <?php if ((float)$request['discount'] > 0): ?>
                <tr>
                    <td>Discount:</td>
                    <td style="text-align:right; color:red;">-<?= $cs . number_format((float)$request['discount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="grand-total">
                    <td>Net Total:</td>
                    <td style="text-align:right;"><?= $cs . number_format((float)$request['net_amount'], 2) ?></td>
                </tr>
            </table>
        </div>

        <!-- Notes -->
        <?php if (!empty($request['notes'])): ?>
        <div class="notes-box">
            <div class="label">Notes:</div>
            <p><?= nl2br(htmlspecialchars($request['notes'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Signatures -->
        <div class="signatures">
            <div class="sig-box"><div class="sig-line">Customer Signature</div></div>
            <div class="sig-box"><div class="sig-line">Authorized Signature</div></div>
        </div>

        <!-- Footer -->
        <div class="inv-footer">
            <p>Thank you for your business! | <?= htmlspecialchars($bname) ?></p>
        </div>
    </div>
</body>
</html>
