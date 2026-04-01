<?php
/**
 * Multi-Channel Notification Helper Functions
 * Supports: SMS, Email, WhatsApp, Telegram
 */

/**
 * Get channel settings
 */
function get_channel_config($channel) {
    $ch = db_query_one("SELECT * FROM channel_settings WHERE channel = ?", [$channel]);
    if ($ch) {
        $ch['config_data'] = json_decode($ch['config'] ?? '{}', true) ?: [];
    }
    return $ch;
}

/**
 * Get auto-notification settings
 */
function get_auto_notification_settings() {
    $settings = db_select_one('sms_settings', ['id' => 1]);
    return $settings ?: [];
}

/**
 * Core function to send SMS via configured Gateway
 */
function send_sms($mobile, $message) {
    $ch = get_channel_config('sms');
    if (!$ch || !$ch['is_enabled']) return ['status' => false, 'message' => 'SMS channel not enabled'];
    
    $config = $ch['config_data'];
    if (empty($config['api_url']) || empty($config['api_key'])) {
        // Fallback to old sms_settings
        $settings = db_select_one('sms_settings', ['id' => 1]);
        if (!$settings || empty($settings['api_url'])) {
            return ['status' => false, 'message' => 'SMS Gateway not configured'];
        }
        $config = ['api_url' => $settings['api_url'], 'api_key' => $settings['api_key'], 'sender_id' => $settings['sender_id'] ?? ''];
    }

    $message_encoded = urlencode($message);
    $api_url = $config['api_url'];
    $api_key = $config['api_key'];
    $sender_id = $config['sender_id'] ?? '';

    // Replace all known placeholder formats:
    // {to}, {msg}, {apikey}, {senderid} — bracket style
    // Receiver, TestSMS, YourAPIKey — BulkSMSBD/common literal style
    $search_patterns  = ['{to}', '{msg}', '{apikey}', '{senderid}', 'Receiver', 'TestSMS'];
    $replace_values   = [$mobile, $message_encoded, $api_key, $sender_id, $mobile, $message_encoded];

    $final_url = str_replace($search_patterns, $replace_values, $api_url);

    // If no phone number placeholder was found at all, append as query params (fallback)
    if (strpos($api_url, '{to}') === false && strpos($api_url, 'Receiver') === false && strpos($api_url, 'number=') === false) {
        $sep = (strpos($api_url, '?') === false) ? '?' : '&';
        $final_url .= "{$sep}to={$mobile}&message={$message_encoded}&apikey={$api_key}";
        if ($sender_id) $final_url .= "&senderid={$sender_id}";
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $final_url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    return $http_code == 200 
        ? ['status' => true, 'message' => 'SMS sent', 'response' => $response]
        : ['status' => false, 'message' => "Gateway error (HTTP $http_code)", 'response' => $response];
}

/**
 * Send WhatsApp message via configured API
 */
function send_whatsapp($mobile, $message) {
    $ch = get_channel_config('whatsapp');
    if (!$ch || !$ch['is_enabled']) return ['status' => false, 'message' => 'WhatsApp channel not enabled'];
    
    $config = $ch['config_data'];
    if (empty($config['api_url']) || empty($config['api_key'])) {
        return ['status' => false, 'message' => 'WhatsApp API not configured'];
    }

    $curl = curl_init($config['api_url']);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $config['api_key']],
        CURLOPT_POSTFIELDS => json_encode([
            'phone' => $mobile,
            'message' => $message,
            'instance_id' => $config['instance_id'] ?? ''
        ]),
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    return $http_code == 200
        ? ['status' => true, 'message' => 'WhatsApp sent', 'response' => $response]
        : ['status' => false, 'message' => "WhatsApp error (HTTP $http_code)", 'response' => $response];
}

/**
 * Send Telegram message via Bot API
 */
