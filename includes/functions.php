<?php

/**
 * Global Helper Functions
 * Utility functions used throughout the application
 */

/**
 * Sanitize user input to prevent XSS
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitize_input($data)
{
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data ?? '')), ENT_QUOTES, 'UTF-8');
}

/**
 * Clean input for database (trim whitespace)
 * @param mixed $data Input data
 * @return mixed Cleaned data
 */
function clean_input($data)
{
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    return trim($data ?? '');
}

/**
 * Format currency value
 * @param float $amount Amount to format
 * @param bool $include_symbol Include currency symbol
 * @return string Formatted currency
 */
function format_currency($amount, $include_symbol = true)
{
    $formatted = number_format((float)$amount, 2, '.', ',');
    return $include_symbol ? APP_CURRENCY_SYMBOL . $formatted : $formatted;
}

/**
 * Format date
 * @param string $date Date to format
 * @param string $format Format string
 * @return string Formatted date
 */
function format_date($date, $format = null)
{
    if (!$format) {
        $format = DATE_FORMAT;
    }
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format datetime
 * @param string $datetime Datetime to format
 * @return string Formatted datetime
 */
function format_datetime($datetime)
{
    return format_date($datetime, DATETIME_FORMAT);
}

/**
 * Generate random string
 * @param int $length Length of string
 * @return string Random string
 */
function generate_random_string($length = 10)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate invoice number with configurable format
 * Supports 5 different formats based on invoice_settings
 * @param string $prefix Invoice prefix
 * @param int $last_id Last invoice ID
 * @return string Invoice number
 */
function generate_invoice_number($prefix = null, $last_id = 0)
{
    // Get invoice settings from DB
    $settings = db_select_one('invoice_settings', ['id' => 1]);

    // Use DB prefix if not explicitly provided, otherwise fallback to constant
    if (!$prefix) {
        $prefix = $settings['invoice_prefix'] ?? INVOICE_PREFIX;
    }

    // Get invoice format and padding from settings
    $format = $settings['invoice_number_format'] ?? '{PREFIX}-{NNNN}';
    $padding = (int)($settings['invoice_number_digits'] ?? INVOICE_NUMBER_LENGTH);

    // Generate the sequential number with padding
    $number = str_pad($last_id + 1, $padding, '0', STR_PAD_LEFT);

    // Format invoice number based on selected format
    switch ($format) {
        case '{PREFIX}-{MMM}-{YYYY}-{NNNN}': // NEXINV-FEB-2026-0001
            $month = strtoupper(date('M'));
            $year = date('Y');
            return $prefix . $month . '-' . $year . '-' . $number;

        case '{PREFIX}-{NNNN}': // NEXINV-0001
            return $prefix . $number;

        case '{PREFIX}-{YYYYMMDD}-{NNNN}': // NEXINV-20260228-0001
            $date = date('Ymd');
            return $prefix . $date . '-' . $number;

        case '{PREFIX}-{YY}-{MMM}-{NNNN}': // NEXINV-26-FEB-0001
            $shortYear = date('y');
            $month = strtoupper(date('M'));
            return $prefix . $shortYear . '-' . $month . '-' . $number;

        case '{PREFIX}-{YYYY}-{NNNN}': // NEXINV-2026-0001
            $year = date('Y');
            return $prefix . $year . '-' . $number;

        default: // Fallback to PREFIX-NNNN
            return $prefix . $number;
    }
}

/**
 * Generate purchase number
 * @param int $last_id Last purchase ID
 * @return string Purchase number
 */
function generate_purchase_number($last_id = 0)
{
    return generate_invoice_number(PURCHASE_PREFIX, $last_id);
}

/**
 * Generate quotation number
 * @param int $last_id Last quotation ID
 * @return string Quotation number
 */
function generate_quotation_number($last_id = 0)
{
    return generate_invoice_number(QUOTATION_PREFIX, $last_id);
}

/**
 * Upload image file
 * @param array $file $_FILES array element
 * @param string $directory Upload directory
 * @param array $allowed_types Allowed file types
 * @return array Result with status and message/filename
 */
function upload_image($file, $directory, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'heic', 'heif'])
{
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['status' => false, 'message' => 'No file uploaded'];
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Upload error occurred'];
    }

    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['status' => false, 'message' => 'File size exceeds maximum allowed'];
    }

    // Get file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Check file type
    if (!in_array($file_ext, $allowed_types)) {
        return ['status' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }

    // Verify it's actually an image (skip for HEIC/HEIF as getimagesize may not support them)
    if (!in_array($file_ext, ['heic', 'heif'])) {
        $check = getimagesize($file['tmp_name']);
        if ($check === false) {
            return ['status' => false, 'message' => 'File is not a valid image'];
        }
    }

    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
    $upload_path = $directory . $new_filename;

    // Create directory if it doesn't exist
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['status' => true, 'filename' => $new_filename];
    } else {
        return ['status' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Delete file
 * @param string $filepath Full file path
 * @return bool Success status
 */
function delete_file($filepath)
{
    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Send email (basic - can be enhanced with PHPMailer)
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body
 * @param string $from From email
 * @return bool Success status
 */
function send_email($to, $subject, $body, $from = null)
{
    if (!SMTP_ENABLED) {
        return false;
    }

    if (!$from) {
        $from = SMTP_FROM_EMAIL;
    }

    $headers = "From: $from\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return mail($to, $subject, $body, $headers);
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generate_csrf_token()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool Valid or not
 */
function verify_csrf_token($token)
{
    if (!ENABLE_CSRF) {
        return true;
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect to URL
 * @param string $url URL to redirect to
 */
function redirect($url)
{
    header("Location: $url");
    exit();
}

/**
 * Redirect with message
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function redirect_with_message($url, $message, $type = 'info')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    redirect($url);
}

/**
 * Get flash message and clear it
 * @return array Message and type
 */
function get_flash_message()
{
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';

    unset($_SESSION['flash_message'], $_SESSION['flash_type']);

    return ['message' => $message, 'type' => $type];
}

/**
 * Set flash message without redirect
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function set_message($message, $type = 'info')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}


/**
 * Calculate tax amount
 * @param float $amount Base amount
 * @param float $tax_rate Tax rate (percentage)
 * @return float Tax amount
 */
function calculate_tax($amount, $tax_rate)
{
    return ($amount * $tax_rate) / 100;
}

/**
 * Calculate discount amount
 * @param float $amount Base amount
 * @param float $discount Discount (percentage or flat)
 * @param string $type Discount type (percentage or flat)
 * @return float Discount amount
 */
function calculate_discount($amount, $discount, $type = 'flat')
{
    if ($type === 'percentage') {
        return ($amount * $discount) / 100;
    }
    return $discount;
}

/**
 * Generate barcode (requires barcode library)
 * @param string $code Code to generate
 * @param string $type Barcode type
 * @return string Barcode image path or base64
 */
function generate_barcode($code, $type = 'code128')
{
    // This is a placeholder - implement with actual barcode library
    // Example: Picqer\Barcode\BarcodeGeneratorPNG
    return $code;
}

/**
 * Log user activity
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $details Action details
 * @return bool Success status
 */
function log_activity($user_id, $action, $details = '')
{
    global $conn;
    if ($conn === null) {
        return false;
    }
    try {
        $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get client IP address
 * @return string IP address
 */
function get_client_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Check if request is POST
 * @return bool
 */
function is_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 * @return bool
 */
function is_get()
{
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get POST data
 * @param string $key Key to get
 * @param mixed $default Default value
 * @return mixed Value
 */
function post($key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/**
 * Get GET data
 * @param string $key Key to get
 * @param mixed $default Default value
 * @return mixed Value
 */
function get_param($key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/**
 * Validate email
 * @param string $email Email to validate
 * @return bool Valid or not
 */
function is_valid_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (basic)
 * @param string $phone Phone to validate
 * @return bool Valid or not
 */
function is_valid_phone($phone)
{
    return preg_match('/^[0-9+\-\s()]{10,20}$/', $phone);
}

/**
 * Truncate string
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to add
 * @return string Truncated text
 */
function truncate($text, $length = 100, $suffix = '...')
{
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Format number
 * @param float $number Number to format
 * @param int $decimals Decimal places
 * @return string Formatted number
 */
function format_number($number, $decimals = 2)
{
    return number_format((float)$number, $decimals, '.', ',');
}

/**
 * Get user's full name
 * @param array $user User array
 * @return string Full name
 */
function get_user_name($user)
{
    if (isset($user['name'])) {
        return $user['name'];
    }
    return $user['username'] ?? 'Unknown';
}

/**
 * Generate slug from string
 * @param string $text Text to slugify
 * @return string Slug
 */
function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

/**
 * Format bytes to human readable format
 * @param int $bytes Bytes
 * @param int $precision Decimal precision
 * @return string Formatted size
 */
function format_bytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
/**
 * Convert number to words (BDT format)
 * @param float $number The number to convert
 * @param string $currency The currency prefix (e.g. BDT)
 * @return string The number in words
 */
function convert_number_to_words($number, $currency = 'BDT', $is_recursive = false)
{
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ', ';
    $negative    = 'negative ';
    $decimal     = ' and ';
    $dictionary  = array(
        0                   => 'Zero',
        1                   => 'One',
        2                   => 'Two',
        3                   => 'Three',
        4                   => 'Four',
        5                   => 'Five',
        6                   => 'Six',
        7                   => 'Seven',
        8                   => 'Eight',
        9                   => 'Nine',
        10                  => 'Ten',
        11                  => 'Eleven',
        12                  => 'Twelve',
        13                  => 'Thirteen',
        14                  => 'Fourteen',
        15                  => 'Fifteen',
        16                  => 'Sixteen',
        17                  => 'Seventeen',
        18                  => 'Eighteen',
        19                  => 'Nineteen',
        20                  => 'Twenty',
        30                  => 'Thirty',
        40                  => 'Forty',
        50                  => 'Fifty',
        60                  => 'Sixty',
        70                  => 'Seventy',
        80                  => 'Eighty',
        90                  => 'Ninety',
        100                 => 'Hundred',
        1000                => 'Thousand',
        100000              => 'Lakh',
        10000000            => 'Crore'
    );

    if (!is_numeric($number)) {
        return false;
    }

    if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
        // overflow
        trigger_error(
            'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
            E_USER_WARNING
        );
        return false;
    }

    if ($number < 0) {
        return $negative . convert_number_to_words(abs($number), '', true);
    }

    $string = $fraction = null;

    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }

    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds  = $number / 100;
            $remainder = $number % 100;
            $string = $dictionary[(int) $hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . convert_number_to_words($remainder, '', true);
            }
            break;
        case $number < 100000:
            $thousands = $number / 1000;
            $remainder = $number % 1000;
            $string = convert_number_to_words((int) $thousands, '', true) . ' ' . $dictionary[1000];
            if ($remainder) {
                $string .= $separator . convert_number_to_words($remainder, '', true);
            }
            break;
        case $number < 10000000:
            $lakhs = $number / 100000;
            $remainder = $number % 100000;
            $string = convert_number_to_words((int) $lakhs, '', true) . ' ' . $dictionary[100000];
            if ($remainder) {
                $string .= $separator . convert_number_to_words($remainder, '', true);
            }
            break;
        case $number < 1000000000:
            $crores = $number / 10000000;
            $remainder = $number % 10000000;
            $string = convert_number_to_words((int) $crores, '', true) . ' ' . $dictionary[10000000];
            if ($remainder) {
                $string .= $separator . convert_number_to_words($remainder, '', true);
            }
            break;
        default:
            $baseUnit = pow(10, 9);
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = convert_number_to_words($numBaseUnits, '', true) . ' Billion' . $separator . convert_number_to_words($remainder, '', true);
            break;
    }

    if (null !== $fraction && is_numeric($fraction) && (int)$fraction > 0) {
        $string .= $decimal;
        $string .= convert_number_to_words((int)$fraction, '', true);
        $string .= ' Paisa';
    }

    $result = trim(($currency ? $currency . ' ' : '') . $string);
    return $is_recursive ? $result : $result . ' Only';
}

/**
 * Convert timestamp to "time ago" format
 * @param mixed $timestamp Timestamp string or integer
 * @return string Formatted time ago
 */
function timeago($timestamp)
{
    if (!$timestamp) return '-';
    $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
    if (!$time) return '-';

    $time = time() - $time;
    $time = ($time < 1) ? 1 : $time;
    $tokens = array(
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );

    foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '') . ' ago';
    }
    return 'just now';
}
