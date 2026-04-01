<?php
/**
 * Delete Staff Member
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Check if staff exists
    $staff = db_select_one('staff', ['id' => $id]);
    
    if ($staff) {
        // Delete staff member
        if (db_delete('staff', ['id' => $id])) {
            // Log activity
            log_activity(get_current_user_id(), 'staff_delete', "Deleted staff member: {$staff['name']} (ID: $id)");
            
            redirect_with_message('staff-list.php', 'Staff member deleted successfully.', 'success');
        } else {
            redirect_with_message('staff-list.php', 'Failed to delete staff member.', 'danger');
        }
    } else {
        redirect_with_message('staff-list.php', 'Staff member not found.', 'danger');
    }
} else {
    redirect_with_message('staff-list.php', 'Invalid request.', 'danger');
}
