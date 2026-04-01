<?php
/**
 * API: Get Product Serials
 * Returns available (in_stock) serial numbers for a product
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

$product_id = (int)get_param('product_id', 0);
$search = get_param('search', '');
// Use 'in_stock' as the standard status for available serials
$status_filter = get_param('status', 'in_stock');

try {
    $sql = "SELECT id, serial_number, imei FROM product_serials WHERE product_id = ? AND status = ? AND (stock_type IS NULL OR stock_type != 'rma')";
    $params = [$product_id, $status_filter];
    
    if (!empty($search)) {
        $sql .= " AND (serial_number LIKE ? OR imei LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY serial_number ASC LIMIT 100";
    
    $serials = db_query($sql, $params);
    
    // If it's an AJAX call from stock-adjustment, we might want just the data array
    if (get_param('raw') == '1') {
        echo json_encode($serials);
    } else {
        echo json_encode([
            'status' => true,
            'data' => $serials
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Error fetching serials: ' . $e->getMessage()
    ]);
}
