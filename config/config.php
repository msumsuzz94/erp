<?php
ob_start(); // Buffer output to prevent "headers already sent" errors on cPanel

/**
 * Central Configuration File
 * This is the ONLY file that needs to be modified when deploying from XAMPP to cPanel
 */

// ============================================
// 1. ENVIRONMENT SETTINGS
// ============================================
if (!defined('APP_ENV')) define('APP_ENV', 'development'); // development or production
if (!defined('DEBUG_MODE')) define('DEBUG_MODE', true); // Set to false in production

// Error reporting based on environment
$app_env = APP_ENV;
if ($app_env === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================
// 2. DATABASE CREDENTIALS
// ============================================
// XAMPP Settings (Development)
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', 'business_pos');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// For cPanel deployment, update the above values:
// define('DB_HOST', 'localhost');
// define('DB_NAME', 'your_cpanel_dbname');
// define('DB_USER', 'your_cpanel_dbuser');
// define('DB_PASS', 'your_cpanel_dbpass');

// ============================================
// 3. APPLICATION SETTINGS
// ============================================
if (!defined('APP_NAME')) define('APP_NAME', 'Business Management System');
if (!defined('APP_VERSION')) define('APP_VERSION', '2.8.2');

// ============================================
// 4. DYNAMIC BASE URL (Works on any IP or Domain)
// ============================================
if (PHP_SAPI === 'cli') {
    // Fallback for CLI/Cron scripts
    define('BASE_URL', 'http://localhost/erp');
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Calculate subfolder path
    $app_dir = str_replace('\\', '/', dirname(__DIR__));
    $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');

    $subfolder = '';
    if (!empty($doc_root) && strpos($app_dir, $doc_root) === 0) {
        $subfolder = substr($app_dir, strlen($doc_root));
    }

    define('BASE_URL', $protocol . $host . $subfolder);
}

// ============================================
// ALLOWED DOMAINS (Domain Lock)
// Only these domains can access the application
// ============================================
if (!defined('ALLOWED_DOMAINS')) {
    define('ALLOWED_DOMAINS', [
        'localhost',
        '127.0.0.1',
        '192.168.0.0/16',   // Local network
        // cPanel/hPanel ডোমেইন নিচে যোগ করুন:
        // 'cbcc.accuzest.com',
        // 'yourdomain.com',
    ]);
}

// Domain Lock Enforcement
if (PHP_SAPI !== 'cli') {
    $current_host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    $domain_allowed = false;
    
    foreach (ALLOWED_DOMAINS as $allowed) {
        if (strpos($allowed, '/') !== false) {
            // CIDR notation for IP ranges (e.g., 192.168.0.0/16)
            if (filter_var($current_host, FILTER_VALIDATE_IP) && ip_in_range($current_host, $allowed)) {
                $domain_allowed = true;
                break;
            }
        } elseif ($current_host === strtolower($allowed)) {
            $domain_allowed = true;
            break;
        }
    }
    
    if (!$domain_allowed) {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
        $current_url = $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        
        // Prevent redirect loop if bases completely match but there's an unforeseen issue
        if (strpos($current_url, BASE_URL) !== 0) {
            header("Location: " . BASE_URL, true, 301);
            exit;
        } else {
            http_response_code(403);
            die('Access denied.');
        }
    }
}

// Helper: Check if IP is in CIDR range
function ip_in_range($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = ~((1 << (32 - (int)$mask)) - 1);
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}

// Base Path
define('BASE_PATH', dirname(__DIR__));

// Upload settings
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', BASE_PATH . '/uploads/');
if (!defined('PRODUCT_IMAGE_PATH')) define('PRODUCT_IMAGE_PATH', UPLOAD_PATH . 'products/');
if (!defined('INVOICE_PATH')) define('INVOICE_PATH', UPLOAD_PATH . 'invoices/');
if (!defined('DOCUMENT_PATH')) define('DOCUMENT_PATH', UPLOAD_PATH . 'documents/');
if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
if (!defined('LOG_PATH')) define('LOG_PATH', BASE_PATH . '/logs/');

// Auto-create essential directories for cPanel deployments
$essential_dirs = [
    UPLOAD_PATH, 
    PRODUCT_IMAGE_PATH, 
    INVOICE_PATH, 
    DOCUMENT_PATH,
    LOG_PATH
];

foreach ($essential_dirs as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// ============================================
// 4. DATE AND TIME SETTINGS
// ============================================
if (!defined('DATE_FORMAT')) define('DATE_FORMAT', 'Y-m-d'); // PHP date format
if (!defined('DATETIME_FORMAT')) define('DATETIME_FORMAT', 'Y-m-d H:i:s');
if (!defined('TIMEZONE')) define('TIMEZONE', 'Asia/Dhaka'); // Default to Dhaka
date_default_timezone_set(TIMEZONE);

// ============================================
// 6. SECURITY SETTINGS
// ============================================
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 1800); // 30 minutes in seconds
if (!defined('PASSWORD_MIN_LENGTH')) define('PASSWORD_MIN_LENGTH', 6);
if (!defined('ENABLE_CSRF')) define('ENABLE_CSRF', true);
if (!defined('ALLOWED_FILE_TYPES')) define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'heic', 'heif', 'pdf', 'doc', 'docx']);

