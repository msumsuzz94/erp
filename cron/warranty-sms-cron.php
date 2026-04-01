<?php
/**
 * Cron Script: Warranty Expiry SMS Alerts
 * Should be run once daily
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';
require_once __DIR__ . '/../includes/sms_functions.php';

// Prevent unauthorized web access if not running via CLI
if (php_sapi_name() !== 'cli' && !isset($_GET['run_secret'])) {
    die("Access denied. CLI only or secret required.");
}

$settings = db_select_one('sms_settings', ['id' => 1]);
if (!$settings || !$settings['warranty_sms_enabled']) {
    echo "Warranty SMS is disabled. Exiting.\n";
    exit;
}

echo "Checking for expiring warranties...\n";

// Find serials where expiry is in 7 days and alert hasn't been sent
// Calculation: sale_date + warranty_months = CURDATE() + 7 days
$sql = "SELECT ps.*, p.name as product_name, c.name as customer_name, c.phone as customer_phone,
               DATE_ADD(ps.sale_date, INTERVAL ps.warranty_months MONTH) as expiry_date
        FROM product_serials ps
        JOIN products p ON ps.product_id = p.id
        JOIN sales s ON ps.sale_id = s.id
        JOIN customers c ON s.customer_id = c.id
        WHERE ps.status = 'sold' 
        AND ps.warranty_months > 0
        AND ps.warranty_alert_sent = 0
        AND DATE_ADD(ps.sale_date, INTERVAL ps.warranty_months MONTH) = DATE_ADD(CURDATE(), INTERVAL 7 DAY)";

$serials = db_query($sql);

if (empty($serials)) {
    echo "No warranties expiring in 7 days.\n";
    exit;
}

$count = 0;
foreach ($serials as $item) {
    echo "Sending alert to {$item['customer_name']} for {$item['product_name']} ({$item['serial_number']})...\n";
    
    send_warranty_notification($item['id']);
    
    // The notification logic internally logs status to message_log.
    // We mark the alert sent to avoid processing this serial again next run.
    db_update('product_serials', ['warranty_alert_sent' => 1], ['id' => $item['id']]);
    $count++;
}

echo "Finished. $count alerts sent.\n";
?>
