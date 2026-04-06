<?php

/**
 * Warehouses List Page
 * Manage warehouse locations
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Handle Add/Edit warehouse
if (is_post() && isset($_POST['save_warehouse'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $data = [
            'name' => trim($_POST['name']),
            'code' => trim($_POST['code']),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'status' => $_POST['status'] ?? 'active'
        ];

        if (empty($data['name']) || empty($data['code'])) {
            redirect_with_message($_SERVER['PHP_SELF'], 'Name and Code are required', 'error');
        }

        $edit_id = (int)($_POST['edit_id'] ?? 0);
        if ($edit_id > 0) {
            // Update
            if (db_update('warehouses', $data, ['id' => $edit_id])) {
                redirect_with_message($_SERVER['PHP_SELF'], 'Warehouse updated successfully', 'success');
            } else {
                redirect_with_message($_SERVER['PHP_SELF'], 'Failed to update warehouse', 'error');
            }
        } else {
            // Insert
            if (db_insert('warehouses', $data)) {
                redirect_with_message($_SERVER['PHP_SELF'], 'Warehouse added successfully', 'success');
            } else {
                redirect_with_message($_SERVER['PHP_SELF'], 'Failed to add warehouse. Code may already exist.', 'error');
            }
        }
    }
}

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $warehouse_id = (int)$_POST['delete_id'];
        if (db_delete('warehouses', ['id' => $warehouse_id])) {
            redirect_with_message($_SERVER['PHP_SELF'], 'Warehouse deleted successfully', 'success');
        } else {
            redirect_with_message($_SERVER['PHP_SELF'], 'Failed to delete warehouse', 'error');
        }
    }
}

$warehouses = db_select('warehouses', [], '*', 'name ASC');

$page_title = 'Warehouses';
$page_actions = '
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#warehouseModal" onclick="resetForm()">
        <i class="fas fa-plus"></i> Add Warehouse
    </button>';

$additional_css = '
<style>
@media print {
    .btn, .sidebar, #sidebar, .topbar, .navbar, .card-header, .no-print, form { display: none !important; }
    .container-fluid { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
    #content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table-responsive { overflow: visible !important; }
    .table { width: 100% !important; border-collapse: collapse !important; }
    .table th, .table td { border: 1px solid #ddd !important; padding: 8px !important; }
    body { padding-top: 0 !important; background: white !important; }
    .text-gray-800 { color: black !important; }
    .card-body { padding: 0 !important; }
}
</style>
';

include __DIR__ . '/../../templates/header.php';

// Include centralized print header
include __DIR__ . '/../../templates/print-header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-warehouse"></i> Warehouses List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="warehousesTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($warehouses as $wh): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($wh['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($wh['code']) ?></code></td>
                            <td><?= htmlspecialchars($wh['phone'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($wh['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($wh['address'] ?? '-') ?></td>
                            <td>
                                <?php if ($wh['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning edit-warehouse"
                                    data-id="<?= $wh['id'] ?>"
                                    data-name="<?= htmlspecialchars($wh['name']) ?>"
                                    data-code="<?= htmlspecialchars($wh['code']) ?>"
                                    data-phone="<?= htmlspecialchars($wh['phone'] ?? '') ?>"
                                    data-email="<?= htmlspecialchars($wh['email'] ?? '') ?>"
                                    data-address="<?= htmlspecialchars($wh['address'] ?? '') ?>"
                                    data-status="<?= $wh['status'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger delete-warehouse"
                                    data-id="<?= $wh['id'] ?>"
                                    data-name="<?= htmlspecialchars($wh['name']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($warehouses)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-warehouse fa-2x mb-2 d-block"></i>
                                No warehouses found. Click "Add Warehouse" to create one.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Warehouse Modal -->
<div class="modal fade" id="warehouseModal" tabindex="-1" aria-labelledby="warehouseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="warehouseModalLabel">Add Warehouse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="edit_id" id="edit_id" value="0">
                    
                    <div class="mb-3">
                        <label for="wh_name" class="form-label">Warehouse Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="wh_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="wh_code" class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="wh_code" class="form-control" required maxlength="20">
                        <small class="text-muted">Unique short code, e.g. WH-01</small>
                    </div>
                    <div class="mb-3">
                        <label for="wh_phone" class="form-label">Phone</label>
                        <input type="text" name="phone" id="wh_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="wh_email" class="form-label">Email</label>
                        <input type="email" name="email" id="wh_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="wh_address" class="form-label">Address</label>
                        <textarea name="address" id="wh_address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="wh_status" class="form-label">Status</label>
                        <select name="status" id="wh_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_warehouse" class="btn btn-primary">Save Warehouse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<?php
ob_start();
?>
<script>
    function resetForm() {
        document.getElementById('warehouseModalLabel').textContent = 'Add Warehouse';
        document.getElementById('edit_id').value = '0';
        document.getElementById('wh_name').value = '';
        document.getElementById('wh_code').value = '';
        document.getElementById('wh_phone').value = '';
        document.getElementById('wh_email').value = '';
        document.getElementById('wh_address').value = '';
        document.getElementById('wh_status').value = 'active';
    }

    $(document).ready(function() {
        // Initialize DataTable
        if ($.fn.DataTable && $('#warehousesTable tbody tr').length > 1) {
            $('#warehousesTable').DataTable({
                "pageLength": 25,
                "order": [[1, "asc"]]
            });
        }

        // Edit warehouse
        $(document).on('click', '.edit-warehouse', function() {
            document.getElementById('warehouseModalLabel').textContent = 'Edit Warehouse';
            document.getElementById('edit_id').value = $(this).data('id');
            document.getElementById('wh_name').value = $(this).data('name');
            document.getElementById('wh_code').value = $(this).data('code');
            document.getElementById('wh_phone').value = $(this).data('phone');
            document.getElementById('wh_email').value = $(this).data('email');
            document.getElementById('wh_address').value = $(this).data('address');
            document.getElementById('wh_status').value = $(this).data('status');
            new bootstrap.Modal(document.getElementById('warehouseModal')).show();
        });

        // Delete warehouse
        $(document).on('click', '.delete-warehouse', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            if (confirm('Are you sure you want to delete warehouse "' + name + '"?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        });
    });
</script>
<?php
$additional_js = ob_get_clean();
include __DIR__ . '/../../templates/footer.php';
?>
