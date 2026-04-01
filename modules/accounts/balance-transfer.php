<?php
/**
 * Balance Transfer Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle transfer deletion
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $transfer_id = (int)$_POST['delete_id'];
        $transfer = db_select_one('balance_transfers', ['id' => $transfer_id]);
        
        if ($transfer) {
            $amount = $transfer['amount'];
            
            // Reverse from account balance (Add back)
            $from_table = $transfer['from_account_type'] === 'cash' ? 'cash_accounts' : 'bank_accounts';
            $from_account = db_select_one($from_table, ['id' => $transfer['from_account_id']]);
            if ($from_account) {
                db_update($from_table, ['current_balance' => $from_account['current_balance'] + $amount], ['id' => $transfer['from_account_id']]);
            }
            
            // Reverse to account balance (Subtract)
            $to_table = $transfer['to_account_type'] === 'cash' ? 'cash_accounts' : 'bank_accounts';
            $to_account = db_select_one($to_table, ['id' => $transfer['to_account_id']]);
            if ($to_account) {
                db_update($to_table, ['current_balance' => $to_account['current_balance'] - $amount], ['id' => $transfer['to_account_id']]);
            }
            
            // Delete transaction records
            if ($transfer['from_account_type'] === 'cash') {
                db_delete('cash_transactions', ['reference_type' => 'balance_transfer', 'reference_id' => $transfer_id]);
            } else {
                db_delete('bank_transactions', ['reference_type' => 'balance_transfer', 'reference_id' => $transfer_id]);
            }
            
            if ($transfer['to_account_type'] === 'cash') {
                db_delete('cash_transactions', ['reference_type' => 'balance_transfer', 'reference_id' => $transfer_id]);
            } else {
                db_delete('bank_transactions', ['reference_type' => 'balance_transfer', 'reference_id' => $transfer_id]);
            }
            
            // Delete the transfer record
            db_delete('balance_transfers', ['id' => $transfer_id]);
            
            log_activity(get_current_user_id(), 'delete_balance_transfer', "Deleted balance transfer of " . format_currency($amount));
            redirect_with_message($_SERVER['PHP_SELF'], 'Transfer deleted and balances reverted', 'success');
        }
    }
}

// Handle transfer submission
if (is_post() && isset($_POST['from_account_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        $from_type = clean_input($_POST['from_account_type']);
        $from_id = (int)$_POST['from_account_id'];
        $to_type = clean_input($_POST['to_account_type']);
        $to_id = (int)$_POST['to_account_id'];
        $amount = (float)$_POST['amount'];
        $transfer_date = clean_input($_POST['transfer_date']);
        $description = clean_input($_POST['description']);
        
        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }
        
        if ($from_type === $to_type && $from_id === $to_id) {
            $errors[] = 'Cannot transfer to the same account';
        }
        
        if (empty($errors)) {
            // Insert transfer record
            $transfer_data = [
                'from_account_type' => $from_type,
                'from_account_id' => $from_id,
                'to_account_type' => $to_type,
                'to_account_id' => $to_id,
                'amount' => $amount,
                'transfer_date' => $transfer_date,
                'description' => $description,
                'created_by' => get_current_user_id()
            ];
            
            $transfer_id = db_insert('balance_transfers', $transfer_data);
            
            if ($transfer_id) {
                // Deduct from source account
                $from_table = $from_type === 'cash' ? 'cash_accounts' : 'bank_accounts';
                $from_account = db_select_one($from_table, ['id' => $from_id]);
                $new_from_balance = $from_account['current_balance'] - $amount;
                db_update($from_table, ['current_balance' => $new_from_balance], ['id' => $from_id]);
                
                // Record source transaction
                $from_trans_table = $from_type === 'cash' ? 'cash_transactions' : 'bank_transactions';
                db_insert($from_trans_table, [
                    'account_id' => $from_id,
                    'transaction_type' => 'credit',
                    'amount' => $amount,
                    'reference_type' => 'balance_transfer',
                    'reference_id' => $transfer_id,
                    'description' => "Balance Transfer to " . ucfirst($to_type) . ": " . $description,
                    'transaction_date' => $transfer_date,
                    'created_by' => get_current_user_id()
                ]);
                
                // Add to destination account
                $to_table = $to_type === 'cash' ? 'cash_accounts' : 'bank_accounts';
                $to_account = db_select_one($to_table, ['id' => $to_id]);
                $new_to_balance = $to_account['current_balance'] + $amount;
                db_update($to_table, ['current_balance' => $new_to_balance], ['id' => $to_id]);
                
                // Record destination transaction
                $to_trans_table = $to_type === 'cash' ? 'cash_transactions' : 'bank_transactions';
                db_insert($to_trans_table, [
                    'account_id' => $to_id,
                    'transaction_type' => 'debit',
                    'amount' => $amount,
                    'reference_type' => 'balance_transfer',
                    'reference_id' => $transfer_id,
                    'description' => "Balance Transfer from " . ucfirst($from_type) . ": " . $description,
                    'transaction_date' => $transfer_date,
                    'created_by' => get_current_user_id()
                ]);
                
                log_activity(get_current_user_id(), 'balance_transfer', "Transferred " . format_currency($amount) . " from $from_type to $to_type");
                redirect_with_message($_SERVER['PHP_SELF'], 'Balance transferred successfully', 'success');
            } else {
                $errors[] = 'Failed to record transfer';
            }
        }
    }
}

// Get cash and bank accounts
$cash_accounts = db_select('cash_accounts', []);
$bank_accounts = db_select('bank_accounts', []);

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

// Get filters
$from_date = get_param('from_date', '');
$to_date = get_param('to_date', '');
$f_account_type = get_param('from_account_type', '');
$t_account_type = get_param('to_account_type', '');

// Get transfers with filters
$sql = "SELECT t.*, 
        CASE 
            WHEN t.from_account_type = 'cash' THEN c.account_name 
            ELSE CONCAT(b.bank_name, ' (', b.account_number, ')') 
        END as from_account_name,
        CASE 
            WHEN t.to_account_type = 'cash' THEN c2.account_name 
            ELSE CONCAT(b2.bank_name, ' (', b2.account_number, ')') 
        END as to_account_name
        FROM balance_transfers t
        LEFT JOIN cash_accounts c ON t.from_account_type = 'cash' AND t.from_account_id = c.id
        LEFT JOIN bank_accounts b ON t.from_account_type != 'cash' AND t.from_account_id = b.id
        LEFT JOIN cash_accounts c2 ON t.to_account_type = 'cash' AND t.to_account_id = c2.id
        LEFT JOIN bank_accounts b2 ON t.to_account_type != 'cash' AND t.to_account_id = b2.id
        WHERE 1=1";

$params = [];
if (!empty($from_date)) {
    $sql .= " AND t.transfer_date >= ?";
    $params[] = $from_date;
}
if (!empty($to_date)) {
    $sql .= " AND t.transfer_date <= ?";
    $params[] = $to_date;
}
if (!empty($f_account_type)) {
    $sql .= " AND t.from_account_type = ?";
    $params[] = $f_account_type;
}
if (!empty($t_account_type)) {
    $sql .= " AND t.to_account_type = ?";
    $params[] = $t_account_type;
}

$sql .= " ORDER BY t.transfer_date DESC, t.created_at DESC";
$transfers = db_query($sql, $params);

$page_title = 'Balance Transfer';
include __DIR__ . '/../../templates/header.php';
?>

$additional_css = '
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
    .print-table th, .print-table td { border: 1px solid #333 !important; padding: 8px; text-align: left; }
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
        font-size: 10px; 
        background: #fff;
        z-index: 9999;
    }
    .footer-left { float: left; width: 50%; font-weight: bold; text-align: left; padding-left: 10px; }
    .footer-right { float: right; width: 50%; text-align: right; padding-right: 10px; }
    .clearfix::after { content: ""; clear: both; display: table; }
}
</style>
';

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

        <div class="report-main-title">Balance Transfer Report (Date: <?= date('d M Y') ?>)</div>

        <!-- Data Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>From Account</th>
                    <th>To Account</th>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $total_amount = 0; ?>
                <?php foreach ($transfers as $transfer): ?>
                    <?php $total_amount += $transfer['amount']; ?>
                    <tr>
                        <td><?= format_date($transfer['transfer_date']) ?></td>
                        <td>
                            <?= ucfirst($transfer['from_account_type']) ?>
                            <br><span style="font-size: 9px;"><?= htmlspecialchars($transfer['from_account_name']) ?></span>
                        </td>
                        <td>
                            <?= ucfirst($transfer['to_account_type']) ?>
                            <br><span style="font-size: 9px;"><?= htmlspecialchars($transfer['to_account_name']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($transfer['description']) ?></td>
                        <td class="text-end"><?= format_currency($transfer['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f2f2f2;">
                    <th colspan="4" class="text-end">TOTAL TRANSFERRED</th>
                    <th class="text-end"><?= format_currency($total_amount) ?></th>
                </tr>
            </tfoot>
        </table>

        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<div class="no-print">

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Transfer Form -->
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Transfer Balance</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <h6 class="text-primary border-bottom pb-2">From Account</h6>
                    <div class="form-group mb-3">
                        <label>Account Type <span class="text-danger">*</span></label>
                        <select name="from_account_type" id="from_type" class="form-control form-control-sm" required>
                            <option value="">Select Type</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="card">Card</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label>Account <span class="text-danger">*</span></label>
                        <select name="from_account_id" id="from_account" class="form-control form-control-sm" required>
                            <option value="">Select Account</option>
                        </select>
                    </div>
                    
                    <h6 class="text-primary border-bottom pb-2 mt-4">To Account</h6>
                    <div class="form-group mb-3">
                        <label>Account Type <span class="text-danger">*</span></label>
                        <select name="to_account_type" id="to_type" class="form-control form-control-sm" required>
                            <option value="">Select Type</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="card">Card</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label>Account <span class="text-danger">*</span></label>
                        <select name="to_account_id" id="to_account" class="form-control form-control-sm" required>
                            <option value="">Select Account</option>
                        </select>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" class="form-control form-control-sm" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Transfer Date <span class="text-danger">*</span></label>
                                <input type="date" name="transfer_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-exchange-alt"></i> Transfer Balance
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Filters and List -->
    <div class="col-md-8">
        <!-- Filter Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filter Transfers</h6>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small">From Date</label>
                                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small">To Date</label>
                                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small">From Type</label>
                                <select name="from_account_type" class="form-control form-control-sm">
                                    <option value="">All Types</option>
                                    <option value="cash" <?= $f_account_type === 'cash' ? 'selected' : '' ?>>Cash</option>
                                    <option value="bank" <?= $f_account_type === 'bank' ? 'selected' : '' ?>>Bank</option>
                                    <option value="card" <?= $f_account_type === 'card' ? 'selected' : '' ?>>Card</option>
                                    <option value="bkash" <?= $f_account_type === 'bkash' ? 'selected' : '' ?>>bKash</option>
                                    <option value="nagad" <?= $f_account_type === 'nagad' ? 'selected' : '' ?>>Nagad</option>
                                    <option value="rocket" <?= $f_account_type === 'rocket' ? 'selected' : '' ?>>Rocket</option>
                                    <option value="mobile_money" <?= $f_account_type === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small">To Type</label>
                                <select name="to_account_type" class="form-control form-control-sm">
                                    <option value="">All Types</option>
                                    <option value="cash" <?= $t_account_type === 'cash' ? 'selected' : '' ?>>Cash</option>
                                    <option value="bank" <?= $t_account_type === 'bank' ? 'selected' : '' ?>>Bank</option>
                                    <option value="card" <?= $t_account_type === 'card' ? 'selected' : '' ?>>Card</option>
                                    <option value="bkash" <?= $t_account_type === 'bkash' ? 'selected' : '' ?>>bKash</option>
                                    <option value="nagad" <?= $t_account_type === 'nagad' ? 'selected' : '' ?>>Nagad</option>
                                    <option value="rocket" <?= $t_account_type === 'rocket' ? 'selected' : '' ?>>Rocket</option>
                                    <option value="mobile_money" <?= $t_account_type === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-redo"></i> Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transfers List Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Transfer List</h6>
                <button onclick="window.print()" class="btn btn-sm btn-success no-print"><i class="fas fa-print"></i> Print</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm" id="transfersTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>From Account</th>
                                <th>To Account</th>
                                <th class="text-right">Amount</th>
                                <th>Description</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transfers as $transfer): ?>
                                <tr>
                                    <td><?= format_date($transfer['transfer_date']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $transfer['from_account_type'] === 'cash' ? 'info' : 'primary' ?>">
                                            <?= ucfirst($transfer['from_account_type']) ?>
                                        </span>
                                        <br><small><?= htmlspecialchars($transfer['from_account_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $transfer['to_account_type'] === 'cash' ? 'info' : 'primary' ?>">
                                            <?= ucfirst($transfer['to_account_type']) ?>
                                        </span>
                                        <br><small><?= htmlspecialchars($transfer['to_account_name']) ?></small>
                                    </td>
                                    <td class="text-right font-weight-bold"><?= format_currency($transfer['amount']) ?></td>
                                    <td><?= htmlspecialchars($transfer['description']) ?></td>
                                    <td class="no-print text-center">
                                        <button type="button" class="btn btn-sm btn-danger delete-transfer" 
                                                data-id="<?= $transfer['id'] ?>" 
                                                data-amount="<?= format_currency($transfer['amount']) ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
var cashAccounts = <?= json_encode($cash_accounts) ?>;
var bankAccounts = <?= json_encode($bank_accounts) ?>;

$(document).ready(function() {
    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#transfersTable').DataTable({
            "order": [[0, "desc"]],
            "pageLength": 25,
            "columnDefs": [
                { "orderable": false, "targets": 5 }
            ]
        });
    }

    $('#from_type').change(function() {
        populateAccounts('from_account', $(this).val());
    });

    $('#to_type').change(function() {
        populateAccounts('to_account', $(this).val());
    });

    // Handle Delete
    $('.delete-transfer').click(function() {
        const id = $(this).data('id');
        const amount = $(this).data('amount');
        if (confirm('Are you sure you want to delete this transfer of ' + amount + '? This will also revert account balances.')) {
            $('#delete_id').val(id);
            $('#deleteForm').submit();
        }
    });
});

function populateAccounts(selectId, type) {
    var select = $('#' + selectId);
    select.html('<option value="">Select Account</option>');
    
    var accounts = type === 'cash' ? cashAccounts : bankAccounts;
    accounts.forEach(function(account) {
        // Filter by account_type if it's not cash
        if (type !== 'cash') {
            const accType = account.account_type || 'bank'; // Default to bank for old records
            if (accType !== type && (type !== 'bank' || accType !== 'bank')) {
                // Allow strict match. If type is 'bank', only show 'bank'.
                // If type is 'bkash', only show 'bkash'.
                if (accType !== type) return;
            }
        }
        
        var name = type === 'cash' ? account.account_name : account.bank_name + ' - ' + account.account_number;
        var balance = parseFloat(account.current_balance).toFixed(2);
        select.append('<option value="' + account.id + '">' + name + ' (Balance: <?= APP_CURRENCY_SYMBOL ?>' + balance + ')</option>');
    });
}
</script>
