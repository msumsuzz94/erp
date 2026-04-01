<?php
/**
 * Logout Page
 * Destroy user session and redirect to login
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Logout user
logout_user();

// Redirect to login page
redirect(BASE_URL . '/modules/auth/login.php');
?>
