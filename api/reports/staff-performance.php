<?php

/**
 * API: Staff Performance Report
 * Get staff performance metrics based on sales
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = get_current_user_id();

// Ensure they have HR or Admin access to view reports
if (!is_admin($user_id) && !user_has_menu_access($user_id, 'hr')) {
    echo json_encode(['status' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Filters
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : date('Y-m-t');
$staff_id = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : null;
$detail = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;

if ($detail) {
    if (!$staff_id) {
        echo json_encode(['status' => false, 'message' => 'Staff ID is required for details']);
        exit;
    }

    // Product-level details for a specific staff member
    $sql = "SELECT 
                p.name as product_name,
                c.name as category_name,
                SUM(si.quantity) as total_qty,
                SUM(si.subtotal) as total_sales,
                SUM(si.quantity * p.purchase_price) as total_cost
            FROM sales s
            JOIN sale_items si ON s.id = si.sale_id
            JOIN products p ON si.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE s.sale_date BETWEEN ? AND ? 
            AND s.status = 'completed'
            AND s.staff_id = ?
            GROUP BY p.id, p.name, c.name
            ORDER BY total_sales DESC";

    $results = db_query($sql, [$start_date, $end_date, $staff_id]);

    echo json_encode(['status' => true, 'detail' => $results]);
    exit;
}

// Build query for aggregate performance
$where = "s.sale_date BETWEEN ? AND ? AND s.status = 'completed'";
$params = [$start_date, $end_date];

if ($staff_id) {
    $where .= " AND s.staff_id = ?";
    $params[] = $staff_id;
}

// Get aggregate data including profit calculation
$sql = "SELECT 
            st.id as user_id, 
            st.name as username,
            COUNT(DISTINCT s.id) as total_orders,
            SUM(s.total_amount) as total_sales,
            (
                SELECT SUM(si.subtotal - (si.quantity * p.purchase_price))
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                JOIN sales s2 ON si.sale_id = s2.id
                WHERE s2.staff_id = st.id 
                AND s2.sale_date BETWEEN ? AND ?
                AND s2.status = 'completed'
            ) as profit
        FROM sales s
        JOIN staff st ON s.staff_id = st.id
        WHERE $where
        GROUP BY st.id, st.name
        ORDER BY total_sales DESC";

// We need start/end date again for the subquery
$final_params = [$start_date, $end_date, $start_date, $end_date];
if ($staff_id) {
    $final_params[] = $staff_id;
}

$results = db_query($sql, $final_params);

echo json_encode([
    'status' => true,
    'data' => $results
]);
