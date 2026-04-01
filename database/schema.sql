-- ============================================
-- Business Management System - Database Schema
-- Version: 1.0
-- ============================================
-- Set charset
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- ============================================
-- 1. USERS & AUTHENTICATION
-- ============================================
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `module` (`module`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) DEFAULT NULL,
  `menu_item_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `permission_id` (`permission_id`),
  KEY `menu_item_id` (`menu_item_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 2,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `attempted_at` (`attempted_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 2. SETTINGS
-- ============================================
CREATE TABLE IF NOT EXISTS `business_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_name` varchar(255) NOT NULL,
  `business_phone` varchar(50) DEFAULT NULL,
  `business_email` varchar(255) DEFAULT NULL,
  `business_address` text,
  `currency` varchar(10) DEFAULT '৳',
  `invoice_prefix` varchar(20) DEFAULT 'INV-',
  `tax_rate` decimal(5, 2) DEFAULT 0.00,
  `business_logo` varchar(255) DEFAULT NULL,
  `display_in_menu` enum('name', 'logo') DEFAULT 'name',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `invoice_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) DEFAULT NULL,
  `company_slogan` varchar(255) DEFAULT NULL,
  `company_address` text,
  `company_phone` varchar(50) DEFAULT NULL,
  `company_email` varchar(100) DEFAULT NULL,
  `company_website` varchar(100) DEFAULT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `invoice_prefix` varchar(20) DEFAULT 'INV-',
  `invoice_number_digits` int(11) DEFAULT 6,
  `show_logo` tinyint(1) DEFAULT 1,
  `show_slogan` tinyint(1) DEFAULT 1,
  `show_company_info` tinyint(1) DEFAULT 1,
  `show_customer_info` tinyint(1) DEFAULT 1,
  `show_payment_info` tinyint(1) DEFAULT 1,
  `show_terms` tinyint(1) DEFAULT 1,
  `show_amount_in_words` tinyint(1) DEFAULT 1,
  `amount_in_words_prefix` varchar(20) DEFAULT 'BDT',
  `terms_and_conditions` text,
  `invoice_note` text,
  `default_tax_rate` decimal(5, 2) DEFAULT 0.00,
  `invoice_template` varchar(50) DEFAULT 'professional',
  `header_color` varchar(20) DEFAULT '#4e73df',
  `text_color` varchar(20) DEFAULT '#000000',
  `paper_size` enum('A4', 'Letter') DEFAULT 'A4',
  `show_qr_code` tinyint(1) DEFAULT 0,
  `show_barcode` tinyint(1) DEFAULT 1,
  `footer_text` text,
  `invoice_signature` varchar(255) DEFAULT NULL,
  `show_signature_on_invoice` tinyint(1) DEFAULT 1,
  `author_signature_label` varchar(100) DEFAULT 'Author signature',
  `good_received_text` text,
  `show_print_time` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 3. CUSTOMERS & SUPPLIERS
