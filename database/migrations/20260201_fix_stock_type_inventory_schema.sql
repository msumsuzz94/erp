-- Add warehouse_id to stock_type_inventory table
ALTER TABLE stock_type_inventory
ADD COLUMN warehouse_id INT NULL
AFTER product_id;
ALTER TABLE stock_type_inventory
ADD INDEX (warehouse_id);
-- Update existing records to use the default warehouse if known, or leave as NULL
-- Since we only have one record for product 12, and it came from warehouse 1
UPDATE stock_type_inventory
SET warehouse_id = 1
WHERE product_id = 12;