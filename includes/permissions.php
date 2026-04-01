<?php
/**
 * Permission & Authorization Functions
 * Role-based access control system
 */

/**
 * Check if user has permission
 * @param int $user_id User ID
 * @param string $permission Permission name (module.action)
 * @return bool Has permission
 */
function check_permission($user_id, $permission) {
    // Get user's role
    $user = db_select_one('users', ['id' => $user_id]);
    
    if (!$user) {
        return false;
    }
    
    $role_id = $user['role_id'];
    
    // Check if super admin (role_id = 1 has all permissions)
    if ($role_id == 1) {
        return true;
    }
    
    // Check if role has the permission via either legacy permissions table or new menu_items table
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return false;
    
    $sql = "SELECT COUNT(*) as has_permission 
            FROM role_permissions rp
            LEFT JOIN permissions p ON rp.permission_id = p.id
            LEFT JOIN menu_items m ON rp.menu_item_id = m.id
            WHERE rp.role_id = ? AND (p.name = ? OR m.slug = ?)";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$role_id, $permission, $permission]);
        $result = $stmt->fetch();
        
        return $result['has_permission'] > 0;
    } catch (PDOException $e) {
        logError("Permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if current user has permission (Convenience wrapper)
 * @param string $permission Permission name
 * @return bool Has permission
 */
function has_permission($permission) {
    return check_permission(get_current_user_id(), $permission);
}

/**
 * Require permission - redirect if user doesn't have permission
 * @param string $permission Permission name
 */
function require_permission($permission) {
    require_login();
    
    $user_id = get_current_user_id();
    
    if (!check_permission($user_id, $permission)) {
        redirect_with_message(
            BASE_URL . '/modules/dashboard/index.php',
            'You do not have permission to access this page',
            'error'
        );
    }
}

/**
 * Check if user has any of the specified permissions
 * @param int $user_id User ID
 * @param array $permissions Array of permission names
 * @return bool Has any permission
 */
function has_any_permission($user_id, $permissions) {
    foreach ($permissions as $permission) {
        if (check_permission($user_id, $permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has all specified permissions
 * @param int $user_id User ID
 * @param array $permissions Array of permission names
 * @return bool Has all permissions
 */
function has_all_permissions($user_id, $permissions) {
    foreach ($permissions as $permission) {
        if (!check_permission($user_id, $permission)) {
            return false;
        }
    }
    return true;
}

/**
 * Get user's role
 * @param int $user_id User ID
 * @return array|null Role data
 */
function get_user_role($user_id) {
    $user = db_select_one('users', ['id' => $user_id]);
    
    if (!$user) {
        return null;
    }
    
    return db_select_one('roles', ['id' => $user['role_id']]);
}

/**
 * Check if user has role
 * @param int $user_id User ID
 * @param string $role_name Role name
 * @return bool Has role
 */
function has_role($user_id, $role_name) {
    $role = get_user_role($user_id);
    return $role && strtolower($role['name']) === strtolower($role_name);
}

/**
 * Check if user is admin
 * @param int $user_id User ID
 * @return bool Is admin
 */
function is_admin($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    $user = db_select_one('users', ['id' => $user_id]);
    return $user && $user['role_id'] == 1;
}

/**
 * Check if user is Sales Representative (SR)
 * @param int $user_id User ID
 * @return bool Is SR
 */
function is_sr($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    return has_role($user_id, 'Sales Representative');
}

/**
 * Get all permissions for a role
 * @param int $role_id Role ID
 * @return array Permissions
 */
function get_role_permissions($role_id) {
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return [];
    
    $sql = "SELECT p.* FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.module, p.action";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$role_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all available permissions
 * @return array All permissions grouped by module
 */
function get_all_permissions() {
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return [];
    
    $sql = "SELECT * FROM permissions ORDER BY module, action";
    
    try {
        $stmt = $conn->query($sql);
        $permissions = $stmt->fetchAll();
        
        // Group by module
        $grouped = [];
        foreach ($permissions as $permission) {
            $module = $permission['module'];
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $permission;
        }
        
        return $grouped;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Assign permission to role
 * @param int $role_id Role ID
 * @param int $permission_id Permission ID
 * @return bool Success
 */
function assign_permission_to_role($role_id, $permission_id) {
    // Check if already exists
    if (db_exists('role_permissions', ['role_id' => $role_id, 'permission_id' => $permission_id])) {
        return true;
    }
    
    return db_insert('role_permissions', [
        'role_id' => $role_id,
        'permission_id' => $permission_id,
        'created_at' => date('Y-m-d H:i:s')
    ]) !== false;
}

/**
 * Remove permission from role
 * @param int $role_id Role ID
 * @param int $permission_id Permission ID
 * @return bool Success
 */
function remove_permission_from_role($role_id, $permission_id) {
    return db_delete('role_permissions', [
        'role_id' => $role_id,
        'permission_id' => $permission_id
    ]);
}

/**
 * Sync role permissions
 * @param int $role_id Role ID
 * @param array $permission_ids Array of permission IDs
 * @return bool Success
 */
function sync_role_permissions($role_id, $permission_ids) {
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return false;
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Delete existing permissions
        $sql = "DELETE FROM role_permissions WHERE role_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$role_id]);
        
        // Insert new permissions
        if (!empty($permission_ids)) {
            $sql = "INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            
            foreach ($permission_ids as $permission_id) {
                $stmt->execute([$role_id, $permission_id]);
            }
        }
        
        $conn->commit();
        return true;
    } catch (PDOException $e) {
        $conn->rollBack();
        logError("Sync permissions error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create new role
 * @param string $name Role name
 * @param string $description Role description
 * @return int|bool Role ID or false
 */
function create_role($name, $description = '') {
    return db_insert('roles', [
        'name' => clean_input($name),
        'description' => clean_input($description),
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Update role
 * @param int $role_id Role ID
 * @param array $data Role data
 * @return bool Success
 */
function update_role($role_id, $data) {
    return db_update('roles', $data, ['id' => $role_id]);
}

/**
 * Delete role
 * @param int $role_id Role ID
 * @return bool Success
 */
function delete_role($role_id) {
    // Don't allow deleting super admin role
    if ($role_id == 1) {
        return false;
    }
    
    // Check if role has users
    if (db_count('users', ['role_id' => $role_id]) > 0) {
        return false;
    }
    
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return false;
    
    try {
        $conn->beginTransaction();
        
        // Delete role permissions
        db_delete('role_permissions', ['role_id' => $role_id]);
        
        // Delete role
        db_delete('roles', ['id' => $role_id]);
        
        $conn->commit();
        return true;
    } catch (PDOException $e) {
        $conn->rollBack();
        return false;
    }
}

/**
 * Get all roles
 * @return array Roles
 */
function get_all_roles() {
    return db_select('roles', [], '*', 'name ASC');
}

/**
 * Assign role to user
 * @param int $user_id User ID
 * @param int $role_id Role ID
 * @return bool Success
 */
function assign_role_to_user($user_id, $role_id) {
    return db_update('users', ['role_id' => $role_id], ['id' => $user_id]);
}

/**
 * Generate permission name
 * @param string $module Module name
 * @param string $action Action name (view, create, edit, delete)
 * @return string Permission name
 */
function generate_permission_name($module, $action) {
    return strtolower($module) . '.' . strtolower($action);
}

/**
 * Create default permissions for a module
 * @param string $module Module name
 * @return bool Success
 */
function create_module_permissions($module) {
    $actions = ['view', 'create', 'edit', 'delete'];
    global $conn;
    if ($conn === null && function_exists('getDB')) $conn = getDB();
    if ($conn === null) return false;
    
    try {
        $sql = "INSERT INTO permissions (name, module, action, description, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        
        foreach ($actions as $action) {
            $name = generate_permission_name($module, $action);
            $description = ucfirst($action) . ' ' . $module;
            $stmt->execute([$name, $module, $action, $description]);
        }
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

