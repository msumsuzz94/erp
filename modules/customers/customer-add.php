<?php
/**
 * Add Customer Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];

        if (empty($_POST['name']))
            $errors[] = 'Customer name is required';

        if (empty($errors)) {
            $data = [
                'name' => clean_input($_POST['name']),
                'phone' => clean_input($_POST['phone']),
                'email' => clean_input($_POST['email']),
                'address' => clean_input($_POST['address']),
                'customer_group' => clean_input($_POST['customer_group'] ?? 'Buyer'),
                'credit_limit' => (float) ($_POST['credit_limit'] ?? 0),
                'opening_balance' => (float) ($_POST['opening_balance'] ?? 0),
                'current_balance' => (float) ($_POST['opening_balance'] ?? 0),
                'status' => $_POST['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $customer_id = db_insert('customers', $data);

            if ($customer_id) {
                // If opening balance exists, create ledger entry
                if ($data['opening_balance'] != 0) {
                    db_insert('customer_ledger', [
                        'customer_id' => $customer_id,
                        'transaction_type' => 'opening_balance',
                        'debit' => $data['opening_balance'] > 0 ? $data['opening_balance'] : 0,
                        'credit' => $data['opening_balance'] < 0 ? abs($data['opening_balance']) : 0,
                        'balance' => $data['opening_balance'],
                        'description' => 'Opening Balance',
                        'date' => date('Y-m-d'),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }

                log_activity(get_current_user_id(), 'add_customer', "Added customer: {$data['name']}");
                redirect_with_message('customers-list.php', 'Customer added successfully', 'success');
            } else {
                $errors[] = 'Failed to add customer';
            }
        }
    }
}

$page_title = 'Add Customer';
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
        <h6 class="m-0 font-weight-bold text-primary">Customer Information</h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Customer Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control"
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Group</label>
                        <select name="customer_group" class="form-control">
                            <option value="Buyer" <?= ($_POST['customer_group'] ?? 'Buyer') == 'Buyer' ? 'selected' : '' ?>>Buyer</option>
                            <option value="Vendor" <?= ($_POST['customer_group'] ?? '') == 'Vendor' ? 'selected' : '' ?>>
                                Vendor</option>
                            <option value="Corporate" <?= ($_POST['customer_group'] ?? '') == 'Corporate' ? 'selected' : '' ?>>Corporate</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label>Address</label>
                        <textarea name="address" class="form-control"
                            rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Credit Limit</label>
                        <input type="number" name="credit_limit" class="form-control" step="0.01"
                            value="<?= htmlspecialchars($_POST['credit_limit'] ?? '0') ?>">
                        <small class="text-muted">Maximum credit allowed</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Opening Balance</label>
                        <input type="number" name="opening_balance" class="form-control" step="0.01"
                            value="<?= htmlspecialchars($_POST['opening_balance'] ?? '0') ?>">
                        <small class="text-muted">Positive for receivable, negative for payable</small>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Customer</button>
                    <a href="customers-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
