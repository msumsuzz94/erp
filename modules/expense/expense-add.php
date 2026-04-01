<?php
/**
 * Add Expense Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        if (empty($_POST['category_id'])) $errors[] = 'Category is required';
        if (empty($_POST['amount']) || $_POST['amount'] <= 0) $errors[] = 'Amount must be greater than 0';
        
        if (empty($errors)) {
            $data = [
                'category_id' => (int)$_POST['category_id'],
                'account_id' => (int)$_POST['account_id'],
                'account_type' => clean_input($_POST['account_type']), // 'cash' or 'bank'
                'amount' => (float)$_POST['amount'],
                'description' => clean_input($_POST['description']),
                'expense_date' => $_POST['date'] ?? date('Y-m-d'),
                'created_by' => get_current_user_id(),
                'created_at' => date('Y-m-d H:i:s'),
                'payment_method' => clean_input($_POST['payment_method_type']), // Keep for compatibility
                'status' => 'pending'
            ];
            
            $expense_id = db_insert('expenses', $data);
            
            if ($expense_id) {
                log_activity(get_current_user_id(), 'add_expense', "Added pending expense: {$data['description']}");
                redirect_with_message('expenses-list.php', 'Expense added and pending for approval', 'success');
            } else {
                $errors[] = 'Failed to add expense';
            }
        }
    }
}

$categories = db_select('expense_categories', [], '*', 'name ASC');
$cash_accounts = db_select('cash_accounts', ['status' => 'active'], '*', 'account_name ASC');
$bank_accounts = db_select('bank_accounts', [], '*', 'bank_name ASC');

$page_title = 'Add Expense';
include __DIR__ . '/../../templates/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Expense Information</h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method_type" id="payment_method_type" class="form-control" required onchange="loadAccounts()">
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                            <option value="card">Card</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label>Account <span class="text-danger">*</span></label>
                        <select name="account_id" id="account_id" class="form-control" required>
                            <option value="">Select payment method first</option>
                        </select>
                        <input type="hidden" name="account_type" id="account_type" value="">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Expense</button>
            <a href="expenses-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        </form>
    </div>
</div>

<script>
// Account data from PHP
const cashAccounts = <?= json_encode($cash_accounts) ?>;
const bankAccounts = <?= json_encode($bank_accounts) ?>;

function loadAccounts() {
    const paymentMethodType = document.getElementById('payment_method_type').value;
    const accountSelect = document.getElementById('account_id');
    const accountTypeInput = document.getElementById('account_type');
    
    // Clear existing options
    accountSelect.innerHTML = '<option value="">Select Account</option>';
    
    if (!paymentMethodType) {
        accountSelect.innerHTML = '<option value="">Select payment method first</option>';
        accountTypeInput.value = '';
        return;
    }
    
    let accounts = [];
    let accountType = '';
    
    // Determine which accounts to load based on payment method
    if (paymentMethodType === 'cash') {
        accounts = cashAccounts;
        accountType = 'cash';
    } else {
        // For bank, card, bkash, nagad, rocket, mobile_money - filter bank accounts by type
        accounts = bankAccounts.filter(acc => {
            if (!acc.account_type) return paymentMethodType === 'bank';
            return acc.account_type === paymentMethodType;
        });
        accountType = 'bank';
    }
    
    // Populate account dropdown
    if (accounts.length === 0) {
        accountSelect.innerHTML = '<option value="">No accounts found for this payment method</option>';
        accountTypeInput.value = '';
    } else {
        accounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.id;
            
            if (accountType === 'cash') {
                option.textContent = `${account.account_name} (${formatCurrency(account.current_balance)})`;
            } else {
                option.textContent = `${account.bank_name} - ${account.account_number} (${formatCurrency(account.current_balance)})`;
            }
            
            accountSelect.appendChild(option);
        });
        accountTypeInput.value = accountType;
    }
}

function formatCurrency(amount) {
    return '৳' + parseFloat(amount).toLocaleString('en-BD', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
