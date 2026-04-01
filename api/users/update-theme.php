<?php
/**
 * API: Update User Theme Preference
 * Endpoint to save user's theme preference (light/dark mode)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require login
require_login();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate theme value
    $theme = $input['theme'] ?? '';
    if (!in_array($theme, ['light', 'dark'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid theme value. Must be "light" or "dark"']);
        exit;
    }
    
    // Get current user ID
    $user_id = $_SESSION['user_id'];
    
    // Update theme preference
    $sql = "UPDATE users SET theme_preference = ? WHERE id = ?";
    db_query($sql, [$theme, $user_id]);
    
    // Update session
    $_SESSION['theme_preference'] = $theme;
    
    echo json_encode([
        'success' => true,
        'message' => 'Theme preference updated successfully',
        'theme' => $theme
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update theme preference: ' . $e->getMessage()
    ]);
}
