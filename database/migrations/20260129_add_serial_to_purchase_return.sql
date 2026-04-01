-- Migration: Add serial_id to purchase_return_items
ALTER TABLE `purchase_return_items`
ADD COLUMN `serial_id` INT(11) NULL DEFAULT NULL
AFTER `variant_id`;
-- Add index and foreign key
ALTER TABLE `purchase_return_items`
ADD INDEX `idx_serial_id` (`serial_id`);
ALTER TABLE `purchase_return_items`
ADD FOREIGN KEY (`serial_id`) REFERENCES `product_serials` (`id`) ON DELETE
SET NULL;