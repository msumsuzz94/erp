<?php

/**
 * WebAuthn Registration API
 * Stores the credential (public key + ID) in the database
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$staff_id = (int)($input['staff_id'] ?? 0);
$credential_id = $input['credential_id'] ?? '';
$public_key = $input['public_key'] ?? '';

if (!$staff_id || !$credential_id || !$public_key) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

// Check for existing WebAuthn record
$existing = db_select_one('staff_biometrics', ['staff_id' => $staff_id, 'biometric_type' => 'webauthn']);

$data = [
    'staff_id' => $staff_id,
    'biometric_type' => 'webauthn',
    'webauthn_credential_id' => $credential_id,
    'webauthn_public_key' => $public_key,
    'is_active' => 1
];

if ($existing) {
    $result = db_update('staff_biometrics', $data, ['id' => $existing['id']]);
} else {
    $result = db_insert('staff_biometrics', $data);
}

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Biometric registered successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save biometric to database']);
}
exit;
