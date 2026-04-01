-- Invoice Settings Table
-- Stores customizable invoice design and configuration
CREATE TABLE IF NOT EXISTS `invoice_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `company_name` varchar(255) DEFAULT NULL,
    `company_address` text,
    `company_phone` varchar(50) DEFAULT NULL,
    `company_email` varchar(100) DEFAULT NULL,
    `company_website` varchar(100) DEFAULT NULL,
    `company_logo` varchar(255) DEFAULT NULL,
    `tax_number` varchar(100) DEFAULT NULL,
    `invoice_prefix` varchar(20) DEFAULT 'INV-',
    `invoice_number_digits` int(11) DEFAULT 6,
    `show_logo` tinyint(1) DEFAULT 1,
    `show_company_info` tinyint(1) DEFAULT 1,
    `show_customer_info` tinyint(1) DEFAULT 1,
    `show_payment_info` tinyint(1) DEFAULT 1,
    `show_terms` tinyint(1) DEFAULT 1,
    `terms_and_conditions` text,
    `invoice_note` text,
    `header_color` varchar(20) DEFAULT '#4e73df',
    `text_color` varchar(20) DEFAULT '#000000',
    `paper_size` enum('A4', 'Letter') DEFAULT 'A4',
    `show_qr_code` tinyint(1) DEFAULT 0,
    `show_barcode` tinyint(1) DEFAULT 1,
    `footer_text` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Insert default settings
INSERT INTO `invoice_settings` (
        `company_name`,
        `company_address`,
        `company_phone`,
        `company_email`,
        `invoice_prefix`,
        `terms_and_conditions`,
        `invoice_note`,
        `footer_text`
    )
VALUES (
        'Your Business Name',
        'Your Business Address\nCity, State, ZIP',
        '+1234567890',
        'info@yourbusiness.com',
        'INV-',
        'Payment is due within 15 days\nPlease make checks payable to: Your Business Name',
        'Thank you for your business!',
        'This is a computer generated invoice'
    );