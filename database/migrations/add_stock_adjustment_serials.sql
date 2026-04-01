-- Migration: Add stock_adjustment_serials table
-- This table links stock adjustment entries to specific product serial numbers
-- for proper tracking and audit trail
CREATE TABLE IF NOT EXISTS `stock_adjustment_serials` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `adjustment_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `serial_id` INT(11) NOT NULL,
    `serial_number` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_adjustment_id` (`adjustment_id`),
    KEY `idx_serial_id` (`serial_id`),
    KEY `idx_product_id` (`product_id`),
    CONSTRAINT `fk_stock_adj_serials_adjustment` FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stock_adj_serials_serial` FOREIGN KEY (`serial_id`) REFERENCES `product_serials` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stock_adj_serials_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;