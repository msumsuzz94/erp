<?php
/**
 * Sale View/Invoice Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$sale_id = (int)get_param('id');

// Get sale details
$sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
        u.username as created_by_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.id = ?";
$sale = db_query_one($sql, [$sale_id]);

if (!$sale) {
    redirect_with_message('sales-list.php', 'Sale not found', 'error');
}

// Get sale items
$sql = "SELECT si.*, p.name as product_name, p.code as product_code, p.warranty_duration, p.warranty_period
        FROM sale_items si
        INNER JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?";
$sale_items = db_query($sql, [$sale_id]);

// Get serial numbers for this sale
$serials_raw = db_query("SELECT * FROM product_serials WHERE sale_id = ?", [$sale_id]);
$product_serials = [];
foreach ($serials_raw as $s) {
    $product_serials[$s['product_id']][] = $s['serial_number'];
}

// Get payments
$payments = db_select('sale_payments', ['sale_id' => $sale_id], '*', 'created_at ASC');

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


$page_title = 'Invoice #' . $sale['invoice_number'];
$page_actions = '<a href="invoice-print.php?id=' . $sale_id . '" target="_blank" class="btn btn-success"><i class="fas fa-file-invoice"></i> Print Invoice</a>
                 <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Page</button>
                 <a href="sales-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>';
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
    
    /* Hide regular UI AND standard print header */
    .no-print, .btn, .card, .navbar, .sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, #standard-print-wrapper, #standard-print-footer {
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
    .print-table th, .print-table td { border: 1px solid #333 !important; padding: 6px; text-align: left; }
    .print-table th { background: #f2f2f2 !important; font-weight: bold; text-transform: uppercase; }
    .text-end { text-align: right !important; }
    .text-center { text-align: center !important; }
    
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

        <div class="report-main-title">SALES INVOICE - <?= htmlspecialchars($sale['invoice_number']) ?></div>

        <!-- Invoice Info -->
        <table class="print-table" style="margin-bottom: 10px;">
            <tr>
                <td style="width: 50%;"><strong>Date:</strong> <?= format_date($sale['sale_date']) ?></td>
                <td style="width: 50%;"><strong>Status:</strong> <?= ucfirst($sale['status']) ?></td>
            </tr>
            <tr>
                <td colspan="2">
                    <strong>Customer:</strong> 
                    <?php if ($sale['customer_id']): ?>
                        <?= htmlspecialchars($sale['customer_name']) ?>
                        <?php if ($sale['customer_phone']): ?>
                            | Phone: <?= htmlspecialchars($sale['customer_phone']) ?>
                        <?php endif; ?>
                        <?php if ($sale['customer_address']): ?>
                            | Address: <?= htmlspecialchars($sale['customer_address']) ?>
                        <?php endif; ?>
                    <?php else: ?>
                        Walk-in Customer
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Code</th>
                    <th class="text-end">Price</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Tax</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($sale_items as $item): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <?= htmlspecialchars($item['product_name']) ?>
                            <?php 
                            $cancelled_serials = '';
                            if (!empty($item['description']) && strpos($item['description'], 'Cancelled Serials:') !== false) {
                                $parts = explode('Cancelled Serials:', $item['description']);
                                if (isset($parts[1])) {
                                    $cancelled_serials = trim($parts[1]);
                                }
                            }
                            ?>
                            <?php if (!empty($product_serials[$item['product_id']])): ?>
                                <div style="margin-top: 2px;">
                                    <small style="color: #666;"><strong>Serials:</strong> 
                                        <?= implode(', ', array_map('htmlspecialchars', $product_serials[$item['product_id']])) ?>
                                    </small>
                                </div>
                            <?php elseif (!empty($cancelled_serials)): ?>
                                <div style="margin-top: 2px;">
                                    <small style="color: #dc3545;"><strong>Cancelled Serials:</strong> 
                                        <?= htmlspecialchars($cancelled_serials) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['warranty_duration'])): ?>
                                <div style="margin-top: 2px;">
                                    <small style="color: #666;"><strong>Warranty:</strong> 
                                        <?= htmlspecialchars($item['warranty_duration'] . ' ' . $item['warranty_period']) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($item['product_code']) ?></td>
                        <td class="text-end"><?= format_currency($item['unit_price']) ?></td>
                        <td class="text-center"><?= $item['quantity'] ?></td>
                        <td class="text-end"><?= format_currency($item['tax']) ?></td>
                        <td class="text-end"><?= format_currency($item['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="text-end"><strong>Subtotal:</strong></td>
                    <td class="text-end"><?= format_currency($sale['total_amount'] - $sale['tax_amount'] + $sale['discount']) ?></td>
                </tr>
                <tr>
                    <td colspan="6" class="text-end"><strong>Tax:</strong></td>
                    <td class="text-end"><?= format_currency($sale['tax_amount']) ?></td>
                </tr>
                <?php if ($sale['discount'] > 0): ?>
                <tr>
                    <td colspan="6" class="text-end"><strong>Discount:</strong></td>
                    <td class="text-end">-<?= format_currency($sale['discount']) ?></td>
                </tr>
                <?php endif; ?>
                <tr style="background:#f2f2f2;">
                    <td colspan="6" class="text-end"><strong>Total Amount:</strong></td>
                    <td class="text-end"><strong><?= format_currency($sale['total_amount']) ?></strong></td>
                </tr>
                <tr>
                    <td colspan="6" class="text-end"><strong>Paid Amount:</strong></td>
                    <td class="text-end"><?= format_currency($sale['paid_amount']) ?></td>
                </tr>
                <tr style="background: <?= $sale['due_amount'] > 0 ? '#fff3f3' : '#dff0d8' ?>;">
                    <td colspan="6" class="text-end"><strong>Due Amount:</strong></td>
                    <td class="text-end"><strong><?= format_currency($sale['due_amount']) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div style="margin-bottom: 20px;">
            <strong style="font-size: 12px;">Payment History</strong>
            <table class="print-table" style="margin-top: 5px;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= format_date($payment['payment_date']) ?></td>
                            <td><?= format_currency($payment['amount']) ?></td>
                            <td><?= ucfirst($payment['payment_method']) ?></td>
                            <td><?= htmlspecialchars($payment['reference'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if (!empty($sale['notes'])): ?>
        <div style="margin-bottom: 20px;">
            <strong>Notes:</strong><br>
            <?= nl2br(htmlspecialchars($sale['notes'])) ?>
        </div>
        <?php endif; ?>

        <!-- Thank You Message -->
        <div style="text-align: center; margin-top: 20px; margin-bottom: 30px;">
            <p style="margin: 0;"><small>Thank you for your business!</small></p>
            <p style="margin: 0;"><small>Created by: <?= htmlspecialchars($sale['created_by_name']) ?> on <?= format_datetime($sale['created_at']) ?></small></p>
        </div>

        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body">
        <!-- Invoice Header -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h3><?= BUSINESS_NAME ?></h3>
                <p class="mb-0"><?= BUSINESS_ADDRESS ?></p>
                <p class="mb-0">Phone: <?= BUSINESS_PHONE ?></p>
                <p class="mb-0">Email: <?= BUSINESS_EMAIL ?></p>
            </div>
            <div class="col-md-6 text-end">
                <h2>INVOICE</h2>
                <p class="mb-0"><strong>Invoice #:</strong> <?= htmlspecialchars($sale['invoice_number']) ?></p>
                <p class="mb-0"><strong>Date:</strong> <?= format_date($sale['sale_date']) ?></p>
                <p class="mb-0"><strong>Status:</strong> 
                    <span class="badge bg-<?= $sale['status'] === 'completed' ? 'success' : 'secondary' ?>">
                        <?= ucfirst($sale['status']) ?>
                    </span>
                </p>
            </div>
        </div>
        
        <hr>
        
        <!-- Customer Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Bill To:</h5>
                <?php if ($sale['customer_id']): ?>
                    <p class="mb-0"><strong><?= htmlspecialchars($sale['customer_name']) ?></strong></p>
                    <?php if ($sale['customer_phone']): ?>
                        <p class="mb-0">Phone: <?= htmlspecialchars($sale['customer_phone']) ?></p>
                    <?php endif; ?>
                    <?php if ($sale['customer_address']): ?>
                        <p class="mb-0"><?= htmlspecialchars($sale['customer_address']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="mb-0">Walk-in Customer</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Items Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Code</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($sale_items as $item): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <?= htmlspecialchars($item['product_name']) ?>
                                <?php 
                                $cancelled_serials = '';
                                if (!empty($item['description']) && strpos($item['description'], 'Cancelled Serials:') !== false) {
                                    $parts = explode('Cancelled Serials:', $item['description']);
                                    if (isset($parts[1])) {
                                        $cancelled_serials = trim($parts[1]);
                                    }
                                }
                                ?>
                                <?php if (!empty($product_serials[$item['product_id']])): ?>
                                    <div class="mt-1">
                                        <small class="text-muted"><strong>Serials:</strong> 
                                            <?= implode(', ', array_map('htmlspecialchars', $product_serials[$item['product_id']])) ?>
                                        </small>
                                    </div>
                                <?php elseif (!empty($cancelled_serials)): ?>
                                    <div class="mt-1">
                                        <small class="text-danger"><strong>Cancelled Serials:</strong> 
                                            <?= htmlspecialchars($cancelled_serials) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($item['warranty_duration'])): ?>
                                    <div class="mt-1">
                                        <small class="text-muted"><strong>Warranty:</strong> 
                                            <?= htmlspecialchars($item['warranty_duration'] . ' ' . $item['warranty_period']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['product_code']) ?></td>
                            <td class="text-end"><?= format_currency($item['unit_price']) ?></td>
                            <td class="text-center"><?= $item['quantity'] ?></td>
                            <td class="text-end"><?= format_currency($item['tax']) ?></td>
                            <td class="text-end"><?= format_currency($item['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end"><?= format_currency($sale['total_amount'] - $sale['tax_amount'] + $sale['discount']) ?></td>
                    </tr>
                    <tr>
                        <td colspan="6" class="text-end"><strong>Tax:</strong></td>
                        <td class="text-end"><?= format_currency($sale['tax_amount']) ?></td>
                    </tr>
                    <?php if ($sale['discount'] > 0): ?>
                    <tr>
                        <td colspan="6" class="text-end"><strong>Discount:</strong></td>
                        <td class="text-end">-<?= format_currency($sale['discount']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="table-primary">
                        <td colspan="6" class="text-end"><strong>Total Amount:</strong></td>
                        <td class="text-end"><strong><?= format_currency($sale['total_amount']) ?></strong></td>
                    </tr>
                    <tr>
                        <td colspan="6" class="text-end"><strong>Paid Amount:</strong></td>
                        <td class="text-end"><?= format_currency($sale['paid_amount']) ?></td>
                    </tr>
                    <tr class="<?= $sale['due_amount'] > 0 ? 'table-danger' : 'table-success' ?>">
                        <td colspan="6" class="text-end"><strong>Due Amount:</strong></td>
                        <td class="text-end"><strong><?= format_currency($sale['due_amount']) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div class="mb-4">
            <h5>Payment History</h5>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= format_date($payment['payment_date']) ?></td>
                            <td><?= format_currency($payment['amount']) ?></td>
                            <td><?= ucfirst($payment['payment_method']) ?></td>
                            <td><?= htmlspecialchars($payment['reference'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Notes -->
        <?php if (!empty($sale['notes'])): ?>
        <div class="mb-4">
            <strong>Notes:</strong>
            <p><?= nl2br(htmlspecialchars($sale['notes'])) ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="row mt-5">
            <div class="col-md-12 text-center">
                <p class="mb-0"><small>Thank you for your business!</small></p>
                <p class="mb-0"><small>Created by: <?= htmlspecialchars($sale['created_by_name']) ?> on <?= format_datetime($sale['created_at']) ?></small></p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
