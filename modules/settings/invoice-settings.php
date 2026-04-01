<?php
/**
 * Professional Invoice Settings Page
 * Allows customization of invoice design including logo, signature, and business details
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

init_session();
require_login();

// Initialize messages
$success_message = '';
$error_message = '';

// Get current settings
$settings = db_select_one('invoice_settings', ['id' => 1]);

// Ensure settings has default values if null or missing keys
if (!$settings) {
    $settings = [
        'company_name' => BUSINESS_NAME,
        'company_slogan' => '',
        'company_address' => BUSINESS_ADDRESS,
        'company_phone' => BUSINESS_PHONE,
        'company_email' => BUSINESS_EMAIL,
        'company_website' => '',
        'tax_number' => BUSINESS_TAX_NO,
        'invoice_prefix' => INVOICE_PREFIX,
        'invoice_number_digits' => INVOICE_NUMBER_LENGTH,
        'default_tax_rate' => 0,
        'invoice_note' => 'Thank you for your business!',
        'terms_and_conditions' => 'Payment is due within 15 days',
        'footer_text' => 'This is a computer generated invoice',
        'company_logo' => null,
        'invoice_signature' => null,
        'show_logo' => 1,
        'show_slogan' => 1,
        'show_signature_on_invoice' => 1,
        'author_signature_label' => 'Author signature',
        'good_received_text' => 'Good received by customer in good condition.',
        'show_amount_in_words' => 1,
        'amount_in_words_prefix' => 'BDT',
        'header_color' => '#4e73df',
        'text_color' => '#000000',
        'invoice_template' => 'professional',
        'company_name_font_size' => 28,
        'company_slogan_font_size' => 14
    ];
}

// Handle form submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $data = [
            'company_name' => clean_input($_POST['company_name']),
            'company_slogan' => clean_input($_POST['company_slogan']),
            'company_address' => clean_input($_POST['company_address']),
            'company_phone' => clean_input($_POST['company_phone']),
            'company_email' => clean_input($_POST['company_email']),
            'company_website' => clean_input($_POST['company_website']),
            'tax_number' => clean_input($_POST['tax_number']),
            'invoice_prefix' => clean_input($_POST['invoice_prefix']),
            'invoice_number_digits' => (int)$_POST['invoice_number_digits'],
            'invoice_format' => clean_input($_POST['invoice_format']),
            'default_tax_rate' => (float)$_POST['default_tax_rate'],
            'invoice_note' => clean_input($_POST['invoice_note']),
            'terms_and_conditions' => clean_input($_POST['terms_and_conditions']),
            'footer_text' => clean_input($_POST['footer_text']),
            'show_logo' => isset($_POST['show_logo']) ? 1 : 0,
            'show_slogan' => isset($_POST['show_slogan']) ? 1 : 0,
            'show_signature_on_invoice' => isset($_POST['show_signature_on_invoice']) ? 1 : 0,
            'author_signature_label' => clean_input($_POST['author_signature_label']),
            'good_received_text' => clean_input($_POST['good_received_text']),
            'show_amount_in_words' => isset($_POST['show_amount_in_words']) ? 1 : 0,
            'amount_in_words_prefix' => clean_input($_POST['amount_in_words_prefix']),
            'header_color' => clean_input($_POST['header_color']),
            'text_color' => clean_input($_POST['text_color']),
            'invoice_template' => clean_input($_POST['invoice_template']),
            'show_print_time' => isset($_POST['show_print_time']) ? 1 : 0,
            'software_developed_by' => clean_input($_POST['software_developed_by']),
            'company_name_font_size' => (int)$_POST['company_name_font_size'],
            'company_slogan_font_size' => (int)$_POST['company_slogan_font_size']
        ];

        // Handle logo upload
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_res = upload_image($_FILES['company_logo'], UPLOAD_PATH . 'settings/');
            if ($upload_res['status']) {
                $data['company_logo'] = 'uploads/settings/' . $upload_res['filename'];
                if (!empty($settings['company_logo']) && file_exists(BASE_PATH . '/' . $settings['company_logo'])) {
                    unlink(BASE_PATH . '/' . $settings['company_logo']);
                }
            }
        }

        // Handle signature upload
        if (isset($_FILES['invoice_signature']) && $_FILES['invoice_signature']['error'] === UPLOAD_ERR_OK) {
            $upload_res = upload_image($_FILES['invoice_signature'], UPLOAD_PATH . 'settings/');
            if ($upload_res['status']) {
                $data['invoice_signature'] = 'uploads/settings/' . $upload_res['filename'];
                if (!empty($settings['invoice_signature']) && file_exists(BASE_PATH . '/' . $settings['invoice_signature'])) {
                    unlink(BASE_PATH . '/' . $settings['invoice_signature']);
                }
            }
        }

        try {
            if (db_update('invoice_settings', $data, ['id' => 1])) {
                // Sync with business_settings.tax_rate for global consistency
                db_update('business_settings', ['tax_rate' => $data['default_tax_rate']], ['id' => 1]);
                
                $success_message = 'Invoice settings updated successfully!';
                $settings = db_select_one('invoice_settings', ['id' => 1]);
            } else {
                $error_message = 'Failed to update settings. Row might not exist or no changes were made.';
            }
        } catch (Exception $e) {
            $error_message = 'Database Error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Invoice Settings';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="mb-4">
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= $success_message ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Company Details & Branding</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name *</label>
                                <div class="input-group">
                                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>" required>
                                    <select name="company_name_font_size" class="form-select" style="max-width: 100px;">
                                        <?php for($i=12; $i<=48; $i+=2): ?>
                                            <option value="<?= $i ?>" <?= ($settings['company_name_font_size'] ?? 28) == $i ? 'selected' : '' ?>><?= $i ?>px</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Slogan</label>
                                <div class="input-group">
                                    <input type="text" name="company_slogan" class="form-control" value="<?= htmlspecialchars($settings['company_slogan'] ?? '') ?>">
                                    <select name="company_slogan_font_size" class="form-select" style="max-width: 100px;">
                                        <?php for($i=8; $i<=24; $i+=2): ?>
                                            <option value="<?= $i ?>" <?= ($settings['company_slogan_font_size'] ?? 14) == $i ? 'selected' : '' ?>><?= $i ?>px</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Numbers</label>
                                <input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>" placeholder="e.g. +8801..., +8801...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Website</label>
                                <input type="text" name="company_website" class="form-control" value="<?= htmlspecialchars($settings['company_website'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax Number (VAT/TIN)</label>
                                <input type="text" name="tax_number" class="form-control" value="<?= htmlspecialchars($settings['tax_number'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Company Address</label>
                            <textarea name="company_address" class="form-control" rows="2"><?= htmlspecialchars($settings['company_address'] ?? '') ?></textarea>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Logo</label>
                                <?php if (!empty($settings['company_logo'])): ?>
                                    <div class="mb-2"><img src="<?= BASE_URL . '/' . $settings['company_logo'] ?>" style="max-height: 60px;"></div>
                                <?php endif; ?>
                                <input type="file" name="company_logo" class="form-control">
                                <div class="form-check mt-1">
                                    <input type="checkbox" name="show_logo" id="showLogo" class="form-check-input" <?= ($settings['show_logo'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="showLogo">Show Logo on Invoice</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="show_slogan" id="showSlogan" class="form-check-input" <?= ($settings['show_slogan'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="showSlogan">Show Slogan on Invoice</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Digital Signature</label>
                                <?php if (!empty($settings['invoice_signature'])): ?>
                                    <div class="mb-2"><img src="<?= BASE_URL . '/' . $settings['invoice_signature'] ?>" style="max-height: 40px;"></div>
                                <?php endif; ?>
                                <input type="file" name="invoice_signature" class="form-control">
                                <input type="text" name="author_signature_label" class="form-control mt-2" placeholder="Signature Label" value="<?= htmlspecialchars($settings['author_signature_label'] ?? 'Author signature') ?>">
                                <div class="form-check mt-1">
                                    <input type="checkbox" name="show_signature_on_invoice" id="showSig" class="form-check-input" <?= ($settings['show_signature_on_invoice'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="showSig">Show Signature on Invoice</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Invoice Layout Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Good Received Message</label>
                            <input type="text" name="good_received_text" class="form-control" value="<?= htmlspecialchars($settings['good_received_text'] ?? 'Good received by customer in good condition.') ?>">
                            <small class="text-muted">Appears below the totals</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="show_amount_in_words" id="showWords" class="form-check-input" <?= ($settings['show_amount_in_words'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="showWords">Show Amount in Words</label>
                                </div>
                                <label class="form-label">Currency Prefix for Words</label>
                                <input type="text" name="amount_in_words_prefix" class="form-control" value="<?= htmlspecialchars($settings['amount_in_words_prefix'] ?? 'BDT') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Header Background Color</label>
                                <input type="color" name="header_color" class="form-control form-control-color w-100" value="<?= htmlspecialchars($settings['header_color'] ?? '#4e73df') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Terms & Conditions</label>
                            <textarea name="terms_and_conditions" class="form-control" rows="3"><?= htmlspecialchars($settings['terms_and_conditions'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Invoice Footer Text</label>
                            <textarea name="footer_text" class="form-control" rows="2"><?= htmlspecialchars($settings['footer_text'] ?? '') ?></textarea>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="show_print_time" id="showPrintTime" class="form-check-input" <?= ($settings['show_print_time'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="showPrintTime">Show Print Date & Time on Footer</label>
                            </div>
                            
                            <div class="mt-3">
                                <label class="form-label">Software Developed By (Footer Right)</label>
                                <input type="text" name="software_developed_by" class="form-control" value="<?= htmlspecialchars($settings['software_developed_by'] ?? 'CITNBD | 01976-793351') ?>" readonly>
                                <small class="text-muted">Text to display on the bottom right of the print invoice footer.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Numbering & Technical</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Invoice Prefix</label>
                            <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars($settings['invoice_prefix'] ?? 'INV-') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Number Digits (Padding)</label>
                            <input type="number" name="invoice_number_digits" class="form-control" value="<?= $settings['invoice_number_digits'] ?? 6 ?>" min="4" max="10">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Invoice Number Format</label>
                            <select name="invoice_format" id="invoiceFormat" class="form-control">
                                <option value="format1" <?= ($settings['invoice_format'] ?? 'format1') == 'format1' ? 'selected' : '' ?>>PREFIX-MMM-YYYY-NNNN</option>
                                <option value="format2" <?= ($settings['invoice_format'] ?? '') == 'format2' ? 'selected' : '' ?>>PREFIX-NNNN</option>
                                <option value="format3" <?= ($settings['invoice_format'] ?? '') == 'format3' ? 'selected' : '' ?>>PREFIX-YYYYMMDD-NNNN</option>
                                <option value="format4" <?= ($settings['invoice_format'] ?? '') == 'format4' ? 'selected' : '' ?>>PREFIX-YY-MMM-NNNN</option>
                                <option value="format5" <?= ($settings['invoice_format'] ?? '') == 'format5' ? 'selected' : '' ?>>PREFIX-YYYY-NNNN</option>
                            </select>
                            <small class="text-muted d-block mt-1" id="formatExample">
                                Example: <strong id="exampleText">INV-JAN-2026-0001</strong>
                            </small>
                        </div>
                        
                        <script>
                        document.getElementById('invoiceFormat').addEventListener('change', function() {
                            const format = this.value;
                            const prefix = document.querySelector('input[name="invoice_prefix"]')?.value || 'INV-';
                            const month = new Date().toLocaleString('en', { month: 'short' }).toUpperCase();
                            const year = new Date().getFullYear();
                            const shortYear = year.toString().substr(-2);
                            const dateStr = year + String(new Date().getMonth() + 1).padStart(2, '0') + String(new Date().getDate()).padStart(2, '0');
                            
                                        let example = '';
                            switch(format) {
                                case 'format1':
                                    example = prefix + month + '-' + year + '-0001';
                                    break;
                                case 'format2':
                                    example = prefix + '0001';
                                    break;
                                case 'format3':
                                    example = prefix + dateStr + '-0001';
                                    break;
                                case 'format4':
                                    example = prefix + shortYear + '-' + month + '-0001';
                                    break;
                                case 'format5':
                                    example = prefix + year + '-0001';
                                    break;
                            }
                            document.getElementById('exampleText').textContent = example;
                        });
                        </script>
                        <div class="mb-3">
                            <label class="form-label">Default Tax Rate (%)</label>
                            <input type="number" name="default_tax_rate" class="form-control" value="<?= $settings['default_tax_rate'] ?? 0 ?>" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Template Style</label>
                            <select name="invoice_template" class="form-control">
                                <option value="professional" <?= ($settings['invoice_template'] ?? '') == 'professional' ? 'selected' : '' ?>>Professional (New)</option>
                                <option value="classic" <?= ($settings['invoice_template'] ?? '') == 'classic' ? 'selected' : '' ?>>Classic</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Invoice Note</label>
                            <textarea name="invoice_note" class="form-control" rows="2"><?= htmlspecialchars($settings['invoice_note'] ?? '') ?></textarea>
                        </div>
                        <input type="hidden" name="text_color" value="<?= htmlspecialchars($settings['text_color'] ?? '#000000') ?>">
                        <button type="submit" class="btn btn-primary btn-block">Save Invoice Settings</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
