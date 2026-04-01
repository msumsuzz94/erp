<?php
/**
 * Customer Edit Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$customer_id = (int) get_param('id');
$customer = db_select_one('customers', ['id' => $customer_id]);

if (!$customer) {
    redirect_with_message('customers-list.php', 'Customer not found', 'error');
}

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
                'status' => $_POST['status'] ?? 'active',
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if (db_update('customers', $data, ['id' => $customer_id])) {
                log_activity(get_current_user_id(), 'edit_customer', "Updated customer: {$data['name']}");
                redirect_with_message('customers-list.php', 'Customer updated successfully', 'success');
            } else {
                $errors[] = 'Failed to update customer';
            }
        }
    }
}

$page_title = 'Edit Customer';
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
                            value="<?= htmlspecialchars($customer['name']) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control"
                            value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control"
                            value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Group</label>
                        <select name="customer_group" class="form-control">
                            <option value="Buyer" <?= ($customer['customer_group'] ?? 'Buyer') == 'Buyer' ? 'selected' : '' ?>>Buyer</option>
                            <option value="Vendor" <?= ($customer['customer_group'] ?? '') == 'Vendor' ? 'selected' : '' ?>>Vendor</option>
                            <option value="Corporate" <?= ($customer['customer_group'] ?? '') == 'Corporate' ? 'selected' : '' ?>>Corporate</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= $customer['status'] === 'active' ? 'selected' : '' ?>>Active
                            </option>
                            <option value="inactive" <?= $customer['status'] === 'inactive' ? 'selected' : '' ?>>Inactive
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label>Address</label>
                        <textarea name="address" class="form-control"
                            rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Credit Limit</label>
                        <input type="number" name="credit_limit" class="form-control" step="0.01"
                            value="<?= $customer['credit_limit'] ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Current Balance (Read Only)</label>
                        <input type="text" class="form-control"
                            value="<?= format_currency($customer['current_balance']) ?>" readonly>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Customer</button>
                    <a href="customers-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
