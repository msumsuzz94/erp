<?php
/**
 * Daily Report
 * Shows today's or selected date's business activity
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$report_date = get_param('report_date', date('Y-m-d'));

// Sales today
$sql_sales = "SELECT COUNT(*) as total_sales, SUM(total_amount) as total_amount, SUM(paid_amount) as paid FROM sales 
              WHERE DATE(sale_date) = ? AND status = 'completed'";
$sales_data = db_query_one($sql_sales, [$report_date]);

// Purchases today  
$sql_purchase = "SELECT COUNT(*) as total_purchases, SUM(total_amount) as total_amount FROM purchases 
                 WHERE DATE(purchase_date) = ? AND status = 'completed'";
$purchase_data = db_query_one($sql_purchase, [$report_date]);

// Expenses today
$sql_expense = "SELECT COUNT(*) as total_expenses, SUM(amount) as total_amount FROM expenses 
                WHERE DATE(expense_date) = ?";
$expense_data = db_query_one($sql_expense, [$report_date]);

// Returns today
$sql_return = "SELECT COUNT(*) as total_returns, SUM(total_amount) as total_amount FROM sales_returns 
               WHERE DATE(return_date) = ? AND status = 'completed'";
$return_data = db_query_one($sql_return, [$report_date]);

// Cash in hand
$cash_in = ($sales_data['paid'] ?? 0);
$cash_out = ($purchase_data['total_amount'] ?? 0) + ($expense_data['total_amount'] ?? 0) + ($return_data['total_amount'] ?? 0);
$net_cash = $cash_in - $cash_out;

$page_title = 'Daily Report';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Date</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-8">
                    <input type="date" name="report_date" class="form-control" value="<?= htmlspecialchars($report_date) ?>" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Generate Report</button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>
        </form>
    </div>
</div>

<h5 class="mb-3">Daily Report for <?= format_date($report_date) ?></h5>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Sales (Gross)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($sales_data['total_amount'] ?? 0) ?></div>
                        <small><?= $sales_data['total_sales'] ?? 0 ?> invoices</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Purchases</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($purchase_data['total_amount'] ?? 0) ?></div>
                        <small><?= $purchase_data['total_purchases'] ?? 0 ?> invoices</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-truck fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-4 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Expenses</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($expense_data['total_amount'] ?? 0) ?></div>
                        <small><?= $expense_data['total_expenses'] ?? 0 ?> entries</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-4 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Returns</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($return_data['total_amount'] ?? 0) ?></div>
                        <small><?= $return_data['total_returns'] ?? 0 ?> returns</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-undo fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-<?= $net_cash >= 0 ? 'success' : 'danger' ?> shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-<?= $net_cash >= 0 ? 'success' : 'danger' ?> text-uppercase mb-1">Net Cash</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($net_cash) ?></div>
                        <small>Cash In - (Purc+Exp+Ret)</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-wallet fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
