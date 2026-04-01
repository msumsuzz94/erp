<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

// Add footer_copyright_text column to business_settings table
$sql = "ALTER TABLE `business_settings` ADD COLUMN `footer_copyright_text` TEXT DEFAULT NULL AFTER `display_in_menu`;";

try {
    db_query($sql);
    echo "Successfully added footer_copyright_text column to business_settings table.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column footer_copyright_text already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
