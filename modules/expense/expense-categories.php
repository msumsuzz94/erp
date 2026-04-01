<?php
/**
 * Expense Categories Management Page
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
            'description' => clean_input($_POST['description'])
        ];
        
        if ($_POST['action'] === 'create') {
            if (db_insert('expense_categories', $data)) {
                log_activity(get_current_user_id(), 'create_expense_category', "Created expense category: {$data['name']}");
                redirect_with_message($_SERVER['PHP_SELF'], 'Category created successfully', 'success');
            } else {
                redirect_with_message($_SERVER['PHP_SELF'], 'Failed to create category (name might already exist)', 'error');
            }
        } elseif ($_POST['action'] === 'update') {
            $id = (int)$_POST['id'];
            if (db_update('expense_categories', $data, ['id' => $id])) {
                log_activity(get_current_user_id(), 'update_expense_category', "Updated expense category: {$data['name']}");
                redirect_with_message($_SERVER['PHP_SELF'], 'Category updated successfully', 'success');
            } else {
                redirect_with_message($_SERVER['PHP_SELF'], 'Failed to update category', 'error');
            }
        }
    }
}

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $id = (int)$_POST['delete_id'];
        if (db_delete('expense_categories', ['id' => $id])) {
            log_activity(get_current_user_id(), 'delete_expense_category', "Deleted expense category ID: $id");
            redirect_with_message($_SERVER['PHP_SELF'], 'Category deleted successfully', 'success');
        } else {
            redirect_with_message($_SERVER['PHP_SELF'], 'Failed to delete category', 'error');
        }
    }
}

$categories = db_select('expense_categories', [], '*', 'name ASC');

$page_title = 'Expense Categories';
$page_actions = '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fas fa-plus"></i> Add Category</button>';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Expense Categories</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="categoriesTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= htmlspecialchars($category['name']) ?></td>
                            <td><?= htmlspecialchars($category['description'] ?? '-') ?></td>
                            <td><?= format_date($category['created_at']) ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning" 
                                        onclick='editCategory(<?= json_encode($category) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteCategory(<?= $category['id'] ?>, '<?= htmlspecialchars($category['name']) ?>')">
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

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add Expense Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
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
                    <h5 class="modal-title">Edit Expense Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
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
    $('#categoriesTable').DataTable();
});

function editCategory(category) {
    $('#edit_id').val(category.id);
    $('#edit_name').val(category.name);
    $('#edit_description').val(category.description);
    $('#editModal').modal('show');
}

function deleteCategory(id, name) {
    if (confirm('Are you sure you want to delete category "' + name + '"?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
