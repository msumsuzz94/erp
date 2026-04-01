<?php
/**
 * Add Demo Serial Numbers
 * Run this script to add 5 demo product serial numbers for testing warranty tracking
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Add Demo Serials</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .btn { display: inline-block; padding: 10px 20px; margin: 10px 5px 0 0; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #45a049; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th, table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        table th { background: #4CAF50; color: white; }
        table tr:nth-child(even) { background: #f9f9f9; }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🔢 Add Demo Serial Numbers</h1>";

try {
    echo "<h2>📝 Adding Demo Serial Numbers...</h2>";
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    // Define demo serials directly in PHP
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
            'notes' => 'Original purchase with full warranty (Active - 6 months remaining)'
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
            'notes' => 'Warranty expiring soon (30 days remaining)'
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
            'notes' => 'Warranty has expired'
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
            'status' => 'available',
            'notes' => 'Brand new tablet in stock (not sold yet)'
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
            'notes' => 'Recently sold with warranty (Active)'
        ]
    ];
    
    // Insert each serial
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
            echo "<div class='success'>✓ Serial " . ($i + 1) . " added: " . htmlspecialchars($serial['serial_number']) . "</div>";
        } catch (Exception $e) {
            $error_count++;
            $error_msg = $e->getMessage();
            $errors[] = "Serial " . ($i + 1) . ": " . $error_msg;
            echo "<div class='error'>✗ Serial " . ($i + 1) . " failed: " . htmlspecialchars($error_msg) . "</div>";
        }
    }
    
    echo "<h2>📊 Summary</h2>";
    echo "<table>
        <tr>
            <th>Metric</th>
            <th>Count</th>
        </tr>
        <tr>
            <td>✓ Successful Statements</td>
            <td style='color: green; font-weight: bold;'>$success_count</td>
        </tr>
        <tr>
            <td>✗ Failed Statements</td>
            <td style='color: red; font-weight: bold;'>$error_count</td>
        </tr>
    </table>";
    
    if ($error_count > 0) {
        echo "<div class='error'>
            <strong>⚠️ Some errors occurred:</strong><br>";
        foreach ($errors as $i => $err) {
            echo ($i + 1) . ". " . htmlspecialchars($err) . "<br>";
        }
        echo "</div>";
    }
    
    // Check if serials were added
    $serials = db_query("SELECT COUNT(*) as count FROM product_serials");
    $serial_count = $serials[0]['count'] ?? 0;
    
    echo "<div class='success'>
        <strong>✓ Total Serial Numbers in Database:</strong> $serial_count
    </div>";
    
    // Display the added serials
    $added_serials = db_query("SELECT ps.*, p.name as product_name 
                               FROM product_serials ps 
                               LEFT JOIN products p ON ps.product_id = p.id 
                               ORDER BY ps.id DESC 
                               LIMIT 5");
    
    if (!empty($added_serials)) {
        echo "<h2>🎫 Recently Added Serials</h2>";
        echo "<table>
            <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Serial Number</th>
                <th>IMEI</th>
                <th>Status</th>
                <th>Purchase Date</th>
                <th>Sale Date</th>
                <th>Warranty (Months)</th>
            </tr>";
        
        foreach ($added_serials as $serial) {
            echo "<tr>
                <td>{$serial['id']}</td>
                <td>" . htmlspecialchars($serial['product_name'] ?? 'N/A') . "</td>
                <td>" . htmlspecialchars($serial['serial_number']) . "</td>
                <td>" . htmlspecialchars($serial['imei'] ?? '-') . "</td>
                <td>" . htmlspecialchars($serial['status']) . "</td>
                <td>" . htmlspecialchars($serial['purchase_date'] ?? '-') . "</td>
                <td>" . htmlspecialchars($serial['sale_date'] ?? 'Not Sold') . "</td>
                <td>{$serial['warranty_months']}</td>
            </tr>";
        }
        
        echo "</table>";
    }
    
    echo "<div class='success'>
        <strong>✅ Demo serials added successfully!</strong><br>
        You can now view them in the warranty serial list.
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>
        <strong>❌ Fatal Error:</strong><br>
        " . htmlspecialchars($e->getMessage()) . "
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
