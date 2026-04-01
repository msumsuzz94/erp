<?php
/**
 * Face Registration API
 * POST: Register face descriptor
 * DELETE: Remove biometric record
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if ($id > 0) {
        // Delete photo file if exists
        $bio = db_select_one('staff_biometrics', ['id' => $id]);
        if ($bio && !empty($bio['face_photo']) && file_exists(BASE_PATH . '/' . $bio['face_photo'])) {
            unlink(BASE_PATH . '/' . $bio['face_photo']);
        }
        db_query("DELETE FROM staff_biometrics WHERE id = ?", [$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$staff_id = (int)($input['staff_id'] ?? 0);
$photo = $input['photo'] ?? '';
$descriptor = $input['descriptor'] ?? null;

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Staff ID required']);
    exit;
}

// Save photo
$photo_path = '';
if (!empty($photo) && strpos($photo, 'data:image') === 0) {
    $upload_dir = 'uploads/biometrics';
    if (!is_dir(BASE_PATH . '/' . $upload_dir)) {
        mkdir(BASE_PATH . '/' . $upload_dir, 0777, true);
    }
    $filename = 'face_' . $staff_id . '_' . time() . '.jpg';
    $file_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $photo));
    file_put_contents(BASE_PATH . '/' . $upload_dir . '/' . $filename, $file_data);
    $photo_path = $upload_dir . '/' . $filename;
}

// Save to database
$data = [
    'staff_id' => $staff_id,
    'biometric_type' => 'face',
    'face_descriptor' => $descriptor ? json_encode($descriptor) : null,
    'face_photo' => $photo_path,
    'is_active' => 1,
    'created_at' => date('Y-m-d H:i:s')
];

// Remove old face biometric for this staff (keep only latest)
db_query("UPDATE staff_biometrics SET is_active = 0 WHERE staff_id = ? AND biometric_type = 'face'", [$staff_id]);

try {
    $insert_id = db_insert('staff_biometrics', $data);
    if ($insert_id) {
        echo json_encode(['success' => true, 'message' => 'Face registered successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: Insertion failed']);
    }
} catch (Exception $e) {
    error_log("Face register error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
