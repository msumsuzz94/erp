<?php
/**
 * Halt Analytics & Route Match API
 * Analyzes raw GPS data to detect stops (>10 mins, <50m radius).
 * Matches against Assigned Routes to find hit/miss.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';

header('Content-Type: application/json');

$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$date = $_GET['date'] ?? date('Y-m-d');

if (!$staff_id || !$date) {
    echo json_encode(['success' => false, 'message' => 'Staff ID and Date are required']);
    exit;
}

$conn = getDB();

// 1. Fetch raw locations
$stmt = $conn->prepare("SELECT latitude, longitude, recorded_at, accuracy FROM staff_locations WHERE staff_id = ? AND DATE(recorded_at) = ? ORDER BY recorded_at ASC");
$stmt->execute([$staff_id, $date]);
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($locations)) {
    echo json_encode(['success' => true, 'timeline' => [], 'halts' => [], 'assigned_route' => [], 'has_data' => false]);
    exit;
}

// Distance Helper (Haversine meters)
function getDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

// Centroid Helper
function getCentroid($cluster) {
    if (empty($cluster)) return ['lat'=>0, 'lng'=>0];
    $sumLat = array_sum(array_column($cluster, 'latitude'));
    $sumLng = array_sum(array_column($cluster, 'longitude'));
    return ['lat' => $sumLat / count($cluster), 'lng' => $sumLng / count($cluster)];
}

// Reverse Geocoding Cache
function getAddress($lat, $lng, $conn) {
    $latR = round($lat, 4);
    $lngR = round($lng, 4);
    $stmt = $conn->prepare("SELECT address FROM livemap_geo_cache WHERE lat_round = ? AND lng_round = ?");
    $stmt->execute([$latR, $lngR]);
    if ($row = $stmt->fetch()) return $row['address'];
    
    // API Call
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: ERP_Tracking_App/1.0\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latR}&lon={$lngR}&zoom=18&addressdetails=0";
    $resp = @file_get_contents($url, false, $context);
    
    $address = 'Unknown Area';
    if ($resp) {
        $res = json_decode($resp, true);
        if (isset($res['display_name'])) {
            $parts = explode(',', $res['display_name']);
            $address = implode(',', array_slice($parts, 0, 3)); // Keep it short
        }
    }
    
    $ins = $conn->prepare("INSERT IGNORE INTO livemap_geo_cache (lat_round, lng_round, address) VALUES (?, ?, ?)");
    $ins->execute([$latR, $lngR, $address]);
    
    return $address;
}

// 2. Algorithm Settings
$threshold_meters = 50; 
$threshold_minutes = 10;

$halts = [];
$currentCluster = [];

foreach ($locations as $loc) {
    if (empty($currentCluster)) {
        $currentCluster[] = $loc;
        continue;
    }
    
    $centroid = getCentroid($currentCluster);
    $dist = getDistanceMeters($centroid['lat'], $centroid['lng'], $loc['latitude'], $loc['longitude']);
    
    if ($dist <= $threshold_meters) {
        $currentCluster[] = $loc;
    } else {
        // Process closed cluster
        $start = strtotime($currentCluster[0]['recorded_at']);
        $end = strtotime(end($currentCluster)['recorded_at']);
        $dur = round(($end - $start) / 60);
        
        if ($dur >= $threshold_minutes) {
            $halts[] = [
                'type' => 'halt',
                'lat' => $centroid['lat'],
                'lng' => $centroid['lng'],
                'start_time' => date('H:i', $start),
                'end_time' => date('H:i', $end),
                'duration' => $dur,
                'address' => getAddress($centroid['lat'], $centroid['lng'], $conn)
            ];
        }
        $currentCluster = [$loc]; // Start new
    }
}
// Add final cluster if qualifies
if (!empty($currentCluster)) {
    $start = strtotime($currentCluster[0]['recorded_at']);
    $end = strtotime(end($currentCluster)['recorded_at']);
    $dur = round(($end - $start) / 60);
    if ($dur >= $threshold_minutes) {
        $centroid = getCentroid($currentCluster);
        $halts[] = [
            'type' => 'halt',
            'lat' => $centroid['lat'],
            'lng' => $centroid['lng'],
            'start_time' => date('H:i', $start),
            'end_time' => date('H:i', $end),
            'duration' => $dur,
            'address' => getAddress($centroid['lat'], $centroid['lng'], $conn)
        ];
    }
}

// 3. Assigned Routes Match
$stmtRt = $conn->prepare("SELECT p.* FROM livemap_assigned_route_points p 
    JOIN livemap_assigned_routes r ON p.route_id = r.id 
    WHERE r.staff_id = ? AND r.route_date = ? ORDER BY p.sort_order ASC");
$stmtRt->execute([$staff_id, $date]);
$assigned_points = $stmtRt->fetchAll(PDO::FETCH_ASSOC);

$matched_points = [];

foreach ($assigned_points as $ap) {
    $ptLat = floatval($ap['latitude']);
    $ptLng = floatval($ap['longitude']);
    $radius = intval($ap['radius_meters'] ?: 50);
    
    $is_visited = false;
    $visit_time = null;
    $visit_dur = 0;
    
    // Check against halts
    foreach ($halts as $h) {
        $d = getDistanceMeters($ptLat, $ptLng, $h['lat'], $h['lng']);
        if ($d <= ($radius + $threshold_meters)) { // generous matching
            $is_visited = true;
            $visit_time = $h['start_time'];
            $visit_dur = $h['duration'];
            break;
        }
    }
    
    $matched_points[] = [
        'name' => $ap['name'],
        'status' => $is_visited ? 'Visited' : 'Missed',
        'visit_time' => $visit_time,
        'duration' => $visit_dur,
        'lat' => $ptLat,
        'lng' => $ptLng,
    ];
}

echo json_encode([
    'success' => true, 
    'has_data' => true,
    'halts' => $halts, 
    'assigned_route' => $matched_points
]);
