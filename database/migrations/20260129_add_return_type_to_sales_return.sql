-- Migration: Add return_type to sale_return_items
ALTER TABLE `sale_return_items`
ADD COLUMN `return_type` ENUM('stock', 'rma') NOT NULL DEFAULT 'stock'
AFTER `serial_id`;