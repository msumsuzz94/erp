-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: business_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'update_settings','Updated business settings','::1','2026-01-25 14:01:54'),(2,1,'update_settings','Updated business settings','::1','2026-01-25 14:03:52'),(3,1,'login','User logged in','::1','2026-01-25 14:04:16'),(4,1,'add_user','Added user: accountant','::1','2026-01-25 14:25:51'),(5,1,'login','User logged in','::1','2026-01-25 17:51:13'),(6,1,'add_user','Added user: cashier','::1','2026-01-25 17:52:01'),(7,1,'logout','User logged out','::1','2026-01-25 18:05:05'),(8,1,'login','User logged in','::1','2026-01-25 18:05:12'),(9,1,'login','User logged in','::1','2026-01-25 18:08:40'),(10,1,'staff_create','Added new staff: shimul','::1','2026-01-26 09:07:06'),(11,1,'settings_update','Updated business settings','::1','2026-01-26 09:38:11'),(12,1,'settings_update','Updated business settings','::1','2026-01-26 09:38:39'),(13,1,'create_sale','Created sale: INV-000001','::1','2026-01-26 10:00:47'),(14,1,'login','User logged in','::1','2026-01-26 14:34:06'),(15,1,'settings_update','Updated business settings','::1','2026-01-26 14:34:17'),(16,1,'login','User logged in','::1','2026-01-27 02:27:27'),(17,1,'edit_user','Updated user: accountant','::1','2026-01-27 02:36:56'),(18,1,'opening_stock','Added opening stock for product ID: 2','::1','2026-01-27 02:52:24'),(19,1,'opening_stock','Added opening stock for product ID: 5','::1','2026-01-27 02:53:12'),(20,1,'opening_stock','Added opening stock for product ID: 2','::1','2026-01-27 02:55:05'),(21,1,'opening_stock','Added opening stock for product ID: 3','::1','2026-01-27 02:56:10'),(22,1,'login','User logged in','::1','2026-01-27 02:58:41'),(23,1,'opening_stock','Added opening stock for product ID: 2','::1','2026-01-27 02:58:53'),(24,1,'damaged_stock','Recorded damaged stock for product ID: 5','::1','2026-01-27 03:00:19'),(25,1,'login','User logged in','::1','2026-01-27 03:01:33'),(26,1,'opening_stock','Added opening stock for product ID: 3','::1','2026-01-27 03:01:47'),(27,1,'opening_stock','Added opening stock for product ID: 2','::1','2026-01-27 03:04:30'),(28,1,'login','User logged in','::1','2026-01-27 03:06:29'),(29,1,'opening_stock','Added opening stock for product ID: 2','::1','2026-01-27 03:06:40'),(30,1,'opening_stock','Added opening stock for product ID: 2','::1','2026-01-27 03:08:54'),(31,1,'damaged_stock','Recorded dead stock for product ID: 2','::1','2026-01-27 03:11:41'),(32,1,'damaged_stock','Recorded dead stock for product ID: 2','::1','2026-01-27 03:12:24'),(33,1,'damaged_stock','Recorded damaged stock for product ID: 2','::1','2026-01-27 03:16:29'),(34,1,'damaged_stock','Recorded damaged stock for product ID: 2','::1','2026-01-27 03:17:22'),(35,1,'damaged_stock','Recorded damaged stock for product ID: 2','::1','2026-01-27 03:20:43'),(36,1,'damaged_stock','Recorded dead stock for product ID: 2','::1','2026-01-27 03:24:36'),(37,1,'add_purchase','Added purchase ID: 1','::1','2026-01-27 03:39:10'),(38,1,'add_purchase','Added purchase ID: 2','::1','2026-01-27 03:39:20'),(39,1,'purchase_return','Created purchase return ID: 1','::1','2026-01-27 03:40:13'),(40,1,'login','User logged in','::1','2026-01-27 07:38:19'),(41,1,'settings_update','Updated business settings','::1','2026-01-27 07:39:48'),(42,1,'settings_update','Updated business settings','::1','2026-01-27 07:44:41'),(43,1,'settings_update','Updated business settings','::1','2026-01-27 07:44:53'),(44,1,'settings_update','Updated business settings','::1','2026-01-27 07:45:01'),(45,1,'quotation_create','Created quotation QUO-000001','::1','2026-01-27 07:46:15'),(46,1,'quotation_convert','Converted Quotation #QUO-000001 to Sale ID: 2','::1','2026-01-27 07:53:58'),(47,1,'add_cash_account','Added cash account: Main Cash','::1','2026-01-27 08:11:03'),(48,1,'add_expense','Added expense: ','::1','2026-01-27 08:20:46'),(49,1,'add_expense','Added expense: ','::1','2026-01-27 08:21:01'),(50,1,'create_expense_category','Created expense category: bKash','::1','2026-01-27 08:30:37'),(51,1,'create_bank_account','Created bank account: Sonali','::1','2026-01-27 08:43:57'),(52,1,'update_bank_account','Updated bank account: City Bank','::1','2026-01-27 08:46:18'),(53,1,'balance_transfer','Transferred à§³100.00 from cash to bank','::1','2026-01-27 08:46:58'),(54,1,'create_backup','Created database backup: backup_20260127_152443_manual.sql','::1','2026-01-27 09:24:43'),(55,1,'download_backup','Downloaded backup: backup_20260127_152443_manual.sql','::1','2026-01-27 09:24:52'),(56,1,'staff_create','Added new staff: Kamruzzaman Shimul','::1','2026-01-27 09:48:39'),(57,1,'attendance_record','Recorded attendance for 2026-01-27','::1','2026-01-27 09:49:11'),(58,1,'login','User logged in','::1','2026-01-27 11:07:38'),(59,1,'create_service_center','Created service center: mmnn','::1','2026-01-27 11:09:47'),(60,1,'login','User logged in','::1','2026-01-28 03:28:35'),(61,1,'bill_collection','Payment of à§³500.00 for Sale #INV-000002','::1','2026-01-28 04:35:53'),(62,1,'login','User logged in','::1','2026-01-28 08:52:11'),(63,1,'login','User logged in','::1','2026-01-28 09:12:51'),(64,1,'add_purchase','Added purchase ID: 3','::1','2026-01-28 10:06:25'),(65,1,'sale_add','Created Sale #INV-000005','::1','2026-01-28 10:08:14'),(66,1,'sale_add','Created Sale #INV-000006','::1','2026-01-28 10:12:52'),(67,1,'rma_add','Created RMA Claim #RMA-000001','::1','2026-01-28 10:20:24'),(68,1,'create_service_center','Created service center: City Electronics Service','::1','2026-01-28 10:23:28'),(69,1,'create_service_center','Created service center: City Electronics Service','::1','2026-01-28 10:25:00'),(70,1,'create_service_center','Created service center: Quick Fix Solutions','::1','2026-01-28 10:26:10'),(71,1,'update_service_center','Updated service center: Quick Fix Solutions','::1','2026-01-28 10:26:40'),(72,1,'settings','Updated default tax rate to 0%','::1','2026-01-28 11:00:47'),(73,1,'login','User logged in','::1','2026-01-28 11:34:31'),(74,1,'login','User logged in','::1','2026-01-29 02:56:16'),(75,1,'add_product','Added product: demo','::1','2026-01-29 03:20:51'),(76,1,'edit_product','Updated product: demo','::1','2026-01-29 03:21:18'),(77,1,'login','User logged in','::1','2026-01-29 03:41:50'),(78,1,'sales_return','Created sales return ID: 1','::1','2026-01-29 04:00:34'),(79,1,'sales_return','Created sales return ID: 2','::1','2026-01-29 04:02:59'),(80,1,'purchase_return','Created purchase return ID: 2','::1','2026-01-29 04:19:31'),(81,1,'purchase_return','Created purchase return ID: 3','::1','2026-01-29 04:30:54'),(82,1,'login','User logged in','::1','2026-01-29 05:00:18'),(83,1,'login','User logged in','::1','2026-01-29 05:07:45'),(84,1,'quotation_create','Created quotation QUO-000002','::1','2026-01-29 05:08:30'),(85,1,'quotation_update','Updated quotation #2','::1','2026-01-29 05:14:20'),(86,1,'quotation_update','Updated quotation #2','::1','2026-01-29 05:14:58'),(87,1,'login','User logged in','::1','2026-01-29 07:48:15'),(88,1,'login','User logged in','::1','2026-01-29 08:12:38'),(89,1,'attendance_record','Recorded attendance for 2026-01-29','::1','2026-01-29 08:19:14'),(90,1,'attendance_record','Recorded attendance for 2026-01-28','::1','2026-01-29 08:19:19'),(91,1,'attendance_record','Recorded attendance for 2026-01-26','::1','2026-01-29 08:19:27'),(92,1,'role_create','Created role: hi','::1','2026-01-29 08:30:03'),(93,1,'rma_update','Updated RMA Claim #RMA-000001','::1','2026-01-29 08:32:36'),(94,1,'rma_update','Updated RMA Claim #RMA-000001','::1','2026-01-29 08:32:52'),(95,1,'delete_yearly_closing','Deleted yearly closing for 2030','::1','2026-01-29 08:53:43'),(96,1,'create_yearly_closing','Created yearly closing for 2030','::1','2026-01-29 08:55:21'),(97,1,'sale_add','Created Sale #INV-000008','::1','2026-01-29 09:15:37'),(98,1,'login','User logged in','::1','2026-01-29 09:29:01'),(99,1,'login','User logged in','::1','2026-01-29 10:06:40'),(100,1,'sale_add','Created Sale #INV-000009','::1','2026-01-29 10:07:28'),(101,1,'supplier_payment','Payment of à§³6,000.00 for Purchase #PUR-20260126-4378','::1','2026-01-29 10:24:31'),(102,1,'sale_payment','Payment of à§³40,000.00 for Invoice #INV-000006','::1','2026-01-29 10:26:13'),(103,1,'sale_payment','Payment of à§³10,000.00 for Invoice #INV-000006','::1','2026-01-29 10:26:31'),(104,1,'supplier_payment','Payment of à§³22,250.00 for Purchase #PUR-20260128-9505','::1','2026-01-29 10:28:02'),(105,1,'supplier_payment','Payment of à§³9,000.00 for Purchase #PUR-20260126-4378','::1','2026-01-29 10:28:32'),(106,1,'settings_update','Updated business settings','::1','2026-01-29 10:52:44'),(107,1,'login','User logged in','::1','2026-01-29 11:12:13'),(108,1,'login','User logged in','::1','2026-01-29 15:00:20'),(109,1,'login','User logged in','::1','2026-01-29 15:03:55'),(110,1,'sale_add','Created Sale #INV-000010','::1','2026-01-29 15:29:27'),(111,1,'login','User logged in','::1','2026-01-29 16:29:47'),(112,1,'login','User logged in','::1','2026-01-30 03:18:00'),(113,1,'quotation_update','Updated quotation #2','::1','2026-01-30 03:18:32'),(114,1,'login','User logged in','::1','2026-01-30 03:32:30'),(115,1,'create_backup','Created database backup: backup_20260130_095757_manual.sql','::1','2026-01-30 03:57:57'),(116,1,'download_backup','Downloaded backup: backup_20260130_095757_manual.sql','::1','2026-01-30 03:58:04'),(117,1,'login','User logged in','::1','2026-01-30 04:11:50'),(118,1,'login','User logged in','::1','2026-01-30 06:55:01'),(119,1,'logout','User logged out','::1','2026-01-30 06:59:15'),(120,1,'login','User logged in','::1','2026-01-30 07:01:23'),(121,1,'logout','User logged out','::1','2026-01-30 07:01:33'),(122,1,'login','User logged in','::1','2026-01-30 07:03:16'),(123,1,'logout','User logged out','::1','2026-01-30 07:03:35'),(124,1,'login','User logged in','::1','2026-01-30 08:08:01');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_license`
--

DROP TABLE IF EXISTS `app_license`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_license` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `license_key` varchar(100) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended','expired') DEFAULT 'inactive',
  `activated_at` datetime DEFAULT NULL,
  `last_verified_at` datetime DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_license`
--

LOCK TABLES `app_license` WRITE;
/*!40000 ALTER TABLE `app_license` DISABLE KEYS */;
-- INSERT INTO `app_license` VALUES (1,'','localhost','inactive',NULL,NULL,NULL,CURRENT_TIMESTAMP);
/*!40000 ALTER TABLE `app_license` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','leave','half_day') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `date` (`date`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,2,'2026-01-27','present','','2026-01-27 09:49:11'),(2,1,'2026-01-27','leave','','2026-01-27 09:49:11'),(3,2,'2026-01-29','present','','2026-01-29 08:19:14'),(4,1,'2026-01-29','present','','2026-01-29 08:19:14'),(5,2,'2026-01-28','present','','2026-01-29 08:19:19'),(6,1,'2026-01-28','present','','2026-01-29 08:19:19'),(7,2,'2026-01-26','present','','2026-01-29 08:19:27'),(8,1,'2026-01-26','present','','2026-01-29 08:19:27');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_history`
--

