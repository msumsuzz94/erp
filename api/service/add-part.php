<?php
/**
 * AJAX API: Add Part to Service Ticket
 * Stock validation, inventory deduction, returns updated data as JSON
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
$ticket_id = (int)($_POST['ticket_id'] ?? 0);
$product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
$part_name = trim($_POST['part_name'] ?? '');
$quantity = (int)($_POST['quantity'] ?? 1);
$unit_price = (float)($_POST['unit_price'] ?? 0);
$serials_input = trim($_POST['serials'] ?? '');

// Validate basic fields
if (!$ticket_id || empty($part_name) || $quantity < 1) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields (ticket_id, part_name, quantity)']);
    exit;
}

// Check ticket exists and is not delivered
$ticket = db_select_one('service_tickets', ['id' => $ticket_id]);
if (!$ticket) {
    echo json_encode(['success' => false, 'error' => 'Ticket not found']);
    exit;
}
if ($ticket['status'] === 'Delivered') {
    echo json_encode(['success' => false, 'error' => 'Cannot add parts to a delivered ticket']);
    exit;
}

// Stock & Serial check for inventory products
$serial_list = [];
if ($product_id) {
    $product = db_select_one('products', ['id' => $product_id]);
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found in inventory']);
        exit;
    }
    
    // Process serials if applicable
    if ($product['has_serial'] === 'Available') {
        if (empty($serials_input)) {
            echo json_encode(['success' => false, 'error' => "Serial numbers are required for this product."]);
            exit;
        }
        
        $serials_raw = preg_split('/[\n,]+/', $serials_input);
        foreach ($serials_raw as $s) {
            $s = trim($s);
            if (!empty($s)) {
                $serial_list[] = $s;
            }
        }
        
        if (count($serial_list) !== $quantity) {
             echo json_encode(['success' => false, 'error' => "Required $quantity serials, but " . count($serial_list) . " provided."]);
             exit;
        }
        
        // Verify each serial exists and is in_stock
        foreach ($serial_list as $sn) {
            $serial_record = db_select_one('product_serials', ['serial_number' => $sn, 'product_id' => $product_id, 'status' => 'in_stock']);
            if (!$serial_record) {
                 echo json_encode(['success' => false, 'error' => "Serial number '$sn' is invalid or out of stock."]);
                 exit;
            }
        }
    } else {
        $available = (int)$product['stock_quantity'];
        if ($available < $quantity) {
            echo json_encode(['success' => false, 'error' => "Insufficient stock! Available: $available, Requested: $quantity. Please purchase stock first."]);
            exit;
        }
    }
}

try {
    db_begin_transaction();
    
    $total_price = $quantity * $unit_price;
    $serial_string = !empty($serial_list) ? implode(', ', $serial_list) : null;
    
    // Insert part record
    db_insert('service_ticket_parts', [
        'ticket_id' => $ticket_id,
        'product_id' => $product_id,
        'product_name' => $part_name,
        'quantity' => $quantity,
        'unit_price' => $unit_price,
        'total_price' => $total_price,
        'serial_numbers' => $serial_string
    ]);
    
    // Deduct inventory stock and update serial statuses
    if ($product_id) {
        db_query("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?", [$quantity, $product_id]);
        
        if (!empty($serial_list)) {
            foreach ($serial_list as $sn) {
                db_query("UPDATE product_serials SET status = 'used_in_service', sale_id = ?, sale_date = ? WHERE serial_number = ? AND product_id = ?", 
                         [$ticket_id, date('Y-m-d'), $sn, $product_id]);
            }
        }
    }
    
    // Recalculate ticket totals
    $parts_total = db_query_one("SELECT COALESCE(SUM(total_price),0) as total FROM service_ticket_parts WHERE ticket_id = ?", [$ticket_id]);
    $new_parts_cost = (float)($parts_total['total'] ?? 0);
    $new_total = (float)$ticket['service_charge'] + $new_parts_cost - (float)$ticket['discount'];
    
    db_update('service_tickets', [
        'total_parts_cost' => $new_parts_cost,
        'total_amount' => $new_total
    ], ['id' => $ticket_id]);
    
    log_activity($user_id, 'service_part', "Added part '$part_name' x$quantity to {$ticket['ticket_number']}");
    db_commit();
    
    // Return updated parts list and totals
    $parts = db_query("SELECT * FROM service_ticket_parts WHERE ticket_id = ? ORDER BY id", [$ticket_id]);
    $remaining_stock = null;
    if ($product_id) {
        $p = db_select_one('products', ['id' => $product_id]);
        $remaining_stock = (int)($p['stock_quantity'] ?? 0);
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Part '$part_name' added successfully",
        'parts' => $parts ?: [],
        'total_parts_cost' => $new_parts_cost,
        'total_amount' => $new_total,
        'service_charge' => (float)$ticket['service_charge'],
        'discount' => (float)$ticket['discount'],
        'remaining_stock' => $remaining_stock
    ]);
    
} catch (Exception $e) {
    db_rollback();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
