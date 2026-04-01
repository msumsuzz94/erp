-- Create business_settings table
CREATE TABLE IF NOT EXISTS `business_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_name` varchar(255) NOT NULL,
  `business_phone` varchar(50) DEFAULT NULL,
  `business_email` varchar(255) DEFAULT NULL,
  `business_address` text,
  `currency` varchar(10) DEFAULT '$',
  `invoice_prefix` varchar(20) DEFAULT 'INV-',
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `business_logo` varchar(255) DEFAULT NULL,
  `display_in_menu` enum('name','logo') DEFAULT 'name',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `business_settings` (`id`, `business_name`, `display_in_menu`) 
VALUES (1, 'My Business', 'name')
ON DUPLICATE KEY UPDATE id=id;
