<?php
/**
 * API: Approve/Update Stock Transfer
 * Handles approval, in-transit, and completion actions
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require login
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $transfer_id = $data['transfer_id'] ?? null;
    $action = $data['action'] ?? null;
    
    if (!$transfer_id || !$action) {
        throw new Exception('Transfer ID and action are required');
    }
    
    // Get transfer details
    $transfer = db_select_one('stock_transfers', ['id' => $transfer_id]);
    if (!$transfer) {
        throw new Exception('Transfer not found');
    }
    
    dbBeginTransaction();
    
    $user_id = get_current_user_id();
    
    switch ($action) {
        case 'approve':
            // Only approve if pending
            if ($transfer['status'] !== 'pending') {
                throw new Exception('Only pending transfers can be approved');
            }
            
            // Deduct stock from source
            $items = db_select('stock_transfer_items', ['transfer_id' => $transfer_id]);
            
            foreach ($items as $item) {
                $product_id = $item['product_id'];
                $qty = $item['quantity'];
                
                // 1. Deduct from Source Inventory
                if ($transfer['from_warehouse_id']) {
                    // Warehouse deduction
                    db_query(
                        "UPDATE product_warehouse_stock 
                         SET quantity = quantity - ? 
                         WHERE product_id = ? AND warehouse_id = ?",
                        [$qty, $product_id, $transfer['from_warehouse_id']]
                    );
                    
                    // If source warehouse is saleable, deduct from main products table
                    $from_wh = db_select_one('warehouses', ['id' => $transfer['from_warehouse_id']]);
                    if ($from_wh && $from_wh['is_saleable']) {
                        db_query("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?", [$qty, $product_id]);
                    }
                } else {
                    // Stock Type transfer
                    $type_parts = explode('_to_', $transfer['transfer_type']);
                    $from_type  = $type_parts[0] ?? null;
                    $to_type    = $type_parts[1] ?? null;

                    // --- Deduct from source ---
                    if ($from_type === 'current') {
                        db_query("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?", [$qty, $product_id]);
                    } elseif ($from_type) {
                        // FIX: no warehouse_id filter — warehouse_id is NULL for stock type transfers
                        db_query(
                            "UPDATE stock_type_inventory SET quantity = quantity - ?
                             WHERE product_id = ? AND stock_type = ?",
                            [$qty, $product_id, $from_type]
                        );
                        if ($from_type === 'rma') {
                            db_query("UPDATE products SET rma_quantity = GREATEST(0, rma_quantity - ?) WHERE id = ?", [$qty, $product_id]);
                        }
                    }

                    // --- Add to destination at approve time (stock type = status change only, no physical movement) ---
                    if ($to_type === 'current') {
                        db_query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$qty, $product_id]);
                    } elseif ($to_type === 'loss') {
                        // Loss/Scrap: permanently removed — nothing to add
                    } elseif ($to_type) {
                        db_query(
                            "INSERT INTO stock_type_inventory (product_id, stock_type, quantity)
                             VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE quantity = quantity + ?",
                            [$product_id, $to_type, $qty, $qty]
                        );
                        if ($to_type === 'rma') {
                            db_query("UPDATE products SET rma_quantity = rma_quantity + ? WHERE id = ?", [$qty, $product_id]);
                        }
                    }

                    // --- Update serial numbers NOW at approve time ---
                    if (!empty($item['serial_numbers'])) {
                        $serial_ids = json_decode($item['serial_numbers'], true);
                        if (!empty($serial_ids)) {
                            $ids_str = implode(',', array_map('intval', $serial_ids));
                            if ($to_type === 'loss') {
                                db_query("UPDATE product_serials SET stock_type = 'loss', status = 'scrapped' WHERE id IN ($ids_str)");
                            } else {
                                db_query(
                                    "UPDATE product_serials SET stock_type = ?, status = 'in_stock' WHERE id IN ($ids_str)",
                                    [$to_type]
                                );
                            }
                        }
                    }
                }
            }
            
            // Update transfer status
            db_update('stock_transfers', [
                'status' => 'approved',
                'approved_by' => $user_id,
                'approved_at' => date('Y-m-d H:i:s')
            ], ['id' => $transfer_id]);
            
            log_activity($user_id, 'stock_transfer_approve', "Approved transfer: {$transfer['transfer_number']}");
            $message = 'Transfer approved successfully';
            break;
            
        case 'complete':
            // Can complete from approved or in_transit
            if (!in_array($transfer['status'], ['approved', 'in_transit'])) {
                throw new Exception('Only approved or in-transit transfers can be completed');
            }
            
            $items = db_select('stock_transfer_items', ['transfer_id' => $transfer_id]);
            
            foreach ($items as $item) {
                $product_id = $item['product_id'];
                $qty        = $item['quantity'];
                $to_type    = explode('_to_', $transfer['transfer_type'])[1] ?? 'current';
                
                // For WAREHOUSE transfers: add to destination here (physical receipt)
                if ($transfer['to_warehouse_id']) {
                    db_query(
                        "INSERT INTO product_warehouse_stock (product_id, warehouse_id, quantity)
                         VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?",
                        [$product_id, $transfer['to_warehouse_id'], $qty, $qty]
                    );
                    $to_wh = db_select_one('warehouses', ['id' => $transfer['to_warehouse_id']]);
                    if ($to_wh && $to_wh['is_saleable']) {
                        db_query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$qty, $product_id]);
                    }
                    // Update serials for warehouse transfers
                    if (!empty($item['serial_numbers'])) {
                        $serial_ids = json_decode($item['serial_numbers'], true);
                        if (!empty($serial_ids)) {
                            $ids_str = implode(',', array_map('intval', $serial_ids));
                            db_query(
                                "UPDATE product_serials SET stock_type = ?, status = 'in_stock' WHERE id IN ($ids_str)",
                                [$to_type]
                            );
                        }
                    }
                }
                // For STOCK TYPE transfers: inventory + serials already updated at approve time — skip here
            }
            
            // Update status
            db_update('stock_transfers', [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s')
            ], ['id' => $transfer_id]);
            
            log_activity($user_id, 'stock_transfer_complete', "Completed transfer: {$transfer['transfer_number']}");
            $message = 'Transfer completed successfully';
            break;
            
        case 'reject':
            // Only reject if pending
            if ($transfer['status'] !== 'pending') {
                throw new Exception('Only pending transfers can be rejected');
            }
            
            db_update('stock_transfers', [
                'status' => 'rejected',
                'approved_by' => $user_id,
                'approved_at' => date('Y-m-d H:i:s')
            ], ['id' => $transfer_id]);
            
            log_activity($user_id, 'stock_transfer_reject', 
                        "Rejected stock transfer: {$transfer['transfer_number']}");
            
            $message = 'Transfer rejected';
            break;
            
        case 'cancel':
            // Can cancel if not completed
            if ($transfer['status'] === 'completed') {
                throw new Exception('Completed transfers cannot be cancelled');
            }
            
            // If approved, return stock to source
            if (in_array($transfer['status'], ['approved', 'in_transit'])) {
                $items = db_select('stock_transfer_items', ['transfer_id' => $transfer_id]);
                foreach ($items as $item) {
                    db_query(
                        "UPDATE product_warehouse_stock 
                         SET quantity = quantity + ? 
                         WHERE product_id = ? AND warehouse_id = ?",
                        [$item['quantity'], $item['product_id'], $transfer['from_warehouse_id']]
                    );
                }
            }
            
            db_update('stock_transfers', [
                'status' => 'cancelled'
            ], ['id' => $transfer_id]);
            
            log_activity($user_id, 'stock_transfer_cancel', 
                        "Cancelled stock transfer: {$transfer['transfer_number']}");
            
            $message = 'Transfer cancelled';
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    dbCommit();
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        dbRollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
