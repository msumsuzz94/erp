<?php
/**
 * Bill Collection Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle payment submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        $sale_id = (int)$_POST['sale_id'];
        $amount = (float)$_POST['amount'];
        $payment_method = clean_input($_POST['payment_method']);
        $account_id = (int)$_POST['account_id'];
        $payment_date = clean_input($_POST['payment_date']);
        $reference = clean_input($_POST['reference']);
        $notes = clean_input($_POST['notes']);
        
        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }
        
        if (empty($account_id)) {
            $errors[] = 'Please select an account for deposit';
        }
        
        // Get sale details
        $sale = db_select_one('sales', ['id' => $sale_id]);
        
        if (!$sale) {
            $errors[] = 'Sale not found';
        } else {
            if ($amount > $sale['due_amount']) {
                $errors[] = 'Payment amount (' . format_currency($amount) . ') exceeds the current due amount (' . format_currency($sale['due_amount']) . ')';
            }
        }
        
        // Validate account exists
        if (!empty($account_id)) {
            $account_table = ($payment_method === 'cash') ? 'cash_accounts' : 'bank_accounts';
            $account = db_select_one($account_table, ['id' => $account_id]);
            
            if (!$account) {
                $errors[] = 'Selected account not found';
            }
        }
        
        if (empty($errors)) {
            // Insert payment
            $payment_data = [
                'sale_id' => $sale_id,
                'payment_date' => $payment_date,
                'amount' => $amount,
                'payment_method' => $payment_method,
                'account_id' => $account_id,
                'reference' => $reference,
                'notes' => $notes
            ];
            
            $payment_id = db_insert('sale_payments', $payment_data);
            
            if ($payment_id) {
                // Update sale paid and due amounts
                $new_paid = $sale['paid_amount'] + $amount;
                $new_due = $sale['due_amount'] - $amount;
                $payment_status = $new_due <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');
                
                db_update('sales', 
                    [
                        'paid_amount' => $new_paid,
                        'due_amount' => $new_due,
                        'payment_status' => $payment_status
                    ],
                    ['id' => $sale_id]
                );
                
                // Update customer ledger if customer exists
                if ($sale['customer_id']) {
                    $ledger_data = [
                        'customer_id' => $sale['customer_id'],
                        'transaction_type' => 'payment',
                        'reference_id' => $payment_id,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => "Payment for Invoice #{$sale['invoice_number']}",
                        'date' => $payment_date
                    ];
                    
                    db_insert('customer_ledger', $ledger_data);
                    
                    // Update customer balance
                    $customer = db_select_one('customers', ['id' => $sale['customer_id']]);
                    $new_balance = $customer['current_balance'] - $amount;
                    db_update('customers', ['current_balance' => $new_balance], ['id' => $sale['customer_id']]);
                }
                
                // Add to account balance (customer paying us!)
                $account_table = ($payment_method === 'cash') ? 'cash_accounts' : 'bank_accounts';
                $account = db_select_one($account_table, ['id' => $account_id]);
                db_update($account_table, [
                    'current_balance' => $account['current_balance'] + $amount
                ], ['id' => $account_id]);
                
                // Record transaction in account ledger as CREDIT
                $trans_table = ($payment_method === 'cash') ? 'cash_transactions' : 'bank_transactions';
                $customer_name = $sale['customer_id'] ? $customer['name'] : 'Walk-in';
                db_insert($trans_table, [
                    'account_id' => $account_id,
                    'transaction_type' => 'credit',
                    'amount' => $amount,
                    'reference_type' => 'bill_collection',
                    'reference_id' => $payment_id,
                    'description' => "Bill collection from {$customer_name} for Invoice #{$sale['invoice_number']}",
                    'transaction_date' => $payment_date,
                    'created_by' => get_current_user_id()
                ]);
                
                log_activity(get_current_user_id(), 'bill_collection', "Payment of " . format_currency($amount) . " for Invoice #{$sale['invoice_number']}");
                redirect_with_message($_SERVER['PHP_SELF'], 'Payment recorded successfully', 'success');
            } else {
                $errors[] = 'Failed to record payment';
            }
        }
    }
}


// Get pending/partial sales (limited to 5 for display)
$sql = "SELECT s.*, c.name as customer_name 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE s.payment_status IN ('unpaid', 'partial') AND s.status = 'completed'
        ORDER BY s.sale_date DESC
        LIMIT 5";
$pending_sales = db_query($sql);

// Get all pending sales for the dropdown (not limited)
$sql_all = "SELECT s.*, c.name as customer_name 
            FROM sales s 
            LEFT JOIN customers c ON s.customer_id = c.id 
            WHERE s.payment_status IN ('unpaid', 'partial') AND s.status = 'completed'
            ORDER BY s.sale_date DESC";
$all_pending_sales = db_query($sql_all);

// Get customers for filter
$customers = db_query("SELECT id, name FROM customers ORDER BY name ASC");

// Filter invoices by customer if selected
$filter_customer_id = get_param('filter_customer', '');
$filter_status = get_param('filter_status', '');
$filter_from_date = get_param('filter_from_date', '');
$filter_to_date = get_param('filter_to_date', '');

// Build payment subquery for date-sensitive payment calculation
$payment_subquery = "SELECT COALESCE(SUM(sp.amount), 0) FROM sale_payments sp WHERE sp.sale_id = s.id";
$sub_params = [];
if (!empty($filter_from_date)) {
    $payment_subquery .= " AND sp.payment_date >= ?";
    $sub_params[] = $filter_from_date;
}
if (!empty($filter_to_date)) {
    $payment_subquery .= " AND sp.payment_date <= ?";
    $sub_params[] = $filter_to_date;
}

$invoice_sql = "SELECT s.*, c.name as customer_name, 
                ($payment_subquery) as range_paid_amount 
                FROM sales s 
                LEFT JOIN customers c ON s.customer_id = c.id 
                WHERE s.status = 'completed'";
$invoice_params = $sub_params;

if (!empty($filter_customer_id)) {
    $invoice_sql .= " AND s.customer_id = ?";
    $invoice_params[] = $filter_customer_id;
}

if (!empty($filter_status)) {
    $invoice_sql .= " AND s.payment_status = ?";
    $invoice_params[] = $filter_status;
}

if (!empty($filter_from_date)) {
    $invoice_sql .= " AND s.sale_date >= ?";
    $invoice_params[] = $filter_from_date;
}

if (!empty($filter_to_date)) {
    $invoice_sql .= " AND s.sale_date <= ?";
    $invoice_params[] = $filter_to_date;
}

$invoice_sql .= " ORDER BY s.sale_date DESC";
$filtered_invoices = db_query($invoice_sql, $invoice_params);


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

$page_title = 'Bill Collection';
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

        <div class="report-main-title">Bill Collection Report (Date: <?= date('d M Y') ?>)</div>

        <table class="print-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Payment Status</th>
                    <th class="text-end">Collected Amount (Range)</th>
                    <th class="text-end">Current Due</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_range_paid = 0;
                $total_due = 0;
                foreach ($filtered_invoices as $invoice): 
                    $total_range_paid += $invoice['range_paid_amount'];
                    $total_due += $invoice['due_amount'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                        <td><?= htmlspecialchars($invoice['customer_name'] ?? 'Walk-in') ?></td>
                        <td><?= ucfirst($invoice['payment_status']) ?></td>
                        <td class="text-end"><?= format_currency($invoice['range_paid_amount']) ?></td>
                        <td class="text-end"><?= format_currency($invoice['due_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f2f2f2;">
                    <th colspan="3" class="text-end">TOTAL</th>
                    <th class="text-end"><?= format_currency($total_range_paid) ?></th>
                    <th class="text-end"><?= format_currency($total_due) ?></th>
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

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Payment Form -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Record Payment</h6>
            </div>
            <div class="card-body">
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="form-group mb-3">
                        <label>Select Sale <span class="text-danger">*</span></label>
                        <select name="sale_id" id="sale_id" class="form-control select2" required>
                            <option value="">Select Sale</option>
                            <?php foreach ($all_pending_sales as $sale): ?>
                                <option value="<?= $sale['id'] ?>" 
                                        data-due="<?= $sale['due_amount'] ?>"
                                        data-customer="<?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?>">
                                    <?= $sale['invoice_number'] ?> - <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?> 
                                    (Due: <?= format_currency($sale['due_amount']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Due Amount</label>
                        <input type="text" id="due_amount" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Payment Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="amount" class="form-control" 
                               step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="credit">Credit</option>
                            <option value="card">Card</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Deposit To Account <span class="text-danger">*</span></label>
                        <select name="account_id" id="account_id" class="form-control" required>
                            <option value="">Select Account</option>
                        </select>
                        <small class="form-text text-muted">Account will be credited automatically</small>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Reference/Transaction No.</label>
                        <input type="text" name="reference" class="form-control">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Record Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Pending Sales -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Pending Bill</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_sales)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No pending sales</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_sales as $sale): ?>
                                    <tr>
                                        <td><?= $sale['invoice_number'] ?></td>
                                        <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                        <td><?= format_currency($sale['total_amount']) ?></td>
                                        <td class="text-danger"><?= format_currency($sale['due_amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Customer Invoice Search Section -->
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Customer Invoice Search</h6>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" action="" class="mb-4">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold">Select Customer</label>
                                <select name="filter_customer" class="form-control select2">
                                    <option value="">All Customers</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>" <?= $filter_customer_id == $customer['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($customer['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold">Payment Status</label>
                                <select name="filter_status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="partial" <?= $filter_status === 'partial' ? 'selected' : '' ?>>Partial</option>
                                    <option value="unpaid" <?= $filter_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold">From Date</label>
                                <input type="date" name="filter_from_date" class="form-control" value="<?= htmlspecialchars($filter_from_date) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-0">
                                <label class="small font-weight-bold">To Date</label>
                                <input type="date" name="filter_to_date" class="form-control" value="<?= htmlspecialchars($filter_to_date) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <button type="button" onclick="window.print()" class="btn btn-success flex-grow-1">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary flex-grow-1">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Invoice Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="invoicesTable">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Payment Type</th>
                                <th>Payment</th>
                                <th>Due</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($filtered_invoices)): ?>
                                <?php foreach ($filtered_invoices as $invoice): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                        <td><?= htmlspecialchars($invoice['customer_name'] ?? 'Walk-in') ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'paid' => 'success',
                                                'partial' => 'warning',
                                                'unpaid' => 'danger'
                                            ];
                                            $color = $status_colors[$invoice['payment_status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-<?= $color ?>">
                                                <?= ucfirst($invoice['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-success font-weight-bold"><?= format_currency($invoice['range_paid_amount']) ?></td>
                                        <td class="<?= $invoice['due_amount'] > 0 ? 'text-danger font-weight-bold' : '' ?>">
                                            <?= format_currency($invoice['due_amount']) ?>
                                        </td>
                                        <td>
                                            <a href="sale-view.php?id=<?= $invoice['id'] ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($invoice['due_amount'] > 0): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-success select-invoice-btn" 
                                                        data-id="<?= $invoice['id'] ?>"
                                                        data-invoice="<?= htmlspecialchars($invoice['invoice_number']) ?>"
                                                        data-due="<?= $invoice['due_amount'] ?>"
                                                        title="Select for Payment">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Initialize DataTable for invoices
    if ($.fn.DataTable.isDataTable('#invoicesTable')) {
        $('#invoicesTable').DataTable().destroy();
    }
    
    $('#invoicesTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]], // Sort by Invoice # or SL descending
        "columns": [
            { "orderable": true },  // Invoice #
            { "orderable": true },  // Customer
            { "orderable": true },  // Payment Type
            { "orderable": true },  // Payment
            { "orderable": true },  // Due
            { "orderable": false }  // Actions
        ],
        "responsive": true
    });
    
    // Original due amount updater
    $('#sale_id').change(function() {
        var selected = $(this).find(':selected');
        var dueAmount = selected.data('due');
        
        if (dueAmount) {
            $('#due_amount').val('<?= APP_CURRENCY_SYMBOL ?>' + parseFloat(dueAmount).toFixed(2));
            $('#amount').attr('max', dueAmount);
            $('#amount').val(dueAmount);
        } else {
            $('#due_amount').val('');
            $('#amount').val('');
        }
    });
    
    // Select invoice from search table
    $('.select-invoice-btn').click(function() {
        var invoiceId = $(this).data('id');
        var invoiceNumber = $(this).data('invoice');
        var dueAmount = $(this).data('due');
        
        // Set the select dropdown
        $('#sale_id').val(invoiceId).trigger('change');
        
        // Scroll to payment form
        $('html, body').animate({
            scrollTop: $("#paymentForm").offset().top - 100
        }, 500);
        
        // Show notification
        showAlert('Invoice ' + invoiceNumber + ' selected for payment', 'info');
    });
});

function showAlert(message, type = 'info') {
    var alertClass = 'alert-' + type;
    var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                    message +
                    '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                    '</div>';
    
    $('body').prepend(alertHtml);
    
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 3000);
}

// Load accounts when payment method changes
$('#payment_method').change(function() {
    var method = $(this).val();
    loadAccounts(method);
});

// Load accounts on page load (default to cash)
loadAccounts('cash');

function loadAccounts(paymentMethod) {
    var accountSelect = $('#account_id');
    accountSelect.html('<option value="">Loading...</option>');
    
    $.get('../../api/accounts/get-accounts.php', { type: paymentMethod }, function(res) {
        if (res.status) {
            accountSelect.html('<option value="">Select Account</option>');
            if (res.data && res.data.length > 0) {
                res.data.forEach(function(acc) {
                    var name = paymentMethod === 'cash' ? acc.account_name : 
                               acc.bank_name + ' (' + acc.account_number + ')';
                    var balance = parseFloat(acc.current_balance).toFixed(2);
                    accountSelect.append(
                        '<option value="' + acc.id + '" data-balance="' + balance + '">' + 
                        name + ' (Balance: <?= APP_CURRENCY_SYMBOL ?>' + balance + ')</option>'
                    );
                });
            } else {
                accountSelect.html('<option value="">No accounts available</option>');
            }
        } else {
            accountSelect.html('<option value="">Error loading accounts</option>');
        }
    }).fail(function() {
        accountSelect.html('<option value="">Error loading accounts</option>');
    });
}
</script>
