<?php

/**
 * Dashboard - Modern Design
 * Overview of business statistics with role-based widgets
 */

// ============================================
// 1. INITIALIZATION
// ============================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/menu_functions.php';

// Require login
require_login();

// ============================================
// 2. DATA RETRIEVAL
// ============================================

$today = date('Y-m-d');
$this_month_start = date('Y-m-01');
$this_month_end = date('Y-m-t');
$user_id = get_current_user_id();

// --- PERMISSIONS ---
$is_sr = is_sr($user_id);
$user_role_id = $_SESSION['role_id'] ?? 0;
$user_role_name = $_SESSION['role_name'] ?? '';

// Check for Full Dashboard Access: Super Admin (1), Manager (2), Admin (by name), or POS Menu access
$has_pos_access = user_has_menu_access($user_id, 'sales.pos');
$has_full_dashboard_access = ($user_role_id == 1 || $user_role_id == 2 || stripos($user_role_name, 'Admin') !== false || $has_pos_access);

if ($is_sr && !$has_full_dashboard_access) {
    // SRs only see specific things if they don't have full access override
    $can_view_accounting = false;
    $can_view_expenses = false;
    $can_view_purchases = false;
    $can_view_hr = false;
}

$can_view_sales = user_has_menu_access($user_id, 'sales') || $is_sr || $has_full_dashboard_access;
$can_view_customers = user_has_menu_access($user_id, 'customers') || $has_full_dashboard_access;
$can_view_products = user_has_menu_access($user_id, 'products') || $has_full_dashboard_access;
$can_view_purchases = ($is_sr && !$has_full_dashboard_access) ? false : (user_has_menu_access($user_id, 'purchases') || $has_full_dashboard_access);
$can_view_expenses = ($is_sr && !$has_full_dashboard_access) ? false : (user_has_menu_access($user_id, 'expenses') || $has_full_dashboard_access);
$can_view_accounting = ($is_sr && !$has_full_dashboard_access) ? false : (user_has_menu_access($user_id, 'accounting') || $has_full_dashboard_access);
$can_view_hr = ($is_sr && !$has_full_dashboard_access) ? false : (user_has_menu_access($user_id, 'hr') || $has_full_dashboard_access);
$can_view_quotations = user_has_menu_access($user_id, 'quotation') || $has_full_dashboard_access;
$can_view_warranty = user_has_menu_access($user_id, 'warranty') || $has_full_dashboard_access;

// =============================================
// STAT CARDS DATA
// =============================================

// 1. Today's Sales
$today_sales = 0;
$month_sales_amount = 0;
if ($can_view_sales) {
    if ($is_sr) {
        $sql_today_sales = "SELECT COALESCE(SUM(net_amount), 0) as total FROM sales_requests WHERE DATE(created_at) = ? AND status = 'completed' AND sr_user_id = ?";
        $res_today_sales = db_query_one($sql_today_sales, [$today, $user_id]);
        $today_sales = $res_today_sales['total'] ?? 0;

        $sql_month_sales = "SELECT COALESCE(SUM(net_amount), 0) as total FROM sales_requests WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed' AND sr_user_id = ?";
        $res_month_sales = db_query_one($sql_month_sales, [$this_month_start, $this_month_end, $user_id]);
        $month_sales_amount = $res_month_sales['total'] ?? 0;
    } else {
        $sql_today_sales = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE sale_date = ? AND status != 'cancelled'";
        $res_today_sales = db_query_one($sql_today_sales, [$today]);
        $today_sales_gross = $res_today_sales['total'] ?? 0;

        $sql_today_returns = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales_returns WHERE return_date = ?";
        $res_today_returns = db_query_one($sql_today_returns, [$today]);
        $today_returns = $res_today_returns['total'] ?? 0;

        $today_sales = $today_sales_gross - $today_returns;

        // 2. This Month's Sales
        $sql_month_sales = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE sale_date BETWEEN ? AND ? AND status != 'cancelled'";
        $res_month_sales = db_query_one($sql_month_sales, [$this_month_start, $this_month_end]);
        $month_sales_gross = $res_month_sales['total'] ?? 0;

        $sql_month_returns = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales_returns WHERE return_date BETWEEN ? AND ?";
        $res_month_returns = db_query_one($sql_month_returns, [$this_month_start, $this_month_end]);
        $month_returns = $res_month_returns['total'] ?? 0;

        $month_sales_amount = $month_sales_gross - $month_returns;
    }
}

