-- Create invoice_settings table
CREATE TABLE IF NOT EXISTS `invoice_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_name` varchar(255) DEFAULT NULL,
  `business_address` text,
  `business_phone` varchar(50) DEFAULT NULL,
  `business_email` varchar(255) DEFAULT NULL,
  `business_tax_no` varchar(100) DEFAULT NULL,
  `business_logo` varchar(255) DEFAULT NULL,
  `invoice_prefix` varchar(20) DEFAULT 'INV-',
  `invoice_number_length` int(11) DEFAULT 6,
  `default_tax_rate` decimal(5,2) DEFAULT 0.00,
  `invoice_footer_text` text,
  `invoice_signature` varchar(255) DEFAULT NULL,
  `show_logo_on_invoice` tinyint(1) DEFAULT 0,
  `show_signature_on_invoice` tinyint(1) DEFAULT 0,
  `invoice_template` varchar(50) DEFAULT 'classic',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `invoice_settings` (`id`, `business_name`, `business_address`, `business_phone`, `business_email`, `invoice_footer_text`) 
VALUES (1, 'My Business', '', '', '', 'Thank you for your business!');
