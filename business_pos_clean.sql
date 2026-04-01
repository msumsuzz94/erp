-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 13, 2026 at 07:35 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `business_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_license`
--

CREATE TABLE `app_license` (
  `id` int(11) NOT NULL,
  `license_key` varchar(100) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended','expired') DEFAULT 'inactive',
  `activated_at` datetime DEFAULT NULL,
  `last_verified_at` datetime DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_license`
--

INSERT INTO `app_license` (`id`, `license_key`, `domain`, `status`, `activated_at`, `last_verified_at`, `expiry_date`, `created_at`) VALUES
(1, 'MASTER-CITN-ERPP-2026', 'localhost', 'active', '2026-03-13 12:10:27', '2026-03-13 12:10:27', '2099-12-31', '2026-01-30 16:19:00');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `segment_name` varchar(50) DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','leave','half_day','late') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `time_in_2` time DEFAULT NULL,
  `time_out_2` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_devices`
--

CREATE TABLE `attendance_devices` (
  `id` int(11) NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `device_type` enum('fingerprint','card','face') NOT NULL,
  `device_ip` varchar(50) DEFAULT NULL COMMENT 'IP address for network devices',
  `device_port` int(5) DEFAULT NULL COMMENT 'Port number if applicable',
  `device_sn` varchar(100) DEFAULT NULL COMMENT 'Serial number',
  `api_key` varchar(255) DEFAULT NULL COMMENT 'Authentication key for this device',
  `location` varchar(255) DEFAULT NULL COMMENT 'Physical location (e.g., Main Gate, Office Entrance)',
  `status` enum('active','inactive','error') DEFAULT 'active',
  `last_heartbeat` datetime DEFAULT NULL COMMENT 'Last communication from device',
  `settings` text DEFAULT NULL COMMENT 'JSON configuration for device-specific settings',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `punch_time` datetime NOT NULL,
  `punch_type` enum('in','out','out_duty','return_duty') NOT NULL DEFAULT 'in',
  `verification_method` enum('fingerprint','card','face','password','manual') NOT NULL,
  `verification_data` varchar(255) DEFAULT NULL COMMENT 'Card ID or fingerprint template ID',
  `temperature` decimal(4,1) DEFAULT NULL COMMENT 'Body temperature if device supports',
  `photo_path` varchar(255) DEFAULT NULL COMMENT 'Photo captured during punch',
  `status` enum('valid','invalid','duplicate','manual') DEFAULT 'valid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gps_latitude` decimal(10,8) DEFAULT NULL,
  `gps_longitude` decimal(11,8) DEFAULT NULL,
  `face_confidence` decimal(5,4) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL COMMENT 'Browser/device user agent',
  `source` varchar(30) DEFAULT 'manual' COMMENT 'manual/web_biometric/web_face/webcam_kiosk/machine/cctv'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance_logs`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance_settings`
--

CREATE TABLE `attendance_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_group` varchar(50) DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_settings`
--

INSERT INTO `attendance_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `description`, `updated_at`) VALUES
(1, 'gps_enabled', '1', 'mobile', 'Enable GPS verification for mobile check-in', '2026-03-05 05:41:23'),
(2, 'gps_latitude', '0', 'mobile', 'Office latitude', '2026-03-10 11:30:55'),
(3, 'gps_longitude', '0', 'mobile', 'Office longitude', '2026-03-10 11:30:55'),
(4, 'gps_radius_meters', '100', 'mobile', 'Allowed radius in meters', '2026-03-05 05:41:23'),
(5, 'face_confidence_threshold', '0.5', 'face', 'Minimum face match confidence (0-1)', '2026-03-05 05:41:23'),
(6, 'face_detection_enabled', '1', 'face', 'Enable face detection for attendance', '2026-03-05 05:41:23'),
(7, 'webauthn_enabled', '1', 'webauthn', 'Enable WebAuthn biometric', '2026-03-05 05:41:23'),
(8, 'kiosk_pin', '1234', 'kiosk', 'PIN to exit kiosk mode', '2026-03-05 05:41:23'),
(9, 'kiosk_welcome_sound', '1', 'kiosk', 'Play sound on successful check-in', '2026-03-05 05:41:23'),
(10, 'cctv_away_timeout', '5', 'cctv', 'Minutes before marking staff away', '2026-03-05 05:41:23'),
(11, 'cctv_python_url', 'http://localhost:5050', 'cctv', 'Python CCTV middleware URL', '2026-03-05 05:41:23'),
(12, 'late_notify_enabled', '1', 'notification', 'Send notification on late arrival', '2026-03-05 05:41:23'),
(13, 'absent_notify_enabled', '1', 'notification', 'Send notification on absence', '2026-03-05 05:41:23'),
(14, 'away_notify_enabled', '1', 'notification', 'Send notification when staff goes away via CCTV', '2026-03-05 05:41:23'),
(15, 'max_checkins_per_day', '0', 'general', 'Maximum allowed check-ins per day (0 = unlimited)', '2026-03-05 05:52:04'),
(16, 'allow_web_face', '1', 'general', 'Allow Face Register Check-in', '2026-03-05 05:52:04'),
(17, 'allow_web_biometric', '0', 'general', 'Allow WebAuthn (Fingerprint) Check-in', '2026-03-09 16:27:53'),
(18, 'allow_manual', '0', 'general', 'Allow Admin manual attendance assignment', '2026-03-05 08:53:12'),
(19, 'monthly_working_days', '30', 'general', NULL, '2026-03-09 16:27:25');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_summary`
--

CREATE TABLE `attendance_summary` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `first_in` time DEFAULT NULL COMMENT 'First check-in time',
  `last_out` time DEFAULT NULL COMMENT 'Last check-out time',
  `total_hours` decimal(5,2) DEFAULT 0.00 COMMENT 'Total working hours',
  `is_late` tinyint(1) DEFAULT 0 COMMENT '1 if arrived late',
  `is_early_leave` tinyint(1) DEFAULT 0 COMMENT '1 if left early',
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `status` enum('present','absent','leave','half_day','holiday') DEFAULT 'present',
  `source` enum('automatic','manual','imported') DEFAULT 'automatic',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_history`
--

CREATE TABLE `backup_history` (
  `id` int(11) NOT NULL,
  `backup_name` varchar(255) NOT NULL,
  `backup_type` enum('manual','automatic') DEFAULT 'manual',
  `backup_size` bigint(20) DEFAULT 0,
  `file_path` varchar(500) DEFAULT NULL,
  `destinations` text DEFAULT NULL COMMENT 'JSON array of destinations',
  `status` enum('success','failed','partial') DEFAULT 'success',
  `tables_backed_up` int(11) DEFAULT 0,
  `total_records` bigint(20) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `backup_history`
--

-- --------------------------------------------------------

--
-- Table structure for table `backup_settings`
--

CREATE TABLE `backup_settings` (
  `id` int(11) NOT NULL,
  `auto_backup_enabled` tinyint(1) DEFAULT 0,
  `backup_frequency` enum('daily','weekly','monthly') DEFAULT 'daily',
  `backup_time` time DEFAULT '02:00:00',
  `local_backup_enabled` tinyint(1) DEFAULT 1,
  `local_backup_path` varchar(500) DEFAULT '/backups/database/',
  `google_drive_enabled` tinyint(1) DEFAULT 0,
  `google_drive_folder_id` varchar(255) DEFAULT NULL,
  `google_drive_credentials` text DEFAULT NULL,
  `dropbox_enabled` tinyint(1) DEFAULT 0,
  `dropbox_access_token` text DEFAULT NULL,
  `retention_days` int(11) DEFAULT 30,
  `compress_backups` tinyint(1) DEFAULT 1,
  `last_backup_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `backup_settings`
--

INSERT INTO `backup_settings` (`id`, `auto_backup_enabled`, `backup_frequency`, `backup_time`, `local_backup_enabled`, `local_backup_path`, `google_drive_enabled`, `google_drive_folder_id`, `google_drive_credentials`, `dropbox_enabled`, `dropbox_access_token`, `retention_days`, `compress_backups`, `last_backup_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'daily', '11:54:48', 1, '/backups/database/', 0, NULL, NULL, 0, NULL, 30, 1, '2026-03-12 11:10:37', '2026-02-01 04:21:07', '2026-03-12 11:10:37');

-- --------------------------------------------------------

--
-- Table structure for table `balance_transfers`
--

CREATE TABLE `balance_transfers` (
  `id` int(11) NOT NULL,
  `from_account_type` varchar(50) NOT NULL,
  `from_account_id` int(11) NOT NULL,
  `to_account_type` varchar(50) NOT NULL,
  `to_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transfer_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `balance_transfers`
--

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
  `account_type` varchar(50) DEFAULT 'bank',
  `bank_name` varchar(255) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `branch` varchar(255) DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_accounts`
--

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

CREATE TABLE `bank_transactions` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('debit','credit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_transactions`
--

-- --------------------------------------------------------

--
-- Table structure for table `bill_collections`
--

CREATE TABLE `bill_collections` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_collections`
--

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brands`
--

-- --------------------------------------------------------

--
-- Table structure for table `business_settings`
--

CREATE TABLE `business_settings` (
  `id` int(11) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `business_phone` varchar(50) DEFAULT NULL,
  `business_email` varchar(255) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `currency` varchar(10) DEFAULT '$',
  `invoice_prefix` varchar(20) DEFAULT 'INV-',
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `business_logo` varchar(255) DEFAULT NULL,
  `display_in_menu` enum('name','logo') DEFAULT 'name',
  `footer_copyright_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `default_tax_rate` decimal(5,2) DEFAULT 0.00,
  `cache_clear_interval` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business_settings`
--

INSERT INTO `business_settings` (`id`, `business_name`, `business_phone`, `business_email`, `business_address`, `currency`, `invoice_prefix`, `tax_rate`, `business_logo`, `display_in_menu`, `footer_copyright_text`, `created_at`, `updated_at`, `default_tax_rate`, `cache_clear_interval`) VALUES
(1, 'CleansBuy Computer City CBCC', '+880 1750 79 2097', 'cleansbuycc@gmail.com', 'Sardah, Charghat, Rajshahi', '৳', 'NEXINV-', 10.00, 'uploads/business/logo_1770019218.jpg', 'name', 'Copyright @ CITNEX ERP & POS 2026 All right Reserved', '2026-01-26 21:37:46', '2026-03-12 10:28:37', 5.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `cash_accounts`
--

CREATE TABLE `cash_accounts` (
  `id` int(11) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_accounts`
--

-- --------------------------------------------------------

--
-- Table structure for table `cash_closings`
--

CREATE TABLE `cash_closings` (
  `id` int(11) NOT NULL,
  `closing_date` date NOT NULL,
  `closing_type` enum('daily','weekly','monthly') DEFAULT 'daily',
  `expected_cash` decimal(15,2) NOT NULL,
  `actual_cash` decimal(15,2) NOT NULL,
  `variance` decimal(15,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_closings`
--

-- --------------------------------------------------------

--
-- Table structure for table `cash_transactions`
--

CREATE TABLE `cash_transactions` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('debit','credit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'sale, purchase, expense',
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cash_transactions`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

-- --------------------------------------------------------

--
-- Table structure for table `cctv_configurations`
--

CREATE TABLE `cctv_configurations` (
  `id` int(11) NOT NULL,
  `camera_name` varchar(100) NOT NULL,
  `rtsp_url` varchar(500) NOT NULL,
  `track_function` enum('entry_tracking','away_tracking') NOT NULL DEFAULT 'entry_tracking',
  `location` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `away_timeout_minutes` int(11) DEFAULT 5 COMMENT 'Minutes before marking away',
  `last_status` varchar(50) DEFAULT 'offline' COMMENT 'online/offline/error',
  `last_check` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_tracking_logs`
--

CREATE TABLE `cctv_tracking_logs` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `camera_id` int(11) NOT NULL,
  `event_type` varchar(20) NOT NULL,
  `away_start_time` datetime DEFAULT NULL,
  `return_time` datetime DEFAULT NULL,
  `duration_minutes` decimal(10,2) DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `channel_settings`
--

CREATE TABLE `channel_settings` (
  `id` int(11) NOT NULL,
  `channel` varchar(20) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 0,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `channel_settings`
--

INSERT INTO `channel_settings` (`id`, `channel`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES
(1, 'sms', 1, '{\"api_url\":\"http:\\/\\/bulksmsbd.net\\/api\\/smsapi?api_key=VEP6ZZQL7jtVPWHtVjVn&type=text&number=Receiver&senderid=8809648905803&message=TestSMS\",\"api_key\":\"VEP6ZZQL7jtVPWHtVjVn\",\"sender_id\":\"8809648905803\"}', '2026-03-02 11:08:21', '2026-03-10 10:21:12'),
(2, 'email', 0, '{\"smtp_host\":\"\",\"smtp_port\":\"587\",\"smtp_user\":\"\",\"smtp_pass\":\"\",\"from_name\":\"\",\"from_email\":\"\"}', '2026-03-02 11:08:21', '2026-03-02 11:08:21'),
(3, 'whatsapp', 0, '{\"api_url\":\"\",\"api_key\":\"\",\"instance_id\":\"\",\"phone_number\":\"\"}', '2026-03-02 11:08:21', '2026-03-02 11:08:21'),
(4, 'telegram', 0, '{\"bot_token\":\"\",\"chat_id\":\"\",\"api_url\":\"https://api.telegram.org\"}', '2026-03-02 11:08:21', '2026-03-02 11:08:21');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `customer_group` varchar(20) DEFAULT 'Buyer',
  `credit_limit` decimal(15,2) DEFAULT 0.00,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

-- --------------------------------------------------------

--
-- Table structure for table `customer_ledger`
--

CREATE TABLE `customer_ledger` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL COMMENT 'sale, payment, return, opening_balance',
  `reference_id` int(11) DEFAULT NULL COMMENT 'sale_id or payment_id',
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_ledger`
--

-- --------------------------------------------------------

--
-- Table structure for table `damaged_stock`
--

CREATE TABLE `damaged_stock` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `stock_type` enum('damaged','dead') NOT NULL COMMENT 'damaged=repairable, dead=unsellable',
  `reason` text NOT NULL,
  `cost_value` decimal(15,2) DEFAULT 0.00 COMMENT 'Financial impact',
  `date` date NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `damaged_stock_serials`
--

CREATE TABLE `damaged_stock_serials` (
  `id` int(11) NOT NULL,
  `damaged_stock_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `device_heartbeat_log`
--

CREATE TABLE `device_heartbeat_log` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `heartbeat_time` datetime NOT NULL,
  `status` enum('online','offline','error') DEFAULT 'online',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `expense_number` varchar(50) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `account_type` enum('cash','bank') DEFAULT 'cash',
  `description` text NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank','card') NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_at` datetime DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`, `description`, `created_at`) VALUES
(6, 'Electricity Bill', '', '2026-02-10 20:35:26'),
(7, 'Marketing', '', '2026-02-10 20:35:36'),
(8, 'Salary', '', '2026-02-10 20:35:53'),
(9, 'House Rent', '', '2026-02-10 20:36:12'),
(10, 'Software Cost', '', '2026-02-10 20:36:39'),
(11, 'WiFi Bill', '', '2026-02-10 20:36:47'),
(12, 'Other Expenses', '', '2026-02-10 20:39:30');

-- --------------------------------------------------------

--
-- Table structure for table `import_history`
--

CREATE TABLE `import_history` (
  `id` int(11) NOT NULL,
  `import_file` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `total_records` int(11) DEFAULT 0,
  `imported_records` int(11) DEFAULT 0,
  `skipped_records` int(11) DEFAULT 0,
  `updated_records` int(11) DEFAULT 0,
  `failed_records` int(11) DEFAULT 0,
  `status` enum('success','failed','partial') DEFAULT 'success',
  `import_mode` enum('skip_duplicates','update_existing','merge_smart') DEFAULT 'skip_duplicates',
  `import_details` text DEFAULT NULL COMMENT 'JSON with per-table stats',
  `error_message` text DEFAULT NULL,
  `imported_by` int(11) DEFAULT NULL,
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `duration_seconds` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `import_history`
--

-- --------------------------------------------------------

--
-- Table structure for table `invoice_settings`
--

CREATE TABLE `invoice_settings` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_name_font_size` int(11) DEFAULT 28,
  `company_address` text DEFAULT NULL,
  `company_phone` varchar(50) DEFAULT NULL,
  `company_email` varchar(100) DEFAULT NULL,
  `company_website` varchar(100) DEFAULT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `invoice_prefix` varchar(20) DEFAULT 'INV-',
  `invoice_number_digits` int(11) DEFAULT 6,
  `invoice_format` varchar(50) DEFAULT 'format1',
  `default_tax_rate` decimal(10,2) DEFAULT 0.00,
  `show_logo` tinyint(1) DEFAULT 1,
  `show_company_info` tinyint(1) DEFAULT 1,
  `show_customer_info` tinyint(1) DEFAULT 1,
  `show_payment_info` tinyint(1) DEFAULT 1,
  `show_terms` tinyint(1) DEFAULT 1,
  `terms_and_conditions` text DEFAULT NULL,
  `invoice_note` text DEFAULT NULL,
  `header_color` varchar(20) DEFAULT '#4e73df',
  `text_color` varchar(20) DEFAULT '#000000',
  `invoice_template` varchar(50) DEFAULT 'professional',
  `paper_size` enum('A4','Letter') DEFAULT 'A4',
  `show_qr_code` tinyint(1) DEFAULT 0,
  `show_barcode` tinyint(1) DEFAULT 1,
  `footer_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `good_received_text` text DEFAULT NULL,
  `show_print_time` tinyint(1) DEFAULT 1,
  `software_developed_by` varchar(255) DEFAULT 'Software Developed By Shimul',
  `company_slogan` varchar(255) DEFAULT NULL,
  `company_slogan_font_size` int(11) DEFAULT 14,
  `show_slogan` tinyint(1) DEFAULT 1,
  `show_signature_on_invoice` tinyint(1) DEFAULT 1,
  `author_signature_label` varchar(100) DEFAULT 'Author signature',
  `show_amount_in_words` tinyint(1) DEFAULT 1,
  `amount_in_words_prefix` varchar(20) DEFAULT 'BDT',
  `invoice_signature` varchar(255) DEFAULT NULL,
  `font_size` enum('small','medium','large') DEFAULT 'medium',
  `show_print_datetime` tinyint(1) DEFAULT 1,
  `developed_by` varchar(255) DEFAULT 'CITNBD',
  `invoice_number_format` varchar(100) DEFAULT '{PREFIX}{NUMBER}',
  `template_style` varchar(20) DEFAULT 'classic',
  `company_slogan_color` varchar(20) DEFAULT '#E67E22',
  `invoice_margin` varchar(20) DEFAULT '1cm'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_settings`
--

INSERT INTO `invoice_settings` (`id`, `company_name`, `company_name_font_size`, `company_address`, `company_phone`, `company_email`, `company_website`, `company_logo`, `tax_number`, `invoice_prefix`, `invoice_number_digits`, `invoice_format`, `default_tax_rate`, `show_logo`, `show_company_info`, `show_customer_info`, `show_payment_info`, `show_terms`, `terms_and_conditions`, `invoice_note`, `header_color`, `text_color`, `invoice_template`, `paper_size`, `show_qr_code`, `show_barcode`, `footer_text`, `created_at`, `updated_at`, `good_received_text`, `show_print_time`, `software_developed_by`, `company_slogan`, `company_slogan_font_size`, `show_slogan`, `show_signature_on_invoice`, `author_signature_label`, `show_amount_in_words`, `amount_in_words_prefix`, `invoice_signature`, `font_size`, `show_print_datetime`, `developed_by`, `invoice_number_format`, `template_style`, `company_slogan_color`, `invoice_margin`) VALUES
(1, 'CleansBuy Computer City', 24, 'Sardah, Charghat, Rajshahi', '01750-792097', 'cleansbuycc@gmail.com', 'www.cleansbuy.com', 'uploads/settings/69805913cb277_1770019091.jpg', '98087', 'NEXINV-', 6, 'format1', 10.00, 1, 1, 1, 1, 1, 'The warranty will be void if the product is physically damaged, burned, misused, tampered with, or if the warranty sticker is removed. All sold goods are non-refundable and non-returnable. Thank you for your purchase. Have a great day..', 'Thank you for your business!', '#2563eb', '#000000', 'professional', 'A4', 0, 1, 'Invoice Footer Text', '2026-01-26 22:35:05', '2026-03-12 10:28:37', 'Good received by customer in good condition.', 1, 'CITNBD | 01976-793351', 'Trouble Free Computer Solution', 16, 1, 1, 'Authorized Signature', 1, 'BDT', '', 'medium', 1, 'CITNBD', '{PREFIX}-{YY}-{MMM}-{NNNN}', 'minimal', '#e67e22', '0.25cm');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `organization_name` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `collected_by` int(11) NOT NULL,
  `gps_latitude` decimal(10,8) DEFAULT NULL,
  `gps_longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('new','contacted','converted','closed') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

-- --------------------------------------------------------

--
-- Table structure for table `lead_categories`
--

CREATE TABLE `lead_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lead_categories`
--

INSERT INTO `lead_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES
(1, '├ô┬¬┬╝├ô┬¬┬Ñ├ô┬¬┬ú├ô┬¬┬Ñ├ô┬¬Γûæ', 'Market / Shop', 'active', '2026-03-04 06:31:34'),
(2, '├ô┬¬┬╝├ô┬║├¼├ô┬¬┬╗├ô┬¬┬Ñ├ô┬¬├⌐├ô┬¬├▓', 'Bank / Financial Institution', 'active', '2026-03-04 06:31:34'),
(3, '├ô┬¬├à├ô┬¬┬┐├ô┬¬┬ú├ô┬¬ΓöÉ├ô┬¬├┤', 'NGO / Non-Profit', 'active', '2026-03-04 06:31:34'),
(4, '├ô┬¬├é├ô┬¬ΓöÉ├ô┬¬├▓├ô┬║├¼├ô┬¬├Ç├ô┬¬┬Ñ ├ô┬¬┬¼├ô┬║├¼├ô┬¬Γûæ├ô┬¬├▒├ô┬¬ΓöÉ├ô┬¬├Ç├ô┬║├¼├ô┬¬├í├ô┬¬┬Ñ├ô┬¬┬┐', 'Educational Institution', 'active', '2026-03-04 06:31:34'),
(5, '├ô┬¬├▓├ô┬¬Γûæ├ô┬║├¼├ô┬¬┬¼├ô┬║├»├ô┬¬Γûæ├ô┬║├º├ô┬¬╞Æ ├ô┬¬├á├ô┬¬┬╜├ô┬¬ΓöÉ├ô┬¬┬⌐', 'Corporate Office', 'active', '2026-03-04 06:31:34'),
(6, 'αªúαª╛αª«', 'Government Organization', 'active', '2026-03-04 06:31:34'),
(7, '├ô┬¬├á├ô┬¬┬┐├ô┬║├¼├ô┬¬┬╗├ô┬¬┬Ñ├ô┬¬┬┐├ô┬║├¼├ô┬¬┬╗', 'Others', 'active', '2026-03-04 06:31:34'),
(8, '├ô┬¬┬╝├ô┬¬┬Ñ├ô┬¬┬ú├ô┬¬┬Ñ├ô┬¬Γûæ', 'Market / Shop', 'active', '2026-03-04 06:33:46'),
(9, '├ô┬¬┬╝├ô┬║├¼├ô┬¬┬╗├ô┬¬┬Ñ├ô┬¬├⌐├ô┬¬├▓', 'Bank / Financial Institution', 'active', '2026-03-04 06:33:46'),
(10, '├ô┬¬├à├ô┬¬┬┐├ô┬¬┬ú├ô┬¬ΓöÉ├ô┬¬├┤', 'NGO / Non-Profit', 'active', '2026-03-04 06:33:46'),
(11, '├ô┬¬├é├ô┬¬ΓöÉ├ô┬¬├▓├ô┬║├¼├ô┬¬├Ç├ô┬¬┬Ñ ├ô┬¬┬¼├ô┬║├¼├ô┬¬Γûæ├ô┬¬├▒├ô┬¬ΓöÉ├ô┬¬├Ç├ô┬║├¼├ô┬¬├í├ô┬¬┬Ñ├ô┬¬┬┐', 'Educational Institution', 'active', '2026-03-04 06:33:46'),
(12, '├ô┬¬├▓├ô┬¬Γûæ├ô┬║├¼├ô┬¬┬¼├ô┬║├»├ô┬¬Γûæ├ô┬║├º├ô┬¬╞Æ ├ô┬¬├á├ô┬¬┬╜├ô┬¬ΓöÉ├ô┬¬┬⌐', 'Corporate Office', 'active', '2026-03-04 06:33:46'),
(13, '├ô┬¬┬⌐├ô┬¬Γûæ├ô┬¬├▓├ô┬¬┬Ñ├ô┬¬Γûæ├ô┬¬ΓöÉ ├ô┬¬┬¼├ô┬║├¼├ô┬¬Γûæ├ô┬¬├▒├ô┬¬ΓöÉ├ô┬¬├Ç├ô┬║├¼├ô┬¬├í├ô┬¬┬Ñ├ô┬¬', 'Government Organization', 'active', '2026-03-04 06:33:46'),
(14, '├ô┬¬├á├ô┬¬┬┐├ô┬║├¼├ô┬¬┬╗├ô┬¬┬Ñ├ô┬¬┬┐├ô┬║├¼├ô┬¬┬╗', 'Others', 'active', '2026-03-04 06:33:46'),
(15, 'αªàαª½αª┐αª╕', '', 'active', '2026-03-04 08:21:13'),
(16, 'Test Category', '', 'active', '2026-03-12 07:18:57');

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `leave_type` enum('casual','sick','annual','unpaid') DEFAULT 'casual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

-- --------------------------------------------------------

--
-- Table structure for table `marketing_campaigns`
--

CREATE TABLE `marketing_campaigns` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `channel` enum('sms','email','whatsapp','telegram') DEFAULT 'sms',
  `target_type` enum('all_customers','lead_category','custom') DEFAULT 'all_customers',
  `target_category_id` int(11) DEFAULT NULL,
  `custom_recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_recipients`)),
  `total_recipients` int(11) DEFAULT 0,
  `sent_count` int(11) DEFAULT 0,
  `failed_count` int(11) DEFAULT 0,
  `status` enum('draft','sending','completed','failed') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_campaign_logs`
--

CREATE TABLE `marketing_campaign_logs` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_contact` varchar(255) DEFAULT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_settings`
--

CREATE TABLE `marketing_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marketing_settings`
--

INSERT INTO `marketing_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'email_smtp_host', '', '2026-03-04 06:31:34'),
(2, 'email_smtp_port', '587', '2026-03-04 06:31:34'),
(3, 'email_smtp_user', '', '2026-03-04 06:31:34'),
(4, 'email_smtp_pass', '', '2026-03-04 06:31:34'),
(5, 'email_from_name', '', '2026-03-04 06:31:34'),
(6, 'email_from_address', '', '2026-03-04 06:31:34'),
(7, 'whatsapp_api_url', '', '2026-03-04 06:31:34'),
(8, 'whatsapp_api_key', '', '2026-03-04 06:31:34'),
(9, 'telegram_bot_token', '', '2026-03-04 06:31:34'),
(10, 'sms_gateway_url', '', '2026-03-04 06:31:34'),
(11, 'sms_gateway_key', '', '2026-03-04 06:31:34');

-- --------------------------------------------------------

--
-- Table structure for table `marketing_templates`
--

CREATE TABLE `marketing_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `channel` enum('sms','email','whatsapp','telegram') NOT NULL DEFAULT 'sms',
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marketing_templates`
--

INSERT INTO `marketing_templates` (`id`, `name`, `channel`, `subject`, `body`, `variables`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'SSS', 'sms', NULL, '{name}fggt{store_name}ddre{balance}dee{phone}dd', '[\"name\",\"store_name\",\"balance\",\"phone\"]', 'active', NULL, '2026-03-12 07:44:22', '2026-03-12 07:44:22');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `name`, `slug`, `icon`, `url`, `parent_id`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Dashboard', 'dashboard', 'fas fa-tachometer-alt', '/modules/dashboard/index.php', NULL, 1, 1, '2026-02-28 05:40:36'),
(2, 'Users & Access', 'users', 'fas fa-users-cog', '#', NULL, 2, 1, '2026-02-28 05:40:36'),
(3, 'Media', 'media', 'fas fa-images', '/modules/media/index.php', NULL, 3, 1, '2026-02-28 05:40:36'),
(4, 'Customers', 'customers', 'fas fa-user-friends', '#', NULL, 4, 1, '2026-02-28 05:40:36'),
(5, 'Suppliers', 'suppliers', 'fas fa-truck', '#', NULL, 5, 1, '2026-02-28 05:40:36'),
(6, 'Products', 'products', 'fas fa-box-open', '#', NULL, 6, 1, '2026-02-28 05:40:36'),
(7, 'Purchase', 'purchase', 'fas fa-shopping-cart', '#', NULL, 7, 1, '2026-02-28 05:40:36'),
(8, 'Sales / POS', 'sales', 'fas fa-cash-register', '#', NULL, 8, 1, '2026-02-28 05:40:36'),
(9, 'Quotation', 'quotation', 'fas fa-file-alt', '#', NULL, 9, 1, '2026-02-28 05:40:36'),
(10, 'Warranty & RMA', 'warranty', 'fas fa-tools', '#', NULL, 10, 1, '2026-02-28 05:40:36'),
(11, 'Expense', 'expense', 'fas fa-receipt', '#', NULL, 12, 1, '2026-02-28 05:40:36'),
(12, 'Accounts', 'accounts', 'fas fa-university', '#', NULL, 13, 1, '2026-02-28 05:40:36'),
(13, 'HR', 'hr', 'fas fa-user-tie', '#', NULL, 14, 1, '2026-02-28 05:40:36'),
(14, 'Stock Transfer', 'stock-transfer', 'fas fa-exchange-alt', '#', NULL, 15, 0, '2026-02-28 05:40:36'),
(15, 'Reports', 'reports', 'fas fa-chart-bar', '#', NULL, 16, 1, '2026-02-28 05:40:36'),
(16, 'Settings', 'settings', 'fas fa-cogs', '#', NULL, 17, 1, '2026-02-28 05:40:36'),
(17, 'Users List', 'users.list', 'fas fa-users', '/modules/users/users-list.php', 2, 1, 1, '2026-02-28 05:40:36'),
(18, 'Add User', 'users.add', 'fas fa-user-plus', '/modules/users/user-add.php', 2, 2, 1, '2026-02-28 05:40:36'),
(19, 'Roles & Permissions', 'users.roles', 'fas fa-user-tag', '/modules/users/roles-permissions.php', 2, 3, 1, '2026-02-28 05:40:36'),
(20, 'Customer List', 'customers.list', 'fas fa-users', '/modules/customers/customers-list.php', 4, 1, 1, '2026-02-28 05:40:36'),
(21, 'Add Customer', 'customers.add', 'fas fa-user-plus', '/modules/customers/customer-add.php', 4, 2, 1, '2026-02-28 05:40:36'),
(22, 'Customer Ledger', 'customers.ledger', 'fas fa-file-invoice', '/modules/customers/customer-ledger.php', 4, 3, 1, '2026-02-28 05:40:36'),
(23, 'Supplier List', 'suppliers.list', 'fas fa-truck', '/modules/suppliers/suppliers-list.php', 5, 1, 1, '2026-02-28 05:40:36'),
(24, 'Add Supplier', 'suppliers.add', 'fas fa-plus-circle', '/modules/suppliers/supplier-add.php', 5, 2, 1, '2026-02-28 05:40:36'),
(25, 'Supplier Ledger', 'suppliers.ledger', 'fas fa-file-invoice-dollar', '/modules/suppliers/supplier-ledger.php', 5, 3, 1, '2026-02-28 05:40:36'),
(26, 'Product List', 'products.list', 'fas fa-boxes', '/modules/products/products-list.php', 6, 1, 1, '2026-02-28 05:40:36'),
(27, 'Add Product', 'products.add', 'fas fa-plus-square', '/modules/products/product-add.php', 6, 2, 1, '2026-02-28 05:40:36'),
(28, 'Categories', 'products.categories', 'fas fa-tags', '/modules/products/categories-list.php', 6, 3, 1, '2026-02-28 05:40:36'),
(29, 'Brands', 'products.brands', 'fas fa-bookmark', '/modules/products/brands-list.php', 6, 4, 1, '2026-02-28 05:40:36'),
(30, 'Units', 'products.units', 'fas fa-balance-scale', '/modules/products/units-list.php', 6, 5, 1, '2026-02-28 05:40:36'),
(31, 'Stock Adjustments', 'products.stock-adjustments', 'fas fa-sliders-h', '/modules/products/stock-adjustment.php', 6, 8, 1, '2026-02-28 05:40:36'),
(32, 'Damaged Stock', 'products.damaged-stock', 'fas fa-exclamation-triangle', '/modules/products/damaged-stock.php', 6, 9, 1, '2026-02-28 05:40:36'),
(33, 'Barcode', 'products.barcode', 'fas fa-barcode', '/modules/barcode/barcode-generator.php', 6, 10, 1, '2026-02-28 05:40:36'),
(34, 'Warehouses', 'products.warehouses', 'fas fa-warehouse', '/modules/products/products-list.php', 6, 11, 1, '2026-02-28 05:40:36'),
(35, 'Purchase List', 'purchase.list', 'fas fa-list-alt', '/modules/purchase/purchases-list.php', 7, 1, 1, '2026-02-28 05:40:36'),
(36, 'Add Purchase', 'purchase.add', 'fas fa-cart-plus', '/modules/purchase/purchase-add.php', 7, 2, 1, '2026-02-28 05:40:36'),
(37, 'Purchase Returns', 'purchase.returns', 'fas fa-undo', '/modules/purchase/purchase-return-list.php', 7, 3, 1, '2026-02-28 05:40:36'),
(38, 'Purchase Payments', 'purchase.payments', 'fas fa-money-bill', '/modules/purchase/supplier-payment.php', 7, 4, 1, '2026-02-28 05:40:36'),
(39, 'POS', 'sales.pos', 'fas fa-cash-register', '/modules/sales/pos.php', 8, 1, 1, '2026-02-28 05:40:36'),
(40, 'Sales List', 'sales.list', 'fas fa-list', '/modules/sales/sales-list.php', 8, 2, 1, '2026-02-28 05:40:36'),
(41, 'Due Management', 'sales.due-management', 'fas fa-money-check-alt', '/modules/sales/due-management.php', 8, 6, 1, '2026-02-28 05:40:36'),
(42, 'Bill Collection', 'sales.bill-collection', 'fas fa-hand-holding-usd', '/modules/sales/bill-collection.php', 8, 5, 1, '2026-02-28 05:40:36'),
(43, 'Sales Returns', 'sales.returns', 'fas fa-undo-alt', '/modules/sales/sales-return-list.php', 8, 4, 1, '2026-02-28 05:40:36'),
(44, 'Sales Cancel', 'sales.cancel', 'fas fa-times-circle', '/modules/sales/sales-cancel-list.php', 8, 3, 1, '2026-02-28 05:40:36'),
(45, 'Invoice Settings', 'settings.invoice', 'fas fa-file-invoice', '/modules/sales/invoice-settings.php', 16, 2, 1, '2026-02-28 05:40:36'),
(46, 'Quotation List', 'quotation.list', 'fas fa-file-alt', '/modules/quotation/quotations-list.php', 9, 1, 1, '2026-02-28 05:40:36'),
(47, 'Create Quotation', 'quotation.add', 'fas fa-plus-circle', '/modules/quotation/quotation-add.php', 9, 2, 1, '2026-02-28 05:40:36'),
(48, 'RMA List', 'warranty.rma-list', 'fas fa-tools', '/modules/warranty/rma-list.php', 10, 2, 1, '2026-02-28 05:40:36'),
(49, 'Create RMA', 'warranty.rma-create', 'fas fa-plus-circle', '/modules/warranty/rma-add.php', 10, 3, 1, '2026-02-28 05:40:36'),
(50, 'Service Centers', 'warranty.service-centers', 'fas fa-building', '/modules/warranty/service-center-info.php', 10, 5, 1, '2026-02-28 05:40:36'),
(51, 'Expense List', 'expense.list', 'fas fa-file-invoice-dollar', '/modules/expense/expenses-list.php', 11, 1, 1, '2026-02-28 05:40:36'),
(52, 'Add Expense', 'expense.add', 'fas fa-plus-circle', '/modules/expense/expense-add.php', 11, 2, 1, '2026-02-28 05:40:36'),
(53, 'Categories', 'expense.categories', 'fas fa-tags', '/modules/expense/expense-categories.php', 11, 3, 1, '2026-02-28 05:40:36'),
(54, 'Cash Account', 'accounts.cash-accounts', 'fas fa-coins', '/modules/accounts/cash-accounts-list.php', 12, 1, 1, '2026-02-28 05:40:36'),
(55, 'Bank Accounts', 'accounts.bank-accounts', 'fas fa-university', '/modules/accounts/bank-accounts.php', 12, 3, 1, '2026-02-28 05:40:36'),
(56, 'Daily Cash Closing', 'accounts.cash-closing', 'fas fa-lock', '/modules/accounts/daily-cash-closing.php', 12, 7, 1, '2026-02-28 05:40:36'),
(57, 'Balance Transfer', 'accounts.balance-transfer', 'fas fa-exchange-alt', '/modules/accounts/balance-transfer.php', 12, 4, 1, '2026-02-28 05:40:36'),
(59, 'Mobile Banking', 'accounts.mobile-banking', 'fas fa-mobile-alt', '/modules/accounts/mobile-banking.php', 12, 2, 1, '2026-02-28 05:40:36'),
(60, 'Staff List', 'hr.staff-list', 'fas fa-users-cog', '/modules/hr/staff-list.php', 13, 1, 1, '2026-02-28 05:40:36'),
(61, 'Add Staff', 'hr.staff-add', 'fas fa-user-plus', '/modules/hr/staff-add.php', 13, 2, 1, '2026-02-28 05:40:36'),
(62, 'Staff Roles', 'hr.staff-roles', 'fas fa-user-shield', '/modules/hr/staff-roles.php', 13, 4, 1, '2026-02-28 05:40:36'),
(63, 'Departments', 'departments', 'fas fa-building', '/modules/hr/departments.php', 13, 5, 1, '2026-02-28 05:40:36'),
(64, 'Salary Management', 'hr.salary', 'fas fa-money-check', '/modules/hr/salary-manage.php', 13, 3, 1, '2026-02-28 05:40:36'),
(65, 'Attendance', 'hr.attendance', 'fas fa-calendar-check', '/modules/hr/attendance.php', 13, 6, 1, '2026-02-28 05:40:36'),
(66, 'Transfer Stock', 'stock-transfer.create', 'fas fa-exchange-alt', '/modules/stock-transfer/transfer-create.php', 6, 13, 1, '2026-02-28 05:40:36'),
(67, 'Transfer List', 'stock-transfer.list', 'fas fa-list-ol', '/modules/stock-transfer/transfer-list.php', 6, 12, 1, '2026-02-28 05:40:36'),
(68, 'Sales Report', 'reports.sales', 'fas fa-chart-line', '/modules/reports/sales-report.php', 15, 1, 1, '2026-02-28 05:40:36'),
(69, 'Purchase Report', 'reports.purchase', 'fas fa-chart-bar', '/modules/reports/purchase-report.php', 15, 2, 1, '2026-02-28 05:40:36'),
(70, 'Stock Report', 'reports.stock', 'fas fa-cubes', '/modules/reports/stock-report.php', 15, 3, 1, '2026-02-28 05:40:36'),
(71, 'Expense Report', 'reports.expense', 'fas fa-receipt', '/modules/reports/expense-report.php', 15, 4, 1, '2026-02-28 05:40:36'),
(72, 'Receivable Report', 'reports.receivable', 'fas fa-hand-holding-usd', '/modules/reports/receivable-report.php', 15, 5, 1, '2026-02-28 05:40:36'),
(73, 'Payable Report', 'reports.payable', 'fas fa-file-invoice-dollar', '/modules/reports/payable-report.php', 15, 6, 1, '2026-02-28 05:40:36'),
(74, 'Profit & Loss', 'reports.profit-loss', 'fas fa-balance-scale-left', '/modules/reports/profit-loss-report.php', 15, 7, 1, '2026-02-28 05:40:36'),
(75, 'Top Customer', 'reports.top-customer', 'fas fa-trophy', '/modules/reports/top-customer.php', 15, 8, 1, '2026-02-28 05:40:36'),
(76, 'Product Sales', 'reports.product-sales', 'fas fa-chart-pie', '/modules/reports/product-sales-report.php', 15, 9, 1, '2026-02-28 05:40:36'),
(77, 'Account Transaction', 'reports.account-transaction', 'fas fa-exchange-alt', '/modules/reports/account-transaction-report.php', 15, 10, 1, '2026-02-28 05:40:36'),
(78, 'Low Stock', 'reports.low-stock', 'fas fa-exclamation-circle', '/modules/reports/low-stock-report.php', 15, 11, 1, '2026-02-28 05:40:36'),
(79, 'Alert Product', 'reports.alert-product', 'fas fa-bell', '/modules/reports/alert-product-report.php', 15, 12, 1, '2026-02-28 05:40:36'),
(81, 'Business Settings', 'settings.business', 'fas fa-store', '/modules/settings/business-settings.php', 16, 1, 1, '2026-02-28 05:40:36'),
(82, 'Backup & Restore', 'settings.backup', 'fas fa-database', '/modules/settings/backup.php', 16, 5, 1, '2026-02-28 05:40:36'),
(83, 'License', 'settings.license', 'fas fa-key', '/modules/settings/license-manage.php', 16, 6, 1, '2026-02-28 05:40:36'),
(85, 'Activity Log', 'users.activity-log', 'fas fa-history', '/modules/users/activity-log.php', 2, 4, 1, '2026-02-28 06:19:19'),
(86, 'Serial List', 'warranty.serial-list', 'fas fa-barcode', '/modules/warranty/serial-list.php', 10, 1, 1, '2026-02-28 11:38:51'),
(87, 'RMA Status', 'warranty.rma-status', 'fas fa-info-circle', '/modules/warranty/rma-status.php', 10, 4, 1, '2026-02-28 11:38:51'),
(88, 'Advance RMA Search', 'warranty.advance-rma-search', 'fas fa-search-plus', '/modules/warranty/advance-rma-search.php', 10, 6, 1, '2026-02-28 11:38:51'),
(89, 'Serial/IMEI', 'products.serial-imei', 'fas fa-fingerprint', '/modules/products/serial-imei-list.php', 6, 6, 1, '2026-02-28 14:50:00'),
(90, 'Opening Stock', 'products.opening-stock', 'fas fa-box-open', '/modules/products/opening-stock.php', 6, 7, 1, '2026-02-28 14:50:00'),
(91, 'RMA Stock', 'products.rma-stock', 'fas fa-undo-alt', '/modules/products/rma-stock.php', 6, 14, 1, '2026-02-28 14:50:00'),
(92, 'Expense Approval', 'expense.expense-approval', 'fas fa-check-circle', '/modules/expense/expense-approve.php', 11, 4, 1, '2026-02-28 15:27:39'),
(93, 'Transaction History', 'accounts.transaction-history', 'fas fa-history', '/modules/accounts/transaction-history.php', 12, 6, 1, '2026-02-28 15:27:39'),
(94, 'Party Cash', 'accounts.party-cash', 'fas fa-users', '/modules/accounts/party-cash.php', 12, 5, 1, '2026-02-28 15:27:39'),
(95, 'Yearly Closing', 'accounts.yearly-closing', 'fas fa-calendar-check', '/modules/accounts/yearly-closing.php', 12, 8, 1, '2026-02-28 15:27:39'),
(96, 'Attendance Devices', 'hr.attendance-devices', 'fas fa-fingerprint', '/modules/hr/attendance-devices.php', 128, 1, 1, '2026-02-28 15:39:24'),
(97, 'Team Overview', 'hr.team-overview', 'fas fa-users-cog', '/modules/hr/team-overview.php', 13, 8, 1, '2026-02-28 15:39:25'),
(98, 'Attendance Logs', 'hr.attendance-logs', 'fas fa-clipboard-list', '/modules/hr/attendance-logs.php', 13, 9, 1, '2026-02-28 15:39:25'),
(99, 'Paid Service', 'paid-service', 'fas fa-tools', '#', NULL, 11, 1, '2026-02-28 15:51:50'),
(100, 'Service Intake', 'paid-service.intake', 'fas fa-laptop-medical', '/modules/paid-service/service-intake.php', 99, 1, 1, '2026-02-28 15:51:50'),
(101, 'Service List', 'paid-service.list', 'fas fa-clipboard-list', '/modules/paid-service/service-list.php', 99, 2, 1, '2026-02-28 15:51:50'),
(102, 'Service Status', 'paid-service.status', 'fas fa-search-location', '/modules/paid-service/service-status.php', 99, 3, 1, '2026-02-28 16:12:17'),
(103, 'Service Report', 'paid-service.report', 'fas fa-chart-bar', '/modules/paid-service/service-report.php', 99, 4, 1, '2026-03-01 16:00:40'),
(104, 'Tax Settings', 'settings.tax', 'fas fa-percentage', '/modules/settings/tax-settings.php', 16, 3, 1, '2026-03-02 09:38:09'),
(105, 'Profile', 'settings.profile', 'fas fa-user-cog', '/modules/settings/profile.php', 16, 7, 1, '2026-03-02 09:38:09'),
(106, 'Change Password', 'settings.password', 'fas fa-key', '/modules/settings/change-password.php', 16, 8, 1, '2026-03-02 09:38:09'),
(107, 'Data Cleanup', 'settings.cleanup', 'fas fa-broom', '/modules/settings/data-cleanup.php', 16, 9, 1, '2026-03-02 09:38:09'),
(108, 'Notification', 'notification', 'fas fa-bell', '#', NULL, 12, 1, '2026-03-02 09:56:44'),
(109, 'SMS/API Config', 'notification.sms-config', 'fas fa-cog', '/modules/notification/sms-config.php', 108, 1, 1, '2026-03-02 09:56:44'),
(110, 'Message Templates', 'notification.templates', 'fas fa-file-alt', '/modules/notification/message-templates.php', 108, 2, 1, '2026-03-02 09:56:44'),
(111, 'Send Message', 'notification.send', 'fas fa-paper-plane', '/modules/notification/send-message.php', 108, 3, 1, '2026-03-02 09:56:44'),
(112, 'Due Reminders', 'notification.due', 'fas fa-bell', '/modules/notification/due-reminders.php', 108, 4, 1, '2026-03-02 09:56:44'),
(113, 'Service Updates', 'notification.service', 'fas fa-tools', '/modules/notification/service-updates.php', 108, 5, 1, '2026-03-02 09:56:44'),
(114, 'Scheduled Messages', 'notification.scheduled', 'fas fa-clock', '/modules/notification/scheduled-messages.php', 108, 6, 1, '2026-03-02 09:56:44'),
(115, 'Message History', 'notification.history', 'fas fa-history', '/modules/notification/message-history.php', 108, 7, 1, '2026-03-02 09:56:44'),
(116, 'Shift Management', 'hr.shifts', 'fas fa-business-time', '/modules/hr/shift-manage.php', 13, 10, 1, '2026-03-02 10:40:48'),
(117, 'Leave Management', 'hr.leaves', 'fas fa-calendar-minus', '/modules/hr/leave-manage.php', 13, 11, 1, '2026-03-02 10:40:48'),
(118, 'Task Assignment', 'hr.tasks', 'fas fa-tasks', '/modules/hr/task-assign.php', 13, 12, 1, '2026-03-02 10:40:48'),
(119, 'Staff Broadcast', 'hr.broadcast', 'fas fa-bullhorn', '/modules/hr/staff-broadcast.php', 13, 13, 1, '2026-03-02 10:40:48'),
(120, 'Mobile Check-in', 'hr.mobile_checkin', 'fas fa-mobile-alt', '/modules/hr/mobile-checkin.php', 128, 3, 1, '2026-03-03 08:40:35'),
(121, 'Kiosk Mode', 'hr.kiosk_attendance', 'fas fa-desktop', '/modules/hr/kiosk-attendance.php', 128, 2, 1, '2026-03-03 08:40:35'),
(124, 'CCTV Config', 'hr.cctv_config', 'fas fa-video', '/modules/hr/cctv-config.php', 128, 4, 1, '2026-03-03 08:40:35'),
(125, 'CCTV Tracking', 'hr.cctv_tracking', 'fas fa-eye', '/modules/hr/cctv-tracking.php', 128, 5, 1, '2026-03-03 08:40:35'),
(126, 'Biometric Register', 'hr.face_register', 'fas fa-id-card', '/modules/hr/face-register.php', 128, 6, 1, '2026-03-03 08:40:35'),
(127, 'Attendance Settings', 'hr.attendance_settings', 'fas fa-cog', '/modules/hr/attendance-settings.php', 13, 21, 1, '2026-03-03 08:40:35'),
(128, 'Devices', 'hr.devices', 'fas fa-microchip', '#', 13, 22, 1, '2026-03-03 09:20:42'),
(129, 'Sales Requests', 'sales.requests', 'fas fa-clipboard-list', '/modules/sales-request/request-list.php', 8, 5, 1, '2026-03-04 06:33:46'),
(130, 'Staff Performance', 'reports.staff', 'fas fa-chart-line', '/modules/reports/staff-performance.php', 15, 15, 1, '2026-03-04 06:33:46'),
(131, 'Market Data', 'market', 'fas fa-database', '#', NULL, 13, 1, '2026-03-04 06:33:46'),
(132, 'Lead Categories', 'market.categories', 'fas fa-tags', '/modules/crm/lead-categories.php', 131, 1, 1, '2026-03-04 06:33:46'),
(133, 'Add Lead', 'market.add', 'fas fa-plus-circle', '/modules/crm/lead-add.php', 131, 2, 1, '2026-03-04 06:33:46'),
(134, 'Lead List', 'market.list', 'fas fa-list', '/modules/crm/lead-list.php', 131, 3, 1, '2026-03-04 06:33:46'),
(135, 'Marketing', 'marketing', 'fas fa-bullhorn', '#', NULL, 14, 1, '2026-03-04 06:33:46'),
(136, 'Templates', 'marketing.templates', 'fas fa-file-alt', '/modules/marketing/templates.php', 135, 1, 1, '2026-03-04 06:33:46'),
(137, 'Campaigns', 'marketing.campaigns', 'fas fa-paper-plane', '/modules/marketing/campaigns.php', 135, 2, 1, '2026-03-04 06:33:46'),
(138, 'SMS Send', 'marketing.sms', 'fas fa-sms', '/modules/marketing/sms-send.php', 135, 3, 1, '2026-03-04 06:33:46'),
(139, 'Settings', 'marketing.settings', 'fas fa-cog', '/modules/marketing/settings.php', 135, 4, 1, '2026-03-04 06:33:46');

-- --------------------------------------------------------

--
-- Table structure for table `message_log`
--

CREATE TABLE `message_log` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `type` enum('sms','email','whatsapp','telegram') DEFAULT 'sms',
  `category` enum('sales','due','service','warranty','greeting','payment','custom') DEFAULT 'custom',
  `status` enum('pending','sent','delivered','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_log`
--

-- --------------------------------------------------------

--
-- Table structure for table `message_templates`
--

CREATE TABLE `message_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('sales','warranty','due','service','greeting','payment','custom') DEFAULT 'custom',
  `content` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_templates`
--

INSERT INTO `message_templates` (`id`, `name`, `type`, `content`, `is_active`, `created_at`) VALUES
(1, 'Sale Confirmation', 'sales', 'Dear {customer_name}, your purchase (Invoice: {invoice_no}) of {total_amount} Tk has been completed. Thank you for shopping with us!', 1, '2026-03-02 09:56:44'),
(2, 'Due Reminder', 'due', 'Dear {customer_name}, you have a due balance of {due_amount} Tk (Invoice: {invoice_no}). Please clear your dues at your earliest convenience. Thank you.', 1, '2026-03-02 09:56:44'),
(3, 'Service Received', 'service', 'Dear {customer_name}, your device has been received for servicing (Ticket: {ticket_no}). We will update you on the progress.', 1, '2026-03-02 09:56:44'),
(4, 'Service In Progress', 'service', 'Dear {customer_name}, your device (Ticket: {ticket_no}) is currently being repaired. We will notify you when it is ready.', 1, '2026-03-02 09:56:44'),
(5, 'Service Ready', 'service', 'Dear {customer_name}, your device (Ticket: {ticket_no}) is ready for delivery. Please collect it at your convenience.', 1, '2026-03-02 09:56:44'),
(6, 'Warranty Expiry Alert', 'warranty', 'Dear {customer_name}, your warranty for {product_name} (Serial: {serial_number}) expires on {expiry_date}. Contact us for extended warranty options.', 1, '2026-03-02 09:56:44'),
(7, 'Payment Received', 'payment', 'Dear {customer_name}, we have received your payment of {paid_amount} Tk for Invoice: {invoice_no}. Remaining due: {due_amount} Tk. Thank you!', 1, '2026-03-02 09:56:44'),
(8, 'Eid Greetings', 'greeting', 'Dear {customer_name}, Eid Mubarak! Wishing you and your family a blessed celebration. Thank you for being our valued customer.', 1, '2026-03-02 09:56:44'),
(9, 'New Year Greetings', 'greeting', 'Dear {customer_name}, Happy New Year! Wishing you a prosperous year ahead. Thank you for your continued support.', 1, '2026-03-02 09:56:44');

-- --------------------------------------------------------

--
-- Table structure for table `party_cash`
--

CREATE TABLE `party_cash` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('assign','return') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `module`, `action`, `description`, `created_at`) VALUES
(1, 'stock_transfer.view', 'stock_transfer', '', 'View Stock Transfers', '2026-01-28 21:30:31'),
(2, 'stock_transfer.create', 'stock_transfer', '', 'Create Stock Transfer', '2026-01-28 21:30:31'),
(3, 'stock_transfer.edit', 'stock_transfer', '', 'Edit Stock Transfer', '2026-01-28 21:30:31'),
(4, 'stock_transfer.approve', 'stock_transfer', '', 'Approve Stock Transfer', '2026-01-28 21:30:31'),
(5, 'stock_transfer.delete', 'stock_transfer', '', 'Delete Stock Transfer', '2026-01-28 21:30:31');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `purchase_price` decimal(15,2) DEFAULT 0.00,
  `selling_price` decimal(15,2) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `image` varchar(255) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `rma_quantity` decimal(10,2) DEFAULT 0.00,
  `reorder_level` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_service_product` tinyint(1) DEFAULT 0,
  `has_serial` enum('Available','Not Available') DEFAULT 'Not Available',
  `warranty_duration` int(11) DEFAULT 0,
  `warranty_period` enum('Days','Month','Year') DEFAULT 'Month',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

-- --------------------------------------------------------

--
-- Table structure for table `product_serials`
--

CREATE TABLE `product_serials` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `imei` varchar(100) DEFAULT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `sale_item_id` int(11) DEFAULT NULL,
  `warranty_months` int(11) DEFAULT 12,
  `purchase_date` date DEFAULT NULL,
  `sale_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'in_stock',
  `serial_status` varchar(50) DEFAULT 'in_stock',
  `stock_type` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `warranty_alert_sent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_serials`
--

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_name` varchar(100) NOT NULL COMMENT 'RAM, Storage, Color',
  `variant_value` varchar(100) NOT NULL COMMENT '8GB, 128GB, Black',
  `sku` varchar(100) DEFAULT NULL,
  `price_adjustment` decimal(15,2) DEFAULT 0.00 COMMENT 'Additional price',
  `stock_quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_warehouse_stock`
--

CREATE TABLE `product_warehouse_stock` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `reserved_quantity` decimal(10,2) DEFAULT 0.00,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `purchase_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `due_amount` decimal(15,2) DEFAULT 0.00,
  `payment_status` enum('paid','partial','unpaid') NOT NULL DEFAULT 'unpaid',
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `tax` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

-- --------------------------------------------------------

--
-- Table structure for table `purchase_payments`
--

CREATE TABLE `purchase_payments` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank','credit','card','mobile_money') NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_payments`
--

-- --------------------------------------------------------

--
-- Table structure for table `purchase_returns`
--

CREATE TABLE `purchase_returns` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_return_items`
--

CREATE TABLE `purchase_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `id` int(11) NOT NULL,
  `quotation_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `quotation_date` date NOT NULL,
  `expiration_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','accepted','rejected','converted') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotations`
--

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `tax` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotation_items`
--

-- --------------------------------------------------------

--
-- Table structure for table `rma_external_replacements`
--

CREATE TABLE `rma_external_replacements` (
  `id` int(11) NOT NULL,
  `rma_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `brand_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rma_external_replacements`
--

-- --------------------------------------------------------

--
-- Table structure for table `rma_requests`
--

CREATE TABLE `rma_requests` (
  `id` int(11) NOT NULL,
  `rma_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `problem_description` text NOT NULL,
  `status` enum('pending','sent','solved','delivered') NOT NULL DEFAULT 'pending',
  `service_center_id` int(11) DEFAULT NULL,
  `new_serial_id` int(11) DEFAULT NULL,
  `testing_date` date DEFAULT NULL,
  `testing_note` text DEFAULT NULL,
  `replace_out_number` varchar(50) DEFAULT NULL,
  `replace_out_date` date DEFAULT NULL,
  `replace_in_number` varchar(50) DEFAULT NULL,
  `replace_in_date` date DEFAULT NULL,
  `replace_in_type` enum('repair','new') DEFAULT 'repair',
  `delivery_number` varchar(50) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `created_date` date NOT NULL,
  `sent_date` date DEFAULT NULL,
  `solved_date` date DEFAULT NULL,
  `delivered_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rma_requests`
--

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'Super Admin', 'Full access to all modules', '2026-01-26 01:49:30', NULL, NULL),
(2, 'Manager', 'Manages daily operations', '2026-01-26 01:49:30', NULL, NULL),
(3, 'Cashier', 'POS and sales operations', '2026-01-26 01:49:30', NULL, NULL),
(4, 'Sales Representative', 'Sales and quotations', '2026-01-26 01:49:30', NULL, NULL),
(5, 'Accountant', 'Financial operations', '2026-01-26 01:49:30', NULL, NULL),
(6, 'Warranty & RMA', '', '2026-02-04 20:03:47', NULL, NULL),
(7, 'Technician', '', '2026-02-28 04:29:08', NULL, NULL),
(8, 'Tania Isratαªƒ', '', '2026-03-05 05:14:15', '2026-03-05 05:28:44', 8);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) DEFAULT NULL,
  `menu_item_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `menu_item_id`, `created_at`) VALUES
(342, 2, NULL, 1, '2026-02-28 05:40:37'),
(343, 2, NULL, 2, '2026-02-28 05:40:37'),
(344, 2, NULL, 3, '2026-02-28 05:40:37'),
(345, 2, NULL, 4, '2026-02-28 05:40:37'),
(346, 2, NULL, 5, '2026-02-28 05:40:37'),
(347, 2, NULL, 6, '2026-02-28 05:40:37'),
(348, 2, NULL, 7, '2026-02-28 05:40:37'),
(349, 2, NULL, 8, '2026-02-28 05:40:37'),
(350, 2, NULL, 9, '2026-02-28 05:40:37'),
(351, 2, NULL, 10, '2026-02-28 05:40:37'),
(352, 2, NULL, 11, '2026-02-28 05:40:37'),
(353, 2, NULL, 12, '2026-02-28 05:40:37'),
(354, 2, NULL, 13, '2026-02-28 05:40:37'),
(355, 2, NULL, 14, '2026-02-28 05:40:37'),
(356, 2, NULL, 15, '2026-02-28 05:40:37'),
(357, 2, NULL, 16, '2026-02-28 05:40:37'),
(358, 2, NULL, 17, '2026-02-28 05:40:37'),
(359, 2, NULL, 18, '2026-02-28 05:40:37'),
(360, 2, NULL, 19, '2026-02-28 05:40:37'),
(361, 2, NULL, 20, '2026-02-28 05:40:37'),
(362, 2, NULL, 21, '2026-02-28 05:40:37'),
(363, 2, NULL, 22, '2026-02-28 05:40:37'),
(364, 2, NULL, 23, '2026-02-28 05:40:37'),
(365, 2, NULL, 24, '2026-02-28 05:40:37'),
(366, 2, NULL, 25, '2026-02-28 05:40:37'),
(367, 2, NULL, 26, '2026-02-28 05:40:37'),
(368, 2, NULL, 27, '2026-02-28 05:40:37'),
(369, 2, NULL, 28, '2026-02-28 05:40:37'),
(370, 2, NULL, 29, '2026-02-28 05:40:37'),
(371, 2, NULL, 30, '2026-02-28 05:40:37'),
(372, 2, NULL, 31, '2026-02-28 05:40:37'),
(373, 2, NULL, 32, '2026-02-28 05:40:37'),
(374, 2, NULL, 33, '2026-02-28 05:40:37'),
(375, 2, NULL, 34, '2026-02-28 05:40:37'),
(376, 2, NULL, 35, '2026-02-28 05:40:37'),
(377, 2, NULL, 36, '2026-02-28 05:40:37'),
(378, 2, NULL, 37, '2026-02-28 05:40:37'),
(379, 2, NULL, 38, '2026-02-28 05:40:37'),
(380, 2, NULL, 39, '2026-02-28 05:40:37'),
(381, 2, NULL, 40, '2026-02-28 05:40:37'),
(382, 2, NULL, 41, '2026-02-28 05:40:37'),
(383, 2, NULL, 42, '2026-02-28 05:40:37'),
(384, 2, NULL, 43, '2026-02-28 05:40:37'),
(385, 2, NULL, 44, '2026-02-28 05:40:37'),
(386, 2, NULL, 45, '2026-02-28 05:40:37'),
(387, 2, NULL, 46, '2026-02-28 05:40:37'),
(388, 2, NULL, 47, '2026-02-28 05:40:37'),
(389, 2, NULL, 48, '2026-02-28 05:40:37'),
(390, 2, NULL, 49, '2026-02-28 05:40:37'),
(391, 2, NULL, 50, '2026-02-28 05:40:37'),
(392, 2, NULL, 51, '2026-02-28 05:40:37'),
(393, 2, NULL, 52, '2026-02-28 05:40:37'),
(394, 2, NULL, 53, '2026-02-28 05:40:37'),
(395, 2, NULL, 54, '2026-02-28 05:40:37'),
(396, 2, NULL, 55, '2026-02-28 05:40:37'),
(397, 2, NULL, 56, '2026-02-28 05:40:37'),
(398, 2, NULL, 57, '2026-02-28 05:40:37'),
(400, 2, NULL, 59, '2026-02-28 05:40:37'),
(401, 2, NULL, 60, '2026-02-28 05:40:37'),
(402, 2, NULL, 61, '2026-02-28 05:40:37'),
(403, 2, NULL, 62, '2026-02-28 05:40:37'),
(404, 2, NULL, 63, '2026-02-28 05:40:37'),
(405, 2, NULL, 64, '2026-02-28 05:40:37'),
(406, 2, NULL, 65, '2026-02-28 05:40:37'),
(407, 2, NULL, 66, '2026-02-28 05:40:37'),
(408, 2, NULL, 67, '2026-02-28 05:40:37'),
(409, 2, NULL, 68, '2026-02-28 05:40:37'),
(410, 2, NULL, 69, '2026-02-28 05:40:37'),
(411, 2, NULL, 70, '2026-02-28 05:40:37'),
(412, 2, NULL, 71, '2026-02-28 05:40:37'),
(413, 2, NULL, 72, '2026-02-28 05:40:37'),
(414, 2, NULL, 73, '2026-02-28 05:40:37'),
(415, 2, NULL, 74, '2026-02-28 05:40:37'),
(416, 2, NULL, 75, '2026-02-28 05:40:37'),
(417, 2, NULL, 76, '2026-02-28 05:40:37'),
(418, 2, NULL, 77, '2026-02-28 05:40:37'),
(419, 2, NULL, 78, '2026-02-28 05:40:37'),
(420, 2, NULL, 79, '2026-02-28 05:40:37'),
(422, 2, NULL, 81, '2026-02-28 05:40:37'),
(423, 2, NULL, 82, '2026-02-28 05:40:37'),
(424, 2, NULL, 83, '2026-02-28 05:40:37'),
(426, 3, NULL, 1, '2026-02-28 05:40:37'),
(427, 3, NULL, 2, '2026-02-28 05:40:37'),
(428, 3, NULL, 3, '2026-02-28 05:40:37'),
(429, 3, NULL, 4, '2026-02-28 05:40:37'),
(430, 3, NULL, 5, '2026-02-28 05:40:37'),
(431, 3, NULL, 6, '2026-02-28 05:40:37'),
(432, 3, NULL, 7, '2026-02-28 05:40:37'),
(433, 3, NULL, 8, '2026-02-28 05:40:37'),
(434, 3, NULL, 9, '2026-02-28 05:40:37'),
(435, 3, NULL, 10, '2026-02-28 05:40:37'),
(436, 3, NULL, 11, '2026-02-28 05:40:37'),
(437, 3, NULL, 12, '2026-02-28 05:40:37'),
(438, 3, NULL, 13, '2026-02-28 05:40:37'),
(439, 3, NULL, 14, '2026-02-28 05:40:37'),
(440, 3, NULL, 15, '2026-02-28 05:40:37'),
(441, 3, NULL, 16, '2026-02-28 05:40:37'),
(442, 3, NULL, 17, '2026-02-28 05:40:37'),
(443, 3, NULL, 18, '2026-02-28 05:40:37'),
(444, 3, NULL, 19, '2026-02-28 05:40:37'),
(445, 3, NULL, 20, '2026-02-28 05:40:37'),
(446, 3, NULL, 21, '2026-02-28 05:40:37'),
(447, 3, NULL, 22, '2026-02-28 05:40:37'),
(448, 3, NULL, 23, '2026-02-28 05:40:37'),
(449, 3, NULL, 24, '2026-02-28 05:40:37'),
(450, 3, NULL, 25, '2026-02-28 05:40:37'),
(451, 3, NULL, 26, '2026-02-28 05:40:37'),
(452, 3, NULL, 27, '2026-02-28 05:40:37'),
(453, 3, NULL, 28, '2026-02-28 05:40:37'),
(454, 3, NULL, 29, '2026-02-28 05:40:37'),
(455, 3, NULL, 30, '2026-02-28 05:40:37'),
(456, 3, NULL, 31, '2026-02-28 05:40:37'),
(457, 3, NULL, 32, '2026-02-28 05:40:37'),
(458, 3, NULL, 33, '2026-02-28 05:40:37'),
(459, 3, NULL, 34, '2026-02-28 05:40:37'),
(460, 3, NULL, 35, '2026-02-28 05:40:37'),
(461, 3, NULL, 36, '2026-02-28 05:40:37'),
(462, 3, NULL, 37, '2026-02-28 05:40:37'),
(463, 3, NULL, 38, '2026-02-28 05:40:37'),
(464, 3, NULL, 39, '2026-02-28 05:40:37'),
(465, 3, NULL, 40, '2026-02-28 05:40:37'),
(466, 3, NULL, 41, '2026-02-28 05:40:37'),
(467, 3, NULL, 42, '2026-02-28 05:40:37'),
(468, 3, NULL, 43, '2026-02-28 05:40:37'),
(469, 3, NULL, 44, '2026-02-28 05:40:37'),
(470, 3, NULL, 45, '2026-02-28 05:40:37'),
(471, 3, NULL, 46, '2026-02-28 05:40:37'),
(472, 3, NULL, 47, '2026-02-28 05:40:37'),
(473, 3, NULL, 48, '2026-02-28 05:40:37'),
(474, 3, NULL, 49, '2026-02-28 05:40:37'),
(475, 3, NULL, 50, '2026-02-28 05:40:37'),
(476, 3, NULL, 51, '2026-02-28 05:40:37'),
(477, 3, NULL, 52, '2026-02-28 05:40:37'),
(478, 3, NULL, 53, '2026-02-28 05:40:37'),
(479, 3, NULL, 54, '2026-02-28 05:40:37'),
(480, 3, NULL, 55, '2026-02-28 05:40:37'),
(481, 3, NULL, 56, '2026-02-28 05:40:37'),
(482, 3, NULL, 57, '2026-02-28 05:40:37'),
(484, 3, NULL, 59, '2026-02-28 05:40:37'),
(485, 3, NULL, 60, '2026-02-28 05:40:37'),
(486, 3, NULL, 61, '2026-02-28 05:40:37'),
(487, 3, NULL, 62, '2026-02-28 05:40:37'),
(488, 3, NULL, 63, '2026-02-28 05:40:37'),
(489, 3, NULL, 64, '2026-02-28 05:40:37'),
(490, 3, NULL, 65, '2026-02-28 05:40:37'),
(491, 3, NULL, 66, '2026-02-28 05:40:37'),
(492, 3, NULL, 67, '2026-02-28 05:40:37'),
(493, 3, NULL, 68, '2026-02-28 05:40:37'),
(494, 3, NULL, 69, '2026-02-28 05:40:37'),
(495, 3, NULL, 70, '2026-02-28 05:40:37'),
(496, 3, NULL, 71, '2026-02-28 05:40:37'),
(497, 3, NULL, 72, '2026-02-28 05:40:37'),
(498, 3, NULL, 73, '2026-02-28 05:40:37'),
(499, 3, NULL, 74, '2026-02-28 05:40:37'),
(500, 3, NULL, 75, '2026-02-28 05:40:37'),
(501, 3, NULL, 76, '2026-02-28 05:40:37'),
(502, 3, NULL, 77, '2026-02-28 05:40:37'),
(503, 3, NULL, 78, '2026-02-28 05:40:37'),
(504, 3, NULL, 79, '2026-02-28 05:40:37'),
(506, 3, NULL, 81, '2026-02-28 05:40:37'),
(507, 3, NULL, 82, '2026-02-28 05:40:37'),
(508, 3, NULL, 83, '2026-02-28 05:40:37'),
(594, 7, NULL, 1, '2026-02-28 05:40:38'),
(595, 7, NULL, 2, '2026-02-28 05:40:38'),
(596, 7, NULL, 3, '2026-02-28 05:40:38'),
(597, 7, NULL, 4, '2026-02-28 05:40:38'),
(598, 7, NULL, 5, '2026-02-28 05:40:38'),
(599, 7, NULL, 6, '2026-02-28 05:40:38'),
(600, 7, NULL, 7, '2026-02-28 05:40:38'),
(601, 7, NULL, 8, '2026-02-28 05:40:38'),
(602, 7, NULL, 9, '2026-02-28 05:40:38'),
(603, 7, NULL, 10, '2026-02-28 05:40:38'),
(604, 7, NULL, 11, '2026-02-28 05:40:38'),
(605, 7, NULL, 12, '2026-02-28 05:40:38'),
(606, 7, NULL, 13, '2026-02-28 05:40:38'),
(607, 7, NULL, 14, '2026-02-28 05:40:38'),
(608, 7, NULL, 15, '2026-02-28 05:40:38'),
(609, 7, NULL, 16, '2026-02-28 05:40:38'),
(610, 7, NULL, 17, '2026-02-28 05:40:38'),
(611, 7, NULL, 18, '2026-02-28 05:40:38'),
(612, 7, NULL, 19, '2026-02-28 05:40:38'),
(613, 7, NULL, 20, '2026-02-28 05:40:38'),
(614, 7, NULL, 21, '2026-02-28 05:40:38'),
(615, 7, NULL, 22, '2026-02-28 05:40:38'),
(616, 7, NULL, 23, '2026-02-28 05:40:38'),
(617, 7, NULL, 24, '2026-02-28 05:40:38'),
(618, 7, NULL, 25, '2026-02-28 05:40:38'),
(619, 7, NULL, 26, '2026-02-28 05:40:38'),
(620, 7, NULL, 27, '2026-02-28 05:40:38'),
(621, 7, NULL, 28, '2026-02-28 05:40:38'),
(622, 7, NULL, 29, '2026-02-28 05:40:38'),
(623, 7, NULL, 30, '2026-02-28 05:40:38'),
(624, 7, NULL, 31, '2026-02-28 05:40:38'),
(625, 7, NULL, 32, '2026-02-28 05:40:38'),
(626, 7, NULL, 33, '2026-02-28 05:40:38'),
(627, 7, NULL, 34, '2026-02-28 05:40:38'),
(628, 7, NULL, 35, '2026-02-28 05:40:38'),
(629, 7, NULL, 36, '2026-02-28 05:40:38'),
(630, 7, NULL, 37, '2026-02-28 05:40:38'),
(631, 7, NULL, 38, '2026-02-28 05:40:38'),
(632, 7, NULL, 39, '2026-02-28 05:40:38'),
(633, 7, NULL, 40, '2026-02-28 05:40:38'),
(634, 7, NULL, 41, '2026-02-28 05:40:38'),
(635, 7, NULL, 42, '2026-02-28 05:40:38'),
(636, 7, NULL, 43, '2026-02-28 05:40:38'),
(637, 7, NULL, 44, '2026-02-28 05:40:38'),
(638, 7, NULL, 45, '2026-02-28 05:40:38'),
(639, 7, NULL, 46, '2026-02-28 05:40:38'),
(640, 7, NULL, 47, '2026-02-28 05:40:38'),
(641, 7, NULL, 48, '2026-02-28 05:40:38'),
(642, 7, NULL, 49, '2026-02-28 05:40:38'),
(643, 7, NULL, 50, '2026-02-28 05:40:38'),
(644, 7, NULL, 51, '2026-02-28 05:40:38'),
(645, 7, NULL, 52, '2026-02-28 05:40:38'),
(646, 7, NULL, 53, '2026-02-28 05:40:38'),
(647, 7, NULL, 54, '2026-02-28 05:40:38'),
(648, 7, NULL, 55, '2026-02-28 05:40:38'),
(649, 7, NULL, 56, '2026-02-28 05:40:38'),
(650, 7, NULL, 57, '2026-02-28 05:40:38'),
(652, 7, NULL, 59, '2026-02-28 05:40:38'),
(653, 7, NULL, 60, '2026-02-28 05:40:38'),
(654, 7, NULL, 61, '2026-02-28 05:40:38'),
(655, 7, NULL, 62, '2026-02-28 05:40:38'),
(656, 7, NULL, 63, '2026-02-28 05:40:38'),
(657, 7, NULL, 64, '2026-02-28 05:40:38'),
(658, 7, NULL, 65, '2026-02-28 05:40:38'),
(659, 7, NULL, 66, '2026-02-28 05:40:38'),
(660, 7, NULL, 67, '2026-02-28 05:40:38'),
(661, 7, NULL, 68, '2026-02-28 05:40:38'),
(662, 7, NULL, 69, '2026-02-28 05:40:38'),
(663, 7, NULL, 70, '2026-02-28 05:40:38'),
(664, 7, NULL, 71, '2026-02-28 05:40:38'),
(665, 7, NULL, 72, '2026-02-28 05:40:38'),
(666, 7, NULL, 73, '2026-02-28 05:40:38'),
(667, 7, NULL, 74, '2026-02-28 05:40:38'),
(668, 7, NULL, 75, '2026-02-28 05:40:38'),
(669, 7, NULL, 76, '2026-02-28 05:40:38'),
(670, 7, NULL, 77, '2026-02-28 05:40:38'),
(671, 7, NULL, 78, '2026-02-28 05:40:38'),
(672, 7, NULL, 79, '2026-02-28 05:40:38'),
(674, 7, NULL, 81, '2026-02-28 05:40:38'),
(675, 7, NULL, 82, '2026-02-28 05:40:38'),
(676, 7, NULL, 83, '2026-02-28 05:40:38'),
(729, 1, NULL, 1, '2026-03-04 08:01:42'),
(730, 1, NULL, 2, '2026-03-04 08:01:42'),
(731, 1, NULL, 17, '2026-03-04 08:01:42'),
(732, 1, NULL, 18, '2026-03-04 08:01:42'),
(733, 1, NULL, 19, '2026-03-04 08:01:42'),
(734, 1, NULL, 3, '2026-03-04 08:01:42'),
(735, 1, NULL, 4, '2026-03-04 08:01:42'),
(736, 1, NULL, 20, '2026-03-04 08:01:42'),
(737, 1, NULL, 21, '2026-03-04 08:01:42'),
(738, 1, NULL, 22, '2026-03-04 08:01:42'),
(739, 1, NULL, 5, '2026-03-04 08:01:42'),
(740, 1, NULL, 23, '2026-03-04 08:01:42'),
(741, 1, NULL, 24, '2026-03-04 08:01:42'),
(742, 1, NULL, 25, '2026-03-04 08:01:42'),
(743, 1, NULL, 6, '2026-03-04 08:01:42'),
(744, 1, NULL, 26, '2026-03-04 08:01:42'),
(745, 1, NULL, 27, '2026-03-04 08:01:42'),
(746, 1, NULL, 28, '2026-03-04 08:01:42'),
(747, 1, NULL, 29, '2026-03-04 08:01:42'),
(748, 1, NULL, 30, '2026-03-04 08:01:42'),
(749, 1, NULL, 89, '2026-03-04 08:01:42'),
(750, 1, NULL, 90, '2026-03-04 08:01:42'),
(751, 1, NULL, 31, '2026-03-04 08:01:42'),
(752, 1, NULL, 32, '2026-03-04 08:01:42'),
(753, 1, NULL, 33, '2026-03-04 08:01:42'),
(754, 1, NULL, 34, '2026-03-04 08:01:42'),
(755, 1, NULL, 67, '2026-03-04 08:01:42'),
(756, 1, NULL, 66, '2026-03-04 08:01:42'),
(757, 1, NULL, 91, '2026-03-04 08:01:42'),
(758, 1, NULL, 7, '2026-03-04 08:01:42'),
(759, 1, NULL, 35, '2026-03-04 08:01:42'),
(760, 1, NULL, 36, '2026-03-04 08:01:42'),
(761, 1, NULL, 37, '2026-03-04 08:01:42'),
(762, 1, NULL, 38, '2026-03-04 08:01:42'),
(763, 1, NULL, 8, '2026-03-04 08:01:42'),
(764, 1, NULL, 39, '2026-03-04 08:01:42'),
(765, 1, NULL, 40, '2026-03-04 08:01:42'),
(766, 1, NULL, 44, '2026-03-04 08:01:42'),
(767, 1, NULL, 43, '2026-03-04 08:01:42'),
(768, 1, NULL, 42, '2026-03-04 08:01:42'),
(769, 1, NULL, 129, '2026-03-04 08:01:42'),
(770, 1, NULL, 41, '2026-03-04 08:01:42'),
(771, 1, NULL, 9, '2026-03-04 08:01:42'),
(772, 1, NULL, 46, '2026-03-04 08:01:42'),
(773, 1, NULL, 47, '2026-03-04 08:01:42'),
(774, 1, NULL, 10, '2026-03-04 08:01:42'),
(775, 1, NULL, 86, '2026-03-04 08:01:42'),
(776, 1, NULL, 48, '2026-03-04 08:01:42'),
(777, 1, NULL, 49, '2026-03-04 08:01:42'),
(778, 1, NULL, 87, '2026-03-04 08:01:42'),
(779, 1, NULL, 50, '2026-03-04 08:01:42'),
(780, 1, NULL, 88, '2026-03-04 08:01:42'),
(781, 1, NULL, 99, '2026-03-04 08:01:42'),
(782, 1, NULL, 100, '2026-03-04 08:01:42'),
(783, 1, NULL, 101, '2026-03-04 08:01:42'),
(784, 1, NULL, 102, '2026-03-04 08:01:42'),
(785, 1, NULL, 103, '2026-03-04 08:01:42'),
(786, 1, NULL, 11, '2026-03-04 08:01:42'),
(787, 1, NULL, 51, '2026-03-04 08:01:42'),
(788, 1, NULL, 52, '2026-03-04 08:01:42'),
(789, 1, NULL, 53, '2026-03-04 08:01:42'),
(790, 1, NULL, 92, '2026-03-04 08:01:42'),
(791, 1, NULL, 108, '2026-03-04 08:01:42'),
(792, 1, NULL, 109, '2026-03-04 08:01:42'),
(793, 1, NULL, 110, '2026-03-04 08:01:42'),
(794, 1, NULL, 111, '2026-03-04 08:01:42'),
(795, 1, NULL, 112, '2026-03-04 08:01:42'),
(796, 1, NULL, 113, '2026-03-04 08:01:42'),
(797, 1, NULL, 114, '2026-03-04 08:01:42'),
(798, 1, NULL, 115, '2026-03-04 08:01:42'),
(799, 1, NULL, 12, '2026-03-04 08:01:42'),
(800, 1, NULL, 54, '2026-03-04 08:01:42'),
(801, 1, NULL, 59, '2026-03-04 08:01:42'),
(802, 1, NULL, 55, '2026-03-04 08:01:42'),
(803, 1, NULL, 57, '2026-03-04 08:01:42'),
(804, 1, NULL, 94, '2026-03-04 08:01:42'),
(805, 1, NULL, 93, '2026-03-04 08:01:42'),
(806, 1, NULL, 56, '2026-03-04 08:01:42'),
(807, 1, NULL, 95, '2026-03-04 08:01:42'),
(808, 1, NULL, 131, '2026-03-04 08:01:42'),
(809, 1, NULL, 132, '2026-03-04 08:01:42'),
(810, 1, NULL, 133, '2026-03-04 08:01:42'),
(811, 1, NULL, 134, '2026-03-04 08:01:42'),
(812, 1, NULL, 13, '2026-03-04 08:01:42'),
(813, 1, NULL, 60, '2026-03-04 08:01:42'),
(814, 1, NULL, 61, '2026-03-04 08:01:42'),
(815, 1, NULL, 64, '2026-03-04 08:01:42'),
(816, 1, NULL, 62, '2026-03-04 08:01:42'),
(817, 1, NULL, 63, '2026-03-04 08:01:42'),
(818, 1, NULL, 65, '2026-03-04 08:01:42'),
(819, 1, NULL, 97, '2026-03-04 08:01:42'),
(820, 1, NULL, 98, '2026-03-04 08:01:42'),
(821, 1, NULL, 116, '2026-03-04 08:01:42'),
(822, 1, NULL, 117, '2026-03-04 08:01:42'),
(823, 1, NULL, 118, '2026-03-04 08:01:42'),
(824, 1, NULL, 119, '2026-03-04 08:01:42'),
(825, 1, NULL, 127, '2026-03-04 08:01:42'),
(826, 1, NULL, 128, '2026-03-04 08:01:42'),
(827, 1, NULL, 135, '2026-03-04 08:01:42'),
(828, 1, NULL, 136, '2026-03-04 08:01:42'),
(829, 1, NULL, 137, '2026-03-04 08:01:42'),
(830, 1, NULL, 138, '2026-03-04 08:01:42'),
(831, 1, NULL, 139, '2026-03-04 08:01:42'),
(832, 1, NULL, 14, '2026-03-04 08:01:42'),
(833, 1, NULL, 15, '2026-03-04 08:01:42'),
(834, 1, NULL, 68, '2026-03-04 08:01:42'),
(835, 1, NULL, 69, '2026-03-04 08:01:42'),
(836, 1, NULL, 70, '2026-03-04 08:01:42'),
(837, 1, NULL, 71, '2026-03-04 08:01:42'),
(838, 1, NULL, 72, '2026-03-04 08:01:42'),
(839, 1, NULL, 73, '2026-03-04 08:01:42'),
(840, 1, NULL, 74, '2026-03-04 08:01:42'),
(841, 1, NULL, 75, '2026-03-04 08:01:42'),
(842, 1, NULL, 76, '2026-03-04 08:01:42'),
(843, 1, NULL, 77, '2026-03-04 08:01:42'),
(844, 1, NULL, 78, '2026-03-04 08:01:42'),
(845, 1, NULL, 79, '2026-03-04 08:01:42'),
(846, 1, NULL, 130, '2026-03-04 08:01:42'),
(847, 1, NULL, 16, '2026-03-04 08:01:42'),
(848, 1, NULL, 81, '2026-03-04 08:01:42'),
(849, 1, NULL, 45, '2026-03-04 08:01:42'),
(850, 1, NULL, 104, '2026-03-04 08:01:42'),
(851, 1, NULL, 82, '2026-03-04 08:01:42'),
(852, 1, NULL, 83, '2026-03-04 08:01:43'),
(853, 1, NULL, 105, '2026-03-04 08:01:43'),
(854, 1, NULL, 106, '2026-03-04 08:01:43'),
(855, 1, NULL, 107, '2026-03-04 08:01:43'),
(862, 6, NULL, 1, '2026-03-05 05:07:33'),
(863, 6, NULL, 2, '2026-03-05 05:07:33'),
(864, 6, NULL, 17, '2026-03-05 05:07:33'),
(865, 6, NULL, 18, '2026-03-05 05:07:33'),
(866, 6, NULL, 19, '2026-03-05 05:07:33'),
(867, 6, NULL, 85, '2026-03-05 05:07:33'),
(868, 6, NULL, 6, '2026-03-05 05:07:33'),
(869, 6, NULL, 89, '2026-03-05 05:07:33'),
(870, 6, NULL, 10, '2026-03-05 05:07:33'),
(871, 6, NULL, 86, '2026-03-05 05:07:33'),
(872, 6, NULL, 48, '2026-03-05 05:07:33'),
(873, 6, NULL, 49, '2026-03-05 05:07:33'),
(874, 6, NULL, 87, '2026-03-05 05:07:33'),
(875, 6, NULL, 50, '2026-03-05 05:07:33'),
(876, 6, NULL, 88, '2026-03-05 05:07:33'),
(877, 6, NULL, 99, '2026-03-05 05:07:33'),
(878, 6, NULL, 100, '2026-03-05 05:07:33'),
(879, 6, NULL, 101, '2026-03-05 05:07:33'),
(880, 6, NULL, 102, '2026-03-05 05:07:33'),
(881, 6, NULL, 103, '2026-03-05 05:07:33');

-- --------------------------------------------------------

--
-- Table structure for table `salaries`
--

CREATE TABLE `salaries` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `basic_salary` decimal(15,2) NOT NULL,
  `allowances` decimal(15,2) DEFAULT 0.00,
  `deductions` decimal(15,2) DEFAULT 0.00,
  `net_salary` decimal(15,2) NOT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salaries`
--

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `sale_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `due_amount` decimal(15,2) DEFAULT 0.00,
  `payment_status` enum('paid','partial','unpaid') NOT NULL DEFAULT 'unpaid',
  `status` enum('draft','completed','cancelled') NOT NULL DEFAULT 'completed',
  `payment_method` varchar(50) DEFAULT 'cash',
  `notes` text DEFAULT NULL,
  `cash_handover` decimal(15,2) DEFAULT 0.00,
  `staff_id` int(11) DEFAULT NULL,
  `sold_by` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

-- --------------------------------------------------------

--
-- Table structure for table `sales_requests`
--

CREATE TABLE `sales_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(50) DEFAULT NULL,
  `sr_user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`items`)),
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','approved','processing','completed','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `completed_sale_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_requests`
--

-- --------------------------------------------------------

--
-- Table structure for table `sales_returns`
--

CREATE TABLE `sales_returns` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `return_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_returns`
--

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `tax` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

-- --------------------------------------------------------

--
-- Table structure for table `sale_payments`
--

CREATE TABLE `sale_payments` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'cash',
  `account_id` int(11) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_payments`
--

-- --------------------------------------------------------

--
-- Table structure for table `sale_return_items`
--

CREATE TABLE `sale_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `return_type` enum('stock','rma') NOT NULL DEFAULT 'stock',
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_return_items`
--

-- --------------------------------------------------------

--
-- Table structure for table `sale_return_item_serials`
--

CREATE TABLE `sale_return_item_serials` (
  `id` int(11) NOT NULL,
  `return_item_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_return_item_serials`
--

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_messages`
--

CREATE TABLE `scheduled_messages` (
  `id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `target_type` enum('all','group','individual') DEFAULT 'individual',
  `target_data` text DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT '09:00:00',
  `status` enum('pending','sent','cancelled') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `server_licenses`
--

CREATE TABLE `server_licenses` (
  `id` int(11) NOT NULL,
  `license_key` varchar(50) NOT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'inactive',
  `expiry_date` date DEFAULT NULL,
  `registered_domain` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `server_licenses`
--

INSERT INTO `server_licenses` (`id`, `license_key`, `client_name`, `status`, `expiry_date`, `registered_domain`, `created_at`) VALUES
(2, 'QW2K-BRA6-XSSB-AKB0', 'Test Company Integration', 'active', NULL, NULL, '2026-01-31 03:13:11');

-- --------------------------------------------------------

--
-- Table structure for table `service_centers`
--

CREATE TABLE `service_centers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_status_log`
--

CREATE TABLE `service_status_log` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_status_log`
--

-- --------------------------------------------------------

--
-- Table structure for table `service_tickets`
--

CREATE TABLE `service_tickets` (
  `id` int(11) NOT NULL,
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_tickets`
--

-- --------------------------------------------------------

--
-- Table structure for table `service_ticket_parts`
--

CREATE TABLE `service_ticket_parts` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `serial_numbers` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_ticket_parts`
--

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `early_checkin_time` time DEFAULT NULL,
  `late_mark_time` time DEFAULT NULL,
  `max_checkin_time` time DEFAULT NULL,
  `shift_segments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shift_segments`)),
  `late_after_minutes` int(11) DEFAULT 15,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

-- --------------------------------------------------------

--
-- Table structure for table `sms_settings`
--

CREATE TABLE `sms_settings` (
  `id` int(11) NOT NULL,
  `api_url` varchar(255) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `sender_id` varchar(50) DEFAULT NULL,
  `sales_sms_enabled` tinyint(1) DEFAULT 0,
  `warranty_sms_enabled` tinyint(1) DEFAULT 0,
  `sales_sms_template` text DEFAULT NULL,
  `warranty_sms_template` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `sale_notify_channels` varchar(255) DEFAULT '["sms"]',
  `warranty_notify_channels` varchar(255) DEFAULT '["sms"]'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_settings`
--

INSERT INTO `sms_settings` (`id`, `api_url`, `api_key`, `sender_id`, `sales_sms_enabled`, `warranty_sms_enabled`, `sales_sms_template`, `warranty_sms_template`, `created_at`, `updated_at`, `sale_notify_channels`, `warranty_notify_channels`) VALUES
(1, 'http://bulksmsbd.net/api/getBalanceApi?api_key=VEP6ZZQL7jtVPWHtVjVn', 'VEP6ZZQL7jtVPWHtVjVn', '8809648905803', 1, 0, 'Hello {customer_name}, your sale {invoice_number} is complete. Paid: ', 'Hello {customer_name}, your warranty for {product_name} ({serial_number}) expires soon.', '2026-01-30 15:52:41', '2026-03-10 10:41:51', '[\"sms\"]', '[\"sms\"]');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT 0.00,
  `department_id` int(11) DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `nid_number` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `job_type` enum('permanent','contract','probation','intern') DEFAULT 'permanent',
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `bank_account_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `fingerprint_template` text DEFAULT NULL COMMENT 'Fingerprint biometric data (JSON array of templates)',
  `card_id` varchar(50) DEFAULT NULL COMMENT 'RFID/Card number',
  `face_template` text DEFAULT NULL COMMENT 'Face recognition data',
  `enrolled_at` datetime DEFAULT NULL COMMENT 'Device enrollment date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

-- --------------------------------------------------------

--
-- Table structure for table `staff_biometrics`
--

CREATE TABLE `staff_biometrics` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `biometric_type` enum('face','webauthn') NOT NULL DEFAULT 'face',
  `face_descriptor` text DEFAULT NULL COMMENT 'JSON array of face-api.js descriptor (128 floats)',
  `face_photo` varchar(255) DEFAULT NULL COMMENT 'Path to enrollment photo',
  `webauthn_credential_id` text DEFAULT NULL COMMENT 'Base64 credential ID',
  `webauthn_public_key` text DEFAULT NULL COMMENT 'Base64 public key',
  `webauthn_counter` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_biometrics`
--

-- --------------------------------------------------------

--
-- Table structure for table `staff_broadcasts`
--

CREATE TABLE `staff_broadcasts` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `sent_to` text DEFAULT NULL,
  `channel` enum('sms','email','whatsapp','telegram','system') DEFAULT 'system',
  `sent_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_departments`
--

CREATE TABLE `staff_departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_departments`
--

INSERT INTO `staff_departments` (`id`, `name`, `description`, `created_at`) VALUES
(2, 'IT Support', '', '2026-02-27 14:38:04');

-- --------------------------------------------------------

--
-- Table structure for table `staff_documents`
--

CREATE TABLE `staff_documents` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(100) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_roles`
--

CREATE TABLE `staff_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_roles`
--

INSERT INTO `staff_roles` (`id`, `role_name`, `description`, `created_at`, `updated_at`) VALUES
(11, 'Manager', '', '2026-02-27 14:46:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_tasks`
--

CREATE TABLE `staff_tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `service_ticket_id` int(11) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `deadline` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `adjustment_type` enum('add','subtract','opening_stock') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `date` date NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_adjustments`
--

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustment_serials`
--

CREATE TABLE `stock_adjustment_serials` (
  `id` int(11) NOT NULL,
  `adjustment_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_adjustment_serials`
--

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL,
  `transfer_number` varchar(50) NOT NULL,
  `transfer_date` date NOT NULL,
  `from_warehouse_id` int(11) DEFAULT NULL,
  `to_warehouse_id` int(11) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `challan_number` varchar(100) DEFAULT NULL,
  `transfer_type` enum('normal','current_to_rma','rma_to_current','rma_to_damaged','damaged_to_rma') DEFAULT 'normal',
  `status` enum('pending','approved','in_transit','completed','rejected','cancelled') DEFAULT 'pending',
  `total_items` int(11) DEFAULT 0,
  `total_quantity` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_transfers`
--

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfer_items`
--

CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `serial_numbers` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_transfer_items`
--

-- --------------------------------------------------------

--
-- Table structure for table `stock_type_inventory`
--

CREATE TABLE `stock_type_inventory` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `stock_type` enum('rma','damaged') NOT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks RMA and Damaged stock quantities per product';

--
-- Dumping data for table `stock_type_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `payment_terms` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

-- --------------------------------------------------------

--
-- Table structure for table `supplier_ledger`
--

CREATE TABLE `supplier_ledger` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL COMMENT 'purchase, payment, return, opening_balance',
  `reference_id` int(11) DEFAULT NULL,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_ledger`
--

-- --------------------------------------------------------

--
-- Table structure for table `system_updates`
--

CREATE TABLE `system_updates` (
  `id` int(11) NOT NULL,
  `version` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `download_url` varchar(500) DEFAULT NULL,
  `changelog` text DEFAULT NULL,
  `status` enum('available','downloading','installing','completed','failed') DEFAULT 'available',
  `installed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `short_name` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `name`, `short_name`, `created_at`) VALUES
(13, 'Pcs', 'pcs', '2026-02-04 16:59:56'),
(14, 'Meter', 'M', '2026-02-10 14:08:56'),
(15, 'Box', 'B', '2026-02-10 14:09:31');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `base_salary` decimal(15,2) DEFAULT 0.00,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 2,
  `photo` varchar(255) DEFAULT NULL,
  `theme_preference` enum('light','dark') DEFAULT 'dark',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `shift_id`, `base_salary`, `password_hash`, `role_id`, `photo`, `theme_preference`, `status`, `last_login`, `last_login_ip`, `created_at`, `updated_at`) VALUES
(1, 'Johir', 'admin@cleansbuy.com', NULL, NULL, 0.00, '$2y$10$PZJ727tK79eMwtZ9hTIhIe68zbaIMHfqaoGAslBoCQ6ikz5FQM/gS', 1, NULL, 'light', 'active', '2026-03-13 11:07:39', '::1', '2026-01-26 01:49:30', '2026-03-13 05:07:39');

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_saleable` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `yearly_closings`
--

CREATE TABLE `yearly_closings` (
  `id` int(11) NOT NULL,
  `closing_year` year(4) NOT NULL,
  `closing_date` date NOT NULL,
  `total_revenue` decimal(15,2) DEFAULT 0.00,
  `total_expenses` decimal(15,2) DEFAULT 0.00,
  `total_profit` decimal(15,2) DEFAULT 0.00,
  `total_assets` decimal(15,2) DEFAULT 0.00,
  `total_liabilities` decimal(15,2) DEFAULT 0.00,
  `net_worth` decimal(15,2) DEFAULT 0.00,
  `bank_balance` decimal(15,2) DEFAULT 0.00,
  `cash_balance` decimal(15,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` enum('draft','finalized','audited') DEFAULT 'draft',
  `closed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `app_license`
--
ALTER TABLE `app_license`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `attendance_devices`
--
ALTER TABLE `attendance_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_sn` (`device_sn`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_device_sn` (`device_sn`);

--
-- Indexes for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_date` (`staff_id`,`punch_time`),
  ADD KEY `idx_device_time` (`device_id`,`punch_time`),
  ADD KEY `idx_punch_time` (`punch_time`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `attendance_settings`
--
ALTER TABLE `attendance_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `attendance_summary`
--
ALTER TABLE `attendance_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_staff_date` (`staff_id`,`date`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_backup_type` (`backup_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `backup_settings`
--
ALTER TABLE `backup_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `balance_transfers`
--
ALTER TABLE `balance_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transfer_date` (`transfer_date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `transaction_date` (`transaction_date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `bill_collections`
--
ALTER TABLE `bill_collections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `business_settings`
--
ALTER TABLE `business_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cash_accounts`
--
ALTER TABLE `cash_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cash_closings`
--
ALTER TABLE `cash_closings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `closing_date` (`closing_date`),
  ADD KEY `closed_by` (`closed_by`);

--
-- Indexes for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `transaction_date` (`transaction_date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `cctv_configurations`
--
ALTER TABLE `cctv_configurations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cctv_tracking_logs`
--
ALTER TABLE `cctv_tracking_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_date` (`staff_id`,`date`),
  ADD KEY `idx_camera` (`camera_id`);

--
-- Indexes for table `channel_settings`
--
ALTER TABLE `channel_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `channel` (`channel`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `phone` (`phone`);

--
-- Indexes for table `customer_ledger`
--
ALTER TABLE `customer_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `damaged_stock`
--
ALTER TABLE `damaged_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_variant_id` (`variant_id`),
  ADD KEY `idx_stock_type` (`stock_type`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `damaged_stock_serials`
--
ALTER TABLE `damaged_stock_serials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_damaged_stock_id` (`damaged_stock_id`),
  ADD KEY `idx_serial_id` (`serial_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `device_heartbeat_log`
--
ALTER TABLE `device_heartbeat_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device_time` (`device_id`,`heartbeat_time`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `expense_date` (`expense_date`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_expenses_account` (`account_id`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `import_history`
--
ALTER TABLE `import_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_imported_at` (`imported_at`),
  ADD KEY `imported_by` (`imported_by`);

--
-- Indexes for table `invoice_settings`
--
ALTER TABLE `invoice_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_collected_by` (`collected_by`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `lead_categories`
--
ALTER TABLE `lead_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`),
  ADD KEY `attempted_at` (`attempted_at`);

--
-- Indexes for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `marketing_campaign_logs`
--
ALTER TABLE `marketing_campaign_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campaign` (`campaign_id`);

--
-- Indexes for table `marketing_settings`
--
ALTER TABLE `marketing_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `marketing_templates`
--
ALTER TABLE `marketing_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `message_log`
--
ALTER TABLE `message_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `message_templates`
--
ALTER TABLE `message_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `party_cash`
--
ALTER TABLE `party_cash`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `module` (`module`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `barcode` (`barcode`),
  ADD KEY `brand_id` (`brand_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `product_serials`
--
ALTER TABLE `product_serials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD UNIQUE KEY `imei` (`imei`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `status` (`status`),
  ADD KEY `sale_item_id` (`sale_item_id`),
  ADD KEY `idx_stock_type` (`stock_type`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `sku` (`sku`);

--
-- Indexes for table `product_warehouse_stock`
--
ALTER TABLE `product_warehouse_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_warehouse` (`product_id`,`warehouse_id`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchase_number` (`purchase_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `purchase_date` (`purchase_date`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `purchase_payments`
--
ALTER TABLE `purchase_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `fk_pp_created_by` (`created_by`),
  ADD KEY `idx_account_id` (`account_id`);

--
-- Indexes for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_serial_id` (`serial_id`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_number` (`quotation_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_id` (`quotation_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `rma_external_replacements`
--
ALTER TABLE `rma_external_replacements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rma_id` (`rma_id`);

--
-- Indexes for table `rma_requests`
--
ALTER TABLE `rma_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rma_number` (`rma_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `status` (`status`),
  ADD KEY `service_center_id` (`service_center_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_rma_new_serial` (`new_serial_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_menu` (`role_id`,`menu_item_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `menu_item_id` (`menu_item_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `salaries`
--
ALTER TABLE `salaries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `sale_date` (`sale_date`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `sales_requests`
--
ALTER TABLE `sales_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `idx_sr_user` (`sr_user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `sales_returns`
--
ALTER TABLE `sales_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `fk_sale_payments_created_by` (`created_by`);

--
-- Indexes for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sale_return_item_serials`
--
ALTER TABLE `sale_return_item_serials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_item_id` (`return_item_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `scheduled_messages`
--
ALTER TABLE `scheduled_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`scheduled_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `server_licenses`
--
ALTER TABLE `server_licenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`);

--
-- Indexes for table `service_centers`
--
ALTER TABLE `service_centers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `service_status_log`
--
ALTER TABLE `service_status_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `service_tickets`
--
ALTER TABLE `service_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `status` (`status`),
  ADD KEY `customer_phone` (`customer_phone`);

--
-- Indexes for table `service_ticket_parts`
--
ALTER TABLE `service_ticket_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sms_settings`
--
ALTER TABLE `sms_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `idx_card_id` (`card_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `staff_biometrics`
--
ALTER TABLE `staff_biometrics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_type` (`biometric_type`);

--
-- Indexes for table `staff_broadcasts`
--
ALTER TABLE `staff_broadcasts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_departments`
--
ALTER TABLE `staff_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_roles`
--
ALTER TABLE `staff_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `date` (`date`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `stock_adjustment_serials`
--
ALTER TABLE `stock_adjustment_serials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_adjustment_id` (`adjustment_id`),
  ADD KEY `idx_serial_id` (`serial_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transfer_number` (`transfer_number`),
  ADD KEY `from_warehouse_id` (`from_warehouse_id`),
  ADD KEY `to_warehouse_id` (`to_warehouse_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transfer_id` (`transfer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stock_type_inventory`
--
ALTER TABLE `stock_type_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_type` (`product_id`,`stock_type`),
  ADD KEY `idx_product_type` (`product_id`,`stock_type`),
  ADD KEY `warehouse_id` (`warehouse_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `supplier_ledger`
--
ALTER TABLE `supplier_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `date` (`date`);

--
-- Indexes for table `system_updates`
--
ALTER TABLE `system_updates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `yearly_closings`
--
ALTER TABLE `yearly_closings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_year` (`closing_year`),
  ADD KEY `closed_by` (`closed_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_year` (`closing_year`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=848;

--
-- AUTO_INCREMENT for table `app_license`
--
ALTER TABLE `app_license`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `attendance_devices`
--
ALTER TABLE `attendance_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT for table `attendance_settings`
--
ALTER TABLE `attendance_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `attendance_summary`
--
ALTER TABLE `attendance_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_history`
--
ALTER TABLE `backup_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `backup_settings`
--
ALTER TABLE `backup_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `balance_transfers`
--
ALTER TABLE `balance_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `bill_collections`
--
ALTER TABLE `bill_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `business_settings`
--
ALTER TABLE `business_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cash_accounts`
--
ALTER TABLE `cash_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cash_closings`
--
ALTER TABLE `cash_closings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `cctv_configurations`
--
ALTER TABLE `cctv_configurations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cctv_tracking_logs`
--
ALTER TABLE `cctv_tracking_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `channel_settings`
--
ALTER TABLE `channel_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `customer_ledger`
--
ALTER TABLE `customer_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `damaged_stock`
--
ALTER TABLE `damaged_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `damaged_stock_serials`
--
ALTER TABLE `damaged_stock_serials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `device_heartbeat_log`
--
ALTER TABLE `device_heartbeat_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `import_history`
--
ALTER TABLE `import_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `invoice_settings`
--
ALTER TABLE `invoice_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lead_categories`
--
ALTER TABLE `lead_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `marketing_campaigns`
--
ALTER TABLE `marketing_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marketing_campaign_logs`
--
ALTER TABLE `marketing_campaign_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marketing_settings`
--
ALTER TABLE `marketing_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `marketing_templates`
--
ALTER TABLE `marketing_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

--
-- AUTO_INCREMENT for table `message_log`
--
ALTER TABLE `message_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `message_templates`
--
ALTER TABLE `message_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `party_cash`
--
ALTER TABLE `party_cash`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `product_serials`
--
ALTER TABLE `product_serials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_warehouse_stock`
--
ALTER TABLE `product_warehouse_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `purchase_payments`
--
ALTER TABLE `purchase_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `rma_external_replacements`
--
ALTER TABLE `rma_external_replacements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `rma_requests`
--
ALTER TABLE `rma_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=882;

--
-- AUTO_INCREMENT for table `salaries`
--
ALTER TABLE `salaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `sales_requests`
--
ALTER TABLE `sales_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sales_returns`
--
ALTER TABLE `sales_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `sale_payments`
--
ALTER TABLE `sale_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `sale_return_item_serials`
--
ALTER TABLE `sale_return_item_serials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `scheduled_messages`
--
ALTER TABLE `scheduled_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `server_licenses`
--
ALTER TABLE `server_licenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `service_centers`
--
ALTER TABLE `service_centers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `service_status_log`
--
ALTER TABLE `service_status_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `service_tickets`
--
ALTER TABLE `service_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `service_ticket_parts`
--
ALTER TABLE `service_ticket_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sms_settings`
--
ALTER TABLE `sms_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `staff_biometrics`
--
ALTER TABLE `staff_biometrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff_broadcasts`
--
ALTER TABLE `staff_broadcasts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_departments`
--
ALTER TABLE `staff_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_documents`
--
ALTER TABLE `staff_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_roles`
--
ALTER TABLE `staff_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `stock_adjustment_serials`
--
ALTER TABLE `stock_adjustment_serials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `stock_type_inventory`
--
ALTER TABLE `stock_type_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `supplier_ledger`
--
ALTER TABLE `supplier_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `system_updates`
--
ALTER TABLE `system_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `yearly_closings`
--
ALTER TABLE `yearly_closings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `attendance_devices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_summary`
--
ALTER TABLE `attendance_summary`
  ADD CONSTRAINT `attendance_summary_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `balance_transfers`
--
ALTER TABLE `balance_transfers`
  ADD CONSTRAINT `balance_transfers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD CONSTRAINT `bank_transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bank_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bill_collections`
--
ALTER TABLE `bill_collections`
  ADD CONSTRAINT `bill_collections_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bill_collections_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_closings`
--
ALTER TABLE `cash_closings`
  ADD CONSTRAINT `cash_closings_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD CONSTRAINT `cash_transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cash_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_ledger`
--
ALTER TABLE `customer_ledger`
  ADD CONSTRAINT `customer_ledger_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `damaged_stock_serials`
--
ALTER TABLE `damaged_stock_serials`
  ADD CONSTRAINT `fk_damaged_stock_serials_damaged` FOREIGN KEY (`damaged_stock_id`) REFERENCES `damaged_stock` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_damaged_stock_serials_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_damaged_stock_serials_serial` FOREIGN KEY (`serial_id`) REFERENCES `product_serials` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `device_heartbeat_log`
--
ALTER TABLE `device_heartbeat_log`
  ADD CONSTRAINT `device_heartbeat_log_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `attendance_devices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `import_history`
--
ALTER TABLE `import_history`
  ADD CONSTRAINT `import_history_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `party_cash`
--
ALTER TABLE `party_cash`
  ADD CONSTRAINT `party_cash_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `party_cash_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_3` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_serials`
--
ALTER TABLE `product_serials`
  ADD CONSTRAINT `product_serials_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_warehouse_stock`
--
ALTER TABLE `product_warehouse_stock`
  ADD CONSTRAINT `product_warehouse_stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_warehouse_stock_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `purchase_payments`
--
ALTER TABLE `purchase_payments`
  ADD CONSTRAINT `fk_pp_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_payments_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  ADD CONSTRAINT `purchase_returns_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`),
  ADD CONSTRAINT `purchase_returns_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_returns_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD CONSTRAINT `purchase_return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `purchase_returns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_return_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `purchase_return_items_ibfk_3` FOREIGN KEY (`serial_id`) REFERENCES `product_serials` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `quotations`
--
ALTER TABLE `quotations`
  ADD CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quotations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD CONSTRAINT `quotation_items_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quotation_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `rma_external_replacements`
--
ALTER TABLE `rma_external_replacements`
  ADD CONSTRAINT `rma_external_replacements_ibfk_1` FOREIGN KEY (`rma_id`) REFERENCES `rma_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rma_requests`
--
ALTER TABLE `rma_requests`
  ADD CONSTRAINT `fk_rma_new_serial` FOREIGN KEY (`new_serial_id`) REFERENCES `product_serials` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rma_requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rma_requests_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `rma_requests_ibfk_3` FOREIGN KEY (`service_center_id`) REFERENCES `service_centers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rma_requests_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salaries`
--
ALTER TABLE `salaries`
  ADD CONSTRAINT `salaries_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sales_returns`
--
ALTER TABLE `sales_returns`
  ADD CONSTRAINT `sales_returns_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  ADD CONSTRAINT `sales_returns_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sales_returns_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD CONSTRAINT `fk_sale_payments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sale_payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sale_return_items`
--
ALTER TABLE `sale_return_items`
  ADD CONSTRAINT `sale_return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `sales_returns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_return_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `service_status_log`
--
ALTER TABLE `service_status_log`
  ADD CONSTRAINT `fk_ssl_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `service_tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_ticket_parts`
--
ALTER TABLE `service_ticket_parts`
  ADD CONSTRAINT `fk_stp_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `service_tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `stock_adjustments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_adjustments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_adjustment_serials`
--
ALTER TABLE `stock_adjustment_serials`
  ADD CONSTRAINT `fk_stock_adj_serials_adjustment` FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_adj_serials_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_adj_serials_serial` FOREIGN KEY (`serial_id`) REFERENCES `product_serials` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD CONSTRAINT `stock_transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_transfer_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `stock_type_inventory`
--
ALTER TABLE `stock_type_inventory`
  ADD CONSTRAINT `stock_type_inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_ledger`
--
ALTER TABLE `supplier_ledger`
  ADD CONSTRAINT `supplier_ledger_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD CONSTRAINT `warehouses_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `yearly_closings`
--
ALTER TABLE `yearly_closings`
  ADD CONSTRAINT `yearly_closings_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
