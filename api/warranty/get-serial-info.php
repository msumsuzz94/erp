<?php
/**
 * API: Get Serial Info & Warranty Status
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

$serial = get_param('serial');

if (empty($serial)) {
    echo json_encode(['status' => false, 'message' => 'Serial number is required']);
    exit;
}

try {
    // Search for serial number
    $sql = "SELECT ps.*, p.name as product_name, p.code as product_code, 
                   s.invoice_number, s.sale_date, c.name as customer_name, c.id as customer_id
            FROM product_serials ps
            INNER JOIN products p ON ps.product_id = p.id
            LEFT JOIN sales s ON ps.sale_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE ps.serial_number = ? OR ps.imei = ?
            LIMIT 1";
    
    $data = db_query_one($sql, [$serial, $serial]);

    if (!$data) {
        echo json_encode(['status' => false, 'message' => 'Serial number not found']);
        exit;
    }

    if ($data['status'] !== 'sold') {
        echo json_encode(['status' => false, 'message' => 'Serial number status is "' . $data['status'] . '". Only sold items can have warranty claims.']);
        exit;
    }

    // Calculate Warranty status
    $warranty_months = (int)$data['warranty_months'];
    $sale_date = $data['sale_date'];
    $is_warranty_valid = false;
    $expiry_date = null;

    if ($sale_date) {
        $expiry_date = date('Y-m-d', strtotime("+$warranty_months months", strtotime($sale_date)));
        if (date('Y-m-d') <= $expiry_date) {
            $is_warranty_valid = true;
        }
    }

    echo json_encode([
        'status' => true,
        'data' => $data,
        'warranty' => [
            'is_valid' => $is_warranty_valid,
            'expiry_date' => $expiry_date,
            'months' => $warranty_months
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
