<?php

/**
 * Service Sales Report
 * Shows all service tickets with parts/service revenue, filters, and print
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle Due Payment Collection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_payment'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $ticket_id = (int)$_POST['ticket_id'];
        $paid_amount = (float)$_POST['paid_amount'];
        $payment_method = clean_input($_POST['payment_method']);
        $payment_account_id = !empty($_POST['payment_account_id']) ? (int)$_POST['payment_account_id'] : null;

        $ticket = db_select_one('service_tickets', ['id' => $ticket_id]);
        if ($ticket && $paid_amount > 0 && $payment_account_id) {
            $new_paid = (float)$ticket['paid_amount'] + $paid_amount;
            $payment_status = ($new_paid >= $ticket['total_amount']) ? 'Paid' : 'Partial';

            db_begin_transaction();
            try {
                // Update ticket
                db_update('service_tickets', [
                    'payment_status' => $payment_status,
                    'paid_amount' => $new_paid,
                ], ['id' => $ticket_id]);

                // Log status change
                db_insert('service_status_log', [
                    'ticket_id' => $ticket_id,
                    'old_status' => $ticket['status'],
                    'new_status' => $ticket['status'],
                    'notes' => "Due Payment Collected: " . format_currency($paid_amount) . " via $payment_method",
                    'changed_by' => get_current_user_id()
                ]);

                // Financial Logic
                $is_cash = ($payment_method === 'Cash');
                $acc_table = $is_cash ? 'cash_accounts' : 'bank_accounts';
                $trans_table = $is_cash ? 'cash_transactions' : 'bank_transactions';

                $account = db_select_one($acc_table, ['id' => $payment_account_id]);
                if ($account) {
                    db_update($acc_table, [
                        'current_balance' => $account['current_balance'] + $paid_amount
                    ], ['id' => $payment_account_id]);

                    db_insert($trans_table, [
                        'account_id' => $payment_account_id,
                        'transaction_type' => 'credit',
                        'amount' => $paid_amount,
                        'reference_type' => 'service_payment',
                        'reference_id' => $ticket_id,
                        'description' => "Due Service Payment: {$ticket['ticket_number']} ($payment_method)",
                        'transaction_date' => date('Y-m-d'),
                        'created_by' => get_current_user_id()
                    ]);

                    // Petty Cash sync
                    if ($is_cash) {
                        $acc_name_lower = strtolower($account['account_name']);
                        if (strpos($acc_name_lower, 'cash in hand') !== false || strpos($acc_name_lower, 'petty cash') !== false) {
                            $counterpart_name = (strpos($acc_name_lower, 'cash in hand') !== false) ? 'petty cash' : 'cash in hand';
                            $counterpart = db_query_one("SELECT id, current_balance FROM cash_accounts WHERE LOWER(account_name) LIKE ? AND id != ?", ["%$counterpart_name%", $payment_account_id]);
                            if ($counterpart) {
                                db_update('cash_accounts', [
                                    'current_balance' => $counterpart['current_balance'] + $paid_amount
                                ], ['id' => $counterpart['id']]);
                                db_insert('cash_transactions', [
                                    'account_id' => $counterpart['id'],
                                    'transaction_type' => 'credit',
                                    'amount' => $paid_amount,
                                    'reference_type' => 'service_sync',
                                    'reference_id' => $ticket_id,
                                    'description' => "Auto-sync: Due collection {$ticket['ticket_number']}",
                                    'transaction_date' => date('Y-m-d'),
                                    'created_by' => get_current_user_id()
                                ]);
                            }
                        }
                    }
                }

                log_activity(get_current_user_id(), 'service_payment', "Collected due " . format_currency($paid_amount) . " for ticket {$ticket['ticket_number']}");
                db_commit();
                redirect_with_message("service-report.php?from={$_GET['from']}&to={$_GET['to']}&status={$_GET['status']}&payment_status={$_GET['payment_status']}", "Payment collected successfully!", 'success');
            } catch (Exception $e) {
                db_rollback();
                $errors[] = "Error: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'Service Sales Report';

// Filters
$filter_from = $_GET['from'] ?? date('Y-m-01');
$filter_to = $_GET['to'] ?? date('Y-m-d');
$filter_status = $_GET['status'] ?? '';
$filter_payment = $_GET['payment_status'] ?? '';

// Build query
$where = "WHERE st.created_at >= ? AND st.created_at <= ?";
$params = [$filter_from . ' 00:00:00', $filter_to . ' 23:59:59'];

if ($filter_status) {
    $where .= " AND st.status = ?";
    $params[] = $filter_status;
}
if ($filter_payment) {
    $where .= " AND st.payment_status = ?";
    $params[] = $filter_payment;
}

$sql = "SELECT st.*, 
        (SELECT GROUP_CONCAT(CONCAT(stp.product_name, ' x', stp.quantity) SEPARATOR ', ') 
         FROM service_ticket_parts stp WHERE stp.ticket_id = st.id) as parts_list,
        (SELECT u.username FROM users u WHERE u.id = st.created_by) as created_by_name
        FROM service_tickets st 
        $where 
        ORDER BY st.created_at DESC";

$tickets = db_query($sql, $params);

// Summary calculations
$total_service_charge = 0;
$total_parts_cost = 0;
$total_amount = 0;
$total_paid = 0;
$total_due = 0;
$delivered_count = 0;
$pending_count = 0;

foreach ($tickets as $t) {
    $total_service_charge += (float)$t['service_charge'];
    $total_parts_cost += (float)$t['total_parts_cost'];
    $total_amount += (float)$t['total_amount'];
    $total_paid += (float)($t['paid_amount'] ?? 0);
    if ($t['status'] === 'Delivered') $delivered_count++;
    else $pending_count++;
}
$total_due = $total_amount - $total_paid;

$statuses = ['Pending', 'Inspection', 'Waiting for Parts', 'In Progress', 'Ready to Deliver', 'Delivered', 'Cannot be Fixed'];

$cash_accounts = db_select('cash_accounts', [], '*', 'account_name ASC');
$bank_accounts = db_select('bank_accounts', [], '*', 'bank_name ASC');

include __DIR__ . '/../../templates/header.php';
?>

<style>
    @media print {

        .sidebar,
        .topbar,
        .no-print,
        .btn,
        nav,
        footer,
        .filter-bar,
        .summary-cards,
        #accordionSidebar,
        .navbar {
            display: none !important;
        }

        body {
            background: white !important;
            color: #000 !important;
            font-size: 11px !important;
        }

        .container-fluid {
            padding: 0 !important;
            margin: 0 !important;
        }

        #content {
            margin: 0 !important;
            padding: 0 !important;
        }

        #content-wrapper {
            margin: 0 !important;
            background: white !important;
        }

        .card {
            border: none !important;
            box-shadow: none !important;
        }

        .card-body {
            padding: 0 !important;
        }

        .table {
            border-collapse: collapse !important;
            width: 100% !important;
        }

        .table th {
            background: #333 !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .table th,
        .table td {
            padding: 4px 6px !important;
            font-size: 11px !important;
            border: 1px solid #999 !important;
        }

        .table tfoot td {
            background: #eee !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .report-header {
            display: block !important;
        }

        .print-footer {
            display: block !important;
        }

        .badge {
            border: 1px solid #999 !important;
            padding: 1px 5px !important;
            font-size: 9px !important;
        }

        a {
            color: #000 !important;
            text-decoration: none !important;
        }
    }

    .summary-cards .card-body {
        padding: 15px;
    }

    .summary-cards .card-title {
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.8;
    }

    .summary-cards .h4 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }

    .filter-bar {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .status-badge {
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 4px;
    }

    .print-footer {
        display: none;
        margin-top: 20px;
        padding-top: 10px;
        border-top: 1px solid #ccc;
        font-size: 10px;
        color: #555;
    }
</style>

<div class="container-fluid">
    <!-- Print-only: date range subtitle (shows below the standard-print-header title) -->
    <div class="print-subtitle">
        Period: <?= format_date($filter_from) ?> to <?= format_date($filter_to) ?> | Total Tickets: <?= count($tickets) ?>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4><i class="fas fa-chart-line"></i> সার্ভিস সেলস রিপোর্ট</h4>
        <div>
            <button class="btn btn-outline-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            <a href="service-list.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar no-print">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">From Date</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= $filter_from ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">To Date</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= $filter_to ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-control form-control-sm">
                    <option value="">All Status</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Payment</label>
                <select name="payment_status" class="form-control form-control-sm">
                    <option value="">All</option>
                    <option value="Paid" <?= $filter_payment === 'Paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="Partial" <?= $filter_payment === 'Partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="Unpaid" <?= $filter_payment === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="service-report.php" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4 summary-cards">
        <div class="col-md-2">
            <div class="card border-left-primary shadow h-100">
                <div class="card-body text-center">
                    <div class="card-title text-primary">Total Tickets</div>
                    <div class="h4"><?= count($tickets) ?></div>
                    <small class="text-muted"><?= $delivered_count ?> delivered, <?= $pending_count ?> pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-left-info shadow h-100">
                <div class="card-body text-center">
                    <div class="card-title text-info">Service Revenue</div>
                    <div class="h4"><?= format_currency($total_service_charge) ?></div>
                    <small class="text-muted">Service charges</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-left-warning shadow h-100">
                <div class="card-body text-center">
                    <div class="card-title text-warning">Parts Sales</div>
                    <div class="h4"><?= format_currency($total_parts_cost) ?></div>
                    <small class="text-muted">Product sales</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-left-success shadow h-100">
                <div class="card-body text-center">
                    <div class="card-title text-success">Total Revenue</div>
                    <div class="h4"><?= format_currency($total_amount) ?></div>
                    <small class="text-muted">All income</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-left-success shadow h-100">
                <div class="card-body text-center">
                    <div class="card-title text-success">Collected</div>
                    <div class="h4"><?= format_currency($total_paid) ?></div>
                    <small class="text-muted">Amount received</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-left-danger shadow h-100">
                <div class="card-body text-center">
                    <div class="card-title text-danger">Due Amount</div>
                    <div class="h4"><?= format_currency($total_due) ?></div>
                    <small class="text-muted">Pending collection</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Table -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm" id="reportTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Ticket#</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Device</th>
                            <th>Parts Used</th>
                            <th class="text-end">Service Charge</th>
                            <th class="text-end">Parts Cost</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Paid</th>
                            <th>Status</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2"></i><br>No records found for the selected filters</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $t): ?>
                                <?php
                                $statusColors = [
                                    'Pending' => 'secondary',
                                    'Inspection' => 'info',
                                    'Waiting for Parts' => 'warning',
                                    'In Progress' => 'primary',
                                    'Ready to Deliver' => 'success',
                                    'Delivered' => 'success',
                                    'Cannot be Fixed' => 'danger'
                                ];
                                $payColors = ['Paid' => 'success', 'Partial' => 'warning', 'Unpaid' => 'danger'];
                                $due = (float)$t['total_amount'] - (float)($t['paid_amount'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <a href="service-view.php?id=<?= $t['id'] ?>" class="fw-bold"><?= $t['ticket_number'] ?></a>
                                    </td>
                                    <td class="small"><?= format_date($t['created_at']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($t['customer_name']) ?>
                                        <br><small class="text-muted"><?= $t['customer_phone'] ?></small>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($t['device_type'] . ' - ' . $t['brand'] . ' ' . $t['model']) ?></td>
                                    <td class="small"><?= htmlspecialchars($t['parts_list'] ?? '-') ?></td>
                                    <td class="text-end"><?= format_currency($t['service_charge']) ?></td>
                                    <td class="text-end"><?= format_currency($t['total_parts_cost']) ?></td>
                                    <td class="text-end"><?= (float)$t['discount'] > 0 ? format_currency($t['discount']) : '-' ?></td>
                                    <td class="text-end fw-bold"><?= format_currency($t['total_amount']) ?></td>
                                    <td class="text-end"><?= format_currency($t['paid_amount'] ?? 0) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $statusColors[$t['status']] ?? 'secondary' ?> status-badge">
                                            <?= $t['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($t['status'] === 'Delivered'): ?>
                                            <span class="badge bg-<?= $payColors[$t['payment_status'] ?? ''] ?? 'secondary' ?> status-badge d-block mb-1">
                                                <?= $t['payment_status'] ?? 'N/A' ?>
                                            </span>
                                            <?php if ($due > 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success no-print shadow-sm w-100"
                                                    onclick="openPaymentModal(<?= $t['id'] ?>, '<?= $t['ticket_number'] ?>', <?= $due ?>)">
                                                    Pay <?= format_currency($due) ?>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary status-badge">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td colspan="5" class="text-end">Totals:</td>
                            <td class="text-end"><?= format_currency($total_service_charge) ?></td>
                            <td class="text-end"><?= format_currency($total_parts_cost) ?></td>
                            <td class="text-end">-</td>
                            <td class="text-end"><?= format_currency($total_amount) ?></td>
                            <td class="text-end"><?= format_currency($total_paid) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <!-- Print-only footer -->
    <div class="print-footer">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <strong>Report Summary:</strong>
                Service Revenue: <?= format_currency($total_service_charge) ?> |
                Parts Sales: <?= format_currency($total_parts_cost) ?> |
                Total: <?= format_currency($total_amount) ?> |
                Collected: <?= format_currency($total_paid) ?> |
                Due: <?= format_currency($total_due) ?>
            </div>
            <div style="text-align:right;">
                Printed: <?= date('d M Y, h:i A') ?>
            </div>
        </div>
    </div>
</div>

<!-- Collect Payment Modal -->
<div class="modal fade" id="collectPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="collect_payment" value="1">
                <input type="hidden" name="ticket_id" id="modal_ticket_id">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-money-bill-wave"></i> Collect Due Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2">
                        Ticket: <strong id="modal_ticket_no"></strong><br>
                        Due Amount: <strong id="modal_due_amount_text" class="text-danger"></strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Receive Amount</label>
                        <input type="number" name="paid_amount" id="modal_paid_amount" class="form-control" step="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="modalPaymentMethod" class="form-control" required>
                            <option value="">-- Select Method --</option>
                            <option value="Cash">Cash</option>
                            <option value="bKash">bKash</option>
                            <option value="Nagad">Nagad</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Card">Card</option>
                            <option value="Rocket">Rocket</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Deposit To Account <span class="text-danger">*</span></label>
                        <select name="payment_account_id" id="modalPaymentAccount" class="form-control" required>
                            <option value="">-- আগে Payment Method সিলেক্ট করুন --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Collect Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    const svcCashAccounts = <?= json_encode($cash_accounts ?: []) ?>;
    const svcBankAccounts = <?= json_encode($bank_accounts ?: []) ?>;
    const CURRENCY = '<?= APP_CURRENCY_SYMBOL ?>';

    function openPaymentModal(ticketId, ticketNo, dueAmount) {
        $('#modal_ticket_id').val(ticketId);
        $('#modal_ticket_no').text(ticketNo);
        $('#modal_due_amount_text').text(CURRENCY + dueAmount.toFixed(2));
        $('#modal_paid_amount').val(dueAmount).attr('max', dueAmount);

        $('#collectPaymentModal').modal('show');
    }

    $('#modalPaymentMethod').on('change', function() {
        const method = $(this).val();
        const select = $('#modalPaymentAccount');
        select.empty();

        if (!method) {
            select.html('<option value="">-- আগে Payment Method সিলেক্ট করুন --</option>');
            return;
        }

        select.append('<option value="">-- Select Account --</option>');
        if (method === 'Cash') {
            svcCashAccounts.forEach(function(acc) {
                select.append(`<option value="${acc.id}">${acc.account_name} (${CURRENCY}${parseFloat(acc.current_balance).toFixed(2)})</option>`);
            });
        } else {
            const typeMap = {
                'bKash': 'bkash',
                'Nagad': 'nagad',
                'Rocket': 'rocket',
                'Bank Transfer': 'bank',
                'Card': 'bank'
            };
            const filterType = typeMap[method] || 'bank';
            let added = 0;
            svcBankAccounts.forEach(function(acc) {
                const accType = (acc.account_type || 'bank').toLowerCase();
                if (accType === filterType) {
                    select.append(`<option value="${acc.id}">${acc.bank_name} (${acc.account_number})</option>`);
                    added++;
                }
            });
            if (added === 0) {
                svcBankAccounts.forEach(function(acc) {
                    select.append(`<option value="${acc.id}">${acc.bank_name} (${acc.account_number})</option>`);
                });
            }
        }
    });
</script>
