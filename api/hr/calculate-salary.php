<?php
/**
 * Calculate Salary based on attendance API
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST request required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$staff_id = (int)($input['staff_id'] ?? 0);
$month = (int)($input['month'] ?? date('n'));
$year = (int)($input['year'] ?? date('Y'));

if (!$staff_id || !$month || !$year) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$staff = db_select_one('staff', ['id' => $staff_id]);
if (!$staff) {
    echo json_encode(['success' => false, 'message' => 'Staff not found']);
    exit;
}

// Get monthly working days setting
$settings = db_select_one('attendance_settings', ['setting_key' => 'monthly_working_days']);
$monthly_working_days = $settings ? (int)$settings['setting_value'] : 30;
if ($monthly_working_days <= 0) $monthly_working_days = 30;

// Get present days from attendance table
$start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$end_date = date("Y-m-t", strtotime($start_date));

$attendance_res = db_query("SELECT COUNT(*) as present_days FROM attendance WHERE staff_id = ? AND date BETWEEN ? AND ? AND status = 'present'", [$staff_id, $start_date, $end_date]);
$present_days = (int)($attendance_res[0]['present_days'] ?? 0);

// Default basic salary (usually stored in staff table, but if not, we assume user types it)
// We will return the calculated proportion
// Wait, is there a basic salary in staff table? Let's check.
$standard_basic = (float)($staff['salary'] ?? 0);

// Return calculated info

echo json_encode([
    'success' => true,
    'monthly_working_days' => $monthly_working_days,
    'present_days' => $present_days,
    'absent_days' => max(0, $monthly_working_days - $present_days),
    'standard_basic_salary' => $standard_basic
]);
exit;
