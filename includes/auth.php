<?php
/**
 * Authentication Functions
 * Handle user login, logout, and session management
 */
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/menu_functions.php';

/**
 * Start session if not already started
 * Configured to expire when browser closes (auto-logout)
 */
function init_session()
{
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session cookie to expire when browser closes
        session_set_cookie_params([
            'lifetime' => 0,  // 0 = expires when browser closes
            'path' => '/',
            'domain' => '',
            'secure' => false, // Set to true in production with HTTPS
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in()
{
    init_session();
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
}

/**
 * Require login - redirect to login if not logged in
 * @param string $redirect_url URL to redirect to after login
 */
function require_login($redirect_url = '')
{
    if (!is_logged_in()) {
        if (!empty($redirect_url)) {
            $_SESSION['redirect_after_login'] = $redirect_url;
        }
        redirect(BASE_URL . '/modules/auth/login.php');
    }

    // Global URL Permission Checking Middleware
    if (!check_current_page_access()) {
        // Exclude AJAX calls from redirecting
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }
        
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Permission denied. You do not have access to this page.'];
        header("Location: " . BASE_URL . "/modules/dashboard/index.php");
        exit;
    }
}

/**
 * Check if the user has access to the current page dynamically
 */
function check_current_page_access() {
    $user_id = get_current_user_id();
    if (!$user_id) return false;
    
    // Super admin has full access
    $user = db_select_one('users', ['id' => $user_id]);
    if ($user && $user['role_id'] == 1) return true;

    // Get the script path, e.g. /erp/modules/sales/sales-list.php
    $script_path = $_SERVER['SCRIPT_NAME'];
    
    $base_dir = parse_url(BASE_URL, PHP_URL_PATH); // e.g., '/erp'
    if ($base_dir && strpos($script_path, $base_dir) === 0) {
        $db_url = substr($script_path, strlen($base_dir));
    } else {
        $db_url = $script_path;
    }
    
    // 1. Whitelist public/common pages
    $public_pages = [
        '/index.php',
        '/modules/dashboard/index.php',
        '/modules/auth/login.php',
        '/modules/auth/logout.php',
        '/modules/settings/profile.php',
        '/modules/settings/change-password.php'
    ];
    if (in_array($db_url, $public_pages)) {
        return true;
    }
    
    // 2. Exact match in menu_items
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return true; // Allow access if DB unavailable
    
    try {
        $sql_exact = "SELECT slug FROM menu_items WHERE url = ?";
        $stmt_exact = $conn->prepare($sql_exact);
        $stmt_exact->execute([$db_url]);
        $exact_menu = $stmt_exact->fetch();
        
        if ($exact_menu) {
            return user_has_menu_access($user_id, $exact_menu['slug']);
        }
    } catch (PDOException $e) {
        return true; // Allow access on DB error
    }
    
    // 3. Fallback: Module-level check for auxiliary pages (edit, add, ajax)
    if (preg_match('/^\/modules\/([^\/]+)\//', $db_url, $matches)) {
        $module_name = $matches[1];
        
        try {
            $sql_fallback = "SELECT COUNT(*) FROM role_permissions rp 
                             JOIN menu_items mi ON rp.menu_item_id = mi.id
                             WHERE rp.role_id = ? AND (mi.slug = ? OR mi.slug LIKE ?)";
            $stmt_fallback = $conn->prepare($sql_fallback);
            $stmt_fallback->execute([$user['role_id'], $module_name, $module_name . '.%']);
            
            return $stmt_fallback->fetchColumn() > 0;
        } catch (PDOException $e) {
            return true; // Allow access on DB error
        }
    }
    
    return true; // Default allow, eg non-module folders
}

/**
 * Get current logged-in user ID
 * @return int|null User ID or null
 */
function get_current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current logged-in user data
 * @return array|null User data or null
 */
function get_logged_in_user()
{
    if (!is_logged_in()) {
        return null;
    }

    $user_id = get_current_user_id();
    $user = db_select_one('users', ['id' => $user_id]);

    if ($user) {
        unset($user['password_hash']); // Never expose password hash
    }

    return $user;
}

/**
 * Login user
 * @param string $username Username or email
 * @param string $password Password
 * @return array Result with status and message
 */
function login_user($username, $password)
{
    // Find user by username or email
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) {
        return ['status' => false, 'message' => 'Database connection error. Please try again.'];
    }
    
    $sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $username, USER_STATUS_ACTIVE]);
    $user = $stmt->fetch();

    if (!$user) {
        log_failed_login_attempt($username);
        return ['status' => false, 'message' => 'Invalid username or password'];
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        log_failed_login_attempt($username);
        return ['status' => false, 'message' => 'Invalid username or password'];
    }

    // Check if account is active
    if ($user['status'] !== USER_STATUS_ACTIVE) {
        return ['status' => false, 'message' => 'Your account is inactive. Contact administrator.'];
    }

    // Create session
    init_session();
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role_id'] = $user['role_id'];
    $_SESSION['theme_preference'] = $user['theme_preference'] ?? 'dark';
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    // Update last login
    db_update('users', [
        'last_login' => date('Y-m-d H:i:s'),
        'last_login_ip' => get_client_ip()
    ], ['id' => $user['id']]);

    // Log activity
    log_activity($user['id'], 'login', 'User logged in');

    return ['status' => true, 'message' => 'Login successful', 'user' => $user];
}

