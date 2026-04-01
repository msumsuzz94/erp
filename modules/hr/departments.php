<?php
/**
 * Department Management Page
 * Manage staff departments - Add, Edit, Delete
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$errors = [];
$success_message = '';

// Handle Add/Edit Department
if (is_post() && isset($_POST['save_department'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $dept_id = isset($_POST['dept_id']) ? (int) $_POST['dept_id'] : 0;
        $name = clean_input($_POST['name']);
        $description = clean_input($_POST['description']);

        if (empty($name)) {
            $errors[] = 'Department name is required';
        }

        if (empty($errors)) {
            // Check for duplicate
            $existing = db_query_one("SELECT id FROM staff_departments WHERE name = ? AND id != ?", [$name, $dept_id]);
            if ($existing) {
                $errors[] = 'Department name already exists';
            } else {
                $data = [
                    'name' => $name,
                    'description' => $description
                ];

                if ($dept_id > 0) {
                    if (db_update('staff_departments', $data, ['id' => $dept_id])) {
                        log_activity(get_current_user_id(), 'dept_update', "Updated department: $name");
                        $success_message = 'Department updated successfully!';
                    } else {
                        $errors[] = 'Failed to update department';
                    }
                } else {
                    if (db_insert('staff_departments', $data)) {
                        log_activity(get_current_user_id(), 'dept_create', "Created department: $name");
                        $success_message = 'Department created successfully!';
                    } else {
                        $errors[] = 'Failed to create department';
                    }
                }
            }
        }
    }
}

// Handle Delete Department
if (is_post() && isset($_POST['delete_department'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $dept_id = (int) $_POST['dept_id'];

        // Check if any staff assigned
        $staff_count = db_query("SELECT COUNT(*) as count FROM staff WHERE department_id = $dept_id");
        $count = $staff_count[0]['count'] ?? 0;

        if ($count > 0) {
            $errors[] = "Cannot delete department. $count staff member(s) are assigned to this department.";
        } else {
            db_delete('staff_departments', ['id' => $dept_id]);
            log_activity(get_current_user_id(), 'dept_delete', "Deleted department ID: $dept_id");
            $success_message = 'Department deleted successfully!';
        }
    }
}

// Get all departments with staff count
$departments = db_query("SELECT 
    d.*, 
    COUNT(s.id) as staff_count
    FROM staff_departments d
    LEFT JOIN staff s ON d.id = s.department_id
    GROUP BY d.id
    ORDER BY d.name ASC");
if (!$departments) $departments = [];

// Get all staff for assignment
$all_staff = db_query("SELECT s.*, d.name as dept_name 
    FROM staff s 
    LEFT JOIN staff_departments d ON s.department_id = d.id 
    ORDER BY s.name ASC");
if (!$all_staff) $all_staff = [];

// Handle Assign Department to Staff
if (is_post() && isset($_POST['assign_department'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $staff_id = (int) $_POST['staff_id'];
        $dept_id = (int) $_POST['dept_id'];

        if ($staff_id > 0) {
            $updateData = ['department_id' => $dept_id > 0 ? $dept_id : NULL];
            db_update('staff', $updateData, ['id' => $staff_id]);
            log_activity(get_current_user_id(), 'dept_assignment', "Assigned department to staff ID: $staff_id");
            $success_message = 'Department assigned successfully!';
            // Re-fetch staff list
            $all_staff = db_query("SELECT s.*, d.name as dept_name 
                FROM staff s 
                LEFT JOIN staff_departments d ON s.department_id = d.id 
                ORDER BY s.name ASC");
            if (!$all_staff) $all_staff = [];
        }
    }
}

$page_actions = '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeptModal">
    <i class="fas fa-plus"></i> Add New Department
</button>';

$page_title = 'Department Management';
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
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Departments List -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-building"></i> All Departments</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="deptTable">
                            <thead>
                                <tr>
                                    <th>Department Name</th>
                                    <th>Description</th>
                                    <th>Staff Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($departments)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No departments found. Click "Add New Department" to create one.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($dept['name']) ?></strong></td>
                                            <td><?= htmlspecialchars($dept['description'] ?? '') ?></td>
                                            <td>
                                                <span class="badge badge-primary bg-primary"><?= $dept['staff_count'] ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning btn-edit"
                                                    data-id="<?= $dept['id'] ?>"
                                                    data-name="<?= htmlspecialchars($dept['name']) ?>"
                                                    data-description="<?= htmlspecialchars($dept['description'] ?? '') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($dept['staff_count'] == 0): ?>
                                                    <button class="btn btn-sm btn-danger btn-delete"
                                                        data-id="<?= $dept['id'] ?>"
                                                        data-name="<?= htmlspecialchars($dept['name']) ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-secondary" disabled title="Cannot delete - staff assigned">
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign Department -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-tag"></i> Assign Department to Staff</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="assign_department" value="1">

                        <div class="form-group mb-3">
                            <label>Select Staff <span class="text-danger">*</span></label>
                            <select name="staff_id" class="form-control" required>
                                <option value="">-- Select Staff --</option>
                                <?php foreach ($all_staff as $staff): ?>
                                    <option value="<?= $staff['id'] ?>">
                                        <?= htmlspecialchars($staff['name']) ?>
                                        <?php if (!empty($staff['dept_name'])): ?>
                                            (Current: <?= htmlspecialchars($staff['dept_name']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label>Assign Department <span class="text-danger">*</span></label>
                            <select name="dept_id" class="form-control" required>
                                <option value="0">-- No Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>">
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-check"></i> Assign Department
                        </button>
                    </form>
                </div>
            </div>

            <!-- Staff by Department Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar"></i> Staff by Department</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($departments)): ?>
                        <p class="text-muted text-center mb-0">No departments created yet.</p>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($dept['name']) ?></span>
                                    <span class="badge badge-primary bg-primary"><?= $dept['staff_count'] ?></span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar"
                                        style="width: <?= count($all_staff) > 0 ? ($dept['staff_count'] / count($all_staff) * 100) : 0 ?>%">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<!-- Add/Edit Department Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deptModalTitle">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="save_department" value="1">
                    <input type="hidden" name="dept_id" id="dept_id" value="0">

                    <div class="form-group mb-3">
                        <label>Department Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="dept_name" class="form-control" required placeholder="e.g. IT Support, Marketing">
                    </div>

                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" id="dept_description" class="form-control" rows="3" placeholder="Brief description of this department"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="delete_department" value="1">
                    <input type="hidden" name="dept_id" id="delete_dept_id">

                    <p>Are you sure you want to delete the department <strong id="delete_dept_name"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function () {
        <?php if (!empty($departments)): ?>
        $('#deptTable').DataTable({
            order: [[0, 'asc']]
        });
        <?php endif; ?>

        // Edit department
        $('.btn-edit').on('click', function () {
            var id = $(this).data('id');
            var name = $(this).data('name');
            var description = $(this).data('description');

            $('#deptModalTitle').text('Edit Department');
            $('#dept_id').val(id);
            $('#dept_name').val(name);
            $('#dept_description').val(description);

            $('#addDeptModal').modal('show');
        });

        // Reset modal when closed
        $('#addDeptModal').on('hidden.bs.modal', function () {
            $('#deptModalTitle').text('Add New Department');
            $('#dept_id').val('0');
            $('#dept_name').val('');
            $('#dept_description').val('');
        });

        // Delete department
        $('.btn-delete').on('click', function () {
            var id = $(this).data('id');
            var name = $(this).data('name');

            $('#delete_dept_id').val(id);
            $('#delete_dept_name').text(name);

            $('#deleteDeptModal').modal('show');
        });
    });
</script>
