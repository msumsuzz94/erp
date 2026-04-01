<?php
/**
 * Transaction History Page
 * View all cash and bank account transactions
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get filters
$account_type = isset($_GET['account_type']) ? clean_input($_GET['account_type']) : '';
$account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : date('Y-m-d');
$trans_type = isset($_GET['trans_type']) ? clean_input($_GET['trans_type']) : '';

// Build query for combined transactions
$transactions = [];

// Get cash transactions
if ($account_type == '' || $account_type == 'cash') {
    $cash_sql = "SELECT ct.*, ca.account_name, 'cash' as account_type
                 FROM cash_transactions ct
                 INNER JOIN cash_accounts ca ON ct.account_id = ca.id
                 WHERE ct.transaction_date BETWEEN '$start_date' AND '$end_date'";
    
    if ($account_id > 0 && $account_type == 'cash') {
        $cash_sql .= " AND ct.account_id = $account_id";
    }
    
    if ($trans_type) {
        $cash_sql .= " AND ct.transaction_type = '$trans_type'";
    }
    
    $cash_transactions = db_query($cash_sql);
    $transactions = array_merge($transactions, $cash_transactions);
}

// Get bank transactions
if ($account_type == '' || $account_type == 'bank') {
    $bank_sql = "SELECT bt.*, ba.bank_name as account_name, 'bank' as account_type
                 FROM bank_transactions bt
                 INNER JOIN bank_accounts ba ON bt.account_id = ba.id
                 WHERE bt.transaction_date BETWEEN '$start_date' AND '$end_date'";
    
    if ($account_id > 0 && $account_type == 'bank') {
        $bank_sql .= " AND bt.account_id = $account_id";
    }
    
    if ($trans_type) {
        $bank_sql .= " AND bt.transaction_type = '$trans_type'";
    }
    
    $bank_transactions = db_query($bank_sql);
    $transactions = array_merge($transactions, $bank_transactions);
}

// Sort by date
usort($transactions, function($a, $b) {
    return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
});

// Calculate totals
$total_debit = 0;
$total_credit = 0;

foreach ($transactions as $trans) {
    if ($trans['transaction_type'] == 'debit') {
        $total_debit += $trans['amount'];
    } else {
        $total_credit += $trans['amount'];
    }
}

$net_balance = $total_credit - $total_debit;

// Get all accounts for filter
$cash_accounts = db_query("SELECT * FROM cash_accounts ORDER BY account_name ASC");
$bank_accounts = db_query("SELECT * FROM bank_accounts ORDER BY bank_name ASC");

// Get business settings for header
$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$invoice_settings) {
    $invoice_settings = [
        'company_name' => defined('BUSINESS_NAME') ? BUSINESS_NAME : '',
        'company_address' => defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : '',
        'company_phone' => defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '',
        'company_email' => defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : '',
        'company_website' => '',
        'company_logo' => 'assets/images/logo.png',
        'company_slogan' => ''
    ];
}

$page_title = 'Transaction History';
include __DIR__ . '/../../templates/header.php';
?>

<style>
/* Print Area Styles (Hidden on Screen) */
#print-area {
    display: none;
    padding: 20px;
    background: #fff;
    color: #000;
}

