-- Migration: Add description column to quotation_items
ALTER TABLE `quotation_items`
ADD COLUMN `description` TEXT DEFAULT NULL
AFTER `product_id`;