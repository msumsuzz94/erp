-- Migration: Add damaged_stock_serials table
-- This table links damaged stock entries to specific product serial numbers
CREATE TABLE IF NOT EXISTS `damaged_stock_serials` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `damaged_stock_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `serial_id` INT(11) NOT NULL,
    `serial_number` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_damaged_stock_id` (`damaged_stock_id`),
    KEY `idx_serial_id` (`serial_id`),
    KEY `idx_product_id` (`product_id`),
    CONSTRAINT `fk_damaged_stock_serials_damaged` FOREIGN KEY (`damaged_stock_id`) REFERENCES `damaged_stock` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_damaged_stock_serials_serial` FOREIGN KEY (`serial_id`) REFERENCES `product_serials` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_damaged_stock_serials_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;