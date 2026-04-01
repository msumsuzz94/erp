<?php
/**
 * Profile Settings Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$user = get_logged_in_user();

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];

        if (empty($_POST['username']))
            $errors[] = 'Username is required';
        if (empty($_POST['email']))
            $errors[] = 'Email is required';

        // Check if username exists for other users
        $existing = db_query_one("SELECT id FROM users WHERE username = ? AND id != ?", [$_POST['username'], $user['id']]);
        if ($existing) {
            $errors[] = 'Username already exists';
        }

        if (empty($errors)) {
            // Handle photo upload
            $photo_path = $user['photo'] ?? null;
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
                        if (!empty($user['photo']) && file_exists(__DIR__ . '/../../' . $user['photo'])) {
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
                    'email' => clean_input($_POST['email']),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if (!empty($photo_path)) {
                    $data['photo'] = $photo_path;
                }

                if (db_update('users', $data, ['id' => $user['id']])) {
                    $_SESSION['user_email'] = $data['email'];
                    if (!empty($photo_path)) {
                        $_SESSION['user_photo'] = $photo_path;
                        $user['photo'] = $photo_path; // update local variable for re-render
                    }
                    $user['email'] = $data['email'];
                    redirect_with_message($_SERVER['PHP_SELF'], 'Profile updated successfully', 'success');
                } else {
                    $errors[] = 'Failed to update profile';
                }
            }
        }
    }
}

// Fetch user's menu permissions for display
$sql = "SELECT m.name as menu_name, m.icon 
        FROM role_permissions rp
        JOIN menu_items m ON rp.menu_item_id = m.id
        WHERE rp.role_id = ?
        ORDER BY m.sort_order";
$my_permissions = db_query($sql, [$user['role_id']]);

$page_title = 'My Profile';
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

<style>
.profile-photo-container {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto 15px auto;
    border: 3px solid var(--primary-color);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.profile-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.permission-badge {
    background-color: var(--primary-color);
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.85rem;
    margin-right: 5px;
    margin-bottom: 8px;
    display: inline-block;
}
</style>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="text-center mb-4">
                        <div class="profile-photo-container">
                            <?php if (!empty($user['photo'])): ?>
                                <img src="<?= BASE_URL . '/' . htmlspecialchars($user['photo']) ?>" alt="Profile Photo" class="profile-photo">
                            <?php else: ?>
                                <img src="<?= BASE_URL ?>/assets/img/default-avatar.png" alt="Default Avatar" class="profile-photo" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['username']) ?>&background=random'">
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="btn btn-sm btn-outline-primary" style="cursor: pointer;">
                                <i class="fas fa-camera"></i> Change Photo
                                <input type="file" name="photo" accept="image/*" style="display: none;" onchange="document.getElementById('photo-name').textContent = this.files[0].name;">
                            </label>
                            <div id="photo-name" class="text-muted small mt-1"></div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" readonly
                            value="<?= htmlspecialchars($user['username']) ?>" title="Username cannot be changed">
                        <small class="text-muted">Contact Super Admin to change your username.</small>
                    </div>

                    <div class="form-group mb-3">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required
                            value="<?= htmlspecialchars($user['email']) ?>">
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Account Info</h6>
            </div>
            <div class="card-body">
                <p><strong>Role:</strong> <?= htmlspecialchars($user['role_name'] ?? 'N/A') ?></p>
                <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                <p><strong>Last
                        Login:</strong><br><?= $user['last_login'] ? format_datetime($user['last_login']) : 'Never' ?>
                </p>
                <p><strong>Member Since:</strong><br><?= format_date($user['created_at']) ?></p>
                <hr>
                <a href="change-password.php" class="btn btn-warning btn-block mb-3">
                    <i class="fas fa-key"></i> Change Password
                </a>

                <h6 class="font-weight-bold text-primary mt-4 mb-3">My Menu Permissions</h6>
                <div class="permissions-list">
                    <?php if (empty($my_permissions) && $user['role_id'] != 1): ?>
                        <p class="text-muted mb-0">No specific menu permissions assigned.</p>
                    <?php elseif ($user['role_id'] == 1): ?>
                        <span class="permission-badge"><i class="fas fa-star"></i> Super Admin (All Access)</span>
                    <?php else: ?>
                        <?php foreach ($my_permissions as $perm): ?>
                            <span class="permission-badge">
                                <?php if ($perm['icon']): ?>
                                    <i class="<?= htmlspecialchars($perm['icon']) ?>"></i> 
                                <?php else: ?>
                                    <i class="fas fa-check"></i> 
                                <?php endif; ?>
                                <?= htmlspecialchars($perm['menu_name']) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
