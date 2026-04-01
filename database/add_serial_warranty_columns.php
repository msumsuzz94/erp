<?php
// Add serial and warranty columns to products table
require_once __DIR__ . '/../config/config.php';

try {
    echo "Adding serial and warranty tracking columns...\n\n";

    // Add has_serial column
    try {
        $conn->exec("ALTER TABLE products ADD COLUMN has_serial ENUM('Available', 'Not Available') DEFAULT 'Not Available' AFTER status");
        echo "✓ Added has_serial column\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "  has_serial column already exists\n";
        } else {
            throw $e;
        }
    }

    // Add warranty_duration column
    try {
        $conn->exec("ALTER TABLE products ADD COLUMN warranty_duration INT DEFAULT 0 AFTER has_serial");
        echo "✓ Added warranty_duration column\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "  warranty_duration column already exists\n";
        } else {
            throw $e;
        }
    }

    // Add warranty_period column
    try {
        $conn->exec("ALTER TABLE products ADD COLUMN warranty_period ENUM('Days', 'Month', 'Year') DEFAULT 'Month' AFTER warranty_duration");
        echo "✓ Added warranty_period column\n";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "  warranty_period column already exists\n";
        } else {
            throw $e;
        }
    }

    echo "\n✓ Successfully added all columns!\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
