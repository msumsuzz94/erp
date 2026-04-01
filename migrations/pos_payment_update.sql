-- Add account_id to sale_payments
ALTER TABLE `sale_payments`
ADD COLUMN `account_id` INT NULL
AFTER `payment_method`;
-- Change payment_method to VARCHAR to support generic types
ALTER TABLE `sale_payments`
MODIFY COLUMN `payment_method` VARCHAR(50) DEFAULT 'cash';
-- Also update sales table payment_method if it exists as ENUM (it was VARCHAR in code but let's be safe)
ALTER TABLE `sales`
MODIFY COLUMN `payment_method` VARCHAR(50) DEFAULT 'cash';