/**
 * Logout user
 */
function logout_user()
{
    init_session();

    if (is_logged_in()) {
        log_activity(get_current_user_id(), 'logout', 'User logged out');
    }

    // Destroy session
    $_SESSION = [];

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    session_destroy();
}

/**
 * Log failed login attempt
 * @param string $username Attempted username
 */
function log_failed_login_attempt($username)
{
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return;
    
    try {
        $sql = "INSERT INTO login_attempts (username, ip_address, attempted_at) VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, get_client_ip()]);
    } catch (PDOException $e) {
        // Silently fail
    }
}

/**
 * Check if account is locked due to too many failed attempts
 * @param string $username Username
 * @return bool Is locked
 */
function is_account_locked($username)
{
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return false;
    
    try {
        // Check for more than 5 failed attempts in last 15 minutes
        $sql = "SELECT COUNT(*) as attempts FROM login_attempts 
                WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username]);
        $result = $stmt->fetch();

        return $result['attempts'] >= 5;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Register new user
 * @param array $data User data
 * @return array Result with status and message
 */
function register_user($data)
{
    // Validate required fields
    if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
        return ['status' => false, 'message' => 'All fields are required'];
    }

    // Validate email
    if (!is_valid_email($data['email'])) {
        return ['status' => false, 'message' => 'Invalid email address'];
    }

    // Check password length
    if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
        return ['status' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
    }

    // Check if username exists
    if (db_exists('users', ['username' => $data['username']])) {
        return ['status' => false, 'message' => 'Username already exists'];
    }

    // Check if email exists
    if (db_exists('users', ['email' => $data['email']])) {
        return ['status' => false, 'message' => 'Email already exists'];
    }

    // Hash password
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

    // Insert user
    $user_data = [
        'username' => clean_input($data['username']),
        'email' => clean_input($data['email']),
        'password_hash' => $password_hash,
        'role_id' => $data['role_id'] ?? 2, // Default to basic user role
        'status' => USER_STATUS_ACTIVE,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $user_id = db_insert('users', $user_data);

    if ($user_id) {
        log_activity($user_id, 'register', 'New user registered');
        return ['status' => true, 'message' => 'Registration successful', 'user_id' => $user_id];
    } else {
        return ['status' => false, 'message' => 'Registration failed'];
    }
}

/**
 * Change user password
 * @param int $user_id User ID
 * @param string $old_password Old password
 * @param string $new_password New password
 * @return array Result with status and message
 */
function change_password($user_id, $old_password, $new_password)
{
    $user = db_select_one('users', ['id' => $user_id]);

    if (!$user) {
        return ['status' => false, 'message' => 'User not found'];
    }

    // Verify old password
    if (!password_verify($old_password, $user['password_hash'])) {
        return ['status' => false, 'message' => 'Current password is incorrect'];
    }

    // Validate new password
    if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        return ['status' => false, 'message' => 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
    }

    // Hash new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    if (db_update('users', ['password_hash' => $password_hash], ['id' => $user_id])) {
        log_activity($user_id, 'password_change', 'User changed password');
        return ['status' => true, 'message' => 'Password changed successfully'];
    } else {
        return ['status' => false, 'message' => 'Failed to change password'];
    }
}

/**
 * Reset password (for forgot password functionality)
 * @param string $email User email
 * @return array Result with status and message
 */
function request_password_reset($email)
{
    $user = db_select_one('users', ['email' => $email]);

    if (!$user) {
        // Don't reveal if email exists or not
        return ['status' => true, 'message' => 'If email exists, reset link will be sent'];
    }

    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store token
    db_insert('password_resets', [
        'user_id' => $user['id'],
        'token' => $token,
        'expires_at' => $expiry,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // Send email with reset link
    $reset_link = BASE_URL . '/modules/auth/reset-password.php?token=' . $token;
    $subject = 'Password Reset Request';
    $body = "Click the link to reset your password: <a href='$reset_link'>$reset_link</a>";

    send_email($email, $subject, $body);

    return ['status' => true, 'message' => 'Password reset link sent to your email'];
}

/**
 * Check session timeout
 * @return bool Session is valid
 */
function check_session_timeout()
{
    if (!is_logged_in()) {
        return false;
    }

    $login_time = $_SESSION['login_time'] ?? 0;
    $current_time = time();

    if (($current_time - $login_time) > SESSION_LIFETIME) {
        logout_user();
        return false;
    }

    // Update login time to extend session
    $_SESSION['login_time'] = $current_time;
    return true;
}
