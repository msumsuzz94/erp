<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['serials'])) {
    $serials = json_decode($_POST['serials'], true);
    
    if (!is_array($serials) || empty($serials)) {
        echo json_encode(['success' => true, 'duplicates' => []]);
        exit;
    }
    
    // Clean strings
    $clean_serials = array_map(function($s) {
        return trim($s);
    }, $serials);
    
    $duplicates = [];
    
    // Check DB
    foreach ($clean_serials as $s) {
        $exists = db_select_one('product_serials', ['serial_number' => $s]);
        if ($exists) {
            $duplicates[] = $s;
        }
    }
    
    echo json_encode([
        'success' => true,
        'duplicates' => $duplicates
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
