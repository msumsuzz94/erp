<?php
/**
 * Standardized Print Header Component
 */

// Get business settings for header
$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$invoice_settings) {
    $invoice_settings = [
        'company_name' => defined('BUSINESS_NAME') ? BUSINESS_NAME : '',
        'company_address' => defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : '',
        'company_phone' => defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '',
        'company_email' => defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : '',
        'company_website' => '',
        'company_logo' => 'assets/images/logo.png',
        'company_slogan' => ''
    ];
}

$logo_url = !empty($invoice_settings['company_logo']) ? $invoice_settings['company_logo'] : 'assets/images/logo.png';
if (!preg_match('~^(?:f|ht)tps?://~i', $logo_url)) {
    $logo_url = BASE_URL . '/' . ltrim($logo_url, '/');
}
?>

<div id="standard-print-wrapper">
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <img src="<?= $logo_url ?>" alt="Logo">
            </td>
            <td class="company-cell">
                <h1 class="company-name"><?= htmlspecialchars($invoice_settings['company_name'] ?? '') ?></h1>
                <?php if (!empty($invoice_settings['company_slogan'])): ?>
                    <div class="company-slogan"><?= htmlspecialchars($invoice_settings['company_slogan'] ?? '') ?></div>
                <?php endif; ?>
            </td>
            <td class="contact-cell">
                <p><strong>Address:</strong> <?= htmlspecialchars($invoice_settings['company_address'] ?? '') ?></p>
                <p><strong>Tel:</strong> <?= htmlspecialchars($invoice_settings['company_phone'] ?? '') ?></p>
                <?php if (!empty($invoice_settings['company_email'])): ?>
                    <p><strong>Email:</strong> <?= htmlspecialchars($invoice_settings['company_email'] ?? '') ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice_settings['company_website'])): ?>
                    <p><strong>Web:</strong> <?= htmlspecialchars($invoice_settings['company_website'] ?? '') ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <div class="standard-report-title"><?= $page_title ?? 'Report' ?></div>
</div>
