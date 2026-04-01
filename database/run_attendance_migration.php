<?php
/**
 * Run Attendance System Migration
 * Execute both database schema and menu items migration
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

echo "========================================\n";
echo "Automatic Attendance System Migration\n";
echo "========================================\n\n";

try {
    // 1. Run main attendance automation migration
    echo "Step 1: Creating database tables...\n";
    $sql_file = __DIR__.'/migrations/attendance_automation.sql';
    $sql = file_get_contents($sql_file);
    
    // Split by delimiter and execute
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                db_query($statement);
            } catch (Exception $e) {
                // Skip if table already exists
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate column') === false) {
                    throw $e;
                }
            }
        }
    }
    
    echo "✓ Database tables created successfully\n\n";
    
    // 2. Run menu items migration
    echo "Step 2: Adding menu items...\n";
    $menu_sql_file = __DIR__ . '/migrations/add_attendance_menu_items.sql';
    $menu_sql = file_get_contents($menu_sql_file);
    
    $menu_statements = array_filter(array_map('trim', explode(';', $menu_sql)));
    
    foreach ($menu_statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                db_query($statement);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
    }
    
    echo "✓ Menu items added successfully\n\n";
    
    // 3. Verify installation
    echo "Step 3: Verifying installation...\n";
    
    $tables_to_check = [
        'attendance_devices',
        'attendance_logs',
        'attendance_summary',
        'device_heartbeat_log'
    ];
    
    foreach ($tables_to_check as $table) {
        $result = db_query("SHOW TABLES LIKE '$table'");
        if (empty($result)) {
            throw new Exception("Table $table was not created");
        }
        echo "✓ Table $table exists\n";
    }
    
    // Check if staff table has new columns
    $columns = db_query("SHOW COLUMNS FROM staff LIKE 'fingerprint_template'");
    if (empty($columns)) {
        throw new Exception("Staff table columns were not updated");
    }
    echo "✓ Staff table updated with enrollment fields\n";
    
    // Check menu items
    $menu_check = db_query("SELECT COUNT(*) as count FROM menu_items WHERE slug IN ('hr.attendance_devices', 'hr.attendance_logs')");
    if ($menu_check[0]['count'] < 2) {
        throw new Exception("Menu items were not added");
    }
    echo "✓ Menu items added: Attendance Devices, Attendance Logs\n";
    
    echo "\n========================================\n";
    echo "Migration completed successfully!\n";
    echo "========================================\n\n";
    
    echo "Next steps:\n";
    echo "1. Navigate to HR > Attendance Devices\n";
    echo "2. Add your first fingerprint/card device\n";
    echo "3. Enroll staff members in the device\n";
    echo "4. Start receiving automatic attendance punches!\n\n";
    
    echo "API Endpoint for devices:\n";
    echo BASE_URL . "/api/attendance/punch.php\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Please check the error and try again.\n";
    exit(1);
}
