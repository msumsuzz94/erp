<?php

/**
 * Media Resize Action
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

header('Content-Type: application/json');

if (!is_admin()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

if (!is_post() || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or CSRF token.']);
    exit;
}

$filename = basename($_POST['filename'] ?? '');
$folder = basename($_POST['folder'] ?? '');
$new_width = (int)($_POST['width'] ?? 0);
$new_height = (int)($_POST['height'] ?? 0);

if (empty($filename) || empty($folder) || $new_width <= 0 || $new_height <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid dimensions or missing file info.']);
    exit;
}

$file_path = "uploads/{$folder}/{$filename}";
$absolute_path = BASE_PATH . '/' . $file_path;

// Basic security check to ensure it's in the uploads folder and no traversal
if (strpos($file_path, '..') !== false || !file_exists($absolute_path)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file or file not found.']);
    exit;
}

// Get original dimensions and type
$image_info = @getimagesize($absolute_path);
if (!$image_info) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid image file.']);
    exit;
}

list($orig_width, $orig_height, $image_type) = $image_info;

// Load image based on type
$image = null;
switch ($image_type) {
    case IMAGETYPE_JPEG:
        $image = @imagecreatefromjpeg($absolute_path);
        break;
    case IMAGETYPE_PNG:
        $image = @imagecreatefrompng($absolute_path);
        break;
    case IMAGETYPE_GIF:
        $image = @imagecreatefromgif($absolute_path);
        break;
    case IMAGETYPE_WEBP:
        $image = @imagecreatefromwebp($absolute_path);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Unsupported image type for resizing.']);
        exit;
}

if (!$image) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to load image memory.']);
    exit;
}

// Create new true color image
$new_image = imagecreatetruecolor($new_width, $new_height);

// Handle transparency for PNG and WEBP
if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_WEBP) {
    imagealphablending($new_image, false);
    imagesavealpha($new_image, true);
    $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
    imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
}

// Resize
imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

// Save back to original path (overwrite)
$save_success = false;
switch ($image_type) {
    case IMAGETYPE_JPEG:
        $save_success = imagejpeg($new_image, $absolute_path, 90);
        break;
    case IMAGETYPE_PNG:
        $save_success = imagepng($new_image, $absolute_path, 9);
        break;
    case IMAGETYPE_GIF:
        $save_success = imagegif($new_image, $absolute_path);
        break;
    case IMAGETYPE_WEBP:
        $save_success = imagewebp($new_image, $absolute_path, 90);
        break;
}

// Free memory
unset($image);
unset($new_image);

if ($save_success) {
    echo json_encode(['status' => 'success', 'message' => 'Image resized successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save resized image.']);
}
