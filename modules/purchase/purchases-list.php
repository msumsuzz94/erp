<?php
/**
 * Purchases List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $purchase_id = (int)$_POST['delete_id'];
        
        // Delete purchase (items will be deleted via CASCADE)
        if (db_delete('purchases', ['id' => $purchase_id])) {
            log_activity(get_current_user_id(), 'delete_purchase', "Deleted purchase ID: $purchase_id");
            redirect_with_message($_SERVER['PHP_SELF'], 'Purchase deleted successfully', 'success');
        } else {
            redirect_with_message($_SERVER['PHP_SELF'], 'Failed to delete purchase', 'error');
        }
    }
}

$search = get_param('search', '');
$supplier_id = get_param('supplier_id', '');
$status = get_param('status', '');
$payment_status = get_param('payment_status', '');
$from_date = get_param('from_date', '');
$to_date = get_param('to_date', '');

$sql = "SELECT p.*, s.name as supplier_name
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (p.purchase_number LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($supplier_id)) {
    $sql .= " AND p.supplier_id = ?";
    $params[] = $supplier_id;
}

if (!empty($status)) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

if (!empty($payment_status)) {
    $sql .= " AND p.payment_status = ?";
    $params[] = $payment_status;
}

if (!empty($from_date)) {
    $sql .= " AND p.purchase_date >= ?";
    $params[] = $from_date;
}

if (!empty($to_date)) {
    $sql .= " AND p.purchase_date <= ?";
    $params[] = $to_date;
}

$sql .= " ORDER BY p.created_at DESC";
$purchases = db_query($sql, $params);

// Get suppliers for filter
$suppliers_list = db_query("SELECT id, name FROM suppliers ORDER BY name ASC");

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

$page_title = 'Purchases';
$page_actions = '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <a href="purchase-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Purchase</a>';

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
        font-family: "Segoe UI", Arial, sans-serif; 
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

        <div class="report-main-title">Purchases Report (Date: <?= date('d M Y') ?>) <?= !empty($from_date) || !empty($to_date) ? " - Range: $from_date to $to_date" : "" ?></div>

        <table class="print-table">
            <thead>
                <tr>
                    <th>Purchase #</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th class="text-end">Total Amount</th>
                    <th>Payment</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($purchases)): ?>
                    <tr><td colspan="6" style="text-align:center;">No purchases found</td></tr>
                <?php else: ?>
                    <?php 
                    $total_purchase_amount = 0;
                    foreach ($purchases as $purchase): 
                        $total_purchase_amount += $purchase['total_amount'];
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($purchase['purchase_number'] ?? '#' . $purchase['id']) ?></td>
                            <td><?= date('d M Y', strtotime($purchase['purchase_date'])) ?></td>
                            <td><?= htmlspecialchars($purchase['supplier_name'] ?? 'N/A') ?></td>
                            <td class="text-end"><?= format_currency($purchase['total_amount']) ?></td>
                            <td><?= ucfirst($purchase['payment_status'] ?? 'unpaid') ?></td>
                            <td><?= ucfirst($purchase['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f2f2f2;">
                    <th colspan="3" class="text-end">TOTAL</th>
                    <th class="text-end"><?= format_currency($total_purchase_amount) ?></th>
                    <th colspan="2"></th>
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
        <h6 class="m-0 font-weight-bold text-primary">Filter Purchases</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Purchase #, Supplier" value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Supplier</label>
                        <select name="supplier_id" class="form-control select2">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers_list as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= (string)$supplier_id === (string)$s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Status</label>
                        <div class="d-flex gap-1">
                            <select name="status" class="form-control">
                                <option value="">Status</option>
                                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                            </select>
                            <select name="payment_status" class="form-control">
                                <option value="">Payment</option>
                                <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="partial" <?= $payment_status === 'partial' ? 'selected' : '' ?>>Partial</option>
                                <option value="unpaid" <?= $payment_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date Range</label>
                        <div class="input-group">
                            <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                            <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Purchases List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="purchasesTable">
                <thead>
                    <tr>
                        <th>Purchase #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Total Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($purchases)): ?>
                        <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td><?= htmlspecialchars($purchase['purchase_number'] ?? '#' . $purchase['id']) ?></td>
                                <td><?= format_date($purchase['purchase_date']) ?></td>
                                <td><?= htmlspecialchars($purchase['supplier_name'] ?? 'N/A') ?></td>
                                <td><?= format_currency($purchase['total_amount']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $purchase['payment_status'] === 'paid' ? 'success' : ($purchase['payment_status'] === 'partial' ? 'warning' : 'danger') ?>">
                                        <?= ucfirst($purchase['payment_status'] ?? 'unpaid') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $purchase['status'] === 'completed' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($purchase['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="purchase-view.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="purchase-print.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-success" title="Print Invoice" target="_blank"><i class="fas fa-print"></i></a>
                                    <a href="purchase-edit.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                    <button type="button" class="btn btn-sm btn-danger delete-purchase" data-id="<?= $purchase['id'] ?>" data-number="<?= htmlspecialchars($purchase['purchase_number'] ?? '#' . $purchase['id']) ?>" title="Delete"><i class="fas fa-trash"></i></button>
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

<?php 
ob_start();
?>
<script>
$(document).ready(function() {
    console.log('Purchases List Script Initialized');
    
    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#purchasesTable').DataTable({
            "pageLength": 25,
            "order": [[1, "desc"]],
            "destroy": true,
            "retrieve": false,
            "stateSave": false
        });
    }

    // Use event delegation for delete button
    $(document).on('click', '.delete-purchase', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const number = $(this).data('number');
        
        console.log('Delete button clicked for purchase:', number, 'ID:', id);
        
        if (confirm('Are you sure you want to delete purchase "' + number + '"? This will also delete all related items and cannot be undone.')) {
            const form = document.getElementById('deleteForm');
            const input = document.getElementById('delete_id');
            if (form && input) {
                input.value = id;
                console.log('Submitting delete form for ID:', id);
                form.submit();
            } else {
                console.error('Delete form or input not found');
            }
        }
    });
});
</script>
<?php 
$additional_js = ob_get_clean();
include __DIR__ . '/../../templates/footer.php'; 
?>
