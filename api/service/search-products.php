<?php
/**
 * Search Products API for Service Parts
 * Returns JSON list of matching products with stock info
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';

// Manual session check (bypass require_login URL permission check)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$products = db_query(
    "SELECT id, name, code, selling_price, stock_quantity, has_serial FROM products WHERE (name LIKE ? OR code LIKE ?) AND is_service_product = 1 AND status = 'active' AND stock_quantity > 0 ORDER BY name LIMIT 15",
    ["%$q%", "%$q%"]
);

echo json_encode($products ?: []);