// 3. Total Customers
$total_customers = 0;
if ($can_view_customers) {
    $total_customers = db_count('customers', ['status' => 'active']);
}

// 4. Cash In Hand, Petty Cash & Bank Balance
$cash_in_hand_balance = 0;
$bank_balance_amount = 0;
if ($can_view_accounting) {
    // Regular Cash Accounts
    $sql_cash_in_hand = "SELECT SUM(current_balance) as total FROM cash_accounts WHERE status = 'active'";
    $res_cash_in_hand = db_query_one($sql_cash_in_hand);
    $cash_in_hand_balance = $res_cash_in_hand['total'] ?? 0;

    // Party Cash Integration (Assign adds to cash in hand, Return subtracts)
    $sql_party_cash = "SELECT 
        SUM(CASE WHEN transaction_type = 'assign' THEN amount ELSE 0 END) as total_assigned,
        SUM(CASE WHEN transaction_type = 'return' THEN amount ELSE 0 END) as total_returned
        FROM party_cash";
    $res_party_cash = db_query_one($sql_party_cash);
    $party_cash_balance = ($res_party_cash['total_assigned'] ?? 0) - ($res_party_cash['total_returned'] ?? 0);
    
    // Add dynamically to DASHBOARD Cash In Hand
    $cash_in_hand_balance += $party_cash_balance;

    // Bank Accounts
    $sql_bank_balance = "SELECT SUM(current_balance) as total FROM bank_accounts";
    $res_bank_balance = db_query_one($sql_bank_balance);
    $bank_balance_amount = $res_bank_balance['total'] ?? 0;
}

// 5. Total Receivables
$total_receivables = 0;
if ($can_view_customers) {
    $sql_receivables = "SELECT SUM(current_balance) as total FROM customers WHERE current_balance > 0";
    $res_receivables = db_query_one($sql_receivables);
    $total_receivables = $res_receivables['total'] ?? 0;
}

// 6. Total Payables
$total_payables = 0;
if ($can_view_purchases) {
    $sql_payables = "SELECT SUM(current_balance) as total FROM suppliers WHERE current_balance > 0";
    $res_payables = db_query_one($sql_payables);
    $total_payables = $res_payables['total'] ?? 0;
}

// 7. Total Products
$total_products = 0;
if ($can_view_products) {
    $total_products = db_count('products', ['status' => 'active']);
}

// 8. Today Expenses
$today_expenses = 0;
if ($can_view_expenses) {
    $sql_today_expenses = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE expense_date = ? AND status = 'approved'";
    $res_expenses = db_query_one($sql_today_expenses, [$today]);
    $today_expenses = $res_expenses['total'] ?? 0;
}

// 9. Quotations
$total_quotations = 0;
$pending_quotations = 0;
if ($can_view_quotations) {
    $total_quotations = db_count('quotations');
    $res_pending_quotations = db_query_one("SELECT COUNT(*) as count FROM quotations WHERE status IN ('draft', 'pending')");
    $pending_quotations = $res_pending_quotations['count'] ?? 0;
}

// 10. RMA
$total_rma = 0;
$pending_rma = 0;
if ($can_view_warranty) {
    $total_rma = db_count('rma_requests');
    $res_pending_rma = db_query_one("SELECT COUNT(*) as count FROM rma_requests WHERE status = 'pending'");
    $pending_rma = $res_pending_rma['count'] ?? 0;
}

// 11. Today Collections (payments received today)
$today_collections = 0;
if ($can_view_sales) {
    if ($is_sr) {
        // SR collection tracking not directly tied yet, default 0
        $today_collections = 0;
    } else {
        $sql_collections = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date = ? AND payment_type = 'received'";
        $res_collections = db_query_one($sql_collections, [$today]);
        $today_collections = $res_collections['total'] ?? 0;

        // Fallback: if payments table doesn't have payment_type, try paid_amount from sales
        if ($today_collections == 0) {
            $sql_collections2 = "SELECT COALESCE(SUM(paid_amount), 0) as total FROM sales WHERE sale_date = ? AND status != 'cancelled'";
            $res_collections2 = db_query_one($sql_collections2, [$today]);
            $today_collections = $res_collections2['total'] ?? 0;
        }
    }
}

