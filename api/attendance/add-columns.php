<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'c:/xampp/htdocs/erp/config/config.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=business_db;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM attendance LIKE 'time_in_2'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN time_in_2 TIME NULL AFTER time_out, ADD COLUMN time_out_2 TIME NULL AFTER time_in_2");
        echo "Columns time_in_2 and time_out_2 added successfully.\n";
    } else {
        echo "Columns already exist.\n";
    }
} catch(PDOException $e) {
    echo "PDO Error: " . $e->getMessage();
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
