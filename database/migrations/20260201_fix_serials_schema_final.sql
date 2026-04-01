-- Migration: Fix product_serials schema and status values
-- Date: 2026-02-01
-- 1. Add serial_status column if it doesn't exist
ALTER TABLE product_serials
ADD COLUMN IF NOT EXISTS serial_status VARCHAR(50) DEFAULT 'in_stock'
AFTER status;
-- 2. Modify status column to be a standard VARCHAR instead of a restrictive ENUM
-- This avoids truncation and makes it easier to work with
ALTER TABLE product_serials
MODIFY COLUMN status VARCHAR(20) DEFAULT 'in_stock';
-- 3. Update existing 'in_stoc' values to 'in_stock'
UPDATE product_serials
SET status = 'in_stock'
WHERE status = 'in_stoc'
    OR status = ''
    OR status IS NULL;
-- 4. Set default serial_status for existing records
UPDATE product_serials
SET serial_status = 'in_stock'
WHERE serial_status IS NULL
    OR serial_status = '';
-- 5. Ensure stock_type is set for testing
UPDATE product_serials
SET stock_type = 'current'
WHERE product_id = 12
    AND status = 'in_stock'
    AND (
        stock_type IS NULL
        OR stock_type = ''
    );
-- Verify
SELECT COLUMN_NAME,
    COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_NAME = 'product_serials'
    AND COLUMN_NAME IN ('status', 'serial_status', 'stock_type');
SELECT id,
    serial_number,
    status,
    serial_status,
    stock_type
FROM product_serials
WHERE product_id = 12
LIMIT 5;