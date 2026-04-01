-- Add cloud storage credentials columns to backup_settings
ALTER TABLE backup_settings
ADD COLUMN IF NOT EXISTS onedrive_enabled TINYINT(1) DEFAULT 0
AFTER dropbox_access_token,
    ADD COLUMN IF NOT EXISTS onedrive_access_token TEXT
AFTER onedrive_enabled,
    ADD COLUMN IF NOT EXISTS onedrive_refresh_token TEXT
AFTER onedrive_access_token,
    ADD COLUMN IF NOT EXISTS onedrive_folder_id VARCHAR(255)
AFTER onedrive_refresh_token,
    ADD COLUMN IF NOT EXISTS aws_s3_enabled TINYINT(1) DEFAULT 0
AFTER onedrive_folder_id,
    ADD COLUMN IF NOT EXISTS aws_s3_bucket VARCHAR(255)
AFTER aws_s3_enabled,
    ADD COLUMN IF NOT EXISTS aws_s3_key VARCHAR(255)
AFTER aws_s3_bucket,
    ADD COLUMN IF NOT EXISTS aws_s3_secret TEXT
AFTER aws_s3_key,
    ADD COLUMN IF NOT EXISTS aws_s3_region VARCHAR(50) DEFAULT 'us-east-1'
AFTER aws_s3_secret;