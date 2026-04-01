<?php
/**
 * Add/Edit Category Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$category_id = (int)get_param('id', 0);
$category = null;
$is_edit = false;

if ($category_id > 0) {
    $category = db_select_one('categories', ['id' => $category_id]);
    if ($category) {
        $is_edit = true;
    }
}

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        if (empty($_POST['name'])) $errors[] = 'Category name is required';
        
        if (empty($errors)) {
            $data = [
                'name' => clean_input($_POST['name']),
                'description' => clean_input($_POST['description'])
            ];
            
            if ($is_edit) {
                if (db_update('categories', $data, ['id' => $category_id])) {
                    redirect_with_message('categories-list.php', 'Category updated successfully', 'success');
                } else {
                    $errors[] = 'Failed to update category';
                }
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                if (db_insert('categories', $data)) {
                    redirect_with_message('categories-list.php', 'Category added successfully', 'success');
                } else {
                    $errors[] = 'Failed to add category';
                }
            }
        }
    }
}

$page_title = $is_edit ? 'Edit Category' : 'Add Category';
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
        <h6 class="m-0 font-weight-bold text-primary">Category Information</h6>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <div class="form-group mb-3">
                <label>Category Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($category['name'] ?? $_POST['name'] ?? '') ?>">
            </div>
            
            <div class="form-group mb-3">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($category['description'] ?? $_POST['description'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Category</button>
            <a href="categories-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
