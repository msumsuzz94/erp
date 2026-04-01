-- =====================================================
-- Add Automatic Attendance Menu Items
-- Adds new menu items for attendance devices and logs
-- =====================================================
-- Get HR parent ID
SET @hr_id = (
        SELECT id
        FROM menu_items
        WHERE slug = 'hr'
        LIMIT 1
    );
-- Insert Attendance Devices menu item  
INSERT INTO `menu_items` (
        name,
        slug,
        icon,
        url,
        parent_id,
        sort_order,
        is_active
    )
VALUES (
        'Attendance Devices',
        'hr.attendance_devices',
        NULL,
        '/modules/hr/attendance-devices.php',
        @hr_id,
        6,
        1
    );
-- Insert Attendance Logs menu item
INSERT INTO `menu_items` (
        name,
        slug,
        icon,
        url,
        parent_id,
        sort_order,
        is_active
    )
VALUES (
        'Attendance Logs',
        'hr.attendance_logs',
        NULL,
        '/modules/hr/attendance-logs.php',
        @hr_id,
        7,
        1
    );
-- Update Attendance (Manual) label to clarify it's manual entry
UPDATE `menu_items`
SET name = 'Attendance (Manual)'
WHERE slug = 'hr.attendance';
-- =====================================================
-- Menu Items Added Successfully
-- =====================================================