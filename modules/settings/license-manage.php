<?php
/**
 * License Management Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/license_functions.php';

require_login();

$errors = [];
$success_message = '';

// Handle success/error messages from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = $_GET['msg'] ?? 'License activated successfully!';
}
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $errors[] = htmlspecialchars($_GET['error']);
}

$license = db_select_one('app_license', ['id' => 1]);

// Handle license activation (POST-REDIRECT-GET pattern)
if (is_post() && isset($_POST['activate'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $key = clean_input($_POST['license_key']);
        if (empty($key)) {
            header('Location: license-manage.php?error=' . urlencode('License key is required'));
            exit;
        } else {
            $res = activate_license($key);
            if ($res['status']) {
                header('Location: license-manage.php?success=1&msg=' . urlencode($res['message'] ?? 'License activated successfully!'));
                exit;
            } else {
                $error_msg = 'Failed to activate license: ' . ($res['message'] ?? 'Unknown error');
                header('Location: license-manage.php?error=' . urlencode($error_msg));
                exit;
            }
        }
    }
}

// Handle license renewal
if (is_post() && isset($_POST['renew'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $res = renew_license();
        if ($res['status']) {
            header('Location: license-manage.php?success=1&msg=' . urlencode($res['message']));
            exit;
        } else {
            header('Location: license-manage.php?error=' . urlencode($res['message']));
            exit;
        }
    }
}

// Handle refresh
if (isset($_GET['refresh_license'])) {
    if ($license && !empty($license['license_key'])) {
        $res = remote_verify_license($license['license_key']);
        if ($res['status']) {
            header('Location: license-manage.php?success=1&msg=' . urlencode('License status refreshed.'));
        } else {
            header('Location: license-manage.php?error=' . urlencode($res['message']));
        }
        exit;
    }
}

// Reload license data after any actions
$license = db_select_one('app_license', ['id' => 1]);

$page_title = 'System License';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .license-card { border: none; border-radius: 15px; overflow: hidden; }
    .license-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
    .status-badge { font-size: 0.9rem; padding: 10px 20px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    .btn-activate { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; transition: all 0.3s ease; color: white; }
    .btn-activate:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); color: white; }
    .btn-renew { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; transition: all 0.3s ease; color: white; }
    .btn-renew:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(56, 239, 125, 0.4); color: white; }
    .info-box { background: rgba(102, 126, 234, 0.05); border-left: 5px solid #667eea; transition: all 0.3s ease; }
    .info-box:hover { background: rgba(102, 126, 234, 0.1); transform: translateX(5px); }
    .info-box-success { border-left-color: #1cc88a; }
    .info-box-warning { border-left-color: #f6c23e; }
    .license-key-input { letter-spacing: 2px; font-family: 'Courier New', monospace; }
    .meta-card { background: rgba(0,0,0,0.03); border-radius: 10px; transition: all 0.3s ease; }
    .meta-card:hover { background: rgba(0,0,0,0.06); }
    [data-theme="dark"] .meta-card { background: rgba(255,255,255,0.05); }
    [data-theme="dark"] .meta-card:hover { background: rgba(255,255,255,0.08); }
    [data-theme="dark"] .info-box { background: rgba(102, 126, 234, 0.1); }
    [data-theme="dark"] .info-box:hover { background: rgba(102, 126, 234, 0.15); }
</style>

<div class="row justify-content-center py-4">
    <div class="col-lg-8">
        <div class="card shadow-lg license-card mb-4">
            <div class="card-header license-header py-4 d-flex justify-content-between align-items-center">
                <h4 class="m-0 font-weight-bold">
                    <i class="fas fa-shield-alt me-2"></i> Software Licensing
                </h4>
                <?php if ($license && ($license['status'] ?? '') === 'active'): ?>
                    <a href="?refresh_license=1" class="btn btn-light btn-sm rounded-pill px-3">
                        <i class="fas fa-sync-alt me-1"></i> Refresh Status
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-5">

                <?php if ($success_message): ?>
                    <div class="alert alert-success border-0 shadow-sm"><i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger border-0 shadow-sm">
                        <?php foreach ($errors as $e): ?>
                            <div><i class="fas fa-times-circle me-2"></i> <?= htmlspecialchars($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Status & Expiry Info -->
                <div class="row g-4 mb-5">
                    <div class="col-md-4">
                        <div class="info-box p-4 rounded h-100">
                            <label class="text-xs font-weight-bold text-primary text-uppercase mb-2 d-block">Current Status</label>
                            <div class="d-flex align-items-center">
                                <span class="badge status-badge bg-<?= ($license['status'] ?? '') === 'active' ? 'success' : 'danger' ?>">
                                    <i class="fas <?= ($license['status'] ?? '') === 'active' ? 'fa-check-circle' : 'fa-times-circle' ?> me-2"></i>
                                    <?= strtoupper($license['status'] ?? 'No License') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box info-box-success p-4 rounded h-100">
                            <label class="text-xs font-weight-bold text-success text-uppercase mb-2 d-block">License Expiry</label>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <i class="fas fa-calendar-alt me-2 text-muted"></i>
                                <?php 
                                    $expiry = $license['expiry_date'] ?? null;
                                    if ($expiry && $expiry === '2099-12-31') {
                                        echo '<span class="text-success">Lifetime</span>';
                                    } elseif ($expiry) {
                                        echo htmlspecialchars(date('d M Y', strtotime($expiry)));
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box info-box-warning p-4 rounded h-100">
                            <label class="text-xs font-weight-bold text-warning text-uppercase mb-2 d-block">Last Verified</label>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <i class="fas fa-clock me-2 text-muted"></i>
                                <?php 
                                    $verified = $license['last_verified_at'] ?? null;
                                    echo $verified ? htmlspecialchars(date('d M Y, h:i A', strtotime($verified))) : 'Never';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Activate License Form -->
                <h6 class="font-weight-bold text-gray-700 mb-3">
                    <i class="fas fa-key me-2 text-primary"></i> Activate License
                </h6>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <div class="mb-4">
                        <div class="input-group input-group-lg shadow-sm">
                            <span class="input-group-text bg-white border-end-0 text-primary">
                                <i class="fas fa-id-card"></i>
                            </span>
                            <input type="password" name="license_key" 
                                   class="form-control border-start-0 ps-0 license-key-input" 
                                   placeholder="Enter your license key" required
                                   autocomplete="off">
                        </div>
                        <div class="form-text mt-2 ms-1 text-muted">
                            <i class="fas fa-info-circle me-1"></i> Enter your license key to activate. Contact support if you need a new key.
                        </div>
                    </div>

                    <button type="submit" name="activate" class="btn btn-activate btn-lg w-100 py-3 rounded-pill text-uppercase font-weight-bold">
                        <i class="fas fa-rocket me-2"></i> Activate & Verify
                    </button>
                </form>

                <?php if ($license && ($license['status'] ?? '') === 'active'): ?>
                    <hr class="my-4">
                    <!-- Renew License -->
                    <h6 class="font-weight-bold text-gray-700 mb-3">
                        <i class="fas fa-redo-alt me-2 text-success"></i> Renew License
                    </h6>
                    <p class="text-muted small mb-3">Contact the license server to refresh or extend your license validity period.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <button type="submit" name="renew" class="btn btn-renew btn-lg w-100 py-3 rounded-pill text-uppercase font-weight-bold">
                            <i class="fas fa-sync-alt me-2"></i> Renew from Server
                        </button>
                    </form>
                <?php endif; ?>

                <div class="mt-5 text-center pt-4 border-top">
                    <p class="text-muted small mb-1">Fingerprint: <code><?= md5($_SERVER['HTTP_HOST'] . 'BMS-SALT') ?></code></p>
                    <p class="text-muted small">Need assistance? <a href="http://citnbd.com/" target="_blank" class="text-primary font-weight-bold">Contact Support</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
