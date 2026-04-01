-- Paid Service Module Tables
-- Created: 2026-02-28

-- Main service tickets table
CREATE TABLE IF NOT EXISTS `service_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(20) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `device_type` varchar(50) NOT NULL DEFAULT 'Laptop',
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `warranty_status` varchar(50) DEFAULT 'Out of Warranty',
  `problem_description` text NOT NULL,
  `physical_condition` text DEFAULT NULL,
  `estimated_delivery_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `service_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_parts_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` varchar(20) NOT NULL DEFAULT 'Unpaid',
  `payment_method` varchar(30) DEFAULT NULL,
  `payment_account_id` int(11) DEFAULT NULL,
  `technician_notes` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `status` (`status`),
  KEY `customer_phone` (`customer_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parts consumed per service ticket
CREATE TABLE IF NOT EXISTS `service_ticket_parts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  CONSTRAINT `fk_stp_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `service_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Status change history log
CREATE TABLE IF NOT EXISTS `service_status_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  CONSTRAINT `fk_ssl_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `service_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
