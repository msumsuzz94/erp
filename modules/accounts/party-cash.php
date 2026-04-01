<?php
/**
 * Party Cash Management Page
 * Redesigned to include Sales, Payments, and Expenses
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/account_functions.php';

require_login();

$errors = [];
$success_message = '';

// Handle form submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $person_name = clean_input($_POST['person_name']);
        $transaction_type = clean_input($_POST['transaction_type']);
        $amount = (float) $_POST['amount'];
        $transaction_date = clean_input($_POST['transaction_date']);
        $description = clean_input($_POST['description']);

        // Validation
        if (empty($person_name)) {
            $errors[] = 'Please enter Staff or Person Name';
        }

        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }

        if (!in_array($transaction_type, ['assign', 'return'])) {
            $errors[] = 'Invalid transaction type';
        }

        // For return, warn if user has insufficient balance but allow it to keep logs intact
        if ($transaction_type == 'return' && empty($errors)) {
            $summary_data = get_party_cash_summary();
            $current_balance = 0;
            foreach ($summary_data as $s) {
                if ($s['username'] === $person_name) {
                    $current_balance = $s['balance'];
                    break;
                }
            }
            if ($amount > $current_balance && $current_balance > 0) {
                // We could block it, but often manual errors need adjustments. Let's warn instead.
                // Or just keep the strict block:
                // $errors[] = "Return amount cannot exceed current cash in hand: " . format_currency($current_balance);
            }
        }

        if (empty($errors)) {
            $data = [
                'user_id' => null,
                'person_name' => $person_name,
                'transaction_type' => $transaction_type,
                'amount' => $amount,
                'transaction_date' => $transaction_date,
                'description' => $description,
                'created_by' => get_current_user_id()
            ];

            $insert_id = db_insert('party_cash', $data);

            if ($insert_id) {
                $action = $transaction_type == 'assign' ? 'assigned to' : 'returned from';
                
                log_activity(get_current_user_id(), 'party_cash', "Party cash " . format_currency($amount) . " $action $person_name");
                redirect_with_message('party-cash.php', "Party cash $transaction_type recorded successfully!", 'success');
            } else {
                $errors[] = 'Failed to record transaction';
            }
        }
    }
}

// Get comprehensive summary using the new helper

// Get comprehensive summary using the new helper
$summary = get_party_cash_summary();

// Calculate totals
$grand_total_in = 0;
$grand_total_out = 0;
$grand_total_balance = 0;

$total_assigned = 0;
$total_sales = 0;
$total_returns = 0;
$total_payments = 0;
$total_expenses = 0;

foreach ($summary as $row) {
    // Grand Totals (Summary Logic)
    $grand_total_balance += $row['balance'];
    $grand_total_in += $row['total_in'];
    $grand_total_out += $row['total_out'];
    
    // Detailed Totals (Footer Logic)
    $total_assigned += $row['assigned_cash'];
    $total_sales += $row['sales_cash'];
    $total_returns += $row['returned_cash'];
    $total_payments += $row['payment_cash'];
    $total_expenses += $row['expense_cash'];
}

// Net Business Cash (Sales - Expenses - Payments)
// This shows how much "real" money the business generated/spent through users
$net_business_cash = $total_sales - ($total_payments + $total_expenses);

// Get recent manual transactions
$transactions = db_query("SELECT pc.*, u.username as system_user
    FROM party_cash pc
    LEFT JOIN users u ON pc.user_id = u.id
    ORDER BY pc.transaction_date DESC, pc.created_at DESC
    LIMIT 50");

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

$page_title = 'Party Cash Management';
$page_actions = '<button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print Report</button>';

$additional_css = '
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
    .no-print, .btn, .card, .navbar, .sidebar, #sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, .summary-cards, #standard-print-wrapper, #standard-print-footer, form, .alert {
        display: none !important;
    }
    
    #print-area { display: block !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    
    .print-container { 
        width: 100% !important; 
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        box-sizing: border-box !important; 
        font-family: \'Segoe UI\', Arial, sans-serif; 
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
';

include __DIR__ . '/../../templates/header.php';
?>


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

        <div class="report-main-title">Party Cash Management Report (Date: <?= date('d M Y') ?>)</div>

         <table class="print-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th class="text-end">Assigned (+)</th>
                    <th class="text-end">Invested (+)</th>
                    <th class="text-end">Sales (+)</th>
                    <th class="text-end">Returns (-)</th>
                    <th class="text-end">Payments (-)</th>
                    <th class="text-end">Expenses (-)</th>
                    <th class="text-end">Net Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summary as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                        <td class="text-end"><?= format_currency($row['assigned_cash']) ?></td>
                        <td class="text-end"><?= format_currency($row['invested_cash']) ?></td>
                        <td class="text-end"><?= format_currency($row['sales_cash']) ?></td>
                        <td class="text-end"><?= format_currency($row['returned_cash']) ?></td>
                        <td class="text-end"><?= format_currency($row['payment_cash']) ?></td>
                        <td class="text-end"><?= format_currency($row['expense_cash']) ?></td>
                        <td class="text-end">
                            <?php if ($row['balance'] > 0): ?>
                                <span class="text-dark"><?= format_currency($row['balance']) ?></span>
                            <?php elseif ($row['balance'] < 0): ?>
                                <span class="text-danger"><?= format_currency($row['balance']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f2f2f2;">
                    <th>TOTAL</th>
                    <th class="text-end"><?= format_currency($total_assigned) ?></th>
                    <th class="text-end"><?= format_currency($total_invested) ?></th>
                    <th class="text-end"><?= format_currency($total_sales) ?></th>
                    <th class="text-end"><?= format_currency($total_returns) ?></th>
                    <th class="text-end"><?= format_currency($total_payments) ?></th>
                    <th class="text-end"><?= format_currency($total_expenses) ?></th>
                    <th class="text-end"><?= format_currency($grand_total_balance) ?></th>
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

<div class="no-print">





    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger no-print">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4 no-print">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Cash Inflow</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_currency($grand_total_in) ?>
                            </div>
                            <small class="text-muted">Assigned + Sales</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-down fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Cash Outflow</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_currency($grand_total_out) ?>
                            </div>
                            <small class="text-muted">Returns + Purch. + Exp.</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-up fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Net Business Cash</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_currency($net_business_cash) ?>
                            </div>
                            <small class="text-muted">Sales - (Payments + Exp.)</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Current Cash In Hand</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_currency($grand_total_balance) ?>
                            </div>
                            <small class="text-muted">Held by all staff / users</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-wallet fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Assign/Return Form -->
        <div class="col-lg-12 mb-4 no-print">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Assign/Return Cash</h6>
                </div>
                <div class="card-body">
                    <form method="POST" class="row">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <div class="col-md-2 form-group">
                            <label>Action <span class="text-danger">*</span></label>
                            <select name="transaction_type" id="transaction_type" class="form-control" required>
                                <option value="assign">Assign Cash</option>
                                <option value="return">Return Cash</option>
                            </select>
                        </div>

                        <div class="col-md-3 form-group">
                            <label>Staff / Person Name <span class="text-danger">*</span></label>
                            <input type="text" list="person_names" name="person_name" id="person_name" class="form-control" required placeholder="Staff or Person Name">
                            <datalist id="person_names">
                                <?php foreach ($summary as $row): ?>
                                    <option value="<?= htmlspecialchars($row['username']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="col-md-2 form-group">
                            <label>Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0.01" required>
                        </div>

                        <div class="col-md-2 form-group">
                            <label>Date <span class="text-danger">*</span></label>
                            <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-3 form-group">
                            <label>Description</label>
                            <div class="input-group">
                                <input type="text" name="description" class="form-control" placeholder="Optional note...">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-12 text-info" id="balance_info" style="display: none;">
                            <small><i class="fas fa-info-circle"></i> This user currently has <strong><span id="current_balance_display">0.00</span></strong> cash in hand.</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Comprehensive Cash Summary -->
        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 print-visible">
                    <h6 class="m-0 font-weight-bold text-primary">Detailed Cash Summary by User</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="summaryTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>User</th>
                                    <th class="text-primary">Assigned (+)</th>
                                    <th class="text-success">Sales (+)</th>
                                    <th class="text-warning">Returns (-)</th>
                                    <th class="text-danger">Payments (-)</th>
                                    <th class="text-danger">Expenses (-)</th>
                                    <th class="bg-gray-200">Net Balance</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary as $row): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($row['username']) ?></strong>
                                        </td>
                                        <td class="text-primary">
                                            <?= format_currency($row['assigned_cash']) ?>
                                        </td>
                                        <td class="text-success">
                                            <?= format_currency($row['sales_cash']) ?>
                                        </td>
                                        <td class="text-warning">
                                            <?= format_currency($row['returned_cash']) ?>
                                        </td>
                                        <td class="text-danger">
                                            <?= format_currency($row['payment_cash']) ?>
                                        </td>
                                        <td class="text-danger">
                                            <?= format_currency($row['expense_cash']) ?>
                                        </td>
                                        <td class="bg-gray-200 font-weight-bold">
                                            <?php if ($row['balance'] > 0): ?>
                                                <span class="text-dark"><?= format_currency($row['balance']) ?></span>
                                            <?php elseif ($row['balance'] < 0): ?>
                                                <span class="text-danger"><?= format_currency($row['balance']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['balance'] > 0): ?>
                                                <button class="btn btn-sm btn-success btn-return" 
                                                    data-id="<?= $row['id'] ?>"
                                                    data-name="<?= htmlspecialchars($row['username']) ?>"
                                                    data-balance="<?= $row['balance'] ?>">
                                                    <i class="fas fa-undo"></i> Return
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light font-weight-bold">
                                <tr>
                                    <td>TOTAL</td>
                                    <td class="text-primary"><?= format_currency($total_assigned) ?></td>
                                    <td class="text-success"><?= format_currency($total_sales) ?></td>
                                    <td class="text-warning"><?= format_currency($total_returns) ?></td>
                                    <td class="text-danger"><?= format_currency($total_payments) ?></td>
                                    <td class="text-danger"><?= format_currency($total_expenses) ?></td>
                                    <td><?= format_currency($grand_total_balance) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Manual Transactions (Log) -->
        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 print-visible">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Manual Transactions (Assign/Return)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $trans): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($trans['transaction_date'])) ?></td>
                                        <td><?= htmlspecialchars($trans['person_name'] ? $trans['person_name'] : ($trans['system_user'] ?? 'Unknown')) ?></td>
                                        <td>
                                            <?php if ($trans['transaction_type'] == 'assign'): ?>
                                                <span class="text-dark font-weight-bold">Assign</span>
                                            <?php elseif ($trans['transaction_type'] == 'return'): ?>
                                                <span class="text-dark font-weight-bold">Return</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?= $trans['transaction_type'] == 'return' ? 'text-danger' : 'text-primary' ?>">
                                            <?= $trans['transaction_type'] == 'return' ? '-' : '+' ?><?= format_currency($trans['amount']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($trans['description'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Initialize DataTables
        $('#summaryTable').DataTable({
            order: [[6, 'desc']], // Order by net balance
            pageLength: 25,
            fixedHeader: true
        });

        $('#transactionsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10
        });

        // User balances map for client side
        var userBalances = {};
        <?php foreach ($summary as $row): ?>
        userBalances["<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>"] = <?= $row['balance'] ?>;
        <?php endforeach; ?>

        // Show balance when user is selected
        $('#person_name').on('input change', function () {
            var userId = $(this).val();
            var balance = userBalances[userId] || 0;

            if (userId && balance > 0) {
                $('#balance_info').show();
                $('#current_balance_display').text('<?= APP_CURRENCY_SYMBOL ?>' + parseFloat(balance).toFixed(2));

                // If return type, set max amount
                if ($('#transaction_type').val() == 'return') {
                    $('#amount').attr('max', balance);
                }
            } else {
                $('#balance_info').hide();
            }
        });

        // Update max amount when transaction type changes
        $('#transaction_type').on('change', function () {
            var userId = $('#person_name').val();
            var balance = userId ? (userBalances[userId] || 0) : 0;

            if ($(this).val() == 'return') {
                if (userId && balance > 0) $('#amount').attr('max', balance);
            } else {
                $('#amount').removeAttr('max');
            }
        });

        // Quick return button
        $('.btn-return').on('click', function () {
            var name = $(this).data('name');
            var balance = $(this).data('balance');

            $('#transaction_type').val('return');
            $('#person_name').val(name).trigger('change');
            $('#amount').val(balance);
            
            // Scroll to form
            $('html, body').animate({
                scrollTop: $(".card-header:contains('Assign/Return')").offset().top - 100
            }, 500);
            
            // Highlight input
            setTimeout(function() {
                $('#amount').focus().select();
            }, 600);
        });
    });
</script>
