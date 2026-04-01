<?php
/**
 * User Edit Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('users.list');

$user_id = (int)get_param('id');
$user = db_select_one('users', ['id' => $user_id]);

if (!$user) {
    redirect_with_message('users-list.php', 'User not found', 'error');
}

// Security: Prevent non-Super Admins from editing a Super Admin user
if ($user['role_id'] == 1 && $_SESSION['user_role_id'] != 1) {
    redirect_with_message('users-list.php', 'Permission denied. You cannot edit a Super Admin account.', 'error');
}

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        if (empty($_POST['username'])) $errors[] = 'Username is required';
        if (empty($_POST['email'])) $errors[] = 'Email is required';
        
        // Check if username exists for other users
        $existing = db_query_one("SELECT id FROM users WHERE username = ? AND id != ?", [$_POST['username'], $user_id]);
        if ($existing) {
            $errors[] = 'Username already exists';
        }
        
        if (empty($errors)) {
            // Server-side check: Only Super Admin (Role 1) can assign Super Admin role
            $assigned_role_id = (int)$_POST['role_id'];
            if ($assigned_role_id == 1 && $_SESSION['user_role_id'] != 1) {
                $errors[] = 'You do not have permission to assign the Super Admin role';
            }

            if (empty($errors)) {
                // Handle photo upload
                $photo_path = $user['photo'];
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
                            // Delete old photo if exists
                            if ($user['photo'] && file_exists(__DIR__ . '/../../' . $user['photo'])) {
                                unlink(__DIR__ . '/../../' . $user['photo']);
                            }
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
                        'role_id' => (int)$_POST['role_id'],
                        'status' => $_POST['status'] ?? 'active',
                        'photo' => $photo_path,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Update password if provided
                    if (!empty($_POST['password'])) {
                        $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    }
                    
                    if (db_update('users', $data, ['id' => $user_id])) {
                        // Save menu permissions for the role
                        if (isset($_POST['menu_permissions']) && is_array($_POST['menu_permissions'])) {
                            $menu_ids = array_map('intval', $_POST['menu_permissions']);
                            save_role_permissions($data['role_id'], $menu_ids);
                        }

                        log_activity(get_current_user_id(), 'edit_user', "Updated user: {$data['username']}");
                        redirect_with_message('users-list.php', 'User updated successfully', 'success');
                    } else {
                        $errors[] = 'Failed to update user';
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

$page_title = 'Edit User';
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
                        <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($user['username']) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control">
                        <small class="text-muted">Leave blank to keep current password</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Role <span class="text-danger">*</span></label>
                        <select name="role_id" id="role_id" class="form-control" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>" <?= $user['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role['name']) ?>
                                </option>
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
                            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label>Profile Photo</label>
                        <?php if ($user['photo']): ?>
                            <div class="mb-2">
                                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['photo']) ?>" 
                                     alt="User Photo" 
                                     style="max-width: 150px; max-height: 150px; border-radius: 8px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <small class="text-muted">Allowed: JPG, JPEG, PNG, GIF (Max 2MB). Leave empty to keep current photo.</small>
                    </div>
                </div>
            </div>
            
            <!-- Menu Permissions Section -->
            <div class="row">
                <div class="col-md-12">
                    <hr>
                    <h5 class="mb-3"><i class="fas fa-unlock-alt"></i> Menu Permissions</h5>
                    <p class="text-muted">Select which menus this user's role can access. Permissions are saved per role.</p>
                    <div id="permissions-container" class="mt-3">
                        <p class="text-muted">Please select a role first</p>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update User</button>
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
