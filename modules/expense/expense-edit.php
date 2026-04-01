<?php
/**
 * Edit Expense Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$id = get_param('id', 0);
if (!$id) {
    redirect_with_message('expenses-list.php', 'Invalid expense ID', 'error');
}

$expense = db_select_one('expenses', ['id' => $id]);
if (!$expense) {
    redirect_with_message('expenses-list.php', 'Expense not found', 'error');
}

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        if (empty($_POST['category_id'])) $errors[] = 'Category is required';
        if (empty($_POST['amount']) || $_POST['amount'] <= 0) $errors[] = 'Amount must be greater than 0';
        if (empty($_POST['account_id'])) $errors[] = 'Account is required';
        
        if (empty($errors)) {
            $new_amount = (float)$_POST['amount'];
            $new_account_id = (int)$_POST['account_id'];
            $old_amount = (float)$expense['amount'];
            $old_account_id = (int)$expense['account_id'];
            
            $data = [
                'category_id' => (int)$_POST['category_id'],
                'account_id' => $new_account_id,
                'amount' => $new_amount,
                'description' => clean_input($_POST['description']),
                'expense_date' => $_POST['date'] ?? date('Y-m-d'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            db_begin_transaction();
            
            if (db_update('expenses', $data, ['id' => $id])) {
                // Adjust cash balances if amount or account changed
                if ($old_account_id == $new_account_id) {
                    // Same account, different amount
                    if ($old_amount != $new_amount) {
                        $diff = $new_amount - $old_amount;
                        $account = db_select_one('cash_accounts', ['id' => $new_account_id]);
                        db_update('cash_accounts', ['current_balance' => $account['current_balance'] - $diff], ['id' => $new_account_id]);
                        
                        // Update existing transaction record if it exists
                        $tx = db_select_one('cash_transactions', ['reference_type' => 'expense', 'reference_id' => $id]);
                        if ($tx) {
                            db_update('cash_transactions', [
                                'amount' => $new_amount,
                                'transaction_date' => $data['expense_date'],
                                'description' => "Expense (Updated): " . $data['description']
                            ], ['id' => $tx['id']]);
                        }
                    }
                } else {
                    // Account changed: Revert old, Apply new
                    // 1. Revert old account
                    $old_acc = db_select_one('cash_accounts', ['id' => $old_account_id]);
                    if ($old_acc) {
                        db_update('cash_accounts', ['current_balance' => $old_acc['current_balance'] + $old_amount], ['id' => $old_account_id]);
                    }
                    
                    // 2. Apply to new account
                    $new_acc = db_select_one('cash_accounts', ['id' => $new_account_id]);
                    if ($new_acc) {
                        db_update('cash_accounts', ['current_balance' => $new_acc['current_balance'] - $new_amount], ['id' => $new_account_id]);
                    }
                    
                    // 3. Update or recreate transaction record
                    $tx = db_select_one('cash_transactions', ['reference_type' => 'expense', 'reference_id' => $id]);
                    if ($tx) {
                        db_update('cash_transactions', [
                            'account_id' => $new_account_id,
                            'amount' => $new_amount,
                            'transaction_date' => $data['expense_date'],
                            'description' => "Expense (Updated): " . $data['description']
                        ], ['id' => $tx['id']]);
                    }
                }
                
                db_commit();
                log_activity(get_current_user_id(), 'update_expense', "Updated expense ID: $id");
                redirect_with_message('expenses-list.php', 'Expense updated successfully', 'success');
            } else {
                db_rollback();
                $errors[] = 'Failed to update expense';
            }
        }
    }
}

$categories = db_select('expense_categories', [], '*', 'name ASC');
$accounts = db_select('cash_accounts', ['status' => 'active'], '*', 'account_name ASC');

$page_title = 'Edit Expense';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Edit Expense Details</h6>
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
                                <option value="<?= $cat['id'] ?>" <?= $expense['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Cash Account <span class="text-danger">*</span></label>
                        <select name="account_id" class="form-control" required>
                            <option value="">Select Account</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= $account['id'] ?>" <?= $expense['account_id'] == $account['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($account['account_name']) ?> (<?= format_currency($account['current_balance']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" value="<?= $expense['amount'] ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= $expense['expense_date'] ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($expense['description']) ?></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Expense</button>
            <a href="expenses-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
