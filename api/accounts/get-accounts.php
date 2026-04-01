<?php
/**
 * API: Get Accounts
 * Returns cash or bank accounts based on type parameter
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require login
if (!is_logged_in()) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = get_param('type', 'cash');

try {
    $accounts = [];
    
    if ($type === 'cash') {
        // Fetch active cash accounts
        $accounts = db_select('cash_accounts', ['status' => 'active'], '*', 'account_name ASC');
    } else if ($type === 'bank' || $type === 'card' || $type === 'mobile_money' || $type === 'mobile_banking') {
        // For bank, card, and mobile money, fetch bank accounts
        // You can filter by account_type if that column exists
        $accounts = db_select('bank_accounts', [], '*', 'bank_name ASC');
    } else {
        // Default to cash accounts for other payment methods
        $accounts = db_select('cash_accounts', ['status' => 'active'], '*', 'account_name ASC');
    }
    
    echo json_encode([
        'status' => true,
        'data' => $accounts
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Error fetching accounts: ' . $e->getMessage()
    ]);
}