DROP TABLE IF EXISTS `backup_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_backup_type` (`backup_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_history`
--

LOCK TABLES `backup_history` WRITE;
/*!40000 ALTER TABLE `backup_history` DISABLE KEYS */;
INSERT INTO `backup_history` VALUES (1,'backup_20260127_152443_manual.sql','manual',100562,'C:\\xampp\\htdocs\\business-management-system/backups/database/backup_20260127_152443_manual.sql','[\"local\"]','success',54,325,NULL,1,'2026-01-27 09:24:43',NULL),(2,'backup_20260130_095757_manual.sql','manual',143668,'C:\\xampp\\htdocs\\business-management-system/backups/database/backup_20260130_095757_manual.sql','[\"local\"]','success',61,552,NULL,1,'2026-01-30 03:57:57',NULL);
/*!40000 ALTER TABLE `backup_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_settings`
--

DROP TABLE IF EXISTS `backup_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_settings`
--

LOCK TABLES `backup_settings` WRITE;
/*!40000 ALTER TABLE `backup_settings` DISABLE KEYS */;
INSERT INTO `backup_settings` VALUES (1,1,'daily','02:00:00',1,'/backups/database/',0,NULL,NULL,0,NULL,30,1,'2026-01-30 03:57:57','2026-01-27 09:22:53','2026-01-30 03:57:57');
/*!40000 ALTER TABLE `backup_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `balance_transfers`
--

DROP TABLE IF EXISTS `balance_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `balance_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_account_type` enum('cash','bank') NOT NULL,
  `from_account_id` int(11) NOT NULL,
  `to_account_type` enum('cash','bank') NOT NULL,
  `to_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transfer_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transfer_date` (`transfer_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `balance_transfers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `balance_transfers`
--

LOCK TABLES `balance_transfers` WRITE;
/*!40000 ALTER TABLE `balance_transfers` DISABLE KEYS */;
INSERT INTO `balance_transfers` VALUES (1,'cash',2,'bank',2,100.00,'2026-01-27','',1,'2026-01-27 08:46:58');
/*!40000 ALTER TABLE `balance_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_accounts`
--

DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(255) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `branch` varchar(255) DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_accounts`
--

LOCK TABLES `bank_accounts` WRITE;
/*!40000 ALTER TABLE `bank_accounts` DISABLE KEYS */;
INSERT INTO `bank_accounts` VALUES (1,'Sonali','65555555','dhaka',50000.00,50000.00,'2026-01-27 08:43:57',NULL),(2,'Dutch-Bangla Bank','123.456.7890','Motijheel',500000.00,500100.00,'2026-01-27 08:45:48','2026-01-27 08:46:58'),(3,'Islami Bank','987.654.3210','Gulshan',1250000.00,1250000.00,'2026-01-27 08:45:48',NULL),(4,'City Bank','555.666.1111','Banani',1750000.00,750000.00,'2026-01-27 08:45:48','2026-01-27 08:46:18');
/*!40000 ALTER TABLE `bank_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_transactions`
--

DROP TABLE IF EXISTS `bank_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('debit','credit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  KEY `transaction_date` (`transaction_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `bank_transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_transactions`
--

LOCK TABLES `bank_transactions` WRITE;
/*!40000 ALTER TABLE `bank_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bill_collections`
--

DROP TABLE IF EXISTS `bill_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bill_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `bill_collections_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bill_collections_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bill_collections`
--

LOCK TABLES `bill_collections` WRITE;
/*!40000 ALTER TABLE `bill_collections` DISABLE KEYS */;
/*!40000 ALTER TABLE `bill_collections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `brands`
--

DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
INSERT INTO `brands` VALUES (1,'Samsung',NULL,'Korean electronics manufacturer','2026-01-26 09:52:47'),(2,'Apple',NULL,'Technology and innovation','2026-01-26 09:52:47'),(3,'Nike',NULL,'Sports and athletic wear','2026-01-26 09:52:47'),(4,'IKEA',NULL,'Furniture and home accessories','2026-01-26 09:52:47'),(5,'Coca-Cola',NULL,'Beverage company','2026-01-26 09:52:47'),(12,'SOny',NULL,'','2026-01-28 04:03:30'),(13,'HP',NULL,'Hewlett-Packard Company.','2026-01-28 11:14:51'),(14,'Dell',NULL,'American multinational computer technology company.','2026-01-28 11:14:51'),(15,'Lenovo',NULL,'Chinese multinational technology company.','2026-01-28 11:14:52');
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `business_settings`
--

DROP TABLE IF EXISTS `business_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `business_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `business_settings`
--

LOCK TABLES `business_settings` WRITE;
/*!40000 ALTER TABLE `business_settings` DISABLE KEYS */;
INSERT INTO `business_settings` VALUES (1,'Shimul','2424524','sdfsd@tsdf.gfd','charghat','à§³','INV-',0.00,'uploads/business/logo_1769420291.png','name','Copyright Â© Shimul 2026 All right Reserved','2026-01-26 09:37:46','2026-01-29 10:52:44',0.00);
/*!40000 ALTER TABLE `business_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_accounts`
--

DROP TABLE IF EXISTS `cash_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_accounts`
--

LOCK TABLES `cash_accounts` WRITE;
/*!40000 ALTER TABLE `cash_accounts` DISABLE KEYS */;
INSERT INTO `cash_accounts` VALUES (1,'Main Cash',NULL,0.00,8800.00,'active','2026-01-25 13:49:30','2026-01-27 08:27:53'),(2,'Main Cash','',50000.00,49800.00,'active','2026-01-27 08:11:03','2026-01-27 08:46:58');
/*!40000 ALTER TABLE `cash_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_closings`
--

DROP TABLE IF EXISTS `cash_closings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_closings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `closing_date` date NOT NULL,
  `expected_cash` decimal(15,2) NOT NULL,
  `actual_cash` decimal(15,2) NOT NULL,
  `variance` decimal(15,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `closing_date` (`closing_date`),
  KEY `closed_by` (`closed_by`),
  CONSTRAINT `cash_closings_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_closings`
--

LOCK TABLES `cash_closings` WRITE;
/*!40000 ALTER TABLE `cash_closings` DISABLE KEYS */;
INSERT INTO `cash_closings` VALUES (1,'2026-01-25',25000.00,25000.00,0.00,'Perfectly balanced.',1,'2026-01-27 08:48:19'),(2,'2026-01-26',32450.00,32400.00,-50.00,'50 TK missing from petty cash.',1,'2026-01-27 08:48:19'),(3,'2026-01-27',18700.00,18710.00,10.00,'10 TK extra found.',1,'2026-01-27 08:48:19');
/*!40000 ALTER TABLE `cash_closings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_transactions`
--

DROP TABLE IF EXISTS `cash_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('debit','credit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'sale, purchase, expense',
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  KEY `transaction_date` (`transaction_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `cash_transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_transactions`
--

LOCK TABLES `cash_transactions` WRITE;
/*!40000 ALTER TABLE `cash_transactions` DISABLE KEYS */;
INSERT INTO `cash_transactions` VALUES (1,2,'credit',50000.00,NULL,NULL,'Opening Balance','2026-01-27',1,'2026-01-27 08:11:03'),(2,1,'credit',10000.00,NULL,NULL,'Initial Deposit','2026-01-01',1,'2026-01-27 08:16:01'),(3,1,'debit',2000.00,NULL,NULL,'Office Supplies','2026-01-05',1,'2026-01-27 08:16:01'),(4,1,'credit',5000.00,'sale',101,'Sale Revenue','2026-01-10',1,'2026-01-27 08:16:01'),(5,1,'debit',1500.00,'expense',1,'Electricity Bill','2026-01-15',1,'2026-01-27 08:16:01'),(6,1,'debit',1000.00,'expense',1,'Expense: ','2026-01-27',1,'2026-01-27 08:20:46'),(7,2,'debit',100.00,'expense',2,'Expense: ','2026-01-27',1,'2026-01-27 08:21:01'),(8,1,'debit',500.00,'expense',3,'Expense: Printer Ink and Paper','2026-01-27',1,'2026-01-27 08:27:53'),(9,1,'debit',1200.00,'expense',4,'Expense: Customer Visit - Train Ticket','2026-01-27',1,'2026-01-27 08:27:53');
/*!40000 ALTER TABLE `cash_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Electronics',NULL,NULL,'Electronic devices and accessories','2026-01-26 09:52:47'),(2,'Furniture',NULL,NULL,'Office and home furniture','2026-01-26 09:52:47'),(3,'Clothing',NULL,NULL,'Apparel and fashion items','2026-01-26 09:52:47'),(4,'Food & Beverage',NULL,NULL,'Food and drink products','2026-01-26 09:52:47'),(5,'Office Supplies',NULL,NULL,'Office equipment and supplies','2026-01-26 09:52:47'),(6,'Electronics',NULL,NULL,'Electronic devices and accessories','2026-01-26 09:53:26'),(7,'Furniture',NULL,NULL,'Office and home furniture','2026-01-26 09:53:26'),(8,'Clothing',NULL,NULL,'Apparel and fashion items','2026-01-26 09:53:26'),(9,'Food & Beverage',NULL,NULL,'Food and drink products','2026-01-26 09:53:26'),(10,'Office Supplies',NULL,NULL,'Office equipment and supplies','2026-01-26 09:53:26'),(11,'Electronics',NULL,NULL,'Electronic devices and accessories','2026-01-26 09:53:50'),(12,'Furniture',NULL,NULL,'Office and home furniture','2026-01-26 09:53:50'),(13,'Clothing',NULL,NULL,'Apparel and fashion items','2026-01-26 09:53:50'),(14,'Food & Beverage',NULL,NULL,'Food and drink products','2026-01-26 09:53:50'),(15,'Office Supplies',NULL,NULL,'Office equipment and supplies','2026-01-26 09:53:50'),(16,'Computers',NULL,NULL,'Laptops, desktops, and accessories.','2026-01-28 11:14:51'),(17,'Mobile Phones',NULL,NULL,'Smartphones and tablets.','2026-01-28 11:14:51'),(18,'Accessories',NULL,NULL,'Cables, chargers, and cases.','2026-01-28 11:14:51'),(19,'Networking',NULL,NULL,'Routers, switches, and modems.','2026-01-28 11:14:51'),(20,'Electronics',NULL,NULL,'','2026-01-28 11:15:27');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_ledger`
--

DROP TABLE IF EXISTS `customer_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL COMMENT 'sale, payment, return, opening_balance',
  `reference_id` int(11) DEFAULT NULL COMMENT 'sale_id or payment_id',
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `date` (`date`),
  CONSTRAINT `customer_ledger_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_ledger`
--

LOCK TABLES `customer_ledger` WRITE;
/*!40000 ALTER TABLE `customer_ledger` DISABLE KEYS */;
INSERT INTO `customer_ledger` VALUES (1,4,'sale',2,116500.00,0.00,0.00,'Sale from Quotation #QUO-000001','2026-01-27','2026-01-27 07:53:58'),(2,4,'sale',3,75350.00,0.00,0.00,'Sale Invoice: INV-000003','2026-01-28','2026-01-28 03:42:43'),(3,4,'sale',4,135000.00,0.00,0.00,'Sale Invoice: INV-000004','2026-01-28','2026-01-28 03:46:52'),(4,4,'payment',3,0.00,500.00,264350.00,'Payment for Sale #INV-000002','2026-01-28','2026-01-28 04:35:53'),(5,3,'sale',5,110000.00,0.00,0.00,'Sale Invoice: INV-000005','2026-01-28','2026-01-28 10:08:14'),(6,1,'sale',6,240000.00,0.00,0.00,'Sale Invoice: INV-000006','2026-01-28','2026-01-28 10:12:52'),(7,1,'return',1,0.00,120000.00,120000.00,'Sales Return for Invoice #INV-000006','2026-01-29','2026-01-29 04:00:34'),(8,1,'return',2,0.00,120000.00,0.00,'Sales Return for Invoice #INV-000006','2026-01-29','2026-01-29 04:02:59'),(9,5,'sale',8,1500.00,0.00,0.00,'Sale Invoice: INV-000008','2026-01-29','2026-01-29 09:15:37'),(10,2,'sale',9,110000.00,0.00,0.00,'Sale Invoice: INV-000009','2026-01-29','2026-01-29 10:07:28'),(11,1,'payment',4,0.00,40000.00,0.00,'Payment for Invoice #INV-000006','2026-01-29','2026-01-29 10:26:13'),(12,1,'payment',5,0.00,10000.00,0.00,'Payment for Invoice #INV-000006','2026-01-29','2026-01-29 10:26:31'),(13,4,'sale',10,4200.00,0.00,0.00,'Sale Invoice: INV-000010','2026-01-29','2026-01-29 15:29:27'),(14,4,'payment',10,0.00,500.00,0.00,'Payment for Invoice: INV-000010','2026-01-29','2026-01-29 15:29:27');
/*!40000 ALTER TABLE `customer_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'John Doe','01712345678','john@example.com','123 Main St, Dhaka','Buyer',50000.00,0.00,-50000.00,'active','2026-01-26 09:53:50','2026-01-29 10:26:31'),(2,'Jane Smith','01812345679','jane@example.com','456 Park Ave, Chittagong','Corporate',100000.00,5000.00,115000.00,'active','2026-01-26 09:53:50','2026-01-29 10:07:28'),(3,'Bob Johnson','01912345680','bob@example.com','789 Oak Rd, Sylhet','Buyer',30000.00,0.00,110000.00,'active','2026-01-26 09:53:50','2026-01-28 10:08:14'),(4,'Alice Brown','01612345681','alice@example.com','dhaka','Vendor',75000.00,-2000.00,268050.00,'active','2026-01-26 09:53:50','2026-01-29 15:29:27'),(5,'Charlie Wilson','01512345682','charlie@example.com','654 Pine Ave, Khulna','Buyer',40000.00,1000.00,2500.00,'active','2026-01-26 09:53:50','2026-01-29 09:15:37');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `damaged_stock`
--

DROP TABLE IF EXISTS `damaged_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `damaged_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `stock_type` enum('damaged','dead') NOT NULL COMMENT 'damaged=repairable, dead=unsellable',
  `reason` text NOT NULL,
  `cost_value` decimal(15,2) DEFAULT 0.00 COMMENT 'Financial impact',
  `date` date NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_variant_id` (`variant_id`),
  KEY `idx_stock_type` (`stock_type`),
  KEY `idx_date` (`date`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `damaged_stock`
--

LOCK TABLES `damaged_stock` WRITE;
/*!40000 ALTER TABLE `damaged_stock` DISABLE KEYS */;
INSERT INTO `damaged_stock` VALUES (1,2,NULL,1,'damaged','Test insert after table creation',10.50,'2026-01-26',1,'2026-01-26 16:31:35',NULL),(2,2,NULL,1,'damaged','Direct test insert',10.50,'2026-01-26',1,'2026-01-26 16:28:00',NULL);
/*!40000 ALTER TABLE `damaged_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
INSERT INTO `expense_categories` VALUES (1,'Rent','Office/Shop rent','2026-01-25 13:49:30'),(2,'Utilities','Electricity, water, internet','2026-01-25 13:49:30'),(3,'Salaries','Employee salaries','2026-01-25 13:49:30'),(4,'Marketing','Advertising and promotion','2026-01-25 13:49:30'),(5,'Transportation','Delivery and transport costs','2026-01-25 13:49:30'),(6,'Maintenance','Repairs and maintenance','2026-01-25 13:49:30'),(7,'Miscellaneous','Other expenses','2026-01-25 13:49:30'),(9,'Office Supplies','Stationery and office equipment','2026-01-27 08:27:53'),(10,'Travel','Business travel and transport','2026-01-27 08:27:53'),(11,'bKash','','2026-01-27 08:30:37');
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_number` varchar(50) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `expense_date` (`expense_date`),
  KEY `created_by` (`created_by`),
  KEY `fk_expenses_account` (`account_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expenses_account` FOREIGN KEY (`account_id`) REFERENCES `cash_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,NULL,6,1,'',1000.00,'cash','approved',NULL,NULL,'2026-01-27',NULL,1,'2026-01-27 08:20:46','2026-01-27 08:39:06'),(2,NULL,4,2,'',100.00,'cash','approved',NULL,NULL,'2026-01-27',NULL,1,'2026-01-27 08:21:01','2026-01-27 08:39:06'),(3,NULL,9,1,'Printer Ink and Paper',500.00,'cash','approved',NULL,NULL,'2026-01-27',NULL,1,'2026-01-27 08:27:53','2026-01-27 08:39:06'),(4,NULL,10,1,'Customer Visit - Train Ticket',1200.00,'cash','approved',NULL,NULL,'2026-01-27',NULL,1,'2026-01-27 08:27:53','2026-01-27 08:39:06'),(5,NULL,2,1,'Pending: Electricity Bill',1500.00,'cash','pending',NULL,NULL,'2026-01-27',NULL,1,'2026-01-27 08:41:54',NULL),(6,NULL,2,1,'Pending: Internet Subscription',800.00,'cash','pending',NULL,NULL,'2026-01-27',NULL,1,'2026-01-27 08:41:54',NULL),(7,NULL,2,1,'Pending: Office Cleaning',500.00,'cash','pending',NULL,NULL,'2026-01-27',NULL,1,'2026-01-27 08:41:54',NULL);
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `import_history`
--

DROP TABLE IF EXISTS `import_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `import_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `duration_seconds` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_imported_at` (`imported_at`),
  KEY `imported_by` (`imported_by`),
  CONSTRAINT `import_history_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `import_history`
--

LOCK TABLES `import_history` WRITE;
/*!40000 ALTER TABLE `import_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `import_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_settings`
--

DROP TABLE IF EXISTS `invoice_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_phone` varchar(50) DEFAULT NULL,
  `company_email` varchar(100) DEFAULT NULL,
  `company_website` varchar(100) DEFAULT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `invoice_prefix` varchar(20) DEFAULT 'INV-',
  `invoice_number_digits` int(11) DEFAULT 6,
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
  `show_slogan` tinyint(1) DEFAULT 1,
  `show_signature_on_invoice` tinyint(1) DEFAULT 1,
  `author_signature_label` varchar(100) DEFAULT 'Author signature',
  `show_amount_in_words` tinyint(1) DEFAULT 1,
  `amount_in_words_prefix` varchar(20) DEFAULT 'BDT',
  `invoice_signature` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_settings`
--

LOCK TABLES `invoice_settings` WRITE;
/*!40000 ALTER TABLE `invoice_settings` DISABLE KEYS */;
INSERT INTO `invoice_settings` VALUES (1,'Shimul','Your Business Address\r\nCity, State, ZIP','+1234567890','info@yourbusiness.com','www.fghfg,fgfgh','uploads/settings/697b298c5fd75_1769679244.png','','INV-',6,0.00,1,1,1,1,1,'Payment is due within 15 days\r\nPlease make checks payable to: Your Business Name','Thank you for your business!','#4e73df','#000000','professional','A4',0,1,'Invoice Footer Text','2026-01-26 10:35:05','2026-01-29 09:34:04','Good received by customer in good condition.',1,'Software Developed By Shimul','hell yah',1,1,'Author signature',1,'BDT',NULL);
/*!40000 ALTER TABLE `invoice_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `attempted_at` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,'admin','::1','2026-01-25 13:50:00');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (1,'Dashboard','dashboard','fas fa-tachometer-alt','/modules/dashboard/index.php',NULL,1,1,'2026-01-26 09:03:11'),(2,'Users & Access','users','fas fa-users',NULL,NULL,2,1,'2026-01-26 09:03:11'),(3,'Customers','customers','fas fa-user-tie',NULL,NULL,3,1,'2026-01-26 09:03:11'),(4,'Suppliers','suppliers','fas fa-truck',NULL,NULL,4,1,'2026-01-26 09:03:11'),(5,'Products','products','fas fa-box',NULL,NULL,5,1,'2026-01-26 09:03:11'),(6,'Purchase','purchase','fas fa-shopping-cart',NULL,NULL,6,1,'2026-01-26 09:03:11'),(7,'Sales / POS','sales','fas fa-cash-register',NULL,NULL,7,1,'2026-01-26 09:03:11'),(8,'Quotation','quotation','fas fa-file-invoice',NULL,NULL,8,1,'2026-01-26 09:03:11'),(9,'Warranty & RMA','warranty','fas fa-tools',NULL,NULL,9,1,'2026-01-26 09:03:11'),(10,'Expense','expense','fas fa-money-bill-wave',NULL,NULL,10,1,'2026-01-26 09:03:11'),(11,'Accounts','accounts','fas fa-university',NULL,NULL,11,1,'2026-01-26 09:03:11'),(12,'HR Management','hr','fas fa-user-friends',NULL,NULL,12,1,'2026-01-26 09:03:11'),(13,'Reports','reports','fas fa-chart-bar',NULL,NULL,13,1,'2026-01-26 09:03:11'),(14,'Barcode','barcode','fas fa-barcode',NULL,NULL,14,1,'2026-01-26 09:03:11'),(15,'Settings','settings','fas fa-cog',NULL,NULL,15,1,'2026-01-26 09:03:11'),(16,'Users','users.list',NULL,'/modules/users/users-list.php',2,1,1,'2026-01-26 09:03:11'),(17,'Roles & Permissions','users.roles',NULL,'/modules/users/roles-permissions.php',2,2,1,'2026-01-26 09:03:11'),(18,'Activity Log','users.activity',NULL,'/modules/users/activity-log.php',2,3,1,'2026-01-26 09:03:11'),(19,'Add User','users.add',NULL,'/modules/users/user-add.php',2,4,1,'2026-01-26 09:03:11'),(20,'Customer List','customers.list',NULL,'/modules/customers/customers-list.php',3,1,1,'2026-01-26 09:03:11'),(21,'Add Customer','customers.add',NULL,'/modules/customers/customer-add.php',3,2,1,'2026-01-26 09:03:11'),(22,'Customer Ledger','customers.ledger',NULL,'/modules/customers/customer-ledger.php',3,3,1,'2026-01-26 09:03:11'),(23,'Supplier List','suppliers.list',NULL,'/modules/suppliers/suppliers-list.php',4,1,1,'2026-01-26 09:03:11'),(24,'Add Supplier','suppliers.add',NULL,'/modules/suppliers/supplier-add.php',4,2,1,'2026-01-26 09:03:11'),(25,'Supplier Ledger','suppliers.ledger',NULL,'/modules/suppliers/supplier-ledger.php',4,3,1,'2026-01-26 09:03:11'),(26,'Product List','products.list',NULL,'/modules/products/products-list.php',5,1,1,'2026-01-26 09:03:11'),(27,'Add Product','products.add',NULL,'/modules/products/product-add.php',5,2,1,'2026-01-26 09:03:11'),(28,'Brands','products.brands',NULL,'/modules/products/brands-list.php',5,3,1,'2026-01-26 09:03:11'),(29,'Categories','products.categories',NULL,'/modules/products/categories-list.php',5,4,1,'2026-01-26 09:03:11'),(30,'Units','products.units',NULL,'/modules/products/units-list.php',5,5,1,'2026-01-26 09:03:11'),(31,'Serial/IMEI','products.serial',NULL,'/modules/products/serial-imei-list.php',5,6,1,'2026-01-26 09:03:11'),(32,'Stock Adjustment','products.stock',NULL,'/modules/products/stock-adjustment.php',5,7,1,'2026-01-26 09:03:11'),(33,'Opening Stock','products.opening',NULL,'/modules/products/opening-stock.php',5,8,1,'2026-01-26 09:03:11'),(34,'Damaged Stock','products.damaged',NULL,'/modules/products/damaged-stock.php',5,9,1,'2026-01-26 09:03:11'),(35,'Create Purchase','purchase.create',NULL,'/modules/purchase/purchase-add.php',6,1,1,'2026-01-26 09:03:11'),(36,'Purchase List','purchase.list',NULL,'/modules/purchase/purchases-list.php',6,2,1,'2026-01-26 09:03:11'),(37,'Purchase Returns','purchase.returns',NULL,'/modules/purchase/purchase-return-list.php',6,3,1,'2026-01-26 09:03:11'),(38,'Supplier Payment','purchase.payment',NULL,'/modules/purchase/supplier-payment.php',6,4,1,'2026-01-26 09:03:11'),(39,'Due Management','purchase.dues',NULL,'/modules/sales/due-management.php',7,5,1,'2026-01-26 09:03:11'),(40,'POS','sales.pos',NULL,'/modules/sales/pos.php',7,1,1,'2026-01-26 09:03:11'),(41,'Sales List','sales.list',NULL,'/modules/sales/sales-list.php',7,2,1,'2026-01-26 09:03:11'),(42,'Sales Returns','sales.returns',NULL,'/modules/sales/sales-return-list.php',7,3,1,'2026-01-26 09:03:11'),(43,'Bill Collection','sales.payment',NULL,'/modules/sales/bill-collection.php',7,4,1,'2026-01-26 09:03:11'),(44,'Create Quotation','quotation.create',NULL,'/modules/quotation/quotation-add.php',8,1,1,'2026-01-26 09:03:11'),(45,'Quotation List','quotation.list',NULL,'/modules/quotation/quotations-list.php',8,2,1,'2026-01-26 09:03:11'),(46,'Serial List','warranty.serial',NULL,'/modules/warranty/serial-list.php',9,1,1,'2026-01-26 09:03:11'),(47,'RMA List','warranty.rma',NULL,'/modules/warranty/rma-list.php',9,2,1,'2026-01-26 09:03:11'),(48,'Service Centers','warranty.service',NULL,'/modules/warranty/service-center-info.php',9,3,1,'2026-01-26 09:03:11'),(49,'Expense List','expense.list',NULL,'/modules/expense/expenses-list.php',10,1,1,'2026-01-26 09:03:11'),(50,'Add Expense','expense.add',NULL,'/modules/expense/expense-add.php',10,2,1,'2026-01-26 09:03:11'),(51,'Categories','expense.categories',NULL,'/modules/expense/expense-categories.php',10,3,1,'2026-01-26 09:03:11'),(52,'Cash Account','accounts.cash',NULL,'/modules/accounts/cash-accounts-list.php',11,1,1,'2026-01-26 09:03:11'),(53,'Bank Accounts','accounts.bank',NULL,'/modules/accounts/bank-accounts.php',11,2,1,'2026-01-26 09:03:11'),(54,'Balance Transfer','accounts.transfer',NULL,'/modules/accounts/balance-transfer.php',11,3,1,'2026-01-26 09:03:11'),(55,'Daily Cash Closing','accounts.closing',NULL,'/modules/accounts/daily-cash-closing.php',11,4,1,'2026-01-26 09:03:11'),(56,'Transaction History','accounts.transactions',NULL,'/modules/accounts/transaction-history.php',11,5,1,'2026-01-26 09:03:11'),(57,'Party Cash','accounts.party',NULL,'/modules/accounts/party-cash.php',11,6,1,'2026-01-26 09:03:11'),(58,'Staff List','hr.staff',NULL,'/modules/hr/staff-list.php',12,1,1,'2026-01-26 09:03:11'),(59,'Salary','hr.salary',NULL,'/modules/hr/salary-manage.php',12,2,1,'2026-01-26 09:03:11'),(60,'Attendance','hr.attendance',NULL,'/modules/hr/attendance.php',12,3,1,'2026-01-26 09:03:11'),(61,'Team Overview','hr.overview',NULL,'/modules/hr/team-overview.php',12,4,1,'2026-01-26 09:03:11'),(62,'Roles','hr.roles',NULL,'/modules/hr/staff-roles.php',12,5,1,'2026-01-26 09:03:11'),(63,'Business Summary','reports.summary',NULL,'/modules/reports/business-summary.php',13,1,1,'2026-01-26 09:03:11'),(64,'Daily Report','reports.daily',NULL,'/modules/reports/daily-report.php',13,2,1,'2026-01-26 09:03:11'),(65,'Sales Report','reports.sales',NULL,'/modules/reports/sales-report.php',13,3,1,'2026-01-26 09:03:11'),(66,'Product Sales Report','reports.productsales',NULL,'/modules/reports/product-sales-report.php',13,4,1,'2026-01-26 09:03:11'),(67,'Purchase Report','reports.purchase',NULL,'/modules/reports/purchase-report.php',13,5,1,'2026-01-26 09:03:11'),(68,'Receivable Report','reports.receivable',NULL,'/modules/reports/receivable-report.php',13,6,1,'2026-01-26 09:03:11'),(69,'Payable Report','reports.payable',NULL,'/modules/reports/payable-report.php',13,7,1,'2026-01-26 09:03:11'),(70,'Top Customer','reports.topcustomer',NULL,'/modules/reports/top-customer.php',13,8,1,'2026-01-26 09:03:11'),(71,'Stock Report','reports.stock',NULL,'/modules/reports/stock-report.php',13,9,1,'2026-01-26 09:03:11'),(72,'Alert Product Report','reports.alertproduct',NULL,'/modules/reports/alert-product-report.php',13,10,1,'2026-01-26 09:03:11'),(73,'Expense Report','reports.expense',NULL,'/modules/reports/expense-report.php',13,11,1,'2026-01-26 09:03:11'),(74,'Account Transaction Report','reports.accounttransaction',NULL,'/modules/reports/account-transaction-report.php',13,12,1,'2026-01-26 09:03:11'),(75,'Profit/Loss Report','reports.profit',NULL,'/modules/reports/profit-loss-report.php',13,13,1,'2026-01-26 09:03:11'),(76,'Low Stock Alert','reports.lowstock',NULL,'/modules/reports/low-stock-report.php',13,14,1,'2026-01-26 09:03:11'),(77,'Generate Barcode','barcode.generate',NULL,'/modules/barcode/barcode-generator.php',14,1,1,'2026-01-26 09:03:11'),(78,'Print Barcode','barcode.print',NULL,'/modules/barcode/barcode-print.php',14,2,1,'2026-01-26 09:03:11'),(79,'Business Settings','settings.business',NULL,'/modules/settings/business-settings.php',15,1,1,'2026-01-26 09:03:11'),(80,'Invoice Settings','settings.invoice',NULL,'/modules/settings/invoice-settings.php',15,2,1,'2026-01-26 09:03:11'),(81,'Tax Settings','settings.tax',NULL,'/modules/settings/tax-settings.php',15,3,1,'2026-01-26 09:03:11'),(82,'Profile','settings.profile',NULL,'/modules/settings/profile.php',15,4,1,'2026-01-26 09:03:11'),(83,'Change Password','settings.password',NULL,'/modules/settings/change-password.php',15,5,1,'2026-01-26 09:03:11'),(84,'Expense Approval','expense.approve',NULL,'/modules/expense/expense-approve.php',10,4,1,'2026-01-27 08:39:56'),(85,'Yearly Closing','accounts.yearly_closing',NULL,'/modules/accounts/yearly-closing.php',11,7,1,'2026-01-27 09:07:54'),(86,'Backup & Restore','settings.backup',NULL,'/modules/settings/backup.php',15,10,1,'2026-01-27 09:22:53'),(87,'Stock Transfer','','fas fa-exchange-alt','/modules/stock-transfer/transfer-list.php',5,10,1,'2026-01-28 09:36:35'),(88,'RMA Status','warranty.status',NULL,'/modules/warranty/rma-status.php',9,4,1,'2026-01-29 03:24:04'),(89,'Advance RMA Search','advance-rma-search','fas fa-search-plus','/modules/warranty/advance-rma-search.php',9,5,1,'2026-01-29 09:49:02'),(90,'SMS Settings','sms-settings','fas fa-comments','/modules/settings/sms-settings.php',15,10,1,'2026-01-30 03:54:29'),(91,'License Management','settings.license','fas fa-key','/modules/settings/license-manage.php',15,11,1,'2026-01-30 06:52:56');
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `party_cash`
--

DROP TABLE IF EXISTS `party_cash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `party_cash` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('assign','return') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `party_cash_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `party_cash_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `party_cash`
--

LOCK TABLES `party_cash` WRITE;
/*!40000 ALTER TABLE `party_cash` DISABLE KEYS */;
INSERT INTO `party_cash` VALUES (1,1,'assign',5000.00,'Demo: Initial cash assignment for field expenses','2026-01-22',1,'2026-01-27 08:49:44'),(2,2,'assign',3000.00,'Demo: Petty cash for office supplies','2026-01-23',1,'2026-01-27 08:49:44'),(3,1,'return',1000.00,'Demo: Partial return of unused field cash','2026-01-24',1,'2026-01-27 08:49:44'),(4,3,'assign',1500.00,'Demo: Daily shift opening balance','2026-01-25',1,'2026-01-27 08:49:44'),(5,2,'return',500.00,'Demo: Weekly balance settlement','2026-01-26',1,'2026-01-27 08:49:44'),(6,4,'assign',1000.00,NULL,'2026-01-28',1,'2026-01-28 10:48:11'),(7,4,'return',200.00,NULL,'2026-01-28',1,'2026-01-28 10:48:11');
/*!40000 ALTER TABLE `party_cash` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'stock_transfer.view','stock_transfer','','View Stock Transfers','2026-01-28 09:30:31'),(2,'stock_transfer.create','stock_transfer','','Create Stock Transfer','2026-01-28 09:30:31'),(3,'stock_transfer.edit','stock_transfer','','Edit Stock Transfer','2026-01-28 09:30:31'),(4,'stock_transfer.approve','stock_transfer','','Approve Stock Transfer','2026-01-28 09:30:31'),(5,'stock_transfer.delete','stock_transfer','','Delete Stock Transfer','2026-01-28 09:30:31');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_serials`
--

DROP TABLE IF EXISTS `product_serials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_serials` (
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
  `status` enum('in_stock','sold','returned','defective') NOT NULL DEFAULT 'in_stock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `warranty_alert_sent` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  UNIQUE KEY `imei` (`imei`),
  KEY `product_id` (`product_id`),
  KEY `status` (`status`),
  KEY `sale_item_id` (`sale_item_id`),
  CONSTRAINT `product_serials_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_serials`
--

LOCK TABLES `product_serials` WRITE;
/*!40000 ALTER TABLE `product_serials` DISABLE KEYS */;
INSERT INTO `product_serials` VALUES (1,1,'SN-SAM-001-2026','356938035643809',1,1,NULL,12,'2025-12-15','2026-01-15','defective','2026-01-27 11:14:08',0),(2,2,'SN-IPH-001-2026','352099001761481',1,2,NULL,12,'2025-11-20','2025-12-27','sold','2026-01-27 11:14:08',0),(3,3,'SN-LAP-001-2024','456789012345678',1,NULL,NULL,12,'2024-01-10','2024-02-01','sold','2026-01-27 11:14:08',0),(4,4,'SN-TAB-001-2026','789012345678901',2,NULL,NULL,24,'2025-12-01',NULL,'in_stock','2026-01-27 11:14:08',0),(5,5,'SN-DELL-001-2026','234567890123456',2,3,NULL,12,'2025-11-01','2026-03-27','sold','2026-01-27 11:14:08',0),(6,2,'fgftyt',NULL,3,6,12,12,'2026-01-28','2026-01-28','sold','2026-01-28 10:06:25',0),(7,2,'hjggh',NULL,3,NULL,NULL,12,'2026-01-28',NULL,'returned','2026-01-28 10:06:25',0),(8,2,'ytyjkh',NULL,3,NULL,NULL,12,'2026-01-28',NULL,'returned','2026-01-28 10:06:25',0),(9,2,'ytytt',NULL,3,9,17,12,'2026-01-28','2026-01-29','sold','2026-01-28 10:06:25',0),(10,2,'fghg',NULL,3,6,12,12,'2026-01-28','2026-01-28','sold','2026-01-28 10:06:25',0),(11,2,'ftygy',NULL,3,NULL,NULL,12,'2026-01-28',NULL,'in_stock','2026-01-28 10:06:25',0),(12,2,'uyyyh',NULL,3,NULL,NULL,12,'2026-01-28',NULL,'in_stock','2026-01-28 10:06:25',0),(13,2,'uiyttt',NULL,3,NULL,NULL,12,'2026-01-28',NULL,'in_stock','2026-01-28 10:06:25',0),(14,2,'ghfgjhffg',NULL,3,NULL,NULL,12,'2026-01-28',NULL,'in_stock','2026-01-28 10:06:25',0);
/*!40000 ALTER TABLE `product_serials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variant_name` varchar(100) NOT NULL COMMENT 'RAM, Storage, Color',
  `variant_value` varchar(100) NOT NULL COMMENT '8GB, 128GB, Black',
  `sku` varchar(100) DEFAULT NULL,
  `price_adjustment` decimal(15,2) DEFAULT 0.00 COMMENT 'Additional price',
  `stock_quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `sku` (`sku`),
  CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_warehouse_stock`
--

DROP TABLE IF EXISTS `product_warehouse_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_warehouse_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `reserved_quantity` decimal(10,2) DEFAULT 0.00,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_warehouse` (`product_id`,`warehouse_id`),
  KEY `warehouse_id` (`warehouse_id`),
  CONSTRAINT `product_warehouse_stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_warehouse_stock_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_warehouse_stock`
--

LOCK TABLES `product_warehouse_stock` WRITE;
/*!40000 ALTER TABLE `product_warehouse_stock` DISABLE KEYS */;
INSERT INTO `product_warehouse_stock` VALUES (1,1,1,38.00,0.00,'2026-01-28 09:44:10'),(2,2,1,43.00,0.00,'2026-01-29 04:19:31'),(3,3,1,58.00,0.00,'2026-01-28 09:30:31'),(4,4,1,37.00,0.00,'2026-01-28 09:30:31'),(5,5,1,103.00,0.00,'2026-01-28 09:30:31'),(8,1,4,100.00,0.00,'2026-01-28 09:42:22'),(9,5,3,100.00,0.00,'2026-01-28 09:42:22');
/*!40000 ALTER TABLE `product_warehouse_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `has_serial` enum('Available','Not Available') DEFAULT 'Not Available',
  `warranty_duration` int(11) DEFAULT 0,
  `warranty_period` enum('Days','Month','Year') DEFAULT 'Month',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `barcode` (`barcode`),
  KEY `brand_id` (`brand_id`),
  KEY `category_id` (`category_id`),
  KEY `unit_id` (`unit_id`),
  KEY `status` (`status`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_3` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Samsung Galaxy S23','PRD001','8801234567890',1,1,1,65000.00,75000.00,5.00,NULL,38,0.00,10,'Latest Samsung flagship smartphone','active','Available',12,'Month','2026-01-26 09:53:50','2026-01-28 09:44:10'),(2,'iPhone 14 Pro','PRD002','8801234567891',2,1,1,95000.00,110000.00,5.00,NULL,48,1.00,5,'Apple iPhone 14 Pro 256GB','active','Available',12,'Month','2026-01-26 09:53:50','2026-01-29 10:07:28'),(3,'Office Desk','PRD003','8801234567892',4,2,1,8000.00,12000.00,0.00,NULL,58,0.00,5,'IKEA office desk with drawers','active','Not Available',0,'Month','2026-01-26 09:53:50','2026-01-28 03:46:52'),(4,'Nike Air Max','PRD004','8801234567893',3,3,1,4500.00,6500.00,0.00,NULL,37,0.00,10,'Nike Air Max running shoes','active','Not Available',0,'Month','2026-01-26 09:53:50','2026-01-28 03:46:52'),(5,'A4 Copy Paper','PRD005','8801234567894',NULL,5,4,250.00,350.00,0.00,NULL,188,0.00,20,'A4 size copy paper - 500 sheets/box','active','Not Available',0,'Month','2026-01-26 09:53:50','2026-01-29 15:29:27'),(6,'Demo Out of Stock Pro','DEMO-001',NULL,1,1,1,100.00,150.00,0.00,NULL,-1,0.00,10,NULL,'active','Not Available',0,'Month','2026-01-28 11:37:47','2026-01-29 09:15:37'),(7,'Demo Critical Stock Air','DEMO-002',NULL,1,1,1,500.00,750.00,0.00,NULL,1,0.00,5,NULL,'active','Not Available',0,'Month','2026-01-28 11:37:47','2026-01-29 09:15:37'),(8,'Demo Reorder Limit Max','DEMO-003',NULL,1,1,1,200.00,300.00,0.00,NULL,20,0.00,20,NULL,'active','Not Available',0,'Month','2026-01-28 11:37:47',NULL),(9,'demo','PRD1769656851','',14,14,2,120.00,250.00,0.00,'697ad22e854c3_1769656878.png',11,0.00,10,'','active','Not Available',0,'Month','2026-01-29 03:20:51','2026-01-29 09:15:37');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_items`
--

DROP TABLE IF EXISTS `purchase_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `tax` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_items`
--

LOCK TABLES `purchase_items` WRITE;
/*!40000 ALTER TABLE `purchase_items` DISABLE KEYS */;
INSERT INTO `purchase_items` VALUES (1,1,5,NULL,NULL,5,250.00,0.00,1250.00,'2026-01-26 16:39:10'),(2,2,3,NULL,NULL,12,8000.00,0.00,96000.00,'2026-01-26 16:39:20'),(3,3,2,NULL,NULL,9,95000.00,42750.00,897750.00,'2026-01-28 10:06:25'),(4,3,5,NULL,NULL,98,250.00,0.00,24500.00,'2026-01-28 10:06:25');
/*!40000 ALTER TABLE `purchase_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_payments`
--

DROP TABLE IF EXISTS `purchase_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank','credit','card','mobile_money') NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `fk_pp_created_by` (`created_by`),
  CONSTRAINT `fk_pp_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_payments_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_payments`
--

LOCK TABLES `purchase_payments` WRITE;
/*!40000 ALTER TABLE `purchase_payments` DISABLE KEYS */;
INSERT INTO `purchase_payments` VALUES (1,4,'2026-01-28',300.00,'cash',NULL,NULL,'2026-01-28 10:48:11',4),(2,2,'2026-01-29',6000.00,'credit','','','2026-01-29 10:24:31',1),(3,3,'2026-01-29',22250.00,'cash','','','2026-01-29 10:28:02',1),(4,2,'2026-01-29',9000.00,'cash','','','2026-01-29 10:28:32',1);
/*!40000 ALTER TABLE `purchase_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_return_items`
--

DROP TABLE IF EXISTS `purchase_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `return_id` (`return_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_serial_id` (`serial_id`),
  CONSTRAINT `purchase_return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `purchase_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_return_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `purchase_return_items_ibfk_3` FOREIGN KEY (`serial_id`) REFERENCES `product_serials` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_return_items`
--

LOCK TABLES `purchase_return_items` WRITE;
/*!40000 ALTER TABLE `purchase_return_items` DISABLE KEYS */;
INSERT INTO `purchase_return_items` VALUES (1,1,3,NULL,NULL,2,8000.00,16000.00,'2026-01-26 16:40:13'),(2,2,2,NULL,NULL,9,95000.00,855000.00,'2026-01-29 04:19:31'),(3,3,2,NULL,7,1,95000.00,95000.00,'2026-01-29 04:30:54'),(4,3,2,NULL,8,1,95000.00,95000.00,'2026-01-29 04:30:54');
/*!40000 ALTER TABLE `purchase_return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_returns`
--

DROP TABLE IF EXISTS `purchase_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `purchase_returns_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`),
  CONSTRAINT `purchase_returns_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `purchase_returns_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_returns`
--

LOCK TABLES `purchase_returns` WRITE;
/*!40000 ALTER TABLE `purchase_returns` DISABLE KEYS */;
INSERT INTO `purchase_returns` VALUES (1,2,2,'2026-01-26',16000.00,'none','completed',1,'2026-01-26 16:40:13'),(2,3,2,'2026-01-29',855000.00,'Testing serial return','completed',1,'2026-01-29 04:19:31'),(3,3,2,'2026-01-29',0.00,'Final verification test','completed',1,'2026-01-29 04:30:54');
/*!40000 ALTER TABLE `purchase_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchases`
--

DROP TABLE IF EXISTS `purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_number` (`purchase_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `purchase_date` (`purchase_date`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `purchases_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchases`
--

LOCK TABLES `purchases` WRITE;
/*!40000 ALTER TABLE `purchases` DISABLE KEYS */;
INSERT INTO `purchases` VALUES (1,'PUR-20260126-7885',2,'2026-01-26',1250.00,0.00,0.00,0.00,1250.00,'unpaid','completed','',1,'2026-01-26 16:39:10',NULL),(2,'PUR-20260126-4378',2,'2026-01-26',96000.00,0.00,0.00,15000.00,81000.00,'partial','completed','',1,'2026-01-26 16:39:20','2026-01-29 10:28:32'),(3,'PUR-20260128-9505',2,'2026-01-28',922250.00,42750.00,0.00,22250.00,900000.00,'partial','completed','',1,'2026-01-28 10:06:25','2026-01-29 10:28:02'),(4,'PUR-TEST-1769597291',6,'2026-01-28',1000.00,0.00,0.00,0.00,0.00,'unpaid','completed',NULL,NULL,'2026-01-28 10:48:11',NULL);
/*!40000 ALTER TABLE `purchases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_items`
--

DROP TABLE IF EXISTS `quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `tax` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `quotation_items_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotation_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_items`
--

LOCK TABLES `quotation_items` WRITE;
/*!40000 ALTER TABLE `quotation_items` DISABLE KEYS */;
INSERT INTO `quotation_items` VALUES (1,1,2,NULL,NULL,1,110000.00,0.00,0.00,110000.00,'2026-01-27 07:46:15'),(2,1,4,NULL,NULL,1,6500.00,0.00,0.00,6500.00,'2026-01-27 07:46:15'),(9,2,2,'Updated bundle price',NULL,1,115000.00,0.00,0.00,115000.00,'2026-01-30 03:18:32'),(10,2,6,'Bulk discount applied for this item.',NULL,1,150.00,0.00,0.00,150.00,'2026-01-30 03:18:32');
/*!40000 ALTER TABLE `quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotations`
--

DROP TABLE IF EXISTS `quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `quotation_date` date NOT NULL,
  `expiration_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','accepted','rejected','converted') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `customer_id` (`customer_id`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quotations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotations`
--

LOCK TABLES `quotations` WRITE;
/*!40000 ALTER TABLE `quotations` DISABLE KEYS */;
INSERT INTO `quotations` VALUES (1,'QUO-000001',4,'2026-01-27','2026-02-26',116500.00,0.00,0.00,'converted','',1,'2026-01-27 07:46:15','2026-01-27 07:53:58'),(2,'QUO-000002',NULL,'2026-01-29','2026-02-28',115150.00,0.00,0.00,'pending','nice one for here',1,'2026-01-29 05:08:30','2026-01-30 03:18:32');
/*!40000 ALTER TABLE `quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rma_requests`
--

DROP TABLE IF EXISTS `rma_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rma_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rma_number` (`rma_number`),
  KEY `customer_id` (`customer_id`),
  KEY `product_id` (`product_id`),
  KEY `status` (`status`),
  KEY `service_center_id` (`service_center_id`),
  KEY `created_by` (`created_by`),
  KEY `fk_rma_new_serial` (`new_serial_id`),
  CONSTRAINT `fk_rma_new_serial` FOREIGN KEY (`new_serial_id`) REFERENCES `product_serials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rma_requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rma_requests_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `rma_requests_ibfk_3` FOREIGN KEY (`service_center_id`) REFERENCES `service_centers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rma_requests_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rma_requests`
--

LOCK TABLES `rma_requests` WRITE;
/*!40000 ALTER TABLE `rma_requests` DISABLE KEYS */;
INSERT INTO `rma_requests` VALUES (1,'RMA-000001',NULL,1,1,'Testing RMA workflow - Screen flickering','solved',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'repair',NULL,NULL,'2026-01-28',NULL,NULL,NULL,'',1,'2026-01-28 10:20:24','2026-01-29 08:32:52'),(2,'RMA-20260129-001',1,1,NULL,'Screen is flickering intermittently.','pending',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'repair',NULL,NULL,'2026-01-29',NULL,NULL,NULL,NULL,1,'2026-01-29 08:35:11',NULL),(3,'RMA-20260129-002',1,1,NULL,'Battery not charging. Replaced charging port.','solved',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'repair',NULL,NULL,'2026-01-24','2026-01-25','2026-01-29',NULL,'Fixed under warranty.',1,'2026-01-29 08:35:11',NULL);
/*!40000 ALTER TABLE `rma_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) DEFAULT NULL,
  `menu_item_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_menu` (`role_id`,`menu_item_id`),
  KEY `role_id` (`role_id`),
  KEY `menu_item_id` (`menu_item_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1,NULL,89,'2026-01-29 09:49:02'),(2,1,NULL,91,'2026-01-30 06:53:33');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Super Admin','Full access to all modules','2026-01-25 13:49:30',NULL),(2,'Manager','Manages daily operations','2026-01-25 13:49:30',NULL),(3,'Cashier','POS and sales operations','2026-01-25 13:49:30',NULL),(4,'Sales Representative','Sales and quotations','2026-01-25 13:49:30',NULL),(5,'Accountant','Financial operations','2026-01-25 13:49:30',NULL);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salaries`
--

DROP TABLE IF EXISTS `salaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `salaries_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salaries`
--

LOCK TABLES `salaries` WRITE;
/*!40000 ALTER TABLE `salaries` DISABLE KEYS */;
INSERT INTO `salaries` VALUES (1,1,1,2026,32254.00,2008.00,3349.00,30913.00,'2026-01-29','cheque','paid','2026-01-27 09:40:27'),(2,1,12,2025,74361.00,2242.00,4269.00,72334.00,'2025-12-30','cash','paid','2026-01-27 09:41:04'),(3,1,11,2025,22168.00,6130.00,2645.00,25653.00,'2025-11-25','cash','paid','2026-01-27 09:41:04'),(4,1,10,2025,39869.00,9893.00,2194.00,47568.00,'2025-10-27','cheque','paid','2026-01-27 09:41:04'),(5,1,9,2025,61444.00,4436.00,4718.00,61162.00,'2025-09-28','bank','paid','2026-01-27 09:41:04'),(6,1,8,2025,77265.00,7224.00,3532.00,80957.00,'2025-08-29','cheque','paid','2026-01-27 09:41:04');
/*!40000 ALTER TABLE `salaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `tax` decimal(15,2) DEFAULT 0.00,
  `discount` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_items`
--

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
INSERT INTO `sale_items` VALUES (1,1,5,NULL,NULL,NULL,1,350.00,0.00,0.00,350.00,'2026-01-25 23:00:47'),(2,1,2,NULL,NULL,NULL,2,110000.00,11000.00,0.00,220000.00,'2026-01-25 23:00:47'),(3,1,1,NULL,NULL,NULL,1,75000.00,3750.00,0.00,75000.00,'2026-01-25 23:00:47'),(4,2,2,NULL,NULL,NULL,1,110000.00,0.00,0.00,110000.00,'2026-01-27 07:53:58'),(5,2,4,NULL,NULL,NULL,1,6500.00,0.00,0.00,6500.00,'2026-01-27 07:53:58'),(6,3,5,NULL,NULL,NULL,1,350.00,0.00,0.00,350.00,'2026-01-28 03:42:43'),(7,3,1,NULL,NULL,NULL,1,75000.00,3750.00,0.00,75000.00,'2026-01-28 03:42:43'),(8,4,2,NULL,NULL,NULL,1,110000.00,5500.00,0.00,110000.00,'2026-01-28 03:46:52'),(9,4,4,NULL,NULL,NULL,2,6500.00,0.00,0.00,13000.00,'2026-01-28 03:46:52'),(10,4,3,NULL,NULL,NULL,1,12000.00,0.00,0.00,12000.00,'2026-01-28 03:46:52'),(11,5,2,NULL,NULL,NULL,1,110000.00,5500.00,0.00,110000.00,'2026-01-28 10:08:14'),(12,6,2,NULL,NULL,NULL,2,120000.00,12000.00,0.00,240000.00,'2026-01-28 10:12:52'),(13,8,5,NULL,NULL,NULL,1,350.00,0.00,0.00,350.00,'2026-01-29 09:15:37'),(14,8,6,NULL,NULL,NULL,1,150.00,0.00,0.00,150.00,'2026-01-29 09:15:37'),(15,8,7,NULL,NULL,NULL,1,750.00,0.00,0.00,750.00,'2026-01-29 09:15:37'),(16,8,9,NULL,NULL,NULL,1,250.00,0.00,0.00,250.00,'2026-01-29 09:15:37'),(17,9,2,NULL,NULL,NULL,1,110000.00,5500.00,0.00,110000.00,'2026-01-29 10:07:28'),(18,10,5,'A4 size copy paper - 500 sheets/box',NULL,NULL,12,350.00,0.00,0.00,4200.00,'2026-01-29 15:29:27');
/*!40000 ALTER TABLE `sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_payments`
--

DROP TABLE IF EXISTS `sale_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','bank','credit','card','mobile_money') NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `fk_sale_payments_created_by` (`created_by`),
  CONSTRAINT `fk_sale_payments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_payments_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_payments`
--

LOCK TABLES `sale_payments` WRITE;
/*!40000 ALTER TABLE `sale_payments` DISABLE KEYS */;
INSERT INTO `sale_payments` VALUES (1,3,'2026-01-28',10000.00,'cash',NULL,NULL,NULL,'2026-01-28 03:42:43'),(2,4,'2026-01-28',50000.00,'cash',NULL,NULL,NULL,'2026-01-28 03:46:52'),(3,2,'2026-01-28',500.00,'bank','','',1,'2026-01-28 04:35:53'),(4,6,'2026-01-29',40000.00,'cash','','',NULL,'2026-01-29 10:26:13'),(5,6,'2026-01-29',10000.00,'cash','','',NULL,'2026-01-29 10:26:31'),(6,10,'2026-01-29',500.00,'cash',NULL,NULL,NULL,'2026-01-29 15:29:27');
/*!40000 ALTER TABLE `sale_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_return_items`
--

DROP TABLE IF EXISTS `sale_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `serial_id` int(11) DEFAULT NULL,
  `return_type` enum('stock','rma') NOT NULL DEFAULT 'stock',
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `return_id` (`return_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sale_return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `sales_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_return_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_return_items`
--

LOCK TABLES `sale_return_items` WRITE;
/*!40000 ALTER TABLE `sale_return_items` DISABLE KEYS */;
INSERT INTO `sale_return_items` VALUES (1,1,2,NULL,NULL,'stock',1,120000.00,120000.00,'2026-01-29 04:00:34'),(2,2,2,NULL,NULL,'rma',1,120000.00,120000.00,'2026-01-29 04:02:59');
/*!40000 ALTER TABLE `sale_return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `sold_by` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `customer_id` (`customer_id`),
  KEY `sale_date` (`sale_date`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,'INV-000001',NULL,'2026-01-26',310100.00,14750.00,0.00,0.00,310100.00,'unpaid','completed','cash','',NULL,1,'2026-01-25 23:00:47',NULL),(2,'INV-000002',4,'2026-01-27',116500.00,0.00,0.00,500.00,116000.00,'partial','completed','cash','Converted from Quotation #QUO-000001. ',NULL,1,'2026-01-27 07:53:58','2026-01-28 04:35:53'),(3,'INV-000003',4,'2026-01-28',75350.00,0.00,0.00,10000.00,65350.00,'partial','completed','cash','done','Shimul',1,'2026-01-28 03:42:43',NULL),(4,'INV-000004',4,'2026-01-28',135000.00,0.00,0.00,50000.00,85000.00,'partial','completed','cash','done','shimul',1,'2026-01-28 03:46:52',NULL),(5,'INV-000005',3,'2026-01-28',110000.00,0.00,0.00,0.00,110000.00,'unpaid','completed','cash','','admin',1,'2026-01-28 10:08:14',NULL),(6,'INV-000006',1,'2026-01-28',240000.00,0.00,0.00,50000.00,190000.00,'partial','completed','cash','','admin',1,'2026-01-28 10:12:52','2026-01-29 10:26:31'),(7,'INV-TEST-1769597291',NULL,'2026-01-28',500.00,0.00,0.00,500.00,0.00,'paid','completed','cash',NULL,NULL,4,'2026-01-28 10:48:11',NULL),(8,'INV-000008',5,'2026-01-29',1500.00,0.00,0.00,0.00,1500.00,'unpaid','completed','cash','','admin',1,'2026-01-29 09:15:37',NULL),(9,'INV-000009',2,'2026-01-29',110000.00,0.00,0.00,0.00,110000.00,'unpaid','completed','cash','','admin',1,'2026-01-29 10:07:28',NULL),(10,'INV-000010',4,'2026-01-29',4200.00,0.00,0.00,500.00,3700.00,'partial','completed','cash','','admin',1,'2026-01-29 15:29:27',NULL);
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales_returns`
--

DROP TABLE IF EXISTS `sales_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `return_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `customer_id` (`customer_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `sales_returns_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  CONSTRAINT `sales_returns_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_returns_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_returns`
--

LOCK TABLES `sales_returns` WRITE;
/*!40000 ALTER TABLE `sales_returns` DISABLE KEYS */;
INSERT INTO `sales_returns` VALUES (1,6,1,'2026-01-29',120000.00,'Defective product return to RMA test.','completed',1,'2026-01-29 04:00:34'),(2,6,1,'2026-01-29',120000.00,'Second return to RMA pool test.','completed',1,'2026-01-29 04:02:59');
/*!40000 ALTER TABLE `sales_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_centers`
--

DROP TABLE IF EXISTS `service_centers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_centers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_centers`
--

LOCK TABLES `service_centers` WRITE;
/*!40000 ALTER TABLE `service_centers` DISABLE KEYS */;
INSERT INTO `service_centers` VALUES (1,'mmnn','hhjjh','6555544','','2026-01-27 11:09:47'),(2,'City Electronics Service','45 Tech Plaza, Dhaka','01711223344','service@cityelectronics.com','2026-01-28 10:22:15'),(3,'Global Support Hub','Link Road, Banani','01888776655','support@globalsupport.com','2026-01-28 10:22:15'),(4,'City Electronics Service','Dhaka, Bangladesh','01711223344','service@cityelectronics.com','2026-01-28 10:23:28'),(5,'City Electronics Service','','','','2026-01-28 10:25:00'),(6,'Quick Fix Solutions','Uttara, Sector 7','01511223344','info@quickfix.com','2026-01-28 10:26:10');
/*!40000 ALTER TABLE `service_centers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sms_settings`
--

DROP TABLE IF EXISTS `sms_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_url` varchar(255) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `sender_id` varchar(50) DEFAULT NULL,
  `sales_sms_enabled` tinyint(1) DEFAULT 0,
  `warranty_sms_enabled` tinyint(1) DEFAULT 0,
  `sales_sms_template` text DEFAULT NULL,
  `warranty_sms_template` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_settings`
--

LOCK TABLES `sms_settings` WRITE;
/*!40000 ALTER TABLE `sms_settings` DISABLE KEYS */;
INSERT INTO `sms_settings` VALUES (1,NULL,NULL,NULL,0,0,'Hello {customer_name}, your sale {invoice_number} is complete. View invoice: {invoice_link}','Hello {customer_name}, your warranty for {product_name} ({serial_number}) expires soon.','2026-01-30 03:52:41',NULL);
/*!40000 ALTER TABLE `sms_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff`
--

LOCK TABLES `staff` WRITE;
/*!40000 ALTER TABLE `staff` DISABLE KEYS */;
INSERT INTO `staff` VALUES (1,NULL,'shimul',NULL,NULL,'mn',NULL,7,'12457578','gfhgf@fg.h','mhggjhhjh','2026-01-26',NULL,NULL,NULL,NULL,NULL,NULL,'active','permanent','uploads/staff/staff_1769418426_69772eba03371.png','2026-01-26 09:07:06',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,NULL,'Kamruzzaman Shimul',NULL,NULL,'',NULL,7,'','','Mirpur','2026-01-27',NULL,NULL,NULL,NULL,NULL,NULL,'active','permanent',NULL,'2026-01-27 09:48:39',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_departments`
--

DROP TABLE IF EXISTS `staff_departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_departments`
--

LOCK TABLES `staff_departments` WRITE;
/*!40000 ALTER TABLE `staff_departments` DISABLE KEYS */;
INSERT INTO `staff_departments` VALUES (1,'Administration','General administration and management','2026-01-27 10:01:58'),(2,'Sales','Sales and business development','2026-01-27 10:01:58'),(3,'Marketing','Marketing and promotion','2026-01-27 10:01:58'),(4,'Accounts','Finance and accounting','2026-01-27 10:01:58'),(5,'Human Resources','HR and personnel management','2026-01-27 10:01:58'),(6,'IT','Information Technology and systems','2026-01-27 10:01:58'),(7,'Operations','Daily operations and logistics','2026-01-27 10:01:58');
/*!40000 ALTER TABLE `staff_departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_documents`
--

DROP TABLE IF EXISTS `staff_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(100) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_documents`
--

LOCK TABLES `staff_documents` WRITE;
/*!40000 ALTER TABLE `staff_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_roles`
--

DROP TABLE IF EXISTS `staff_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_roles`
--

LOCK TABLES `staff_roles` WRITE;
/*!40000 ALTER TABLE `staff_roles` DISABLE KEYS */;
INSERT INTO `staff_roles` VALUES (1,'Manager','Department or team manager','2026-01-26 09:00:58',NULL),(2,'Supervisor','Team supervisor','2026-01-26 09:00:58',NULL),(3,'Sales Representative','Sales and customer service','2026-01-26 09:00:58',NULL),(4,'Accountant','Finance and accounting','2026-01-26 09:00:58',NULL),(5,'Cashier','POS and cash handling','2026-01-26 09:00:58',NULL),(6,'Warehouse Staff','Inventory and warehouse management','2026-01-26 09:00:58',NULL),(7,'Delivery Person','Product delivery','2026-01-26 09:00:58',NULL),(8,'IT Support','Technical support','2026-01-26 09:00:58',NULL),(9,'General Staff','General purpose staff member','2026-01-26 09:00:58',NULL),(10,'hi','','2026-01-29 08:30:03',NULL);
/*!40000 ALTER TABLE `staff_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_adjustments`
--

DROP TABLE IF EXISTS `stock_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `adjustment_type` enum('add','subtract','opening_stock') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `date` date NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `date` (`date`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `stock_adjustments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_adjustments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_adjustments`
--

LOCK TABLES `stock_adjustments` WRITE;
/*!40000 ALTER TABLE `stock_adjustments` DISABLE KEYS */;
INSERT INTO `stock_adjustments` VALUES (1,5,NULL,'subtract',1,'Damaged Stock: jjhjhh','2026-01-26',1,'2026-01-26 16:00:19'),(2,2,NULL,'opening_stock',5,'','2026-01-26',1,'2026-01-26 16:08:54'),(3,2,NULL,'subtract',1,'Dead Stock: jkjkj','2026-01-26',1,'2026-01-26 16:11:41'),(4,2,NULL,'subtract',1,'Dead Stock: hjhhhhjjh','2026-01-26',1,'2026-01-26 16:12:24'),(5,2,NULL,'subtract',1,'Damaged Stock: vhjh','2026-01-26',1,'2026-01-26 16:16:29'),(6,2,NULL,'subtract',1,'Damaged Stock: njjk','2026-01-26',1,'2026-01-26 16:17:22'),(7,2,NULL,'subtract',2,'Damaged Stock: jhgjhjh','2026-01-26',1,'2026-01-26 16:20:43'),(8,2,NULL,'subtract',2,'Dead Stock: none','2026-01-26',1,'2026-01-26 16:24:36');
/*!40000 ALTER TABLE `stock_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_transfer_items`
--

DROP TABLE IF EXISTS `stock_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `serial_numbers` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `transfer_id` (`transfer_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `stock_transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_transfer_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_transfer_items`
--

LOCK TABLES `stock_transfer_items` WRITE;
/*!40000 ALTER TABLE `stock_transfer_items` DISABLE KEYS */;
INSERT INTO `stock_transfer_items` VALUES (1,1,1,5.00,50000.00,250000.00,NULL,NULL,'2026-01-28 09:42:22'),(2,2,2,2.00,15000.00,30000.00,NULL,NULL,'2026-01-28 09:42:22'),(3,3,1,3.00,50000.00,150000.00,NULL,NULL,'2026-01-28 09:42:22'),(4,4,5,10.00,500.00,5000.00,NULL,NULL,'2026-01-28 09:42:22'),(5,5,1,1.00,50000.00,50000.00,NULL,NULL,'2026-01-28 09:42:22');
/*!40000 ALTER TABLE `stock_transfer_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_transfers`
--

DROP TABLE IF EXISTS `stock_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(50) NOT NULL,
  `transfer_date` date NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_warehouse_id` int(11) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `challan_number` varchar(100) DEFAULT NULL,
  `transfer_type` enum('normal','damaged_to_good','return_to_good','product_to_rma','rma_to_product') DEFAULT 'normal',
  `status` enum('pending','approved','in_transit','completed','rejected','cancelled') DEFAULT 'pending',
  `total_items` int(11) DEFAULT 0,
  `total_quantity` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_number` (`transfer_number`),
  KEY `from_warehouse_id` (`from_warehouse_id`),
  KEY `to_warehouse_id` (`to_warehouse_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `stock_transfers_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_transfers`
--

LOCK TABLES `stock_transfers` WRITE;
/*!40000 ALTER TABLE `stock_transfers` DISABLE KEYS */;
INSERT INTO `stock_transfers` VALUES (1,'TRF-20260128-0101','2026-01-28',1,4,NULL,NULL,'normal','approved',1,5.00,'Demo data generated',1,1,'2026-01-28 09:44:10',NULL,'2026-01-28 09:42:22','2026-01-28 09:44:10'),(2,'TRF-20260128-0102','2026-01-28',1,2,NULL,NULL,'damaged_to_good','approved',1,2.00,'Demo data generated',1,1,'2026-01-28 09:42:22',NULL,'2026-01-28 09:42:22','2026-01-28 09:42:22'),(3,'TRF-20260128-0103','2026-01-28',4,1,NULL,NULL,'normal','in_transit',1,3.00,'Demo data generated',1,1,'2026-01-28 09:42:22',NULL,'2026-01-28 09:42:22','2026-01-28 09:42:22'),(4,'TRF-20260128-0104','2026-01-28',3,1,NULL,NULL,'return_to_good','completed',1,10.00,'Demo data generated',1,1,'2026-01-28 09:42:22','2026-01-28 09:42:22','2026-01-28 09:42:22','2026-01-28 09:42:22'),(5,'TRF-20260128-0105','2026-01-28',1,4,NULL,NULL,'normal','cancelled',1,1.00,'Demo data generated',1,NULL,NULL,NULL,'2026-01-28 09:42:22','2026-01-28 09:42:22');
/*!40000 ALTER TABLE `stock_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_ledger`
--

DROP TABLE IF EXISTS `supplier_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL COMMENT 'purchase, payment, return, opening_balance',
  `reference_id` int(11) DEFAULT NULL,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `date` (`date`),
  CONSTRAINT `supplier_ledger_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_ledger`
--

LOCK TABLES `supplier_ledger` WRITE;
/*!40000 ALTER TABLE `supplier_ledger` DISABLE KEYS */;
INSERT INTO `supplier_ledger` VALUES (1,2,'purchase',1,1250.00,0.00,1250.00,'Purchase #PUR-20260126-7885','2026-01-26','2026-01-26 16:39:10'),(2,2,'purchase',2,96000.00,0.00,96000.00,'Purchase #PUR-20260126-4378','2026-01-26','2026-01-26 16:39:20'),(3,2,'purchase',3,922250.00,0.00,922250.00,'Purchase #PUR-20260128-9505','2026-01-28','2026-01-28 10:06:25'),(4,2,'return',2,855000.00,0.00,158500.00,'Purchase Return for Purchase #PUR-20260128-9505','2026-01-29','2026-01-29 04:19:31'),(5,2,'return',3,0.00,0.00,158500.00,'Purchase Return for Purchase #PUR-20260128-9505','2026-01-29','2026-01-29 04:30:54'),(6,2,'payment',2,6000.00,0.00,0.00,'Payment for Purchase #PUR-20260126-4378','2026-01-29','2026-01-29 10:24:31'),(7,2,'payment',3,22250.00,0.00,0.00,'Payment for Purchase #PUR-20260128-9505','2026-01-29','2026-01-29 10:28:02'),(8,2,'payment',4,9000.00,0.00,0.00,'Payment for Purchase #PUR-20260126-4378','2026-01-29','2026-01-29 10:28:32');
/*!40000 ALTER TABLE `supplier_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `payment_terms` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'Tech Supplies Ltd','02-9876543','tech@supplier.com','Gulshan, Dhaka',0.00,0.00,'Net 30 days','active','2026-01-26 09:53:50',NULL),(2,'Fashion Wholesale','02-9876544','fashion@supplier.com','Banani, Dhaka',10000.00,121250.00,'Net 15 days','active','2026-01-26 09:53:50','2026-01-29 10:28:32'),(3,'Food Distributors','02-9876545','food@supplier.com','Uttara, Dhaka',0.00,0.00,'Cash on delivery','active','2026-01-26 09:53:50',NULL),(4,'Furniture Factory','02-9876546','furniture@supplier.com','Gazipur',5000.00,5000.00,'Net 45 days','active','2026-01-26 09:53:50',NULL),(5,'Office Essentials','02-9876547','office@supplier.com','Mirpur, Dhaka',0.00,0.00,'Net 30 days','active','2026-01-26 09:53:50',NULL),(6,'Test Supp','123',NULL,NULL,0.00,0.00,NULL,'active','2026-01-28 10:48:11',NULL);
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `short_name` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `units`
--

LOCK TABLES `units` WRITE;
/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` VALUES (1,'Piece','Pcs','2026-01-25 13:49:30'),(2,'Box','Box','2026-01-25 13:49:30'),(3,'Carton','Ctn','2026-01-25 13:49:30'),(4,'Kilogram','Kg','2026-01-25 13:49:30'),(5,'Liter','L','2026-01-25 13:49:30'),(11,'Dozen','dz','2026-01-26 09:53:50'),(12,'demo','demo','2026-01-29 03:16:38');
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 2,
  `photo` varchar(255) DEFAULT NULL,
  `theme_preference` enum('light','dark') DEFAULT 'dark',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `status` (`status`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@business.com','$2y$10$fDEVnatU7EwngbBghH9wZ.dya4to7PQax/h4YB1MostWFxhReNofO',1,NULL,'light','active','2026-01-30 14:08:01','::1','2026-01-25 13:49:30','2026-01-30 08:08:01'),(2,'accountant','accountant@g.co','$2y$10$EsghfC0qUhDcE43yp6HrnOuoWZeFooqUCM2663EL2CugOYNBnjNgG',3,'uploads/users/user_1769481416_697824c879309.png','dark','active',NULL,NULL,'2026-01-25 03:25:51','2026-01-26 15:36:56'),(3,'cashier','cashier@g.co','$2y$10$jlJ/YSwdTU4BHIt2ezVNsu1XjkqtO4wVg.9Y4Z/AkJz4CNDG5FFLq',3,NULL,'dark','active',NULL,NULL,'2026-01-25 06:52:01',NULL),(4,'test_user_1769597291','test_user_1769597291@test.com','$2y$10$xJrouYCaltTC2e9aBLVn8.8vlwDl.UhRMxuEOAtJBKx9MFATHEgO2',1,NULL,'dark','active',NULL,NULL,'2026-01-28 10:48:11',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warehouses`
--

DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `is_saleable` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `manager_id` (`manager_id`),
  CONSTRAINT `warehouses_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouses`
--

LOCK TABLES `warehouses` WRITE;
/*!40000 ALTER TABLE `warehouses` DISABLE KEYS */;
INSERT INTO `warehouses` VALUES (1,'MAIN','Main Warehouse','Main Storage Location',NULL,NULL,NULL,NULL,'active','2026-01-28 09:30:31','2026-01-28 09:30:31',1),(2,'DMG','Damaged Stock','Damaged Items Storage',NULL,NULL,NULL,NULL,'active','2026-01-28 09:30:31','2026-01-28 09:37:37',0),(3,'RTN','Return Stock','Returned Items Storage',NULL,NULL,NULL,NULL,'active','2026-01-28 09:30:31','2026-01-28 09:37:37',0),(4,'BR1','Branch 1','Branch 1 Location',NULL,NULL,NULL,NULL,'active','2026-01-28 09:30:31','2026-01-28 09:30:31',1);
/*!40000 ALTER TABLE `warehouses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `yearly_closings`
--

DROP TABLE IF EXISTS `yearly_closings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `yearly_closings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_year` (`closing_year`),
  KEY `closed_by` (`closed_by`),
  KEY `idx_status` (`status`),
  KEY `idx_year` (`closing_year`),
  CONSTRAINT `yearly_closings_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `yearly_closings`
--

LOCK TABLES `yearly_closings` WRITE;
/*!40000 ALTER TABLE `yearly_closings` DISABLE KEYS */;
INSERT INTO `yearly_closings` VALUES (1,2000,'2000-12-31',490000.00,380000.00,110000.00,1100000.00,522000.00,578000.00,514800.00,108900.00,'Year-end closing. Revenue targets exceeded by 17%.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(2,2001,'2001-12-31',475200.00,388800.00,86400.00,1123200.00,598500.00,524700.00,458265.60,89856.00,'Year-end financial reconciliation completed successfully.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(3,2002,'2002-12-31',507384.00,419904.00,87480.00,1143072.00,615195.00,527877.00,438939.65,139454.78,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(4,2003,'2003-12-31',617258.88,488768.26,128490.62,1385683.20,590388.75,795294.45,587529.68,153810.84,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(5,2004,'2004-12-31',653034.70,565963.41,87071.29,1292464.51,670959.45,621505.06,527325.52,124076.59,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(6,2005,'2005-12-31',705277.48,523080.80,182196.68,1616260.88,704507.42,911753.46,568923.83,126068.35,'Annual financial closing completed. Strong performance in Q4.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(7,2006,'2006-12-31',864846.51,641097.23,223749.28,1523399.35,683448.78,839950.57,603266.14,190424.92,'Fiscal year closure. Overall positive growth trajectory.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(8,2007,'2007-12-31',728375.31,685529.71,42845.60,1730962.51,785162.04,945800.47,650841.90,173096.25,'Year-end financial reconciliation completed successfully.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(9,2008,'2008-12-31',1045775.57,599701.39,446074.18,1943476.72,868743.80,1074732.92,715199.43,227386.78,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(10,2009,'2009-12-31',859571.99,799601.85,59970.14,1899054.40,809793.33,1089261.07,774814.20,222189.36,'Fiscal year closure. Overall positive growth trajectory.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(11,2010,'2010-12-31',1165819.50,820391.50,345428.00,2029389.50,967563.41,1061826.09,787403.13,243526.74,'Year-end closing. Revenue targets exceeded by 14%.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(12,2011,'2011-12-31',1270743.25,876696.26,394046.99,2285006.22,985155.47,1299850.75,877442.39,281055.77,'Fiscal year closure. Overall positive growth trajectory.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(13,2012,'2012-12-31',1070222.30,977050.01,93172.29,2341898.21,991312.69,1350585.52,1039802.81,243557.41,'Year-end financial reconciliation completed successfully.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(14,2013,'2013-12-31',1414204.34,968186.05,446018.29,2474857.59,1074820.01,1400037.58,970144.18,287083.48,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(15,2014,'2014-12-31',1409852.94,951650.73,458202.21,2907821.69,1140440.60,1767381.09,1011921.95,346030.78,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(16,2015,'2015-12-31',1617806.25,1154669.56,463136.69,3425942.64,1072726.94,2353215.70,1562229.84,393983.40,'Annual financial closing completed. Strong performance in Q4.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(17,2016,'2016-12-31',1473155.34,1301858.20,171297.14,3151867.23,1257335.76,1894531.47,1487681.33,343553.53,'Year-end financial reconciliation completed successfully.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(18,2017,'2017-12-31',1924009.39,1184005.78,740003.61,3552017.33,1265194.11,2286823.22,1605511.83,301921.47,'Year-end closing. Revenue targets exceeded by 10%.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(19,2018,'2018-12-31',1918089.36,1630375.96,287713.40,4395621.45,1429531.82,2966089.63,1986820.90,536265.82,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(20,2019,'2019-12-31',1877329.96,1726280.42,151049.54,4488329.10,1288744.60,3199584.50,2064631.39,507181.19,'Year-end closing. Revenue targets exceeded by 16%.','audited',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(21,2020,'2020-12-31',2470307.29,1938958.17,531349.12,4521128.43,1369101.62,3152026.81,2007381.02,569662.18,'Fiscal year closure. Overall positive growth trajectory.','finalized',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(22,2021,'2021-12-31',2542086.03,1731638.80,810447.23,5335863.74,1420840.92,3915022.82,2561214.60,629631.92,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','finalized',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(23,2022,'2022-12-31',2854183.72,1739692.93,1114490.79,5327809.60,1684950.17,3642859.43,2408169.94,394257.91,'Fiscal year closure. Overall positive growth trajectory.','finalized',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(24,2023,'2023-12-31',3141233.05,1949325.93,1191907.12,6047607.55,1787626.83,4259980.72,1935234.42,423332.53,'Annual financial closing completed. Strong performance in Q4.','finalized',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(25,2024,'2024-12-31',2821825.43,2409648.68,412176.75,6087533.51,1741553.97,4345979.54,2532413.94,462652.55,'Annual financial closing completed. Strong performance in Q4.','finalized',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(26,2025,'2025-12-31',3081813.84,2629814.48,451999.36,6574536.19,2031812.96,4542723.23,2524621.90,637730.01,'Year-end closing. Revenue targets exceeded by 12%.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(27,2026,'2026-12-31',4215921.33,3076882.94,1139038.39,7840134.40,1984065.36,5856069.04,3543740.75,854574.65,'Fiscal year closure. Overall positive growth trajectory.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(28,2027,'2027-12-31',4593135.34,2683988.65,1909146.69,8547225.77,2105669.37,6441556.40,3418890.31,658136.38,'Year-end closing. Revenue targets exceeded by 16%.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(29,2028,'2028-12-31',3709655.75,3588876.26,120779.49,7764395.75,2305035.93,5459359.82,3105758.30,621151.66,'Year-end closing. Revenue targets exceeded by 10%.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(30,2029,'2029-12-31',4425705.58,3764179.06,661526.52,9783138.64,2272106.85,7511031.79,4421978.67,812000.51,'Year-end closing. Revenue targets exceeded by 6%.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(32,2031,'2031-12-31',5433834.72,3564595.58,1869239.14,10324285.97,2504997.80,7819288.17,4418794.40,970482.88,'Fiscal year closure. Overall positive growth trajectory.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(33,2032,'2032-12-31',5633799.84,4882626.53,751173.31,11267599.68,2830375.23,8437224.45,5408447.85,1419717.56,'Fiscal year closure. Overall positive growth trajectory.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(34,2033,'2033-12-31',6845066.80,4969011.46,1876055.34,11408444.67,2551626.16,8856818.51,3787603.63,1426055.58,'Fiscal year closure. Overall positive growth trajectory.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(35,2034,'2034-12-31',5886757.45,4709405.96,1177351.49,13963936.28,2836807.90,11127128.38,6646833.67,1312610.01,'Year-end financial reconciliation completed successfully.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(36,2035,'2035-12-31',8427646.25,5677572.21,2750074.04,13306809.86,3011744.39,10295065.47,4843678.79,1516976.32,'Fiscal year closure. Overall positive growth trajectory.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(37,2036,'2036-12-31',8463131.07,6004032.61,2459098.46,15808490.12,3092829.82,12715660.30,6576331.89,1454381.09,'Year-end closing. Revenue targets exceeded by 6%.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(38,2037,'2037-12-31',8105444.02,6277407.71,1828036.31,15693519.28,3393425.07,12300094.21,5586892.86,1506577.85,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(39,2038,'2038-12-31',9405764.19,7152105.84,2253658.35,19184033.90,3409844.87,15774189.03,6829516.07,1534722.71,'Fiscal year closure. Overall positive growth trajectory.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(40,2039,'2039-12-31',8649578.00,6758740.02,1890837.98,20517603.64,3580337.12,16937266.52,8453252.70,1908137.14,'Year-end financial reconciliation completed successfully.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(41,2040,'2040-12-31',12382977.25,8429114.34,3953862.91,19552069.35,4055033.50,15497035.85,6569495.30,2150727.63,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(42,2041,'2041-12-31',13490927.85,8164944.16,5325983.69,24870232.21,4346489.03,20523743.18,8555359.88,2387542.29,'Year-end closing. Revenue targets exceeded by 18%.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(43,2042,'2042-12-31',13049833.17,10135792.75,2914040.42,24832692.24,4563813.48,20268878.76,11125046.12,2731596.15,'Annual financial closing completed. Strong performance in Q4.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(44,2043,'2043-12-31',13820153.41,10289856.80,3530296.61,27092974.02,4351922.14,22741051.88,12787883.74,2086159.00,'Annual accounts closed. Some challenges in Q2, recovered in Q4.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(45,2044,'2044-12-31',12856847.67,9576134.82,3280712.85,29260411.94,4774889.86,24485522.08,9480373.47,2955301.61,'Annual financial closing completed. Strong performance in Q4.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(46,2045,'2045-12-31',17077440.42,13151225.15,3926215.27,30005222.43,5283184.58,24722037.85,14162464.99,3060532.69,'Year-end financial reconciliation completed successfully.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(47,2046,'2046-12-31',14996227.12,11859085.36,3137141.76,34129344.49,5207710.52,28921633.97,13788255.17,2764476.90,'Year-end financial reconciliation completed successfully.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(48,2047,'2047-12-31',21408407.00,15041732.92,6366674.08,34625771.32,5170916.91,29454854.41,13296296.19,3012442.10,'Year-end financial reconciliation completed successfully.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(49,2048,'2048-12-31',17893705.05,16405913.84,1487791.21,37395833.02,5803908.46,31591924.56,16454166.53,4487499.96,'Annual financial closing completed. Strong performance in Q4.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(50,2049,'2049-12-31',21062298.21,17197257.92,3865040.29,46033064.13,5766463.89,40266600.24,20806944.99,5846199.14,'Year-end closing. Revenue targets exceeded by 15%.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(51,2050,'2050-12-31',23216298.19,19511070.81,3705227.38,42211451.26,6811635.47,35399815.79,19586113.38,3503550.45,'Fiscal year closure. Overall positive growth trajectory.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(52,2051,'2051-12-31',29125901.37,16817042.18,12308859.19,49640666.68,6935483.39,42705183.29,23430394.67,5112988.67,'Fiscal year closure. Overall positive growth trajectory.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(53,2052,'2052-12-31',31182443.28,22976537.15,8205906.13,60176644.92,7358114.41,52818530.51,19497232.95,5295544.75,'Year-end closing. Revenue targets exceeded by 14%.','draft',1,'2026-01-27 09:07:54','2026-01-27 09:07:54'),(54,2030,'2026-12-31',6555555.00,65524.00,6490031.00,555666.00,9696.00,545970.00,6666.00,6666.00,'','finalized',1,'2026-01-29 08:55:21','2026-01-29 08:55:21');
/*!40000 ALTER TABLE `yearly_closings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-30 14:52:14

