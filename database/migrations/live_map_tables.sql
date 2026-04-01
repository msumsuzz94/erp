-- =============================================
-- Live Map Feature - Database Migration
-- 4 new tables for GPS tracking system
-- =============================================

SET NAMES 'utf8mb4';

-- 1. staff_locations: কর্মীর GPS ডাটা স্টোর
CREATE TABLE IF NOT EXISTS `staff_locations` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NOT NULL,
    `latitude` DECIMAL(10,8) NOT NULL,
    `longitude` DECIMAL(11,8) NOT NULL,
    `accuracy` FLOAT DEFAULT NULL COMMENT 'GPS accuracy in meters',
    `battery_level` INT DEFAULT NULL COMMENT 'Battery percentage 0-100',
    `is_charging` TINYINT(1) DEFAULT 0,
    `speed` FLOAT DEFAULT NULL COMMENT 'Speed in km/h',
    `recorded_at` DATETIME NOT NULL COMMENT 'Original timestamp from device',
    `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Server receive time',
    `device_info` VARCHAR(255) DEFAULT NULL,
    INDEX `idx_staff_time` (`staff_id`, `recorded_at`),
    INDEX `idx_recorded` (`recorded_at`),
    INDEX `idx_staff_synced` (`staff_id`, `synced_at`),
    FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. geofences: জিওফেন্স এলাকা
CREATE TABLE IF NOT EXISTS `geofences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `latitude` DECIMAL(10,8) NOT NULL,
    `longitude` DECIMAL(11,8) NOT NULL,
    `radius` INT NOT NULL DEFAULT 50 COMMENT 'Radius in meters',
    `type` ENUM('client','office','zone') DEFAULT 'client',
    `address` VARCHAR(500) DEFAULT NULL,
    `contact_person` VARCHAR(255) DEFAULT NULL,
    `contact_phone` VARCHAR(20) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. geofence_visits: কর্মীর ভিজিট রেকর্ড
CREATE TABLE IF NOT EXISTS `geofence_visits` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NOT NULL,
    `geofence_id` INT NOT NULL,
    `entered_at` DATETIME NOT NULL,
    `exited_at` DATETIME DEFAULT NULL,
    `duration_minutes` INT DEFAULT NULL COMMENT 'Auto-calculated on exit',
    INDEX `idx_staff_geo` (`staff_id`, `geofence_id`),
    INDEX `idx_entered` (`entered_at`),
    INDEX `idx_staff_date` (`staff_id`, `entered_at`),
    FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`geofence_id`) REFERENCES `geofences`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. livemap_settings: কনফিগারেশন
CREATE TABLE IF NOT EXISTS `livemap_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default settings
INSERT IGNORE INTO `livemap_settings` (`setting_key`, `setting_value`) VALUES
('tracking_interval_seconds', '30'),
('map_provider', 'leaflet'),
('map_default_lat', '23.8103'),
('map_default_lng', '90.4125'),
('map_default_zoom', '13'),
('auto_report_enabled', '0'),
('auto_report_frequency', 'daily'),
('auto_report_time', '00:00'),
('auto_report_emails', ''),
('data_retention_days', '90'),
('stale_threshold_minutes', '15');
