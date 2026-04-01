<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';

try {
    $sql = file_get_contents(__DIR__ . '/20260129_add_quotation_item_description.sql');
    if (db_query($sql) !== false) {
        echo "Migration successful: description column added to quotation_items.\n";
    } else {
        echo "Migration failed or column already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
