<?php
/**
 * Export Products to CSV
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Fetch products
$sql = "SELECT p.*, 
        c.name as category_name, 
        b.name as brand_name,
        u.short_name as unit_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN units u ON p.unit_id = u.id
        ORDER BY p.id ASC";

$products = db_query($sql);

$filename = "products_export_" . date('Y-m-d_H-i-s') . ".csv";

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Set CSV header row
fputcsv($output, [
    'Code', 
    'Name', 
    'Category', 
    'Brand', 
    'Unit', 
    'Purchase Price', 
    'Selling Price', 
    'Tax Rate', 
    'Stock', 
    'Reorder Level', 
    'Status', 
    'Description'
]);

foreach ($products as $product) {
    fputcsv($output, [
        $product['code'],
        $product['name'],
        $product['category_name'] ?? '',
        $product['brand_name'] ?? '',
        $product['unit_name'] ?? '',
        $product['purchase_price'],
        $product['selling_price'],
        $product['tax_rate'],
        $product['stock_quantity'],
        $product['reorder_level'],
        $product['status'],
        $product['description']
    ]);
}

fclose($output);
exit;
