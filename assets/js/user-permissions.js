/**
 * User Permissions Management JavaScript
 * Handles dynamic loading and selection of menu permissions
 */

document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role_id');
    const permissionsContainer = document.getElementById('permissions-container');

    if (roleSelect && permissionsContainer) {
        // Load permissions when role changes
        roleSelect.addEventListener('change', function () {
            const roleId = this.value;

            if (roleId) {
                loadRolePermissions(roleId);
            } else {
                permissionsContainer.innerHTML = '<p class="text-muted">Please select a role first</p>';
            }
        });

        // Load permissions on page load if role is already selected
        if (roleSelect.value) {
            loadRolePermissions(roleSelect.value);
        }
    }
});

/**
 * Load menu permissions for a role via AJAX
 */
function loadRolePermissions(roleId) {
    const container = document.getElementById('permissions-container');
    container.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading permissions...</p>';

    fetch(`../../api/get-role-permissions.php?role_id=${roleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderPermissions(data.menus, data.permissions);
            } else {
                container.innerHTML = `<div class="alert alert-danger">${data.error || 'Failed to load permissions'}</div>`;
            }
        })
        .catch(error => {
            container.innerHTML = `<div class="alert alert-danger">Error loading permissions: ${error.message}</div>`;
        });
}

/**
 * Render permission checkboxes
 */
function renderPermissions(menus, permissions) {
    const container = document.getElementById('permissions-container');
    let html = '<div class="permissions-grid">';

    // Create select all/none buttons
    html += `
        <div class="mb-3">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPermissions()">
                <i class="fas fa-check-square"></i> Select All
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllPermissions()">
                <i class="fas fa-square"></i> Deselect All
            </button>
        </div>
    `;

    // Loop through parent menus
    for (const menuId in menus) {
        const menu = menus[menuId];
        const isChecked = permissions.includes(parseInt(menuId));

        html += `
            <div class="permission-group mb-4">
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" 
                           class="custom-control-input parent-checkbox" 
                           id="menu_${menu.id}" 
                           name="menu_permissions[]" 
                           value="${menu.id}"
                           ${isChecked ? 'checked' : ''}
                           onchange="toggleChildrenCheckboxes(this)">
                    <label class="custom-control-label font-weight-bold" for="menu_${menu.id}">
                        <i class="${menu.icon}"></i> ${menu.name}
                    </label>
                </div>
        `;

        // Add children if exist
        if (menu.children && menu.children.length > 0) {
            html += '<div class="permission-children ml-4">';
            menu.children.forEach(child => {
                const childChecked = permissions.includes(parseInt(child.id));
                html += `
                    <div class="custom-control custom-checkbox mb-1">
                        <input type="checkbox" 
                               class="custom-control-input child-checkbox" 
                               id="menu_${child.id}" 
                               name="menu_permissions[]" 
                               value="${child.id}"
                               data-parent="${menu.id}"
                               ${childChecked ? 'checked' : ''}
                               onchange="updateParentCheckbox(this)">
                        <label class="custom-control-label" for="menu_${child.id}">
                            ${child.name}
                        </label>
                    </div>
                `;
            });
            html += '</div>';
        }

        html += '</div>';
    }

    html += '</div>';
    container.innerHTML = html;
}

/**
 * Select all permission checkboxes
 */
function selectAllPermissions() {
    document.querySelectorAll('input[name="menu_permissions[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
}

/**
 * Deselect all permission checkboxes
 */
function deselectAllPermissions() {
    document.querySelectorAll('input[name="menu_permissions[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
}

/**
 * When parent is toggled, toggle all children
 */
function toggleChildrenCheckboxes(parentCheckbox) {
    const parentId = parentCheckbox.value;
    const isChecked = parentCheckbox.checked;

    document.querySelectorAll(`input[data-parent="${parentId}"]`).forEach(child => {
        child.checked = isChecked;
    });
}

/**
 * When child is toggled, update parent checkbox state
 */
function updateParentCheckbox(childCheckbox) {
    const parentId = childCheckbox.dataset.parent;
    const parentCheckbox = document.getElementById(`menu_${parentId}`);

    if (!parentCheckbox) return;

    // Get all children of this parent
    const children = document.querySelectorAll(`input[data-parent="${parentId}"]`);
    const checkedChildren = Array.from(children).filter(ch => ch.checked);

    // If any child is checked, check the parent
    parentCheckbox.checked = checkedChildren.length > 0;
}
