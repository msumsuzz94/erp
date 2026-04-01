-- Migration: Add stock_type column to product_serials table
-- Date: 2026-02-01
-- Description: Adds stock_type column to track whether serial is in current, RMA, or damaged stock
-- Add stock_type column if it doesn't exist
ALTER TABLE product_serials
ADD COLUMN IF NOT EXISTS stock_type VARCHAR(20) DEFAULT NULL
AFTER status;
-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_stock_type ON product_serials(stock_type);
-- Update existing serials to have 'current' stock_type if they are in_stock
UPDATE product_serials
SET stock_type = 'current'
WHERE status = 'in_stock'
    AND (
        stock_type IS NULL
        OR stock_type = ''
    );
-- Verify the changes
SELECT 'Migration complete - stock_type column added' AS message;
SELECT COUNT(*) as total_serials,
    stock_type,
    status
FROM product_serials
GROUP BY stock_type,
    status;