<?php
/**
 * Change Password Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $result = change_password(
            get_current_user_id(),
            $_POST['current_password'],
            $_POST['new_password']
        );
        
        if ($result['status']) {
            redirect_with_message('profile.php', $result['message'], 'success');
        } else {
            $errors[] = $result['message'];
        }
    }
}

$page_title = 'Change Password';
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

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Change Your Password</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="form-group mb-3">
                        <label>Current Password <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Change Password</button>
                    <a href="profile.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$('form').on('submit', function(e) {
    var newPass = $('input[name="new_password"]').val();
    var confirmPass = $('input[name="confirm_password"]').val();
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('New password and confirm password do not match!');
        return false;
    }
});
</script>
