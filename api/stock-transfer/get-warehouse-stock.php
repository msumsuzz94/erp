<?php
/**
 * API: Get Warehouse Stock for Product
 * Returns available stock quantity for a product in a specific warehouse
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
    $type = $_GET['type'] ?? 'product';
    
    if (!$product_id) {
        throw new Exception('Product ID is required');
    }

    if ($type === 'rma_to_product') {
        // Get global RMA quantity for product
        $stock = db_query_one(
            "SELECT rma_quantity as quantity FROM products 
             WHERE id = ?",
            [$product_id]
        );
        $warehouse_id = 'RMA_POOL';
    } else {
        if (!$warehouse_id) {
            throw new Exception('Warehouse ID is required for regular stock');
        }
        // Get stock from product_warehouse_stock table
        $stock = db_query_one(
            "SELECT quantity FROM product_warehouse_stock 
             WHERE product_id = ? AND warehouse_id = ?",
            [$product_id, $warehouse_id]
        );
    }
    
    $quantity = $stock['quantity'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'quantity' => $quantity,
        'product_id' => $product_id,
        'warehouse_id' => $warehouse_id,
        'type' => $type
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
