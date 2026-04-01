-- ==============================================
-- Biometric Attendance System - Database Migration
-- ==============================================

-- 1. Staff Biometrics (Face Vectors + WebAuthn Tokens)
CREATE TABLE IF NOT EXISTS staff_biometrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    biometric_type ENUM('face','webauthn') NOT NULL DEFAULT 'face',
    face_descriptor TEXT NULL COMMENT 'JSON array of face-api.js descriptor (128 floats)',
    face_photo VARCHAR(255) NULL COMMENT 'Path to enrollment photo',
    webauthn_credential_id TEXT NULL COMMENT 'Base64 credential ID',
    webauthn_public_key TEXT NULL COMMENT 'Base64 public key',
    webauthn_counter INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff (staff_id),
    INDEX idx_type (biometric_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. CCTV Configurations
CREATE TABLE IF NOT EXISTS cctv_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camera_name VARCHAR(100) NOT NULL,
    rtsp_url VARCHAR(500) NOT NULL,
    track_function ENUM('entry_tracking','away_tracking') NOT NULL DEFAULT 'entry_tracking',
    location VARCHAR(200) NULL,
    is_active TINYINT(1) DEFAULT 1,
    away_timeout_minutes INT DEFAULT 5 COMMENT 'Minutes before marking away',
    last_status VARCHAR(50) DEFAULT 'offline' COMMENT 'online/offline/error',
    last_check TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. CCTV Tracking Logs
CREATE TABLE IF NOT EXISTS cctv_tracking_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    camera_id INT NOT NULL,
    event_type ENUM('present','away','return') NOT NULL,
    away_start_time TIMESTAMP NULL,
    return_time TIMESTAMP NULL,
    duration_minutes DECIMAL(10,2) NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_staff_date (staff_id, date),
    INDEX idx_camera (camera_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Attendance Settings
CREATE TABLE IF NOT EXISTS attendance_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_group VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT IGNORE INTO attendance_settings (setting_key, setting_value, setting_group, description) VALUES
('gps_enabled', '1', 'mobile', 'Enable GPS verification for mobile check-in'),
('gps_latitude', '0', 'mobile', 'Office latitude'),
('gps_longitude', '0', 'mobile', 'Office longitude'),
('gps_radius_meters', '100', 'mobile', 'Allowed radius in meters'),
('face_confidence_threshold', '0.5', 'face', 'Minimum face match confidence (0-1)'),
('face_detection_enabled', '1', 'face', 'Enable face detection for attendance'),
('webauthn_enabled', '1', 'webauthn', 'Enable WebAuthn biometric'),
('kiosk_pin', '1234', 'kiosk', 'PIN to exit kiosk mode'),
('kiosk_welcome_sound', '1', 'kiosk', 'Play sound on successful check-in'),
('cctv_away_timeout', '5', 'cctv', 'Minutes before marking staff away'),
('cctv_python_url', 'http://localhost:5050', 'cctv', 'Python CCTV middleware URL'),
('late_notify_enabled', '1', 'notification', 'Send notification on late arrival'),
('absent_notify_enabled', '1', 'notification', 'Send notification on absence'),
('away_notify_enabled', '1', 'notification', 'Send notification when staff goes away via CCTV');

-- 5. Alter attendance_logs to add source column if not exists  
-- (Safe: uses ALTER IGNORE pattern)
ALTER TABLE attendance_logs ADD COLUMN IF NOT EXISTS source VARCHAR(30) DEFAULT 'manual' COMMENT 'manual/web_biometric/web_face/webcam_kiosk/machine/cctv';
ALTER TABLE attendance_logs ADD COLUMN IF NOT EXISTS gps_latitude DECIMAL(10,8) NULL;
ALTER TABLE attendance_logs ADD COLUMN IF NOT EXISTS gps_longitude DECIMAL(11,8) NULL;
ALTER TABLE attendance_logs ADD COLUMN IF NOT EXISTS face_confidence DECIMAL(5,4) NULL;
ALTER TABLE attendance_logs ADD COLUMN IF NOT EXISTS device_info VARCHAR(255) NULL COMMENT 'Browser/device user agent';
