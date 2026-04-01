<?php

/**
 * Card/RFID Registration API
 * Updates the card_id in the staff table
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
$card_id = trim($input['card_id'] ?? '');

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => 'Missing staff ID']);
    exit;
}

// Update staff table
$result = db_update('staff', ['card_id' => $card_id], ['id' => $staff_id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Card ID updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update Card ID']);
}
exit;
