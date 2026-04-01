<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

// Add software_developed_by column to invoice_settings table
$sql = "ALTER TABLE `invoice_settings` ADD COLUMN `software_developed_by` VARCHAR(255) DEFAULT 'Software Developed By Shimul' AFTER `show_print_time`;";

try {
    db_query($sql);
    echo "Successfully added software_developed_by column to invoice_settings table.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column software_developed_by already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
