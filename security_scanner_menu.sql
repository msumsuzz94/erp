-- Security Scanner Menu Entry
-- Run this SQL on your database to add the Security Scanner to the Settings menu

-- First, find the Settings parent menu ID
SET @settings_parent_id = (SELECT id FROM menu_items WHERE slug = 'settings' LIMIT 1);

-- Insert Security Scanner as a child of Settings
INSERT INTO menu_items (name, slug, url, icon, parent_id, sort_order, is_active, created_at)
VALUES ('Security Scanner', 'settings.security', '/modules/settings/security-scanner.php', 
        'fas fa-shield-alt', @settings_parent_id, 99, 1, NOW())
ON DUPLICATE KEY UPDATE name = 'Security Scanner', url = '/modules/settings/security-scanner.php';

-- Grant access to Super Admin (role_id = 1)
SET @security_menu_id = (SELECT id FROM menu_items WHERE slug = 'settings.security' LIMIT 1);
INSERT INTO role_permissions (role_id, menu_item_id)
SELECT 1, @security_menu_id
WHERE @security_menu_id IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM role_permissions WHERE role_id = 1 AND menu_item_id = @security_menu_id);
