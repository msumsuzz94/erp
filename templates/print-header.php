<?php
// Fetch invoice settings for print header customization
$print_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$print_settings) {
    $print_settings = [
        'company_name' => BUSINESS_NAME ?? 'Company Name',
        'company_slogan' => defined('BUSINESS_SLOGAN') ? BUSINESS_SLOGAN : '',
        'company_address' => BUSINESS_ADDRESS ?? '',
        'company_phone' => BUSINESS_PHONE ?? '',
        'company_email' => BUSINESS_EMAIL ?? '',
        'company_website' => defined('BUSINESS_WEBSITE') ? BUSINESS_WEBSITE : '',
        'company_logo' => defined('BUSINESS_LOGO') ? BUSINESS_LOGO : '',
        'company_name_font_size' => 50,
        'company_slogan_font_size' => 50,
        'address_font_size' => 30,
        'show_logo' => 1,
        'show_slogan' => 1,
        'header_color' => '#ff6600'
    ];
}

// Set font sizes with fallbacks
$company_name_size = $print_settings['company_name_font_size'] ?? 50;
$company_slogan_size = $print_settings['company_slogan_font_size'] ?? 50;
$address_size = $print_settings['address_font_size'] ?? 30;
?>

<!-- Print Header Styles for List Pages -->
<style>
@media print {
    .print-header { 
        display: flex !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
        margin-bottom: 30px !important;
        border-bottom: 2px solid #000 !important;
        padding-bottom: 15px !important;
    }
    .print-header-left {
        width: 15% !important;
        text-align: left !important;
    }
    .print-header-logo {
        max-width: 100px !important;
        height: auto !important;
    }
    .print-header-center {
        width: 50% !important;
        text-align: center !important;
    }
    .print-header-company-name {
        font-size: <?= $company_name_size ?>px !important;
        font-weight: bold !important;
        margin: 0 !important;
        line-height: 1.1 !important;
        color: #000 !important;
        text-transform: uppercase !important;
    }
    .print-header-slogan {
        font-size: <?= $company_slogan_size ?>px !important;
        color: <?= $print_settings['header_color'] ?? '#ff6600' ?> !important;
        margin: 5px 0 0 0 !important;
        font-weight: normal !important;
        text-transform: uppercase !important;
    }
    .print-header-right {
        width: 35% !important;
        text-align: left !important;
        border: 1px solid #000 !important;
        padding: 10px !important;
    }
    .print-header-address {
        font-size: <?= $address_size ?>px !important;
        margin: 0 !important;
        line-height: 1.3 !important;
        color: #000 !important;
    }
}
.print-header { 
    display: none; 
}
</style>

<div class="print-header">
    <div class="print-header-left">
        <?php if (($print_settings['show_logo'] ?? 1) && !empty($print_settings['company_logo']) && file_exists(__DIR__ . '/../../' . $print_settings['company_logo'])): ?>
            <img src="<?= BASE_URL . '/' . $print_settings['company_logo'] ?>" alt="Company Logo" class="print-header-logo">
        <?php else: ?>
            <div style="font-weight: bold;">Company Logo</div>
        <?php endif; ?>
    </div>
    
    <div class="print-header-center">
        <div class="print-header-company-name"><?= htmlspecialchars($print_settings['company_name']) ?></div>
        <?php if (($print_settings['show_slogan'] ?? 1) && !empty($print_settings['company_slogan'])): ?>
            <div class="print-header-slogan"><?= htmlspecialchars($print_settings['company_slogan']) ?></div>
        <?php endif; ?>
    </div>
    
    <div class="print-header-right">
        <div class="print-header-address">
            <strong>Company Address:</strong><br>
            <?= htmlspecialchars($print_settings['company_address'] ?? '') ?><br>
            <strong>Phone Numbers:</strong><br>
            <?= htmlspecialchars($print_settings['company_phone'] ?? '') ?><br>
            <?php if (!empty($print_settings['company_email'])): ?>
                <strong>Email Address:</strong><br>
                <?= htmlspecialchars($print_settings['company_email']) ?><br>
            <?php endif; ?>
            <?php if (!empty($print_settings['company_website'])): ?>
                <strong>Website:</strong><br>
                <?= htmlspecialchars($print_settings['company_website']) ?>
            <?php endif; ?>
        </div>
    </div>
</div>
