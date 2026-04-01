<?php
/**
 * Sale Payment Page - Add payment for a specific sale
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$sale_id = (int)get_param('id');

// Get sale details
$sale = db_select_one('sales', ['id' => $sale_id]);

if (!$sale) {
    redirect_with_message('sales-list.php', 'Sale not found', 'error');
}

// Get customer details if exists
$customer = null;
if ($sale['customer_id']) {
    $customer = db_select_one('customers', ['id' => $sale['customer_id']]);
}

// Handle payment submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        $amount = (float)$_POST['amount'];
        $payment_method = clean_input($_POST['payment_method']);
        $account_id = (int)$_POST['account_id'];
        $payment_date = clean_input($_POST['payment_date']);
        $reference = clean_input($_POST['reference']);
        $notes = clean_input($_POST['notes']);
        
        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than 0';
        } else {
            if ($amount > $sale['due_amount']) {
                $errors[] = 'Payment amount (' . format_currency($amount) . ') exceeds the current due amount (' . format_currency($sale['due_amount']) . ')';
            }
        }
        
        if (empty($account_id)) {
            $errors[] = 'Please select an account for payment';
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
            db_begin_transaction();
            
            try {
                // Insert payment
                $payment_data = [
                    'sale_id' => $sale_id,
                    'payment_date' => $payment_date,
                    'amount' => $amount,
                    'payment_method' => $payment_method,
                    'account_id' => $account_id,
                    'reference' => $reference,
                    'notes' => $notes,
                    'created_by' => get_current_user_id()
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
                        $new_balance = $customer['current_balance'] - $amount;
                        db_update('customers', ['current_balance' => $new_balance], ['id' => $sale['customer_id']]);
                    }
                    
                    // Add to account balance (receiving money)
                    $account_table = ($payment_method === 'cash') ? 'cash_accounts' : 'bank_accounts';
                    $account = db_select_one($account_table, ['id' => $account_id]);
                    db_update($account_table, [
                        'current_balance' => $account['current_balance'] + $amount
                    ], ['id' => $account_id]);
                    
                    // Record transaction in account ledger
                    $trans_table = ($payment_method === 'cash') ? 'cash_transactions' : 'bank_transactions';
                    $customer_name = $customer ? $customer['name'] : 'Walk-in Customer';
                    db_insert($trans_table, [
                        'account_id' => $account_id,
                        'transaction_type' => 'credit',
                        'amount' => $amount,
                        'reference_type' => 'customer_payment',
                        'reference_id' => $payment_id,
                        'description' => "Payment from {$customer_name} for Invoice #{$sale['invoice_number']}",
                        'transaction_date' => $payment_date,
                        'created_by' => get_current_user_id()
                    ]);
                    
                    log_activity(get_current_user_id(), 'sale_payment', "Payment of " . format_currency($amount) . " for Invoice #{$sale['invoice_number']}");
                    
                    db_commit();
                    redirect_with_message('sale-view.php?id=' . $sale_id, 'Payment recorded successfully', 'success');
                } else {
                    throw new Exception('Failed to record payment');
                }
            } catch (Exception $e) {
                db_rollback();
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Get existing payments for this sale
$sql = "SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY payment_date DESC";
$payments = db_query($sql, [$sale_id]);

$page_title = 'Add Payment - ' . $sale['invoice_number'];
$page_actions = '<a href="sale-view.php?id=' . $sale_id . '" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Sale</a>
                 <a href="sales-list.php" class="btn btn-info"><i class="fas fa-list"></i> Sales List</a>';
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
    <!-- Sale Info & Payment Form -->
    <div class="col-md-8">
        <!-- Sale Information Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Sale Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Invoice Number:</strong> <?= htmlspecialchars($sale['invoice_number']) ?></p>
                        <p><strong>Sale Date:</strong> <?= format_date($sale['sale_date']) ?></p>
                        <p><strong>Customer:</strong> <?= $customer ? htmlspecialchars($customer['name']) : 'Walk-in Customer' ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Total Amount:</strong> <?= format_currency($sale['total_amount']) ?></p>
                        <p><strong>Paid Amount:</strong> <span class="text-success"><?= format_currency($sale['paid_amount']) ?></span></p>
                        <p><strong>Due Amount:</strong> <span class="text-danger font-weight-bold"><?= format_currency($sale['due_amount']) ?></span></p>
                        <p>
                            <strong>Payment Status:</strong> 
                            <?php
                            $status_colors = ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger'];
                            $color = $status_colors[$sale['payment_status']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?= $color ?>">
                                <?= ucfirst($sale['payment_status']) ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($sale['due_amount'] > 0): ?>
        <!-- Payment Form -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Add Payment</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" class="form-control" 
                                       step="0.01" min="0.01" max="<?= $sale['due_amount'] ?>"
                                       value="<?= $sale['due_amount'] ?>" required>
                                <small class="form-text text-muted">Maximum: <?= format_currency($sale['due_amount']) ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Method <span class="text-danger">*</span></label>
                                <select name="payment_method" id="payment_method" class="form-control" required>
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="card">Card</option>
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="credit">Credit</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Deposit To Account <span class="text-danger">*</span></label>
                                <select name="account_id" id="account_id" class="form-control" required>
                                    <option value="">Select Account</option>
                                </select>
                                <small class="form-text text-muted">Account will be automatically loaded based on payment method</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Reference/Transaction No.</label>
                                <input type="text" name="reference" class="form-control" 
                                       placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Optional notes"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Record Payment
                    </button>
                    <a href="sale-view.php?id=<?= $sale_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> This sale has been fully paid.
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Payment History Sidebar -->
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Payment History</h6>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <p class="text-muted text-center">No payments recorded yet</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($payments as $payment): ?>
                            <div class="payment-item mb-3 p-3 border-left border-primary">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-success"><?= format_currency($payment['amount']) ?></strong>
                                    <small class="text-muted"><?= format_date($payment['payment_date']) ?></small>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-credit-card"></i> <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                </small>
                                <?php if ($payment['reference']): ?>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-hashtag"></i> <?= htmlspecialchars($payment['reference']) ?>
                                    </small>
                                <?php endif; ?>
                                <?php if ($payment['notes']): ?>
                                    <small class="text-muted d-block mt-1">
                                        <?= htmlspecialchars($payment['notes']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr>
                    <div class="text-right">
                        <strong>Total Paid:</strong> 
                        <span class="text-success font-weight-bold">
                            <?= format_currency($sale['paid_amount']) ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($customer): ?>
        <!-- Customer Info -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Customer Info</h6>
            </div>
            <div class="card-body">
                <p><strong>Name:</strong><br><?= htmlspecialchars($customer['name']) ?></p>
                <p><strong>Phone:</strong><br><?= htmlspecialchars($customer['phone'] ?? '-') ?></p>
                <p><strong>Email:</strong><br><?= htmlspecialchars($customer['email'] ?? '-') ?></p>
                <hr>
                <p><strong>Current Balance:</strong><br>
                    <?php if ($customer['current_balance'] > 0): ?>
                        <span class="text-danger font-weight-bold"><?= format_currency($customer['current_balance']) ?> (Due)</span>
                    <?php elseif ($customer['current_balance'] < 0): ?>
                        <span class="text-success font-weight-bold"><?= format_currency(abs($customer['current_balance'])) ?> (Advance)</span>
                    <?php else: ?>
                        <span class="text-muted"><?= format_currency(0) ?></span>
                    <?php endif; ?>
                </p>
                <a href="../customers/customer-view.php?id=<?= $customer['id'] ?>" class="btn btn-sm btn-info btn-block">
                    <i class="fas fa-user"></i> View Customer
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
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

<style>
.payment-item {
    border-left: 3px solid #4e73df !important;
    background-color: #f8f9fc;
}
</style>
