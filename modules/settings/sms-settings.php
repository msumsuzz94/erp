<?php
/**
 * SMS Settings Page
 * Configure SMS API and message templates
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$errors = [];
$success_message = '';

// Get current settings
$settings = db_select_one('sms_settings', ['id' => 1]);

// Handle form submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $api_url = clean_input($_POST['api_url']);
        $api_key = clean_input($_POST['api_key']);
        $sender_id = clean_input($_POST['sender_id']);
        $sales_sms_enabled = isset($_POST['sales_sms_enabled']) ? 1 : 0;
        $warranty_sms_enabled = isset($_POST['warranty_sms_enabled']) ? 1 : 0;
        $sales_sms_template = $_POST['sales_sms_template'];
        $warranty_sms_template = $_POST['warranty_sms_template'];

        // Update database
        $update_data = [
            'api_url' => $api_url,
            'api_key' => $api_key,
            'sender_id' => $sender_id,
            'sales_sms_enabled' => $sales_sms_enabled,
            'warranty_sms_enabled' => $warranty_sms_enabled,
            'sales_sms_template' => $sales_sms_template,
            'warranty_sms_template' => $warranty_sms_template
        ];
        
        if (db_update('sms_settings', $update_data, ['id' => 1])) {
            log_activity(get_current_user_id(), 'settings_update', 'Updated SMS settings');
            $success_message = 'SMS settings updated successfully!';
            $settings = db_select_one('sms_settings', ['id' => 1]);
        } else {
            $errors[] = 'Failed to update settings';
        }
    }
}

$page_title = 'SMS Notification Settings';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-sms"></i> SMS Configuration
                </h6>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= $success_message ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary border-bottom pb-2 mb-3">Gateway API Settings</h6>
                            <div class="form-group mb-3">
                                <label>Gateway API URL</label>
                                <input type="text" name="api_url" class="form-control" 
                                       value="<?= htmlspecialchars($settings['api_url'] ?? '') ?>" 
                                       placeholder="https://api.gateway.com/send">
                                <small class="text-muted">The HTTP URL provided by your SMS service provider.</small>
                            </div>
                            <div class="form-group mb-3">
                                <label>API Key / Token</label>
                                <input type="text" name="api_key" class="form-control" 
                                       value="<?= htmlspecialchars($settings['api_key'] ?? '') ?>">
                            </div>
                            <div class="form-group mb-3">
                                <label>Sender ID / Name</label>
                                <input type="text" name="sender_id" class="form-control" 
                                       value="<?= htmlspecialchars($settings['sender_id'] ?? '') ?>">
                                <small class="text-muted">Approved Sender ID (if required by provider).</small>
                            </div>
                            
                            <h6 class="text-primary border-bottom pb-2 mt-4 mb-3">Notification Toggles</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="sales_sms_enabled" id="sales_sms_enabled" 
                                       <?= ($settings['sales_sms_enabled'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sales_sms_enabled">
                                    Enable Sales Completion SMS
                                </label>
                            </div>
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="warranty_sms_enabled" id="warranty_sms_enabled" 
                                       <?= ($settings['warranty_sms_enabled'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="warranty_sms_enabled">
                                    Enable Warranty Expiry Alerts
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-primary border-bottom pb-2 mb-3">Message Templates</h6>
                            <div class="form-group mb-3">
                                <label>Sale Completion Template</label>
                                <textarea name="sales_sms_template" class="form-control" rows="4"><?= htmlspecialchars($settings['sales_sms_template'] ?? '') ?></textarea>
                                <small class="text-muted">
                                    Placeholders: <code>{customer_name}</code>, <code>{invoice_number}</code>, <code>{total_amount}</code>, <code>{invoice_link}</code>
                                </small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label>Warranty Expiry Template</label>
                                <textarea name="warranty_sms_template" class="form-control" rows="4"><?= htmlspecialchars($settings['warranty_sms_template'] ?? '') ?></textarea>
                                <small class="text-muted">
                                    Placeholders: <code>{customer_name}</code>, <code>{product_name}</code>, <code>{serial_number}</code>, <code>{expiry_date}</code>
                                </small>
                            </div>

                            <div class="alert alert-info small mt-4">
                                <strong>Note:</strong> SMS will be sent using a standard HTTP GET/POST request. 
                                Ensure your provider supports this method.
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save SMS Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
