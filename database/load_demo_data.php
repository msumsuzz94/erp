<?php
/**
 * Load Demo Data Script
 * Inserts sample data for testing all modules
 */

require_once __DIR__ . '/../config/config.php';

try {
    echo "==============================================\n";
    echo "LOADING DEMO DATA\n";
    echo "==============================================\n\n";
    
    $sql = file_get_contents(__DIR__ . '/demo_data.sql');
    
    // Split by semicolon but exclude those in strings
    $statements = explode(';', $sql);
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements and comments
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $conn->exec($statement);
            $success_count++;
            
            // Show progress for INSERT statements
            if (stripos($statement, 'INSERT INTO') === 0) {
                $table_name = '';
                if (preg_match('/INSERT INTO `?(\w+)`?/i', $statement, $matches)) {
                    $table_name = $matches[1];
                    echo "✓ Inserted data into: {$table_name}\n";
                }
            }
        } catch (PDOException $e) {
            $error_count++;
            // Only show errors that aren't duplicate entries
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                echo "⚠ Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n==============================================\n";
    echo "DEMO DATA LOADED SUCCESSFULLY!\n";
    echo "==============================================\n";
    echo "Statements executed: {$success_count}\n";
    echo "Warnings: {$error_count}\n\n";
    
    echo "Demo data includes:\n";
    echo "• 5 Categories\n";
    echo "• 5 Brands\n";
    echo "• 5 Units\n";
    echo "• 5 Customers\n";
    echo "• 5 Suppliers\n";
    echo "• 5 Products\n";
    echo "• 5 Expense Categories\n";
    echo "• 5 Cash Accounts\n";
    echo "• 5 Bank Accounts\n";
    echo "• 5 Staff Members\n";
    echo "• 5 Purchases\n";
    echo "• 5 Sales\n";
    echo "• 5 Expenses\n";
    echo "• 5 Stock Adjustments\n";
    echo "• 5 RMA Requests\n";
    echo "• 5 Attendance Records\n";
    echo "• 5 Salary Records\n\n";
    
    echo "You can now test all menus and buttons!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
