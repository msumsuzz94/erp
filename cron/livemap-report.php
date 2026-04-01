<?php
/**
 * Live Map - Automated Email Report (Cron Job)
 * 
 * Run via cPanel Cron: 
 * php /home/username/public_html/cron/livemap-report.php
 * 
 * Schedule: Daily at midnight (0 0 * * *)
 */

// CLI check
if (PHP_SAPI !== 'cli' && !defined('ALLOW_CRON')) {
    die('This script must be run from CLI or cron');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';

// Check if auto report is enabled
$settings = [];
$rows = db_query("SELECT setting_key, setting_value FROM livemap_settings");
foreach ($rows ?: [] as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

if (($settings['auto_report_enabled'] ?? '0') !== '1') {
    echo "Auto report is disabled.\n";
    exit;
}

$frequency = $settings['auto_report_frequency'] ?? 'daily';
$emails = trim($settings['auto_report_emails'] ?? '');

if (empty($emails)) {
    // Fallback: get Super Admin and Admin emails
    $admins = db_query("SELECT email FROM users WHERE role_id IN (1,2) AND status = 'active'");
    $emails = implode(',', array_column($admins ?: [], 'email'));
}

if (empty($emails)) {
    echo "No email recipients configured.\n";
    exit;
}

// Determine date range based on frequency
$today = date('Y-m-d');
switch ($frequency) {
    case 'daily':
        $from = date('Y-m-d', strtotime('-1 day'));
        $to = $from;
        $period_label = "Daily Report: " . date('d M Y', strtotime($from));
        break;
    case 'weekly':
        $from = date('Y-m-d', strtotime('-7 days'));
        $to = date('Y-m-d', strtotime('-1 day'));
        $period_label = "Weekly Report: " . date('d M', strtotime($from)) . " - " . date('d M Y', strtotime($to));
        break;
    case 'monthly':
        $from = date('Y-m-01', strtotime('-1 month'));
        $to = date('Y-m-t', strtotime('-1 month'));
        $period_label = "Monthly Report: " . date('F Y', strtotime($from));
        break;
    default:
        $from = date('Y-m-d', strtotime('-1 day'));
        $to = $from;
        $period_label = "Report: " . date('d M Y', strtotime($from));
}

// Get staff visit data
$staff_data = db_query(
    "SELECT 
        s.name, s.designation,
        COUNT(gv.id) AS total_visits,
        SUM(COALESCE(gv.duration_minutes, 0)) AS total_minutes,
        COUNT(DISTINCT gv.geofence_id) AS unique_locations
     FROM staff s
     LEFT JOIN geofence_visits gv ON s.id = gv.staff_id 
        AND gv.entered_at BETWEEN ? AND ?
     WHERE s.status = 'active'
     GROUP BY s.id, s.name, s.designation
     ORDER BY total_visits DESC",
    [$from . ' 00:00:00', $to . ' 23:59:59']
);

// Calculate travel distances
$travel_data = [];
$locations = db_query(
    "SELECT staff_id, latitude, longitude FROM staff_locations 
     WHERE recorded_at BETWEEN ? AND ? ORDER BY staff_id, recorded_at",
    [$from . ' 00:00:00', $to . ' 23:59:59']
);

if ($locations) {
    $current_staff = null;
    $prev_lat = $prev_lng = 0;
    $dist = 0;
    
    foreach ($locations as $loc) {
        if ($current_staff !== $loc['staff_id']) {
            if ($current_staff !== null) $travel_data[$current_staff] = round($dist / 1000, 2);
            $current_staff = $loc['staff_id'];
            $prev_lat = floatval($loc['latitude']);
            $prev_lng = floatval($loc['longitude']);
            $dist = 0;
            continue;
        }
        $lat = floatval($loc['latitude']);
        $lng = floatval($loc['longitude']);
        $R = 6371000;
        $dLat = deg2rad($lat - $prev_lat);
        $dLng = deg2rad($lng - $prev_lng);
        $a = sin($dLat/2)**2 + cos(deg2rad($prev_lat)) * cos(deg2rad($lat)) * sin($dLng/2)**2;
        $dist += $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
        $prev_lat = $lat;
        $prev_lng = $lng;
    }
    if ($current_staff !== null) $travel_data[$current_staff] = round($dist / 1000, 2);
}

// Build HTML email
$business_name = defined('BUSINESS_NAME') ? BUSINESS_NAME : 'ERP System';

$html = "<!DOCTYPE html><html><head><style>
body { font-family: Arial, sans-serif; background: #f4f6f9; padding: 20px; }
.container { max-width: 700px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.header { background: linear-gradient(135deg, #4e73df, #36b9cc); color: #fff; padding: 25px; text-align: center; }
.header h1 { margin: 0; font-size: 1.5rem; }
.header p { margin: 5px 0 0; opacity: 0.9; }
.body { padding: 25px; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th { background: #f8f9fc; color: #4e73df; text-align: left; padding: 10px; border-bottom: 2px solid #e3e6f0; font-size: 0.85rem; }
td { padding: 10px; border-bottom: 1px solid #e3e6f0; font-size: 0.85rem; }
tr:hover { background: #f8f9fc; }
.badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
.badge-primary { background: #4e73df20; color: #4e73df; }
.footer { text-align: center; padding: 15px; background: #f8f9fc; color: #858796; font-size: 0.75rem; }
</style></head><body>
<div class='container'>
<div class='header'>
    <h1>📍 Live Map Report</h1>
    <p>{$period_label}</p>
    <p>{$business_name}</p>
</div>
<div class='body'>
<h3>Staff Activity Summary</h3>
<table>
<tr><th>Staff</th><th>Designation</th><th>Visits</th><th>Locations</th><th>Time Spent</th><th>Travel (km)</th></tr>";

$total_visits = 0;
foreach ($staff_data ?: [] as $s) {
    $hours = floor($s['total_minutes'] / 60);
    $mins = $s['total_minutes'] % 60;
    $time = $s['total_visits'] > 0 ? "{$hours}h {$mins}m" : '-';
    $km = $travel_data[$s['staff_id'] ?? 0] ?? 0;
    $total_visits += $s['total_visits'];
    
    $html .= "<tr>
        <td><strong>{$s['name']}</strong></td>
        <td>{$s['designation']}</td>
        <td><span class='badge badge-primary'>{$s['total_visits']}</span></td>
        <td>{$s['unique_locations']}</td>
        <td>{$time}</td>
        <td>{$km} km</td>
    </tr>";
}

$html .= "</table>
<p style='color:#858796;font-size:0.8rem;'>Total Visits: <strong>{$total_visits}</strong> | Staff with data: <strong>" . count(array_filter($staff_data ?: [], fn($s) => $s['total_visits'] > 0)) . "</strong></p>
</div>
<div class='footer'>
    This is an automatically generated report by {$business_name} ERP System.<br>
    Generated at: " . date('d M Y h:i A') . "
</div>
</div></body></html>";

// Send email
$recipient_list = array_map('trim', explode(',', $emails));

foreach ($recipient_list as $to_email) {
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) continue;
    
    $subject = "📍 Live Map {$period_label} - {$business_name}";
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@erp.com') . "\r\n";
    
    $sent = mail($to_email, $subject, $html, $headers);
    echo ($sent ? "✅" : "❌") . " Report sent to: {$to_email}\n";
}

echo "Report generation completed.\n";
