<?php
/**
 * Migration Script: Update Invoice Settings
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoice Settings Migration</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        pre { background: #252526; padding: 10px; border-left: 3px solid #007acc; margin: 10px 0; }
    </style>
</head>
<body>
<h1>🚀 Running Invoice Settings Migration</h1>

<?php
global $conn;

$queries = [
    "ALTER TABLE `invoice_settings` ADD COLUMN IF NOT EXISTS `company_slogan` varchar(255) DEFAULT NULL AFTER `company_name`",
    "ALTER TABLE `invoice_settings` ADD COLUMN IF NOT EXISTS `company_website` varchar(100) DEFAULT NULL AFTER `company_email` IF NOT EXISTS",
    "ALTER TABLE `invoice_settings` ADD COLUMN IF NOT EXISTS `author_signature_label` varchar(100) DEFAULT 'Author signature' AFTER `invoice_signature` IF NOT EXISTS",
    "ALTER TABLE `invoice_settings` ADD COLUMN IF NOT EXISTS `good_received_text` text DEFAULT NULL AFTER `author_signature_label` IF NOT EXISTS",
    "ALTER TABLE `invoice_settings` ADD COLUMN IF NOT EXISTS `show_amount_in_words` tinyint(1) DEFAULT 1 AFTER `show_terms` IF NOT EXISTS",
    "ALTER TABLE `invoice_settings` ADD COLUMN IF NOT EXISTS `amount_in_words_prefix` varchar(20) DEFAULT 'BDT' AFTER `show_amount_in_words` IF NOT EXISTS",
    "ALTER TABLE `invoice_settings` ADD COLUMN IF NOT EXISTS `show_slogan` tinyint(1) DEFAULT 1 AFTER `show_logo` IF NOT EXISTS"
];

// Re-defining queries without 'IF NOT EXISTS' for columns because MySQL 5.7/8.0's ALTER TABLE doesn't support it for columns directly in all versions, though I can use a procedure or just try/catch.
// Actually, I'll check existence first.

function columnExists($table, $column) {
    global $conn;
    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return $stmt->rowCount() > 0;
}

$columns_to_add = [
    'company_slogan' => "ALTER TABLE `invoice_settings` ADD `company_slogan` varchar(255) DEFAULT NULL AFTER `company_name` text",
    'company_website' => "ALTER TABLE `invoice_settings` ADD `company_website` varchar(100) DEFAULT NULL AFTER `company_email` text",
    'author_signature_label' => "ALTER TABLE `invoice_settings` ADD `author_signature_label` varchar(100) DEFAULT 'Author signature'",
    'good_received_text' => "ALTER TABLE `invoice_settings` ADD `good_received_text` text DEFAULT NULL",
    'show_amount_in_words' => "ALTER TABLE `invoice_settings` ADD `show_amount_in_words` tinyint(1) DEFAULT 1",
    'amount_in_words_prefix' => "ALTER TABLE `invoice_settings` ADD `amount_in_words_prefix` varchar(20) DEFAULT 'BDT'",
    'show_slogan' => "ALTER TABLE `invoice_settings` ADD `show_slogan` tinyint(1) DEFAULT 1"
];

// Adding missing core columns that might be renamed in the other file
$core_renames = [
    'company_name' => 'business_name',
    'company_address' => 'business_address',
    'company_phone' => 'business_phone',
    'company_email' => 'business_email',
    'tax_number' => 'business_tax_no',
    'company_logo' => 'business_logo',
    'show_logo' => 'show_logo_on_invoice',
    'invoice_signature' => 'invoice_signature', // same
    'show_signature_on_invoice' => 'show_signature_on_invoice' // already exists in my plan
];

foreach ($columns_to_add as $col => $sql) {
    if (!columnExists('invoice_settings', $col)) {
        try {
            $conn->exec($sql);
            echo "<div class='success'>✓ Added column: $col</div>";
        } catch (Exception $e) {
            echo "<div class='error'>✗ Failed to add $col: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div>- Column $col already exists</div>";
    }
}

// Final check/insert of default record
try {
    $existing = db_select_one('invoice_settings', ['id' => 1]);
    if (!$existing) {
        db_insert('invoice_settings', [
            'id' => 1,
            'company_name' => 'Your Business Name',
            'company_address' => 'Your Business Address',
            'good_received_text' => 'Good received by customer in good condition.'
        ]);
        echo "<div class='success'>✓ Created default invoice settings</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}

?>
<h2>Next Steps:</h2>
<p>The database is ready. Now updating the settings page and invoice template...</p>
</body>
</html>
