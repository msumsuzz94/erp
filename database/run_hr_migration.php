<?php
/**
 * Simple HR Roles Migration
 */

require_once __DIR__ . '/../config/config.php';

try {
    echo "Running HR Roles Migration...\n\n";

    // Create staff_roles table
    echo "Creating staff_roles table...\n";
    $conn->exec("CREATE TABLE IF NOT EXISTS `staff_roles` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `role_name` varchar(100) NOT NULL,
      `description` text,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `role_name` (`role_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ staff_roles table created\n\n";

    // Insert default roles
    echo "Inserting default roles...\n";
    $conn->exec("INSERT IGNORE INTO `staff_roles` (`role_name`, `description`) VALUES
    ('Manager', 'Department or team manager'),
    ('Supervisor', 'Team supervisor'),
    ('Sales Representative', 'Sales and customer service'),
    ('Accountant', 'Finance and accounting'),
    ('Cashier', 'POS and cash handling'),
    ('Warehouse Staff', 'Inventory and warehouse management'),
    ('Delivery Person', 'Product delivery'),
    ('IT Support', 'Technical support'),
    ('General Staff', 'General purpose staff member')");
    echo "✓ Default roles inserted\n\n";

    // Check if role_id column exists in staff table
    $check = $conn->query("SHOW COLUMNS FROM staff LIKE 'role_id'")->fetch();
    if (!$check) {
        echo "Adding role_id column to staff table...\n";
        $conn->exec("ALTER TABLE staff ADD COLUMN role_id int(11) DEFAULT NULL AFTER designation");
        echo "✓ role_id column added\n\n";
    } else {
        echo "ℹ role_id column already exists\n\n";
    }

    // Verify
    $count = $conn->query("SELECT COUNT(*) FROM staff_roles")->fetchColumn();
    echo "===========================================\n";
    echo "✓ Migration completed successfully!\n";
    echo "Total roles in database: $count\n";
    echo "===========================================\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
