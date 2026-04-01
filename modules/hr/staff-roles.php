<?php
/**
 * Staff Roles Management Page
 * Manage staff roles/positions and assignments
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$errors = [];
$success_message = '';

// Handle Add/Edit Role
if (is_post() && isset($_POST['save_role'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $role_id = isset($_POST['role_id']) ? (int) $_POST['role_id'] : 0;
        $role_name = clean_input($_POST['role_name']);
        $description = clean_input($_POST['description']);

        if (empty($role_name)) {
            $errors[] = 'Role name is required';
        }

        if (empty($errors)) {
            // Check for duplicate role name
            $existing_role = db_query_one("SELECT id FROM staff_roles WHERE role_name = ? AND id != ?", [$role_name, $role_id]);
            if ($existing_role) {
                $errors[] = 'Role name already exists';
            } else {
                $data = [
                    'role_name' => $role_name,
                    'description' => $description
                ];

                if ($role_id > 0) {
                    // Update
                    if (db_update('staff_roles', $data, ['id' => $role_id])) {
                        log_activity(get_current_user_id(), 'role_update', "Updated role: $role_name");
                        $success_message = 'Role updated successfully!';
                    } else {
                        $errors[] = 'Failed to update role';
                    }
                } else {
                    // Insert
                    if (db_insert('staff_roles', $data)) {
                        log_activity(get_current_user_id(), 'role_create', "Created role: $role_name");
                        $success_message = 'Role created successfully!';
                    } else {
                        $errors[] = 'Failed to create role';
                    }
                }
            }
        }
    }
}

// Handle Delete Role
if (is_post() && isset($_POST['delete_role'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $role_id = (int) $_POST['role_id'];

        // Check if any staff assigned to this role
        $staff_count = db_query("SELECT COUNT(*) as count FROM staff WHERE role_id = $role_id")[0]['count'];

        if ($staff_count > 0) {
            $errors[] = "Cannot delete role. $staff_count staff member(s) are assigned to this role.";
        } else {
            db_delete('staff_roles', ['id' => $role_id]);
            log_activity(get_current_user_id(), 'role_delete', "Deleted role ID: $role_id");
            $success_message = 'Role deleted successfully!';
        }
    }
}

// Handle Assign Role to Staff
if (is_post() && isset($_POST['assign_role'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $staff_id = (int) $_POST['staff_id'];
        $role_id = (int) $_POST['role_id'];

        if ($staff_id > 0) {
            $updateData = ['role_id' => $role_id > 0 ? $role_id : NULL];
            db_update('staff', $updateData, ['id' => $staff_id]);
            log_activity(get_current_user_id(), 'role_assignment', "Assigned role to staff ID: $staff_id");
            $success_message = 'Role assigned successfully!';
        }
    }
}

// Get all roles with staff count
$roles = db_query("SELECT 
    sr.*, 
    COUNT(s.id) as staff_count
    FROM staff_roles sr
    LEFT JOIN staff s ON sr.id = s.role_id
    GROUP BY sr.id
    ORDER BY sr.role_name ASC");

// Get all staff
$all_staff = db_query("SELECT s.*, sr.role_name 
    FROM staff s
    LEFT JOIN staff_roles sr ON s.role_id = sr.id
    ORDER BY s.name ASC");

$page_actions = '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
    <i class="fas fa-plus"></i> Add New Role
</button>';

$page_title = 'Staff Roles Management';
include __DIR__ . '/../../templates/header.php';
?>



    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i>
            <?= $success_message ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Roles List -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Roles</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="rolesTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Role Name</th>
                                    <th>Description</th>
                                    <th width="80">Staff Count</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($roles as $role): ?>
                                    <tr>
                                        <td><strong>
                                                <?= htmlspecialchars($role['role_name']) ?>
                                            </strong></td>
                                        <td>
                                            <?= htmlspecialchars($role['description']) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-primary bg-primary">
                                                <?= $role['staff_count'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning btn-edit" data-id="<?= $role['id'] ?>"
                                                data-name="<?= htmlspecialchars($role['role_name']) ?>"
                                                data-description="<?= htmlspecialchars($role['description']) ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($role['staff_count'] == 0): ?>
                                                <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $role['id'] ?>"
                                                    data-name="<?= htmlspecialchars($role['role_name']) ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled
                                                    title="Cannot delete - staff assigned">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign Roles -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Assign Role to Staff</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="assign_role" value="1">

                        <div class="form-group">
                            <label>Select Staff <span class="text-danger">*</span></label>
                            <select name="staff_id" class="form-control" required>
                                <option value="">-- Select Staff --</option>
                                <?php foreach ($all_staff as $staff): ?>
                                    <option value="<?= $staff['id'] ?>">
                                        <?= htmlspecialchars($staff['name']) ?>
                                        <?php if ($staff['role_name']): ?>
                                            (Current:
                                            <?= htmlspecialchars($staff['role_name']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Assign Role <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-control" required>
                                <option value="0">-- No Role --</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>">
                                        <?= htmlspecialchars($role['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-check"></i> Assign Role
                        </button>
                    </form>
                </div>
            </div>

            <!-- Staff by Role Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Staff by Role</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($roles as $role): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <?= htmlspecialchars($role['role_name']) ?>
                                </span>
                                <span class="badge badge-primary">
                                    <?= $role['staff_count'] ?>
                                </span>
                                </div>

                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar"
                                    style="width: <?= count($all_staff) > 0 ? ($role['staff_count'] / count($all_staff) * 100) : 0 ?>%">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>


<!-- Add/Edit Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="save_role" value="1">
                    <input type="hidden" name="role_id" id="role_id" value="0">

                    <div class="form-group">
                        <label>Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="role_name" id="role_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="delete_role" value="1">
                    <input type="hidden" name="role_id" id="delete_role_id">

                    <p>Are you sure you want to delete the role <strong id="delete_role_name"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Role
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function () {
        $('#rolesTable').DataTable({
            order: [[0, 'asc']]
        });

        // Edit role
        $('.btn-edit').on('click', function () {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var description = $(this).data('description');

            $('#modalTitle').text('Edit Role');
            $('#role_id').val(id);
            $('#role_name').val(name);
            $('#description').val(description);

            $('#addRoleModal').modal('show');
        });

        // Reset modal when closed
        $('#addRoleModal').on('hidden.bs.modal', function () {
            $('#modalTitle').text('Add New Role');
            $('#role_id').val('0');
            $('#role_name').val('');
            $('#description').val('');
        });

        // Delete role
        $('.btn-delete').on('click', function () {
            var id = $(this).data('id');
            var name = $(this).data('name');

            $('#delete_role_id').val(id);
            $('#delete_role_name').text(name);

            $('#deleteModal').modal('show');
        });
    });
</script>
