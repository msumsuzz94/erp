-- Add sale_item_id to product_serials table for better tracking
-- This links each serial to the specific line item in the sale
ALTER TABLE product_serials
ADD COLUMN sale_item_id INT(11) DEFAULT NULL
AFTER sale_id,
    ADD KEY sale_item_id (sale_item_id);
-- Note: We're not adding a foreign key constraint because sale_item_id can be NULL
-- and we want flexibility in case items are deleted