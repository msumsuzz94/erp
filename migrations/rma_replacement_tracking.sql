-- RMA Replacement Product Tracking Migration
-- This migration adds support for tracking replacement products
-- that can be different from the original product
-- Add new columns to track replacement product separately
ALTER TABLE rma_requests
ADD COLUMN IF NOT EXISTS replacement_product_id INT NULL
AFTER new_serial_id,
    ADD COLUMN IF NOT EXISTS replacement_serial_number VARCHAR(255) NULL
AFTER replacement_product_id;
-- Add foreign key constraint for replacement product
-- First check if constraint exists
SET @constraint_exists = (
        SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'rma_requests'
            AND CONSTRAINT_NAME = 'fk_rma_replacement_product'
    );
SET @sql = IF(
        @constraint_exists = 0,
        'ALTER TABLE rma_requests ADD CONSTRAINT fk_rma_replacement_product FOREIGN KEY (replacement_product_id) REFERENCES products(id) ON DELETE SET NULL',
        'SELECT "Constraint already exists" AS message'
    );
PREPARE stmt
FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS idx_replacement_product ON rma_requests(replacement_product_id);
CREATE INDEX IF NOT EXISTS idx_replacement_serial ON rma_requests(replacement_serial_number);