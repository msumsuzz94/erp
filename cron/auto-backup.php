<?php

/**
 * Automatic Backup Cron Job
 * Run this script daily via cron/task scheduler
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/backup_functions.php';
require_once __DIR__ . '/../includes/cloud_storage.php';

// Check if auto backup is enabled
$settings = db_select_one('backup_settings', ['id' => 1]);

if (!$settings || !$settings['auto_backup_enabled']) {
    exit("Auto backup is disabled\n");
}

// Check if it's time to run
$current_time = date('H:i:00');
$backup_time = $settings['backup_time'];

// Allow 5-minute window
if (abs(strtotime($current_time) - strtotime($backup_time)) > 300) {
    exit("Not time for backup yet\n");
}

// Check frequency
$last_backup = $settings['last_backup_at'];
$frequency = $settings['backup_frequency'];

if ($last_backup) {
    $hours_since_last = (time() - strtotime($last_backup)) / 3600;

    if ($frequency === 'daily' && $hours_since_last < 20) {
        exit("Backup already done today\n");
    } elseif ($frequency === 'weekly' && $hours_since_last < 144) {
        exit("Backup already done this week\n");
    } elseif ($frequency === 'monthly' && $hours_since_last < 672) {
        exit("Backup already done this month\n");
    }
}

echo "Starting automatic backup...\n";

// Create backup
$result = create_database_backup([
    'type' => 'automatic',
    'compress' => (bool)$settings['compress_backups'],
    'destinations' => ['local']
]);

if ($result['success']) {
    echo "✓ Backup created successfully: {$result['backup_name']}\n";
    echo "  Size: " . format_bytes($result['file_size']) . "\n";
    echo "  Tables: {$result['tables']}\n";
    echo "  Records: {$result['records']}\n";

    // Upload to cloud storage if enabled
    $cloud_results = upload_backup_to_cloud($result['file_path']);

    if (!empty($cloud_results)) {
        echo "\nUploading to cloud storage...\n";

        foreach ($cloud_results as $service => $cloud_result) {
            if ($cloud_result['success']) {
                echo "  ✓ " . ucfirst(str_replace('_', ' ', $service)) . ": Uploaded successfully\n";
            } else {
                echo "  ✗ " . ucfirst(str_replace('_', ' ', $service)) . ": " . ($cloud_result['error'] ?? 'Failed') . "\n";
            }
        }
    }

    // Clean up old backups
    $deleted = cleanup_old_backups();
    if ($deleted > 0) {
        echo "\n✓ Cleaned up $deleted old backup(s)\n";
    }
} else {
    echo "✗ Backup failed: {$result['error']}\n";
}
