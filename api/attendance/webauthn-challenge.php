<?php

/**
 * WebAuthn Challenge API
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$staff_id = (int)($input['staff_id'] ?? 0);
$action = $input['action'] ?? 'authenticate';

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Staff ID required']);
    exit;
}

// Check if staff has WebAuthn credential
$credential = db_query("SELECT * FROM staff_biometrics WHERE staff_id = ? AND biometric_type = 'webauthn' AND is_active = 1 LIMIT 1", [$staff_id]);

if ($action === 'authenticate' && empty($credential)) {
    // No credential yet — allow registration flow instead of blocking
    // The response will have status: 'needs_registration' and the JS will handle it
}

// Generate challenge
if (session_status() === PHP_SESSION_NONE) session_start();
$challenge = random_bytes(32);
$_SESSION['webauthn_challenge'] = base64_encode($challenge);

$response = [
    'success' => true,
    'challenge' => array_values(unpack('C*', $challenge)),
    'rp_id' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    'status' => empty($credential) ? 'needs_registration' : 'ready'
];

if (!empty($credential)) {
    $response['credential_id'] = $credential[0]['webauthn_credential_id'];
} else {
    // For registration, we need user info
    $staff = db_select_one('staff', ['id' => $staff_id]);
    $response['user'] = [
        'id' => array_values(unpack('C*', (string)$staff_id)),
        'name' => 'staff_' . $staff_id,
        'display_name' => $staff['name'] ?? 'Staff'
    ];
}

echo json_encode($response);
exit;
