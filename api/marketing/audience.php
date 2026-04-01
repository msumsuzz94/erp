<?php
/**
 * Marketing API: Audience Calculator
 * Returns the estimated recipient count based on targeting parameters
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

header('Content-Type: application/json');

if (!is_logged_in() || (!is_admin() && !has_role(get_current_user_id(), 'Manager'))) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$target_type = $_GET['target_type'] ?? '';
$channel = $_GET['channel'] ?? 'sms';
$count = 0;

try {
    if ($target_type === 'all_customers') {
        // Needs mobile or email based on channel
        if ($channel === 'email') {
            $count = db_query_one("SELECT COUNT(*) as c FROM customers WHERE email IS NOT NULL AND email != '' AND status='active'")['c'];
        } else {
            $count = db_query_one("SELECT COUNT(*) as c FROM customers WHERE phone IS NOT NULL AND phone != '' AND status='active'")['c'];
        }
        
    } elseif ($target_type === 'lead_category') {
        $cat_id = (int)($_GET['target_category_id'] ?? 0);
        if ($channel === 'email') {
            $count = db_query_one("SELECT COUNT(*) as c FROM leads WHERE category_id=? AND email IS NOT NULL AND email != ''", [$cat_id])['c'];
        } else {
            $count = db_query_one("SELECT COUNT(*) as c FROM leads WHERE category_id=? AND mobile IS NOT NULL AND mobile != ''", [$cat_id])['c'];
        }
        
    } elseif ($target_type === 'custom') {
        $min_due = (float)($_GET['min_due'] ?? 0);
        $cond = "current_balance >= $min_due AND status='active'";
        if ($channel === 'email') {
            $cond .= " AND email IS NOT NULL AND email != ''";
        } else {
            $cond .= " AND phone IS NOT NULL AND phone != ''";
        }
        
        $count = db_query_one("SELECT COUNT(*) as c FROM customers WHERE $cond")['c'];
    }

    echo json_encode(['status' => true, 'count' => (int)$count]);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
