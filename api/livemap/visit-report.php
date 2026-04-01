<?php
/**
 * Live Map - Visit Report API
 * GET: ?period=today|week|month|custom&from=YYYY-MM-DD&to=YYYY-MM-DD&staff_id=X
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$period = $_GET['period'] ?? 'today';
$staff_id = intval($_GET['staff_id'] ?? 0);

// Determine date range
switch ($period) {
    case 'today':
        $from = date('Y-m-d');
        $to = date('Y-m-d');
        break;
    case 'week':
        $from = date('Y-m-d', strtotime('monday this week'));
        $to = date('Y-m-d');
        break;
    case 'month':
        $from = date('Y-m-01');
        $to = date('Y-m-d');
        break;
    case 'custom':
        $from = $_GET['from'] ?? date('Y-m-d');
        $to = $_GET['to'] ?? date('Y-m-d');
        break;
    default:
        $from = date('Y-m-d');
        $to = date('Y-m-d');
}

$params = [$from . ' 00:00:00', $to . ' 23:59:59'];
$staff_filter = "";

if ($staff_id > 0) {
    $staff_filter = " AND gv.staff_id = ?";
    $params[] = $staff_id;
}

// Get visit details
$visits = db_query(
    "SELECT 
        gv.id, gv.staff_id, gv.entered_at, gv.exited_at, gv.duration_minutes,
        s.name AS staff_name, s.designation, s.photo,
        g.name AS geofence_name, g.address AS geofence_address, g.type AS geofence_type
     FROM geofence_visits gv
     JOIN staff s ON gv.staff_id = s.id
     JOIN geofences g ON gv.geofence_id = g.id
     WHERE gv.entered_at BETWEEN ? AND ? {$staff_filter}
     ORDER BY gv.entered_at DESC",
    $params
);

// Staff-wise summary
$summary_params = [$from . ' 00:00:00', $to . ' 23:59:59'];
$summary_filter = "";
if ($staff_id > 0) {
    $summary_filter = " AND gv.staff_id = ?";
    $summary_params[] = $staff_id;
}

$staff_summary = db_query(
    "SELECT 
        s.id AS staff_id, s.name AS staff_name, s.designation, s.photo,
        COUNT(gv.id) AS total_visits,
        SUM(COALESCE(gv.duration_minutes, 0)) AS total_minutes,
        COUNT(DISTINCT gv.geofence_id) AS unique_locations
     FROM staff s
     LEFT JOIN geofence_visits gv ON s.id = gv.staff_id 
        AND gv.entered_at BETWEEN ? AND ? {$summary_filter}
     WHERE s.status = 'active'
     GROUP BY s.id, s.name, s.designation, s.photo
     HAVING total_visits > 0
     ORDER BY total_visits DESC",
    $summary_params
);

// Calculate travel distances per staff
$travel_data = [];
$travel_params = [$from . ' 00:00:00', $to . ' 23:59:59'];
$travel_filter = "";
if ($staff_id > 0) {
    $travel_filter = " AND staff_id = ?";
    $travel_params[] = $staff_id;
}

$location_data = db_query(
    "SELECT staff_id, latitude, longitude, recorded_at 
     FROM staff_locations 
     WHERE recorded_at BETWEEN ? AND ? {$travel_filter}
     ORDER BY staff_id, recorded_at ASC",
    $travel_params
);

if ($location_data) {
    $current_staff = null;
    $prev_lat = $prev_lng = 0;
    $total_dist = 0;

    foreach ($location_data as $loc) {
        if ($current_staff !== $loc['staff_id']) {
            if ($current_staff !== null) {
                $travel_data[$current_staff] = round($total_dist / 1000, 2);
            }
            $current_staff = $loc['staff_id'];
            $prev_lat = floatval($loc['latitude']);
            $prev_lng = floatval($loc['longitude']);
            $total_dist = 0;
            continue;
        }

        $lat = floatval($loc['latitude']);
        $lng = floatval($loc['longitude']);
        $R = 6371000;
        $dLat = deg2rad($lat - $prev_lat);
        $dLng = deg2rad($lng - $prev_lng);
        $a = sin($dLat/2)**2 + cos(deg2rad($prev_lat)) * cos(deg2rad($lat)) * sin($dLng/2)**2;
        $total_dist += $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
        $prev_lat = $lat;
        $prev_lng = $lng;
    }
    if ($current_staff !== null) {
        $travel_data[$current_staff] = round($total_dist / 1000, 2);
    }
}

// Merge travel data into staff summary
if (is_array($staff_summary)) {
    foreach ($staff_summary as &$s) {
        $s['travel_km'] = $travel_data[$s['staff_id']] ?? 0;
    }
    unset($s);
} else {
    $staff_summary = [];
}

echo json_encode([
    'success' => true,
    'period' => $period,
    'from' => $from,
    'to' => $to,
    'visits' => $visits ?: [],
    'staff_summary' => $staff_summary ?: [],
    'totals' => [
        'total_visits' => count($visits ?: []),
        'total_staff' => count($staff_summary ?: [])
    ]
]);
