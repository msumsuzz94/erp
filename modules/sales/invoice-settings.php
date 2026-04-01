<?php
/**
 * Invoice Settings Page
 * Configure invoice design and customization
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle form submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $settings_data = [
            'company_name' => clean_input($_POST['company_name']),
            'company_name_font_size' => (int)$_POST['company_name_font_size'],
            'company_slogan' => clean_input($_POST['company_slogan']),
            'company_slogan_font_size' => (int)$_POST['company_slogan_font_size'],
            'company_slogan_color' => clean_input($_POST['company_slogan_color']),
            'company_address' => clean_input($_POST['company_address']),
            'company_phone' => clean_input($_POST['company_phone']),
            'company_email' => clean_input($_POST['company_email']),
            'company_website' => clean_input($_POST['company_website']),
            'tax_number' => clean_input($_POST['tax_number']),
            'invoice_prefix' => clean_input($_POST['invoice_prefix']),
            'invoice_number_digits' => (int)$_POST['invoice_number_digits'],
            'invoice_number_format' => clean_input($_POST['invoice_number_format']),
            'font_size' => clean_input($_POST['font_size']),
            'template_style' => clean_input($_POST['template_style']),
            'invoice_margin' => clean_input($_POST['invoice_margin']),
            'show_logo' => isset($_POST['show_logo']) ? 1 : 0,
            'show_company_info' => isset($_POST['show_company_info']) ? 1 : 0,
            'show_customer_info' => isset($_POST['show_customer_info']) ? 1 : 0,
            'show_payment_info' => isset($_POST['show_payment_info']) ? 1 : 0,
            'show_terms' => isset($_POST['show_terms']) ? 1 : 0,
            'show_print_datetime' => isset($_POST['show_print_datetime']) ? 1 : 0,
            'terms_and_conditions' => clean_input($_POST['terms_and_conditions']),
            'invoice_note' => clean_input($_POST['invoice_note']),
            'header_color' => clean_input($_POST['header_color']),
            'text_color' => clean_input($_POST['text_color']),
            'footer_text' => clean_input($_POST['footer_text']),
            'developed_by' => clean_input($_POST['developed_by']),
            'default_tax_rate' => floatval($_POST['default_tax_rate'] ?? 0),
            'show_slogan' => isset($_POST['show_slogan']) ? 1 : 0,
            'show_signature_on_invoice' => isset($_POST['show_signature_on_invoice']) ? 1 : 0,
            'author_signature_label' => clean_input($_POST['author_signature_label'] ?? 'Authorized Signature')
        ];
        
        // Handle Logo Upload
        $upload_dir = __DIR__ . '/../../uploads/invoice/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        if (!empty($_FILES['company_logo']['name']) && $_FILES['company_logo']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
                $filename = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_dir . $filename)) {
                    $settings_data['company_logo'] = 'uploads/invoice/' . $filename;
                }
            }
        }
        
        // Handle Signature Upload
        if (!empty($_FILES['invoice_signature']['name']) && $_FILES['invoice_signature']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['invoice_signature']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'])) {
                $filename = 'signature_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['invoice_signature']['tmp_name'], $upload_dir . $filename)) {
                    $settings_data['invoice_signature'] = 'uploads/invoice/' . $filename;
                }
            }
        }
        
        // Remove logo if requested
        if (isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
            $settings_data['company_logo'] = '';
        }
        // Remove signature if requested
        if (isset($_POST['remove_signature']) && $_POST['remove_signature'] == '1') {
            $settings_data['invoice_signature'] = '';
        }
        
        $existing = db_select_one('invoice_settings', ['id' => 1]);
        if ($existing) {
            db_update('invoice_settings', $settings_data, ['id' => 1]);
            // Sync with business_settings.tax_rate for global consistency
            db_update('business_settings', ['tax_rate' => $settings_data['default_tax_rate']], ['id' => 1]);
            $message = 'Invoice settings updated successfully';
        } else {
            db_insert('invoice_settings', $settings_data);
            // Sync with business_settings.tax_rate for global consistency
            db_update('business_settings', ['tax_rate' => $settings_data['default_tax_rate']], ['id' => 1]);
            $message = 'Invoice settings created successfully';
        }
        
        log_activity(get_current_user_id(), 'invoice_settings_update', 'Updated invoice settings');
        redirect_with_message($_SERVER['PHP_SELF'], $message, 'success');
    }
}

// Get current settings
$settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$settings) {
    $settings = [
        'company_name' => BUSINESS_NAME, 'company_name_font_size' => 28,
        'company_slogan' => '', 'company_slogan_font_size' => 14, 'company_slogan_color' => '#E67E22',
        'company_address' => BUSINESS_ADDRESS, 'company_phone' => BUSINESS_PHONE,
        'company_email' => BUSINESS_EMAIL, 'company_website' => '', 'tax_number' => '',
        'company_logo' => '', 'invoice_signature' => '',
        'invoice_prefix' => 'INV-', 'invoice_number_digits' => 6, 'invoice_number_format' => '{PREFIX}{NUMBER}',
        'font_size' => 'medium', 'template_style' => 'classic', 'invoice_margin' => '1cm',
        'show_logo' => 1, 'show_company_info' => 1, 'show_customer_info' => 1,
        'show_payment_info' => 1, 'show_terms' => 1, 'show_print_datetime' => 1,
        'terms_and_conditions' => 'Payment is due within 15 days',
        'invoice_note' => 'Thank you for your business!',
        'header_color' => '#4e73df', 'text_color' => '#000000',
        'footer_text' => 'This is a computer generated invoice', 'developed_by' => 'CITNBD'
    ];
}

$page_title = 'Invoice Settings';
include __DIR__ . '/../../templates/header.php';

$hc = htmlspecialchars($settings['header_color']);
$sc = htmlspecialchars($settings['company_slogan_color'] ?? '#E67E22');
?>

<form method="POST" action="" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
<div class="row">
    <!-- LEFT COLUMN -->
    <div class="col-md-7">
        
        <!-- Company Information -->
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-building"></i> Company Information</h6></div>
            <div class="card-body">
                <!-- Row 1: Name + Font Size -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" id="inp_company_name"
                                   value="<?= htmlspecialchars($settings['company_name']) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Name Font Size</label>
                            <div class="input-group">
                                <input type="number" name="company_name_font_size" class="form-control" id="inp_name_fs"
                                       value="<?= $settings['company_name_font_size'] ?? 28 ?>" min="14" max="48">
                                <span class="input-group-text">px</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Row 2: Slogan + Font Size + Color -->
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Company Slogan</label>
                            <input type="text" name="company_slogan" class="form-control" id="inp_slogan"
                                   value="<?= htmlspecialchars($settings['company_slogan'] ?? '') ?>"
                                   placeholder="e.g., Your Trusted Partner">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Slogan Font</label>
                            <div class="input-group">
                                <input type="number" name="company_slogan_font_size" class="form-control" id="inp_slogan_fs"
                                       value="<?= $settings['company_slogan_font_size'] ?? 14 ?>" min="8" max="30">
                                <span class="input-group-text">px</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Slogan Color</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="company_slogan_color" class="form-control" id="inp_slogan_color"
                                       value="<?= $sc ?>" style="height:38px; width:60px;">
                                <span style="font-size:12px;"><?= $sc ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Row 3: Address -->
                <div class="form-group">
                    <label>Company Address</label>
                    <input type="text" name="company_address" class="form-control"
                           value="<?= htmlspecialchars($settings['company_address']) ?>">
                </div>
                
                <!-- Row 4: Phone, Email -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="company_phone" class="form-control" 
                                   value="<?= htmlspecialchars($settings['company_phone']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="company_email" class="form-control" 
                                   value="<?= htmlspecialchars($settings['company_email']) ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Row 5: Tax Number, Website (below Phone/Email) -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Tax Number</label>
                            <input type="text" name="tax_number" class="form-control" 
                                   value="<?= htmlspecialchars($settings['tax_number'] ?? '') ?>"
                                   placeholder="e.g., TIN-123456789">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Default Tax Rate (%)</label>
                            <div class="input-group">
                                <input type="number" name="default_tax_rate" class="form-control" 
                                       value="<?= htmlspecialchars($settings['default_tax_rate'] ?? 0) ?>"
                                       min="0" max="100" step="0.01" placeholder="0">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="form-text text-muted">0 = No tax applied</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Website</label>
                            <input type="text" name="company_website" class="form-control" 
                                   value="<?= htmlspecialchars($settings['company_website'] ?? '') ?>"
                                   placeholder="e.g., www.example.com">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Company Logo & Digital Signature -->
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-image"></i> Logo & Signature</h6></div>
            <div class="card-body">
                <div class="row">
                    <!-- Company Logo -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-image text-info"></i> Company Logo</label>
                            <?php if (!empty($settings['company_logo'])): ?>
                                <div class="mb-2 p-2 border rounded bg-white text-center">
                                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($settings['company_logo']) ?>" 
                                         alt="Logo" style="max-height:80px; max-width:100%;">
                                    <div class="mt-1">
                                        <label class="text-danger" style="cursor:pointer; font-size:12px;">
                                            <input type="checkbox" name="remove_logo" value="1" style="display:none;" 
                                                   onchange="if(this.checked){this.parentElement.parentElement.parentElement.style.opacity='0.3';}else{this.parentElement.parentElement.parentElement.style.opacity='1';}">
                                            <i class="fas fa-trash"></i> Remove Logo
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="company_logo" class="form-control" accept="image/*">
                            <small class="form-text text-muted">PNG, JPG, SVG (Max 2MB)</small>
                        </div>
                    </div>
                    
                    <!-- Digital Signature -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><i class="fas fa-signature text-success"></i> Digital Signature</label>
                            <?php if (!empty($settings['invoice_signature'])): ?>
                                <div class="mb-2 p-2 border rounded bg-white text-center">
                                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($settings['invoice_signature']) ?>" 
                                         alt="Signature" style="max-height:60px; max-width:100%;">
                                    <div class="mt-1">
                                        <label class="text-danger" style="cursor:pointer; font-size:12px;">
                                            <input type="checkbox" name="remove_signature" value="1" style="display:none;"
                                                   onchange="if(this.checked){this.parentElement.parentElement.parentElement.style.opacity='0.3';}else{this.parentElement.parentElement.parentElement.style.opacity='1';}">
                                            <i class="fas fa-trash"></i> Remove Signature
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="invoice_signature" class="form-control" accept="image/*">
                            <small class="form-text text-muted">Signature image for invoice footer</small>
                        </div>
                        <div class="form-check form-switch mb-2 mt-2">
                            <input type="checkbox" class="form-check-input" id="show_signature_on_invoice" name="show_signature_on_invoice" <?= ($settings['show_signature_on_invoice'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_signature_on_invoice">Show Signature on Invoice</label>
                        </div>
                        <div class="form-group mt-2">
                            <label>Signature Label</label>
                            <input type="text" name="author_signature_label" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($settings['author_signature_label'] ?? 'Authorized Signature') ?>"
                                   placeholder="e.g., Authorized Signature">
                            <small class="form-text text-muted">Text shown below signature on invoice</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Design & Colors -->
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-palette"></i> Design & Colors</h6></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Header / Accent Color</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="header_color" class="form-control" id="inp_header_color"
                                       value="<?= $hc ?>" style="height:45px; width:70px;">
                                <span id="header_hex" class="ms-2"><?= $hc ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Text Color</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="text_color" class="form-control"
                                       value="<?= htmlspecialchars($settings['text_color']) ?>" style="height:45px; width:70px;">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Invoice Margin</label>
                            <select name="invoice_margin" class="form-control">
                                <?php 
                                $margins = ['0.25cm'=>'0.25 cm','0.5cm'=>'0.5 cm','0.75cm'=>'0.75 cm','1cm'=>'1 cm (Default)','1.5cm'=>'1.5 cm','2cm'=>'2 cm'];
                                foreach ($margins as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= ($settings['invoice_margin'] ?? '1cm') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Display Options -->
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-toggle-on"></i> Display Options</h6></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" class="form-check-input" id="show_logo" name="show_logo" <?= ($settings['show_logo'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_logo">Show Logo</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" class="form-check-input" id="show_slogan" name="show_slogan" <?= ($settings['show_slogan'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_slogan">Show Slogan on Invoice</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" class="form-check-input" id="show_company_info" name="show_company_info" <?= $settings['show_company_info'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_company_info">Show Company Info</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" class="form-check-input" id="show_customer_info" name="show_customer_info" <?= $settings['show_customer_info'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_customer_info">Show Customer Info</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" class="form-check-input" id="show_payment_info" name="show_payment_info" <?= $settings['show_payment_info'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_payment_info">Show Payment Info</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" class="form-check-input" id="show_terms" name="show_terms" <?= $settings['show_terms'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_terms">Show Terms</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" class="form-check-input" id="show_print_datetime" name="show_print_datetime" <?= ($settings['show_print_datetime'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_print_datetime">Print Date & Time</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Sections -->
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-align-left"></i> Content Sections</h6></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Invoice Note</label>
                    <textarea name="invoice_note" class="form-control" rows="2" id="inp_invoice_note"
                              placeholder="e.g., Thank you for your business!"><?= htmlspecialchars($settings['invoice_note']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Terms & Conditions</label>
                    <textarea name="terms_and_conditions" class="form-control" rows="3"><?= htmlspecialchars($settings['terms_and_conditions']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Footer Text</label>
                    <input type="text" name="footer_text" class="form-control"
                           value="<?= htmlspecialchars($settings['footer_text']) ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock text-muted"></i> Software Developed By</label>
                    <input type="text" name="developed_by" class="form-control" 
                           value="<?= htmlspecialchars($settings['developed_by'] ?? 'CITNBD') ?>"
                           readonly style="background:#e9ecef;">
                    <small class="form-text text-muted">Shown as "Developed By CITNBD" on footer</small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg mt-3">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </div>
    </div>
    
    <!-- RIGHT COLUMN: Invoice Settings + Preview + Templates -->
    <div class="col-md-5">
        
        <!-- Invoice Settings -->
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice"></i> Invoice Settings</h6></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Invoice Prefix</label>
                            <input type="text" name="invoice_prefix" class="form-control form-control-sm" 
                                   value="<?= htmlspecialchars($settings['invoice_prefix']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Number Digits</label>
                            <input type="number" name="invoice_number_digits" class="form-control form-control-sm" 
                                   value="<?= $settings['invoice_number_digits'] ?>" min="4" max="10">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Invoice Number Format</label>
                            <select name="invoice_number_format" class="form-control form-control-sm">
                                <option value="{PREFIX}-{MMM}-{YYYY}-{NNNN}" <?= ($settings['invoice_number_format'] ?? '') === '{PREFIX}-{MMM}-{YYYY}-{NNNN}' ? 'selected' : '' ?>>NEXINV-FEB-2026-0001</option>
                                <option value="{PREFIX}-{NNNN}" <?= ($settings['invoice_number_format'] ?? '') === '{PREFIX}-{NNNN}' ? 'selected' : '' ?>>NEXINV-0001</option>
                                <option value="{PREFIX}-{YYYYMMDD}-{NNNN}" <?= ($settings['invoice_number_format'] ?? '') === '{PREFIX}-{YYYYMMDD}-{NNNN}' ? 'selected' : '' ?>>NEXINV-20260228-0001</option>
                                <option value="{PREFIX}-{YY}-{MMM}-{NNNN}" <?= ($settings['invoice_number_format'] ?? '') === '{PREFIX}-{YY}-{MMM}-{NNNN}' ? 'selected' : '' ?>>NEXINV-26-FEB-0001</option>
                                <option value="{PREFIX}-{YYYY}-{NNNN}" <?= ($settings['invoice_number_format'] ?? '') === '{PREFIX}-{YYYY}-{NNNN}' ? 'selected' : '' ?>>NEXINV-2026-0001</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Body Font Size</label>
                            <select name="font_size" class="form-control form-control-sm">
                                <option value="small" <?= ($settings['font_size'] ?? '') === 'small' ? 'selected' : '' ?>>Small (10px)</option>
                                <option value="medium" <?= ($settings['font_size'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium (12px)</option>
                                <option value="large" <?= ($settings['font_size'] ?? '') === 'large' ? 'selected' : '' ?>>Large (14px)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Template Style</label>
                    <select name="template_style" class="form-control form-control-sm" id="template_style">
                        <option value="classic" <?= ($settings['template_style'] ?? 'classic') === 'classic' ? 'selected' : '' ?>>📄 Classic (Default)</option>
                        <option value="modern" <?= ($settings['template_style'] ?? '') === 'modern' ? 'selected' : '' ?>>🎨 Modern</option>
                        <option value="minimal" <?= ($settings['template_style'] ?? '') === 'minimal' ? 'selected' : '' ?>>📋 Minimal</option>
                        <option value="corporate" <?= ($settings['template_style'] ?? '') === 'corporate' ? 'selected' : '' ?>>🏢 Corporate</option>
                        <option value="elegant" <?= ($settings['template_style'] ?? '') === 'elegant' ? 'selected' : '' ?>>✨ Elegant</option>
                        <option value="professional" <?= ($settings['template_style'] ?? '') === 'professional' ? 'selected' : '' ?>>💼 Professional</option>
                        <option value="receipt" <?= ($settings['template_style'] ?? '') === 'receipt' ? 'selected' : '' ?>>🧾 Receipt</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Invoice Preview -->
        <div class="card shadow mb-4" id="preview-card">
            <div class="card-header py-2" id="preview-header" style="background-color: <?= $hc ?>; color: white;">
                <h6 class="m-0 font-weight-bold" style="font-size:12px;">Invoice Preview</h6>
            </div>
            <div class="card-body p-3" style="background:#fff; color:#333; font-size:10px;" id="preview-body">
                <div class="text-center mb-2" id="preview-header-area">
                    <?php if (!empty($settings['company_logo'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($settings['company_logo']) ?>" alt="Logo" style="max-height:40px; margin-bottom:5px;">
                    <?php endif; ?>
                    <div id="preview-company-name" style="font-size:<?= $settings['company_name_font_size'] ?? 28 ?>px; font-weight:900; color:<?= $hc ?>; text-transform:uppercase; border-bottom:2px solid <?= $hc ?>; display:inline-block; padding-bottom:2px;">
                        <?= htmlspecialchars($settings['company_name']) ?>
                    </div>
                    <?php if (!empty($settings['company_slogan'])): ?>
                    <div id="preview-slogan" style="font-size:<?= $settings['company_slogan_font_size'] ?? 14 ?>px; color:<?= $sc ?>; font-weight:bold; text-transform:uppercase; letter-spacing:1px;">
                        <?= htmlspecialchars($settings['company_slogan']) ?>
                    </div>
                    <?php else: ?>
                    <div id="preview-slogan" style="font-size:<?= $settings['company_slogan_font_size'] ?? 14 ?>px; color:<?= $sc ?>; font-weight:bold; display:none;"></div>
                    <?php endif; ?>
                    <small style="color:#666;"><?= htmlspecialchars($settings['company_address']) ?></small>
                </div>
                <hr style="margin:4px 0;">
                <div class="d-flex justify-content-between" style="font-size:9px;">
                    <span><b>Invoice #:</b> <?= htmlspecialchars($settings['invoice_prefix']) ?>000001</span>
                    <span><b>Date:</b> <?= date('d-M-Y') ?></span>
                </div>
                <hr style="margin:4px 0; border-style:solid;">
                <p class="mb-1" style="font-size:9px;"><b>Customer:</b> Walk-in Customer</p>
                
                <table style="width:100%;border-collapse:collapse;margin:5px 0;font-size:9px;" id="preview-table">
                    <thead>
                        <tr id="preview-thead" style="background:<?= $hc ?>; color:#fff;">
                            <th style="border:1px solid #ccc;padding:2px 4px;">SL</th>
                            <th style="border:1px solid #ccc;padding:2px 4px;">Item</th>
                            <th style="border:1px solid #ccc;padding:2px 4px;">Qty</th>
                            <th style="border:1px solid #ccc;padding:2px 4px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td style="border:1px solid #ccc;padding:2px 4px;text-align:center;">01</td><td style="border:1px solid #ccc;padding:2px 4px;">Sample Product</td><td style="border:1px solid #ccc;padding:2px 4px;text-align:center;">1</td><td style="border:1px solid #ccc;padding:2px 4px;text-align:right;">1,250.00</td></tr>
                    </tbody>
                </table>
                
                <div style="text-align:right;font-weight:bold;font-size:10px;margin-bottom:5px;">Grand Total: ৳1,250.00</div>
                
                <div id="preview-note" style="text-align:center;color:<?= $hc ?>;font-weight:bold;font-size:9px;">
                    <?= htmlspecialchars($settings['invoice_note']) ?>
                </div>
                
                <?php if (!empty($settings['invoice_signature'])): ?>
                <div style="text-align:right;margin-top:5px;">
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($settings['invoice_signature']) ?>" alt="Signature" style="max-height:30px;">
                    <div style="font-size:8px;border-top:1px solid #333;display:inline-block;padding-top:2px;">Authorized</div>
                </div>
                <?php endif; ?>
                
                <hr style="margin:4px 0; border-style:dashed;">
                <div style="display:flex;justify-content:space-between;font-size:8px;color:#888;">
                    <span>Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></span>
                    <span>Developed By <?= htmlspecialchars($settings['developed_by'] ?? 'CITNBD') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card shadow mb-4">
            <div class="card-body py-3">
                <a href="sales-list.php" class="btn btn-info btn-block mb-2"><i class="fas fa-file-invoice"></i> View Sales List</a>
                <button type="button" class="btn btn-success btn-block" onclick="previewInvoice()"><i class="fas fa-eye"></i> Preview Full Invoice</button>
            </div>
        </div>

        <!-- Template Thumbnails -->
        <div class="card shadow">
            <div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary" style="font-size:13px;"><i class="fas fa-th-large"></i> Template Styles</h6></div>
            <div class="card-body py-2">
                <div class="row g-2">
                    <?php
                    $templates = [
                        'classic' => ['📄 Classic', '#2c3e50', 'Standard borders'],
                        'modern' => ['🎨 Modern', 'linear-gradient(135deg,#667eea,#764ba2)', 'Gradient header'],
                        'minimal' => ['📋 Minimal', '#f8f9fa', 'Borderless clean'],
                        'corporate' => ['🏢 Corporate', '#1a3a5c', 'Dark formal'],
                        'elegant' => ['✨ Elegant', '#8b6914', 'Gold premium'],
                        'professional' => ['💼 Professional', '#2563eb', 'Blue business'],
                        'receipt' => ['🧾 Receipt', '#374151', 'Compact POS'],
                    ];
                    foreach ($templates as $key => $t):
                        $active = ($settings['template_style'] ?? 'classic') === $key;
                    ?>
                    <div class="col-4 mb-2">
                        <div class="template-thumb rounded p-1 text-center" data-template="<?= $key ?>" 
                             style="cursor:pointer;font-size:9px;border:2px solid <?= $active ? '#4e73df' : '#ddd' ?>;">
                            <div style="background:<?= $t[1] ?>;color:<?= $key === 'minimal' ? '#333' : '#fff' ?>;padding:2px;border-radius:3px 3px 0 0;font-size:9px;"><?= $t[0] ?></div>
                            <div style="padding:3px;color:#888;font-size:8px;"><?= $t[2] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
function previewInvoice() {
    window.open('invoice-print.php?id=16', '_blank');
}

// Real-time preview: Header Color
var hci = document.getElementById('inp_header_color');
if (hci) {
    hci.addEventListener('input', function(e) {
        var c = e.target.value;
        document.getElementById('header_hex').textContent = c;
        document.getElementById('preview-header').style.backgroundColor = c;
        var cn = document.getElementById('preview-company-name');
        if (cn) { cn.style.color = c; cn.style.borderBottomColor = c; }
        var pn = document.getElementById('preview-note');
        if (pn) pn.style.color = c;
        var th = document.getElementById('preview-thead');
        if (th) th.style.backgroundColor = c;
    });
}

// Company Name text
var ni = document.getElementById('inp_company_name');
if (ni) ni.addEventListener('input', function() { var el = document.getElementById('preview-company-name'); if(el) el.textContent = this.value; });

// Name font size
var nfs = document.getElementById('inp_name_fs');
if (nfs) nfs.addEventListener('input', function() { var el = document.getElementById('preview-company-name'); if(el) el.style.fontSize = this.value + 'px'; });

// Slogan text
var si = document.getElementById('inp_slogan');
if (si) si.addEventListener('input', function() { var el = document.getElementById('preview-slogan'); if(el) { el.textContent = this.value; el.style.display = this.value ? 'block' : 'none'; } });

// Slogan font size
var sfs = document.getElementById('inp_slogan_fs');
if (sfs) sfs.addEventListener('input', function() { var el = document.getElementById('preview-slogan'); if(el) el.style.fontSize = this.value + 'px'; });

// Slogan color
var sco = document.getElementById('inp_slogan_color');
if (sco) sco.addEventListener('input', function() { var el = document.getElementById('preview-slogan'); if(el) el.style.color = this.value; });

// Invoice note
var ino = document.getElementById('inp_invoice_note');
if (ino) ino.addEventListener('input', function() { var el = document.getElementById('preview-note'); if(el) el.textContent = this.value; });

// Template style selection
document.querySelectorAll('.template-thumb').forEach(function(el) {
    el.addEventListener('click', function() {
        document.getElementById('template_style').value = this.dataset.template;
        document.querySelectorAll('.template-thumb').forEach(function(t) { t.style.border = '2px solid #ddd'; });
        this.style.border = '2px solid #4e73df';
        updatePreviewTemplate(this.dataset.template);
    });
});

// Template style dropdown change
document.getElementById('template_style').addEventListener('change', function() {
    updatePreviewTemplate(this.value);
    document.querySelectorAll('.template-thumb').forEach(function(t) { 
        t.style.border = t.dataset.template === document.getElementById('template_style').value ? '2px solid #4e73df' : '2px solid #ddd'; 
    });
});

function updatePreviewTemplate(template) {
    var headerArea = document.getElementById('preview-header-area');
    var thead = document.getElementById('preview-thead');
    var table = document.getElementById('preview-table');
    var cn = document.getElementById('preview-company-name');
    var hc = document.getElementById('inp_header_color').value;
    
    // Reset
    headerArea.style.background = 'none';
    headerArea.style.padding = '0';
    headerArea.style.borderRadius = '0';
    headerArea.style.borderBottom = 'none';
    cn.style.color = hc;
    cn.style.borderBottom = '2px solid ' + hc;
    cn.style.fontSize = (document.getElementById('inp_name_fs').value || 28) + 'px';
    thead.style.background = hc;
    thead.style.color = '#fff';
    
    var cells = table.querySelectorAll('td, th');
    cells.forEach(function(c) { c.style.border = '1px solid #ccc'; c.style.background = ''; c.style.color = ''; });
    
    switch(template) {
        case 'modern':
            headerArea.style.background = 'linear-gradient(135deg, ' + hc + ', ' + hc + '99)';
            headerArea.style.padding = '10px';
            headerArea.style.borderRadius = '8px';
            cn.style.color = '#fff';
            cn.style.borderBottomColor = 'rgba(255,255,255,0.5)';
            break;
        case 'minimal':
            cn.style.borderBottom = 'none';
            thead.style.background = 'transparent';
            thead.style.color = hc;
            cells.forEach(function(c) { c.style.border = 'none'; c.style.borderBottom = '1px solid #eee'; });
            break;
        case 'corporate':
            headerArea.style.borderBottom = '4px solid ' + hc;
            thead.style.background = '#1a2332';
            break;
        case 'elegant':
            cn.style.color = '#8b6914';
            cn.style.borderBottomColor = '#c9a84c';
            thead.style.background = 'linear-gradient(135deg, #8b6914, #c9a84c)';
            break;
    }
}
</script>