@media print {
    @page { margin: 0.25cm; size: auto; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; width: 100% !important; }
    
    /* Hide regular UI AND standard print header/footer */
    .no-print, .btn, .card, .navbar, .sidebar, #sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, .summary-cards, #standard-print-wrapper, #standard-print-footer, form, .alert {
        display: none !important;
    }
    
    #print-area { display: block !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    
    .print-container { 
        width: 100% !important; 
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        box-sizing: border-box !important; 
        font-family: \'Segoe UI\', Arial, sans-serif; 
        font-size: 11px; 
    }
    
    /* 3-Column Header */
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .header-table td { vertical-align: top; border: none !important; padding: 0; }
    .logo-cell { width: 10%; text-align: left; }
    .logo-cell img { max-width: 80px; height: auto; display: block; }
    
    .title-cell { width: 65%; text-align: center; padding: 0 10px; }
    .company-name { font-size: 22px; font-weight: 900; color: #000; margin: 0; text-transform: uppercase; border-bottom: 2px solid #000; display: inline-block; line-height: 1.2; padding-bottom: 2px; }
    .company-slogan { font-size: 11px; color: #000; font-weight: bold; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .info-cell { width: 25%; text-align: left; line-height: 1.4; font-size: 9px; border: 1px solid #000; padding: 5px 8px; box-sizing: border-box; }
    .info-cell p { margin: 0; margin-bottom: 2px; }
    .info-cell p:last-child { margin-bottom: 0; }
    
    .report-main-title { text-align: center; font-size: 16px; color: #000; margin: 12px 0; font-weight: bold; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 0; }

    /* Simple Bordered Table */
    .print-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .print-table th, .print-table td { border: 1px solid #333 !important; padding: 6px; text-align: left; }
    .print-table th { background: #f2f2f2 !important; font-weight: bold; text-transform: uppercase; }
    .text-end { text-align: right !important; }
    
    /* Fixed Footer at bottom of EVERY page */
    .print-footer { 
        position: fixed;
        bottom: 0.3cm;
        left: 0;
        right: 0;
        border-top: 1px solid #333; 
        padding-top: 10px; 
        display: block;
        width: 100%;
        text-align: center;
        font-size: 10px; 
        background: #fff;
        z-index: 9999;
    }
}
</style>

<!-- Hidden Print Area -->
<div id="print-area">
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
                    <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo">
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

        <div class="report-main-title">Transaction History Report (Date: <?= format_date($start_date) ?> to <?= format_date($end_date) ?>)</div>

        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Account</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trans): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($trans['transaction_date'])) ?></td>
                        <td><?= htmlspecialchars($trans['account_name']) ?> (<?= strtoupper($trans['account_type']) ?>)</td>
                        <td><?= htmlspecialchars($trans['reference_type'] ?? '') ?> <?= $trans['reference_id'] ? '#'.$trans['reference_id'] : '' ?></td>
                        <td><?= htmlspecialchars($trans['description'] ?? '-') ?></td>
                        <td class="text-end"><?= $trans['transaction_type'] == 'debit' ? format_currency($trans['amount']) : '-' ?></td>
                        <td class="text-end"><?= $trans['transaction_type'] == 'credit' ? format_currency($trans['amount']) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f2f2f2;">
                    <th colspan="4" class="text-end">TOTAL</th>
                    <th class="text-end"><?= format_currency($total_debit) ?></th>
                    <th class="text-end"><?= format_currency($total_credit) ?></th>
                </tr>
            </tfoot>
        </table>

        <!-- Print Footer -->
        <div class="print-footer">
            <?php if (!empty($invoice_settings['invoice_footer_text'])): ?>
                <div class="mb-2" style="font-weight: bold; border-bottom: 1px solid #ddd; padding-bottom: 5px;">
                    <?= nl2br(htmlspecialchars($invoice_settings['invoice_footer_text'])) ?>
                </div>
            <?php endif; ?>
            <div>Copyright @ CITNEX ERP & POS 2026 All right Reserved</div>
            <div style="font-size: 9px; margin-top: 3px;">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<div class="container-fluid no-print">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Debits</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($total_debit) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-down fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Credits</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($total_credit) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Net Balance</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_currency(abs($net_balance)) ?>
                                <?php if ($net_balance < 0): ?>
                                    <small class="text-danger">(Deficit)</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-balance-scale fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Transactions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($transactions) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-receipt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-2 mb-3">
                    <label>Account Type</label>
                    <select name="account_type" id="account_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="cash" <?= $account_type == 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="bank" <?= $account_type == 'bank' ? 'selected' : '' ?>>Bank</option>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <label>Account</label>
                    <select name="account_id" id="account_id" class="form-control">
                        <option value="0">All Accounts</option>
                        <optgroup label="Cash Accounts" id="cash_accounts_group">
                            <?php foreach ($cash_accounts as $account): ?>
                                <option value="<?= $account['id'] ?>" data-type="cash" 
                                    <?= $account_id == $account['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($account['account_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Bank Accounts" id="bank_accounts_group">
                            <?php foreach ($bank_accounts as $account): ?>
                                <option value="<?= $account['id'] ?>" data-type="bank"
                                    <?= $account_id == $account['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($account['bank_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <div class="col-md-2 mb-3">
                    <label>Transaction Type</label>
                    <select name="trans_type" class="form-control">
                        <option value="">All Types</option>
                        <option value="debit" <?= $trans_type == 'debit' ? 'selected' : '' ?>>Debit</option>
                        <option value="credit" <?= $trans_type == 'credit' ? 'selected' : '' ?>>Credit</option>
                    </select>
                </div>

                <div class="col-md-2 mb-3">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                </div>

                <div class="col-md-2 mb-3">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                </div>

                <div class="col-md-1 mb-3">
                    <label>&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <button type="button" onclick="window.print()" class="btn btn-success flex-grow-1">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Transactions</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="transactionsTable" width="100%">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Account</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th>Debit</th>
                            <th>Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trans): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($trans['transaction_date'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $trans['account_type'] == 'cash' ? 'success' : 'info' ?>">
                                        <?= strtoupper($trans['account_type']) ?>
                                    </span>
                                    <?= htmlspecialchars($trans['account_name']) ?>
                                </td>
                                <td>
                                    <?php if ($trans['transaction_type'] == 'debit'): ?>
                                        <span class="badge badge-danger">Debit</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Credit</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($trans['reference_type']): ?>
                                        <small class="text-muted"><?= ucfirst($trans['reference_type']) ?></small>
                                        <?php if ($trans['reference_id']): ?>
                                            #<?= $trans['reference_id'] ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($trans['description'] ?? '-') ?></td>
                                <td class="text-danger">
                                    <?= $trans['transaction_type'] == 'debit' ? format_currency($trans['amount']) : '-' ?>
                                </td>
                                <td class="text-success">
                                    <?= $trans['transaction_type'] == 'credit' ? format_currency($trans['amount']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="5" class="text-right">Total:</td>
                            <td class="text-danger"><?= format_currency($total_debit) ?></td>
                            <td class="text-success"><?= format_currency($total_credit) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#transactionsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25
    });

    // Filter accounts based on account type
    $('#account_type').on('change', function() {
        var type = $(this).val();
        $('#account_id option[data-type]').parent('optgroup').hide();
        
        if (type === '') {
            $('#account_id optgroup').show();
        } else if (type === 'cash') {
            $('#cash_accounts_group').show();
        } else if (type === 'bank') {
            $('#bank_accounts_group').show();
        }
        
        $('#account_id').val('0');
    });
});
</script>
