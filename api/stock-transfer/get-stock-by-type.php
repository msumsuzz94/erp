<?php
/**
 * API: Get Stock Quantity by Type or Warehouse
 * Returns available stock for a product based on stock type (current, rma, damaged) OR warehouse
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';

$product_id = $_GET['product_id'] ?? null;
$stock_type = $_GET['stock_type'] ?? null;
$warehouse_id = $_GET['warehouse_id'] ?? null;

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Missing product_id']);
    exit;
}

if (!$stock_type && !$warehouse_id) {
    echo json_encode(['success' => false, 'message' => 'Missing stock_type or warehouse_id']);
    exit;
}

try {
    $quantity = 0;
    
    if ($warehouse_id) {
        // Warehouse mode - get from product_warehouse_stock
        $stock = db_query_one(
            "SELECT quantity FROM product_warehouse_stock WHERE product_id = ? AND warehouse_id = ?",
            [$product_id, $warehouse_id]
        );
        $quantity = $stock['quantity'] ?? 0;
    } else if ($stock_type === 'current') {
        // Get from main products table
        $product = db_select_one('products', ['id' => $product_id]);
        $quantity = $product['stock_quantity'] ?? 0;
    } else {
        // Get from stock_type_inventory table
        $stock = db_query_one(
            "SELECT quantity FROM stock_type_inventory WHERE product_id = ? AND stock_type = ?",
            [$product_id, $stock_type]
        );
        $quantity = $stock['quantity'] ?? 0;
    }
    
    echo json_encode([
        'success' => true,
        'quantity' => (float)$quantity
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
