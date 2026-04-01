<?php

/**
 * Business Settings Page
 * Manage business information, logo, and display preferences
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$errors = [];

// Get current settings
$settings = db_select_one('business_settings', ['id' => 1]);

// If no settings exist, create default
if (!$settings) {
    db_insert('business_settings', [
        'business_name' => defined('BUSINESS_NAME') ? BUSINESS_NAME : 'My Business',
        'business_phone' => defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '',
        'business_email' => defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : '',
        'business_address' => defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : '',
        'currency' => '৳',
        'invoice_prefix' => defined('INVOICE_PREFIX') ? INVOICE_PREFIX : 'INV-',
        'tax_rate' => defined('DEFAULT_TAX_RATE') ? DEFAULT_TAX_RATE : 0,
        'display_in_menu' => 'name',
        'cache_clear_interval' => 0
    ]);
    $settings = db_select_one('business_settings', ['id' => 1]);
}

// Handle form submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $business_name = clean_input($_POST['business_name']);
        $business_phone = clean_input($_POST['business_phone']);
        $business_email = clean_input($_POST['business_email']);
        $business_address = clean_input($_POST['business_address']);
        $currency = clean_input($_POST['currency']);
        $invoice_prefix = clean_input($_POST['invoice_prefix']);
        $tax_rate = (float)$_POST['tax_rate'];
        $display_in_menu = clean_input($_POST['display_in_menu']);
        $footer_copyright_text = clean_input($_POST['footer_copyright_text']);
        $cache_clear_interval = (int)$_POST['cache_clear_interval'];

        // Validation
        if (empty($business_name)) {
            $errors[] = 'Business name is required';
        }

        // Handle logo upload
        $logo_path = $settings['business_logo'] ?? null;

        if (isset($_FILES['business_logo']) && $_FILES['business_logo']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/business/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['business_logo']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

            if (!in_array($file_ext, $allowed_ext)) {
                $errors[] = 'Invalid logo format. Only JPG, PNG, GIF, and SVG are allowed.';
            } elseif ($_FILES['business_logo']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Logo size should not exceed 5MB';
            } else {
                $new_filename = 'logo_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['business_logo']['tmp_name'], $upload_path)) {
                    // Delete old logo if exists
                    if (!empty($settings['business_logo']) && file_exists(__DIR__ . '/../../' . $settings['business_logo'])) {
                        unlink(__DIR__ . '/../../' . $settings['business_logo']);
                    }
                    $logo_path = 'uploads/business/' . $new_filename;
                } else {
                    $errors[] = 'Failed to upload logo';
                }
            }
        }

        // Update database if no errors
        if (empty($errors)) {
            $update_data = [
                'business_name' => $business_name,
                'business_phone' => $business_phone,
                'business_email' => $business_email,
                'business_address' => $business_address,
                'currency' => $currency,
                'invoice_prefix' => $invoice_prefix,
                'tax_rate' => $tax_rate,
                'business_logo' => $logo_path,
                'display_in_menu' => $display_in_menu,
                'footer_copyright_text' => $footer_copyright_text,
                'cache_clear_interval' => $cache_clear_interval
            ];

            if (db_update('business_settings', $update_data, ['id' => 1])) {
                // Sync with invoice_settings as well for total compatibility
                db_update('invoice_settings', ['default_tax_rate' => $tax_rate], ['id' => 1]);
                
                log_activity(get_current_user_id(), 'settings_update', 'Updated business settings');
                redirect_with_message('business-settings.php', 'Business settings updated successfully!', 'success');
            } else {
                $errors[] = 'Failed to update settings';
            }
        }
    }
}

$page_title = 'Business Settings';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <!-- Main Settings Form -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-building"></i> Business Information
                </h6>
            </div>
            <div class="card-body">


                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Business Name <span class="text-danger">*</span></label>
                                <input type="text" name="business_name" class="form-control"
                                    value="<?= htmlspecialchars($settings['business_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Business Phone</label>
                                <input type="text" name="business_phone" class="form-control"
                                    value="<?= htmlspecialchars($settings['business_phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Business Email</label>
                                <input type="email" name="business_email" class="form-control"
                                    value="<?= htmlspecialchars($settings['business_email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Currency</label>
                                <select name="currency" class="form-control">
                                    <?php $current_curr = defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : ($settings['currency'] ?? '$'); 
                                          $current_curr = ($current_curr === '&#2547;') ? '৳' : $current_curr; 
                                    ?>
                                    <option value="$" <?= $current_curr == '$' ? 'selected' : '' ?>>$ - US Dollar</option>
                                    <option value="৳" <?= $current_curr == '৳' ? 'selected' : '' ?>>৳ - Bangladeshi Taka</option>
                                    <option value="€" <?= $current_curr == '€' ? 'selected' : '' ?>>€ - Euro</option>
                                    <option value="£" <?= $current_curr == '£' ? 'selected' : '' ?>>£ - British Pound</option>
                                    <option value="¥" <?= $current_curr == '¥' ? 'selected' : '' ?>>¥ - Japanese Yen</option>
                                    <option value="₹" <?= $current_curr == '₹' ? 'selected' : '' ?>>₹ - Indian Rupee</option>
                                    <option value="₨" <?= $current_curr == '₨' ? 'selected' : '' ?>>₨ - Pakistani Rupee</option>
                                    <option value="د.إ" <?= $current_curr == 'د.إ' ? 'selected' : '' ?>>د.إ - UAE Dirham</option>
                                    <option value="﷼" <?= $current_curr == '﷼' ? 'selected' : '' ?>>﷼ - Saudi Riyal</option>
                                    <option value="₦" <?= $current_curr == '₦' ? 'selected' : '' ?>>₦ - Nigerian Naira</option>
                                    <option value="R" <?= $current_curr == 'R' ? 'selected' : '' ?>>R - South African Rand</option>
                                    <option value="CHF" <?= $current_curr == 'CHF' ? 'selected' : '' ?>>CHF - Swiss Franc</option>
                                    <option value="CAD" <?= $current_curr == 'CAD' ? 'selected' : '' ?>>CAD - Canadian Dollar</option>
                                    <option value="AUD" <?= $current_curr == 'AUD' ? 'selected' : '' ?>>AUD - Australian Dollar</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label>Business Address</label>
                                <textarea name="business_address" class="form-control" rows="2"><?= htmlspecialchars($settings['business_address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Invoice Prefix</label>
                                <input type="text" name="invoice_prefix" class="form-control"
                                    value="<?= htmlspecialchars($settings['invoice_prefix'] ?? 'INV-') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Tax Rate (%)</label>
                                <input type="number" name="tax_rate" class="form-control" step="0.01"
                                    value="<?= $settings['tax_rate'] ?? 0 ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Auto Clear Cache Interval</label>
                                <select name="cache_clear_interval" class="form-control">
                                    <option value="0" <?= ($settings['cache_clear_interval'] ?? 0) == 0 ? 'selected' : '' ?>>Disabled</option>
                                    <option value="1" <?= ($settings['cache_clear_interval'] ?? 0) == 1 ? 'selected' : '' ?>>Every 1 Minute</option>
                                    <option value="5" <?= ($settings['cache_clear_interval'] ?? 0) == 5 ? 'selected' : '' ?>>Every 5 Minutes</option>
                                    <option value="15" <?= ($settings['cache_clear_interval'] ?? 0) == 15 ? 'selected' : '' ?>>Every 15 Minutes</option>
                                    <option value="30" <?= ($settings['cache_clear_interval'] ?? 0) == 30 ? 'selected' : '' ?>>Every 30 Minutes</option>
                                    <option value="60" <?= ($settings['cache_clear_interval'] ?? 0) == 60 ? 'selected' : '' ?>>Every 1 Hour</option>
                                </select>
                                <small class="form-text text-muted">Time between automatic cache clearing intervals.</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <h6 class="text-primary mb-3"><i class="fas fa-image"></i> Branding & Display</h6>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label>Business Logo</label>
                                <?php if (!empty($settings['business_logo'])): ?>
                                    <div class="mb-2">
                                        <img src="../../<?= htmlspecialchars($settings['business_logo']) ?>"
                                            class="img-thumbnail"
                                            style="max-width: 200px; max-height: 100px; object-fit: contain;"
                                            alt="Current Logo">
                                        <p class="text-muted small mb-0">Current Logo</p>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="business_logo" class="form-control-file" accept="image/*">
                                <small class="form-text text-muted">Recommended: 200x60px, PNG with transparent background. Max size: 5MB</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label>Display in Menu Bar <span class="text-danger">*</span></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="display_in_menu"
                                        id="display_name" value="name"
                                        <?= ($settings['display_in_menu'] ?? 'name') == 'name' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="display_name">
                                        <i class="fas fa-font"></i> Show Business Name
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="display_in_menu"
                                        id="display_logo" value="logo"
                                        <?= ($settings['display_in_menu'] ?? 'name') == 'logo' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="display_logo">
                                        <i class="fas fa-image"></i> Show Business Logo
                                    </label>
                                </div>
                                <small class="form-text text-muted">Choose what to display in the top menu bar</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-3">
                                <label>Footer Copyright Text</label>
                                <textarea name="footer_copyright_text" class="form-control" rows="2"
                                    placeholder="e.g. Copyright &copy; 2024 My Business. All rights reserved."><?= htmlspecialchars($settings['footer_copyright_text'] ?? '') ?></textarea>
                                <small class="form-text text-muted">This text will appear in the footer of every page. Leave empty for default.</small>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Panel -->
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-eye"></i> Menu Bar Preview
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Preview:</strong> How it will appear in the menu bar
                </div>

                <div class="menu-preview" style="background: #4e73df; padding: 15px; border-radius: 5px; color: white;">
                    <?php if (($settings['display_in_menu'] ?? 'name') == 'logo' && $settings['business_logo']): ?>
                        <img src="../../<?= htmlspecialchars($settings['business_logo']) ?>"
                            style="max-height: 40px; max-width: 180px; object-fit: contain;"
                            alt="Logo Preview">
                    <?php else: ?>
                        <h5 class="mb-0" style="color: white;">
                            <i class="fas fa-store"></i> <?= htmlspecialchars($settings['business_name'] ?? 'Your Business Name') ?>
                        </h5>
                        <small style="color: rgba(255,255,255,0.8);"><?= APP_VERSION ?></small>
                    <?php endif; ?>
                </div>

                <div class="mt-3">
                    <h6>Current Settings:</h6>
                    <ul class="list-unstyled">
                        <li><strong>Name:</strong> <?= htmlspecialchars($settings['business_name'] ?? 'Not set') ?></li>
                        <li><strong>Phone:</strong> <?= htmlspecialchars($settings['business_phone'] ?? 'Not set') ?></li>
                        <li><strong>Email:</strong> <?= htmlspecialchars($settings['business_email'] ?? 'Not set') ?></li>
                        <li><strong>Currency:</strong> <?= defined('APP_CURRENCY_SYMBOL') ? APP_CURRENCY_SYMBOL : htmlspecialchars($settings['currency'] ?? '$') ?></li>
                        <li><strong>Display:</strong>
                            <span class="badge badge-<?= ($settings['display_in_menu'] ?? 'name') == 'logo' ? 'primary' : 'secondary' ?>">
                                <?= ucfirst($settings['display_in_menu'] ?? 'name') ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
