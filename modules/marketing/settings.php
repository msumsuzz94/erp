<?php
/**
 * Marketing Settings
 * Reads from the same channel_settings table used by Notification module
 * This ensures a single source of truth for SMS/Email/WhatsApp/Telegram configuration
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if (!is_admin()) {
    redirect_with_message('../../index.php', 'Only Admin can access marketing settings.', 'danger');
}

// Read channel settings from same table as Notification module
$channels = db_query("SELECT * FROM channel_settings ORDER BY FIELD(channel, 'sms','email','whatsapp','telegram')");
$channel_map = [];
foreach ($channels as $ch) {
    $ch['config_data'] = json_decode($ch['config'] ?? '{}', true) ?: [];
    $channel_map[$ch['channel']] = $ch;
}

$page_title = 'Marketing Settings';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        <strong>Shared Settings:</strong> Marketing uses the same channel configuration as Notification. 
        To update SMS, Email, WhatsApp, or Telegram settings, go to 
        <a href="../notification/sms-config.php" class="alert-link"><i class="fas fa-cog"></i> Notification &rarr; Channel Configuration</a>.
    </div>

    <div class="row g-3 mb-4">
        <?php
        $tab_info = [
            'sms' => ['fas fa-sms', '#4CAF50', 'SMS Gateway', 'Send SMS campaigns via API gateway'],
            'email' => ['fas fa-envelope', '#2196F3', 'Email SMTP', 'Send email campaigns via SMTP'],
            'whatsapp' => ['fab fa-whatsapp', '#25D366', 'WhatsApp API', 'Send WhatsApp messages via API'],
            'telegram' => ['fab fa-telegram-plane', '#0088cc', 'Telegram Bot', 'Send Telegram messages via Bot'],
        ];
        foreach ($tab_info as $key => $info):
            $ch = $channel_map[$key] ?? ['is_enabled' => 0, 'config_data' => []];
            $configured = !empty($ch['config_data']) && count(array_filter($ch['config_data'])) > 0;
        ?>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <div style="font-size: 36px; color: <?= $info[1] ?>;" class="mb-2">
                        <i class="<?= $info[0] ?>"></i>
                    </div>
                    <h6 class="fw-bold"><?= $info[2] ?></h6>
                    <p class="text-muted small mb-2"><?= $info[3] ?></p>
                    
                    <?php if ($ch['is_enabled'] ?? 0): ?>
                        <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary"><i class="fas fa-times-circle"></i> Inactive</span>
                    <?php endif; ?>
                    
                    <?php if ($configured): ?>
                        <span class="badge bg-info"><i class="fas fa-cog"></i> Configured</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle"></i> Not Configured</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Links -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-rocket"></i> Marketing Quick Actions</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="templates.php" class="btn btn-outline-primary w-100 py-3">
                        <i class="fas fa-file-alt fa-2x d-block mb-2"></i> Message Templates
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="campaign-create.php" class="btn btn-outline-success w-100 py-3">
                        <i class="fas fa-bullhorn fa-2x d-block mb-2"></i> Create Campaign
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="campaigns.php" class="btn btn-outline-info w-100 py-3">
                        <i class="fas fa-chart-bar fa-2x d-block mb-2"></i> Campaign History
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="sms-send.php" class="btn btn-outline-warning w-100 py-3">
                        <i class="fas fa-mobile-alt fa-2x d-block mb-2"></i> Quick SMS Send
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Configure Button -->
    <div class="text-center">
        <a href="../notification/sms-config.php" class="btn btn-primary btn-lg">
            <i class="fas fa-cog"></i> Go to Channel Configuration
        </a>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
