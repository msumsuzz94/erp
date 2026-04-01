<?php
/**
 * Print Salary / Payslip
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$id = get_param('id');
if (!$id) {
    die('Invalid Request');
}

// Get salary and staff details
$sql = "SELECT s.*, st.name as staff_name, st.employee_code, st.designation, st.department, st.joining_date 
        FROM salaries s 
        INNER JOIN staff st ON s.staff_id = st.id 
        WHERE s.id = ?";
$salary = db_query($sql, [$id]);

if (empty($salary)) {
    die('Salary record not found');
}
$salary = $salary[0];

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$salary_month_name = $months[$salary['month']] . ' ' . $salary['year'];

// Get invoice settings for print header
$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$invoice_settings) {
    $invoice_settings = [
        'company_name' => defined('BUSINESS_NAME') ? BUSINESS_NAME : 'ERP System',
        'company_address' => defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : '',
        'company_phone' => defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '',
        'company_email' => defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : '',
        'company_logo' => 'assets/images/logo.png',
        'company_slogan' => ''
    ];
}

$page_title = 'Print Payslip - ' . $salary['staff_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <!-- Basic Bootstrap for structure -->
    <link href="<?= BASE_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 30px;
            box-sizing: border-box;
        }
        
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header-table td { vertical-align: top; border: none !important; padding: 0; }
        .logo-cell { width: 15%; text-align: left; }
        .logo-cell img { max-width: 100px; height: auto; display: block; }
        
        .title-cell { width: 70%; text-align: center; padding: 0 10px; }
        .company-name { font-size: 26px; font-weight: 900; color: #000; margin: 0; text-transform: uppercase; line-height: 1.2; }
        .company-slogan { font-size: 14px; color: #000; font-weight: bold; margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .company-address { font-size: 12px; margin-top: 5px; }
        
        .payslip-title { text-align: center; font-size: 22px; font-weight: bold; margin: 20px 0; text-transform: uppercase; text-decoration: underline; }
        
        .info-table { width: 100%; margin-bottom: 30px; }
        .info-table td { padding: 5px; font-size: 14px; vertical-align: top; }
        .info-table td strong { display: inline-block; width: 120px; }
        
        .salary-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .salary-table th, .salary-table td { border: 1px solid #000; padding: 10px; font-size: 14px; }
        .salary-table th { background-color: #f2f2f2; text-transform: uppercase; }
        .text-right { text-align: right; }
        .font-weight-bold { font-weight: bold; }
        
        .amount-in-words { margin-bottom: 40px; font-style: italic; font-size: 14px; }
        
        .signatures { width: 100%; margin-top: 50px; }
        .signatures td { text-align: center; vertical-align: bottom; height: 80px; }
        .signature-line { border-top: 1px solid #000; width: 200px; display: inline-block; margin-top: 50px; padding-top: 5px; font-weight: bold; }
        
        .no-print { margin-bottom: 20px; text-align: center; }
        .btn { padding: 8px 15px; background: #4e73df; color: #fff; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; display: inline-block; margin: 0 5px; }
        .btn-secondary { background: #858796; }

        @media print {
            @page { margin: 0.25cm; size: portrait; }
            body { padding: 0; margin: 0; }
            .payslip-container { border: none; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            .salary-table th { background-color: #e0e0e0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn">Print Payslip</button>
    <button onclick="window.close()" class="btn btn-secondary">Close</button>
</div>

<div class="payslip-container">
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <?php if(!empty($invoice_settings['company_logo']) && file_exists(__DIR__ . '/../../' . $invoice_settings['company_logo'])): ?>
                    <img src="<?= BASE_URL ?>/<?= $invoice_settings['company_logo'] ?>" alt="Logo">
                <?php endif; ?>
            </td>
            <td class="title-cell">
                <h2 class="company-name"><?= htmlspecialchars($invoice_settings['company_name']) ?></h2>
                <?php if(!empty($invoice_settings['company_slogan'])): ?>
                    <div class="company-slogan"><?= htmlspecialchars($invoice_settings['company_slogan']) ?></div>
                <?php endif; ?>
                <div class="company-address">
                    <?= nl2br(htmlspecialchars($invoice_settings['company_address'])) ?><br>
                    Phone: <?= htmlspecialchars($invoice_settings['company_phone']) ?> | Email: <?= htmlspecialchars($invoice_settings['company_email']) ?>
                </div>
            </td>
        </tr>
    </table>
    
    <div class="payslip-title">PAYSLIP FOR <?= strtoupper($salary_month_name) ?></div>
    
    <table class="info-table">
        <tr>
            <td width="50%">
                <strong>Employee Name:</strong> <?= htmlspecialchars($salary['staff_name']) ?><br>
                <strong>Employee ID:</strong> <?= htmlspecialchars($salary['employee_code'] ?? 'N/A') ?><br>
                <strong>Designation:</strong> <?= htmlspecialchars($salary['designation'] ?? 'N/A') ?>
            </td>
            <td width="50%">
                <strong>Department:</strong> <?= htmlspecialchars($salary['department'] ?? 'N/A') ?><br>
                <strong>Payment Date:</strong> <?= format_date($salary['payment_date']) ?><br>
                <strong>Payment Mode:</strong> <?= ucfirst($salary['payment_method']) ?>
            </td>
        </tr>
    </table>
    
    <table class="salary-table">
        <thead>
            <tr>
                <th width="40%">Earnings</th>
                <th width="10%" class="text-right">Amount</th>
                <th width="40%">Deductions</th>
                <th width="10%" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Basic Salary</td>
                <td class="text-right"><?= format_currency($salary['basic_salary']) ?></td>
                <td>Deductions / Fines</td>
                <td class="text-right"><?= format_currency($salary['deductions']) ?></td>
            </tr>
            <tr>
                <td>Allowances / Bonus</td>
                <td class="text-right"><?= format_currency($salary['allowances']) ?></td>
                <td></td>
                <td class="text-right"></td>
            </tr>
            <tr>
                <td class="font-weight-bold text-right">Total Earnings</td>
                <td class="font-weight-bold text-right"><?= format_currency($salary['basic_salary'] + $salary['allowances']) ?></td>
                <td class="font-weight-bold text-right">Total Deductions</td>
                <td class="font-weight-bold text-right"><?= format_currency($salary['deductions']) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="font-weight-bold text-right" style="font-size: 16px;">NET SALARY PAYABLE</td>
                <td class="font-weight-bold text-right" style="font-size: 16px; background-color: #f8f9fa; -webkit-print-color-adjust: exact; print-color-adjust: exact;"><?= format_currency($salary['net_salary']) ?></td>
            </tr>
        </tbody>
    </table>
    
    <div class="amount-in-words">
        Note: This is a computer-generated document and does not require a physical signature for validity unless specified.
    </div>
    
    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line">Employer Signature</div>
            </td>
            <td>
                <div class="signature-line">Employee Signature</div>
            </td>
        </tr>
    </table>
</div>

<script>
    // Auto print when page loads
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>
</body>
</html>