// ============================================
// 7. PAGINATION & LIMITS
// ============================================
if (!defined('RECORDS_PER_PAGE')) define('RECORDS_PER_PAGE', 25);
if (!defined('MAX_SEARCH_RESULTS')) define('MAX_SEARCH_RESULTS', 100);
if (!defined('DASHBOARD_RECENT_ITEMS')) define('DASHBOARD_RECENT_ITEMS', 10);

// ============================================
// 8. NOTIFICATION SETTINGS
// ============================================
// Email settings (SMTP)
if (!defined('SMTP_ENABLED')) define('SMTP_ENABLED', false);
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', 'your-email@gmail.com');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'your-password');
if (!defined('SMTP_ENCRYPTION')) define('SMTP_ENCRYPTION', 'tls');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'noreply@yourbusiness.com');

// SMS & WhatsApp (Future features)
if (!defined('SMS_ENABLED')) define('SMS_ENABLED', false);
if (!defined('SMS_API_KEY')) define('SMS_API_KEY', '');
if (!defined('WHATSAPP_ENABLED')) define('WHATSAPP_ENABLED', false);
if (!defined('WHATSAPP_API_KEY')) define('WHATSAPP_API_KEY', '');

// ============================================
// 9. FEATURE FLAGS
// ============================================
if (!defined('ENABLE_PRODUCT_VARIANTS')) define('ENABLE_PRODUCT_VARIANTS', true);
if (!defined('ENABLE_SERIAL_TRACKING')) define('ENABLE_SERIAL_TRACKING', true);
if (!defined('ENABLE_WARRANTY_RMA')) define('ENABLE_WARRANTY_RMA', true);
if (!defined('ENABLE_QUOTATIONS')) define('ENABLE_QUOTATIONS', true);
if (!defined('ENABLE_HR_MODULE')) define('ENABLE_HR_MODULE', true);
if (!defined('ENABLE_SALARY')) define('ENABLE_SALARY', false); // Optional
if (!defined('ENABLE_ATTENDANCE')) define('ENABLE_ATTENDANCE', false); // Optional
if (!defined('ENABLE_MULTI_CURRENCY')) define('ENABLE_MULTI_CURRENCY', false); // Future

// ============================================
// 10. INVOICE SETTINGS
// ============================================
if (!defined('INVOICE_NUMBER_LENGTH')) define('INVOICE_NUMBER_LENGTH', 6); // INV-000001
if (!defined('PURCHASE_PREFIX')) define('PURCHASE_PREFIX', 'PUR-');
if (!defined('QUOTATION_PREFIX')) define('QUOTATION_PREFIX', 'QUO-');

// ============================================
// 11. BARCODE SETTINGS
// ============================================
if (!defined('BARCODE_TYPE')) define('BARCODE_TYPE', 'code128'); // code128, ean13, qr
if (!defined('BARCODE_PREFIX')) define('BARCODE_PREFIX', 'BMS');

// ============================================
// 12. AUTO-DETECT ENVIRONMENT (Optional)
// ============================================
function is_localhost()
{
    $whitelist = ['127.0.0.1', '::1', 'localhost'];
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', $whitelist) ||
        in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
}

// Optionally auto-adjust settings based on environment
if (is_localhost()) {
    // Running on localhost (XAMPP)
    if (!defined('BASE_URL') || BASE_URL === '') {
        define('BASE_URL_AUTO', 'http://localhost/business-management-system');
    }
} else {
    // Running on live server
    if (!defined('BASE_URL') || BASE_URL === '') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        define('BASE_URL_AUTO', $protocol . $_SERVER['HTTP_HOST']);
    }
}

