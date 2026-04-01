<?php

/**
 * Roles & Permissions Management Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('users.roles');

// Handle role creation
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'create_role') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $data = [
            'name' => clean_input($_POST['name']),
            'description' => clean_input($_POST['description']),
            'created_by' => get_current_user_id()
        ];

        if (db_insert('roles', $data)) {
            log_activity(get_current_user_id(), 'create_role', "Created role: {$data['name']}");
            redirect_with_message($_SERVER['PHP_SELF'], 'Role created successfully', 'success');
        }
    }
}

// Handle role editing
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'edit_role') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $edit_role_id = (int)$_POST['edit_role_id'];

        // Prevent editing of essential system roles structurally if needed, but usually just name/desc are fine, or we can restrict editing role 1 & 2
        if ($edit_role_id == 1 || $edit_role_id == 2) {
            redirect_with_message($_SERVER['PHP_SELF'], 'Cannot edit system default roles.', 'danger');
        } else {
            // Verify permission to edit
            $can_edit = false;
            if (get_logged_in_user()['role_id'] == 1) {
                $can_edit = true;
            } else {
                // Check if user created this role
                $role_check = db_select_one('roles', ['id' => $edit_role_id, 'created_by' => get_current_user_id()]);
                if ($role_check) {
                    $can_edit = true;
                }
            }

            if ($can_edit) {
                $data = [
                    'name' => clean_input($_POST['name']),
                    'description' => clean_input($_POST['description'])
                ];

                if (db_update('roles', $data, ['id' => $edit_role_id])) {
                    log_activity(get_current_user_id(), 'edit_role', "Edited role ID: {$edit_role_id}");
                    redirect_with_message($_SERVER['PHP_SELF'], 'Role updated successfully', 'success');
                }
            } else {
                redirect_with_message($_SERVER['PHP_SELF'], 'You do not have permission to edit this role.', 'danger');
            }
        }
    }
}

// Handle permission assignment
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'assign_permissions') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $role_id = (int)$_POST['role_id'];
        $menu_ids = $_POST['menu_permissions'] ?? [];

        // Delete existing permissions for this role
        db_delete('role_permissions', ['role_id' => $role_id]);

        // Insert new permissions
        foreach ($menu_ids as $menu_id) {
            db_insert('role_permissions', [
                'role_id' => $role_id,
                'menu_item_id' => (int)$menu_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        log_activity(get_current_user_id(), 'update_permissions', "Updated permissions for role ID: $role_id");
        redirect_with_message($_SERVER['PHP_SELF'] . '?role_id=' . $role_id, 'Permissions updated successfully', 'success');
    }
}

// Handle role deletion
if (is_post() && isset($_POST['action']) && $_POST['action'] === 'delete_role') {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $delete_role_id = (int)$_POST['delete_role_id'];

        // Prevent deletion of essential roles (e.g., Super Admin, Default User)
        if ($delete_role_id == 1 || $delete_role_id == 2) {
            redirect_with_message($_SERVER['PHP_SELF'], 'Cannot delete system default roles.', 'danger');
        } else {
            // Verify User Password First
            $confirm_password = $_POST['confirm_password'] ?? '';
            $user_id = get_current_user_id();
            $user = db_select_one('users', ['id' => $user_id]);

            if (!$user || !password_verify($confirm_password, $user['password_hash'])) {
                redirect_with_message($_SERVER['PHP_SELF'], 'Incorrect password. Role deletion cancelled.', 'danger');
            } else {
                // Verify permission to delete
                $can_delete = false;
                if (get_logged_in_user()['role_id'] == 1) {
                    $can_delete = true;
                } else {
                    // Check if user created this role
                    $role_check = db_select_one('roles', ['id' => $delete_role_id, 'created_by' => get_current_user_id()]);
                    if ($role_check) {
                        $can_delete = true;
                    }
                }

                if ($can_delete) {
                    // Remove assigned users or reset them to default role (2)
                    db_update('users', ['role_id' => 2], ['role_id' => $delete_role_id]);
                    // Delete permissions
                    db_delete('role_permissions', ['role_id' => $delete_role_id]);
                    // Delete role
                    db_delete('roles', ['id' => $delete_role_id]);

                    log_activity(get_current_user_id(), 'delete_role', "Deleted role ID: {$delete_role_id}");
                    redirect_with_message($_SERVER['PHP_SELF'], 'Role deleted successfully', 'success');
                } else {
                    redirect_with_message($_SERVER['PHP_SELF'], 'You do not have permission to delete this role.', 'danger');
                }
            } // Close the verify password else block
        }
    }
}

// Get all roles
$current_role_id = $_SESSION['user_role_id'];
if ($current_role_id == 1) {
    // Super Admin sees all roles
    $roles = db_select('roles', [], '*', 'name ASC');
} else {
    // Others see roles they created themselves AND roles they are assigned to, EXCLUDING Super Admin (1)
    $sql_roles = "SELECT * FROM roles WHERE (id = ? OR created_by = ?) AND id != 1 ORDER BY name ASC";
    $roles = db_query($sql_roles, [$current_role_id, get_current_user_id()]);
}

// Get all menu items based on current user's permissions
$current_user_id = get_current_user_id();
$current_user = db_select_one('users', ['id' => $current_user_id]);

if ($current_user && $current_user['role_id'] == 1) {
    // Super Admin gets all menus
    $sql = "SELECT * FROM menu_items ORDER BY parent_id, sort_order";
    $all_menus = db_query($sql);
} else {
    // Other users only see what they have access to
    $role_id = $current_user['role_id'];
    // We need menus that the user's role has in role_permissions, OR it's a parent menu where a child has access
    $sql = "SELECT DISTINCT m.* FROM menu_items m
            LEFT JOIN role_permissions rp ON m.id = rp.menu_item_id AND rp.role_id = ?
            LEFT JOIN menu_items child ON child.parent_id = m.id
            LEFT JOIN role_permissions rp_child ON child.id = rp_child.menu_item_id AND rp_child.role_id = ?
            WHERE rp.id IS NOT NULL OR rp_child.id IS NOT NULL
            ORDER BY m.parent_id, m.sort_order";
    $all_menus = db_query($sql, [$role_id, $role_id]);
}

// Organize menus by parent
$menu_tree = [];
$items_by_id = [];

// Index all items and prepare structure
foreach ($all_menus as $menu) {
    $menu['children'] = [];
    $items_by_id[$menu['id']] = $menu;
}

// Build tree
foreach ($all_menus as $menu) {
    if ($menu['parent_id'] !== null && $menu['parent_id'] != 0) {
        if (isset($items_by_id[$menu['parent_id']])) {
            $items_by_id[$menu['parent_id']]['children'][] = &$items_by_id[$menu['id']];
        }
    }
}

// Extract top-level parents
foreach ($items_by_id as $id => $item) {
    if ($item['parent_id'] === null || $item['parent_id'] == 0) {
        $menu_tree[$id] = $item;
    }
}

// Get selected role's permissions
$selected_role_id = get_param('role_id', 0);
if (!$selected_role_id && count($roles) > 0) {
    $selected_role_id = $roles[0]['id'];
}
$role_permissions = [];
if ($selected_role_id) {
    $sql = "SELECT menu_item_id FROM role_permissions WHERE role_id = ?";
    $perms = db_query($sql, [$selected_role_id]);
    foreach ($perms as $p) {
        $role_permissions[] = $p['menu_item_id'];
    }
}

$page_title = 'Roles & Permissions';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <!-- Roles List -->
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Roles</h6>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($roles as $role): ?>
                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $selected_role_id == $role['id'] ? 'active' : '' ?>" style="background-color: <?= $selected_role_id == $role['id'] ? 'var(--primary-color)' : 'var(--card-bg)' ?>; color: <?= $selected_role_id == $role['id'] ? '#fff' : 'var(--text-primary)' ?>; border-color: var(--border-color);">
                            <a href="?role_id=<?= $role['id'] ?>" class="text-decoration-none" style="color: inherit; flex-grow: 1;">
                                <strong><?= htmlspecialchars($role['name']) ?></strong>
                                <?php if ($role['id'] == $current_role_id): ?>
                                    <span class="badge bg-info text-white ms-1" style="font-size: 0.7em;">(Your Role)</span>
                                <?php endif; ?>
                                <?php if ($role['description']): ?>
                                    <br><small><?= htmlspecialchars($role['description']) ?></small>
                                <?php endif; ?>
                            </a>

                            <?php
                            // Only allow edit/delete if it's not a core role (1,2) and user has permission (Super Admin or created it)
                            $can_manage_ui = false;
                            if ($role['id'] != 1 && $role['id'] != 2) {
                                if ($current_role_id == 1 || $role['created_by'] == get_current_user_id()) {
                                    $can_manage_ui = true;
                                }
                            }
                            ?>
                            <?php if ($can_manage_ui): ?>
                                <div class="ms-2" style="white-space: nowrap;">
                                    <button type="button" class="btn btn-sm btn-info edit-role-btn text-white" data-bs-toggle="modal" data-bs-target="#editRoleModal" data-role-id="<?= $role['id'] ?>" data-role-name="<?= htmlspecialchars($role['name']) ?>" data-role-desc="<?= htmlspecialchars($role['description'] ?? '') ?>" title="Edit Role">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-role-btn ms-1" data-bs-toggle="modal" data-bs-target="#deleteRoleModal" data-role-id="<?= $role['id'] ?>" data-role-name="<?= htmlspecialchars($role['name']) ?>" title="Delete Role">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                    <i class="fas fa-plus"></i> Add New Role
                </button>
            </div>
        </div>
    </div>

    <!-- Permissions Assignment -->
    <div class="col-md-8">
        <?php if ($selected_role_id): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Assign Menu Permissions</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="assign_permissions">
                        <input type="hidden" name="role_id" value="<?= $selected_role_id ?>">

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Select which menu items this role can access. Parent menus and their submenus are listed below.
                        </div>

                        <div class="row">
                            <?php foreach ($menu_tree as $parent_id => $parent_data): ?>
                                <div class="col-md-6">
                                    <div class="card mb-3" style="border-color: var(--border-color); background-color: var(--card-bg);">
                                        <div class="card-header" style="background-color: var(--body-bg); border-bottom: 1px solid var(--border-color);">
                                            <div class="form-check">
                                                <input class="form-check-input parent-checkbox level-1"
                                                    type="checkbox"
                                                    name="menu_permissions[]"
                                                    value="<?= $parent_data['id'] ?>"
                                                    id="menu_<?= $parent_data['id'] ?>"
                                                    data-id="<?= $parent_data['id'] ?>"
                                                    <?= in_array($parent_data['id'], $role_permissions) ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold" for="menu_<?= $parent_data['id'] ?>">
                                                    <i class="<?= htmlspecialchars($parent_data['icon'] ?? 'fas fa-link') ?>"></i>
                                                    <?= htmlspecialchars($parent_data['name']) ?>
                                                </label>
                                            </div>
                                        </div>

                                        <?php if (!empty($parent_data['children'])): ?>
                                            <div class="card-body">
                                                <div class="row">
                                                    <?php foreach ($parent_data['children'] as $child): ?>
                                                        <div class="col-md-12 mb-3">
                                                            <div class="form-check ms-3">
                                                                <input class="form-check-input child-checkbox level-2"
                                                                    type="checkbox"
                                                                    name="menu_permissions[]"
                                                                    value="<?= $child['id'] ?>"
                                                                    id="menu_<?= $child['id'] ?>"
                                                                    data-id="<?= $child['id'] ?>"
                                                                    data-parent-id="<?= $parent_data['id'] ?>"
                                                                    <?= in_array($child['id'], $role_permissions) ? 'checked' : '' ?>>
                                                                <label class="form-check-label fw-bold" for="menu_<?= $child['id'] ?>">
                                                                    <?= htmlspecialchars($child['name']) ?>
                                                                </label>
                                                            </div>

                                                            <?php if (!empty($child['children'])): ?>
                                                                <div class="row mt-2 ms-4">
                                                                    <?php foreach ($child['children'] as $grandchild): ?>
                                                                        <div class="col-md-12">
                                                                            <div class="form-check ms-3">
                                                                                <input class="form-check-input grandchild-checkbox level-3"
                                                                                    type="checkbox"
                                                                                    name="menu_permissions[]"
                                                                                    value="<?= $grandchild['id'] ?>"
                                                                                    id="menu_<?= $grandchild['id'] ?>"
                                                                                    data-id="<?= $grandchild['id'] ?>"
                                                                                    data-parent-id="<?= $child['id'] ?>"
                                                                                    data-grandparent-id="<?= $parent_data['id'] ?>"
                                                                                    <?= in_array($grandchild['id'], $role_permissions) ? 'checked' : '' ?>>
                                                                                <label class="form-check-label small" for="menu_<?= $grandchild['id'] ?>">
                                                                                    <?= htmlspecialchars($grandchild['name']) ?>
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Permissions
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Please select a role to manage permissions.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="create_role">

                <div class="modal-header">
                    <h5 class="modal-title">Create New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="edit_role">
                <input type="hidden" name="edit_role_id" id="edit_role_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title">Edit Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label>Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_role_name" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Description</label>
                        <textarea name="description" id="edit_role_desc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Role Modal -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="delete_role">
                <input type="hidden" name="delete_role_id" id="delete_role_id" value="">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Delete Role</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the role <strong id="delete_role_name"></strong>?</p>
                    <p class="text-danger small"><i class="fas fa-exclamation-triangle"></i> Warning: Any users assigned to this role will be downgraded to the default user role. Associated permissions will also be deleted. This action cannot be undone.</p>
                    <div class="form-group mt-3">
                        <label>Confirm Your Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required placeholder="Enter your password to confirm">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Handle level 1 checkbox click - select/deselect all level 2 and level 3 children
    document.querySelectorAll('.level-1').forEach(function(check) {
        check.addEventListener('change', function() {
            const id = this.getAttribute('data-id');
            const isChecked = this.checked;

            // Select all level 2 children
            document.querySelectorAll('.level-2[data-parent-id="' + id + '"]').forEach(function(child) {
                child.checked = isChecked;
            });

            // Select all level 3 grandchildren
            document.querySelectorAll('.level-3[data-grandparent-id="' + id + '"]').forEach(function(grandchild) {
                grandchild.checked = isChecked;
            });
        });
    });

    // Handle level 2 checkbox click
    document.querySelectorAll('.level-2').forEach(function(check) {
        check.addEventListener('change', function() {
            const id = this.getAttribute('data-id');
            const parentId = this.getAttribute('data-parent-id');
            const isChecked = this.checked;

            // Select all level 3 children
            document.querySelectorAll('.level-3[data-parent-id="' + id + '"]').forEach(function(grandchild) {
                grandchild.checked = isChecked;
            });

            // If checked, ensure level 1 is also checked
            if (isChecked) {
                const parent = document.querySelector('.level-1[data-id="' + parentId + '"]');
                if (parent) parent.checked = true;
            } else {
                // If all level 2 children of this parent are unchecked, uncheck parent (optional logic)
                // For now, keeping it simple: parent stays checked if ANY child or grandchild is checked
            }
        });
    });

    // Handle level 3 checkbox click
    document.querySelectorAll('.level-3').forEach(function(check) {
        check.addEventListener('change', function() {
            const parentId = this.getAttribute('data-parent-id');
            const grandparentId = this.getAttribute('data-grandparent-id');
            const isChecked = this.checked;

            if (isChecked) {
                const parent = document.querySelector('.level-2[data-id="' + parentId + '"]');
                if (parent) parent.checked = true;
                const grandparent = document.querySelector('.level-1[data-id="' + grandparentId + '"]');
                if (grandparent) grandparent.checked = true;
            }
        });
    });

    // Handle Role Deletion Modal Data
    document.querySelectorAll('.delete-role-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('delete_role_id').value = this.getAttribute('data-role-id');
            document.getElementById('delete_role_name').textContent = this.getAttribute('data-role-name');
        });
    });

    // Handle Role Edition Modal Data
    document.querySelectorAll('.edit-role-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_role_id').value = this.getAttribute('data-role-id');
            document.getElementById('edit_role_name').value = this.getAttribute('data-role-name');
            document.getElementById('edit_role_desc').value = this.getAttribute('data-role-desc');
        });
    });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>