<?php
/**
 * Biometric Attendance Migration Runner
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

echo "Biometric Attendance Migration\n";
echo "==============================\n\n";

try {
    $sql_file = __DIR__ . '/migrations/biometric_attendance.sql';
    $sql = file_get_contents($sql_file);
    
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $success = 0;
    $skipped = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        
        try {
            db_query($statement);
            $success++;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'already exists') !== false || 
                strpos($msg, 'Duplicate') !== false ||
                strpos($msg, 'Duplicate entry') !== false) {
                $skipped++;
            } else {
                echo "WARNING: " . $msg . "\n";
                $skipped++;
            }
        }
    }
    
    echo "OK: $success statements executed, $skipped skipped\n";
    
    // Verify
    $tables = ['staff_biometrics', 'cctv_configurations', 'cctv_tracking_logs', 'attendance_settings'];
    foreach ($tables as $t) {
        $r = db_query("SHOW TABLES LIKE '$t'");
        echo ($r ? "OK" : "FAIL") . ": $t\n";
    }
    
    echo "\nDone!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
