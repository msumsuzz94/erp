<?php
/**
 * Business Management System - Entry Point
 * Redirects to login or dashboard based on authentication status
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

init_session();

// Redirect based on login status
if (is_logged_in()) {
    redirect(BASE_URL . '/modules/dashboard/index.php');
} else {
    redirect(BASE_URL . '/modules/auth/login.php');
}
?>
