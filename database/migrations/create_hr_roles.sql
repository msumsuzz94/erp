-- ============================================
-- HR Management - Staff Roles Migration
-- Creates staff_roles table and modifies staff table
-- ============================================

-- Create staff_roles table
CREATE TABLE IF NOT EXISTS `staff_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add role_id to staff table (check if column doesn't exist first)
SET @dbname = DATABASE();
SET @tablename = 'staff';
SET @columnname = 'role_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE staff ADD COLUMN role_id int(11) DEFAULT NULL AFTER designation, ADD FOREIGN KEY (role_id) REFERENCES staff_roles(id) ON DELETE SET NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Insert default roles
INSERT INTO `staff_roles` (`role_name`, `description`) VALUES
('Manager', 'Department or team manager'),
('Supervisor', 'Team supervisor'),
('Sales Representative', 'Sales and customer service'),
('Accountant', 'Finance and accounting'),
('Cashier', 'POS and cash handling'),
('Warehouse Staff', 'Inventory and warehouse management'),
('Delivery Person', 'Product delivery'),
('IT Support', 'Technical support'),
('General Staff', 'General purpose staff member')
ON DUPLICATE KEY UPDATE description=VALUES(description);