-- ============================================
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `credit_limit` decimal(15, 2) DEFAULT 0.00,
  `opening_balance` decimal(15, 2) DEFAULT 0.00,
  `current_balance` decimal(15, 2) DEFAULT 0.00,
  `status` enum('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `phone` (`phone`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `opening_balance` decimal(15, 2) DEFAULT 0.00,
  `current_balance` decimal(15, 2) DEFAULT 0.00,
  `payment_terms` varchar(100) DEFAULT NULL,
  `status` enum('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `customer_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL COMMENT 'sale, payment, return, opening_balance',
  `reference_id` int(11) DEFAULT NULL COMMENT 'sale_id or payment_id',
  `debit` decimal(15, 2) DEFAULT 0.00,
  `credit` decimal(15, 2) DEFAULT 0.00,
  `balance` decimal(15, 2) DEFAULT 0.00,
  `description` text,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `date` (`date`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `supplier_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL COMMENT 'purchase, payment, return, opening_balance',
  `reference_id` int(11) DEFAULT NULL,
  `debit` decimal(15, 2) DEFAULT 0.00,
  `credit` decimal(15, 2) DEFAULT 0.00,
  `balance` decimal(15, 2) DEFAULT 0.00,
  `description` text,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `date` (`date`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 3. PRODUCTS
-- ============================================
CREATE TABLE IF NOT EXISTS `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `short_name` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `purchase_price` decimal(15, 2) DEFAULT 0.00,
  `selling_price` decimal(15, 2) NOT NULL,
  `tax_rate` decimal(5, 2) DEFAULT 0.00,
  `image` varchar(255) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 0,
  `description` text,
  `status` enum('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `barcode` (`barcode`),
  KEY `brand_id` (`brand_id`),
  KEY `category_id` (`category_id`),
  KEY `unit_id` (`unit_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE
  SET NULL,
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE
  SET NULL,
    FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variant_name` varchar(100) NOT NULL COMMENT 'RAM, Storage, Color',
  `variant_value` varchar(100) NOT NULL COMMENT '8GB, 128GB, Black',
  `sku` varchar(100) DEFAULT NULL,
  `price_adjustment` decimal(15, 2) DEFAULT 0.00 COMMENT 'Additional price',
  `stock_quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `sku` (`sku`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `product_serials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `sale_item_id` int(11) DEFAULT NULL,
  `warranty_months` int(11) DEFAULT 12,
  `purchase_date` date DEFAULT NULL,
  `sale_date` date DEFAULT NULL,
  `status` enum('in_stock', 'sold', 'returned', 'defective') NOT NULL DEFAULT 'in_stock',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  UNIQUE KEY `imei` (`imei`),
  KEY `product_id` (`product_id`),
  KEY `status` (`status`),
  KEY `idx_sale_item_id` (`sale_item_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `adjustment_type` enum('add', 'subtract', 'opening_stock') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` text,
  `date` date NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `date` (`date`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `damaged_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `stock_type` enum('damaged', 'dead') NOT NULL,
  `reason` text NOT NULL,
  `cost_value` decimal(15, 2) DEFAULT 0.00,
  `date` date NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`),
  KEY `stock_type` (`stock_type`),
  KEY `date` (`date`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `stock_adjustment_serials` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `adjustment_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `serial_id` INT(11) NOT NULL,
  `serial_number` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_adjustment_id` (`adjustment_id`),
  KEY `idx_serial_id` (`serial_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `fk_stock_adj_serials_adjustment` FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_adj_serials_serial` FOREIGN KEY (`serial_id`) REFERENCES `product_serials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_adj_serials_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 4. PURCHASES
-- ============================================
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_date` date NOT NULL,
  `total_amount` decimal(15, 2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15, 2) DEFAULT 0.00,
  `discount` decimal(15, 2) DEFAULT 0.00,
  `paid_amount` decimal(15, 2) DEFAULT 0.00,
  `due_amount` decimal(15, 2) DEFAULT 0.00,
  `payment_status` enum('paid', 'partial', 'unpaid') NOT NULL DEFAULT 'unpaid',
  `status` enum('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_number` (`purchase_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `purchase_date` (`purchase_date`),
  KEY `status` (`status`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15, 2) NOT NULL,
  `tax` decimal(15, 2) DEFAULT 0.00,
  `subtotal` decimal(15, 2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `product_id` (`product_id`),
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `purchase_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15, 2) NOT NULL,
  `payment_method` enum('cash', 'bank', 'credit', 'card', 'mobile_money') NOT NULL,
  `account_id` INT(11) NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_by` INT(11) NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_created_by` (`created_by`),
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `purchase_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `total_amount` decimal(15, 2) NOT NULL,
  `reason` text,
  `status` enum('pending', 'completed') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `supplier_id` (`supplier_id`),
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `purchase_return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15, 2) NOT NULL,
  `subtotal` decimal(15, 2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `return_id` (`return_id`),
  KEY `product_id` (`product_id`),
  FOREIGN KEY (`return_id`) REFERENCES `purchase_returns` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `bill_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15, 2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_payment_date` (`payment_date`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 5. SALES
-- ============================================
CREATE TABLE IF NOT EXISTS `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `sale_date` date NOT NULL,
  `total_amount` decimal(15, 2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15, 2) DEFAULT 0.00,
  `discount` decimal(15, 2) DEFAULT 0.00,
  `paid_amount` decimal(15, 2) DEFAULT 0.00,
  `due_amount` decimal(15, 2) DEFAULT 0.00,
  `payment_status` enum('paid', 'partial', 'unpaid') NOT NULL DEFAULT 'unpaid',
  `status` enum('draft', 'completed', 'cancelled') NOT NULL DEFAULT 'completed',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `customer_id` (`customer_id`),
  KEY `sale_date` (`sale_date`),
  KEY `status` (`status`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE
  SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15, 2) NOT NULL,
  `tax` decimal(15, 2) DEFAULT 0.00,
  `discount` decimal(15, 2) DEFAULT 0.00,
  `subtotal` decimal(15, 2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `sale_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15, 2) NOT NULL,
  `payment_method` enum('cash', 'bank', 'credit', 'card', 'mobile_money') NOT NULL,
  `account_id` INT(11) NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `sales_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `return_date` date NOT NULL,
  `total_amount` decimal(15, 2) NOT NULL,
  `reason` text,
  `status` enum('pending', 'completed') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `customer_id` (`customer_id`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE
  SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `sale_return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15, 2) NOT NULL,
  `subtotal` decimal(15, 2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `return_id` (`return_id`),
  KEY `product_id` (`product_id`),
  FOREIGN KEY (`return_id`) REFERENCES `sales_returns` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 6. QUOTATIONS
-- ============================================
CREATE TABLE IF NOT EXISTS `quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `quotation_date` date NOT NULL,
  `expiration_date` date DEFAULT NULL,
  `total_amount` decimal(15, 2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15, 2) DEFAULT 0.00,
  `discount` decimal(15, 2) DEFAULT 0.00,
  `status` enum('pending', 'accepted', 'rejected', 'converted') NOT NULL DEFAULT 'pending',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `customer_id` (`customer_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE
  SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15, 2) NOT NULL,
  `tax` decimal(15, 2) DEFAULT 0.00,
  `discount` decimal(15, 2) DEFAULT 0.00,
  `subtotal` decimal(15, 2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`),
  KEY `product_id` (`product_id`),
  FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 7. EXPENSES
-- ============================================
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_number` varchar(50) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `amount` decimal(15, 2) NOT NULL,
  `payment_method` enum('cash', 'bank', 'card') NOT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `approval_status` enum('pending', 'approved', 'rejected') DEFAULT 'approved',
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `expense_date` (`expense_date`),
  FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 8. WARRANTY & RMA
-- ============================================
CREATE TABLE IF NOT EXISTS `service_centers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `rma_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rma_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `problem_description` text NOT NULL,
  `status` enum('pending', 'sent', 'solved', 'delivered') NOT NULL DEFAULT 'pending',
  `service_center_id` int(11) DEFAULT NULL,
  `new_serial_id` int(11) DEFAULT NULL,
  `testing_date` date DEFAULT NULL,
  `testing_note` text,
  `replace_out_number` varchar(50) DEFAULT NULL,
  `replace_out_date` date DEFAULT NULL,
  `replace_in_number` varchar(50) DEFAULT NULL,
  `replace_in_date` date DEFAULT NULL,
  `replace_in_type` enum('repair', 'new') DEFAULT 'repair',
  `delivery_number` varchar(50) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rma_number` (`rma_number`),
  KEY `customer_id` (`customer_id`),
  KEY `product_id` (`product_id`),
  KEY `status` (`status`),
  KEY `new_serial_id` (`new_serial_id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE
  SET NULL,
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    FOREIGN KEY (`service_center_id`) REFERENCES `service_centers` (`id`) ON DELETE
  SET NULL,
    FOREIGN KEY (`new_serial_id`) REFERENCES `product_serials` (`id`) ON DELETE
  SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 9. ACCOUNTS
-- ============================================
CREATE TABLE IF NOT EXISTS `cash_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `opening_balance` decimal(15, 2) DEFAULT 0.00,
  `current_balance` decimal(15, 2) DEFAULT 0.00,
  `status` enum('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `cash_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('debit', 'credit') NOT NULL,
  `amount` decimal(15, 2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'sale, purchase, expense',
  `reference_id` int(11) DEFAULT NULL,
  `description` text,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  KEY `transaction_date` (`transaction_date`),
  FOREIGN KEY (`account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(255) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `branch` varchar(255) DEFAULT NULL,
  `opening_balance` decimal(15, 2) DEFAULT 0.00,
  `current_balance` decimal(15, 2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('debit', 'credit') NOT NULL,
  `amount` decimal(15, 2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  KEY `transaction_date` (`transaction_date`),
  FOREIGN KEY (`account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `balance_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_account_type` enum('cash', 'bank') NOT NULL,
  `from_account_id` int(11) NOT NULL,
  `to_account_type` enum('cash', 'bank') NOT NULL,
  `to_account_id` int(11) NOT NULL,
  `amount` decimal(15, 2) NOT NULL,
  `transfer_date` date NOT NULL,
  `description` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `transfer_date` (`transfer_date`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `party_cash` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('assign', 'return') NOT NULL,
  `amount` decimal(15, 2) NOT NULL,
  `description` text,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `cash_closings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `closing_date` date NOT NULL,
  `expected_cash` decimal(15, 2) NOT NULL,
  `actual_cash` decimal(15, 2) NOT NULL,
  `variance` decimal(15, 2) NOT NULL,
  `notes` text,
  `closed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `closing_date` (`closing_date`),
  FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 10. HR MANAGEMENT
-- ============================================
CREATE TABLE IF NOT EXISTS `staff_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `joining_date` date DEFAULT NULL,
  `status` enum('active', 'inactive') NOT NULL DEFAULT 'active',
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  FOREIGN KEY (`role_id`) REFERENCES `staff_roles` (`id`) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `salaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `basic_salary` decimal(15, 2) NOT NULL,
  `allowances` decimal(15, 2) DEFAULT 0.00,
  `deductions` decimal(15, 2) DEFAULT 0.00,
  `net_salary` decimal(15, 2) NOT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('pending', 'paid') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present', 'absent', 'leave', 'half_day') NOT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `date` (`date`),
  FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ============================================
-- 11. INITIAL DATA
-- ==========================================
-- Insert default roles
INSERT INTO `roles` (`id`, `name`, `description`)
VALUES (1, 'Super Admin', 'Full access to all modules'),
  (2, 'Manager', 'Manages daily operations'),
  (3, 'Cashier', 'POS and sales operations'),
  (
    4,
    'Sales Representative',
    'Sales and quotations'
  ),
  (5, 'Accountant', 'Financial operations');
-- Insert default admin user (password: admin123)
INSERT INTO `users` (
    `username`,
    `email`,
    `password_hash`,
    `role_id`,
    `status`
  )
VALUES (
    'admin',
    'admin@business.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    1,
    'active'
  );
-- Insert default units
INSERT INTO `units` (`name`, `short_name`)
VALUES ('Piece', 'Pcs'),
  ('Box', 'Box'),
  ('Carton', 'Ctn'),
  ('Kilogram', 'Kg'),
  ('Liter', 'L');
-- Insert default expense categories
INSERT INTO `expense_categories` (`name`, `description`)
VALUES ('Rent', 'Office/Shop rent'),
  ('Utilities', 'Electricity, water, internet'),
  ('Salaries', 'Employee salaries'),
  ('Marketing', 'Advertising and promotion'),
  ('Transportation', 'Delivery and transport costs'),
  ('Maintenance', 'Repairs and maintenance'),
  ('Miscellaneous', 'Other expenses');
-- Insert default cash account
INSERT INTO `cash_accounts` (
    `account_name`,
    `opening_balance`,
    `current_balance`
  )
VALUES ('Main Cash', 0.00, 0.00);
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================
-- END OF SCHEMA
-- ============================================