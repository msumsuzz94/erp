<?php
/**
 * Mobile Banking Account Management
 * Manages bKash, Nagad, Rocket, and other mobile money accounts
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle Add/Edit Mobile Account
if (is_post() && isset($_POST['save_account'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $data = [
            'bank_name' => clean_input($_POST['provider_name']),
            'account_number' => clean_input($_POST['account_number']),
            'branch' => clean_input($_POST['branch'] ?? ''),
            'account_type' => clean_input($_POST['account_type']),
            'opening_balance' => (float)$_POST['opening_balance']
        ];

        if (!empty($_POST['id'])) {
            // Edit existing
            db_update('bank_accounts', $data, ['id' => (int)$_POST['id']]);
            log_activity(get_current_user_id(), 'mobile_account_update', "Updated Mobile Account: {$data['bank_name']}");
            redirect_with_message('mobile-banking.php', 'Mobile account updated successfully', 'success');
        } else {
            // Add new
            $data['current_balance'] = $data['opening_balance'];
            db_insert('bank_accounts', $data);
            log_activity(get_current_user_id(), 'mobile_account_add', "Added Mobile Account: {$data['bank_name']}");
            redirect_with_message('mobile-banking.php', 'Mobile account added successfully', 'success');
        }
    }
}

// Handle Delete
if (is_post() && isset($_POST['delete_account'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $id = (int)$_POST['id'];
        $account = db_select_one('bank_accounts', ['id' => $id]);
        
        if ($account) {
            db_delete('bank_accounts', ['id' => $id]);
            log_activity(get_current_user_id(), 'mobile_account_delete', "Deleted Mobile Account: {$account['bank_name']}");
            redirect_with_message('mobile-banking.php', 'Mobile account deleted successfully', 'success');
        }
    }
}

// Fetch all mobile banking accounts
$mobile_accounts = db_query("SELECT * FROM bank_accounts WHERE account_type IN ('bkash', 'nagad', 'rocket', 'mobile_money') ORDER BY created_at DESC");

$page_title = 'Mobile Banking Accounts';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-mobile-alt"></i> Mobile Banking Accounts</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="fas fa-plus"></i> Add Mobile Account
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover" id="mobileAccountsTable">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Account Type</th>
                        <th>Account Number</th>
                        <th>Opening Balance</th>
                        <th>Current Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mobile_accounts as $acc): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($acc['bank_name']) ?></strong></td>
                            <td>
                                <span class="badge bg-info">
                                    <?= strtoupper($acc['account_type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($acc['account_number']) ?></td>
                            <td><?= format_currency($acc['opening_balance']) ?></td>
                            <td><strong><?= format_currency($acc['current_balance']) ?></strong></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick='editAccount(<?= json_encode($acc) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteAccount(<?= $acc['id'] ?>, '<?= htmlspecialchars($acc['bank_name']) ?>')">
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

<!-- Add Mobile Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add Mobile Banking Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Account Type <span class="text-danger">*</span></label>
                        <select name="account_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label>Provider/Name <span class="text-danger">*</span></label>
                        <input type="text" name="provider_name" class="form-control" placeholder="e.g., Personal bKash" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Account Number <span class="text-danger">*</span></label>
                        <input type="text" name="account_number" class="form-control" placeholder="e.g., 01712345678" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Branch/Agent (Optional)</label>
                        <input type="text" name="branch" class="form-control" placeholder="e.g., Agent Name">
                    </div>
                    <div class="form-group mb-3">
                        <label>Opening Balance <span class="text-danger">*</span></label>
                        <input type="number" name="opening_balance" class="form-control" value="0" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="save_account" class="btn btn-primary">Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Mobile Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Mobile Banking Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Account Type <span class="text-danger">*</span></label>
                        <select name="account_type" id="edit_account_type" class="form-control" required>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label>Provider/Name <span class="text-danger">*</span></label>
                        <input type="text" name="provider_name" id="edit_provider_name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Account Number <span class="text-danger">*</span></label>
                        <input type="text" name="account_number" id="edit_account_number" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Branch/Agent</label>
                        <input type="text" name="branch" id="edit_branch" class="form-control">
                    </div>
                    <div class="form-group mb-3">
                        <label>Opening Balance <span class="text-danger">*</span></label>
                        <input type="number" name="opening_balance" id="edit_opening_balance" class="form-control" step="0.01" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="save_account" class="btn btn-primary">Update Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="id" id="delete_id">
    <input type="hidden" name="delete_account" value="1">
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#mobileAccountsTable').DataTable({
        order: [[0, 'asc']]
    });
});

function editAccount(account) {
    $("#edit_id").val(account.id);
    $("#edit_account_type").val(account.account_type);
    $("#edit_provider_name").val(account.bank_name);
    $("#edit_account_number").val(account.account_number);
    $("#edit_branch").val(account.branch);
    $("#edit_opening_balance").val(account.opening_balance);
    
    $('#editAccountModal').modal('show');
}

function deleteAccount(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"?`)) {
        $("#delete_id").val(id);
        $("#deleteForm").submit();
    }
}
</script>
