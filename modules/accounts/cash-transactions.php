<?php
/**
 * Cash Transactions Page
 * Shows history of transactions for a specific cash account
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$account_id = get_param('account_id', 0);

if (!$account_id) {
    redirect_with_message('cash-accounts-list.php', 'Please select a cash account', 'error');
}

// Get account details
$account = db_select_one('cash_accounts', ['id' => $account_id]);

if (!$account) {
    redirect_with_message('cash-accounts-list.php', 'Cash account not found', 'error');
}

// Get transactions
$sql = "SELECT ct.*, u.username as creator_name 
        FROM cash_transactions ct 
        LEFT JOIN users u ON ct.created_by = u.id 
        WHERE ct.account_id = ? 
        ORDER BY ct.transaction_date DESC, ct.id DESC";
$transactions = db_query($sql, [$account_id]);

$page_title = 'Account Transactions: ' . htmlspecialchars($account['account_name']);
$page_actions = '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <a href="cash-accounts-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>';

$additional_css = '
<style>
@media print {
    .btn, .sidebar, #sidebar, .topbar, .navbar, .card-header { display: none !important; }
    .container-fluid { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
    #content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table-responsive { overflow: visible !important; }
    .table { width: 100% !important; border-collapse: collapse !important; }
    .table th, .table td { border: 1px solid #ddd !important; padding: 8px !important; }
    body { padding-top: 0 !important; background: white !important; }
    .text-gray-800 { color: black !important; }
    .badge { border: 1px solid #000 !important; color: black !important; background: transparent !important; }
    .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
}
.print-header { display: none; }
</style>
';

include __DIR__ . '/../../templates/header.php';
?>

<div class="print-header">
    <h2><?= htmlspecialchars(BUSINESS_NAME) ?></h2>
    <h4>Cash Transaction Report</h4>
    <p>Account: <?= htmlspecialchars($account['account_name']) ?> | Date: <?= date('Y-m-d H:i') ?></p>
</div>

<div class="row">
    <!-- Account Summary Card -->
    <div class="col-md-12 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            <?= htmlspecialchars($account['account_name']) ?> (<?= htmlspecialchars($account['account_number'] ?? 'N/A') ?>)
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            Current Balance: <?= format_currency($account['current_balance']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-wallet fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Transaction History</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="transactionsTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No transactions found for this account.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td><?= format_date($tx['transaction_date']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $tx['transaction_type'] === 'credit' ? 'success' : 'danger' ?>">
                                        <?= ucfirst($tx['transaction_type']) ?>
                                    </span>
                                </td>
                                <td><strong><?= format_currency($tx['amount']) ?></strong></td>
                                <td>
                                    <?php if ($tx['reference_type']): ?>
                                        <span class="text-capitalize"><?= htmlspecialchars($tx['reference_type']) ?></span>
                                        <?php if ($tx['reference_id']): ?>
                                            #<?= $tx['reference_id'] ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($tx['description'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($tx['creator_name'] ?? 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
