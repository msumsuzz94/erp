-- Create Departments Table
CREATE TABLE IF NOT EXISTS `staff_departments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Insert default departments
INSERT IGNORE INTO `staff_departments` (`name`, `description`)
VALUES (
        'Administration',
        'General administration and management'
    ),
    ('Sales', 'Sales and business development'),
    ('Marketing', 'Marketing and promotion'),
    ('Accounts', 'Finance and accounting'),
    ('Human Resources', 'HR and personnel management'),
    ('IT', 'Information Technology and systems'),
    ('Operations', 'Daily operations and logistics');
-- Create Staff Documents Table
CREATE TABLE IF NOT EXISTS `staff_documents` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `staff_id` int(11) NOT NULL,
    `document_type` varchar(50) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `file_name` varchar(100) NOT NULL,
    `upload_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `staff_id` (`staff_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Create Staff Table (if not exists) or Update it
-- Since we can't easily use "ADD COLUMN IF NOT EXISTS" in MariaDB/MySQL easily in one go without procedures,
-- we'll create the structure assuming it might be missing.
CREATE TABLE IF NOT EXISTS `staff` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `email` varchar(100) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `address` text,
    `designation` varchar(100) DEFAULT NULL,
    `role_id` int(11) DEFAULT NULL,
    `joining_date` date DEFAULT NULL,
    `salary` decimal(10, 2) DEFAULT 0.00,
    `status` enum('active', 'inactive') DEFAULT 'active',
    `photo` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Add new columns individually (using a procedure to avoid errors if they exist)
DROP PROCEDURE IF EXISTS AddStaffColumns;
DELIMITER // CREATE PROCEDURE AddStaffColumns() BEGIN -- Personal Information
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'employee_id'
) THEN
ALTER TABLE `staff`
ADD COLUMN `employee_id` varchar(50) DEFAULT NULL
AFTER `id`;
ALTER TABLE `staff`
ADD UNIQUE KEY `employee_id` (`employee_id`);
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'department_id'
) THEN
ALTER TABLE `staff`
ADD COLUMN `department_id` int(11) DEFAULT NULL
AFTER `designation`;
-- We won't add constraint immediately to avoid issues with existing data, but good to have index
ALTER TABLE `staff`
ADD INDEX `department_id` (`department_id`);
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'father_name'
) THEN
ALTER TABLE `staff`
ADD COLUMN `father_name` varchar(100) DEFAULT NULL
AFTER `name`;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'mother_name'
) THEN
ALTER TABLE `staff`
ADD COLUMN `mother_name` varchar(100) DEFAULT NULL
AFTER `father_name`;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'date_of_birth'
) THEN
ALTER TABLE `staff`
ADD COLUMN `date_of_birth` date DEFAULT NULL
AFTER `joining_date`;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'gender'
) THEN
ALTER TABLE `staff`
ADD COLUMN `gender` enum('male', 'female', 'other') DEFAULT NULL
AFTER `date_of_birth`;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'marital_status'
) THEN
ALTER TABLE `staff`
ADD COLUMN `marital_status` enum('single', 'married', 'divorced', 'widowed') DEFAULT NULL
AFTER `gender`;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'blood_group'
) THEN
ALTER TABLE `staff`
ADD COLUMN `blood_group` varchar(5) DEFAULT NULL
AFTER `marital_status`;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'nid_number'
) THEN
ALTER TABLE `staff`
ADD COLUMN `nid_number` varchar(50) DEFAULT NULL
AFTER `blood_group`;
END IF;
-- Employment Details
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'job_type'
) THEN
ALTER TABLE `staff`
ADD COLUMN `job_type` enum('permanent', 'contract', 'probation', 'intern') DEFAULT 'permanent'
AFTER `status`;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'contract_end_date'
) THEN
ALTER TABLE `staff`
ADD COLUMN `contract_end_date` date DEFAULT NULL
AFTER `joining_date`;
END IF;
-- Banking Info
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'bank_name'
) THEN
ALTER TABLE `staff`
ADD COLUMN `bank_name` varchar(100) DEFAULT NULL;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'bank_branch'
) THEN
ALTER TABLE `staff`
ADD COLUMN `bank_branch` varchar(100) DEFAULT NULL;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'bank_account_name'
) THEN
ALTER TABLE `staff`
ADD COLUMN `bank_account_name` varchar(100) DEFAULT NULL;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'bank_account_number'
) THEN
ALTER TABLE `staff`
ADD COLUMN `bank_account_number` varchar(50) DEFAULT NULL;
END IF;
-- Emergency Contact
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'emergency_contact_name'
) THEN
ALTER TABLE `staff`
ADD COLUMN `emergency_contact_name` varchar(100) DEFAULT NULL;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'emergency_contact_phone'
) THEN
ALTER TABLE `staff`
ADD COLUMN `emergency_contact_phone` varchar(20) DEFAULT NULL;
END IF;
IF NOT EXISTS(
    SELECT *
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'staff'
        AND COLUMN_NAME = 'emergency_contact_relation'
) THEN
ALTER TABLE `staff`
ADD COLUMN `emergency_contact_relation` varchar(50) DEFAULT NULL;
END IF;
END // DELIMITER;
CALL AddStaffColumns();
DROP PROCEDURE AddStaffColumns;