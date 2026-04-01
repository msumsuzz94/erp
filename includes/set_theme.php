<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

if (isset($_GET['theme'])) {
    $theme = $_GET['theme'];
    if (in_array($theme, ['light', 'dark'])) {
        $_SESSION['theme_preference'] = $theme;
        
        // Optional: Update user preference in database if logged in
        if (is_logged_in()) {
            // $user_id = get_user_id();
            // db_update('users', ['theme_preference' => $theme], ['id' => $user_id]);
        }
    }
}

// Return success regardless
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'theme' => $_SESSION['theme_preference'] ?? 'dark']);
?>
