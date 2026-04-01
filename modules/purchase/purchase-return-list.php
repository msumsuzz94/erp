<?php
/**
 * Purchase Return List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $return_id = (int)$_POST['delete_id'];
        
        if (db_delete('purchase_returns', ['id' => $return_id])) {
            log_activity(get_current_user_id(), 'delete_purchase_return', "Deleted purchase return ID: $return_id");
            redirect_with_message($_SERVER['PHP_SELF'], 'Purchase return deleted successfully', 'success');
        } else {
            redirect_with_message($_SERVER['PHP_SELF'], 'Failed to delete purchase return', 'error');
        }
    }
}

// Get all purchase returns
$sql = "SELECT pr.*, s.name as supplier_name, u.username as created_by_name
        FROM purchase_returns pr
        INNER JOIN suppliers s ON pr.supplier_id = s.id
        LEFT JOIN users u ON pr.created_by = u.id
        ORDER BY pr.created_at DESC";
$returns = db_query($sql);

// Get invoice settings for print header
$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$invoice_settings) {
    // Default settings if not found
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

$page_title = 'Purchase Returns';
$page_actions = '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <a href="purchase-return-create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Return</a>';

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
    .no-print, .btn, .card, .navbar, .sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, .summary-cards, #standard-print-wrapper, #standard-print-footer, form {
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
    .print-table th, .print-table td { border: 1px solid #333 !important; padding: 8px; text-align: left; }
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
                    <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo">
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

        <div class="report-main-title">Purchase Returns Report (Date: <?= date('d M Y') ?>)</div>

        <!-- Report Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>Return Date</th>
                    <th>Purchase #</th>
                    <th>Supplier</th>
                    <th class="text-end">Total Amount</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Created By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($returns)): ?>
                    <tr><td colspan="7" style="text-align:center;">No purchase returns found</td></tr>
                <?php else: ?>
                    <?php foreach ($returns as $return): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($return['return_date'])) ?></td>
                            <td>#<?= htmlspecialchars($return['purchase_id']) ?></td>
                            <td><?= htmlspecialchars($return['supplier_name']) ?></td>
                            <td class="text-end"><?= format_currency($return['total_amount']) ?></td>
                            <td><?= htmlspecialchars($return['reason'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(ucfirst($return['status'])) ?></td>
                            <td><?= htmlspecialchars($return['created_by_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f2f2f2;">
                    <th colspan="3" class="text-end">TOTAL</th>
                    <th class="text-end"><?= format_currency(array_sum(array_column($returns, 'total_amount'))) ?></th>
                    <th colspan="3"></th>
                </tr>
            </tfoot>
        </table>

        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Purchase Returns List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="returnsTable">
                <thead>
                    <tr>
                        <th>Return Date</th>
                        <th>Purchase #</th>
                        <th>Supplier</th>
                        <th>Total Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($returns)): ?>
                        <?php foreach ($returns as $return): ?>
                            <tr>
                                <td><?= format_date($return['return_date']) ?></td>
                                <td>
                                    <a href="purchase-view.php?id=<?= $return['purchase_id'] ?>">
                                        #<?= $return['purchase_id'] ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($return['supplier_name']) ?></td>
                                <td><?= format_currency($return['total_amount']) ?></td>
                                <td><?= htmlspecialchars(truncate($return['reason'] ?? '-', 50)) ?></td>
                                <td>
                                    <span class="badge bg-<?= $return['status'] === 'completed' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($return['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($return['created_by_name'] ?? '-') ?></td>
                                <td>
                                    <a href="purchase-return-view.php?id=<?= $return['id'] ?>" 
                                       class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteReturn(<?= $return['id'] ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#returnsTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]]
    });
});

function deleteReturn(id) {
    if (confirm('Are you sure you want to delete this purchase return?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
