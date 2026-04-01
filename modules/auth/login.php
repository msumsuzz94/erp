<?php
/**
 * Login Page
 * User authentication
 */

// ============================================
// 1. INITIALIZATION
// ============================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

init_session();

// Redirect if already logged in
if (is_logged_in()) {
    redirect(BASE_URL . '/modules/dashboard/index.php');
}

// ============================================
// 2. BACKEND LOGIC
// ============================================
$error = '';
$username = '';

if (is_post()) {
    $username = clean_input(post('username'));
    $password = post('password');
    $remember = post('remember') === 'on';
    
    // Validate CSRF token
    if (!verify_csrf_token(post('csrf_token'))) {
        $error = 'Invalid request. Please try again.';
    }
    // Validate input
    elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    }
    // Check if account is locked
    elseif (is_account_locked($username)) {
        $error = 'Account temporarily locked due to too many failed login attempts. Please try again in 15 minutes.';
    }
    // Attempt login
    else {
        $result = login_user($username, $password);
        
        if ($result['status']) {
            // Set remember me cookie if checked
            if ($remember) {
                setcookie('remember_user', $username, [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
                ]);
            }
            
            // Redirect to intended page or dashboard
            $redirect_url = $_SESSION['redirect_after_login'] ?? BASE_URL . '/modules/dashboard/index.php';
            unset($_SESSION['redirect_after_login']);
            redirect($redirect_url);
        } else {
            $error = $result['message'];
        }
    }
}

// Get remember me cookie
$remembered_username = $_COOKIE['remember_user'] ?? '';
if (!empty($remembered_username) && empty($username)) {
    $username = $remembered_username;
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', sans-serif;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .login-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-floating label {
            color: #6c757d;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .login-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            text-align: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="mb-3">
                <i class="fas fa-store fa-3x"></i>
            </div>
            <h2 class="mb-1"><?= BUSINESS_NAME ?></h2>
            <p class="mb-3"><?= APP_NAME ?></p>
            <div class="small opacity-75">
                Designed & Developed By 
                <a href="http://citnbd.com/" target="_blank" class="text-white fw-bold text-decoration-none border-bottom border-white">CITNBD</a>
            </div>
        </div>
        
        <div class="login-body">
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            

            
            <form method="POST" action="" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-floating mb-3">
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           placeholder="Username" 
                           value="<?= htmlspecialchars($username) ?>"
                           required 
                           autofocus>
                    <label for="username">
                        <i class="fas fa-user me-2"></i>Username or Email
                    </label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="Password" 
                           required>
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                </div>
                
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        Remember me
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
                
            </form>
        </div>
        
        <div class="login-footer">
            <div class="mb-1">
                &copy; <?= date('Y') ?> <?= BUSINESS_NAME ?>. All rights reserved.
            </div>
            <div class="mb-2">
                <small>Designed & Developed By 
                    <a href="http://citnbd.com/" target="_blank" class="text-primary fw-bold text-decoration-none">CITNBD</a>
                </small>
            </div>
            <small class="text-muted">Version <?= APP_VERSION ?></small>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
