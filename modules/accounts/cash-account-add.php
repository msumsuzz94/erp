<?php
/**
 * Add Cash Account Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle form submission
if (is_post() && isset($_POST['add_account'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $account_name = clean_input($_POST['account_name']);
        $account_number = clean_input($_POST['account_number']);
        $opening_balance = floatval($_POST['opening_balance']);
        $status = clean_input($_POST['status']);
        
        if (empty($account_name)) {
            set_message('Account name is required', 'error');
        } else {
            $data = [
                'account_name' => $account_name,
                'account_number' => $account_number,
                'opening_balance' => $opening_balance,
                'current_balance' => $opening_balance, // Initially current = opening
                'status' => $status
            ];
            
            $result = db_insert('cash_accounts', $data);
            
            if ($result) {
                // If opening balance > 0, record an initial transaction
                if ($opening_balance > 0) {
                    $transaction_data = [
                        'account_id' => $result,
                        'transaction_type' => 'credit',
                        'amount' => $opening_balance,
                        'description' => 'Opening Balance',
                        'transaction_date' => date('Y-m-d'),
                        'created_by' => get_current_user_id()
                    ];
                    db_insert('cash_transactions', $transaction_data);
                }
                
                log_activity(get_current_user_id(), 'add_cash_account', "Added cash account: $account_name");
                redirect_with_message('cash-accounts-list.php', 'Cash account added successfully', 'success');
            } else {
                set_message('Failed to add cash account', 'error');
            }
        }
    }
}

$page_title = 'Add Cash Account';
$page_actions = '<a href="cash-accounts-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">New Cash Account Details</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" name="account_name" class="form-control" required placeholder="e.g., Main Cash, Peti Cash">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="account_number" class="form-control" placeholder="Optional identifier">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Opening Balance (<?= APP_CURRENCY_SYMBOL ?>)</label>
                                <input type="number" name="opening_balance" class="form-control" step="0.01" min="0" value="0.00">
                                <small class="text-muted">Initial balance in this account.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <button type="submit" name="add_account" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Account
                        </button>
                        <a href="cash-accounts-list.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
