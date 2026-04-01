<?php
/**
 * Migration: Add License Management to Menu
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

try {
    $pdo = getDB();

    // 1. Get Settings Parent ID
    $stmt = $pdo->prepare("SELECT id FROM menu_items WHERE slug = ?");
    $stmt->execute(['settings']);
    $settings = $stmt->fetch();

    if (!$settings) {
        die("Error: 'Settings' menu not found.");
    }

    $parent_id = $settings['id'];

    // 2. Check if License Management already exists
    $stmt = $pdo->prepare("SELECT id FROM menu_items WHERE slug = ?");
    $stmt->execute(['settings.license']);
    $existing = $stmt->fetch();

    if (!$existing) {
        // Find max sort order in settings submenus
        $stmt = $pdo->prepare("SELECT MAX(sort_order) as max_sort FROM menu_items WHERE parent_id = ?");
        $stmt->execute([$parent_id]);
        $res = $stmt->fetch();
        $sort_order = ($res['max_sort'] ?? 0) + 1;

        // Insert new menu item
        $stmt = $pdo->prepare("INSERT INTO menu_items (name, slug, icon, url, parent_id, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'License Management',
            'settings.license',
            'fas fa-key',
            '/modules/settings/license-manage.php',
            $parent_id,
            $sort_order
        ]);
        $menu_item_id = $pdo->lastInsertId();
        echo "Menu item 'License Management' added successfully.\n";
    } else {
        $menu_item_id = $existing['id'];
        echo "Menu item 'License Management' already exists.\n";
    }

    // 3. Grant permission to Admin and Super Admin roles
    $stmt = $pdo->query("SELECT id FROM roles WHERE name IN ('Super Admin', 'Admin')");
    $roles = $stmt->fetchAll();

    foreach ($roles as $role) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, menu_item_id) VALUES (?, ?)");
        $stmt->execute([$role['id'], $menu_item_id]);
    }

    echo "Permissions granted to Admin roles.\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
