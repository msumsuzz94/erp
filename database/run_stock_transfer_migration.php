<?php
/**
 * Run Stock Transfer Migration
 * Executes the database schema updates for RMA and Damaged stock support
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

echo "Starting Stock Transfer Migration...\n\n";

try {
    // Read the SQL file
    $sql_file = __DIR__ . '/migrations/update_stock_transfer_for_rma.sql';
    
    if (!file_exists($sql_file)) {
        die("ERROR: Migration file not found: $sql_file\n");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Remove comments and split into individual statements
    $lines = explode("\n", $sql);
    $statement = '';
    $statements = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments and empty lines
        if (empty($line) || substr($line, 0, 2) == '--') {
            continue;
        }
        
        $statement .= $line . ' ';
        
        // Check if statement is complete (ends with semicolon)
        if (substr(trim($line), -1) == ';') {
            $statements[] = trim($statement);
            $statement = '';
        }
    }
    
    // Execute each statement
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $idx => $stmt) {
        if (empty(trim($stmt))) continue;
        
        try {
            db_query($stmt);
            $success_count++;
            echo "[✓] Statement " . ($idx + 1) . " executed successfully\n";
        } catch (Exception $e) {
            $error_count++;
            echo "[✗] Statement " . ($idx + 1) . " failed: " . $e->getMessage() . "\n";
            echo "    SQL: " . substr($stmt, 0, 100) . "...\n";
        }
    }
    
    echo "\n========================================\n";
    echo "Migration Completed!\n";
    echo "Successful: $success_count statements\n";
    echo "Failed: $error_count statements\n";
    echo "========================================\n\n";
    
    // Run verification queries
    echo "Verification Results:\n";
    echo "--------------------\n";
    
    $inventory_count = db_query_one("SELECT COUNT(*) as count FROM stock_type_inventory");
    echo "Stock Type Inventory Records: " . $inventory_count['count'] . "\n";
    
    $serial_distribution = db_query("SELECT stock_type, COUNT(*) as count FROM product_serials GROUP BY stock_type");
    echo "\nSerial Stock Type Distribution:\n";
    foreach ($serial_distribution as $row) {
        echo "  - " . ucfirst($row['stock_type']) . ": " . $row['count'] . "\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "\nERROR: Migration failed\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
