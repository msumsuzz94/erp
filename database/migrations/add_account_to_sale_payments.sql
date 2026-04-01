-- Migration: Add account_id to sale_payments table
-- This allows tracking which account received bill collection payments
ALTER TABLE `sale_payments`
ADD COLUMN `account_id` INT(11) NULL
AFTER `payment_method`,
    ADD KEY `idx_account_id` (`account_id`);