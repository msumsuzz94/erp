-- ========================================================
-- Migration: 6 New Modules Database Schema
-- Date: 2026-03-04
-- ========================================================

-- =============================================
-- MODULE 2: RBAC - Add SR Role
-- =============================================
INSERT INTO roles (name, description) 
SELECT 'Sales Representative', 'SR - Can create sales requests, view products (no purchase price)'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Sales Representative');

-- =============================================
-- MODULE 1: Sales Requests & Serial Tracking
-- =============================================
CREATE TABLE IF NOT EXISTS sales_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) UNIQUE,
    sr_user_id INT NOT NULL,
    customer_id INT NULL,
    customer_name VARCHAR(255),
    customer_phone VARCHAR(50),
    items JSON,
    total_amount DECIMAL(15,2) DEFAULT 0,
    discount DECIMAL(15,2) DEFAULT 0,
    net_amount DECIMAL(15,2) DEFAULT 0,
    status ENUM('pending','approved','processing','completed','rejected') DEFAULT 'pending',
    approved_by INT NULL,
    completed_sale_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sr_user (sr_user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add serial_number to sale_items if not exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_items' AND COLUMN_NAME = 'serial_number');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE sale_items ADD COLUMN serial_number VARCHAR(100) NULL AFTER product_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- MODULE 4: Market Data / CRM
-- =============================================
CREATE TABLE IF NOT EXISTS lead_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default categories
INSERT INTO lead_categories (name, description) VALUES
('বাজার', 'Market / Shop'),
('ব্যাংক', 'Bank / Financial Institution'),
('এনজিও', 'NGO / Non-Profit'),
('শিক্ষা প্রতিষ্ঠান', 'Educational Institution'),
('কর্পোরেট অফিস', 'Corporate Office'),
('সরকারি প্রতিষ্ঠান', 'Government Organization'),
('অন্যান্য', 'Others')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    organization_name VARCHAR(255),
    contact_person VARCHAR(255),
    mobile VARCHAR(50),
    email VARCHAR(255),
    address TEXT,
    remarks TEXT,
    collected_by INT NOT NULL,
    gps_latitude DECIMAL(10,8) NULL,
    gps_longitude DECIMAL(11,8) NULL,
    status ENUM('new','contacted','converted','closed') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_collected_by (collected_by),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- MODULE 5: Marketing & Communication
-- =============================================
CREATE TABLE IF NOT EXISTS marketing_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    channel ENUM('sms','email','whatsapp','telegram') NOT NULL DEFAULT 'sms',
    subject VARCHAR(255) NULL,
    body TEXT NOT NULL,
    variables JSON,
    status ENUM('active','inactive') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    template_id INT,
    channel ENUM('sms','email','whatsapp','telegram') DEFAULT 'sms',
    target_type ENUM('all_customers','lead_category','custom') DEFAULT 'all_customers',
    target_category_id INT NULL,
    custom_recipients JSON NULL,
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    status ENUM('draft','sending','completed','failed') DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_campaign_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    recipient_name VARCHAR(255),
    recipient_contact VARCHAR(255),
    status ENUM('sent','failed','pending') DEFAULT 'pending',
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    INDEX idx_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- MODULE 5: Marketing Settings
-- =============================================
CREATE TABLE IF NOT EXISTS marketing_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO marketing_settings (setting_key, setting_value) VALUES
('email_smtp_host', ''),
('email_smtp_port', '587'),
('email_smtp_user', ''),
('email_smtp_pass', ''),
('email_from_name', ''),
('email_from_address', ''),
('whatsapp_api_url', ''),
('whatsapp_api_key', ''),
('telegram_bot_token', ''),
('sms_gateway_url', ''),
('sms_gateway_key', '')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

-- =============================================
-- MODULE 6: OTA Auto-Update System
-- =============================================
CREATE TABLE IF NOT EXISTS system_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(20),
    description TEXT,
    download_url VARCHAR(500),
    changelog TEXT,
    status ENUM('available','downloading','installing','completed','failed') DEFAULT 'available',
    installed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- MENU ITEMS for new modules
-- =============================================

-- Sales Request menu (under Sales)
INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active) 
SELECT 'Sales Requests', 'sales.requests', '/modules/sales-request/request-list.php', 'fas fa-clipboard-list', 
    (SELECT id FROM menu_items WHERE name = 'Sales / POS' AND parent_id IS NULL LIMIT 1), 5, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'sales.requests');

-- Staff Performance (under Reports)
INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active)
SELECT 'Staff Performance', 'reports.staff', '/modules/reports/staff-performance.php', 'fas fa-user-chart',
    (SELECT id FROM menu_items WHERE name = 'Reports' AND parent_id IS NULL LIMIT 1), 15, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'reports.staff');

-- Market Data (top-level)
INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active)
SELECT 'Market Data', 'market', '#', 'fas fa-database', NULL, 55, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'market');

-- Market Data sub-items
INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active)
SELECT 'Lead Categories', 'market.categories', '/modules/crm/lead-categories.php', 'fas fa-tags',
    (SELECT id FROM menu_items WHERE slug = 'market' LIMIT 1), 1, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'market.categories');

INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active)
SELECT 'Add Lead', 'market.add', '/modules/crm/lead-add.php', 'fas fa-plus-circle',
    (SELECT id FROM menu_items WHERE slug = 'market' LIMIT 1), 2, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'market.add');

INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active)
SELECT 'Lead List', 'market.list', '/modules/crm/lead-list.php', 'fas fa-list',
    (SELECT id FROM menu_items WHERE slug = 'market' LIMIT 1), 3, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'market.list');

-- Marketing (top-level)
INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active)
SELECT 'Marketing', 'marketing', '#', 'fas fa-bullhorn', NULL, 60, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'marketing');

INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active)
SELECT 'Templates', 'marketing.templates', '/modules/marketing/templates.php', 'fas fa-file-alt',
    (SELECT id FROM menu_items WHERE slug = 'marketing' LIMIT 1), 1, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'marketing.templates');

INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active)
SELECT 'Campaigns', 'marketing.campaigns', '/modules/marketing/campaigns.php', 'fas fa-paper-plane',
    (SELECT id FROM menu_items WHERE slug = 'marketing' LIMIT 1), 2, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'marketing.campaigns');

INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active)
SELECT 'SMS Send', 'marketing.sms', '/modules/marketing/sms-send.php', 'fas fa-sms',
    (SELECT id FROM menu_items WHERE slug = 'marketing' LIMIT 1), 3, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'marketing.sms');

INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active)
SELECT 'Settings', 'marketing.settings', '/modules/marketing/settings.php', 'fas fa-cog',
    (SELECT id FROM menu_items WHERE slug = 'marketing' LIMIT 1), 4, 1
WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE slug = 'marketing.settings');
