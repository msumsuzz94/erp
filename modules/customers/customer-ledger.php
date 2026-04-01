<?php
/**
 * Customer Ledger Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$customer_id = (int)get_param('id', 0);

if (!$customer_id) {
    // Show customer selection list
    $customers = db_select('customers', [], '*', 'name ASC');
    $page_title = 'Select Customer for Ledger';
    include __DIR__ . '/../../templates/header.php';
    ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Select Customer</h6>
            <button onclick="window.print()" class="btn btn-sm btn-success no-print">
                <i class="fas fa-print"></i> Print List
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="customerSelectTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Current Balance</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['name']) ?></td>
                                <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($c['email'] ?? '-') ?></td>
                                <td class="text-end">
                                    <span class="<?= $c['current_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= format_currency(abs($c['current_balance'])) ?>
                                        <?= $c['current_balance'] > 0 ? '(Due)' : '(Advance)' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-book"></i> View Ledger
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../../templates/footer.php'; ?>
    <script>
    $(document).ready(function() {
        $('#customerSelectTable').DataTable({
            "pageLength": 25,
            "order": [[0, "asc"]]
        });
    });
    </script>
    <?php
    exit;
}

$customer = db_select_one('customers', ['id' => $customer_id]);

if (!$customer) {
    redirect_with_message('customers-list.php', 'Customer not found', 'error');
}

// Get invoice settings for header info
$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$invoice_settings) {
    $invoice_settings = [
        'company_name' => BUSINESS_NAME,
        'company_address' => BUSINESS_ADDRESS,
        'company_phone' => BUSINESS_PHONE,
        'company_email' => BUSINESS_EMAIL,
        'company_website' => '',
        'company_logo' => 'assets/images/logo.png',
        'company_slogan' => ''
    ];
}

// Get filters
$from_date = get_param('from_date', '');
$to_date = get_param('to_date', '');
$transaction_type = get_param('transaction_type', '');

// 1. Calculate the initial opening balance from customer record
$initial_opening_balance = (float)$customer['opening_balance']; 

// 2. Calculate balance of all transactions BEFORE $from_date
$opening_balance_sql = "SELECT SUM(debit) as total_debit, SUM(credit) as total_credit FROM customer_ledger WHERE customer_id = ?";
$opening_params = [$customer_id];
if (!empty($from_date)) {
    $opening_balance_sql .= " AND date < ?";
    $opening_params[] = $from_date;
} else {
    // If no fromDate, the opening balance is just the customer's initial opening balance
    $opening_balance_sql .= " AND 1=2"; // Force empty result for transaction sum
}
$opening_totals = db_query_one($opening_balance_sql, $opening_params);
$transactions_before = ($opening_totals['total_debit'] ?? 0) - ($opening_totals['total_credit'] ?? 0);

$opening_balance = $initial_opening_balance + $transactions_before;

// 3. Get ledger entries for the range
$sql = "SELECT * FROM customer_ledger WHERE customer_id = ?";
$params = [$customer_id];

if (!empty($from_date)) {
    $sql .= " AND date >= ?";
    $params[] = $from_date;
}
if (!empty($to_date)) {
    $sql .= " AND date <= ?";
    $params[] = $to_date;
}
if (!empty($transaction_type)) {
    $sql .= " AND transaction_type = ?";
    $params[] = $transaction_type;
}

// Fetch in ASC order for running balance calculation, we'll reverse for display if needed
// Actually, it's easier to calculate running balance in ASC then reverse the array or just show ASC.
// Ledgers are usually better in ASC. But if the user likes DESC, we calculate balance first.
$sql .= " ORDER BY date ASC, created_at ASC";
$ledger_entries = db_query($sql, $params);

$running_balance = $opening_balance;
$processed_entries = [];
$total_debit = 0;
$total_credit = 0;

foreach ($ledger_entries as $entry) {
    $running_balance += ($entry['debit'] - $entry['credit']);
    $entry['running_balance'] = $running_balance;
    $processed_entries[] = $entry;
    $total_debit += $entry['debit'];
    $total_credit += $entry['credit'];
}

// Now reverse for DESC display if preferred
$display_entries = array_reverse($processed_entries);
$final_balance = $running_balance;

// Get distinct transaction types for filter
$types_sql = "SELECT DISTINCT transaction_type FROM customer_ledger WHERE customer_id = ?";
$available_types = db_query($types_sql, [$customer_id]);

$page_title = 'Customer Ledger - ' . $customer['name'];
$page_actions = '<button onclick="window.print()" class="btn btn-primary no-print"><i class="fas fa-print"></i> Print</button>
                 <a href="customer-view.php?id=' . $customer_id . '" class="btn btn-secondary no-print"><i class="fas fa-arrow-left"></i> Back</a>';
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
    .no-print, .btn, .card, .card-header, .navbar, .sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, .summary-cards, #standard-print-wrapper, #standard-print-footer {
        display: none !important;
    }
    
    #print-area { display: block !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    
    /* Document Container */
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
    
    /* Horizontal Info Bar */
    .info-bar { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 5px 0; margin-bottom: 15px; width: 100%; }
    .info-bar-table { width: 100%; border-collapse: collapse; }
    .info-bar-table td { padding: 2px 0; border: none !important; }
    .label { font-weight: bold; }
    .date-box { border: 1px solid #000; padding: 2px 5px; font-weight: bold; }

    /* Centered Title */
    .page-main-title { text-align: center; font-size: 16px; color: #000; margin-bottom: 10px; font-weight: bold; }

    /* Simple Bordered Table for Ledger */
    .ledger-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .ledger-table th, .ledger-table td { border: 1px solid #333 !important; padding: 5px; text-align: left; }
    .ledger-table th { background: #f2f2f2 !important; font-weight: bold; text-transform: uppercase; }
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

        <!-- Info Bar -->
        <div class="info-bar">
            <table class="info-bar-table">
                <tr>
                    <td width="25%"><span class="label">Customer:</span> <?= htmlspecialchars($customer['name'] ?? '') ?></td>
                    <td width="15%"><span class="label">ID:</span> <?= htmlspecialchars($customer['id'] ?? '') ?></td>
                    <td width="20%"><span class="label">Phone:</span> <?= htmlspecialchars($customer['phone'] ?? '') ?></td>
                    <td width="20%"><span class="label">Email:</span> <?= htmlspecialchars($customer['email'] ?? '') ?></td>
                    <td width="20%" class="text-end"><span class="date-box">DATE: <?= date('d-M-Y') ?></span></td>
                </tr>
                <tr>
                    <td colspan="3"><span class="label">Address:</span> <?= htmlspecialchars($customer['address'] ?? '') ?></td>
                    <td colspan="2" class="text-end"><span class="label">Generated By:</span> <?= htmlspecialchars($_SESSION['username'] ?? 'System Admin') ?></td>
                </tr>
            </table>
        </div>

        <div class="page-main-title">Account Ledger</div>

        <!-- Print Ledger Table -->
        <table class="ledger-table">
            <thead>
                <tr>
                    <th>DATE</th>
                    <th>TRANSACTION</th>
                    <th>DESCRIPTION</th>
                    <th class="text-end">DEBIT</th>
                    <th class="text-end">CREDIT</th>
                    <th class="text-end">BALANCE</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="3" class="text-end"><strong>Opening Balance</strong></td>
                    <td class="text-end"><?= $opening_balance > 0 ? format_currency($opening_balance) : '-' ?></td>
                    <td class="text-end"><?= $opening_balance < 0 ? format_currency(abs($opening_balance)) : '-' ?></td>
                    <td class="text-end"><strong><?= format_currency(abs($opening_balance)) ?></strong></td>
                </tr>
                <?php foreach (array_reverse($display_entries) as $entry): ?>
                    <tr>
                        <td><?= date('Y-m-d', strtotime($entry['date'])) ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $entry['transaction_type'])) ?></td>
                        <td><?= htmlspecialchars($entry['description'] ?? '-') ?></td>
                        <td class="text-end"><?= $entry['debit'] > 0 ? format_currency($entry['debit']) : '-' ?></td>
                        <td class="text-end"><?= $entry['credit'] > 0 ? format_currency($entry['credit']) : '-' ?></td>
                        <td class="text-end"><strong><?= format_currency(abs($entry['running_balance'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<div class="row no-print">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Customer Information</h6>
            </div>
            <div class="card-body">
                <div class="row text-center text-md-left">
                    <div class="col-md-3 mb-2 mb-md-0">
                        <strong class="text-gray-800">Name:</strong><br>
                        <?= htmlspecialchars($customer['name']) ?>
                    </div>
                    <div class="col-md-3 mb-2 mb-md-0">
                        <strong class="text-gray-800">Phone:</strong><br>
                        <?= htmlspecialchars($customer['phone'] ?? '-') ?>
                    </div>
                    <div class="col-md-3 mb-2 mb-md-0">
                        <strong class="text-gray-800">Email:</strong><br>
                        <?= htmlspecialchars($customer['email'] ?? '-') ?>
                    </div>
                    <div class="col-md-3">
                        <strong class="text-gray-800">Current Balance:</strong><br>
                        <span class="<?= $customer['current_balance'] > 0 ? 'text-danger' : 'text-success' ?> font-weight-bold">
                            <?= format_currency(abs($customer['current_balance'])) ?>
                            <?= $customer['current_balance'] > 0 ? '(Due)' : '(Advance)' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4 no-print">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Ledger</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <input type="hidden" name="id" value="<?= $customer_id ?>">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <label>Transaction Type</label>
                        <select name="transaction_type" class="form-control">
                            <option value="">All Types</option>
                            <?php foreach ($available_types as $type): ?>
                                <option value="<?= htmlspecialchars($type['transaction_type']) ?>" <?= $transaction_type === $type['transaction_type'] ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $type['transaction_type'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <a href="?id=<?= $customer_id ?>" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Ledger View Card (Screen) -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Account Ledger</h6>
        <div class="no-print">
            <button onclick="window.print()" class="btn btn-sm btn-success">
                <i class="fas fa-print"></i> Print Ledger
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="ledgerTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction</th>
                        <th>Description</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Balance</th>
                    </tr>
                    <tr class="table-light no-print">
                        <th colspan="3" class="text-end">Opening Balance</th>
                        <th class="text-end font-weight-normal"><?= $opening_balance > 0 ? format_currency($opening_balance) : '-' ?></th>
                        <th class="text-end font-weight-normal"><?= $opening_balance < 0 ? format_currency(abs($opening_balance)) : '-' ?></th>
                        <th class="text-end <?= $opening_balance > 0 ? 'text-danger' : 'text-success' ?>">
                            <strong><?= format_currency(abs($opening_balance)) ?></strong>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($display_entries)): ?>
                        <tr><td colspan="6" class="text-center">No transactions found</td></tr>
                    <?php else: ?>
                        <?php foreach ($display_entries as $entry): ?>
                            <tr>
                                <td><?= format_date($entry['date']) ?></td>
                                <td>
                                    <?php
                                    $type_colors = ['sale' => 'primary', 'payment' => 'success', 'return' => 'danger'];
                                    $color = $type_colors[$entry['transaction_type']] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?= $color ?>">
                                        <?= ucfirst(str_replace('_', ' ', $entry['transaction_type'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($entry['description'] ?? '-') ?></td>
                                <td class="text-end text-danger"><?= $entry['debit'] > 0 ? format_currency($entry['debit']) : '-' ?></td>
                                <td class="text-end text-success"><?= $entry['credit'] > 0 ? format_currency($entry['credit']) : '-' ?></td>
                                <td class="text-end <?= $entry['running_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <strong><?= format_currency(abs($entry['running_balance'])) ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-active">
                        <th colspan="3" class="text-end">Total for Period:</th>
                        <th class="text-end text-danger"><?= format_currency($total_debit) ?></th>
                        <th class="text-end text-success"><?= format_currency($total_credit) ?></th>
                        <th class="text-end <?= $final_balance > 0 ? 'text-danger' : 'text-success' ?>">
                            <strong><?= format_currency(abs($final_balance)) ?></strong>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php 
$additional_js = '
<script>
$(document).ready(function() {
    if ($.fn.DataTable) {
        $("#ledgerTable").DataTable({
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "order": [[0, "desc"]],
            "dom": "<\'row\'<\'col-sm-12 col-md-6\'l><\'col-sm-12 col-md-6\'f>>" +
                   "<\'row\'<\'col-sm-12\'tr>>" +
                   "<\'row\'<\'col-sm-12 col-md-5\'i><\'col-sm-12 col-md-7\'p>>",
            "language": {
                "search": "Search:",
                "lengthMenu": "Show _MENU_ entries"
            }
        });
    }
});
</script>
';
include __DIR__ . '/../../templates/footer.php'; 
?>
