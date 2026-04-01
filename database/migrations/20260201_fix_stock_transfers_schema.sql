-- Migration: Fix stock_transfers schema to support Stock Type transfers
-- Date: 2026-02-01
-- 1. Make warehouse IDs nullable (required for transfers between stock types)
ALTER TABLE stock_transfers
MODIFY COLUMN from_warehouse_id INT NULL,
    MODIFY COLUMN to_warehouse_id INT NULL;
-- 2. Update transfer_type enum to include 'normal' (for warehouse transfers)
ALTER TABLE stock_transfers
MODIFY COLUMN transfer_type ENUM(
        'normal',
        'current_to_rma',
        'rma_to_current',
        'rma_to_damaged',
        'damaged_to_rma'
    ) DEFAULT 'normal';
-- 3. Verify changes
SELECT COLUMN_NAME,
    IS_NULLABLE,
    COLUMN_TYPE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_NAME = 'stock_transfers'
    AND COLUMN_NAME IN (
        'from_warehouse_id',
        'to_warehouse_id',
        'transfer_type'
    );