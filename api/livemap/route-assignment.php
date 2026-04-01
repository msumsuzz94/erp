<?php
/**
 * Route Assignment API
 * Handles creating, updating, parsing Assigned Routes
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// GET requests (Fetch routes)
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'get';
    
    if ($action === 'get') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : null;
        
        $params = [$date];
        $sql = "SELECT r.*, s.name as staff_name 
                FROM livemap_assigned_routes r
                JOIN staff s ON r.staff_id = s.id
                WHERE r.route_date = ?";
        
        if ($staff_id) {
            $sql .= " AND r.staff_id = ?";
            $params[] = $staff_id;
        }
        
        $routes = db_query($sql, $params) ?: [];
        
        // Fetch points for these routes
        foreach ($routes as &$r) {
            $r['points'] = db_query(
                "SELECT * FROM livemap_assigned_route_points WHERE route_id = ? ORDER BY sort_order ASC",
                [$r['id']]
            ) ?: [];
        }
        
        echo json_encode(['success' => true, 'routes' => $routes]);
        exit;
    }
    
    if ($action === 'list_all') {
        $sql = "SELECT r.*, s.name as staff_name, s.designation,
                (SELECT COUNT(*) FROM livemap_assigned_route_points p WHERE p.route_id = r.id) as point_count
                FROM livemap_assigned_routes r
                JOIN staff s ON r.staff_id = s.id
                ORDER BY r.route_date DESC, r.id DESC LIMIT 50";
        $routes = db_query($sql) ?: [];
        echo json_encode(['success' => true, 'routes' => $routes]);
        exit;
    }
}

// POST requests (Save/Update routes)
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
         echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
         exit;
    }
    
    $action = $_GET['action'] ?? 'save';
    
    if ($action === 'save') {
        $staff_id = intval($input['staff_id'] ?? 0);
        $route_date = $input['route_date'] ?? '';
        $title = $input['title'] ?? '';
        $notes = $input['notes'] ?? '';
        $points = $input['points'] ?? []; // Array of {name, lat, lng, radius}
        
        if (!$staff_id || !$route_date || empty($points)) {
            echo json_encode(['success' => false, 'message' => 'Staff, Date, and at least one point are required']);
            exit;
        }

        try {
            $conn->beginTransaction();
            
            // Check if route already exists for this staff on this date
            $existing = db_query("SELECT id FROM livemap_assigned_routes WHERE staff_id = ? AND route_date = ?", [$staff_id, $route_date]);
            
            if ($existing) {
                $route_id = $existing[0]['id'];
                // Update header
                $stmt = $conn->prepare("UPDATE livemap_assigned_routes SET title = ?, notes = ? WHERE id = ?");
                $stmt->execute([$title, $notes, $route_id]);
                
                // Clear old points
                $stmtDel = $conn->prepare("DELETE FROM livemap_assigned_route_points WHERE route_id = ?");
                $stmtDel->execute([$route_id]);
            } else {
                // Insert new route
                $stmt = $conn->prepare("INSERT INTO livemap_assigned_routes (staff_id, route_date, title, notes, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$staff_id, $route_date, $title, $notes, get_current_user_id()]);
                $route_id = $conn->lastInsertId();
            }
            
            // Insert points
            $stmtPt = $conn->prepare("INSERT INTO livemap_assigned_route_points (route_id, name, latitude, longitude, radius_meters, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $sort = 1;
            foreach ($points as $pt) {
                if (empty($pt['latitude']) || empty($pt['longitude']) || empty($pt['name'])) continue;
                $rad = isset($pt['radius']) && $pt['radius'] > 0 ? intval($pt['radius']) : 50; // allow adjustment
                $stmtPt->execute([$route_id, $pt['name'], $pt['latitude'], $pt['longitude'], $rad, $sort]);
                $sort++;
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Route assigned successfully', 'route_id' => $route_id]);
            
        } catch (PDOException $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// DELETE requests
elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Missing ID']);
        exit;
    }
    
    // Delete the route (Cascade will delete points)
    $stmt = $conn->prepare("DELETE FROM livemap_assigned_routes WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true, 'message' => 'Route deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete route']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
