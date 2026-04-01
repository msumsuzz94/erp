<?php
/**
 * Multi-Channel Notification Configuration
 * Configure SMS, Email, WhatsApp, and Telegram channels
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $channel = clean_input($_POST['channel'] ?? '');
        
        if ($channel === 'auto_notify') {
            // Save auto-notification settings to sms_settings
            $sale_enabled = isset($_POST['sales_sms_enabled']) ? 1 : 0;
            $warranty_enabled = isset($_POST['warranty_sms_enabled']) ? 1 : 0;
            $sale_template = $_POST['sales_sms_template'] ?? '';
            $warranty_template = $_POST['warranty_sms_template'] ?? '';
            $sale_channels = isset($_POST['sale_channels']) ? json_encode($_POST['sale_channels']) : '["sms"]';
            $warranty_channels = isset($_POST['warranty_channels']) ? json_encode($_POST['warranty_channels']) : '["sms"]';
            
            db_update('sms_settings', [
                'sales_sms_enabled' => $sale_enabled,
                'warranty_sms_enabled' => $warranty_enabled,
                'sales_sms_template' => $sale_template,
                'warranty_sms_template' => $warranty_template,
                'sale_notify_channels' => $sale_channels,
                'warranty_notify_channels' => $warranty_channels
            ], ['id' => 1]);
            $_SESSION['success_message'] = 'Auto-notification settings updated!';
        } else {
            $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
            $config_fields = $_POST['config'] ?? [];
            $config = json_encode($config_fields);
            db_update('channel_settings', ['config' => $config, 'is_enabled' => $is_enabled], ['channel' => $channel]);
            log_activity(get_current_user_id(), 'settings_update', "Updated $channel channel settings");
            $_SESSION['success_message'] = ucfirst($channel) . ' settings updated!';
        }
        
        header("Location: " . BASE_URL . "/modules/notification/sms-config.php");
        exit;
    }
}

// Get all channel settings
$channels = db_query("SELECT * FROM channel_settings ORDER BY FIELD(channel, 'sms','email','whatsapp','telegram')");
$channel_map = [];
foreach ($channels as $ch) {
    $ch['config_data'] = json_decode($ch['config'] ?? '{}', true) ?: [];
    $channel_map[$ch['channel']] = $ch;
}

// Stats
$stats = db_query("SELECT type, COUNT(*) as cnt FROM message_log GROUP BY type");
$stat_map = [];
foreach ($stats as $s) { $stat_map[$s['type']] = $s['cnt']; }

// Auto-notification settings
$auto_settings = db_select_one('sms_settings', ['id' => 1]) ?: [];
$sale_channels = json_decode($auto_settings['sale_notify_channels'] ?? '["sms"]', true) ?: ['sms'];
$warranty_channels = json_decode($auto_settings['warranty_notify_channels'] ?? '["sms"]', true) ?: ['sms'];

$page_title = 'Channel Configuration';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.channel-tab { border: 2px solid var(--border-color); border-radius: 12px; padding: 15px; cursor: pointer; text-align: center; transition: all 0.3s; }
.channel-tab:hover, .channel-tab.active { border-color: var(--primary-color); background: rgba(78,115,223,0.05); }
.channel-tab .icon { font-size: 28px; margin-bottom: 8px; }
.channel-tab .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.channel-panel { display: none; }
.channel-panel.active { display: block; }
.config-field { margin-bottom: 12px; }
.config-field label { font-weight: 600; font-size: 13px; }
.channel-stat { font-size: 11px; color: #666; }
</style>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <h4 class="mb-4"><i class="fas fa-cog text-primary"></i> Notification Channels</h4>

    <!-- Channel Tabs -->
    <div class="row mb-4 g-3">
        <?php
        $tab_info = [
            'sms' => ['fas fa-sms', '#4CAF50', 'SMS Gateway'],
            'email' => ['fas fa-envelope', '#2196F3', 'Email SMTP'],
            'whatsapp' => ['fab fa-whatsapp', '#25D366', 'WhatsApp API'],
            'telegram' => ['fab fa-telegram-plane', '#0088cc', 'Telegram Bot'],
        ];
        foreach ($tab_info as $key => $info):
            $ch = $channel_map[$key] ?? ['is_enabled' => 0];
        ?>
        <div class="col-md-3">
            <div class="channel-tab <?= $key === 'sms' ? 'active' : '' ?>" onclick="showChannel('<?= $key ?>')" id="tab-<?= $key ?>">
                <div class="icon" style="color: <?= $info[1] ?>"><i class="<?= $info[0] ?>"></i></div>
                <h6 class="mb-1"><?= $info[2] ?></h6>
                <span class="status-dot" style="background: <?= $ch['is_enabled'] ? '#4CAF50' : '#ccc' ?>"></span>
                <small><?= $ch['is_enabled'] ? 'Active' : 'Inactive' ?></small>
                <div class="channel-stat"><?= $stat_map[$key] ?? 0 ?> sent</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- SMS Panel -->
    <div class="channel-panel active" id="panel-sms">
        
        <!-- IP Checker Alert -->
        <div class="alert alert-warning d-flex align-items-center mb-4 border-warning">
            <i class="fas fa-globe me-3 fs-3 text-warning"></i>
            <div>
                <strong>Whitelisting IP Address needed:</strong> <br>
                <span>আপনার সার্ভারের বর্তমান পাবলিক IP:</span> 
                <span id="server-ip-display" class="badge bg-dark fs-6 mt-1 ms-1">Checking...</span>
                <button type="button" class="btn btn-sm btn-dark ms-2 mt-1 py-0 px-2" onclick="checkPublicIP()">
                    <i class="fas fa-sync-alt"></i> Check Again
                </button>
                <div class="small mt-1 text-muted">এই IP Address টি কপি করে আপনার SMS Gateway (যেমন BulkSMSBD)-এর Phonebook বা API সেটিংসে Whitelist করুন।</div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header py-3" style="border-left: 4px solid #4CAF50;">
                <h6 class="m-0 font-weight-bold" style="color:#4CAF50;"><i class="fas fa-sms"></i> SMS Gateway Settings</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="channel" value="sms">
                    <?php $c = $channel_map['sms']['config_data'] ?? []; ?>
                    <div class="mb-3">
                        <label class="fw-bold mb-2"><i class="fas fa-plug text-primary"></i> Quick Connect Providers (Click to auto-fill API URL)</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="fillGateway('http://api.greenweb.com.bd/api.php?token={apikey}&to={to}&message={msg}')">Greenweb BD</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="fillGateway('http://bulksmsbd.net/api/smsapi?api_key={apikey}&type=text&number={to}&senderid={senderid}&message={msg}')">BulkSMSBD</button>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="fillGateway('https://api.bulksms.com/v1/messages?to={to}&body={msg}')">BulkSMS.com</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="fillGateway('https://bulksms.teletalk.com.bd/api/action/send-sms?api_key={apikey}&contacts={to}&senderid={senderid}&msg={msg}')">Teletalk BD</button>
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="fillGateway('https://mimsms.com/api/sendsms?api_key={apikey}&type=text&contacts={to}&senderid={senderid}&msg={msg}')">MimSMS</button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 config-field">
                            <label>API URL</label>
                            <input type="url" name="config[api_url]" id="sms_api_url" class="form-control" value="<?= htmlspecialchars($c['api_url'] ?? '') ?>" placeholder="https://api.sms-provider.com/send">
                        </div>
                        <div class="col-md-6 config-field">
                            <label>API Key / Token</label>
                            <input type="text" name="config[api_key]" class="form-control" value="<?= htmlspecialchars($c['api_key'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 config-field">
                            <label>Sender ID</label>
                            <input type="text" name="config[sender_id]" class="form-control" value="<?= htmlspecialchars($c['sender_id'] ?? '') ?>" placeholder="MyBusiness">
                        </div>
                        <div class="col-md-6 config-field">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="is_enabled" id="sms_enabled" <?= ($channel_map['sms']['is_enabled'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="sms_enabled">Enable SMS Channel</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save SMS Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Email Panel -->
    <div class="channel-panel" id="panel-email">
        <div class="card shadow-sm">
            <div class="card-header py-3" style="border-left: 4px solid #2196F3;">
                <h6 class="m-0 font-weight-bold" style="color:#2196F3;"><i class="fas fa-envelope"></i> Email SMTP Settings</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="channel" value="email">
                    <?php $c = $channel_map['email']['config_data'] ?? []; ?>
                    <div class="row">
                        <div class="col-md-6 config-field">
                            <label>SMTP Host</label>
                            <input type="text" name="config[smtp_host]" class="form-control" value="<?= htmlspecialchars($c['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-3 config-field">
                            <label>SMTP Port</label>
                            <input type="number" name="config[smtp_port]" class="form-control" value="<?= htmlspecialchars($c['smtp_port'] ?? '587') ?>">
                        </div>
                        <div class="col-md-3 config-field">
                            <label>Encryption</label>
                            <select name="config[encryption]" class="form-select">
                                <option value="tls" <?= ($c['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= ($c['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                        <div class="col-md-6 config-field">
                            <label>SMTP Username</label>
                            <input type="text" name="config[smtp_user]" class="form-control" value="<?= htmlspecialchars($c['smtp_user'] ?? '') ?>" placeholder="your@email.com">
                        </div>
                        <div class="col-md-6 config-field">
                            <label>SMTP Password</label>
                            <input type="password" name="config[smtp_pass]" class="form-control" value="<?= htmlspecialchars($c['smtp_pass'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 config-field">
                            <label>From Name</label>
                            <input type="text" name="config[from_name]" class="form-control" value="<?= htmlspecialchars($c['from_name'] ?? '') ?>" placeholder="My Business">
                        </div>
                        <div class="col-md-6 config-field">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="is_enabled" id="email_enabled" <?= ($channel_map['email']['is_enabled'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="email_enabled">Enable Email Channel</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Email Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- WhatsApp Panel -->
    <div class="channel-panel" id="panel-whatsapp">
        <div class="card shadow-sm">
            <div class="card-header py-3" style="border-left: 4px solid #25D366;">
                <h6 class="m-0 font-weight-bold" style="color:#25D366;"><i class="fab fa-whatsapp"></i> WhatsApp API Settings</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="channel" value="whatsapp">
                    <?php $c = $channel_map['whatsapp']['config_data'] ?? []; ?>
                    <div class="row">
                        <div class="col-md-6 config-field">
                            <label>API URL</label>
                            <input type="url" name="config[api_url]" class="form-control" value="<?= htmlspecialchars($c['api_url'] ?? '') ?>" placeholder="https://api.whatsapp-provider.com">
                        </div>
                        <div class="col-md-6 config-field">
                            <label>API Key</label>
                            <input type="text" name="config[api_key]" class="form-control" value="<?= htmlspecialchars($c['api_key'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 config-field">
                            <label>Instance ID</label>
                            <input type="text" name="config[instance_id]" class="form-control" value="<?= htmlspecialchars($c['instance_id'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 config-field">
                            <label>Phone Number</label>
                            <input type="text" name="config[phone_number]" class="form-control" value="<?= htmlspecialchars($c['phone_number'] ?? '') ?>" placeholder="+8801XXXXXXXXX">
                        </div>
                        <div class="col-12 config-field">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_enabled" id="wa_enabled" <?= ($channel_map['whatsapp']['is_enabled'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="wa_enabled">Enable WhatsApp Channel</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info small"><i class="fas fa-info-circle"></i> WhatsApp Business API বা 3rd-party provider (WATI, Ultramsg, Chat-API) ব্যবহার করুন।</div>
                    <button type="submit" class="btn" style="background:#25D366;color:#fff;"><i class="fas fa-save"></i> Save WhatsApp Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Telegram Panel -->
    <div class="channel-panel" id="panel-telegram">
        <div class="card shadow-sm">
            <div class="card-header py-3" style="border-left: 4px solid #0088cc;">
                <h6 class="m-0 font-weight-bold" style="color:#0088cc;"><i class="fab fa-telegram-plane"></i> Telegram Bot Settings</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="channel" value="telegram">
                    <?php $c = $channel_map['telegram']['config_data'] ?? []; ?>
                    <div class="row">
                        <div class="col-md-6 config-field">
                            <label>Bot Token</label>
                            <input type="text" name="config[bot_token]" class="form-control" value="<?= htmlspecialchars($c['bot_token'] ?? '') ?>" placeholder="123456:ABC-DEF...">
                            <small class="text-muted">@BotFather থেকে Token নিন</small>
                        </div>
                        <div class="col-md-6 config-field">
                            <label>Default Chat ID / Group ID</label>
                            <input type="text" name="config[chat_id]" class="form-control" value="<?= htmlspecialchars($c['chat_id'] ?? '') ?>" placeholder="-1001234567890">
                            <small class="text-muted">@userinfobot দিয়ে Chat ID বের করুন</small>
                        </div>
                        <div class="col-md-6 config-field">
                            <label>API URL</label>
                            <input type="url" name="config[api_url]" class="form-control" value="<?= htmlspecialchars($c['api_url'] ?? 'https://api.telegram.org') ?>">
                        </div>
                        <div class="col-md-6 config-field">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="is_enabled" id="tg_enabled" <?= ($channel_map['telegram']['is_enabled'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="tg_enabled">Enable Telegram Channel</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info small"><i class="fas fa-info-circle"></i> Telegram Bot ফ্রি এবং দ্রুত। টাস্ক অ্যাসাইনমেন্ট ও ডেইলি রিপোর্টের জন্য আদর্শ।</div>
                    <button type="submit" class="btn" style="background:#0088cc;color:#fff;"><i class="fas fa-save"></i> Save Telegram Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Auto-Notification Settings -->
    <div class="card shadow-sm mt-4" id="autoNotifyCard">
        <div class="card-header py-3" style="border-left: 4px solid #ff9800; background: rgba(255,152,0,0.05);">
            <h6 class="m-0 font-weight-bold" style="color:#ff9800;"><i class="fas fa-bolt"></i> Auto-Notification Settings</h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="channel" value="auto_notify">
                
                <!-- Sale Notification -->
                <div class="p-3 mb-3 rounded" style="background: rgba(25,135,84,0.05); border: 1px solid rgba(25,135,84,0.2);">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="fas fa-shopping-cart text-success"></i> Sale Completion Notification</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="sales_sms_enabled" id="saleToggle" <?= !empty($auto_settings['sales_sms_enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="saleToggle">ON/OFF</label>
                        </div>
                    </div>
                    <small class="text-muted d-block mb-2">সেল করার সাথে সাথে কাস্টমারকে অটো নোটিফিকেশন পাঠাবে</small>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Send Via:</label><br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="sale_channels[]" value="sms" id="sc_sms" <?= in_array('sms', $sale_channels) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sc_sms">📱 SMS</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="sale_channels[]" value="whatsapp" id="sc_wa" <?= in_array('whatsapp', $sale_channels) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sc_wa">💬 WA</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="sale_channels[]" value="telegram" id="sc_tg" <?= in_array('telegram', $sale_channels) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sc_tg">✈️ TG</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="sale_channels[]" value="email" id="sc_em" <?= in_array('email', $sale_channels) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sc_em">📧 Email</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Template:</label>
                            <textarea name="sales_sms_template" class="form-control" rows="2" placeholder="Dear {customer_name}, your purchase of {total_amount}..."><?= htmlspecialchars($auto_settings['sales_sms_template'] ?? 'Dear {customer_name}, your purchase of {total_amount} (Invoice: {invoice_number}) is complete. Thank you!') ?></textarea>
                            <small class="text-muted">Variables: {customer_name}, {invoice_number}, {total_amount}, {paid_amount}, {due_amount}, {invoice_link}</small>
                        </div>
                    </div>
                </div>

                <!-- Warranty Notification -->
                <div class="p-3 mb-3 rounded" style="background: rgba(13,110,253,0.05); border: 1px solid rgba(13,110,253,0.2);">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="fas fa-shield-alt text-primary"></i> Warranty Notification</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="warranty_sms_enabled" id="warrantyToggle" <?= !empty($auto_settings['warranty_sms_enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold" for="warrantyToggle">ON/OFF</label>
                        </div>
                    </div>
                    <small class="text-muted d-block mb-2">ওয়ারেন্টি নোটিফিকেশন — ইচ্ছা করলে বন্ধ/চালু করা যাবে</small>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Send Via:</label><br>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="warranty_channels[]" value="sms" id="wc_sms" <?= in_array('sms', $warranty_channels) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="wc_sms">📱 SMS</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="warranty_channels[]" value="whatsapp" id="wc_wa" <?= in_array('whatsapp', $warranty_channels) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="wc_wa">💬 WA</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="warranty_channels[]" value="telegram" id="wc_tg" <?= in_array('telegram', $warranty_channels) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="wc_tg">✈️ TG</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="warranty_channels[]" value="email" id="wc_em" <?= in_array('email', $warranty_channels) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="wc_em">📧 Email</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Template:</label>
                            <textarea name="warranty_sms_template" class="form-control" rows="2"><?= htmlspecialchars($auto_settings['warranty_sms_template'] ?? 'Dear {customer_name}, your {product_name} (S/N: {serial_number}) warranty expires on {expiry_date}. Visit us for extended warranty.') ?></textarea>
                            <small class="text-muted">Variables: {customer_name}, {product_name}, {serial_number}, {expiry_date}</small>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Save Auto-Notification Settings</button>
            </form>
        </div>
    </div>
</div>

<script>
function checkPublicIP() {
    const display = document.getElementById('server-ip-display');
    if(!display) return;
    display.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    display.className = 'badge bg-secondary fs-6 mt-1 ms-1';
    
    fetch('https://api.ipify.org?format=json')
        .then(response => response.json())
        .then(data => {
            display.innerText = data.ip;
            display.className = 'badge bg-success fs-6 mt-1 ms-1 text-white';
        })
        .catch(err => {
            display.innerText = 'Failed to fetch';
            display.className = 'badge bg-danger fs-6 mt-1 ms-1 text-white';
            console.error(err);
        });
}

// Auto-check on load
document.addEventListener("DOMContentLoaded", function() {
    checkPublicIP();
});

function showChannel(name) {
    document.querySelectorAll('.channel-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.channel-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    document.getElementById('panel-' + name).classList.add('active');
}

function fillGateway(url) {
    const input = document.getElementById('sms_api_url');
    if (input) {
        input.value = url;
    }
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
