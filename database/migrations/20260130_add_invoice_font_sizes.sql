-- Migration: Add font size settings for company name and slogan
ALTER TABLE `invoice_settings`
ADD COLUMN IF NOT EXISTS `company_name_font_size` INT DEFAULT 28
AFTER `company_name`,
    ADD COLUMN IF NOT EXISTS `company_slogan_font_size` INT DEFAULT 14
AFTER `company_slogan`;
-- Update existing record with defaults if they are NULL
UPDATE `invoice_settings`
SET `company_name_font_size` = 28
WHERE `company_name_font_size` IS NULL;
UPDATE `invoice_settings`
SET `company_slogan_font_size` = 14
WHERE `company_slogan_font_size` IS NULL;