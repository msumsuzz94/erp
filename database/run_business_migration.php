<?php
/**
 * Run Business Settings Migration
 */

require_once __DIR__ . '/../config/config.php';

try {
    echo "Creating business_settings table...\n\n";

    $sql = file_get_contents(__DIR__ . '/migrations/create_business_settings.sql');

    $statements = explode(';', $sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        try {
            $conn->exec($statement);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "Warning: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "✓ business_settings table created\n";
    echo "✓ Default settings inserted\n\n";
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
