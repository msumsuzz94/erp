<?php
/**
 * Sidebar Navigation Template
 * Dynamic menu based on user permissions
 */

if (!defined('BASE_URL')) {
    die('Direct access not permitted');
}

// Ensure required functions are loaded
if (!function_exists('check_permission')) {
    require_once __DIR__ . '/../includes/permissions.php';
}
if (!function_exists('get_user_menu_items')) {
    require_once __DIR__ . '/../includes/menu_functions.php';
}

$current_user = get_logged_in_user();
$current_page = basename($_SERVER['PHP_SELF']);

// Get user's permitted menu items
$user_id = get_current_user_id();
$permitted_menus = get_user_menu_items($user_id);

// If no menus are assigned, user might be super admin - show all
if (empty($permitted_menus) && is_admin($user_id)) {
    $permitted_menus = get_all_menu_items();
}

// Get business settings for logo/name display
$business_settings = db_select_one('business_settings', ['id' => 1]);
if (!$business_settings) {
    $business_settings = [
        'business_name' => defined('BUSINESS_NAME') ? BUSINESS_NAME : 'My Business',
        'business_logo' => null,
        'display_in_menu' => 'name'
    ];
}
?>

<nav id="sidebar">
    <div class="sidebar-header">
        <?php if ($business_settings['display_in_menu'] == 'logo' && $business_settings['business_logo']): ?>
            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($business_settings['business_logo']) ?>"
                alt="<?= htmlspecialchars($business_settings['business_name']) ?>"
                style="max-width: 100%; max-height: 50px; object-fit: contain;">
        <?php else: ?>
            <h3><i class="fas fa-store"></i> <?= htmlspecialchars($business_settings['business_name']) ?></h3>
            <small class="text-white-50"><?= APP_VERSION ?></small>
        <?php endif; ?>
        
        <!-- Copyright Section -->
        <div class="sidebar-copyright mt-3 pt-2" style="border-top: 1px solid rgba(255,255,255,0.1);">
            <small class="text-white-50">
                Designed & Developed By <a href="https://citnbd.com/" target="_blank" rel="noopener noreferrer" class="text-white font-weight-bold" style="text-decoration: none;">CITNBD</a>
            </small>
        </div>
    </div>

    <ul class="components list-unstyled">

        <?php if (!empty($permitted_menus)): ?>
            <?php foreach ($permitted_menus as $menu): ?>
                <?php if (empty($menu['children'])): ?>
                    <!-- Single menu item without children -->
                    <li>
                        <a href="<?= BASE_URL ?><?= $menu['url'] ?>"
                            class="<?= strpos($_SERVER['PHP_SELF'], $menu['url']) !== false ? 'active' : '' ?>">
                            <i class="<?= $menu['icon'] ?>"></i> <?= htmlspecialchars($menu['name']) ?>
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Menu item with children -->
                    <li>
                        <a href="#menu<?= $menu['id'] ?>" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                            <i class="<?= $menu['icon'] ?>"></i> <?= htmlspecialchars($menu['name']) ?>
                        </a>
                        <ul class="collapse list-unstyled" id="menu<?= $menu['id'] ?>">
                            <?php foreach ($menu['children'] as $child): ?>
                                <?php if (!empty($child['children'])): ?>
                                    <!-- Sub-submenu (3rd level) -->
                                    <li>
                                        <a href="#submenu<?= $child['id'] ?>" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                                            <?php if (!empty($child['icon'])): ?><i class="<?= $child['icon'] ?>"></i> <?php endif; ?><?= htmlspecialchars($child['name']) ?>
                                        </a>
                                        <ul class="collapse list-unstyled ms-3" id="submenu<?= $child['id'] ?>">
                                            <?php foreach ($child['children'] as $grandchild): ?>
                                                <li>
                                                    <a href="<?= BASE_URL ?><?= $grandchild['url'] ?>">
                                                        <?php if (!empty($grandchild['icon'])): ?><i class="<?= $grandchild['icon'] ?>"></i> <?php endif; ?><?= htmlspecialchars($grandchild['name']) ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php else: ?>
                                    <li>
                                        <a href="<?= BASE_URL ?><?= $child['url'] ?>">
                                            <?php if (!empty($child['icon'])): ?><i class="<?= $child['icon'] ?>"></i> <?php endif; ?><?= htmlspecialchars($child['name']) ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- No permissions assigned - show only dashboard -->
            <li>
                <a href="<?= BASE_URL ?>/modules/dashboard/index.php"
                    class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
        <?php endif; ?>

    </ul>
</nav>
