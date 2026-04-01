<?php
/**
 * Run Damaged Stock Table Migration
 * Execute this file once to create the damaged_stock table
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "Creating damaged_stock table...\n";

    $sql = "CREATE TABLE IF NOT EXISTS `damaged_stock` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `product_id` INT(11) NOT NULL,
      `variant_id` INT(11) DEFAULT NULL,
      `quantity` INT(11) NOT NULL,
      `stock_type` ENUM('damaged', 'dead') NOT NULL COMMENT 'damaged=repairable, dead=unsellable',
      `reason` TEXT NOT NULL,
      `cost_value` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Financial impact',
      `date` DATE NOT NULL,
      `user_id` INT(11) DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `product_id` (`product_id`),
      KEY `variant_id` (`variant_id`),
      KEY `stock_type` (`stock_type`),
      KEY `date` (`date`),
      KEY `user_id` (`user_id`),
      FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->exec($sql);
    echo "✓ Table 'damaged_stock' created successfully\n\n";

    // Add indexes
    echo "Adding indexes...\n";

    try {
        $conn->exec("CREATE INDEX idx_damaged_stock_product_date ON damaged_stock(product_id, date)");
        echo "✓ Index idx_damaged_stock_product_date created\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "- Index idx_damaged_stock_product_date already exists\n";
        } else {
            throw $e;
        }
    }

    try {
        $conn->exec("CREATE INDEX idx_damaged_stock_type ON damaged_stock(stock_type, date)");
        echo "✓ Index idx_damaged_stock_type created\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "- Index idx_damaged_stock_type already exists\n";
        } else {
            throw $e;
        }
    }

    echo "\n✓ Migration completed successfully!\n";
    echo "You can now use the Damaged/Dead Stock feature.\n";

} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
