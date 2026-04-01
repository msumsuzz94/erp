<?php
/**
 * Live Map - Get Live Positions API
 * Returns the latest known position of all active staff members
 * GET: optional ?department_id=X&role_id=Y
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get stale threshold from settings
$stale_minutes = 15;
$setting = db_query("SELECT setting_value FROM livemap_settings WHERE setting_key = 'stale_threshold_minutes'");
if (!empty($setting)) {
    $stale_minutes = intval($setting[0]['setting_value']);
}

// Build staff filter
$where_extra = "";
$params = [];

if (!empty($_GET['department_id'])) {
    $where_extra .= " AND s.department_id = ?";
    $params[] = intval($_GET['department_id']);
}
if (!empty($_GET['role_id'])) {
    $where_extra .= " AND s.role_id = ?";
    $params[] = intval($_GET['role_id']);
}

// Get latest location for each active staff member
$sql = "SELECT 
    s.id AS staff_id,
    s.name AS staff_name,
    s.designation,
    s.phone,
    s.photo,
    sr.role_name,
    sd.name AS department_name,
    sl.latitude,
    sl.longitude,
    sl.accuracy,
    sl.battery_level,
    sl.is_charging,
    sl.speed,
    sl.recorded_at,
    sl.synced_at,
    TIMESTAMPDIFF(MINUTE, sl.recorded_at, NOW()) AS minutes_ago
FROM staff s
LEFT JOIN staff_roles sr ON s.role_id = sr.id
LEFT JOIN staff_departments sd ON s.department_id = sd.id
LEFT JOIN staff_locations sl ON sl.id = (
    SELECT id FROM staff_locations WHERE staff_id = s.id ORDER BY recorded_at DESC LIMIT 1
)
WHERE s.status = 'active' {$where_extra}
ORDER BY s.name ASC";

$results = db_query($sql, $params);

$positions = [];
foreach ($results ?: [] as $row) {
    $minutes_ago = $row['minutes_ago'] !== null ? intval($row['minutes_ago']) : null;
    
    $status = 'no_data';
    if ($row['latitude'] !== null) {
        $status = ($minutes_ago !== null && $minutes_ago <= $stale_minutes) ? 'online' : 'offline';
    }

    $positions[] = [
        'staff_id' => intval($row['staff_id']),
        'name' => $row['staff_name'],
        'designation' => $row['designation'],
        'phone' => $row['phone'],
        'photo' => $row['photo'],
        'role' => $row['role_name'],
        'department' => $row['department_name'],
        'latitude' => $row['latitude'] ? floatval($row['latitude']) : null,
        'longitude' => $row['longitude'] ? floatval($row['longitude']) : null,
        'accuracy' => $row['accuracy'] ? floatval($row['accuracy']) : null,
        'battery_level' => $row['battery_level'] !== null ? intval($row['battery_level']) : null,
        'is_charging' => intval($row['is_charging'] ?? 0),
        'speed' => $row['speed'] ? floatval($row['speed']) : null,
        'recorded_at' => $row['recorded_at'],
        'minutes_ago' => $minutes_ago,
        'status' => $status
    ];
}

// Summary counts
$online = count(array_filter($positions, fn($p) => $p['status'] === 'online'));
$offline = count(array_filter($positions, fn($p) => $p['status'] === 'offline'));
$no_data = count(array_filter($positions, fn($p) => $p['status'] === 'no_data'));

echo json_encode([
    'success' => true,
    'positions' => $positions,
    'summary' => [
        'total' => count($positions),
        'online' => $online,
        'offline' => $offline,
        'no_data' => $no_data
    ],
    'stale_threshold_minutes' => $stale_minutes
]);
