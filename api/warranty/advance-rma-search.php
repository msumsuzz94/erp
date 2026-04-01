<?php
/**
 * API: Advance RMA Search
 * Fetches complete history of a serial number in RMA
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';

header('Content-Type: application/json');

$serial = get_param('serial');

if (empty($serial)) {
    echo json_encode(['status' => false, 'message' => 'Serial number is required']);
    exit;
}

try {
    // Query RMA data with all relevant joins including replacement product
    $sql = "SELECT r.*, 
                   p.name as product_name, p.code as product_code,
                   ps.serial_number as old_serial_number,
                   new_ps.serial_number as new_serial_number,
                   new_p.name as new_product_name,
                   ext.product_name as ext_product_name,
                   ext.serial_number as ext_serial_number,
                   ext.brand_name as ext_brand_name
            FROM rma_requests r
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN product_serials ps ON r.serial_id = ps.id
            LEFT JOIN product_serials new_ps ON r.new_serial_id = new_ps.id
            LEFT JOIN products new_p ON new_ps.product_id = new_p.id
            LEFT JOIN rma_external_replacements ext ON r.id = ext.rma_id
            WHERE ps.serial_number = ? OR new_ps.serial_number = ? OR ext.serial_number = ? OR r.rma_number = ?
            ORDER BY r.id DESC LIMIT 1";
    
    $rma = db_query_one($sql, [$serial, $serial, $serial, $serial]);

    if (!$rma) {
        echo json_encode(['status' => false, 'message' => 'No RMA records found for this serial number.']);
        exit;
    }
    
    // Determine display values for replacement product
    // Priority: External replacement (from brand) > New serial (internal) > Original product
    $replacement_product_display = null;
    $replacement_serial_display = null;
    
    if ($rma['ext_product_name']) {
        // External replacement exists (from brand replacement)
        $replacement_product_display = $rma['ext_product_name'];
        if ($rma['ext_brand_name']) {
            $replacement_product_display .= ' (' . $rma['ext_brand_name'] . ')';
        }
        $replacement_serial_display = $rma['ext_serial_number'];
    } elseif ($rma['new_product_name']) {
        // Internal replacement (new serial from inventory)
        $replacement_product_display = $rma['new_product_name'];
        $replacement_serial_display = $rma['new_serial_number'];
    }

    $data = [
        'complain' => [
            'rma_number' => $rma['rma_number'],
            'created_date' => $rma['created_date'],
            'product_name' => $rma['product_name'],
            'serial_number' => $rma['old_serial_number']
        ],
        'testing_date' => $rma['testing_date'],
        'testing_note' => $rma['testing_note'],
        'replace_out_number' => $rma['replace_out_number'],
        'replace_out_date' => $rma['replace_out_date'],
        'replace_in_number' => $rma['replace_in_number'],
        'replace_in_date' => $rma['replace_in_date'],
        'replace_in_type' => $rma['replace_in_type'],
        'new_serial_number' => $replacement_serial_display,
        'new_product_name' => $replacement_product_display,
        'delivery_number' => $rma['delivery_number'],
        'delivery_date' => $rma['delivery_date']
    ];

    echo json_encode(['status' => true, 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'API Error: ' . $e->getMessage()]);
}
