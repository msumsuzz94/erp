-- Stock Transfer Enhancement Migration
-- Add support for RMA and Damaged stock transfers with serial tracking
-- Run Date: 2026-01-31
-- Step 1: Add serial_numbers column to stock_transfer_items
ALTER TABLE stock_transfer_items
ADD COLUMN IF NOT EXISTS serial_numbers TEXT COMMENT 'JSON array of serial numbers transferred'
AFTER notes;
-- Step 2: Update transfer_type ENUM to new values
ALTER TABLE stock_transfers
MODIFY COLUMN transfer_type ENUM(
        'current_to_rma',
        'rma_to_current',
        'rma_to_damaged',
        'damaged_to_rma'
    ) NOT NULL DEFAULT 'current_to_rma';
-- Step 3: Create stock_type_inventory table for RMA and Damaged tracking
CREATE TABLE IF NOT EXISTS stock_type_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    stock_type ENUM('rma', 'damaged') NOT NULL,
    quantity DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_product_type (product_id, stock_type),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_type (product_id, stock_type)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = 'Tracks RMA and Damaged stock quantities per product';
-- Step 4: Add stock_type to product_serials table
ALTER TABLE product_serials
ADD COLUMN IF NOT EXISTS stock_type ENUM('current', 'rma', 'damaged') DEFAULT 'current' COMMENT 'Current location/status of this serial number'
AFTER serial_status;
-- Step 5: Initialize stock_type_inventory with existing data
-- Migrate existing RMA quantities from products table
INSERT INTO stock_type_inventory (product_id, stock_type, quantity)
SELECT id,
    'rma',
    rma_quantity
FROM products
WHERE rma_quantity > 0 ON DUPLICATE KEY
UPDATE quantity =
VALUES(quantity);
-- Migrate existing damaged quantities from damaged_stock table
INSERT INTO stock_type_inventory (product_id, stock_type, quantity)
SELECT product_id,
    'damaged',
    SUM(quantity)
FROM damaged_stock
GROUP BY product_id ON DUPLICATE KEY
UPDATE quantity = quantity +
VALUES(quantity);
-- Step 6: Update existing product_serials to set stock_type based on status
UPDATE product_serials
SET stock_type = CASE
        WHEN serial_status IN ('sold', 'available', 'reserved') THEN 'current'
        WHEN serial_status = 'rma' THEN 'rma'
        WHEN serial_status IN ('damaged', 'defective') THEN 'damaged'
        ELSE 'current'
    END
WHERE stock_type IS NULL
    OR stock_type = 'current';
-- Step 7: Make warehouse IDs nullable to support stock-type transfers
-- This allows transfers to work in two modes:
-- 1. Warehouse mode: from_warehouse_id and to_warehouse_id are populated
-- 2. Stock-type mode: warehouse IDs are NULL, stock types used instead
ALTER TABLE stock_transfers
MODIFY COLUMN from_warehouse_id INT(11) NULL,
    MODIFY COLUMN to_warehouse_id INT(11) NULL;
-- Step 8: Update foreign key constraints to allow NULL
-- Drop existing constraints
ALTER TABLE stock_transfers DROP FOREIGN KEY stock_transfers_ibfk_1;
ALTER TABLE stock_transfers DROP FOREIGN KEY stock_transfers_ibfk_2;
-- Recreate with NULL support
ALTER TABLE stock_transfers
ADD CONSTRAINT stock_transfers_ibfk_1 FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
    ADD CONSTRAINT stock_transfers_ibfk_2 FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT;
-- Verification queries (comment out after running)
-- SELECT 'Stock Type Inventory Count' as info, COUNT(*) as count FROM stock_type_inventory;
-- SELECT 'Products with RMA' as info, COUNT(*) as count FROM products WHERE rma_quantity > 0;
-- SELECT 'Serial Stock Type Distribution' as info, stock_type, COUNT(*) as count FROM product_serials GROUP BY stock_type;