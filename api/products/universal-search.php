<?php
/**
 * Universal Product Search API
 * Allows searching for ANY product by name, code, or serial number
 * Returns stock status (current, rma, or out of stock)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';

$search = $_GET['search'] ?? '';

if (empty($search)) {
    echo json_encode(['status' => false, 'message' => 'Search term required']);
    exit;
}

try {
    $search_term = '%' . $search . '%';
    
    // Search in products by name or code
    $products = db_query(
        "SELECT p.id, p.name, p.code, p.stock_quantity,
                (SELECT COUNT(*) FROM product_serials WHERE product_id = p.id AND status = 'in_stock') as available_serials,
                (SELECT COUNT(*) FROM stock_type_inventory WHERE product_id = p.id AND stock_type = 'rma') as rma_stock
         FROM products p
         WHERE p.name LIKE ? OR p.code LIKE ?
         ORDER BY p.name ASC
         LIMIT 50",
        [$search_term, $search_term]
    );
    
    // Also search by serial number
    $serial_results = db_query(
        "SELECT ps.id as serial_id, ps.serial_number, ps.status, ps.stock_type, ps.product_id,
                p.id, p.name, p.code, p.stock_quantity,
                (SELECT COUNT(*) FROM product_serials WHERE product_id = p.id AND status = 'in_stock') as available_serials,
                (SELECT COUNT(*) FROM stock_type_inventory WHERE product_id = p.id AND stock_type = 'rma') as rma_stock
         FROM product_serials ps
         INNER JOIN products p ON ps.product_id = p.id
         WHERE ps.serial_number LIKE ?
         LIMIT 10",
        [$search_term]
    );
    
    // Combine results
    $results = [];
    
    // Add product results
    foreach ($products as $product) {
        $stock_status = '';
        if ($product['stock_quantity'] > 0) {
            $stock_status = 'Current Stock (' . $product['stock_quantity'] . ')';
        } elseif ($product['rma_stock'] > 0) {
            $stock_status = 'RMA Stock (' . $product['rma_stock'] . ')';
        } else {
            $stock_status = 'Out of Stock';
        }
        
        $results[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'code' => $product['code'],
            'stock_quantity' => $product['stock_quantity'],
            'stock_status' => $stock_status,
            'available_serials' => $product['available_serials'],
            'type' => 'product'
        ];
    }
    
    // Add serial results with their product info and selection rules
    foreach ($serial_results as $serial) {
        $stock_status = '';
        $is_selectable = true;
        $selection_message = '';
        
        // Determine selectability based on stock_type
        if ($serial['stock_type'] === 'current' && $serial['status'] === 'in_stock') {
            // Current stock serials are NOT selectable for RMA replacement
            $is_selectable = false;
            $stock_status = 'Current Stock';
            $selection_message = '❌ Current Stock - Not Selectable';
        } elseif ($serial['stock_type'] === 'rma') {
            // RMA stock serials are ALWAYS selectable
            $is_selectable = true;
            $stock_status = 'RMA Stock';
            $selection_message = '✅ RMA Stock - Selectable';
        } elseif ($serial['stock_type'] === 'damaged') {
            $is_selectable = true;
            $stock_status = 'Damaged Stock';
            $selection_message = '⚠️ Damaged Stock';
        } else {
            // Unknown or null stock_type (sold, transferred, etc)
            $is_selectable = true;
            $stock_status = $serial['status'] === 'sold' ? 'Sold' : 'Available';
            $selection_message = $serial['status'];
        }
        
        $results[] = [
            'id' => $serial['id'],
            'serial_id' => $serial['serial_id'],
            'serial_number' => $serial['serial_number'],
            'serial_status' => $serial['status'],
            'stock_type' => $serial['stock_type'],
            'name' => $serial['name'],
            'code' => $serial['code'],
            'stock_quantity' => $serial['stock_quantity'],
            'stock_status' => $stock_status,
            'selection_message' => $selection_message,
            'is_selectable' => $is_selectable,
            'type' => 'serial',
            'product_id' => $serial['product_id']
        ];
    }
    
    echo json_encode([
        'status' => true,
        'data' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}
?>
