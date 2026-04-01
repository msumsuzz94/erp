<?php

/**
 * Header Template
 * Included in all pages for consistent layout
 */

// Ensure config is loaded
if (!defined('BASE_URL')) {
    die('Direct access not permitted');
}

// Load cache helper functions
require_once __DIR__ . '/../includes/cache_helper.php';

// Set no-cache headers for all dynamic pages by default
set_cache_headers('none');

// Get flash message
$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <!-- Prevent Browser Caching -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <title><?= $page_title ?? 'Dashboard' ?> - <?= APP_NAME ?></title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- PWA Manifest & App Config -->
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <meta name="theme-color" content="#111827">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

    <!-- Custom CSS with Cache Busting -->
    <link href="<?= BASE_URL ?>/assets/css/custom.css?v=<?= time() ?>" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="<?= asset_url('assets/css/dark-mode.css') ?>" rel="stylesheet">
    <link href="<?= asset_url('assets/css/responsive.css') ?>" rel="stylesheet">

    <!-- PWA Setup ========================================================== -->
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <meta name="theme-color" content="#4e73df">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/images/logo.png">

    <!-- Register Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('<?= BASE_URL ?>/service-worker.js').then(function(registration) {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                }, function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
    <!-- ==================================================================== -->

    <!-- Standardized Print Styles -->
    <link href="<?= asset_url('assets/css/print.css') ?>" rel="stylesheet">

    <?php if (isset($additional_css)): ?>
        <?= $additional_css ?>
    <?php endif; ?>

    <!-- Theme Manager -->
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        // Auto Clear Cache Interval in Minutes (0 = Disabled)
        window.CACHE_INTERVAL = <?= isset($business_settings['cache_clear_interval']) ? (int)$business_settings['cache_clear_interval'] : 0 ?>;

        // Immediately apply theme from localStorage to prevent FOUC
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        }
    </script>

    <!-- Cache Manager for Auto Cache Clearing -->
    <script src="<?= asset_url('assets/js/cache-manager.js') ?>"></script>

    <script>
        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js')
                    .then(registration => console.log('PWA ServiceWorker registered with scope:', registration.scope))
                    .catch(err => console.log('PWA ServiceWorker registration failed:', err));
            });
        }
    </script>
</head>

<body>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <!-- Topbar -->
            <?php include __DIR__ . '/topbar.php'; ?>

            <!-- Main Content -->
            <div class="container-fluid px-4">

                <!-- Standardized Print Header (Visible only when printing) -->
                <?php include __DIR__ . '/standard-print-header.php'; ?>

                <!-- Flash Messages -->
                <?php if ($flash['message']): ?>
                    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Page Heading with Back Button -->
                <?php if (isset($page_title)): ?>
                    <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
                        <div class="d-flex align-items-center gap-2">
                            <!-- Back Button -->
                            <button onclick="history.back()" class="btn btn-outline-secondary btn-sm me-2" title="Go Back">
                                <i class="fas fa-arrow-left"></i>
                            </button>
                            <h1 class="h3 mb-0 text-gray-800"><?= $page_title ?></h1>
                        </div>
                        <?php if (isset($page_actions)): ?>
                            <div>
                                <?= $page_actions ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>