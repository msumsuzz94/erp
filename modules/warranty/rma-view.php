<?php
/**
 * View RMA Claim Details
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$id = (int)get_param('id');
if (!$id) {
    redirect_with_message('rma-list.php', 'Invalid RMA ID', 'danger');
}

// Fetch RMA Details
$sql = "SELECT r.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
               p.name as product_name, p.code as product_code,
               ps.serial_number, ps.imei, ps.sale_date, ps.warranty_months,
               sc.name as sc_name, sc.phone as sc_phone, sc.address as sc_address,
               u.username as creator_name,
               nps.serial_number as new_serial_no
        FROM rma_requests r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN products p ON r.product_id = p.id
        LEFT JOIN product_serials ps ON r.serial_id = ps.id
        LEFT JOIN product_serials nps ON r.new_serial_id = nps.id
        LEFT JOIN service_centers sc ON r.service_center_id = sc.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.id = ?";
$rma = db_query_one($sql, [$id]);

if (!$rma) {
    redirect_with_message('rma-list.php', 'RMA Claim not found', 'danger');
}

// Fetch replacement product if exists
$replacement_product = db_query_one("SELECT * FROM rma_external_replacements WHERE rma_id = ?", [$id]);

$page_title = 'RMA Claim: ' . $rma['rma_number'];

// Get invoice settings for print header
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

include __DIR__ . '/../../templates/header.php';
?>

<style>
/* Print Area Styles (Hidden on Screen) */
#print-area {
    display: none;
    padding: 20px;
    background: #fff;
    color: #000;
}

