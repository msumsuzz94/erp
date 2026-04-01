-- Migration: Add account_id to purchase_payments table
-- This allows tracking which account was used for supplier payments
ALTER TABLE `purchase_payments`
ADD COLUMN `account_id` INT(11) NULL
AFTER `payment_method`,
    ADD KEY `idx_account_id` (`account_id`);