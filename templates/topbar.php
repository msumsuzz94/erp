<?php
/**
 * Topbar Template
 * Top navigation bar with user info and actions
 */

if (!defined('BASE_URL')) {
    die('Direct access not permitted');
}

$current_user = get_logged_in_user();
?>

<nav class="topbar">
    <div class="d-flex align-items-center">
        <button type="button" id="sidebarCollapse" class="btn btn-light me-3">
            <i class="fas fa-bars"></i>
        </button>
        <span class="text-muted me-3"><?= format_date(date('Y-m-d'), 'l, F j, Y') ?></span>
        <span class="text-muted d-none d-lg-inline">
            <i class="far fa-copyright me-1"></i> Designed & Developed By 
            <a href="https://citnbd.com" target="_blank" class="text-primary fw-bold text-decoration-none">CITNBD</a>
        </span>
    </div>

    <div class="navbar-nav">
        <!-- Theme Toggle -->
        <div class="nav-item">
            <div class="theme-switch-wrapper">
                <label class="theme-switch" for="checkbox">
                    <input type="checkbox" id="checkbox" <?= ($_SESSION['theme_preference'] ?? 'dark') === 'dark' ? 'checked' : '' ?>>
                    <div class="slider round">
                        <i class="fas fa-sun"></i>
                        <i class="fas fa-moon"></i>
                    </div>
                </label>
            </div>
        </div>
        
        <!-- Quick POS -->
        <div class="nav-item">
            <a href="<?= BASE_URL ?>/modules/sales/pos-create.php" class="btn btn-primary btn-sm"
                title="Quick POS (F2)">
                <i class="fas fa-cash-register"></i> POS
            </a>
        </div>

        <!-- Notifications -->
        <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <?php
                // Get low stock count
                $low_stock_count = db_count('products', ['status' => 'active']);
                $sql = "SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level AND status = 'active'";
                $result = db_query_one($sql);
                $low_stock_count = $result['count'] ?? 0;

                if ($low_stock_count > 0):
                    ?>
                    <span class="badge bg-danger badge-counter"><?= $low_stock_count ?></span>
                <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="alertsDropdown">
                <li>
                    <h6 class="dropdown-header">Alerts</h6>
                </li>
                <?php if ($low_stock_count > 0): ?>
                    <li>
                        <a class="dropdown-item" href="<?= BASE_URL ?>/modules/reports/low-stock-report.php">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            <?= $low_stock_count ?> product(s) low on stock
                        </a>
                    </li>
                <?php else: ?>
                    <li><span class="dropdown-item text-muted">No new alerts</span></li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- User Menu -->
        <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <i class="fas fa-user-circle fa-fw"></i>
                <span class="d-none d-md-inline"><?= htmlspecialchars($current_user['username'] ?? 'User') ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <li>
                    <h6 class="dropdown-header">
                        <?= htmlspecialchars($current_user['username'] ?? 'User') ?>
                        <br><small class="text-muted"><?= htmlspecialchars($current_user['email'] ?? '') ?></small>
                    </h6>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item"
                        href="<?= BASE_URL ?>/modules/settings/profile.php">
                        <i class="fas fa-user fa-sm fa-fw me-2"></i> Profile
                    </a>
                </li>
                <?php if (is_admin()): ?>
                    <li>
                        <a class="dropdown-item" href="<?= BASE_URL ?>/modules/settings/business-settings.php">
                            <i class="fas fa-cog fa-sm fa-fw me-2"></i> Settings
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?= BASE_URL ?>/modules/users/activity-log.php">
                            <i class="fas fa-list fa-sm fa-fw me-2"></i> Activity Log
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/modules/auth/logout.php"
                        onclick="return confirm('Are you sure you want to logout?')">
                        <i class="fas fa-sign-out-alt fa-sm fa-fw me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
    // Sidebar toggle with mobile overlay support
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    const overlay = document.getElementById('sidebarOverlay');
    const isMobile = () => window.innerWidth <= 991;

    document.getElementById('sidebarCollapse').addEventListener('click', function () {
        if (isMobile()) {
            // Mobile: toggle overlay mode
            sidebar.classList.toggle('active');
            overlay.classList.toggle('show');
        } else {
            // Desktop: old collapse behavior
            sidebar.classList.toggle('active');
            content.classList.toggle('active');
        }
    });

    // Close sidebar when clicking overlay
    overlay.addEventListener('click', function () {
        sidebar.classList.remove('active');
        overlay.classList.remove('show');
    });

    // Handle window resize
    window.addEventListener('resize', function () {
        if (!isMobile()) {
            overlay.classList.remove('show');
        }
    });

    // Theme Toggle Logic
    const toggleSwitch = document.querySelector('.theme-switch input[type="checkbox"]');

    function switchTheme(e) {
        if (e.target.checked) {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            // Check if set_theme.php endpoint exists or use cookie/session via ajax
            fetch('<?= BASE_URL ?>/includes/set_theme.php?theme=dark');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
            document.documentElement.setAttribute('data-bs-theme', 'light');
            localStorage.setItem('theme', 'light');
            fetch('<?= BASE_URL ?>/includes/set_theme.php?theme=light');
        }
    }

    toggleSwitch.addEventListener('change', switchTheme);

    // Check for saved user preference, if any, on load of the script
    const currentTheme = localStorage.getItem('theme') ? localStorage.getItem('theme') : 'dark'; // Default to dark if null

    if (currentTheme) {
        document.documentElement.setAttribute('data-theme', currentTheme);
        document.documentElement.setAttribute('data-bs-theme', currentTheme);

        if (currentTheme === 'dark') {
            toggleSwitch.checked = true;
        } else {
            toggleSwitch.checked = false;
        }
    }

    // F2 Shortcut for POS
    document.addEventListener('keydown', function (e) {
        if (e.key === 'F2') {
            e.preventDefault();
            window.location.href = '<?= BASE_URL ?>/modules/sales/pos-create.php';
        }
    });
</script>
