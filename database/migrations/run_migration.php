<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'business_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);

    $sql = "
    -- Add rma_quantity to products table
    ALTER TABLE products ADD COLUMN rma_quantity DECIMAL(10,2) DEFAULT 0.00 AFTER stock_quantity;

    -- Update stock_transfers table transfer_type enum
    ALTER TABLE stock_transfers MODIFY COLUMN transfer_type ENUM('normal', 'damaged_to_good', 'return_to_good', 'product_to_rma', 'rma_to_product') DEFAULT 'normal';
    ";

    $conn->exec($sql);
    echo "Migration successful!\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