// ============================================
// 13. INCLUDE CONSTANTS & DATABASE CONNECTION
// ============================================
// Include constants first
require_once BASE_PATH . '/config/constants.php';

// Include database connection
require_once BASE_PATH . '/config/database.php';

// Include database helper functions
require_once BASE_PATH . '/includes/db_functions.php';

// ============================================
// 14. DYNAMIC BUSINESS SETTINGS
// ============================================
// Get business settings for global defaults (Updated to fetch early)
$meta_business_settings = db_select_one('business_settings', ['id' => 1]) ?: [];

// Business & Personal Identity
if (!defined('BUSINESS_NAME')) define('BUSINESS_NAME', !empty($meta_business_settings['business_name']) ? $meta_business_settings['business_name'] : 'CITNBD ERP');
if (!defined('BUSINESS_LOGO')) define('BUSINESS_LOGO', !empty($meta_business_settings['business_logo']) ? $meta_business_settings['business_logo'] : 'assets/images/logo.png');
if (!defined('BUSINESS_PHONE')) define('BUSINESS_PHONE', !empty($meta_business_settings['business_phone']) ? $meta_business_settings['business_phone'] : '01976793351');
if (!defined('BUSINESS_EMAIL')) define('BUSINESS_EMAIL', !empty($meta_business_settings['business_email']) ? $meta_business_settings['business_email'] : 'citnbd.com@gmail.com');
if (!defined('BUSINESS_ADDRESS')) define('BUSINESS_ADDRESS', !empty($meta_business_settings['business_address']) ? $meta_business_settings['business_address'] : 'Dakhin khan, Uttara, Dhaka-1230');
if (!defined('BUSINESS_TAX_NO')) define('BUSINESS_TAX_NO', !empty($meta_business_settings['business_tax_no']) ? $meta_business_settings['business_tax_no'] : 'TAX123456');

// Financial Defaults
if (!defined('DEFAULT_TAX_RATE')) define('DEFAULT_TAX_RATE', floatval($meta_business_settings['tax_rate'] ?? 0.00)); // Standardized to tax_rate column

if (!defined('APP_CURRENCY_SYMBOL')) {
    $db_curr = isset($meta_business_settings['currency']) ? trim($meta_business_settings['currency']) : '';
    
    // Allowed valid currency symbols in the system
    $valid_currencies = ['$', '৳', '€', '£', '¥', '₹', '₨', 'د.إ', '﷼', '₦', 'R', 'CHF', 'CAD', 'AUD', '&#2547;'];
    
    // If the currency from DB is corrupted, fallback to literal Taka symbol
    if (!in_array($db_curr, $valid_currencies)) {
        $db_curr = '৳'; 
    }
    
    // Force final Taka symbol if it still ended up as the HTML entity
    if ($db_curr === '&#2547;') {
        $db_curr = '৳';
    }
    
    define('APP_CURRENCY_SYMBOL', $db_curr); 
}

if (!defined('INVOICE_PREFIX')) define('INVOICE_PREFIX', !empty($meta_business_settings['invoice_prefix']) ? $meta_business_settings['invoice_prefix'] : 'INV-');

// Regional Settings
if (!defined('CURRENCY_CODE')) {
    $db_curr = defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : '&#2547;';
    define('CURRENCY_CODE', ($db_curr === '৳' || $db_curr === '&#2547;') ? 'BDT' : 'USD');
}

// Business constants used in other settings
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', BUSINESS_NAME);

// ============================================
// 15. LICENSE ENFORCEMENT
// ============================================
require_once BASE_PATH . '/includes/license_functions.php';

// Exclude certain pages from the license check to prevent redirection loops
$current_page = $_SERVER['PHP_SELF'];
$exclude_pages = [
    '/modules/settings/license-manage.php',
    '/modules/auth/login.php',
    '/modules/auth/logout.php',
    '/assets',
    '/api/auth'
];

$is_excluded = false;
foreach ($exclude_pages as $page) {
    if (strpos($current_page, $page) !== false) {
        $is_excluded = true;
        break;
    }
}

// Redirect to license page if no valid license (and not on an excluded page)
if (!$is_excluded && PHP_SAPI !== 'cli') {
    $license_check = validate_license();
    if (!$license_check['status']) {
        // If not logged in, we might just want to show a message or redirect to login first
        // But for a hard lock, we redirect to activation
        header("Location: " . BASE_URL . "/modules/settings/license-manage.php?error=" . ($license_check['message'] ?? 'invalid_license'));
        exit;
    }
}
