<?php
/**
 * Lead Categories Management
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_once __DIR__ . '/../../includes/permissions.php';

if (!is_admin() && !has_role(get_current_user_id(), 'Manager')) {
    redirect_with_message('../../index.php', 'Insufficient permissions.', 'danger');
}

// Handle Add/Edit/Delete
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            db_insert('lead_categories', [
                'name' => clean_input($_POST['name']),
                'description' => clean_input($_POST['description']),
                'status' => 'active'
            ]);
            redirect_with_message('lead-categories.php', 'Category added successfully');
        } elseif ($action === 'edit') {
            db_update('lead_categories', [
                'name' => clean_input($_POST['name']),
                'description' => clean_input($_POST['description']),
                'status' => clean_input($_POST['status'])
            ], ['id' => (int)$_POST['category_id']]);
            redirect_with_message('lead-categories.php', 'Category updated successfully');
        } elseif ($action === 'delete') {
            $cat_id = (int)$_POST['category_id'];
            // Check if leads exist for this category
            $count = db_query_one("SELECT COUNT(*) as count FROM leads WHERE category_id = ?", [$cat_id])['count'];
            if ($count > 0) {
                redirect_with_message('lead-categories.php', "Cannot delete. Category has $count leads.", 'danger');
            } else {
                db_query("DELETE FROM lead_categories WHERE id = ?", [$cat_id]);
                redirect_with_message('lead-categories.php', 'Category deleted', 'success');
            }
        }
    }
}

$categories = db_query("SELECT * FROM lead_categories ORDER BY name ASC");

$page_title = 'Lead Categories';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle"></i> Add Category</h6>
            </div>
            <div class="card-body">
                <form method="POST" accept-charset="UTF-8">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group mb-3">
                        <label>Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Market, Bank, NGO" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Category description..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Save Category</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Lead Categories List</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= htmlspecialchars($cat['name']) ?></td>
                                <td><?= htmlspecialchars($cat['description']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $cat['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($cat['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info edit-btn" data-category='<?= htmlspecialchars(json_encode($cat), ENT_QUOTES) ?>'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" accept-charset="UTF-8">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="category_id" id="edit_id">
                    
                    <div class="form-group mb-3">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#dataTable').DataTable();
    
    $('.edit-btn').click(function() {
        const cat = $(this).data('category');
        $('#edit_id').val(cat.id);
        $('#edit_name').val(cat.name);
        $('#edit_description').val(cat.description);
        $('#edit_status').val(cat.status);
        $('#editModal').modal('show');
    });
});
</script>