function send_telegram($chat_id, $message) {
    $ch = get_channel_config('telegram');
    if (!$ch || !$ch['is_enabled']) return ['status' => false, 'message' => 'Telegram channel not enabled'];
    
    $config = $ch['config_data'];
    if (empty($config['bot_token'])) {
        return ['status' => false, 'message' => 'Telegram Bot not configured'];
    }

    $api_url = ($config['api_url'] ?? 'https://api.telegram.org') . '/bot' . $config['bot_token'] . '/sendMessage';
    
    $curl = curl_init($api_url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['chat_id' => $chat_id ?: $config['chat_id'], 'text' => $message, 'parse_mode' => 'HTML'],
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    return $http_code == 200
        ? ['status' => true, 'message' => 'Telegram sent', 'response' => $response]
        : ['status' => false, 'message' => "Telegram error (HTTP $http_code)", 'response' => $response];
}

/**
 * Send Email via SMTP
 */
function send_email_notification($to_email, $subject, $message) {
    $ch = get_channel_config('email');
    if (!$ch || !$ch['is_enabled']) return ['status' => false, 'message' => 'Email channel not enabled'];
    
    $config = $ch['config_data'];
    if (empty($config['smtp_host']) || empty($config['smtp_user'])) {
        return ['status' => false, 'message' => 'Email SMTP not configured'];
    }

    $from_name = $config['from_name'] ?? 'ERP System';
    $from_email = $config['smtp_user'];
    
    $headers = "From: {$from_name} <{$from_email}>\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    $result = @mail($to_email, $subject, $message, $headers);

    return $result
        ? ['status' => true, 'message' => 'Email sent']
        : ['status' => false, 'message' => 'Email sending failed'];
}

/**
 * Universal send function — dispatches to correct channel
 * @param string $channel 'sms', 'whatsapp', 'telegram', 'email'
 * @param string $recipient Phone number or email or chat_id
 * @param string $message Message body
 * @param string $subject Email subject (only for email)
 */
function send_notification($channel, $recipient, $message, $subject = 'Notification') {
    switch ($channel) {
        case 'sms':      return send_sms($recipient, $message);
        case 'whatsapp':  return send_whatsapp($recipient, $message);
        case 'telegram':  return send_telegram($recipient, $message);
        case 'email':     return send_email_notification($recipient, $subject, $message);
        default:          return ['status' => false, 'message' => 'Unknown channel: ' . $channel];
    }
}

/**
 * Auto-send sale notification (multi-channel)
 * Called after sale is completed in POS
 */
function send_sale_notification($sale_id) {
    $settings = get_auto_notification_settings();
    if (empty($settings['sales_sms_enabled'])) return;

    $sale = db_query_one("SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email
                         FROM sales s 
                         LEFT JOIN customers c ON s.customer_id = c.id 
                         WHERE s.id = ?", [$sale_id]);

    if (!$sale || empty($sale['customer_phone'])) return;

    // Build message from template or default
    $template = $settings['sales_sms_template'] ?? 'Dear {customer_name}, your purchase of {total_amount} (Invoice: {invoice_number}) is complete. Thank you!';
    $invoice_link = BASE_URL . "/modules/sales/invoice-print.php?id=" . $sale_id;

    $message = str_replace(
        ['{customer_name}', '{invoice_number}', '{total_amount}', '{paid_amount}', '{due_amount}', '{invoice_link}'],
        [
            $sale['customer_name'] ?? 'Customer',
            $sale['invoice_number'],
            format_currency($sale['total_amount']),
            format_currency($sale['paid_amount']),
            format_currency($sale['due_amount']),
            $invoice_link
        ],
        $template
    );

    // Determine which channel(s) to send via
    $channels = !empty($settings['sale_notify_channels']) ? json_decode($settings['sale_notify_channels'], true) : ['sms'];
    
    $results = [];
    foreach ($channels as $channel) {
        $recipient = ($channel === 'email') ? ($sale['customer_email'] ?? '') : $sale['customer_phone'];
        if (empty($recipient)) continue;

        $result = send_notification($channel, $recipient, $message, 'Sale Confirmation - ' . $sale['invoice_number']);
        $results[$channel] = $result;

        // Log to message_log
        db_insert('message_log', [
            'customer_id' => $sale['customer_id'],
            'phone' => $sale['customer_phone'],
            'message' => $message,
            'type' => $channel,
            'category' => 'sales',
            'status' => $result['status'] ? 'sent' : 'failed',
            'sent_at' => date('Y-m-d H:i:s'),
            'created_by' => get_current_user_id()
        ]);
    }
    return $results;
}

/**
 * Auto-send warranty notification (multi-channel, toggleable)
 */
function send_warranty_notification($serial_id) {
    $settings = get_auto_notification_settings();
    if (empty($settings['warranty_sms_enabled'])) return; // WARRANTY TOGGLE — if off, skip

    $sql = "SELECT ps.*, p.name as product_name, c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                   DATE_ADD(ps.sale_date, INTERVAL ps.warranty_months MONTH) as expiry_date
            FROM product_serials ps
            JOIN products p ON ps.product_id = p.id
            JOIN sales s ON ps.sale_id = s.id
            JOIN customers c ON s.customer_id = c.id
            WHERE ps.id = ?";
    
    $data = db_query_one($sql, [$serial_id]);
    if (!$data || empty($data['customer_phone'])) return;

    $template = $settings['warranty_sms_template'] ?? 'Dear {customer_name}, your {product_name} (S/N: {serial_number}) warranty expires on {expiry_date}. Visit us for extended warranty.';
    $message = str_replace(
        ['{customer_name}', '{product_name}', '{serial_number}', '{expiry_date}'],
        [$data['customer_name'], $data['product_name'], $data['serial_number'], format_date($data['expiry_date'])],
        $template
    );

    $channels = !empty($settings['warranty_notify_channels']) ? json_decode($settings['warranty_notify_channels'], true) : ['sms'];
    
    foreach ($channels as $channel) {
        $recipient = ($channel === 'email') ? ($data['customer_email'] ?? '') : $data['customer_phone'];
        if (empty($recipient)) continue;

        $result = send_notification($channel, $recipient, $message, 'Warranty Info - ' . $data['product_name']);
        
        db_insert('message_log', [
            'customer_id' => null, 'phone' => $data['customer_phone'],
            'message' => $message, 'type' => $channel, 'category' => 'warranty',
            'status' => $result['status'] ? 'sent' : 'failed',
            'sent_at' => date('Y-m-d H:i:s'),
            'created_by' => get_current_user_id()
        ]);
    }
}

/**
 * Legacy compatibility — calls new multi-channel function
 */
function send_sale_sms($sale_id) {
    return send_sale_notification($sale_id);
}

function send_warranty_sms($serial_id) {
    return send_warranty_notification($serial_id);
}
?>
