<?php
// Add customer_group column to database
require_once __DIR__ . '/../config/config.php';

try {
    echo "Adding customer_group column to customers table...\n";

    $sql = "ALTER TABLE customers ADD COLUMN customer_group VARCHAR(20) DEFAULT 'Buyer' AFTER address";

    $conn->exec($sql);

    echo "✓ Successfully added customer_group column!\n";

} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Column already exists, skipping...\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
?>
