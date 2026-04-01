-- Fix serial data for testing stock transfer
-- Link MSE-AX23-* serials to Mouse Logitech M170 (product_id=12) with correct stock type
-- First, check current state
SELECT 'Current state of MSE-AX23 serials:' as info;
SELECT id,
    product_id,
    serial_number,
    status,
    IFNULL(stock_type, 'NULL') as stock_type
FROM product_serials
WHERE serial_number LIKE 'MSE%';
-- Update all MSE-AX23 serials to:
-- 1. Link to product_id = 12 (Mouse Logitech M170)
-- 2. Set stock_type = 'current'
-- 3. Ensure status = 'in_stock'
UPDATE product_serials
SET product_id = 12,
    stock_type = 'current',
    status = 'in_stock'
WHERE serial_number LIKE 'MSE-AX23%';
-- Verify the update
SELECT 'After update:' as info;
SELECT id,
    product_id,
    serial_number,
    status,
    stock_type
FROM product_serials
WHERE serial_number LIKE 'MSE%';
-- Test the API query directly
SELECT 'API test query (product_id=12, stock_type=current):' as info;
SELECT id,
    serial_number,
    status,
    stock_type
FROM product_serials
WHERE product_id = 12
    AND status = 'in_stock'
    AND (
        stock_type IS NULL
        OR stock_type = ''
        OR stock_type = 'current'
    )
ORDER BY serial_number ASC;