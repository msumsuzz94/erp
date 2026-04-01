<?php
/**
 * Sales List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$search = get_param('search', '');
$customer_id = get_param('customer_id', '');
$sold_by = get_param('sold_by', '');
$status = get_param('status', '');
$payment_status = get_param('payment_status', '');
$from_date = get_param('from_date', '');
$to_date = get_param('to_date', '');

$sql = "SELECT s.*, c.name as customer_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.status = 'cancelled'";

$params = [];

if (!empty($search)) {
    $sql .= " AND (s.invoice_number LIKE ? OR c.name LIKE ? OR s.sold_by LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
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

// Status is always cancelled, no need for status filter

if (!empty($payment_status)) {
    $sql .= " AND s.payment_status = ?";
    $params[] = $payment_status;
}

if (!empty($from_date)) {
    $sql .= " AND s.sale_date >= ?";
    $params[] = $from_date;
}

if (!empty($to_date)) {
    $sql .= " AND s.sale_date <= ?";
    $params[] = $to_date;
}

$sql .= " ORDER BY s.created_at DESC";
$sales = db_query($sql, $params);

// Get dependencies for filters
$customers_list = db_query("SELECT id, name FROM customers ORDER BY name ASC");
$salespeople_list = db_query("SELECT DISTINCT sold_by FROM sales WHERE sold_by IS NOT NULL AND sold_by != '' ORDER BY sold_by ASC");

$page_title = 'Cancelled Sales';
$page_actions = '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <a href="pos.php" class="btn btn-primary"><i class="fas fa-cash-register"></i> POS</a>';

$additional_css = '
<style>
@media print {
    .btn, .sidebar, #sidebar, .topbar, .navbar, .card-header, .no-print, form { display: none !important; }
    .container-fluid { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: 100% !important; }
    #content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table-responsive { overflow: visible !important; }
    .table { width: 100% !important; border-collapse: collapse !important; }
    .table th, .table td { border: 1px solid #ddd !important; padding: 8px !important; }
    body { padding-top: 0 !important; background: white !important; }
    .text-gray-800 { color: black !important; }
    .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
    .card-body { padding: 0 !important; }
}
.print-header { display: none; }
</style>
';

include __DIR__ . '/../../templates/header.php';
?>

<div class="print-header">
    <h2><?= htmlspecialchars(BUSINESS_NAME) ?></h2>
    <h4>Cancelled Sales Report</h4>
    <p>Date: <?= date('d M Y') ?></p>
</div>

<!-- Action Section -> Cancel Invoice -->
<div class="card shadow mb-4 border-left-danger">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-times-circle"></i> Cancel a Sale</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="mb-0">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <label>Enter Invoice Number</label>
                        <input type="text" name="find_invoice" class="form-control" placeholder="e.g. INV-..." value="<?= htmlspecialchars($_GET['find_invoice'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Find</button>
                </div>
            </div>
        </form>
        
        <?php if (!empty($_GET['find_invoice'])): ?>
            <hr>
            <?php 
            $find_inv = clean_input($_GET['find_invoice']);
            // Join with customers to get name
            $sale_target = db_query("SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.invoice_number = ?", [$find_inv]);
            
            if (!empty($sale_target)): 
                $st = $sale_target[0];
            ?>
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <strong>Invoice:</strong> <?= htmlspecialchars($st['invoice_number']) ?> &nbsp;|&nbsp;
                        <strong>Date:</strong> <?= format_date($st['sale_date']) ?> &nbsp;|&nbsp;
                        <strong>Customer:</strong> <?= htmlspecialchars($st['customer_name'] ?? 'Walk-in') ?> &nbsp;|&nbsp;
                        <strong>Total:</strong> <?= format_currency($st['total_amount']) ?> &nbsp;|&nbsp;
                        <strong>Status:</strong> 
                        <?php if ($st['status'] === 'cancelled'): ?>
                            <span class="badge bg-danger">Already Cancelled</span>
                        <?php else: ?>
                            <span class="badge bg-success"><?= ucfirst($st['status']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="sale-view.php?id=<?= $st['id'] ?>" class="btn btn-info btn-sm" target="_blank"><i class="fas fa-eye"></i> View</a>
                        <?php if ($st['status'] !== 'cancelled'): ?>
                            <form method="POST" action="sale-cancel-action.php" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this sale? This will reverse stock and accounting entries.');">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="sale_id" value="<?= $st['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Cancel Sale">
                                    <i class="fas fa-times-circle"></i> Cancel Sale 
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">No invoice found with number "<?= htmlspecialchars($find_inv) ?>".</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Section -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Sales</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Invoice, Name..." value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control select2">
                            <option value="">All Customers</option>
                            <?php foreach ($customers_list as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (string)$customer_id === (string)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Sales By</label>
                        <select name="sold_by" class="form-control select2">
                            <option value="">All Salespeople</option>
                            <?php foreach ($salespeople_list as $sp): ?>
                                <option value="<?= htmlspecialchars($sp['sold_by']) ?>" <?= $sold_by === $sp['sold_by'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sp['sold_by']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Status</label>
                        <div class="d-flex gap-1">
                                <!-- Removed Status Selection -->
                            <select name="payment_status" class="form-control">
                                <option value="">Payment</option>
                                <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="partial" <?= $payment_status === 'partial' ? 'selected' : '' ?>>Partial</option>
                                <option value="unpaid" <?= $payment_status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Date Range</label>
                        <div class="input-group">
                            <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
                            <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Sales Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-danger">Cancelled Sales Invoices</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="salesTable">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Sales By</th>
                        <th>Products &amp; Serials</th>
                        <th>Total Amount</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Payment Status</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?= htmlspecialchars($sale['invoice_number']) ?></td>
                                <td><?= format_date($sale['sale_date']) ?></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($sale['sold_by'] ?: 'N/A') ?></span></td>
                                <td>
                                    <?php
                                    $s_items = db_query("SELECT si.product_id, si.quantity, si.description, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?", [$sale['id']]);
                                    if(count($s_items) > 0) {
                                        echo "<ul class='mb-0 ps-3' style='font-size: 0.85rem;'>";
                                        foreach($s_items as $si) {
                                            $desc = $si['description'] ?? '';
                                            $serials_text = '';
                                            
                                            if (strpos($desc, 'Cancelled Serials:') !== false) {
                                                $parts = explode('Cancelled Serials:', $desc);
                                                if (isset($parts[1])) {
                                                    $serials_text = trim($parts[1]);
                                                }
                                            }
                                            
                                            echo "<li><b>" . htmlspecialchars($si['name']) . "</b> (x" . (float)$si['quantity'] . ")";
                                            if (!empty($serials_text)) {
                                                echo "<br><small class='text-muted'>Serials: " . htmlspecialchars($serials_text) . "</small>";
                                            }
                                            echo "</li>";
                                        }
                                        echo "</ul>";
                                    } else {
                                        echo "<span class='text-muted'>No items</span>";
                                    }
                                    ?>
                                </td>
                                <td><?= format_currency($sale['total_amount']) ?></td>
                                <td><?= format_currency($sale['paid_amount']) ?></td>
                                <td><?= format_currency($sale['due_amount']) ?></td>
                                <td>
                                    <?php
                                    $badge_class = [
                                        'paid' => 'success',
                                        'partial' => 'warning',
                                        'unpaid' => 'danger'
                                    ];
                                    $p_status = $sale['payment_status'] ?? 'unpaid';
                                    ?>
                                    <span class="badge bg-<?= $badge_class[$p_status] ?? 'secondary' ?>">
                                        <?= ucfirst($p_status) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $s_status = $sale['status'] ?? 'pending'; 
                                    $s_badge_class = 'secondary';
                                    if ($s_status === 'completed') $s_badge_class = 'success';
                                    elseif ($s_status === 'cancelled') $s_badge_class = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $s_badge_class ?>">
                                        <?= ucfirst($s_status) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="sale-view.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="invoice-print.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-primary" title="Print Invoice" target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
ob_start();
?>
<script>
$(document).ready(function() {
    console.log('Sales List Script Initialized');
    
    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#salesTable').DataTable({
            "pageLength": 25,
            "order": [[1, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": 9 }
            ]
        });
    }
});
</script>
<?php 
$additional_js = ob_get_clean();
include __DIR__ . '/../../templates/footer.php'; 
?>
