<?php
/**
 * Menu Permission Functions
 * Helper functions for managing menu-based permissions
 */

/**
 * Get all menu items in hierarchical structure
 * @return array Parent menus with their children
 */
function get_all_menu_items()
{
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return [];

    // Get all active menu items ordered by sort_order
    $sql = "SELECT * FROM menu_items WHERE is_active = 1 ORDER BY sort_order ASC";
    $stmt = $conn->query($sql);
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize into hierarchical structure (supports 3 levels)
    $menu_tree = [];
    $items_by_id = [];
    $children_map = [];

    // Index all items by ID
    foreach ($all_items as $item) {
        $item['children'] = [];
        $items_by_id[$item['id']] = $item;
        if ($item['parent_id'] !== null && $item['parent_id'] != 0) {
            $children_map[$item['parent_id']][] = $item['id'];
        }
    }

    // Attach grandchildren to their parents first (level 3 -> level 2)
    foreach ($items_by_id as $id => $item) {
        if (isset($children_map[$id])) {
            foreach ($children_map[$id] as $child_id) {
                $items_by_id[$id]['children'][] = &$items_by_id[$child_id];
            }
        }
    }

    // Build top-level tree
    foreach ($items_by_id as $id => $item) {
        if ($item['parent_id'] === null || $item['parent_id'] == 0) {
            $menu_tree[$id] = $items_by_id[$id];
        }
    }

    return $menu_tree;
}

/**
 * Get menu permissions for a specific role
 * @param int $role_id Role ID
 * @return array Array of menu item IDs that the role has access to
 */
function get_role_menu_permissions($role_id)
{
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return [];

    $sql = "SELECT menu_item_id FROM role_permissions WHERE role_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$role_id]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Save menu permissions for a role
 * @param int $role_id Role ID
 * @param array $menu_ids Array of menu item IDs to assign
 * @return bool Success status
 */
function save_role_permissions($role_id, $menu_ids = [])
{
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return false;

    try {
        // Start transaction
        $conn->beginTransaction();

        // Delete existing permissions for this role
        $delete_sql = "DELETE FROM role_permissions WHERE role_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->execute([$role_id]);

        // Insert new permissions
        if (!empty($menu_ids)) {
            $insert_sql = "INSERT INTO role_permissions (role_id, menu_item_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);

            foreach ($menu_ids as $menu_id) {
                $insert_stmt->execute([$role_id, $menu_id]);
            }
        }

        // Commit transaction
        $conn->commit();
        return true;
    } catch (PDOException $e) {
        // Rollback on error
        $conn->rollBack();
        error_log("Error saving role permissions: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a user has access to a specific menu
 * @param int $user_id User ID
 * @param string $menu_slug Menu slug to check
 * @return bool True if user has access
 */
function user_has_menu_access($user_id, $menu_slug)
{
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return false;

    // Get user's role
    $user_sql = "SELECT role_id FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    if (!$user) {
        return false;
    }

    // Super Admin check
    if ($user['role_id'] == 1) {
        return true;
    }

    // Check if role has permission for this menu
    $perm_sql = "SELECT COUNT(*) FROM role_permissions rp
                 JOIN menu_items mi ON rp.menu_item_id = mi.id
                 WHERE rp.role_id = ? AND mi.slug = ?";
    $perm_stmt = $conn->prepare($perm_sql);
    $perm_stmt->execute([$user['role_id'], $menu_slug]);

    return $perm_stmt->fetchColumn() > 0;
}

/**
 * Get user's permitted menu items
 * @param int $user_id User ID
 * @return array Hierarchical array of permitted menus
 */
function get_user_menu_items($user_id)
{
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return [];

    // Get user's role
    $user_sql = "SELECT role_id FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    if (!$user) {
        return [];
    }

    // If Super Admin, get all active menus
    if ($user['role_id'] == 1) {
        $menu_sql = "SELECT * FROM menu_items WHERE is_active = 1 ORDER BY sort_order ASC";
        $menu_stmt = $conn->prepare($menu_sql);
        $menu_stmt->execute();
    } else {
        // Get all permitted menu items for this role
        $menu_sql = "SELECT DISTINCT mi.* 
                     FROM menu_items mi
                     JOIN role_permissions rp ON mi.id = rp.menu_item_id
                     WHERE rp.role_id = ? AND mi.is_active = 1
                     ORDER BY mi.sort_order ASC";
        $menu_stmt = $conn->prepare($menu_sql);
        $menu_stmt->execute([$user['role_id']]);
    }
    $all_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get parent menu IDs that have at least one permitted child
    $permitted_parents = [];
    foreach ($all_items as $item) {
        if ($item['parent_id']) {
            $permitted_parents[$item['parent_id']] = true;
        }
    }

    // Also get parent menus that are directly permitted
    $parent_ids = [];
    foreach ($all_items as $item) {
        if (!$item['parent_id']) {
            $parent_ids[] = $item['id'];
        }
    }

    // Build hierarchical structure (supports 3 levels)
    $items_by_id = [];
    $children_map = [];

    foreach ($all_items as $item) {
        $item['children'] = [];
        $items_by_id[$item['id']] = $item;
        if ($item['parent_id'] !== null && $item['parent_id'] != 0) {
            $children_map[$item['parent_id']][] = $item['id'];
        }
    }

    // Attach children (supports grandchildren - 3 levels)
    foreach ($items_by_id as $id => $item) {
        if (isset($children_map[$id])) {
            foreach ($children_map[$id] as $child_id) {
                if (isset($items_by_id[$child_id])) {
                    $items_by_id[$id]['children'][] = &$items_by_id[$child_id];
                }
            }
        }
    }

    // Build top-level tree
    $menu_tree = [];
    foreach ($items_by_id as $id => $item) {
        if ($item['parent_id'] === null || $item['parent_id'] == 0) {
            if (isset($permitted_parents[$item['id']]) || in_array($item['id'], $parent_ids)) {
                $menu_tree[$id] = $items_by_id[$id];
            }
        }
    }

    return $menu_tree;
}

/**
 * Check if menu slug belongs to a parent menu
 * @param string $slug Menu slug
 * @return bool True if parent menu
 */
function is_parent_menu($slug)
{
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return false;

    $sql = "SELECT parent_id FROM menu_items WHERE slug = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$slug]);
    $result = $stmt->fetch();

    return $result && ($result['parent_id'] === null || $result['parent_id'] == 0);
}
