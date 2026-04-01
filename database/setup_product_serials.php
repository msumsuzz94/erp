<?php
/**
 * Run Product Serials Migration and Add Demo Data
 * This script creates the product_serials table and adds 5 demo serial numbers
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Product Serials</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f4f4; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; }
       .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px 0 0; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #45a049; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th, table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        table th { background: #4CAF50; color: white; }
        table tr:nth-child(even) { background: #f9f9f9; }
        .step { background: #e3f2fd; border-left: 4px solid #2196F3; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🛠️ Setup Product Serials & Warranty Tracking</h1>";

$overall_success = true;

// STEP 1: Create product_serials table
echo "<div class='step'><h2>Step 1: Create Product Serials Table</h2>";
try {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `product_serials` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `product_id` int(11) NOT NULL,
      `serial_number` varchar(100) DEFAULT NULL,
      `imei` varchar(100) DEFAULT NULL,
      `purchase_id` int(11) DEFAULT NULL,
      `sale_id` int(11) DEFAULT NULL,
      `sale_item_id` int(11) DEFAULT NULL,
      `warranty_months` int(11) DEFAULT 0,
      `purchase_date` date DEFAULT NULL,
      `sale_date` date DEFAULT NULL,
      `status` enum('in_stock','sold','returned','defective') DEFAULT 'in_stock',
      `notes` text,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `serial_number` (`serial_number`),
      UNIQUE KEY `imei` (`imei`),
      KEY `product_id` (`product_id`),
      KEY `purchase_id` (`purchase_id`),
      KEY `sale_id` (`sale_id`),
      KEY `sale_item_id` (`sale_item_id`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    db_query($create_table_sql);
    echo "<div class='success'>✓ Table 'product_serials' created successfully!</div>";
} catch (Exception $e) {
    $error_msg = $e->getMessage();
    if (strpos($error_msg, 'already exists') !== false) {
        echo "<div class='info'>ℹ️ Table 'product_serials' already exists (skipped)</div>";
    } else {
        echo "<div class='error'>✗ Error creating table: " . htmlspecialchars($error_msg) . "</div>";
        $overall_success = false;
    }
}
echo "</div>";

// STEP 2: Add demo serial numbers
echo "<div class='step'><h2>Step 2: Add Demo Serial Numbers</h2>";

// Check if demo serials already exist
$existing = db_query("SELECT COUNT(*) as count FROM product_serials WHERE serial_number LIKE 'SN-%'");
$existing_count = $existing[0]['count'] ?? 0;

if ($existing_count > 0) {
    echo "<div class='warning'>⚠️ Found $existing_count existing demo serials. Skipping insertion to avoid duplicates.</div>";
    echo "<div class='info'>If you want to reset demo data, delete existing serials first.</div>";
} else {
    $demo_serials = [
        [
            'product_id' => 1,
            'serial_number' => 'SN-SAM-001-2026',
            'imei' => '356938035643809',
            'purchase_id' => 1,
            'sale_id' => 1,
            'warranty_months' => 12,
            'purchase_date' => '2025-12-15',
            'sale_date' => '2026-01-15',
            'status' => 'sold',
            'notes' => 'Samsung - Active warranty (6 months remaining)'
        ],
        [
            'product_id' => 2,
            'serial_number' => 'SN-IPH-001-2026',
            'imei' => '352099001761481',
            'purchase_id' => 1,
            'sale_id' => 2,
            'warranty_months' => 12,
            'purchase_date' => '2025-11-20',
            'sale_date' => '2025-12-27',
            'status' => 'sold',
            'notes' => 'iPhone - Warranty expiring soon (30 days)'
        ],
        [
            'product_id' => 3,
            'serial_number' => 'SN-LAP-001-2024',
            'imei' => '456789012345678',
            'purchase_id' => 1,
            'sale_id' => NULL,
            'warranty_months' => 12,
            'purchase_date' => '2024-01-10',
            'sale_date' => '2024-02-01',
            'status' => 'sold',
            'notes' => 'Laptop - Warranty expired'
        ],
        [
            'product_id' => 4,
            'serial_number' => 'SN-TAB-001-2026',
            'imei' => '789012345678901',
            'purchase_id' => 2,
            'sale_id' => NULL,
            'warranty_months' => 24,
            'purchase_date' => '2025-12-01',
            'sale_date' => NULL,
            'status' => 'in_stock',
            'notes' => 'Tablet - Not sold yet (in stock)'
        ],
        [
            'product_id' => 5,
            'serial_number' => 'SN-DELL-001-2026',
            'imei' => '234567890123456',
            'purchase_id' => 2,
            'sale_id' => 3,
            'warranty_months' => 12,
            'purchase_date' => '2025-11-01',
            'sale_date' => '2026-03-27',
            'status' => 'sold',
            'notes' => 'Dell Laptop - Active warranty'
        ]
    ];
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($demo_serials as $i => $serial) {
        try {
            $sql = "INSERT INTO product_serials (product_id, serial_number, imei, purchase_id, sale_id, warranty_months, purchase_date, sale_date, status, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $serial['product_id'],
                $serial['serial_number'],
                $serial['imei'],
                $serial['purchase_id'],
                $serial['sale_id'],
                $serial['warranty_months'],
                $serial['purchase_date'],
                $serial['sale_date'],
                $serial['status'],
                $serial['notes']
            ];
            
            db_query($sql, $params);
            $success_count++;
            echo "<div class='success'>✓ Serial " . ($i + 1) . " added: " . htmlspecialchars($serial['serial_number']) . " - " . htmlspecialchars($serial['notes']) . "</div>";
        } catch (Exception $e) {
            $error_count++;
            echo "<div class='error'>✗ Serial " . ($i + 1) . " failed: " . htmlspecialchars($e->getMessage()) . "</div>";
            $overall_success = false;
        }
    }
    
    echo "<div class='info'><strong>Summary:</strong> $success_count added, $error_count failed</div>";
}
echo "</div>";

// STEP 3: Verify the data
echo "<div class='step'><h2>Step 3: Verify Serial Numbers</h2>";
try {
    $all_serials = db_query("SELECT ps.*, p.name as product_name 
                             FROM product_serials ps 
                             LEFT JOIN products p ON ps.product_id = p.id 
                             ORDER BY ps.id DESC 
                             LIMIT 10");
    
    $total_count = db_query("SELECT COUNT(*) as count FROM product_serials");
    $total = $total_count[0]['count'] ?? 0;
    
    echo "<div class='success'>✓ Total Serial Numbers in Database: <strong>$total</strong></div>";
    
    if (!empty($all_serials)) {
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Serial Number</th>
                <th>IMEI</th>
                <th>Status</th>
                <th>Sale Date</th>
                <th>Warranty (Months)</th>
                <th>Notes</th>
            </tr>";
        
        foreach ($all_serials as $serial) {
            $status_color = [
                'available' => '#17a2b8',
                'sold' => '#28a745',
                'returned' => '#ffc107',
                'damaged' => '#dc3545'
            ];
            $color = $status_color[$serial['status']] ?? '#6c757d';
            
            echo "<tr>
                <td>{$serial['id']}</td>
                <td>" . htmlspecialchars($serial['product_name'] ?? 'Unknown') . "</td>
                <td><strong>" . htmlspecialchars($serial['serial_number']) . "</strong></td>
                <td>" . htmlspecialchars($serial['imei'] ?? '-') . "</td>
                <td><span style='background: $color; color: white; padding: 3px 8px; border-radius: 3px;'>" . ucfirst($serial['status']) . "</span></td>
                <td>" . htmlspecialchars($serial['sale_date'] ?? 'Not Sold') . "</td>
                <td>{$serial['warranty_months']}</td>
                <td><small>" . htmlspecialchars($serial['notes'] ?? '') . "</small></td>
            </tr>";
        }
        
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error verifying data: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div>";

// Final summary
if ($overall_success) {
    echo "<div class='success'>
        <h2>✅ Setup Completed Successfully!</h2>
        <p>The product_serials table has been created and demo data has been added.</p>
        <p>You can now use the warranty serial tracking feature.</p>
    </div>";
} else {
    echo "<div class='warning'>
        <h2>⚠️ Setup Completed with Warnings</h2>
        <p>Some errors occurred during setup. Please review the messages above.</p>
    </div>";  
}

echo "
    <h2>🔗 Quick Links</h2>
    <a href='../modules/warranty/serial-list.php' class='btn'>📋 View Serial List</a>
    <a href='../index.php' class='btn' style='background: #2196F3;'>🏠 Dashboard</a>
    <a href='$_SERVER[PHP_SELF]' class='btn' style='background: #ff9800;'>🔄 Run Again</a>
    </div>
</body>
</html>";
?>
