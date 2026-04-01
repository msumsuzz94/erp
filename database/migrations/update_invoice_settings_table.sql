-- Migration: Add custom fields to invoice_settings for professional design
-- This script adds missing columns and ensures consistency between modules
ALTER TABLE `invoice_settings`
ADD COLUMN IF NOT EXISTS `company_slogan` varchar(255) DEFAULT NULL
AFTER `company_name`,
    ADD COLUMN IF NOT EXISTS `author_signature_label` varchar(100) DEFAULT 'Author signature'
AFTER `invoice_signature`,
    ADD COLUMN IF NOT EXISTS `good_received_text` text DEFAULT NULL
AFTER `author_signature_label`,
    ADD COLUMN IF NOT EXISTS `show_amount_in_words` tinyint(1) DEFAULT 1
AFTER `show_terms`,
    ADD COLUMN IF NOT EXISTS `amount_in_words_prefix` varchar(20) DEFAULT 'BDT'
AFTER `show_amount_in_words`,
    ADD COLUMN IF NOT EXISTS `show_slogan` tinyint(1) DEFAULT 1
AFTER `show_logo`;
-- Ensure we have a default record if not exists
INSERT IGNORE INTO `invoice_settings` (
        id,
        company_name,
        company_address,
        company_phone,
        company_email,
        good_received_text
    )
VALUES (
        1,
        'Your Business Name',
        'Your Business Address',
        '+1234567890',
        'info@yourbusiness.com',
        'Good received by customer in good condition.'
    );