@media print {
    @page { margin: 0.25cm; size: auto; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; width: 100% !important; }
    
    /* Hide regular UI AND standard print header/footer */
    .no-print, .btn, .card, .navbar, .sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, .summary-cards, #standard-print-wrapper, #standard-print-footer {
        display: none !important;
    }
    
    #print-area { display: block !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    
    .print-container { 
        width: 100% !important; 
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        box-sizing: border-box !important; 
        font-family: 'Segoe UI', Arial, sans-serif; 
        font-size: 11px; 
    }
    
    /* 3-Column Header */
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .header-table td { vertical-align: top; border: none !important; padding: 0; }
    .logo-cell { width: 10%; text-align: left; }
    .logo-cell img { max-width: 80px; height: auto; display: block; }
    
    .title-cell { width: 65%; text-align: center; padding: 0 10px; }
    .company-name { font-size: 22px; font-weight: 900; color: #000; margin: 0; text-transform: uppercase; border-bottom: 2px solid #000; display: inline-block; line-height: 1.2; padding-bottom: 2px; }
    .company-slogan { font-size: 11px; color: #000; font-weight: bold; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .info-cell { width: 25%; text-align: left; line-height: 1.4; font-size: 9px; border: 1px solid #000; padding: 5px 8px; box-sizing: border-box; }
    .info-cell p { margin: 0; margin-bottom: 2px; }
    .info-cell p:last-child { margin-bottom: 0; }
    
    .report-main-title { text-align: center; font-size: 16px; color: #000; margin: 12px 0; font-weight: bold; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 0; }

    /* Simple Bordered Table */
    .print-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .print-table th, .print-table td { border: 1px solid #333 !important; padding: 6px; text-align: left; font-size: 10px; }
    .print-table th { background: #f2f2f2 !important; font-weight: bold; text-transform: uppercase; }
    .text-end { text-align: right !important; }
    
    /* Fixed Footer at bottom of EVERY page */
    .print-footer { 
        position: fixed;
        bottom: 0.3cm;
        left: 0;
        right: 0;
        border-top: 1px solid #333; 
        padding-top: 10px; 
        display: block;
        width: 100%;
        font-size: 10px; 
        background: #fff;
        z-index: 9999;
    }
    .footer-left { float: left; width: 50%; font-weight: bold; text-align: left; padding-left: 10px; }
    .footer-right { float: right; width: 50%; text-align: right; padding-right: 10px; }
    .clearfix::after { content: ""; clear: both; display: table; }
}
</style>

<!-- Hidden Print Area -->
<div id="print-area">
    <div class="print-container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <?php 
                    $logo_url = !empty($invoice_settings['company_logo']) ? $invoice_settings['company_logo'] : 'assets/images/logo.png';
                    if (!preg_match('~^(?:f|ht)tps?://~i', $logo_url)) {
                        $logo_url = BASE_URL . '/' . ltrim($logo_url, '/');
                    }
                    ?>
                    <img src="<?= $logo_url ?>" alt="Logo">
                </td>
                <td class="title-cell">
                    <h1 class="company-name"><?= htmlspecialchars($invoice_settings['company_name'] ?? '') ?></h1><br>
                    <div class="company-slogan"><?= htmlspecialchars($invoice_settings['company_slogan'] ?? '') ?></div>
                </td>
                <td class="info-cell">
                    <p>Address: <?= htmlspecialchars($invoice_settings['company_address'] ?? '') ?></p>
                    <p>Tel: <?= htmlspecialchars($invoice_settings['company_phone'] ?? '') ?></p>
                    <p>Email: <?= htmlspecialchars($invoice_settings['company_email'] ?? '') ?></p>
                    <p>Website: <?= htmlspecialchars($invoice_settings['company_website'] ?? '') ?></p>
                </td>
            </tr>
        </table>

        <div class="report-main-title">RMA CLAIM - <?= htmlspecialchars($rma['rma_number']) ?> (<?= date('d-M-Y', strtotime($rma['created_date'])) ?>)</div>

        <!-- Customer & Service Center Info Table -->
        <table class="print-table">
            <tr>
                <th colspan="2">Customer Information</th>
                <th colspan="2">Service Center</th>
            </tr>
            <tr>
                <td><strong>Name:</strong></td>
                <td><?= htmlspecialchars($rma['customer_name'] ?? 'Walk-in') ?></td>
                <td><strong>Name:</strong></td>
                <td><?= $rma['service_center_id'] ? htmlspecialchars($rma['sc_name']) : 'Handled Internally' ?></td>
            </tr>
            <tr>
                <td><strong>Phone:</strong></td>
                <td><?= htmlspecialchars($rma['customer_phone'] ?? 'N/A') ?></td>
                <td><strong>Phone:</strong></td>
                <td><?= $rma['service_center_id'] ? htmlspecialchars($rma['sc_phone'] ?? 'N/A') : '-' ?></td>
            </tr>
            <tr>
                <td><strong>Address:</strong></td>
                <td><?= htmlspecialchars($rma['customer_address'] ?? 'N/A') ?></td>
                <td><strong>Address:</strong></td>
                <td><?= $rma['service_center_id'] ? htmlspecialchars($rma['sc_address'] ?? 'N/A') : '-' ?></td>
            </tr>
        </table>

        <!-- Problem & Notes -->
        <table class="print-table">
            <tr>
                <th>Problem Description</th>
                <th>Internal Notes</th>
            </tr>
            <tr>
                <td><?= nl2br(htmlspecialchars($rma['problem_description'])) ?></td>
                <td><?= $rma['notes'] ? nl2br(htmlspecialchars($rma['notes'])) : 'No notes' ?></td>
            </tr>
        </table>

        <!-- Stage Progress -->
        <table class="print-table">
            <tr>
                <th colspan="4" style="text-align: center; background: #5a9fd4 !important; color: white;">Claim Progress (Status: <?= strtoupper($rma['status']) ?>)</th>
            </tr>
            <tr>
                <th>1. TESTING</th>
                <th>2. REPLACE OUT (BRAND)</th>
                <th>3. REPLACE IN (RESOLUTION)</th>
                <th>4. DELIVERY</th>
            </tr>
            <tr>
                <td>
                    <?php if ($rma['testing_date']): ?>
                        <strong>Date:</strong> <?= date('d-M-Y', strtotime($rma['testing_date'])) ?>
                    <?php else: ?>
                        Pending
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($rma['replace_out_date']): ?>
                        <strong>Out No:</strong> <?= htmlspecialchars($rma['replace_out_number'] ?? 'N/A') ?><br>
                        <strong>Date:</strong> <?= date('d-M-Y', strtotime($rma['replace_out_date'])) ?>
                    <?php else: ?>
                        Pending
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($rma['replace_in_date']): ?>
                        <strong>In No:</strong> <?= htmlspecialchars($rma['replace_in_number'] ?? 'N/A') ?><br>
                        <strong>Date:</strong> <?= date('d-M-Y', strtotime($rma['replace_in_date'])) ?><br>
                        <strong>Type:</strong> <?= strtoupper($rma['replace_in_type']) ?>
                    <?php else: ?>
                        Pending
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($rma['delivery_date']): ?>
                        <strong>Del No:</strong> <?= htmlspecialchars($rma['delivery_number'] ?? 'N/A') ?><br>
                        <strong>Date:</strong> <?= date('d-M-Y', strtotime($rma['delivery_date'])) ?>
                    <?php else: ?>
                        Pending
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- Product & Warranty Details -->
        <table class="print-table">
            <tr>
                <th colspan="4" style="text-align: center;">Product & Warranty Details</th>
            </tr>
            <tr>
                <td><strong>Product:</strong></td>
                <td><?= htmlspecialchars($rma['product_name']) ?></td>
                <td><strong>Sale Date:</strong></td>
                <td><?= date('d-M-Y', strtotime($rma['sale_date'])) ?></td>
            </tr>
            <tr>
                <td><strong>Product Code:</strong></td>
                <td><?= htmlspecialchars($rma['product_code']) ?></td>
                <td><strong>Warranty:</strong></td>
                <td><?= $rma['warranty_months'] ?> Months</td>
            </tr>
            <tr>
                <td><strong>Serial Number:</strong></td>
                <td><?= htmlspecialchars($rma['serial_number'] ?? 'N/A') ?></td>
                <td><strong>Expiry:</strong></td>
                <td><?= date('d-M-Y', strtotime("+{$rma['warranty_months']} months", strtotime($rma['sale_date']))) ?></td>
            </tr>
            <tr>
                <td><strong>IMEI:</strong></td>
                <td><?= htmlspecialchars($rma['imei'] ?? 'N/A') ?></td>
                <td></td>
                <td></td>
            </tr>
            <?php if ($replacement_product): ?>
                <tr style="background: #e8f5e9 !important;">
                    <td colspan="4" style="text-align: center; font-weight: bold; color: #2e7d32;">REPLACEMENT PRODUCT</td>
                </tr>
                <tr style="background: #f1f8e9 !important;">
                    <td><strong>Replacement Product:</strong></td>
                    <td><?= htmlspecialchars($replacement_product['product_name']) ?></td>
                    <td><strong>Replacement Serial:</strong></td>
                    <td><?= htmlspecialchars($replacement_product['serial_number']) ?></td>
                </tr>
                <?php if (!empty($replacement_product['brand_name'])): ?>
                    <tr style="background: #f1f8e9 !important;">
                        <td><strong>Replacement Brand:</strong></td>
                        <td><?= htmlspecialchars($replacement_product['brand_name']) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php endif; ?>
            <?php endif; ?>
        </table>

        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<!-- Screen View -->
<div class="row no-print">
    <div class="col-lg-8">
        <!-- Main Claim Details -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Claim Information</h6>
                <span class="badge bg-<?= ['pending' => 'warning', 'sent' => 'info', 'solved' => 'success', 'delivered' => 'primary'][$rma['status']] ?? 'secondary' ?> p-2 text-dark">
                    <?= strtoupper($rma['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-sm-6">
                        <p class="mb-1 text-muted small">PROBLEM DESCRIPTION</p>
                        <div class="p-3 bg-light border rounded">
                            <?= nl2br(htmlspecialchars($rma['problem_description'])) ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <p class="mb-1 text-muted small">INTERNAL NOTES</p>
                        <div class="p-3 bg-light border rounded italic">
                            <?= $rma['notes'] ? nl2br(htmlspecialchars($rma['notes'])) : '<span class="text-muted">No notes available</span>' ?>
                        </div>
                    </div>
                </div>

                <!-- NEW: Advanced Lifecycle Stages -->
                <div class="row">
                    <!-- Stage 1: Testing -->
                    <div class="col-md-6 mb-3">
                        <div class="p-2 border rounded border-left-primary h-100">
                            <h6 class="small font-weight-bold text-primary">1. TESTING</h6>
                            <?php if ($rma['testing_date']): ?>
                                <p class="mb-1 small"><strong>Date:</strong> <?= format_date($rma['testing_date']) ?></p>
                                <p class="mb-0 small italic text-muted"><?= htmlspecialchars($rma['testing_note'] ?? 'No notes') ?></p>
                            <?php else: ?>
                                <p class="small text-muted italic">Pending testing</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Stage 2: Sent to Brand -->
                    <div class="col-md-6 mb-3">
                        <div class="p-2 border rounded border-left-info h-100">
                            <h6 class="small font-weight-bold text-info">2. REPLACE OUT (BRAND)</h6>
                            <?php if ($rma['replace_out_date']): ?>
                                <p class="mb-1 small"><strong>Out No:</strong> <?= htmlspecialchars($rma['replace_out_number'] ?? 'N/A') ?></p>
                                <p class="mb-0 small"><strong>Date:</strong> <?= format_date($rma['replace_out_date']) ?></p>
                            <?php else: ?>
                                <p class="small text-muted italic">Pending dispatch</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Stage 3: Received from Brand -->
                    <div class="col-md-6 mb-3">
                        <div class="p-2 border rounded border-left-success h-100">
                            <h6 class="small font-weight-bold text-success">3. REPLACE IN (RESOLUTION)</h6>
                            <?php if ($rma['replace_in_date']): ?>
                                <p class="mb-1 small"><strong>In No:</strong> <?= htmlspecialchars($rma['replace_in_number'] ?? 'N/A') ?></p>
                                <p class="mb-1 small"><strong>Date:</strong> <?= format_date($rma['replace_in_date']) ?></p>
                                <p class="mb-0 small"><strong>Type:</strong> 
                                    <span class="badge bg-<?= $rma['replace_in_type'] == 'new' ? 'primary' : 'success' ?> text-dark">
                                        <?= strtoupper($rma['replace_in_type']) ?>
                                    </span>
                                </p>
                                <?php if ($rma['new_serial_no']): ?>
                                    <p class="mt-1 mb-0 small text-primary font-weight-bold">New Serial: <?= $rma['new_serial_no'] ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="small text-muted italic">Pending resolution</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Stage 4: Delivered -->
                    <div class="col-md-6 mb-3">
                        <div class="p-2 border rounded border-left-dark h-100">
                            <h6 class="small font-weight-bold">4. DELIVERY</h6>
                            <?php if ($rma['delivery_date']): ?>
                                <p class="mb-1 small"><strong>Del No:</strong> <?= htmlspecialchars($rma['delivery_number'] ?? 'N/A') ?></p>
                                <p class="mb-0 small"><strong>Date:</strong> <?= format_date($rma['delivery_date']) ?></p>
                            <?php else: ?>
                                <p class="small text-muted italic">Pending delivery</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row border-top pt-3 mt-2">
                    <div class="col-sm-4">
                        <p class="mb-0 text-muted small">CREATED DATE</p>
                        <p class="font-weight-bold"><?= format_date($rma['created_date']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product & Warranty Info -->
        <div class="card shadow mb-4 border-left-info">
            <div class="card-body">
                <h6 class="font-weight-bold text-info mb-3">
                    Product & Warranty Detail
                    <?php if ($replacement_product): ?>
                        <span class="badge bg-success text-white ml-2" style="font-size: 0.75rem;">
                            <i class="fas fa-exchange-alt"></i> Replacement Product
                        </span>
                    <?php endif; ?>
                </h6>
                <div class="row">
                    <div class="col-sm-6">
                        <table class="table table-sm table-borderless">
                            <tr><td width="40%">Product:</td><td class="font-weight-bold"><?= htmlspecialchars($rma['product_name'] ?? '') ?></td></tr>
                            <tr><td>Product Code:</td><td><?= htmlspecialchars($rma['product_code'] ?? '') ?></td></tr>
                            <tr><td>Serial Number:</td><td class="text-primary font-weight-bold"><?= htmlspecialchars($rma['serial_number'] ?? 'N/A') ?></td></tr>
                            <tr><td>IMEI:</td><td><?= htmlspecialchars($rma['imei'] ?? 'N/A') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-sm-6">
                        <table class="table table-sm table-borderless">
                            <tr><td width="40%">Sale Date:</td><td><?= !empty($rma['sale_date']) ? format_date($rma['sale_date']) : '-' ?></td></tr>
                            <tr><td>Warranty:</td><td><?= $rma['warranty_months'] ?> Months</td></tr>
                            <tr><td>Expiry:</td><td><?= !empty($rma['sale_date']) ? date('d M Y', strtotime("+{$rma['warranty_months']} months", strtotime($rma['sale_date']))) : '-' ?></td></tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($replacement_product): ?>
                    <!-- Replacement Product Information -->
                    <div class="border-top pt-3 mt-2">
                        <h6 class="font-weight-bold text-success mb-2">
                            <i class="fas fa-redo"></i> Replacement Product Details
                        </h6>
                        <div class="row">
                            <div class="col-sm-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="40%" class="text-muted">Replacement Product:</td>
                                        <td class="font-weight-bold text-success">
                                            <?= htmlspecialchars($replacement_product['product_name']) ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Replacement Serial:</td>
                                        <td class="text-success font-weight-bold">
                                            <?= htmlspecialchars($replacement_product['serial_number']) ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($replacement_product['brand_name'])): ?>
                                        <tr>
                                            <td class="text-muted">Brand:</td>
                                            <td><?= htmlspecialchars($replacement_product['brand_name']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Customer Info -->
        <div class="card shadow mb-4 border-left-primary">
            <div class="card-body">
                <h6 class="font-weight-bold text-primary mb-3"><i class="fas fa-user"></i> Customer Info</h6>
                <p class="mb-1 font-weight-bold text-lg"><?= htmlspecialchars($rma['customer_name'] ?? 'Walk-in Customer') ?></p>
                <p class="mb-1"><i class="fas fa-phone text-muted"></i> <?= htmlspecialchars($rma['customer_phone'] ?? 'N/A') ?></p>
                <p class="mb-0 small"><i class="fas fa-map-marker-alt text-muted"></i> <?= htmlspecialchars($rma['customer_address'] ?? 'N/A') ?></p>
            </div>
        </div>

        <!-- Service Center Info -->
        <div class="card shadow mb-4 border-left-warning">
            <div class="card-body">
                <h6 class="font-weight-bold text-warning mb-3"><i class="fas fa-tools"></i> Service Center</h6>
                <?php if ($rma['service_center_id']): ?>
                    <p class="mb-1 font-weight-bold"><?= htmlspecialchars($rma['sc_name']) ?></p>
                    <p class="mb-1 small"><i class="fas fa-phone text-muted"></i> <?= htmlspecialchars($rma['sc_phone'] ?? 'N/A') ?></p>
                    <p class="mb-0 small text-muted"><?= htmlspecialchars($rma['sc_address'] ?? 'N/A') ?></p>
                <?php else: ?>
                    <p class="text-muted italic">Handled internally / No external service center assigned.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4 no-print">
            <a href="rma-edit.php?id=<?= $id ?>" class="btn btn-warning btn-block mb-2"><i class="fas fa-edit"></i> Edit / Update Status</a>
            <button onclick="window.print()" class="btn btn-primary btn-block mb-2"><i class="fas fa-print"></i> Print Receipt</button>
            <a href="rma-list.php" class="btn btn-secondary btn-block">Back to List</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
