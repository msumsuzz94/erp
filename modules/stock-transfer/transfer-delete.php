<?php
/**
 * Stock Transfer - Delete/Cancel Transfer
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require login
require_login();

$transfer_id = $_GET['id'] ?? null;

if (!$transfer_id) {
    set_message('Invalid transfer ID', 'danger');
    redirect(BASE_URL . '/modules/stock-transfer/transfer-list.php');
}

// Get transfer
$transfer = db_select_one('stock_transfers', ['id' => $transfer_id]);

if (!$transfer) {
    set_message('Transfer not found', 'danger');
    redirect(BASE_URL . '/modules/stock-transfer/transfer-list.php');
}

// Can only delete pending or cancel approved/in-transit
if ($transfer['status'] === 'completed') {
    set_message('Completed transfers cannot be deleted', 'danger');
    redirect(BASE_URL . '/modules/stock-transfer/transfer-view.php?id=' . $transfer_id);
}

try {
    global $conn;
    $conn->beginTransaction();
    
    // If approved or in-transit, return stock to source
    if (in_array($transfer['status'], ['approved', 'in_transit'])) {
        $items = db_select('stock_transfer_items', ['transfer_id' => $transfer_id]);
        foreach ($items as $item) {
            $product_id = $item['product_id'];
            $qty = $item['quantity'];
            
            if ($transfer['from_warehouse_id']) {
                // Return to warehouse
                db_query(
                    "UPDATE product_warehouse_stock 
                     SET quantity = quantity + ? 
                     WHERE product_id = ? AND warehouse_id = ?",
                    [$qty, $product_id, $transfer['from_warehouse_id']]
                );
                
                // If source warehouse is saleable, return to main products table
                $from_wh = db_select_one('warehouses', ['id' => $transfer['from_warehouse_id']]);
                if ($from_wh && $from_wh['is_saleable']) {
                    db_query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$qty, $product_id]);
                }
            } else {
                // Return to Stock Type
                $from_type = explode('_to_', $transfer['transfer_type'])[0];
                if ($from_type === 'current') {
                    db_query("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$qty, $product_id]);
                } else {
                    db_query(
                        "UPDATE stock_type_inventory SET quantity = quantity + ? 
                         WHERE product_id = ? AND stock_type = ?",
                        [$qty, $product_id, $from_type]
                    );
                }
            }
            
            // Return serials to source stock_type
            if ($item['serial_numbers']) {
                $serial_ids = json_decode($item['serial_numbers'], true);
                if (!empty($serial_ids)) {
                    $from_type = explode('_to_', $transfer['transfer_type'])[0];
                    $ids_str = implode(',', array_map('intval', $serial_ids));
                    db_query(
                        "UPDATE product_serials SET stock_type = ?, status = 'in_stock' WHERE id IN ($ids_str)",
                        [$from_type]
                    );
                }
            }
        }
    }
    
    // Delete transfer (will cascade delete items)
    db_delete('stock_transfers', ['id' => $transfer_id]);
    
    log_activity(get_current_user_id(), 'stock_transfer_delete', 
                "Deleted stock transfer: {$transfer['transfer_number']}");
    
    $conn->commit();
    
    set_message('Stock transfer deleted successfully', 'success');
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    set_message('Error: ' . $e->getMessage(), 'danger');
}

redirect(BASE_URL . '/modules/stock-transfer/transfer-list.php');
?>
