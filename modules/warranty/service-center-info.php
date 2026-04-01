<?php
/**
 * Service Centers Management Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle create/update
if (is_post() && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $data = [
            'name' => clean_input($_POST['name']),
            'address' => clean_input($_POST['address']),
            'phone' => clean_input($_POST['phone']),
            'email' => clean_input($_POST['email'])
        ];
        
        if ($_POST['action'] === 'create') {
            if (db_insert('service_centers', $data)) {
                log_activity(get_current_user_id(), 'create_service_center', "Created service center: {$data['name']}");
                redirect_with_message($_SERVER['PHP_SELF'], 'Service center created successfully', 'success');
            }
        } elseif ($_POST['action'] === 'update') {
            $id = (int)$_POST['id'];
            if (db_update('service_centers', $data, ['id' => $id])) {
                log_activity(get_current_user_id(), 'update_service_center', "Updated service center: {$data['name']}");
                redirect_with_message($_SERVER['PHP_SELF'], 'Service center updated successfully', 'success');
            }
        }
    }
}

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $id = (int)$_POST['delete_id'];
        if (db_delete('service_centers', ['id' => $id])) {
            log_activity(get_current_user_id(), 'delete_service_center', "Deleted service center ID: $id");
            redirect_with_message($_SERVER['PHP_SELF'], 'Service center deleted successfully', 'success');
        }
    }
}

$service_centers = db_select('service_centers', [], '*', 'name ASC');

$page_title = 'Service Centers';
$page_actions = '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fas fa-plus"></i> Add Service Center</button>';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    /* Dark Mode for Service Centers */
    [data-theme="dark"] .modal-content {
        background-color: #1e293b;
        border-color: #334155;
    }
    [data-theme="dark"] .modal-header {
        border-bottom-color: #334155;
    }
    [data-theme="dark"] .modal-header .modal-title {
        color: #e2e8f0;
    }
    [data-theme="dark"] .modal-body label {
        color: #e2e8f0 !important;
    }
    [data-theme="dark"] .modal-body .form-control {
        background-color: #0f172a;
        border-color: #475569;
        color: #e2e8f0;
    }
    [data-theme="dark"] .modal-body .form-control::placeholder {
        color: #64748b;
    }
    [data-theme="dark"] .modal-footer {
        border-top-color: #334155;
    }
    [data-theme="dark"] .card {
        background-color: #0f172a;
        border-color: #334155;
    }
    [data-theme="dark"] .card-header {
        background-color: #1e293b;
        border-bottom-color: #334155;
    }
    [data-theme="dark"] .table th,
    [data-theme="dark"] .table td {
        border-color: #334155;
        color: #e2e8f0;
    }
    [data-theme="dark"] .table-hover tbody tr:hover td {
        background-color: #1e293b;
    }
</style>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Service Centers List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="centersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($service_centers)): ?>
                        <?php foreach ($service_centers as $center): ?>
                            <tr>
                                <td><?= htmlspecialchars($center['name']) ?></td>
                                <td><?= htmlspecialchars($center['address'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($center['phone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($center['email'] ?? '-') ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            onclick='editCenter(<?= json_encode($center) ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteCenter(<?= $center['id'] ?>, '<?= htmlspecialchars($center['name']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Service Center</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="form-group mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Service Center</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Address</label>
                        <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="form-group mb-3">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#centersTable').DataTable();
});

function editCenter(center) {
    $('#edit_id').val(center.id);
    $('#edit_name').val(center.name);
    $('#edit_address').val(center.address);
    $('#edit_phone').val(center.phone);
    $('#edit_email').val(center.email);
    $('#editModal').modal('show');
}

function deleteCenter(id, name) {
    if (confirm('Are you sure you want to delete service center "' + name + '"?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
