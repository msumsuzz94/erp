-- Backup System Database Schema
-- Creates tables for managing backups, imports, and settings
-- Backup Settings Table
CREATE TABLE IF NOT EXISTS backup_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    auto_backup_enabled TINYINT(1) DEFAULT 0,
    backup_frequency ENUM('daily', 'weekly', 'monthly') DEFAULT 'daily',
    backup_time TIME DEFAULT '02:00:00',
    local_backup_enabled TINYINT(1) DEFAULT 1,
    local_backup_path VARCHAR(500) DEFAULT '/backups/database/',
    google_drive_enabled TINYINT(1) DEFAULT 0,
    google_drive_folder_id VARCHAR(255),
    google_drive_credentials TEXT,
    dropbox_enabled TINYINT(1) DEFAULT 0,
    dropbox_access_token TEXT,
    retention_days INT DEFAULT 30,
    compress_backups TINYINT(1) DEFAULT 1,
    last_backup_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Insert default settings
INSERT INTO backup_settings (id, local_backup_enabled, retention_days)
VALUES (1, 1, 30) ON DUPLICATE KEY
UPDATE id = id;
-- Backup History Table
CREATE TABLE IF NOT EXISTS backup_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_name VARCHAR(255) NOT NULL,
    backup_type ENUM('manual', 'automatic') DEFAULT 'manual',
    backup_size BIGINT DEFAULT 0,
    file_path VARCHAR(500),
    destinations TEXT COMMENT 'JSON array of destinations',
    status ENUM('success', 'failed', 'partial') DEFAULT 'success',
    tables_backed_up INT DEFAULT 0,
    total_records BIGINT DEFAULT 0,
    error_message TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    INDEX idx_backup_type (backup_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- Import History Table
CREATE TABLE IF NOT EXISTS import_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_file VARCHAR(255) NOT NULL,
    file_size BIGINT DEFAULT 0,
    total_records INT DEFAULT 0,
    imported_records INT DEFAULT 0,
    skipped_records INT DEFAULT 0,
    updated_records INT DEFAULT 0,
    failed_records INT DEFAULT 0,
    status ENUM('success', 'failed', 'partial') DEFAULT 'success',
    import_mode ENUM(
        'skip_duplicates',
        'update_existing',
        'merge_smart'
    ) DEFAULT 'skip_duplicates',
    import_details TEXT COMMENT 'JSON with per-table stats',
    error_message TEXT,
    imported_by INT,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    duration_seconds INT,
    INDEX idx_imported_at (imported_at),
    FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;