// =============================================
// CHARTS DATA - PERMISSION BASED
// Sales & Purchase for 7, 15, 30 days
// =============================================

$chart_period = isset($_GET['chart_days']) ? (int)$_GET['chart_days'] : 7;
if (!in_array($chart_period, [7, 15, 30])) $chart_period = 7;

$sales_data = [];
$purchase_data = [];
$chart_labels = [];

if ($can_view_sales || $can_view_purchases) {
    for ($i = $chart_period - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = $chart_period <= 7 ? date('D', strtotime($date)) : date('d M', strtotime($date));

        // Sales
        if ($can_view_sales) {
            if ($is_sr) {
                $sql = "SELECT COALESCE(SUM(net_amount), 0) as total FROM sales_requests WHERE DATE(created_at) = ? AND status = 'completed' AND sr_user_id = ?";
                $sales_data[] = (float)db_query_one($sql, [$date, $user_id])['total'];
            } else {
                $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE sale_date = ? AND status != 'cancelled'";
                $sales_data[] = (float)db_query_one($sql, [$date])['total'];
            }
        }

        // Purchases
        if ($can_view_purchases) {
            $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM purchases WHERE purchase_date = ? AND status != 'cancelled'";
            $purchase_data[] = (float)db_query_one($sql, [$date])['total'];
        }
    }
}

// =============================================
// TABLE DATA - PERMISSION BASED
// =============================================

// Top Selling Products (Last 30 Days) - visible to all
$top_products = [];
$sql_top_products = "SELECT p.name, SUM(si.quantity) as total_qty, SUM(si.subtotal) as total_amount
                     FROM sale_items si
                     JOIN products p ON si.product_id = p.id
                     JOIN sales s ON si.sale_id = s.id
                     WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                     AND s.status != 'cancelled'
                     GROUP BY si.product_id
                     ORDER BY total_qty DESC
                     LIMIT 10";
$top_products = db_query($sql_top_products);

// Top 10 Customers (All Time) - visible to all
$top_customers = [];
$sql_top_customers = "SELECT c.name, SUM(s.total_amount) as total_sales, COUNT(s.id) as total_orders
                      FROM sales s
                      JOIN customers c ON s.customer_id = c.id
                      WHERE s.status != 'cancelled'
                      GROUP BY s.customer_id
                      ORDER BY total_sales DESC
                      LIMIT 10";
$top_customers = db_query($sql_top_customers);

// Top 10 Staff (This Month) - requires HR access
$top_staff = [];
if ($can_view_hr) {
    $sql_top_staff = "SELECT st.name as name, SUM(s.total_amount) as total_sales, COUNT(s.id) as total_orders
                      FROM sales s
                      JOIN staff st ON s.staff_id = st.id
                      WHERE s.sale_date BETWEEN ? AND ?
                      AND s.status != 'cancelled'
                      GROUP BY s.staff_id
                      ORDER BY total_sales DESC
                      LIMIT 10";
    $top_staff = db_query($sql_top_staff, [$this_month_start, $this_month_end]);
}

// =============================================
// PERMISSION-BASED TABLE DATA
// =============================================

// Recent Sales / Requests - Visible to all, but content restricted if SR
$recent_sales = [];
if ($is_sr && !$has_full_dashboard_access) {
    $sql_recent_sales = "SELECT id, request_number as invoice_number, customer_name, total_amount as net_amount, 'pending' as payment_status, created_at
                         FROM sales_requests
                         WHERE sr_user_id = ?
                         ORDER BY created_at DESC
                         LIMIT 10";
    $recent_sales = db_query($sql_recent_sales, [$user_id]);
} else {
    $sql_recent_sales = "SELECT s.*, c.name as customer_name
                         FROM sales s
                         LEFT JOIN customers c ON s.customer_id = c.id
                         WHERE s.status != 'cancelled'
                         ORDER BY s.created_at DESC
                         LIMIT 10";
    $recent_sales = db_query($sql_recent_sales);
}

// Low Stock Alert - Visible to all
$low_stock_list = [];
$low_stock_count = 0;
$sql_low_stock_count = "SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level AND status = 'active'";
$res_low_stock = db_query_one($sql_low_stock_count);
$low_stock_count = $res_low_stock['count'] ?? 0;

$sql_low_stock_list = "SELECT name, code, stock_quantity, reorder_level
                       FROM products
                       WHERE stock_quantity <= reorder_level
                       AND status = 'active'
                       ORDER BY stock_quantity ASC
                       LIMIT 10";
