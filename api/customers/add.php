<?php

/**
 * Ajax API: Add New Customer
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!is_post()) {
    echo json_encode(['status' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!is_logged_in()) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$name = clean_input($_POST['name'] ?? '');
$phone = clean_input($_POST['phone'] ?? '');

if (empty($name) || empty($phone) || empty($_POST['address'])) {
    echo json_encode(['status' => false, 'message' => 'Customer name, phone, and address are required']);
    exit;
}

// Check for duplicate phone
if (!empty($phone)) {
    $existing = db_select_one('customers', ['phone' => $phone]);
    if ($existing) {
        echo json_encode(['status' => false, 'message' => 'A customer with this phone number already exists']);
        exit;
    }
}

$customer_data = [
    'name' => $name,
    'phone' => $phone,
    'email' => clean_input($_POST['email'] ?? ''),
    'address' => clean_input($_POST['address'] ?? ''),
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s')
];

$customer_id = db_insert('customers', $customer_data);

if ($customer_id) {
    echo json_encode([
        'status' => true,
        'message' => 'Customer created successfully',
        'customer' => [
            'id' => $customer_id,
            'name' => $name,
            'phone' => $phone
        ]
    ]);
} else {
    echo json_encode(['status' => false, 'message' => 'Failed to create customer']);
}
