-- ============================================
-- Create Product Serials Table for Warranty Tracking
-- ============================================
CREATE TABLE IF NOT EXISTS `product_serials` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `serial_number` varchar(100) DEFAULT NULL,
    `imei` varchar(50) DEFAULT NULL,
    `purchase_id` int(11) DEFAULT NULL,
    `sale_id` int(11) DEFAULT NULL,
    `sale_item_id` int(11) DEFAULT NULL,
    `warranty_months` int(11) DEFAULT 0,
    `purchase_date` date DEFAULT NULL,
    `sale_date` date DEFAULT NULL,
    `status` enum('available', 'sold', 'returned', 'damaged') DEFAULT 'available',
    `notes` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `serial_number` (`serial_number`),
    KEY `product_id` (`product_id`),
    KEY `purchase_id` (`purchase_id`),
    KEY `sale_id` (`sale_id`),
    KEY `sale_item_id` (`sale_item_id`),
    KEY `status` (`status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;