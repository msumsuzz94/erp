<?php
/**
 * Payable Statement
 * Printable statement for individual supplier payables
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$supplier_id = get_param('supplier_id', 0);

if (!$supplier_id) {
    die('Invalid supplier ID');
}

// Get supplier details
$supplier = db_query("SELECT * FROM suppliers WHERE id = ?", [$supplier_id]);
if (empty($supplier)) {
    die('Supplier not found');
}
$supplier = $supplier[0];

// Get all purchases from this supplier
$sql = "SELECT p.*
        FROM purchases p
        WHERE p.supplier_id = ? AND p.status = 'completed' AND p.due_amount > 0
        ORDER BY p.purchase_date DESC";
$purchases = db_query($sql, [$supplier_id]);

// Calculate totals
$total_purchases = 0;
$total_paid = 0;
$total_due = 0;
foreach ($purchases as $purchase) {
    $total_purchases += $purchase['total_amount'];
    $total_paid += $purchase['paid_amount'];
    $total_due += $purchase['due_amount'];
}

// Get company settings
$settings = [];
$settings_raw = db_query("SELECT setting_key, setting_value FROM settings");
foreach ($settings_raw as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payable Statement - <?= htmlspecialchars($supplier['name']) ?></title>
    <style>
        @media print {
            .no-print { display: none; }
            @page { margin: 0.25cm; }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .statement-info {
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
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
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
        .summary-box {
            background-color: #f9f9f9;
            border: 2px solid #333;
            padding: 15px;
            margin-top: 20px;
        }
        .summary-box h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }
        .summary-row.total {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #333;
            margin-top: 10px;
            padding-top: 10px;
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
        }
        .print-btn:hover {
            background: #45a049;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #777;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Print Statement</button>

    <div class="header">
        <h1><?= htmlspecialchars($settings['company_name'] ?? 'Business Management System') ?></h1>
        <p><?= htmlspecialchars($settings['company_address'] ?? '') ?></p>
        <p>Phone: <?= htmlspecialchars($settings['company_phone'] ?? '') ?> | Email: <?= htmlspecialchars($settings['company_email'] ?? '') ?></p>
    </div>

    <h2 style="text-align: center; margin-bottom: 20px;">PAYABLE STATEMENT</h2>

    <div class="statement-info">
        <div class="info-block">
            <h3>Supplier Information</h3>
            <p><strong><?= htmlspecialchars($supplier['name']) ?></strong></p>
            <?php if (!empty($supplier['phone'])): ?>
                <p>Phone: <?= htmlspecialchars($supplier['phone']) ?></p>
            <?php endif; ?>
            <?php if (!empty($supplier['email'])): ?>
                <p>Email: <?= htmlspecialchars($supplier['email']) ?></p>
            <?php endif; ?>
            <?php if (!empty($supplier['address'])): ?>
                <p>Address: <?= htmlspecialchars($supplier['address']) ?></p>
            <?php endif; ?>
        </div>

        <div class="info-block text-right">
            <h3>Statement Details</h3>
            <p><strong>Statement Date:</strong> <?= date('d M Y') ?></p>
            <p><strong>Supplier ID:</strong> <?= $supplier['id'] ?></p>
        </div>
    </div>

    <?php if (empty($purchases)): ?>
        <p class="text-center" style="padding: 40px; background: #f9f9f9;">No outstanding payables to this supplier.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 20%;">Purchase#</th>
                    <th style="width: 15%;" class="text-right">Total Amount</th>
                    <th style="width: 15%;" class="text-right">Paid Amount</th>
                    <th style="width: 15%;" class="text-right">Due Amount</th>
                    <th style="width: 20%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $purchase): ?>
                    <tr>
                        <td><?= format_date($purchase['purchase_date']) ?></td>
                        <td><?= htmlspecialchars($purchase['purchase_number']) ?></td>
                        <td class="text-right"><?= format_currency($purchase['total_amount']) ?></td>
                        <td class="text-right"><?= format_currency($purchase['paid_amount']) ?></td>
                        <td class="text-right" style="color: red;"><strong><?= format_currency($purchase['due_amount']) ?></strong></td>
                        <td><?= ucfirst($purchase['payment_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-box">
            <h3>Payable Summary</h3>
            <div class="summary-row">
                <span>Total Purchases:</span>
                <span><?= format_currency($total_purchases) ?></span>
            </div>
            <div class="summary-row">
                <span>Total Paid:</span>
                <span><?= format_currency($total_paid) ?></span>
            </div>
            <div class="summary-row total">
                <span>Total Outstanding:</span>
                <span style="color: red;"><?= format_currency($total_due) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="footer">
        <p>Payment will be made according to agreed terms.</p>
        <p>This is a computer-generated statement.</p>
        <p>Thank you for your services!</p>
    </div>
</body>
</html>
