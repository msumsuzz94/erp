<?php
/**
 * Fix stale stock_type_inventory data for product 129
 * Run once, then delete this file.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db_functions.php';

$product_id = 129;

echo "<pre>";
echo "BEFORE:\n";
$sti = db_query("SELECT id, stock_type, quantity FROM stock_type_inventory WHERE product_id = ?", [$product_id]);
print_r($sti);
$prod = db_query_one("SELECT stock_quantity, rma_quantity FROM products WHERE id = ?", [$product_id]);
print_r($prod);

// Fix: since all serials are 'current', zero out damaged and rma in stock_type_inventory
db_query("UPDATE stock_type_inventory SET quantity = 0 WHERE product_id = ? AND stock_type IN ('damaged','rma')", [$product_id]);

// Fix: zero out rma_quantity in products table
db_query("UPDATE products SET rma_quantity = 0 WHERE id = ?", [$product_id]);

echo "\nAFTER FIX:\n";
$sti2 = db_query("SELECT id, stock_type, quantity FROM stock_type_inventory WHERE product_id = ?", [$product_id]);
print_r($sti2);
$prod2 = db_query_one("SELECT stock_quantity, rma_quantity FROM products WHERE id = ?", [$product_id]);
print_r($prod2);

echo "\nDone! Please delete this file.\n";
echo "</pre>";
