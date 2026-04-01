<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $sql = file_get_contents(__DIR__ . '/20260129_add_return_type_to_sales_return.sql');
    if ($sql === false) {
        throw new Exception("Could not read migration file");
    }
    
    // Split by semicolon if there are multiple queries
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            dbExecute($query);
            echo "Executed: " . substr($query, 0, 50) . "...<br>";
        }
    }
    echo "Migration completed successfully!";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
