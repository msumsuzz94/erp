-- Migration to add 'rma' status to product_serials enum
-- Date: 2026-02-02
-- Description: Adds 'rma' status to differentiate RMA stock from damaged stock
-- Add 'rma' to the status enum
ALTER TABLE `product_serials`
MODIFY COLUMN `status` ENUM(
        'in_stock',
        'sold',
        'returned',
        'defective',
        'rma'
    ) NOT NULL DEFAULT 'in_stock';
-- Add stock_type field if it doesn't exist (safe migration)
ALTER TABLE `product_serials`
ADD COLUMN IF NOT EXISTS `stock_type` ENUM('current', 'rma', 'damaged') DEFAULT 'current'
AFTER `status`;
-- Update existing rows: set stock_type based on current status
UPDATE `product_serials`
SET `stock_type` = CASE
        WHEN `status` IN ('in_stock', 'sold', 'returned') THEN 'current'
        WHEN `status` = 'defective' THEN 'damaged'
        WHEN `status` = 'rma' THEN 'rma'
        ELSE 'current'
    END
WHERE `stock_type` IS NULL
    OR `stock_type` = '';
-- Add index for stock_type for better query performance
ALTER TABLE `product_serials`
ADD INDEX `idx_stock_type` (`stock_type`);