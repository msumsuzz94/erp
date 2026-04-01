-- Add account_type to bank_accounts
ALTER TABLE `bank_accounts`
ADD COLUMN `account_type` VARCHAR(50) DEFAULT 'bank'
AFTER `id`;
-- Update balance_transfers to support more types (remove ENUM constraint)
ALTER TABLE `balance_transfers`
MODIFY COLUMN `from_account_type` VARCHAR(50) NOT NULL;
ALTER TABLE `balance_transfers`
MODIFY COLUMN `to_account_type` VARCHAR(50) NOT NULL;
-- Update invoice_settings (optional cleanup if needed, but primary focus is types)