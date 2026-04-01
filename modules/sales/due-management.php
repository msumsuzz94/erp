<?php
/**
 * Sales Due Management Page
 * Comprehensive view of all outstanding sales dues from customers
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle AJAX payment recording
if (isset($_POST['ajax_payment'])) {
    header('Content-Type: application/json');

    if (!verify_csrf_token($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $sale_id_raw = $_POST['sale_id'];
    $is_ob = (strpos($sale_id_raw, 'ob_') === 0);
    $sale_id = $is_ob ? 0 : (int) $sale_id_raw;
    $amount = (float) $_POST['amount'];
    $payment_method = clean_input($_POST['payment_method']);
    $payment_date = clean_input($_POST['payment_date']);
    $reference = clean_input($_POST['reference']);
    $notes = clean_input($_POST['notes']);

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
        exit;
    }

    $sale = null;
    $customer_id = 0;
    $customer_name = '';
    
    if (!$is_ob) {
        // Get sale details
        $sale = db_select_one('sales', ['id' => $sale_id]);

        if (!$sale) {
            echo json_encode(['success' => false, 'message' => 'Sale not found']);
            exit;
        }

        if ($amount > $sale['due_amount']) {
            echo json_encode(['success' => false, 'message' => 'Payment amount cannot exceed due amount']);
            exit;
        }
        $customer_id = $sale['customer_id'];
    } else {
        $customer_id = (int) str_replace('ob_', '', $sale_id_raw);
        $customer = db_select_one('customers', ['id' => $customer_id]);
        if (!$customer) {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
            exit;
        }
        $customer_name = $customer['name'];
        
        $sales_due = db_query("SELECT SUM(due_amount) as total FROM sales WHERE customer_id = ? AND payment_status != 'paid' AND status != 'cancelled'", [$customer_id])[0]['total'] ?? 0;
        $ob_due = round($customer['current_balance'] - $sales_due, 2);
        
        if ($amount > $ob_due) {
            echo json_encode(['success' => false, 'message' => 'Payment amount cannot exceed opening balance due']);
            exit;
        }
    }

    // Insert payment
    // Determine target account ID dynamically
    $account_id = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
    
    if (!$account_id) {
        if ($payment_method === 'cash') {
            $acc = db_query("SELECT id FROM cash_accounts WHERE status = 'active' LIMIT 1");
            $account_id = $acc[0]['id'] ?? 1;
        } else {
            $acc = db_query("SELECT id FROM bank_accounts WHERE status = 'active' LIMIT 1");
            $account_id = $acc[0]['id'] ?? 1;
        }
    }
    
    $payment_data = [
        'payment_date' => $payment_date,
        'amount' => $amount,
        'payment_method' => $payment_method,
        'reference' => $reference,
        'notes' => $notes,
        'created_by' => get_current_user_id()
    ];

    if (!$is_ob) {
        $payment_data['sale_id'] = $sale_id;
        $payment_data['account_id'] = $account_id;
        $payment_id = db_insert('sale_payments', $payment_data);
    } else {
        $payment_data['customer_id'] = $customer_id;
        $payment_id = db_insert('bill_collections', $payment_data);
    }

    if ($payment_id) {
        $customer = db_select_one('customers', ['id' => $customer_id]);
        $new_balance = $customer['current_balance'] - $amount;
        
        if (!$is_ob) {
            // Update sale amounts
            $new_paid = $sale['paid_amount'] + $amount;
            $new_due = $sale['due_amount'] - $amount;
            $payment_status = $new_due <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');

            db_update(
                'sales',
                [
                    'paid_amount' => $new_paid,
                    'due_amount' => $new_due,
                    'payment_status' => $payment_status
                ],
                ['id' => $sale_id]
            );

            // Update customer balance (Sales increase balance, payments decrease it)
            db_update('customers', ['current_balance' => $new_balance], ['id' => $customer_id]);

            // Update customer ledger
            $ledger_data = [
                'customer_id' => $customer_id,
                'transaction_type' => 'payment',
                'reference_id' => $payment_id,
                'debit' => 0,
                'credit' => $amount,
                'balance' => $new_balance,
                'description' => "Payment for Sale #{$sale['invoice_number']}",
                'date' => $payment_date
            ];
            db_insert('customer_ledger', $ledger_data);

            log_activity(get_current_user_id(), 'bill_collection', "Payment of " . format_currency($amount) . " for Sale #{$sale['invoice_number']}");
        } else {
            // Update customer balance for opening balance payment
            db_update('customers', ['current_balance' => $new_balance], ['id' => $customer_id]);

            // Update customer ledger
            $ledger_data = [
                'customer_id' => $customer_id,
                'transaction_type' => 'payment',
                'reference_id' => $payment_id,
                'debit' => 0,
                'credit' => $amount,
                'balance' => $new_balance,
                'description' => "Opening Balance / Previous Due Collection" . ($reference ? " - {$reference}" : ""),
                'date' => $payment_date
            ];
            db_insert('customer_ledger', $ledger_data);

            log_activity(get_current_user_id(), 'bill_collection', "Payment of " . format_currency($amount) . " for Opening Balance Due from {$customer_name}");
        }

        // Update Financial Account Balance
        if ($account_id) {
            $is_cash_payment = ($payment_method === 'cash');
            $table = $is_cash_payment ? 'cash_accounts' : 'bank_accounts';
            $trans_table = $is_cash_payment ? 'cash_transactions' : 'bank_transactions';
            
            $acc = db_select_one($table, ['id' => $account_id]);
            if ($acc) {
                db_update($table, ['current_balance' => $acc['current_balance'] + $amount], ['id' => $account_id]);
                $trans_data = [
                    'account_id' => $account_id,
                    'transaction_date' => $payment_date,
                    'transaction_type' => 'in',
                    'amount' => $amount,
                    'reference_type' => 'sale_payment',
                    'reference_id' => $payment_id,
                    'description' => $is_ob ? "Payment collection for Opening Balance" . ($reference ? " - {$reference}" : "") : "Payment collection for Sale #{$sale['invoice_number']}",
                    'created_by' => get_current_user_id()
                ];
                db_insert($trans_table, $trans_data);
            }
        }

        $success_msg = $is_ob ? 'Opening balance payment recorded successfully' : 'Payment recorded successfully';
        echo json_encode(['success' => true, 'message' => $success_msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record payment']);
    }
    exit;
}

// Get summary statistics (Only include sales that have a linked active customer)
$total_dues = db_query("SELECT SUM(s.due_amount) as total FROM sales s INNER JOIN customers c ON s.customer_id = c.id WHERE s.payment_status != 'paid' AND s.status != 'cancelled'")[0]['total'] ?? 0;
$pending_count = db_query("SELECT COUNT(*) as count FROM sales s INNER JOIN customers c ON s.customer_id = c.id WHERE s.payment_status != 'paid' AND s.status != 'cancelled'")[0]['count'] ?? 0;

// Overdue amount (sales older than 30 days with dues)
$overdue_date = date('Y-m-d', strtotime('-30 days'));
$overdue_amount = db_query("SELECT SUM(s.due_amount) as total FROM sales s INNER JOIN customers c ON s.customer_id = c.id WHERE s.payment_status != 'paid' AND s.status != 'cancelled' AND s.sale_date < '$overdue_date'")[0]['total'] ?? 0;

// This month's dues
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$month_dues = db_query("SELECT SUM(s.due_amount) as total FROM sales s INNER JOIN customers c ON s.customer_id = c.id WHERE s.payment_status != 'paid' AND s.status != 'cancelled' AND s.sale_date BETWEEN '$month_start' AND '$month_end'")[0]['total'] ?? 0;

// Include Opening Balance dues in the summary statistics
$global_ob_sql = "
    SELECT 
        c.current_balance, 
        c.created_at,
        (SELECT SUM(due_amount) FROM sales WHERE customer_id = c.id AND payment_status != 'paid' AND status != 'cancelled') as total_sales_due
    FROM customers c
    WHERE c.current_balance > 0
";
$global_ob_customers = db_query($global_ob_sql);

$overdue_timestamp = strtotime('-30 days');
$month_start_timestamp = strtotime(date('Y-m-01 00:00:00'));
$month_end_timestamp = strtotime(date('Y-m-t 23:59:59'));

foreach ($global_ob_customers as $c_ob) {
    $non_invoice_due = round($c_ob['current_balance'] - ($c_ob['total_sales_due'] ?? 0), 2);
    if ($non_invoice_due > 0.01) {
        $total_dues += $non_invoice_due;
        $pending_count += 1; // Count as 1 pending bill for the opening balance
        
        $created_timestamp = strtotime($c_ob['created_at']);
        if ($created_timestamp < $overdue_timestamp) {
            $overdue_amount += $non_invoice_due;
        }
        if ($created_timestamp >= $month_start_timestamp && $created_timestamp <= $month_end_timestamp) {
            $month_dues += $non_invoice_due;
        }
    }
}

$search = get_param('search', '');
$customer_id = get_param('customer_id', '');
$sold_by = get_param('sold_by', '');
$from_date = get_param('from_date', '');
$to_date = get_param('to_date', '');

// Get all sales with dues
$sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone,
        DATEDIFF(CURDATE(), s.sale_date) as days_old
        FROM sales s 
        INNER JOIN customers c ON s.customer_id = c.id 
        WHERE s.payment_status != 'paid' AND s.status != 'cancelled'";

$params = [];

if (!empty($search)) {
    $sql .= " AND (s.invoice_number LIKE ? OR c.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($customer_id)) {
    $sql .= " AND s.customer_id = ?";
    $params[] = $customer_id;
}

if (!empty($sold_by)) {
    $sql .= " AND s.sold_by = ?";
    $params[] = $sold_by;
}

if (!empty($from_date)) {
    $sql .= " AND s.sale_date >= ?";
    $params[] = $from_date;
}

if (!empty($to_date)) {
    $sql .= " AND s.sale_date <= ?";
    $params[] = $to_date;
}

$sql .= " ORDER BY s.sale_date ASC";
$due_sales = db_query($sql, $params);

// Fetch Opening Balance dues
$ob_sql = "
    SELECT 
        c.id as customer_id, 
        c.name as customer_name, 
        c.phone as customer_phone, 
        c.current_balance, 
        c.created_at,
        c.opening_balance,
        (SELECT SUM(due_amount) FROM sales WHERE customer_id = c.id AND payment_status != 'paid' AND status != 'cancelled') as total_sales_due
    FROM customers c
    WHERE c.current_balance > 0
";
$ob_params = [];

if (!empty($customer_id)) {
    $ob_sql .= " AND c.id = ?";
    $ob_params[] = $customer_id;
}
if (!empty($search)) {
    $ob_sql .= " AND c.name LIKE ?";
    $ob_params[] = "%$search%";
}

$customers_with_dues = db_query($ob_sql, $ob_params);

foreach ($customers_with_dues as $c_ob) {
    if (!empty($from_date) && date('Y-m-d', strtotime($c_ob['created_at'])) < $from_date) continue;
    if (!empty($to_date) && date('Y-m-d', strtotime($c_ob['created_at'])) > $to_date) continue;
    
    $non_invoice_due = round($c_ob['current_balance'] - ($c_ob['total_sales_due'] ?? 0), 2);
    if ($non_invoice_due > 0.01) { // use small epsilon for float comparison safely
        // Add to due_sales
        $due_sales[] = [
            'id' => 'ob_' . $c_ob['customer_id'],
            'invoice_number' => 'Opening Balance',
            'customer_name' => $c_ob['customer_name'],
            'customer_phone' => $c_ob['customer_phone'],
            'sale_date' => $c_ob['created_at'],
            'payment_method' => '',
            'due_amount' => $non_invoice_due,
            'payment_status' => 'unpaid'
        ];
    }
}

// Get dependencies for filters
$customers_list = db_query("SELECT id, name FROM customers ORDER BY name ASC");
$salespeople_list = db_query("SELECT DISTINCT sold_by FROM sales WHERE sold_by IS NOT NULL AND sold_by != '' ORDER BY sold_by ASC");

// Get recent payments
$recent_payments = db_query("SELECT sp.*, s.invoice_number, c.name as customer_name
    FROM sale_payments sp
    INNER JOIN sales s ON sp.sale_id = s.id
    INNER JOIN customers c ON s.customer_id = c.id
    ORDER BY sp.payment_date DESC, sp.created_at DESC
    LIMIT 10");

$page_title = 'Sales Due Management';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Dues</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_currency($total_dues) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Bill
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $pending_count ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue (>30 days)
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_currency($overdue_amount) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">This Month</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= format_currency($month_dues) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Outstanding Sales Table -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Filter Invoices</h6>
                </div>
                <div class="card-body border-bottom">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group mb-0">
                                    <label class="small">Customer</label>
                                    <select name="customer_id" class="form-control form-control-sm select2">
                                        <option value="">All Customers</option>
                                        <?php foreach ($customers_list as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= (string)$customer_id === (string)$c['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-0">
                                    <label class="small">Sales By</label>
                                    <select name="sold_by" class="form-control form-control-sm select2">
                                        <option value="">All Salespeople</option>
                                        <?php foreach ($salespeople_list as $sp): ?>
                                            <option value="<?= htmlspecialchars($sp['sold_by']) ?>" <?= $sold_by === $sp['sold_by'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sp['sold_by']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-0">
                                    <label class="small">Date Range</label>
                                    <div class="input-group input-group-sm">
                                        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                                        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="small">&nbsp;</label>
                                <div class="d-flex gap-1">
                                    <button type="submit" class="btn btn-primary btn-sm btn-block w-100">Filter</button>
                                    <a href="due-management.php" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i></a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-header py-3 d-flex justify-content-between align-items-center bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">Outstanding Sales</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="dueTable" width="100%">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Collection Type</th>
                                    <th>Due</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($due_sales as $sale): ?>
                                    <tr data-status="<?= $sale['payment_status'] ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($sale['invoice_number']) ?></strong>
                                            <br><small class="text-muted"><?= format_date($sale['sale_date']) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?>
                                            <?php if (!empty($sale['customer_phone'])): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($sale['customer_phone']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= ucfirst($sale['payment_method'] ?: 'Cash') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-danger"><?= format_currency($sale['due_amount']) ?></strong>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2 align-items-center">
                                                <button class="btn btn-primary btn-sm btn-pay" 
                                                    data-id="<?= $sale['id'] ?>"
                                                    data-number="<?= htmlspecialchars($sale['invoice_number']) ?>"
                                                    data-customer="<?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?>"
                                                    data-due="<?= $sale['due_amount'] ?>"
                                                    title="Pay">
                                                    <i class="fas fa-money-bill-wave"></i> Pay
                                                </button>
                                                <?php if (strpos($sale['id'], 'ob_') !== 0): ?>
                                                    <a href="sale-view.php?id=<?= $sale['id'] ?>" class="btn btn-info btn-sm" title="View">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="invoice-print.php?id=<?= $sale['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Print">
                                                        <i class="fas fa-print"></i> Print
                                                    </a>
                                                    <a href="pos.php?edit_id=<?= $sale['id'] ?>" class="btn btn-success btn-sm" title="Edit">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Payments</h6>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    <?php if (empty($recent_payments)): ?>
                        <p class="text-center text-muted">No recent payments</p>
                    <?php else: ?>
                        <?php foreach ($recent_payments as $payment): ?>
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-success">
                                        <?= format_currency($payment['amount']) ?>
                                    </strong>
                                    <small class="text-muted">
                                        <?= date('d M Y', strtotime($payment['payment_date'])) ?>
                                    </small>
                                </div>
                                <div class="text-sm">
                                    <i class="fas fa-file-invoice"></i>
                                    <?= htmlspecialchars($payment['invoice_number']) ?><br>
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($payment['customer_name']) ?><br>
                                    <small class="text-muted">
                                        <i class="fas fa-credit-card"></i>
                                        <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Bill Collection</h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="ajax_payment" value="1">
                    <input type="hidden" name="sale_id" id="modal_sale_id">

                    <div class="form-group">
                        <label>Invoice #</label>
                        <input type="text" id="modal_invoice_no" class="form-control" readonly>
                    </div>

                    <div class="form-group">
                        <label>Customer</label>
                        <input type="text" id="modal_customer" class="form-control" readonly>
                    </div>

                    <div class="form-group">
                        <label>Due Amount</label>
                        <input type="text" id="modal_due_amount" class="form-control" readonly>
                    </div>

                    <div class="form-group">
                        <label>Payment Amount <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="modal_amount" class="form-control" step="0.01" min="0"
                            required>
                    </div>

                    <div class="form-group">
                        <label>Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label>Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="mobile_banking">Mobile Banking</option>
                            <option value="card">Card</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Deposit To Account <span class="text-danger">*</span></label>
                        <select name="account_id" id="account_id" class="form-control" required>
                            <option value="">Select Account</option>
                        </select>
                        <small class="form-text text-muted">Account will automatically load based on method</small>
                    </div>

                    <div class="form-group">
                        <label>Reference/Cheque No.</label>
                        <input type="text" name="reference" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Initialize DataTable
        var table = $('#dueTable').DataTable({
            "pageLength": 25,
            "order": [[1, "asc"]],
            "columnDefs": [
                { "orderable": false, "targets": 4 } // Action column
            ],
            "responsive": true
        });

        // Initialize Select2 if function exists
        if ($.fn.select2) {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }

        // Open payment modal
        $('.btn-pay').on('click', function () {
            var id = $(this).data('id');
            var number = $(this).data('number');
            var customer = $(this).data('customer');
            var due = $(this).data('due');

            $('#modal_sale_id').val(id);
            $('#modal_invoice_no').val(number);
            $('#modal_customer').val(customer);
            $('#modal_due_amount').val('<?= APP_CURRENCY_SYMBOL ?>' + parseFloat(due).toFixed(2));
            $('#modal_amount').val(due).attr('max', due);

            $('#paymentModal').modal('show');
            
            // Trigger load for current payment method if empty
            if ($('#account_id').find('option').length <= 1) {
                $('#payment_method').trigger('change');
            }
        });

        // Load accounts when payment method changes
        $('#payment_method').change(function() {
            var method = $(this).val();
            loadAccounts(method);
        });

        // Load default on page load
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
                                      (acc.bank_name ? acc.bank_name + ' (' + acc.account_number + ')' : acc.account_name);
                            var balance = parseFloat(acc.current_balance).toFixed(2);
                            accountSelect.append(
                                '<option value="' + acc.id + '">' + 
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

        // Handle payment form submission
        $('#paymentForm').on('submit', function (e) {
            e.preventDefault();

            var formData = $(this).serialize();
            var submitBtn = $(this).find('button[type="submit"]');

            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save"></i> Record Payment');
                    }
                },
                error: function () {
                    alert('An error occurred. Please try again.');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save"></i> Record Payment');
                }
            });
        });
    });
</script>
