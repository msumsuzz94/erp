<?php
/**
 * Receivable Statement
 * Printable statement for individual customer receivables
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$customer_id = get_param('customer_id', 0);

if (!$customer_id) {
    die('Invalid customer ID');
}

// Get customer details
$customer = db_query("SELECT * FROM customers WHERE id = ?", [$customer_id]);
if (empty($customer)) {
    die('Customer not found');
}
$customer = $customer[0];

// Get all sales for this customer
$sql = "SELECT s.*, 
        SUM(s.total_amount) OVER() as overall_total,
        SUM(s.paid_amount) OVER() as overall_paid,
        SUM(s.due_amount) OVER() as overall_due
        FROM sales s
        WHERE s.customer_id = ? AND s.status = 'completed' AND s.due_amount > 0
        ORDER BY s.sale_date DESC";
$sales = db_query($sql, [$customer_id]);

// Calculate totals
$total_sales = 0;
$total_paid = 0;
$total_due = 0;
foreach ($sales as $sale) {
    $total_sales += $sale['total_amount'];
    $total_paid += $sale['paid_amount'];
    $total_due += $sale['due_amount'];
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
    <title>Receivable Statement - <?= htmlspecialchars($customer['name']) ?></title>
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

    <h2 style="text-align: center; margin-bottom: 20px;">RECEIVABLE STATEMENT</h2>

    <div class="statement-info">
        <div class="info-block">
            <h3>Customer Information</h3>
            <p><strong><?= htmlspecialchars($customer['name']) ?></strong></p>
            <?php if (!empty($customer['phone'])): ?>
                <p>Phone: <?= htmlspecialchars($customer['phone']) ?></p>
            <?php endif; ?>
            <?php if (!empty($customer['email'])): ?>
                <p>Email: <?= htmlspecialchars($customer['email']) ?></p>
            <?php endif; ?>
            <?php if (!empty($customer['address'])): ?>
                <p>Address: <?= htmlspecialchars($customer['address']) ?></p>
            <?php endif; ?>
        </div>

        <div class="info-block text-right">
            <h3>Statement Details</h3>
            <p><strong>Statement Date:</strong> <?= date('d M Y') ?></p>
            <p><strong>Customer ID:</strong> <?= $customer['id'] ?></p>
        </div>
    </div>

    <?php if (empty($sales)): ?>
        <p class="text-center" style="padding: 40px; background: #f9f9f9;">No outstanding receivables for this customer.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 20%;">Invoice#</th>
                    <th style="width: 15%;" class="text-right">Total Amount</th>
                    <th style="width: 15%;" class="text-right">Paid Amount</th>
                    <th style="width: 15%;" class="text-right">Due Amount</th>
                    <th style="width: 20%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><?= format_date($sale['sale_date']) ?></td>
                        <td><?= htmlspecialchars($sale['invoice_number']) ?></td>
                        <td class="text-right"><?= format_currency($sale['total_amount']) ?></td>
                        <td class="text-right"><?= format_currency($sale['paid_amount']) ?></td>
                        <td class="text-right" style="color: red;"><strong><?= format_currency($sale['due_amount']) ?></strong></td>
                        <td><?= ucfirst($sale['payment_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-box">
            <h3>Receivable Summary</h3>
            <div class="summary-row">
                <span>Total Sales:</span>
                <span><?= format_currency($total_sales) ?></span>
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
        <p>Please remit payment at your earliest convenience.</p>
        <p>This is a computer-generated statement.</p>
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
