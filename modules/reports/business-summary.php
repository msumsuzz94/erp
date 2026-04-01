<?php
/**
 * Business Summary Report
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$from_date = get_param('from_date', date('Y-m-01'));
$to_date = get_param('to_date', date('Y-m-d'));

// Sales Summary
$sql_sales = "SELECT COUNT(*) as total_sales, SUM(total_amount) as total_amount, SUM(paid_amount) as paid_amount, SUM(due_amount) as due_amount
              FROM sales WHERE sale_date BETWEEN ? AND ? AND status = 'completed'";
$sales_summary = db_query_one($sql_sales, [$from_date, $to_date]);

// Purchase Summary
$sql_purchase = "SELECT COUNT(*) as total_purchases, SUM(total_amount) as total_amount, SUM(paid_amount) as paid_amount, SUM(due_amount) as due_amount
                 FROM purchases WHERE purchase_date BETWEEN ? AND ? AND status = 'completed'";
$purchase_summary = db_query_one($sql_purchase, [$from_date, $to_date]);

// Expense Summary
$sql_expense = "SELECT SUM(amount) as total_expenses FROM expenses WHERE date BETWEEN ? AND ?";
$expense_summary = db_query_one($sql_expense, [$from_date, $to_date]);

// Profit Calculation
$profit = ($sales_summary['total_amount'] ?? 0) - ($purchase_summary['total_amount'] ?? 0) - ($expense_summary['total_expenses'] ?? 0);

// Customer & Supplier Stats
$total_customers = db_count('customers', ['status' => 'active']);
$total_suppliers = db_count('suppliers', ['status' => 'active']);

// Product Stats
$total_products = db_count('products', ['status' => 'active']);
$low_stock_products = db_query_one("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level AND status = 'active'");

$page_title = 'Business Summary Report';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Select Date Range</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Generate Report</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<h5 class="mb-3">Period: <?= format_date($from_date) ?> to <?= format_date($to_date) ?></h5>

<!-- Sales Summary -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sales</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($sales_summary['total_amount'] ?? 0) ?></div>
                        <small class="text-muted"><?= $sales_summary['total_sales'] ?? 0 ?> invoices</small>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Purchases</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($purchase_summary['total_amount'] ?? 0) ?></div>
                        <small class="text-muted"><?= $purchase_summary['total_purchases'] ?? 0 ?> invoices</small>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-truck fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Expenses</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($expense_summary['total_expenses'] ?? 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Net Profit</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($profit) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receivables & Payables -->
<div class="row">
    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Receivables</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($sales_summary['due_amount'] ?? 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Payables</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($purchase_summary['due_amount'] ?? 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Business Stats -->
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card shadow h-100 py-2">
            <div class="card-body text-center">
                <h6 class="text-primary">Total Customers</h6>
                <h3><?= $total_customers ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card shadow h-100 py-2">
            <div class="card-body text-center">
                <h6 class="text-primary">Total Suppliers</h6>
                <h3><?= $total_suppliers ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card shadow h-100 py-2">
            <div class="card-body text-center">
                <h6 class="text-primary">Total Products</h6>
                <h3><?= $total_products ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card shadow h-100 py-2">
            <div class="card-body text-center">
                <h6 class="text-warning">Low Stock Items</h6>
                <h3><?= $low_stock_products['count'] ?? 0 ?></h3>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
