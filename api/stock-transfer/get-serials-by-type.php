<?php
/**
 * API: Get Serial Numbers by Stock Type
 * Returns available serial numbers for a product in a specific stock type
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';

$product_id = $_GET['product_id'] ?? null;
$stock_type = $_GET['stock_type'] ?? null;

if (!$product_id || !$stock_type) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Check if product has serial tracking
    $product = db_select_one('products', ['id' => $product_id]);
    
    if (!$product || $product['has_serial'] !== 'Available') {
        echo json_encode([
            'success' => true,
            'has_serial' => false,
            'serials' => []
        ]);
        exit;
    }
    
    // Get serials for this stock type
    // Note: serial_status can be 'in_stock', 'sold', 'returned', 'defective', etc.
    // When stock_type is 'current', serials typically have serial_status='in_stock'
    // When stock_type is 'rma' or 'damaged', serials have matching serial_status
    
    // Build query based on stock type
    // damaged serials have status='defective', RMA serials have status='in_stock'
    $query = "SELECT id, serial_number, status, stock_type, serial_status
              FROM product_serials 
              WHERE product_id = ?";
    
    $params = [$product_id];
    
    // Add stock_type and status filter
    if ($stock_type === 'current') {
        // For current stock, check either stock_type is null/empty or explicitly 'current'
        $query .= " AND status = 'in_stock'";
        $query .= " AND (stock_type IS NULL OR stock_type = '' OR stock_type = 'current')";
    } else if ($stock_type === 'damaged') {
        // Damaged serials: status='defective', stock_type='damaged'
        $query .= " AND stock_type = 'damaged'";
        $query .= " AND status IN ('in_stock', 'defective')";
    } else {
        // RMA or other stock types
        $query .= " AND stock_type = ?";
        $query .= " AND status = 'in_stock'";
        $params[] = $stock_type;
    }
    
    $query .= " ORDER BY serial_number ASC";
    
    $serials = db_query($query, $params);
    
    echo json_encode([
        'success' => true,
        'has_serial' => true,
        'serials' => $serials
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
