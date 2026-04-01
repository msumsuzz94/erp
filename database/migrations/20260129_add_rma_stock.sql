-- Add rma_quantity to products table
ALTER TABLE products
ADD COLUMN rma_quantity DECIMAL(10, 2) DEFAULT 0.00
AFTER stock_quantity;
-- Update stock_transfers table transfer_type enum
ALTER TABLE stock_transfers
MODIFY COLUMN transfer_type ENUM(
        'normal',
        'damaged_to_good',
        'return_to_good',
        'product_to_rma',
        'rma_to_product'
    ) DEFAULT 'normal';