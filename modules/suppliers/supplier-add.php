<?php
/**
 * Add Supplier Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

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
                'opening_balance' => (float)($_POST['opening_balance'] ?? 0),
                'current_balance' => (float)($_POST['opening_balance'] ?? 0),
                'payment_terms' => clean_input($_POST['payment_terms']),
                'status' => $_POST['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $supplier_id = db_insert('suppliers', $data);
            
            if ($supplier_id) {
                if ($data['opening_balance'] != 0) {
                    db_insert('supplier_ledger', [
                        'supplier_id' => $supplier_id,
                        'transaction_type' => 'opening_balance',
                        'debit' => $data['opening_balance'] < 0 ? abs($data['opening_balance']) : 0,
                        'credit' => $data['opening_balance'] > 0 ? $data['opening_balance'] : 0,
                        'balance' => $data['opening_balance'],
                        'description' => 'Opening Balance',
                        'date' => date('Y-m-d'),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                log_activity(get_current_user_id(), 'add_supplier', "Added supplier: {$data['name']}");
                redirect_with_message('suppliers-list.php', 'Supplier added successfully', 'success');
            } else {
                $errors[] = 'Failed to add supplier';
            }
        }
    }
}

$page_title = 'Add Supplier';
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
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
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
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Payment Terms</label>
                        <input type="text" name="payment_terms" class="form-control" placeholder="e.g., Net 30 days" value="<?= htmlspecialchars($_POST['payment_terms'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Opening Balance</label>
                        <input type="number" name="opening_balance" class="form-control" step="0.01" value="<?= htmlspecialchars($_POST['opening_balance'] ?? '0') ?>">
                        <small class="text-muted">Positive for payable, negative for receivable</small>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Supplier</button>
                    <a href="suppliers-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
