<?php
/**
 * API: Product Search
 * Returns products matching search query
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require login
if (!is_logged_in()) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$search = get_param('search', '');
$limit = (int)get_param('limit', 50);

$sql = "SELECT id, name, code, barcode, selling_price, purchase_price, description, tax_rate, stock_quantity, status, has_serial, image, (
            SELECT serial_number FROM product_serials ps 
            WHERE ps.product_id = products.id 
            AND ps.serial_number = ? 
            AND ps.status = 'in_stock'
            LIMIT 1
        ) as matched_serial
        FROM products
        WHERE status = 'active' AND stock_quantity > 0";

$params = [$search]; // For the matched_serial subquery

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR code LIKE ? OR barcode LIKE ? OR EXISTS (
        SELECT 1 FROM product_serials ps 
        WHERE ps.product_id = products.id 
        AND ps.serial_number = ? 
        AND ps.status = 'in_stock'
    ))";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search; // Exact match for serial number in EXISTS
}

require_once __DIR__ . '/../../includes/permissions.php';

// Prepare SR check
$is_user_sr = is_sr(get_current_user_id());

$sql .= " ORDER BY name ASC LIMIT ?";
$params[] = $limit;

try {
    $products = db_query($sql, $params);
    
    if ($is_user_sr) {
        foreach ($products as &$product) {
            unset($product['purchase_price']);
        }
        unset($product);
    }
    
    echo json_encode([
        'status' => true,
        'data' => $products
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Error fetching products'
    ]);
}
