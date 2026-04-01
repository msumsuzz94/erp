<?php
/**
 * Marketing API: Send Campaign
 * Processes a campaign target audience, sends messages (simulated or real), and logs results.
 */

// WARNING: For production with large audiences, this should be dispatched to a queue worker. 
// For this MVP, we process synchronously limited to simple direct updates.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/sms_functions.php';

header('Content-Type: application/json');

if (!is_post() || (!is_admin() && !has_role(get_current_user_id(), 'Manager'))) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$name = clean_input($_POST['name'] ?? '');
$template_id = (int)($_POST['template_id'] ?? 0);
$target_type = clean_input($_POST['target_type'] ?? '');
$target_category_id = (int)($_POST['target_category_id'] ?? 0);
$min_due = (float)($_POST['min_due'] ?? 0);

if (!$name || !$template_id || !$target_type) {
    echo json_encode(['status' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $template = db_query_one("SELECT * FROM marketing_templates WHERE id = ?", [$template_id]);
    if (!$template) throw new Exception("Template not found");
    
    // Create Campaign Record
    $campaign_id = db_insert('marketing_campaigns', [
        'name' => $name,
        'template_id' => $template_id,
        'channel' => $template['channel'],
        'target_type' => $target_type,
        'target_category_id' => $target_category_id ?: null,
        'status' => 'sending',
        'created_by' => get_current_user_id()
    ]);
    
    if (!$campaign_id) throw new Exception("Failed to initialize campaign");
    
    // Fetch Audience
    $recipients = []; // Array of [name, contact, vars[]]
    $channel = $template['channel'];
    
    if ($target_type === 'all_customers') {
        $cond = $channel === 'email' ? "email != ''" : "phone != ''";
        $custs = db_query("SELECT name, phone, email, current_balance FROM customers WHERE $cond AND status='active'");
        foreach ($custs as $c) {
            $recipients[] = [
                'name' => $c['name'],
                'contact' => $channel === 'email' ? $c['email'] : $c['phone'],
                'vars' => [
                    '{name}' => $c['name'],
                    '{phone}' => $c['phone'],
                    '{balance}' => format_currency($c['current_balance']),
                    '{store_name}' => BUSINESS_NAME
                ]
            ];
        }
    } elseif ($target_type === 'lead_category') {
        $cond = $channel === 'email' ? "email != ''" : "mobile != ''";
        $leads = db_query("SELECT organization_name, contact_person, mobile, email FROM leads WHERE category_id = ? AND $cond", [$target_category_id]);
        foreach ($leads as $l) {
            $recipients[] = [
                'name' => $l['organization_name'] ?: $l['contact_person'],
                'contact' => $channel === 'email' ? $l['email'] : $l['mobile'],
                'vars' => [
                    '{name}' => $l['contact_person'],
                    '{phone}' => $l['mobile'],
                    '{balance}' => '0.00',
                    '{store_name}' => BUSINESS_NAME
                ]
            ];
        }
    } elseif ($target_type === 'custom') {
        $cond = "current_balance >= $min_due AND status='active'";
        $cond .= $channel === 'email' ? " AND email != ''" : " AND phone != ''";
        $custs = db_query("SELECT name, phone, email, current_balance FROM customers WHERE $cond");
        foreach ($custs as $c) {
            $recipients[] = [
                'name' => $c['name'],
                'contact' => $channel === 'email' ? $c['email'] : $c['phone'],
                'vars' => [
                    '{name}' => $c['name'],
                    '{phone}' => $c['phone'],
                    '{balance}' => format_currency($c['current_balance']),
                    '{store_name}' => BUSINESS_NAME
                ]
            ];
        }
    }
    
    $total = count($recipients);
    db_update('marketing_campaigns', ['total_recipients' => $total], ['id' => $campaign_id]);
    
    // Process Sending (Simulated/Sync)
    $sent = 0;
    $failed = 0;
    
    // Real world implementation would integrate with SMS/Email API here
    // e.g. require_once '../../includes/sms_api.php';
    
    foreach ($recipients as $r) {
        // Build message by replacing variables
        $message = str_replace(array_keys($r['vars']), array_values($r['vars']), $template['body']);
        
        $status = 'sent';
        $error = null;
        
        // --- REAL SENDING VIA GATEWAY ---
        $api_response = send_notification($template['channel'], $r['contact'], $message, $name);
        
        if (!$api_response['status']) { 
            $status = 'failed'; 
            $error = $api_response['message'] ?? 'Unknown error'; 
        }
        
        if ($status === 'sent') {
            $sent++;
        } else {
            $failed++;
        }
        
        db_insert('marketing_campaign_logs', [
            'campaign_id' => $campaign_id,
            'recipient_name' => $r['name'],
            'recipient_contact' => $r['contact'],
            'status' => $status,
            'error_message' => $error,
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Finalize Campaign
    db_update('marketing_campaigns', [
        'status' => 'completed',
        'sent_count' => $sent,
        'failed_count' => $failed
    ], ['id' => $campaign_id]);
    
    echo json_encode([
        'status' => true, 
        'message' => 'Campaign Sent',
        'campaign_id' => $campaign_id,
        'sent' => $sent,
        'failed' => $failed
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
