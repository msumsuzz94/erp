-- ========================================================
-- ERPPOS Migration Script - March 2026 Updates
-- Run this on cPanel phpMyAdmin BEFORE uploading files
-- ========================================================
-- ---------------------------------------------------
-- 1. Quotation Tax Rate (if not already exists)
-- ---------------------------------------------------
ALTER TABLE `quotations`
ADD COLUMN IF NOT EXISTS `tax_rate` DECIMAL(5, 2) DEFAULT 0.00
AFTER `total_amount`;
-- ---------------------------------------------------
-- 2. License Table - Ensure exists with all columns
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_license` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `license_key` varchar(100) NOT NULL,
    `domain` varchar(255) DEFAULT NULL,
    `status` enum('active', 'inactive', 'suspended', 'expired') DEFAULT 'inactive',
    `activated_at` datetime DEFAULT NULL,
    `last_verified_at` datetime DEFAULT NULL,
    `expiry_date` date DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Insert default license record if not exists
INSERT INTO `app_license` (
        `id`,
        `license_key`,
        `status`,
        `domain`,
        `expiry_date`,
        `activated_at`,
        `last_verified_at`
    )
VALUES (
        1,
        'MASTER-CITN-ERPP-2026',
        'active',
        'localhost',
        '2099-12-31',
        NOW(),
        NOW()
    ) ON DUPLICATE KEY
UPDATE `license_key` = 'MASTER-CITN-ERPP-2026',
    `status` = 'active',
    `expiry_date` = '2099-12-31',
    `last_verified_at` = NOW();
-- ---------------------------------------------------
-- 3. Backup Tables - Ensure exists  
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `backup_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `local_backup_enabled` tinyint(1) DEFAULT 1,
    `backup_frequency` enum('daily', 'weekly', 'monthly') DEFAULT 'daily',
    `backup_time` time DEFAULT '02:00:00',
    `compression_enabled` tinyint(1) DEFAULT 1,
    `backup_path` varchar(255) DEFAULT '/backups/database/',
    `gdrive_enabled` tinyint(1) DEFAULT 0,
    `gdrive_token` text DEFAULT NULL,
    `gdrive_folder_id` varchar(255) DEFAULT NULL,
    `onedrive_enabled` tinyint(1) DEFAULT 0,
    `onedrive_token` text DEFAULT NULL,
    `retention_days` int(11) DEFAULT 30,
    `auto_backup_enabled` tinyint(1) DEFAULT 1,
    `last_backup_at` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- Insert default if not exists
INSERT INTO `backup_settings` (`id`, `local_backup_enabled`, `retention_days`)
VALUES (1, 1, 30) ON DUPLICATE KEY
UPDATE `id` = `id`;
CREATE TABLE IF NOT EXISTS `backup_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `filename` varchar(255) NOT NULL,
    `file_size` int(11) DEFAULT 0,
    `backup_type` enum('manual', 'automatic') DEFAULT 'manual',
    `tables_count` int(11) DEFAULT 0,
    `records_count` int(11) DEFAULT 0,
    `status` enum('success', 'failed') DEFAULT 'success',
    `error_message` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS `import_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `filename` varchar(255) NOT NULL,
    `import_mode` varchar(50) DEFAULT 'skip_duplicate',
    `total_imported` int(11) DEFAULT 0,
    `total_skipped` int(11) DEFAULT 0,
    `total_failed` int(11) DEFAULT 0,
    `status` enum('success', 'failed', 'partial') DEFAULT 'success',
    `error_message` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ---------------------------------------------------
-- 4. License Menu Item (if not exists)
-- ---------------------------------------------------
INSERT INTO `menu_items` (
        `name`,
        `slug`,
        `icon`,
        `url`,
        `parent_id`,
        `sort_order`,
        `is_active`
    )
SELECT 'License Management',
    'settings.license',
    'fas fa-key',
    '/modules/settings/license-manage.php',
    15,
    11,
    1
WHERE NOT EXISTS (
        SELECT 1
        FROM `menu_items`
        WHERE `slug` = 'settings.license'
-- ---------------------------------------------------
-- 5. Role & Permission Safety Checks
-- ---------------------------------------------------

-- Ensure Super Admin role (ID 1) exists
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`)
VALUES (1, 'Super Admin', 'Full system access with all permissions', NOW())
ON DUPLICATE KEY UPDATE `name` = 'Super Admin';

-- Ensure Super Admin has permission for the new License Management menu
INSERT INTO `role_permissions` (`role_id`, `menu_id`)
SELECT 1, id FROM `menu_items` WHERE `slug` = 'settings.license'
AND NOT EXISTS (
    SELECT 1 FROM `role_permissions` rp 
    INNER JOIN `menu_items` mi ON rp.menu_id = mi.id 
    WHERE rp.role_id = 1 AND mi.slug = 'settings.license'
);
-- ========================================================
-- DONE! Now upload the modified PHP/CSS files to cPanel
-- ========================================================