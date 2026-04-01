<?php
/**
 * Supplier Payment Page
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
        
        $purchase_id = (int)$_POST['purchase_id'];
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
            $errors[] = 'Please select an account for payment';
        }
        
        // Get purchase details
        $purchase = db_select_one('purchases', ['id' => $purchase_id]);
        
        if (!$purchase) {
            $errors[] = 'Purchase not found';
        } else {
            // Re-calculate actual due to prevent overpayment from stale session data
            if ($amount > $purchase['due_amount']) {
                $errors[] = 'Payment amount (' . format_currency($amount) . ') exceeds the current due amount (' . format_currency($purchase['due_amount']) . ')';
            }
        }
        
        // Validate account and balance
        if (!empty($account_id)) {
            $account_table = ($payment_method === 'cash') ? 'cash_accounts' : 'bank_accounts';
            $account = db_select_one($account_table, ['id' => $account_id]);
            
            if (!$account) {
                $errors[] = 'Selected account not found';
            } else if ($account['current_balance'] < $amount) {
                $errors[] = 'Insufficient balance in selected account. Available: ' . format_currency($account['current_balance']);
            }
        }
        
        if (empty($errors)) {
            // Insert payment
            $payment_data = [
                'purchase_id' => $purchase_id,
                'payment_date' => $payment_date,
                'amount' => $amount,
                'payment_method' => $payment_method,
                'account_id' => $account_id,
                'reference' => $reference,
                'notes' => $notes,
                'created_by' => get_current_user_id()
            ];
            
            $payment_id = db_insert('purchase_payments', $payment_data);
            
            if ($payment_id) {
                // Update purchase paid and due amounts
                $new_paid = $purchase['paid_amount'] + $amount;
                $new_due = $purchase['due_amount'] - $amount;
                $payment_status = $new_due <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');
                
                db_update('purchases', 
                    [
                        'paid_amount' => $new_paid,
                        'due_amount' => $new_due,
                        'payment_status' => $payment_status
                    ],
                    ['id' => $purchase_id]
                );
                
                // Update supplier ledger
                $ledger_data = [
                    'supplier_id' => $purchase['supplier_id'],
                    'transaction_type' => 'payment',
                    'reference_id' => $payment_id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "Payment for Purchase #{$purchase['purchase_number']}",
                    'date' => $payment_date
                ];
                
                db_insert('supplier_ledger', $ledger_data);
                
                // Update supplier balance
                $supplier = db_select_one('suppliers', ['id' => $purchase['supplier_id']]);
                $new_balance = $supplier['current_balance'] - $amount;
                db_update('suppliers', ['current_balance' => $new_balance], ['id' => $purchase['supplier_id']]);
                
                // Deduct from account balance
                $account_table = ($payment_method === 'cash') ? 'cash_accounts' : 'bank_accounts';
                $account = db_select_one($account_table, ['id' => $account_id]);
                db_update($account_table, [
                    'current_balance' => $account['current_balance'] - $amount
                ], ['id' => $account_id]);
                
                // Record transaction in account ledger
                $trans_table = ($payment_method === 'cash') ? 'cash_transactions' : 'bank_transactions';
                db_insert($trans_table, [
                    'account_id' => $account_id,
                    'transaction_type' => 'debit',
                    'amount' => $amount,
                    'reference_type' => 'supplier_payment',
                    'reference_id' => $payment_id,
                    'description' => "Payment to {$supplier['name']} for Purchase #{$purchase['purchase_number']}",
                    'transaction_date' => $payment_date,
                    'created_by' => get_current_user_id()
                ]);
                
                log_activity(get_current_user_id(), 'supplier_payment', "Payment of " . format_currency($amount) . " for Purchase #{$purchase['purchase_number']}");
                redirect_with_message($_SERVER['PHP_SELF'], 'Payment recorded successfully', 'success');
            } else {
                $errors[] = 'Failed to record payment';
            }
        }
    }
}

// Get pending/partial purchases (limited to 5 for display)
$sql = "SELECT p.*, s.name as supplier_name 
        FROM purchases p 
        INNER JOIN suppliers s ON p.supplier_id = s.id 
        WHERE p.payment_status IN ('unpaid', 'partial') AND p.status = 'completed'
        ORDER BY p.purchase_date DESC
        LIMIT 5";
$pending_purchases = db_query($sql);

// Get all pending purchases for the dropdown (not limited)
$sql_all = "SELECT p.*, s.name as supplier_name 
            FROM purchases p 
            INNER JOIN suppliers s ON p.supplier_id = s.id 
            WHERE p.payment_status IN ('unpaid', 'partial') AND p.status = 'completed'
            ORDER BY p.purchase_date DESC";
$all_pending_purchases = db_query($sql_all);

// Get suppliers for filter
$suppliers = db_query("SELECT id, name FROM suppliers ORDER BY name ASC");

// Filter purchases
$filter_supplier_id = get_param('filter_supplier', '');
$filter_status = get_param('filter_status', '');
$filter_from_date = get_param('filter_from_date', '');
$filter_to_date = get_param('filter_to_date', '');

$purchase_sql = "SELECT p.*, s.name as supplier_name,
                (SELECT payment_method FROM purchase_payments WHERE purchase_id = p.id ORDER BY created_at DESC LIMIT 1) as latest_payment_method
                FROM purchases p 
                INNER JOIN suppliers s ON p.supplier_id = s.id 
                WHERE p.status = 'completed'";
$purchase_params = [];

if (!empty($filter_supplier_id)) {
    $purchase_sql .= " AND p.supplier_id = ?";
    $purchase_params[] = $filter_supplier_id;
}

if (!empty($filter_status)) {
    $purchase_sql .= " AND p.payment_status = ?";
    $purchase_params[] = $filter_status;
}

if (!empty($filter_from_date)) {
    $purchase_sql .= " AND p.purchase_date >= ?";
    $purchase_params[] = $filter_from_date;
}

if (!empty($filter_to_date)) {
    $purchase_sql .= " AND p.purchase_date <= ?";
    $purchase_params[] = $filter_to_date;
}

$purchase_sql .= " ORDER BY p.purchase_date DESC";
$filtered_purchases = db_query($purchase_sql, $purchase_params);

$page_title = 'Supplier Payment';
include __DIR__ . '/../../templates/header.php';
?>

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
                        <label>Select Purchase <span class="text-danger">*</span></label>
                        <select name="purchase_id" id="purchase_id" class="form-control select2" required>
                            <option value="">Select Purchase</option>
                            <?php foreach ($all_pending_purchases as $purchase): ?>
                                <option value="<?= $purchase['id'] ?>" 
                                        data-due="<?= $purchase['due_amount'] ?>"
                                        data-supplier="<?= htmlspecialchars($purchase['supplier_name']) ?>">
                                    <?= $purchase['purchase_number'] ?> - <?= htmlspecialchars($purchase['supplier_name']) ?> 
                                    (Due: <?= format_currency($purchase['due_amount']) ?>)
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
                        <label>Withdraw From Account <span class="text-danger">*</span></label>
                        <select name="account_id" id="account_id" class="form-control" required>
                            <option value="">Select Account</option>
                        </select>
                        <small class="form-text text-muted">Account will be automatically loaded based on payment method</small>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Reference/Cheque No.</label>
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
    
    <!-- Pending Purchases -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Pending Purchases (Latest 5)</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Purchase #</th>
                                <th>Supplier</th>
                                <th>Total</th>
                                <th>Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_purchases)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No pending purchases</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_purchases as $purchase): ?>
                                    <tr>
                                        <td><?= $purchase['purchase_number'] ?></td>
                                        <td><?= htmlspecialchars($purchase['supplier_name']) ?></td>
                                        <td><?= format_currency($purchase['total_amount']) ?></td>
                                        <td class="text-danger"><?= format_currency($purchase['due_amount']) ?></td>
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

<!-- Supplier Purchase Search Section -->
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Supplier Purchase Search</h6>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" action="" class="mb-4">
                    <div class="row align-items-end">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Select Supplier</label>
                            <select name="filter_supplier" class="form-control select2">
                                <option value="">All Suppliers</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>" <?= $filter_supplier_id == $supplier['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Payment Status</label>
                            <select name="filter_status" class="form-control">
                                <option value="">All Status</option>
                                <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="partial" <?= $filter_status === 'partial' ? 'selected' : '' ?>>Partial</option>
                                <option value="unpaid" <?= $filter_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="filter_from_date" class="form-control" value="<?= htmlspecialchars($filter_from_date) ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="filter_to_date" class="form-control" value="<?= htmlspecialchars($filter_to_date) ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="d-grid gap-2 d-md-block">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Purchase Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="purchasesTable">
                        <thead>
                            <tr>
                                <th>Purchase #</th>
                                <th>Supplier</th>
                                <th>Payment Type</th>
                                <th class="text-end">Payment</th>
                                <th class="text-end">Due</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($filtered_purchases)): ?>
                                <?php foreach ($filtered_purchases as $purchase): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($purchase['purchase_number']) ?></td>
                                        <td><?= htmlspecialchars($purchase['supplier_name']) ?></td>
                                        <td>
                                            <?php 
                                            if ($purchase['latest_payment_method']) {
                                                echo ucfirst(str_replace('_', ' ', $purchase['latest_payment_method']));
                                            } else {
                                                echo '<span class="text-muted">Unpaid</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-end">
                                            <?= format_currency($purchase['paid_amount']) ?>
                                        </td>
                                        <td class="text-end <?= $purchase['due_amount'] > 0 ? 'text-danger font-weight-bold' : '' ?>">
                                            <?= format_currency($purchase['due_amount']) ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="purchase-view.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-outline-info">View</a>
                                                <a href="purchase-print.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-outline-success">Print</a>
                                                <a href="purchase-edit.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-outline-warning">Edit</a>
                                                <?php if ($purchase['due_amount'] > 0): ?>
                                                    <button type="button" class="btn btn-sm btn-success select-purchase-btn" 
                                                            data-id="<?= $purchase['id'] ?>"
                                                            data-purchase="<?= htmlspecialchars($purchase['purchase_number']) ?>"
                                                            data-due="<?= $purchase['due_amount'] ?>"
                                                            title="Pay Now">
                                                        <i class="fas fa-hand-holding-usd"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
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
    
    // Initialize DataTable for purchases
    if ($.fn.DataTable.isDataTable('#purchasesTable')) {
        $('#purchasesTable').DataTable().destroy();
    }
    
    $('#purchasesTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]], 
        "columns": [
            { "orderable": true },  // Purchase #
            { "orderable": true },  // Supplier
            { "orderable": true },  // Payment Type
            { "orderable": true },  // Payment
            { "orderable": true },  // Due
            { "orderable": false }  // Actions
        ],
        "responsive": true
    });
    
    // Original due amount updater
    $('#purchase_id').change(function() {
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
    
    // Select purchase from search table
    $('.select-purchase-btn').click(function() {
        var purchaseId = $(this).data('id');
        var purchaseNumber = $(this).data('purchase');
        var dueAmount = $(this).data('due');
        
        // Set the select dropdown
        $('#purchase_id').val(purchaseId).trigger('change');
        
        // Scroll to payment form
        $('html, body').animate({
            scrollTop: $("#paymentForm").offset().top - 100
        }, 500);
        
        // Show notification
        showAlert('Purchase ' + purchaseNumber + ' selected for payment', 'info');
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
