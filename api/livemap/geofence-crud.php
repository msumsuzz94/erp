<?php
/**
 * Live Map - Geofence CRUD API
 * GET: List all geofences
 * POST: Create/Update geofence
 * DELETE: ?id=X
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function handleGet() {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id > 0) {
        $geofence = db_select_one('geofences', ['id' => $id]);
        if ($geofence) {
            // Get visit count
            $visits = db_query("SELECT COUNT(*) as cnt FROM geofence_visits WHERE geofence_id = ?", [$id]);
            $geofence['visit_count'] = intval($visits[0]['cnt'] ?? 0);
            echo json_encode(['success' => true, 'geofence' => $geofence]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Not found']);
        }
    } else {
        $geofences = db_query("SELECT g.*, u.username AS created_by_name,
            (SELECT COUNT(*) FROM geofence_visits WHERE geofence_id = g.id) AS visit_count
            FROM geofences g 
            LEFT JOIN users u ON g.created_by = u.id
            ORDER BY g.name ASC");
        echo json_encode(['success' => true, 'geofences' => $geofences ?: []]);
    }
}

function handlePost() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Try form data
        $input = $_POST;
    }

    $id = intval($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $latitude = floatval($input['latitude'] ?? 0);
    $longitude = floatval($input['longitude'] ?? 0);
    $radius = intval($input['radius'] ?? 50);

    if (empty($name) || ($latitude == 0 && $longitude == 0)) {
        echo json_encode(['success' => false, 'message' => 'Name and location required']);
        return;
    }

    $data = [
        'name' => $name,
        'description' => trim($input['description'] ?? ''),
        'latitude' => $latitude,
        'longitude' => $longitude,
        'radius' => max(10, $radius),
        'type' => $input['type'] ?? 'client',
        'address' => trim($input['address'] ?? ''),
        'contact_person' => trim($input['contact_person'] ?? ''),
        'contact_phone' => trim($input['contact_phone'] ?? ''),
        'is_active' => intval($input['is_active'] ?? 1)
    ];

    global $conn;
    
    if ($id > 0) {
        // Update
        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            $sets[] = "`{$key}` = ?";
            $params[] = $value;
        }
        $params[] = $id;
        
        $stmt = $conn->prepare("UPDATE geofences SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Geofence updated', 'id' => $id]);
    } else {
        // Create
        $data['created_by'] = get_current_user_id();
        
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $stmt = $conn->prepare("INSERT INTO geofences (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")");
        $stmt->execute(array_values($data));
        
        echo json_encode(['success' => true, 'message' => 'Geofence created', 'id' => $conn->lastInsertId()]);
    }
}

function handleDelete() {
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }

    global $conn;
    $stmt = $conn->prepare("DELETE FROM geofences WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Geofence deleted']);
}
