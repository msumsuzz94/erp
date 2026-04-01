<?php
/**
 * Live Map - Update Location API
 * Receives GPS data from mobile PWA (supports batch sync for offline data)
 * POST: { staff_id, locations: [{lat, lng, accuracy, battery, speed, recorded_at}] }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$staff_id = intval($input['staff_id'] ?? 0);
if ($staff_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff_id']);
    exit;
}

// Verify staff exists
$staff = db_select_one('staff', ['id' => $staff_id]);
if (!$staff) {
    echo json_encode(['success' => false, 'message' => 'Staff not found']);
    exit;
}

// Support both single location and batch sync
$locations = [];
if (isset($input['locations']) && is_array($input['locations'])) {
    $locations = $input['locations']; // Batch from offline sync
} elseif (isset($input['latitude'])) {
    $locations[] = $input; // Single location update
}

if (empty($locations)) {
    echo json_encode(['success' => false, 'message' => 'No location data']);
    exit;
}

global $conn;
$inserted = 0;
$errors = 0;

// Load active geofences for auto-detection
$geofences = db_query("SELECT * FROM geofences WHERE is_active = 1");

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("INSERT INTO staff_locations 
        (staff_id, latitude, longitude, accuracy, battery_level, is_charging, speed, recorded_at, device_info) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($locations as $loc) {
        $lat = floatval($loc['latitude'] ?? $loc['lat'] ?? 0);
        $lng = floatval($loc['longitude'] ?? $loc['lng'] ?? 0);
        
        if ($lat == 0 && $lng == 0) { $errors++; continue; }

        $accuracy = isset($loc['accuracy']) ? floatval($loc['accuracy']) : null;
        $battery = isset($loc['battery_level']) ? intval($loc['battery_level']) : (isset($loc['battery']) ? intval($loc['battery']) : null);
        $is_charging = intval($loc['is_charging'] ?? 0);
        $speed = isset($loc['speed']) ? floatval($loc['speed']) : null;
        $recorded_at = $loc['recorded_at'] ?? date('Y-m-d H:i:s');
        $device_info = $loc['device_info'] ?? ($input['device_info'] ?? null);

        $stmt->execute([
            $staff_id, $lat, $lng, $accuracy, $battery, $is_charging, $speed, $recorded_at, $device_info
        ]);
        $inserted++;

        // Geofence auto-detection
        if (!empty($geofences)) {
            checkGeofence($staff_id, $lat, $lng, $recorded_at, $geofences);
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Synced {$inserted} location(s)",
        'inserted' => $inserted,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Check if staff entered or exited any geofence
 */
function checkGeofence($staff_id, $lat, $lng, $recorded_at, $geofences) {
    foreach ($geofences as $gf) {
        $distance = haversineDistance($lat, $lng, floatval($gf['latitude']), floatval($gf['longitude']));
        $inside = $distance <= $gf['radius'];

        // Check if there's an open visit (entered but not exited)
        $open_visit = db_query(
            "SELECT id FROM geofence_visits WHERE staff_id = ? AND geofence_id = ? AND exited_at IS NULL ORDER BY entered_at DESC LIMIT 1",
            [$staff_id, $gf['id']]
        );

        if ($inside && empty($open_visit)) {
            // Staff entered geofence - create new visit
            db_insert('geofence_visits', [
                'staff_id' => $staff_id,
                'geofence_id' => $gf['id'],
                'entered_at' => $recorded_at
            ]);
        } elseif (!$inside && !empty($open_visit)) {
            // Staff exited geofence - close the visit
            $visit_id = $open_visit[0]['id'];
            $entered = db_query("SELECT entered_at FROM geofence_visits WHERE id = ?", [$visit_id]);
            $entered_at = $entered[0]['entered_at'] ?? $recorded_at;
            $duration = round((strtotime($recorded_at) - strtotime($entered_at)) / 60);

            global $conn;
            $conn->prepare("UPDATE geofence_visits SET exited_at = ?, duration_minutes = ? WHERE id = ?")
                 ->execute([$recorded_at, max(0, $duration), $visit_id]);
        }
    }
}

/**
 * Calculate distance between two GPS points (Haversine formula)
 * @return float Distance in meters
 */
function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000; // Earth radius in meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}
