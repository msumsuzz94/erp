<?php
/**
 * Direct Serial Insertion - Shows All Errors
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Direct Serial Insertion</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        .warning { color: #dcdcaa; }
        pre { background: #252526; padding: 10px; border-left: 3px solid #007acc; margin: 10px 0; }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; }
        a { color: #4ec9b0; text-decoration: none; padding: 10px 15px; background: #0e639c; display: inline-block; margin: 5px; border-radius: 3px; }
        a:hover { background: #1177bb; }
    </style>
</head>
<body>
<h1>🔧 Direct Serial Insertion with Error Reporting</h1>

<?php

echo "<h2>Step 1: Clear Old Demo Data</h2>";
try {
    $deleted = db_query("DELETE FROM product_serials WHERE serial_number LIKE 'SN-%'");
    echo "<div class='success'>✓ Cleared old demo serials</div>";
} catch (Exception $e) {
    echo "<div class='warning'>⚠ " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<h2>Step 2: Insert Serial Numbers (with detailed errors)</h2>";

$serials_to_add = [
    ['SN-SAM-001-2026', '356938035643809', 1, 1, 1, 12, '2025-12-15', '2026-01-15', 'sold'],
    ['SN-IPH-001-2026', '352099001761481', 2, 1, 2, 12, '2025-11-20', '2025-12-27', 'sold'],
    ['SN-LAP-001-2024', '456789012345678', 3, 1, NULL, 12, '2024-01-10', '2024-02-01', 'sold'],
    ['SN-TAB-001-2026', '789012345678901', 4, 2, NULL, 24, '2025-12-01', NULL, 'in_stock'],
    ['SN-DELL-001-2026', '234567890123456', 5, 2, 3, 12, '2025-11-01', '2026-03-27', 'sold']
];

$success = 0;
$failed = 0;

foreach ($serials_to_add as $i => $data) {
    list($sn, $imei, $prod_id, $purch_id, $sale_id, $warranty, $purch_date, $sale_date, $status) = $data;
    
    echo "<div class='info'><strong>Serial " . ($i+1) . ":</strong> $sn</div>";
    
    try {
        $sql = "INSERT INTO product_serials 
                (product_id, serial_number, imei, purchase_id, sale_id, warranty_months, purchase_date, sale_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        db_query($sql, [$prod_id, $sn, $imei, $purch_id, $sale_id, $warranty, $purch_date, $sale_date, $status]);
        
        echo "<div class='success'>  ✓ Inserted successfully</div>";
        $success++;
        
    } catch (Exception $e) {
        echo "<div class='error'>  ✗ FAILED: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<pre>SQL: $sql\nParams: " . print_r([$prod_id, $sn, $imei, $purch_id, $sale_id, $warranty, $purch_date, $sale_date, $status], true) . "</pre>";
        $failed++;
    }
}

echo "<h2>Summary</h2>";
echo "<div class='success'>✓ Success: $success</div>";
echo "<div class='error'>✗ Failed: $failed</div>";

echo "<h2>Step 3: Verify Data</h2>";
try {
    $count = db_query("SELECT COUNT(*) as total FROM product_serials");
    $total = $count[0]['total'];
    echo "<div class='info'>Total serials in database: <strong>$total</strong></div>";
    
    if ($total > 0) {
        $all = db_query("SELECT id, serial_number, imei, product_id, status, sale_date FROM product_serials ORDER BY id DESC LIMIT 10");
        echo "<pre>";
        foreach ($all as $s) {
            echo "ID: {$s['id']} | Serial: {$s['serial_number']} | Product: {$s['product_id']} | Status: {$s['status']}\n";
        }
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<h2>Step 4: Test Query from Serial List Page</h2>";
try {
    $test_sql = "SELECT ps.*, p.name as product_name 
                 FROM product_serials ps 
                 INNER JOIN products p ON ps.product_id = p.id 
                 LIMIT 5";
    $test = db_query($test_sql);
    $test_count = count($test);
    
    if ($test_count > 0) {
        echo "<div class='success'>✓ Query returns $test_count records - Serial list SHOULD work!</div>";
    } else {
        echo "<div class='error'>✗ Query returns 0 records</div>";
        echo "<div class='warning'>This means INNER JOIN is failing - products might not exist</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>Query failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>

<h2>🔗 Navigation</h2>
<a href="../modules/warranty/serial-list.php">📋 View Serial List</a>
<a href="check_serials.php">🔍 Run Diagnostic</a>
<a href="<?= $_SERVER['PHP_SELF'] ?>">🔄 Run Again</a>

</body>
</html>