$low_stock_list = db_query($sql_low_stock_list);

$page_title = 'Dashboard';
$additional_css = '<link href="' . BASE_URL . '/assets/css/dashboard-modern.css?v=' . time() . '" rel="stylesheet">';
include __DIR__ . '/../../templates/header.php';
?>

<!-- Modern Dashboard -->
<div class="dashboard-container">

    <!-- STAT ROW 1: Sales, Customers, Cash -->
    <div class="stats-row row-1">
        <?php if ($can_view_sales): ?>
            <div class="stat-box">
                <i class="fas fa-cart-plus stat-icon"></i>
                <div class="stat-content">
                    <div class="stat-label">TODAY'S SALES</div>
                    <div class="stat-value"><?= format_currency($today_sales) ?></div>
                </div>
            </div>
            <div class="stat-box">
                <i class="fas fa-chart-line stat-icon"></i>
                <div class="stat-content">
                    <div class="stat-label">THIS MONTH'S SALES</div>
                    <div class="stat-value"><?= format_currency($month_sales_amount) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($can_view_customers): ?>
            <div class="stat-box">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-content">
                    <div class="stat-label">TOTAL CUSTOMERS</div>
                    <div class="stat-value"><?= number_format($total_customers) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($can_view_accounting): ?>
            <div class="stat-box cash-in-hand">
                <i class="fas fa-coins stat-icon text-success"></i>
                <div class="stat-content">
                    <div class="stat-label">CASH IN HAND</div>
                    <div class="stat-value text-success"><?= format_currency($cash_in_hand_balance) ?></div>
                </div>
            </div>
            <div class="stat-box bank-balance">
                <i class="fas fa-university stat-icon text-info"></i>
                <div class="stat-content">
                    <div class="stat-label">BANK BALANCE</div>
                    <div class="stat-value text-info"><?= format_currency($bank_balance_amount) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($can_view_sales): ?>
            <div class="stat-box">
                <i class="fas fa-money-bill-wave stat-icon text-success"></i>
                <div class="stat-content">
                    <div class="stat-label">TODAY COLLECTIONS</div>
                    <div class="stat-value text-success"><?= format_currency($today_collections) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- STAT ROW 2: Receivables, Payables, Products, Expenses -->
    <div class="stats-row row-2">
        <?php if ($can_view_customers): ?>
            <div class="stat-box">
                <i class="fas fa-hand-holding-usd stat-icon text-warning"></i>
                <div class="stat-content">
                    <div class="stat-label d-flex align-items-center">
                        TOTAL RECEIVABLES
                        <a href="<?= BASE_URL ?>/modules/reports/receivable-report.php" class="ms-2 text-warning" title="View Details">
                            <i class="fas fa-info-circle"></i>
                        </a>
                    </div>
                    <div class="stat-value text-warning"><?= format_currency($total_receivables) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($can_view_purchases): ?>
            <div class="stat-box">
                <i class="fas fa-file-invoice-dollar stat-icon text-danger"></i>
                <div class="stat-content">
                    <div class="stat-label d-flex align-items-center">
                        TOTAL PAYABLES
                        <a href="<?= BASE_URL ?>/modules/reports/payable-report.php" class="ms-2 text-danger" title="View Details">
                            <i class="fas fa-info-circle"></i>
                        </a>
                    </div>
                    <div class="stat-value text-danger"><?= format_currency($total_payables) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($can_view_products): ?>
            <div class="stat-box">
                <i class="fas fa-boxes stat-icon"></i>
                <div class="stat-content">
                    <div class="stat-label">TOTAL PRODUCTS</div>
                    <div class="stat-value"><?= number_format($total_products) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($can_view_expenses): ?>
            <div class="stat-box">
                <i class="fas fa-receipt stat-icon text-danger"></i>
                <div class="stat-content">
                    <div class="stat-label">TODAY EXPENSES</div>
                    <div class="stat-value text-danger"><?= format_currency($today_expenses) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- STAT ROW 3: Quotations, RMA, Collections -->
    <?php if ($can_view_quotations || $can_view_warranty || $can_view_sales): ?>
        <div class="stats-row row-3">
            <?php if ($can_view_quotations): ?>
                <div class="stat-box">
                    <i class="fas fa-file-signature stat-icon text-primary"></i>
                    <div class="stat-content">
                        <div class="stat-label">TOTAL QUOTATIONS</div>
                        <div class="stat-value text-primary"><?= number_format($total_quotations) ?></div>
                    </div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-hourglass-half stat-icon text-warning"></i>
                    <div class="stat-content">
                        <div class="stat-label">PENDING QUOTATIONS</div>
                        <div class="stat-value text-warning"><?= number_format($pending_quotations) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($can_view_warranty): ?>
                <div class="stat-box">
                    <i class="fas fa-tools stat-icon text-info"></i>
                    <div class="stat-content">
                        <div class="stat-label">TOTAL RMA</div>
                        <div class="stat-value text-info"><?= number_format($total_rma) ?></div>
                    </div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-clock stat-icon text-danger"></i>
                    <div class="stat-content">
                        <div class="stat-label">PENDING RMA</div>
                        <div class="stat-value text-danger"><?= number_format($pending_rma) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- CHARTS ROW - PERMISSION BASED -->
    <!-- ============================================ -->
    <?php if ($can_view_sales || $can_view_purchases): ?>
        <div class="row">
            <?php if ($can_view_sales): ?>
                <div class="col-lg-<?= $can_view_purchases ? '6' : '12' ?> mb-4">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="card-title mb-0">Sales Overview</div>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="?chart_days=7" class="btn btn-<?= $chart_period == 7 ? 'primary' : 'outline-primary' ?> btn-sm">7 Days</a>
                                <a href="?chart_days=15" class="btn btn-<?= $chart_period == 15 ? 'primary' : 'outline-primary' ?> btn-sm">15 Days</a>
                                <a href="?chart_days=30" class="btn btn-<?= $chart_period == 30 ? 'primary' : 'outline-primary' ?> btn-sm">30 Days</a>
                            </div>
                        </div>
                        <div class="chart-container-sm">
                            <canvas id="salesOverviewChart"></canvas>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($can_view_purchases): ?>
                <div class="col-lg-<?= $can_view_sales ? '6' : '12' ?> mb-4">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="card-title mb-0">Purchase Overview</div>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="?chart_days=7" class="btn btn-<?= $chart_period == 7 ? 'warning' : 'outline-warning' ?> btn-sm">7 Days</a>
                                <a href="?chart_days=15" class="btn btn-<?= $chart_period == 15 ? 'warning' : 'outline-warning' ?> btn-sm">15 Days</a>
                                <a href="?chart_days=30" class="btn btn-<?= $chart_period == 30 ? 'warning' : 'outline-warning' ?> btn-sm">30 Days</a>
                            </div>
                        </div>
                        <div class="chart-container-sm">
                            <canvas id="purchaseOverviewChart"></canvas>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- TOP LISTS ROW - PERMISSION BASED -->
    <!-- ============================================ -->
    <div class="row">
        <!-- Top Selling Products -->
        <div class="col-lg-4 mb-4">
            <div class="dashboard-card">
                <div class="card-title">Top Selling Products</div>
                <div class="table-responsive">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Amount (<?= APP_CURRENCY_SYMBOL ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted">No sales data yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_products as $i => $p): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td class="text-end"><?= number_format($p['total_qty']) ?></td>
                                        <td class="text-end"><?= number_format($p['total_amount']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top 10 Customers -->
        <div class="col-lg-4 mb-4">
            <div class="dashboard-card">
                <div class="card-title">Top 10 Customers</div>
                <div class="table-responsive">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Customer Name</th>
                                <th class="text-end">Total (<?= APP_CURRENCY_SYMBOL ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_customers)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No customer data yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_customers as $i => $c): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($c['name']) ?></td>
                                        <td class="text-end"><?= number_format($c['total_sales']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top 10 Staff -->
        <?php if ($can_view_hr): ?>
            <div class="col-lg-4 mb-4">
                <div class="dashboard-card">
                    <div class="card-title">Top 10 Staff (This Month)</div>
                    <div class="table-responsive">
                        <table class="compact-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Staff Name</th>
                                    <th class="text-end">Total (<?= APP_CURRENCY_SYMBOL ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_staff)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No staff sales data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($top_staff as $i => $s): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($s['name']) ?></td>
                                            <td class="text-end"><?= number_format($s['total_sales']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- RECENT SALES & LOW STOCK - PERMISSION-BASED -->
    <!-- ============================================ -->
    <div class="row">
        <!-- Recent Sales -->
        <div class="col-lg-6 mb-4">
            <div class="dashboard-card list-card">
                <div class="card-header-flex">
                    <span class="header-title text-primary">Recent Sales</span>
                    <?php if ($can_view_sales): ?>
                        <a href="<?= BASE_URL ?>/modules/sales/sales-list.php" class="btn btn-sm btn-primary">View All</a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th>INVOICE #</th>
                                <th>CUSTOMER</th>
                                <th>AMOUNT</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_sales)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted">No sales yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_sales as $sale): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sale['invoice_number']) ?></td>
                                        <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                        <td><?= APP_CURRENCY_SYMBOL ?><?= number_format($sale['total_amount'] ?? $sale['net_amount'] ?? 0) ?></td>
                                        <td><span class="badge-status <?= $sale['payment_status'] ?? $sale['status'] ?? 'pending' ?>"><?= ucfirst($sale['payment_status'] ?? $sale['status'] ?? 'pending') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="col-lg-6 mb-4">
            <div class="dashboard-card list-card">
                <div class="card-header-flex">
                    <span class="header-title text-warning">Low Stock Alert <span class="badge bg-danger ms-2"><?= $low_stock_count ?></span></span>
                    <?php if ($can_view_products): ?>
                        <a href="<?= BASE_URL ?>/modules/products/products-list.php?filter=low_stock" class="btn btn-sm btn-warning">View All</a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th>PRODUCT</th>
                                <th>CODE</th>
                                <th>STOCK</th>
                                <th>REORDER</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($low_stock_list)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-success py-4">All products are well stocked!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($low_stock_list as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['name']) ?></td>
                                        <td><?= htmlspecialchars($item['code']) ?></td>
                                        <td class="text-danger font-bold"><?= $item['stock_quantity'] ?></td>
                                        <td><?= $item['reorder_level'] ?></td>
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
    // Function to get current theme colors
    function getThemeColors() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            textColor: isDark ? '#cbd5e1' : '#858796',
            gridColor: isDark ? '#334155' : '#e3e6f0',
            borderColor: isDark ? '#60a5fa' : '#4e73df'
        };
    }

    let salesChart, purchaseChart;

    function initCharts() {
        const colors = getThemeColors();
        Chart.defaults.color = colors.textColor;
        Chart.defaults.borderColor = colors.gridColor;
        Chart.defaults.font.family = "'Inter', 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";

        // Sales Overview Chart
        const canvasSales = document.getElementById('salesOverviewChart');
        if (canvasSales) {
            const ctxSales = canvasSales.getContext('2d');
            if (salesChart) salesChart.destroy();

            salesChart = new Chart(ctxSales, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Sales (<?= APP_CURRENCY_SYMBOL ?>)',
                        data: <?= json_encode($sales_data) ?>,
                        borderColor: colors.borderColor,
                        backgroundColor: colors.borderColor === '#60a5fa' ? 'rgba(96, 165, 250, 0.1)' : 'rgba(78, 115, 223, 0.05)',
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: <?= $chart_period <= 7 ? 4 : ($chart_period <= 15 ? 3 : 2) ?>,
                        pointBackgroundColor: colors.borderColor
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                borderDash: [2, 2],
                                color: colors.gridColor
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Purchase Overview Chart
        const canvasPurchase = document.getElementById('purchaseOverviewChart');
        if (canvasPurchase) {
            const ctxPurchase = canvasPurchase.getContext('2d');
            if (purchaseChart) purchaseChart.destroy();

            purchaseChart = new Chart(ctxPurchase, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Purchases (<?= APP_CURRENCY_SYMBOL ?>)',
                        data: <?= json_encode($purchase_data) ?>,
                        borderColor: '#f6c23e',
                        backgroundColor: 'rgba(246, 194, 62, 0.05)',
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: <?= $chart_period <= 7 ? 4 : ($chart_period <= 15 ? 3 : 2) ?>,
                        pointBackgroundColor: '#f6c23e'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                borderDash: [2, 2],
                                color: colors.gridColor
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    }

    // Initialize charts on load
    document.addEventListener('DOMContentLoaded', initCharts);

    // Listen for theme changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type == "attributes" && mutation.attributeName == "data-theme") {
                initCharts();
            }
        });
    });

    observer.observe(document.documentElement, {
        attributes: true
    });
</script>
