<?php
/**
 * AJAX API: Remove Part from Service Ticket
 * Restores stock to inventory, recalculates totals
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';

// Manual session check (don't use require_login - it checks URL permissions)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired. Please login again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$part_id = (int)($_POST['part_id'] ?? 0);
$ticket_id = (int)($_POST['ticket_id'] ?? 0);

if (!$part_id || !$ticket_id) {
    echo json_encode(['success' => false, 'error' => 'Missing part_id or ticket_id']);
    exit;
}

// Find the part
$part = db_select_one('service_ticket_parts', ['id' => $part_id]);
if (!$part) {
    echo json_encode(['success' => false, 'error' => 'Part not found']);
    exit;
}

// Check ticket
$ticket = db_select_one('service_tickets', ['id' => $ticket_id]);
if (!$ticket) {
    echo json_encode(['success' => false, 'error' => 'Ticket not found']);
    exit;
}
if ($ticket['status'] === 'Delivered') {
    echo json_encode(['success' => false, 'error' => 'Cannot modify a delivered ticket']);
    exit;
}

try {
    db_begin_transaction();
    
    // Restore stock if inventory product (stock goes back to original)
    if ($part['product_id']) {
        db_query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", 
                 [$part['quantity'], $part['product_id']]);
                 
        // Restore serials if any
        if (!empty($part['serial_numbers'])) {
            $serials = array_map('trim', explode(',', $part['serial_numbers']));
            foreach ($serials as $sn) {
                if (!empty($sn)) {
                    db_query("UPDATE product_serials SET status = 'in_stock', sale_id = NULL, sale_date = NULL WHERE serial_number = ? AND product_id = ?", 
                             [$sn, $part['product_id']]);
                }
            }
        }
    }
    
    // Delete part from ticket
    db_delete('service_ticket_parts', ['id' => $part_id]);
    
    // Recalculate totals
    $parts_total = db_query_one("SELECT COALESCE(SUM(total_price),0) as total FROM service_ticket_parts WHERE ticket_id = ?", [$ticket_id]);
    $new_parts_cost = (float)($parts_total['total'] ?? 0);
    $new_total = (float)$ticket['service_charge'] + $new_parts_cost - (float)$ticket['discount'];
    
    db_update('service_tickets', [
        'total_parts_cost' => $new_parts_cost, 
        'total_amount' => $new_total
    ], ['id' => $ticket_id]);
    
    log_activity($user_id, 'service_part', "Removed part '{$part['product_name']}' from {$ticket['ticket_number']}");
    db_commit();
    
    // Return updated parts list
    $parts = db_query("SELECT * FROM service_ticket_parts WHERE ticket_id = ? ORDER BY id", [$ticket_id]);
    
    echo json_encode([
        'success' => true,
        'message' => "Part '{$part['product_name']}' removed. Stock restored.",
        'parts' => $parts ?: [],
        'total_parts_cost' => $new_parts_cost,
        'total_amount' => $new_total,
        'service_charge' => (float)$ticket['service_charge'],
        'discount' => (float)$ticket['discount']
    ]);
    
} catch (Exception $e) {
    db_rollback();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
