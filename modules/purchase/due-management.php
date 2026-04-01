<?php
/**
 * Purchase Due Management Page
 * Comprehensive view of all outstanding purchase dues
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

    $purchase_id = (int) $_POST['purchase_id'];
    $amount = (float) $_POST['amount'];
    $payment_method = clean_input($_POST['payment_method']);
    $payment_date = clean_input($_POST['payment_date']);
    $reference = clean_input($_POST['reference']);
    $notes = clean_input($_POST['notes']);

    // Get purchase details
    $purchase = db_select_one('purchases', ['id' => $purchase_id]);

    if (!$purchase) {
        echo json_encode(['success' => false, 'message' => 'Purchase not found']);
        exit;
    }

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
        exit;
    }

    if ($amount > $purchase['due_amount']) {
        echo json_encode(['success' => false, 'message' => 'Payment amount (' . format_currency($amount) . ') exceeds the current due amount (' . format_currency($purchase['due_amount']) . ')']);
        exit;
    }

    // Insert payment
    $payment_data = [
        'purchase_id' => $purchase_id,
        'payment_date' => $payment_date,
        'amount' => $amount,
        'payment_method' => $payment_method,
        'reference' => $reference,
        'notes' => $notes
    ];

    $payment_id = db_insert('purchase_payments', $payment_data);

    if ($payment_id) {
        // Update purchase amounts
        $new_paid = $purchase['paid_amount'] + $amount;
        $new_due = $purchase['due_amount'] - $amount;
        $payment_status = $new_due <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');

        db_update(
            'purchases',
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
            'debit' => 0,
            'credit' => $amount,
            'description' => "Payment for Purchase #{$purchase['purchase_number']}",
            'date' => $payment_date
        ];
        db_insert('supplier_ledger', $ledger_data);

        // Update supplier balance
        $supplier = db_select_one('suppliers', ['id' => $purchase['supplier_id']]);
        $new_balance = $supplier['current_balance'] - $amount;
        db_update('suppliers', ['current_balance' => $new_balance], ['id' => $purchase['supplier_id']]);

        log_activity(get_current_user_id(), 'supplier_payment', "Payment of " . format_currency($amount) . " for Purchase #{$purchase['purchase_number']}");

        echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record payment']);
    }
    exit;
}

// Get summary statistics
$total_dues = db_query("SELECT SUM(due_amount) as total FROM purchases WHERE payment_status IN ('unpaid', 'partial') AND status = 'completed'")[0]['total'] ?? 0;
$pending_count = db_query("SELECT COUNT(*) as count FROM purchases WHERE payment_status IN ('unpaid', 'partial') AND status = 'completed'")[0]['count'] ?? 0;

// Overdue amount (purchases older than 30 days with dues)
$overdue_date = date('Y-m-d', strtotime('-30 days'));
$overdue_amount = db_query("SELECT SUM(due_amount) as total FROM purchases WHERE payment_status IN ('unpaid', 'partial') AND status = 'completed' AND purchase_date < '$overdue_date'")[0]['total'] ?? 0;

// This month's dues
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$month_dues = db_query("SELECT SUM(due_amount) as total FROM purchases WHERE payment_status IN ('unpaid', 'partial') AND status = 'completed' AND purchase_date BETWEEN '$month_start' AND '$month_end'")[0]['total'] ?? 0;

// Get all purchases with dues
$sql = "SELECT p.*, s.name as supplier_name, s.phone as supplier_phone,
        DATEDIFF(CURDATE(), p.purchase_date) as days_old
        FROM purchases p 
        INNER JOIN suppliers s ON p.supplier_id = s.id 
        WHERE p.payment_status IN ('unpaid', 'partial') AND p.status = 'completed'
        ORDER BY p.purchase_date ASC";
$due_purchases = db_query($sql);

// Get all suppliers for filter
$suppliers = db_query("SELECT * FROM suppliers ORDER BY name ASC");

// Get recent payments
$recent_payments = db_query("SELECT pp.*, p.purchase_number, s.name as supplier_name
    FROM purchase_payments pp
    INNER JOIN purchases p ON pp.purchase_id = p.id
    INNER JOIN suppliers s ON p.supplier_id = s.id
    ORDER BY pp.payment_date DESC, pp.created_at DESC
    LIMIT 10");

$page_title = 'Purchase Due Management';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-money-bill-wave"></i> Purchase Due Management
        </h1>
    </div>

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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Purchases
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
        <!-- Outstanding Purchases Table -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Outstanding Purchases</h6>
                    <div class="d-flex gap-2">
                        <select id="filterSupplier" class="form-control form-control-sm" style="width: 200px;">
                            <option value="">All Suppliers</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>">
                                    <?= htmlspecialchars($supplier['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="filterStatus" class="form-control form-control-sm" style="width: 150px;">
                            <option value="">All Status</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="dueTable" width="100%">
                            <thead>
                                <tr>
                                    <th>Purchase #</th>
                                    <th>Date</th>
                                    <th>Supplier</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($due_purchases as $purchase): ?>
                                    <tr data-supplier="<?= $purchase['supplier_id'] ?>"
                                        data-status="<?= $purchase['payment_status'] ?>">
                                        <td>
                                            <strong>
                                                <?= $purchase['purchase_number'] ?>
                                            </strong>
                                            <?php if ($purchase['days_old'] > 30): ?>
                                                <br><small class="text-danger"><i class="fas fa-exclamation-circle"></i>
                                                    <?= $purchase['days_old'] ?> days old
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('d M Y', strtotime($purchase['purchase_date'])) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($purchase['supplier_name']) ?>
                                            <?php if ($purchase['supplier_phone']): ?>
                                                <br><small class="text-muted">
                                                    <?= $purchase['supplier_phone'] ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= format_currency($purchase['total_amount']) ?>
                                        </td>
                                        <td>
                                            <?= format_currency($purchase['paid_amount']) ?>
                                        </td>
                                        <td><strong class="text-danger">
                                                <?= format_currency($purchase['due_amount']) ?>
                                            </strong></td>
                                        <td>
                                            <?php if ($purchase['payment_status'] == 'unpaid'): ?>
                                                <span class="badge badge-danger">Unpaid</span>
                                            <?php elseif ($purchase['payment_status'] == 'partial'): ?>
                                                <span class="badge badge-warning">Partial</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Paid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="purchase-edit.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-info" title="Edit Purchase">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-primary btn-pay" data-id="<?= $purchase['id'] ?>"
                                                data-number="<?= $purchase['purchase_number'] ?>"
                                                data-supplier="<?= htmlspecialchars($purchase['supplier_name']) ?>"
                                                data-due="<?= $purchase['due_amount'] ?>"
                                                title="Record Payment">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </button>
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
                                    <?= $payment['purchase_number'] ?><br>
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($payment['supplier_name']) ?><br>
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
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="ajax_payment" value="1">
                    <input type="hidden" name="purchase_id" id="modal_purchase_id">

                    <div class="form-group">
                        <label>Purchase #</label>
                        <input type="text" id="modal_purchase_number" class="form-control" readonly>
                    </div>

                    <div class="form-group">
                        <label>Supplier</label>
                        <input type="text" id="modal_supplier" class="form-control" readonly>
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
                        <select name="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="credit">Credit</option>
                            <option value="card">Card</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
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
            order: [[1, 'asc']],
            pageLength: 25,
            language: {
                search: "Search purchases:"
            }
        });

        // Filter by supplier
        $('#filterSupplier').on('change', function () {
            var supplierId = $(this).val();
            if (supplierId) {
                table.column(2).search('^' + supplierId + '$', true, false).draw();
            } else {
                table.column(2).search('').draw();
            }
        });

        // Filter by status
        $('#filterStatus').on('change', function () {
            var status = $(this).val();
            table.column(6).search(status).draw();
        });

        // Open payment modal
        $('.btn-pay').on('click', function () {
            var id = $(this).data('id');
            var number = $(this).data('number');
            var supplier = $(this).data('supplier');
            var due = $(this).data('due');

            $('#modal_purchase_id').val(id);
            $('#modal_purchase_number').val(number);
            $('#modal_supplier').val(supplier);
            $('#modal_due_amount').val('<?= APP_CURRENCY_SYMBOL ?>' + parseFloat(due).toFixed(2));
            $('#modal_amount').val(due).attr('max', due);

            $('#paymentModal').modal('show');
        });

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
