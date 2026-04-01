<?php
/**
 * Database Migration: Add Theme Preference to Users Table
 * Adds theme_preference column to users table for dark/light mode persistence
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';

try {
    // Check if column already exists
    $check_sql = "SHOW COLUMNS FROM `users` LIKE 'theme_preference'";
    $result = db_query($check_sql);
    
    if (count($result) > 0) {
        echo "✓ Column 'theme_preference' already exists in users table.\n";
        exit(0);
    }
    
    // Add theme_preference column
    $alter_sql = "ALTER TABLE `users` 
                  ADD COLUMN `theme_preference` ENUM('light', 'dark') DEFAULT 'dark' 
                  AFTER `photo`";
    
    db_query($alter_sql);
    
    echo "✓ Successfully added 'theme_preference' column to users table.\n";
    echo "✓ Default theme set to 'dark' for all users.\n";
    
    // Update existing users to have dark theme as default
    $update_sql = "UPDATE `users` SET `theme_preference` = 'dark' WHERE `theme_preference` IS NULL";
    db_query($update_sql);
    
    echo "✓ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
