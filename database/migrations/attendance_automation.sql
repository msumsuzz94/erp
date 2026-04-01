-- =====================================================
-- Automatic Attendance System Migration
-- Version: 1.0
-- Date: 2026-01-31
-- Description: Add fingerprint and card punching support
-- =====================================================
-- 1. Create attendance_devices table
CREATE TABLE IF NOT EXISTS `attendance_devices` (
    `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
    `device_name` VARCHAR(100) NOT NULL,
    `device_type` ENUM('fingerprint', 'card', 'face') NOT NULL,
    `device_ip` VARCHAR(50) DEFAULT NULL COMMENT 'IP address for network devices',
    `device_port` INT(5) DEFAULT NULL COMMENT 'Port number if applicable',
    `device_sn` VARCHAR(100) UNIQUE COMMENT 'Serial number',
    `api_key` VARCHAR(255) DEFAULT NULL COMMENT 'Authentication key for this device',
    `location` VARCHAR(255) DEFAULT NULL COMMENT 'Physical location (e.g., Main Gate, Office Entrance)',
    `status` ENUM('active', 'inactive', 'error') DEFAULT 'active',
    `last_heartbeat` DATETIME DEFAULT NULL COMMENT 'Last communication from device',
    `settings` TEXT COMMENT 'JSON configuration for device-specific settings',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_device_sn` (`device_sn`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 2. Create attendance_logs table
CREATE TABLE IF NOT EXISTS `attendance_logs` (
    `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
    `staff_id` INT(11) NOT NULL,
    `device_id` INT(11) NOT NULL,
    `punch_time` DATETIME NOT NULL,
    `punch_type` ENUM('in', 'out', 'break_out', 'break_in') DEFAULT 'in',
    `verification_method` ENUM(
        'fingerprint',
        'card',
        'face',
        'password',
        'manual'
    ) NOT NULL,
    `verification_data` VARCHAR(255) DEFAULT NULL COMMENT 'Card ID or fingerprint template ID',
    `temperature` DECIMAL(4, 1) DEFAULT NULL COMMENT 'Body temperature if device supports',
    `photo_path` VARCHAR(255) DEFAULT NULL COMMENT 'Photo captured during punch',
    `status` ENUM('valid', 'invalid', 'duplicate', 'manual') DEFAULT 'valid',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`device_id`) REFERENCES `attendance_devices`(`id`) ON DELETE CASCADE,
    INDEX `idx_staff_date` (`staff_id`, `punch_time`),
    INDEX `idx_device_time` (`device_id`, `punch_time`),
    INDEX `idx_punch_time` (`punch_time`),
    INDEX `idx_status` (`status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 3. Modify staff table to add device enrollment fields
ALTER TABLE `staff`
ADD COLUMN `fingerprint_template` TEXT DEFAULT NULL COMMENT 'Fingerprint biometric data (JSON array of templates)',
    ADD COLUMN `card_id` VARCHAR(50) DEFAULT NULL COMMENT 'RFID/Card number',
    ADD COLUMN `face_template` TEXT DEFAULT NULL COMMENT 'Face recognition data',
    ADD COLUMN `enrolled_at` DATETIME DEFAULT NULL COMMENT 'Device enrollment date',
    ADD UNIQUE INDEX `idx_card_id` (`card_id`);
-- 4. Create attendance_summary table for daily consolidated records
CREATE TABLE IF NOT EXISTS `attendance_summary` (
    `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
    `staff_id` INT(11) NOT NULL,
    `date` DATE NOT NULL,
    `first_in` TIME DEFAULT NULL COMMENT 'First check-in time',
    `last_out` TIME DEFAULT NULL COMMENT 'Last check-out time',
    `total_hours` DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Total working hours',
    `is_late` TINYINT(1) DEFAULT 0 COMMENT '1 if arrived late',
    `is_early_leave` TINYINT(1) DEFAULT 0 COMMENT '1 if left early',
    `overtime_hours` DECIMAL(5, 2) DEFAULT 0.00,
    `status` ENUM(
        'present',
        'absent',
        'leave',
        'half_day',
        'holiday'
    ) DEFAULT 'present',
    `source` ENUM('automatic', 'manual', 'imported') DEFAULT 'automatic',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_staff_date` (`staff_id`, `date`),
    INDEX `idx_date` (`date`),
    INDEX `idx_status` (`status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 5. Create device_heartbeat_log table for monitoring
CREATE TABLE IF NOT EXISTS `device_heartbeat_log` (
    `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
    `device_id` INT(11) NOT NULL,
    `heartbeat_time` DATETIME NOT NULL,
    `status` ENUM('online', 'offline', 'error') DEFAULT 'online',
    `error_message` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`device_id`) REFERENCES `attendance_devices`(`id`) ON DELETE CASCADE,
    INDEX `idx_device_time` (`device_id`, `heartbeat_time`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 6. Insert default sample device for testing
INSERT INTO `attendance_devices` (
        `device_name`,
        `device_type`,
        `device_sn`,
        `api_key`,
        `location`,
        `status`
    )
VALUES (
        'Main Gate Scanner',
        'fingerprint',
        'DEMO001',
        MD5(CONCAT('device_', NOW())),
        'Main Entrance',
        'active'
    ),
    (
        'Office Card Reader',
        'card',
        'DEMO002',
        MD5(CONCAT('device_', NOW(), '2')),
        'Office Floor 1',
        'active'
    );
-- =====================================================
-- Migration Complete
-- =====================================================