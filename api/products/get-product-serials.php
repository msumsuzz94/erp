<?php
/**
 * API: Get Product Serials
 * Fetches available serial numbers for a product
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';

header('Content-Type: application/json');

$product_id = (int) get_param('product_id');

if (!$product_id) {
    echo json_encode(['status' => false, 'message' => 'Product ID is required']);
    exit;
}

try {
    // Get available serials for the product (exclude sold, rma, etc.)
    $sql = "SELECT id, serial_number, status, sale_date
            FROM product_serials
            WHERE product_id = ? AND status = 'in_stock' 
            ORDER BY serial_number ASC";
    
    $serials = db_query($sql, [$product_id]);
    
    echo json_encode([
        'status' => true,
        'serials' => $serials
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Error fetching serials: ' . $e->getMessage()
    ]);
}
