<?php
/**
 * Add User Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/menu_functions.php';

require_login();
require_permission('users.add');

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];

        if (empty($_POST['username']))
            $errors[] = 'Username is required';
        if (empty($_POST['email']))
            $errors[] = 'Email is required';
        if (empty($_POST['password']))
            $errors[] = 'Password is required';
        if (strlen($_POST['password']) < 6)
            $errors[] = 'Password must be at least 6 characters';

        if (db_exists('users', ['username' => $_POST['username']])) {
            $errors[] = 'Username already exists';
        }

        if (db_exists('users', ['email' => $_POST['email']])) {
            $errors[] = 'Email already exists';
        }

        if (empty($errors)) {
            // Server-side check: Only Super Admin (Role 1) can create another Super Admin
            $assigned_role_id = (int)$_POST['role_id'];
            if ($assigned_role_id == 1 && $_SESSION['user_role_id'] != 1) {
                $errors[] = 'You do not have permission to create a Super Admin account';
            }

            if (empty($errors)) {
                // Handle photo upload
                $photo_path = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../../uploads/users/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_extension, $allowed_extensions)) {
                        $new_filename = 'user_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
                            $photo_path = 'uploads/users/' . $new_filename;
                        }
                    } else {
                        $errors[] = 'Invalid image format. Only JPG, JPEG, PNG, and GIF are allowed.';
                    }
                }
                
                if (empty($errors)) {
                    $data = [
                        'username' => clean_input($_POST['username']),
                        'email' => clean_input($_POST['email']),
                        'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                        'role_id' => (int) $_POST['role_id'],
                        'status' => $_POST['status'] ?? 'active',
                        'photo' => $photo_path,
                        'created_at' => date('Y-m-d H:i:s')
                    ];

                    $user_id = db_insert('users', $data);

                    if ($user_id) {
                        // Save menu permissions for the role
                        if (isset($_POST['menu_permissions']) && is_array($_POST['menu_permissions'])) {
                            $menu_ids = array_map('intval', $_POST['menu_permissions']);
                            save_role_permissions($data['role_id'], $menu_ids);
                        }

                        log_activity(get_current_user_id(), 'add_user', "Added user: {$data['username']}");
                        redirect_with_message('users-list.php', 'User added successfully', 'success');
                    } else {
                        $errors[] = 'Failed to add user';
                    }
                }
            }
        }
    }
}

$current_user_role = $_SESSION['user_role_id'];
$where_roles = "";
if ($current_user_role != 1) {
    $where_roles = " WHERE id != 1 ";
}
$roles = db_query("SELECT * FROM roles $where_roles ORDER BY name ASC");

$page_title = 'Add User';
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
        <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Role <span class="text-danger">*</span></label>
                        <select name="role_id" id="role_id" class="form-control" required>
                            <option value="">Select Role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Profile Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <small class="text-muted">Allowed: JPG, JPEG, PNG, GIF (Max 2MB)</small>
                    </div>
                </div>
            </div>

            <!-- Menu Permissions Section -->
            <div class="row">
                <div class="col-md-12">
                    <hr>
                    <h5 class="mb-3"><i class="fas fa-unlock-alt"></i> Menu Permissions</h5>
                    <p class="text-muted">Select which menus this user's role can access. Permissions are saved per
                        role.</p>
                    <div id="permissions-container" class="mt-3">
                        <p class="text-muted">Please select a role first</p>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save User</button>
                    <a href="users-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Load permissions JavaScript -->
<script src="<?= BASE_URL ?>/assets/js/user-permissions.js"></script>

<!-- Add custom styling for permissions -->
<style>
    .permission-group {
        border: 1px solid var(--border-color);
        padding: 15px;
        border-radius: 5px;
        background-color: var(--card-bg);
    }

    .permission-children {
        background-color: var(--body-bg);
        padding: 10px;
        border-radius: 3px;
        margin-top: 10px;
    }

    .custom-control-label {
        cursor: pointer;
    }

    .permissions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
    }

    @media (max-width: 768px) {
        .permissions-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
