<?php
/**
 * Live Map - Route History API
 * Returns GPS trail for a staff member on a specific date
 * GET: ?staff_id=X&date=YYYY-MM-DD
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$staff_id = intval($_GET['staff_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');

if ($staff_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'staff_id required']);
    exit;
}

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Get staff info
$staff = db_select_one('staff', ['id' => $staff_id]);
if (!$staff) {
    echo json_encode(['success' => false, 'message' => 'Staff not found']);
    exit;
}

// Get all GPS points for this staff on this date, ordered by time
$points = db_query(
    "SELECT latitude, longitude, accuracy, battery_level, is_charging, speed, recorded_at 
     FROM staff_locations 
     WHERE staff_id = ? AND DATE(recorded_at) = ? 
     ORDER BY recorded_at ASC",
    [$staff_id, $date]
);

$points = $points ?: [];

// Calculate total distance
$total_distance_km = 0;
$trail = [];
$timeline = [];

for ($i = 0; $i < count($points); $i++) {
    $p = $points[$i];
    $lat = floatval($p['latitude']);
    $lng = floatval($p['longitude']);
    
    $trail[] = [$lat, $lng];

    // Calculate distance from previous point
    if ($i > 0) {
        $prevLat = floatval($points[$i-1]['latitude']);
        $prevLng = floatval($points[$i-1]['longitude']);
        $dist = haversineDistance($prevLat, $prevLng, $lat, $lng);
        $total_distance_km += $dist / 1000;
    }

    // Build timeline entries (every ~30 min or significant movement)
    $time = strtotime($p['recorded_at']);
    $hour = date('H:i', $time);
    
    // Add to timeline at key intervals
    if ($i === 0 || $i === count($points) - 1) {
        $timeline[] = [
            'time' => date('h:i A', $time),
            'lat' => $lat,
            'lng' => $lng,
            'battery' => $p['battery_level'],
            'speed' => $p['speed'],
            'label' => $i === 0 ? 'Start' : 'Last Known'
        ];
    } else {
        // Add every 30 minutes
        $prev_time = strtotime($points[$i-1]['recorded_at']);
        if (($time - $prev_time) >= 1800 || $i === count($points) - 1) {
            $timeline[] = [
                'time' => date('h:i A', $time),
                'lat' => $lat,
                'lng' => $lng,
                'battery' => $p['battery_level'],
                'speed' => $p['speed'],
                'label' => ''
            ];
        }
    }
}

// Get geofence visits for this day
$visits = db_query(
    "SELECT gv.*, g.name AS geofence_name, g.address AS geofence_address
     FROM geofence_visits gv 
     JOIN geofences g ON gv.geofence_id = g.id
     WHERE gv.staff_id = ? AND DATE(gv.entered_at) = ?
     ORDER BY gv.entered_at ASC",
    [$staff_id, $date]
);

echo json_encode([
    'success' => true,
    'staff' => [
        'id' => intval($staff['id']),
        'name' => $staff['name'],
        'designation' => $staff['designation'] ?? '',
        'photo' => $staff['photo']
    ],
    'date' => $date,
    'trail' => $trail,
    'points' => array_map(function($p) {
        return [
            'lat' => floatval($p['latitude']),
            'lng' => floatval($p['longitude']),
            'battery' => $p['battery_level'],
            'speed' => $p['speed'],
            'time' => $p['recorded_at']
        ];
    }, $points),
    'timeline' => $timeline,
    'total_distance_km' => round($total_distance_km, 2),
    'total_points' => count($points),
    'visits' => $visits ?: [],
    'first_record' => !empty($points) ? $points[0]['recorded_at'] : null,
    'last_record' => !empty($points) ? end($points)['recorded_at'] : null
]);

function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}
