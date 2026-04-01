<?php
/**
 * Bill Collections Page
 * Record payments from customers
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$customer_id = (int)get_param('id');
$customer = db_select_one('customers', ['id' => $customer_id]);

if (!$customer) {
    redirect_with_message('customers-list.php', 'Customer not found', 'error');
}

// Handle payment submission
if (is_post() && (isset($_POST['add_payment']) || isset($_POST['amount']))) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_message('CSRF Token Validation Failed!', 'error');
    } else {
        $amount = floatval($_POST['amount']);
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $account_id = (int)($_POST['account_id'] ?? 0);
        $reference = $_POST['reference'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        $errors = [];
        if ($amount <= 0) {
            $errors[] = 'Payment amount must be greater than zero';
        }
        if (empty($account_id)) {
            $errors[] = 'Please select an account for deposit';
        }
        
        if (!empty($errors)) {
            set_message(implode('<br>', $errors), 'error');
        } else {
            // Begin transaction
            db_begin_transaction();
            
            try {
                // Insert into bill_collections
                $payment_data = [
                    'customer_id' => $customer_id,
                    'amount' => $amount,
                    'payment_date' => $payment_date,
                    'payment_method' => $payment_method,
                    'reference' => $reference,
                    'notes' => $notes,
                    'created_by' => get_current_user_id()
                ];
                $payment_id = db_insert('bill_collections', $payment_data);
                
                // Insert into customer_ledger
                $ledger_data = [
                    'customer_id' => $customer_id,
                    'transaction_type' => 'payment',
                    'reference_id' => $payment_id,
                    'credit' => $amount,
                    'debit' => 0,
                    'balance' => $customer['current_balance'] - $amount,
                    'description' => "Payment received - " . ucfirst($payment_method) . ($reference ? " (Ref: $reference)" : ""),
                    'date' => $payment_date
                ];
                db_insert('customer_ledger', $ledger_data);
                
                // Update customer balance
                $new_balance = $customer['current_balance'] - $amount;
                db_update('customers', 
                    ['current_balance' => $new_balance], 
                    ['id' => $customer_id]
                );
                
                // Credit to account balance
                $account_table = ($payment_method === 'cash') ? 'cash_accounts' : 'bank_accounts';
                $account = db_select_one($account_table, ['id' => $account_id]);
                db_update($account_table, [
                    'current_balance' => $account['current_balance'] + $amount
                ], ['id' => $account_id]);
                
                // Record transaction in account ledger
                $trans_table = ($payment_method === 'cash') ? 'cash_transactions' : 'bank_transactions';
                db_insert($trans_table, [
                    'account_id' => $account_id,
                    'transaction_type' => 'credit',
                    'amount' => $amount,
                    'reference_type' => 'bill_collection',
                    'reference_id' => $payment_id,
                    'description' => "Collection from {$customer['name']}" . ($reference ? " (Ref: $reference)" : ""),
                    'transaction_date' => $payment_date,
                    'created_by' => get_current_user_id()
                ]);
                
                // Log activity
                log_activity(get_current_user_id(), 'bill_collection', "Payment received from {$customer['name']}: " . format_currency($amount));
                
                db_commit();
                redirect_with_message('bill-collections.php?id=' . $customer_id, 'Payment recorded successfully', 'success');
                
            } catch (Exception $e) {
                db_rollback();
                set_message('Failed to record payment: ' . $e->getMessage(), 'error');
            }
        }
    }
}

// Get payment history joining ledger with bill_collections for more details
$sql = "SELECT cl.*, cp.payment_method, cp.reference, cp.notes as payment_notes 
        FROM customer_ledger cl
        LEFT JOIN bill_collections cp ON cl.reference_id = cp.id
        WHERE cl.customer_id = ? AND cl.transaction_type = 'payment' 
        ORDER BY cl.date DESC, cl.created_at DESC";
$payment_history = db_query($sql, [$customer_id]);

$page_title = 'Bill Collection';
$page_actions = '<a href="customer-view.php?id=' . $customer_id . '" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-md-8">
        <!-- Payment Form -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Record Payment</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer Name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($customer['name']) ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Current Balance</label>
                                <input type="text" class="form-control text-danger font-weight-bold" 
                                       value="<?= format_currency($customer['current_balance']) ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" class="form-control" 
                                       step="0.01" min="0.01" required 
                                       placeholder="0.00">
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
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Deposit To Account <span class="text-danger">*</span></label>
                                <select name="account_id" id="account_id" class="form-control" required>
                                    <option value="">Select Account</option>
                                </select>
                                <small class="form-text text-muted">Account will be loaded based on payment method</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Reference Number</label>
                                <input type="text" name="reference" class="form-control" 
                                       placeholder="Transaction reference">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes (optional)"></textarea>
                    </div>
                    
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <button type="submit" name="add_payment" class="btn btn-primary">
                        <i class="fas fa-save"></i> Record Payment
                    </button>
                    <a href="customer-view.php?id=<?= $customer_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </form>
            </div>
        </div>
        
        <!-- Payment History -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Payment History</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="paymentsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Balance After</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payment_history)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No payment history found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payment_history as $payment): ?>
                                    <tr>
                                        <td><?= format_date($payment['date']) ?></td>
                                        <td class="text-success font-weight-bold"><?= format_currency($payment['credit']) ?></td>
                                        <td><?= ucfirst($payment['payment_method'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($payment['reference'] ?? '-') ?></td>
                                        <td><?= format_currency($payment['balance']) ?></td>
                                        <td class="no-print">
                                            <a href="customer-ledger.php?id=<?= $customer_id ?>" class="btn btn-sm btn-info" title="View in Ledger">
                                                <i class="fas fa-list"></i>
                                            </a>
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
    
    <div class="col-md-4">
        <!-- Customer Info Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Customer Info</h6>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>Name:</strong><br>
                    <?= htmlspecialchars($customer['name']) ?>
                </p>
                <p class="mb-2">
                    <strong>Phone:</strong><br>
                    <?= htmlspecialchars($customer['phone'] ?? '-') ?>
                </p>
                <p class="mb-2">
                    <strong>Email:</strong><br>
                    <?= htmlspecialchars($customer['email'] ?? '-') ?>
                </p>
                <hr>
                <p class="mb-2">
                    <strong>Credit Limit:</strong><br>
                    <span class="text-info"><?= format_currency($customer['credit_limit']) ?></span>
                </p>
                <p class="mb-0">
                    <strong>Current Balance:</strong><br>
                    <?php if ($customer['current_balance'] > 0): ?>
                        <span class="text-danger font-weight-bold"><?= format_currency($customer['current_balance']) ?> (Due)</span>
                    <?php elseif ($customer['current_balance'] < 0): ?>
                        <span class="text-success font-weight-bold"><?= format_currency(abs($customer['current_balance'])) ?> (Advance)</span>
                    <?php else: ?>
                        <span class="text-muted"><?= format_currency(0) ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Links</h6>
            </div>
            <div class="card-body">
                <a href="customer-view.php?id=<?= $customer_id ?>" class="btn btn-info btn-block mb-2">
                    <i class="fas fa-eye"></i> View Customer
                </a>
                <a href="customer-ledger.php?id=<?= $customer_id ?>" class="btn btn-primary btn-block mb-2">
                    <i class="fas fa-book"></i> View Ledger
                </a>
                <a href="../sales/pos.php?customer_id=<?= $customer_id ?>" class="btn btn-success btn-block">
                    <i class="fas fa-shopping-cart"></i> New Sale
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Check if DataTable already exists and destroy it
    if ($.fn.DataTable.isDataTable('#paymentsTable')) {
        $('#paymentsTable').DataTable().destroy();
    }
    
    // Initialize DataTable with explicit column definitions
    $('#paymentsTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]], // Sort by date descending
        "columns": [
            { "orderable": true },  // Date
            { "orderable": true },  // Amount
            { "orderable": true },  // Method
            { "orderable": true },  // Reference
            { "orderable": true },  // Balance After
            { "orderable": false }  // Actions
        ],
        "responsive": true,
        "language": {
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        }
    });
    
    // Load accounts when payment method changes
    $('#payment_method').change(function() {
        loadAccounts($(this).val());
    });
    
    // Load accounts on page load (default: cash)
    loadAccounts('cash');
});

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
