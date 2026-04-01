<?php
/**
 * Quotation Status Update Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $quotation_id = (int)$_POST['quotation_id'];
        $status = clean_input($_POST['status']);
        
        $allowed_statuses = ['pending', 'sent', 'accepted', 'rejected', 'converted'];
        
        if (in_array($status, $allowed_statuses)) {
            $result = db_update('quotations', ['status' => $status], ['id' => $quotation_id]);
            
            if ($result) {
                log_activity(get_current_user_id(), 'quotation_status_update', "Updated quotation #$quotation_id status to $status");
                redirect_with_message("quotation-view.php?id=$quotation_id", "Quotation status updated successfully", "success");
            } else {
                redirect_with_message("quotation-view.php?id=$quotation_id", "Failed to update quotation status", "error");
            }
        } else {
            redirect_with_message("quotation-view.php?id=$quotation_id", "Invalid status", "error");
        }
    }
}

redirect('quotations-list.php');
?>
