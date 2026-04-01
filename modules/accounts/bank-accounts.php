<?php
/**
 * Bank Accounts Management Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle create/update
if (is_post() && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $data = [
            'bank_name' => clean_input($_POST['bank_name']),
            'account_type' => clean_input($_POST['account_type']),
            'account_number' => clean_input($_POST['account_number']),
            'branch' => clean_input($_POST['branch']),
            'opening_balance' => (float)$_POST['opening_balance'],
            'current_balance' => (float)$_POST['opening_balance']
        ];
        
        if ($_POST['action'] === 'create') {
            if (db_insert('bank_accounts', $data)) {
                log_activity(get_current_user_id(), 'create_bank_account', "Created bank account: {$data['bank_name']}");
                redirect_with_message($_SERVER['PHP_SELF'], 'Bank account created successfully', 'success');
            }
        } elseif ($_POST['action'] === 'update') {
            $id = (int)$_POST['id'];
            unset($data['current_balance']); // Don't update current balance on edit
            if (db_update('bank_accounts', $data, ['id' => $id])) {
                log_activity(get_current_user_id(), 'update_bank_account', "Updated bank account: {$data['bank_name']}");
                redirect_with_message($_SERVER['PHP_SELF'], 'Bank account updated successfully', 'success');
            }
        }
    }
}

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $id = (int)$_POST['delete_id'];
        if (db_delete('bank_accounts', ['id' => $id])) {
            log_activity(get_current_user_id(), 'delete_bank_account', "Deleted bank account ID: $id");
            redirect_with_message($_SERVER['PHP_SELF'], 'Bank account deleted successfully', 'success');
        }
    }
}

$accounts = db_select('bank_accounts', [], '*', 'bank_name ASC');

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

$page_title = 'Bank Accounts';
$page_actions = '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fas fa-plus"></i> Add Bank Account</button>';

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
    .no-print, .btn, .card, .navbar, .sidebar, #sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, .summary-cards, #standard-print-wrapper, #standard-print-footer, form, .modal {
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

include __DIR__ . '/../../templates/header.php';
?>


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

        <div class="report-main-title">Bank Accounts Report (Date: <?= date('d M Y') ?>)</div>

        <!-- Data Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Bank Name</th>
                    <th>Account Number</th>
                    <th>Branch</th>
                    <th class="text-end">Opening Balance</th>
                    <th class="text-end">Current Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php $total_balance = 0; ?>
                <?php foreach ($accounts as $account): ?>
                    <?php $total_balance += $account['current_balance']; ?>
                    <tr>
                        <td><?= ucfirst(htmlspecialchars($account['account_type'] ?? 'bank')) ?></td>
                        <td><?= htmlspecialchars($account['bank_name']) ?></td>
                        <td><?= htmlspecialchars($account['account_number']) ?></td>
                        <td><?= htmlspecialchars($account['branch'] ?? '-') ?></td>
                        <td class="text-end"><?= format_currency($account['opening_balance']) ?></td>
                        <td class="text-end"><?= format_currency($account['current_balance']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f2f2f2;">
                    <th colspan="5" class="text-end">TOTAL AVAILABLE BALANCE</th>
                    <th class="text-end"><?= format_currency($total_balance) ?></th>
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

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Bank Accounts List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="accountsTable">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Account Number</th>
                        <th>Branch/Details</th>
                        <th>Opening Balance</th>
                        <th>Current Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= ucfirst(htmlspecialchars($account['account_type'] ?? 'bank')) ?></span></td>
                            <td><?= htmlspecialchars($account['bank_name']) ?></td>
                            <td><?= htmlspecialchars($account['account_number']) ?></td>
                            <td><?= htmlspecialchars($account['branch'] ?? '-') ?></td>
                            <td><?= format_currency($account['opening_balance']) ?></td>
                            <td class="<?= $account['current_balance'] < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= format_currency($account['current_balance']) ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning" 
                                        onclick='editAccount(<?= json_encode($account) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteAccount(<?= $account['id'] ?>, '<?= htmlspecialchars($account['bank_name']) ?>')">
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

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Bank Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Account Type <span class="text-danger">*</span></label>
                        <select name="account_type" class="form-control" required>
                            <option value="bank">Bank</option>
                            <option value="card">Card</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label>Bank/Provider Name <span class="text-danger">*</span></label>
                        <input type="text" name="bank_name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Account Number <span class="text-danger">*</span></label>
                        <input type="text" name="account_number" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Branch</label>
                        <input type="text" name="branch" class="form-control">
                    </div>
                    <div class="form-group mb-3">
                        <label>Opening Balance</label>
                        <input type="number" name="opening_balance" class="form-control" step="0.01" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Bank Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Account Type <span class="text-danger">*</span></label>
                        <select name="account_type" id="edit_account_type" class="form-control" required>
                            <option value="bank">Bank</option>
                            <option value="card">Card</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label>Bank/Provider Name <span class="text-danger">*</span></label>
                        <input type="text" name="bank_name" id="edit_bank_name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Account Number <span class="text-danger">*</span></label>
                        <input type="text" name="account_number" id="edit_account_number" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Branch</label>
                        <input type="text" name="branch" id="edit_branch" class="form-control">
                    </div>
                    <div class="form-group mb-3">
                        <label>Opening Balance</label>
                        <input type="number" name="opening_balance" id="edit_opening_balance" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
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
$(document).ready(function() {
    $('#accountsTable').DataTable();
});

function editAccount(account) {
    $('#edit_id').val(account.id);
    $('#edit_account_type').val(account.account_type || 'bank');
    $('#edit_bank_name').val(account.bank_name);
    $('#edit_account_number').val(account.account_number);
    $('#edit_branch').val(account.branch);
    $('#edit_opening_balance').val(account.opening_balance);
    $('#editModal').modal('show');
}

function deleteAccount(id, name) {
    if (confirm('Are you sure you want to delete bank account "' + name + '"?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
