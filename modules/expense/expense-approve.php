<?php
/**
 * Expense Approval Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle approval/rejection
if (is_post() && isset($_POST['expense_id']) && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $expense_id = (int)$_POST['expense_id'];
        $expense = db_select_one('expenses', ['id' => $expense_id]);
        
        if ($expense && $expense['status'] === 'pending') {
            if ($_POST['action'] === 'approve') {
                db_begin_transaction();
                
                // 1. Update status
                $update_data = [
                    'status' => 'approved',
                    'approved_at' => date('Y-m-d H:i:s'),
                    'approved_by' => get_current_user_id()
                ];
                
                if (db_update('expenses', $update_data, ['id' => $expense_id])) {
                    // 2. Deduct from correct account based on account_type
                    $account_type = $expense['account_type'] ?? 'cash'; // Default to cash for old records
                    
                    if ($account_type == 'cash') {
                        // Handle cash account
                        $account = db_select_one('cash_accounts', ['id' => $expense['account_id']]);
                        if ($account) {
                            $new_balance = $account['current_balance'] - $expense['amount'];
                            db_update('cash_accounts', ['current_balance' => $new_balance], ['id' => $expense['account_id']]);
                            
                            // 3. Record transaction in cash_transactions
                            $transaction_data = [
                                'account_id' => $expense['account_id'],
                                'transaction_type' => 'debit',
                                'amount' => $expense['amount'],
                                'reference_type' => 'expense',
                                'reference_id' => $expense_id,
                                'description' => "Expense Approved: " . $expense['description'],
                                'transaction_date' => $expense['expense_date'],
                                'created_by' => get_current_user_id()
                            ];
                            db_insert('cash_transactions', $transaction_data);
                            
                            db_commit();
                            log_activity(get_current_user_id(), 'approve_expense', "Approved cash expense ID: $expense_id");
                            redirect_with_message($_SERVER['PHP_SELF'], 'Expense approved and cash balance updated', 'success');
                        } else {
                            db_rollback();
                            redirect_with_message($_SERVER['PHP_SELF'], 'Cash account not found', 'error');
                        }
                    } else {
                        // Handle bank account
                        $account = db_select_one('bank_accounts', ['id' => $expense['account_id']]);
                        if ($account) {
                            $new_balance = $account['current_balance'] - $expense['amount'];
                            db_update('bank_accounts', ['current_balance' => $new_balance], ['id' => $expense['account_id']]);
                            
                            // 3. Record transaction in bank_transactions
                            $transaction_data = [
                                'account_id' => $expense['account_id'],
                                'transaction_type' => 'debit',
                                'amount' => $expense['amount'],
                                'reference_type' => 'expense',
                                'reference_id' => $expense_id,
                                'description' => "Expense Approved: " . $expense['description'],
                                'transaction_date' => $expense['expense_date'],
                                'created_by' => get_current_user_id()
                            ];
                            db_insert('bank_transactions', $transaction_data);
                            
                            db_commit();
                            log_activity(get_current_user_id(), 'approve_expense', "Approved bank expense ID: $expense_id");
                            redirect_with_message($_SERVER['PHP_SELF'], 'Expense approved and bank balance updated', 'success');
                        } else {
                            db_rollback();
                            redirect_with_message($_SERVER['PHP_SELF'], 'Bank account not found', 'error');
                        }
                    }
                } else {
                    db_rollback();
                    redirect_with_message($_SERVER['PHP_SELF'], 'Failed to update status', 'error');
                }
            } elseif ($_POST['action'] === 'reject') {
                if (db_update('expenses', ['status' => 'rejected'], ['id' => $expense_id])) {
                    log_activity(get_current_user_id(), 'reject_expense', "Rejected expense ID: $expense_id");
                    redirect_with_message($_SERVER['PHP_SELF'], 'Expense rejected', 'info');
                }
            }
        }
    }
}

$sql = "SELECT e.*, ec.name as category_name, ca.account_name as account_name, u.username as created_by_name
        FROM expenses e
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN cash_accounts ca ON e.account_id = ca.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.status = 'pending'
        ORDER BY e.created_at DESC";

$pending_expenses = db_query($sql);

$page_title = 'Expense Approval';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Pending Expenses</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="approvalTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Created By</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Account</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_expenses as $ex): ?>
                        <tr>
                            <td><?= format_date($ex['expense_date']) ?></td>
                            <td><?= htmlspecialchars($ex['created_by_name']) ?></td>
                            <td><?= htmlspecialchars($ex['category_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($ex['description']) ?></td>
                            <td><?= htmlspecialchars($ex['account_name'] ?? '-') ?></td>
                            <td><?= format_currency($ex['amount']) ?></td>
                            <td>
                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Approve this expense? This will deduct the balance.')">
                                    <input type="hidden" name="expense_id" value="<?= $ex['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Reject this expense?')">
                                    <input type="hidden" name="expense_id" value="<?= $ex['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#approvalTable').DataTable({
        "order": [[0, "desc"]]
    });
});
</script>
