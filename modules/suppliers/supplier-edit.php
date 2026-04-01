<?php
/**
 * Supplier Edit Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$supplier_id = (int)get_param('id');
$supplier = db_select_one('suppliers', ['id' => $supplier_id]);

if (!$supplier) {
    redirect_with_message('suppliers-list.php', 'Supplier not found', 'error');
}

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        if (empty($_POST['name'])) $errors[] = 'Supplier name is required';
        
        if (empty($errors)) {
            $data = [
                'name' => clean_input($_POST['name']),
                'phone' => clean_input($_POST['phone']),
                'email' => clean_input($_POST['email']),
                'address' => clean_input($_POST['address']),
                'payment_terms' => clean_input($_POST['payment_terms']),
                'status' => $_POST['status'] ?? 'active',
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (db_update('suppliers', $data, ['id' => $supplier_id])) {
                log_activity(get_current_user_id(), 'edit_supplier', "Updated supplier: {$data['name']}");
                redirect_with_message('suppliers-list.php', 'Supplier updated successfully', 'success');
            } else {
                $errors[] = 'Failed to update supplier';
            }
        }
    }
}

$page_title = 'Edit Supplier';
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
        <h6 class="m-0 font-weight-bold text-primary">Supplier Information</h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($supplier['name']) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($supplier['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= $supplier['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $supplier['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-3">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($supplier['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Payment Terms</label>
                        <input type="text" name="payment_terms" class="form-control" value="<?= htmlspecialchars($supplier['payment_terms'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Current Balance (Read Only)</label>
                        <input type="text" class="form-control" value="<?= format_currency($supplier['current_balance']) ?>" readonly>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Supplier</button>
            <a href="suppliers-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
