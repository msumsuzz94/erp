<?php
/**
 * Warranty Serial List Page
 * Track products with serial numbers and their warranty status
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get filter parameters
$warranty_status = get_param('warranty_status', '');
$search = get_param('search', '');
$customer_id = get_param('customer_id', '');

// Build query with warranty expiration calculation
$sql = "SELECT ps.*, 
        p.name as product_name, 
        p.code as product_code,
        p.warranty_duration,
        p.warranty_period,
        c.name as customer_name,
        c.phone as customer_phone,
        CASE 
            WHEN ps.sale_date IS NULL THEN 'Not Sold'
            WHEN (
                CASE 
                    WHEN p.warranty_period = 'Days' THEN DATE_ADD(ps.sale_date, INTERVAL p.warranty_duration DAY)
                    WHEN p.warranty_period = 'Year' THEN DATE_ADD(ps.sale_date, INTERVAL p.warranty_duration YEAR)
                    ELSE DATE_ADD(ps.sale_date, INTERVAL p.warranty_duration MONTH)
                END
            ) < CURDATE() THEN 'Expired'
            ELSE 'Active'
        END as warranty_status,
        DATEDIFF(
            CASE 
                WHEN p.warranty_period = 'Days' THEN DATE_ADD(ps.sale_date, INTERVAL p.warranty_duration DAY)
                WHEN p.warranty_period = 'Year' THEN DATE_ADD(ps.sale_date, INTERVAL p.warranty_duration YEAR)
                ELSE DATE_ADD(ps.sale_date, INTERVAL p.warranty_duration MONTH)
            END, 
            CURDATE()
        ) as days_remaining,
        CASE 
            WHEN p.warranty_period = 'Days' THEN DATE_ADD(ps.sale_date, INTERVAL p.warranty_duration DAY)
            WHEN p.warranty_period = 'Year' THEN DATE_ADD(ps.sale_date, INTERVAL p.warranty_duration YEAR)
            ELSE DATE_ADD(ps.sale_date, INTERVAL p.warranty_duration MONTH)
        END as warranty_expiry_date,
        supp.name as supplier_name
        FROM product_serials ps 
        INNER JOIN products p ON ps.product_id = p.id 
        LEFT JOIN sales s ON ps.sale_id = s.id
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN purchases pur ON ps.purchase_id = pur.id
        LEFT JOIN suppliers supp ON pur.supplier_id = supp.id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (ps.serial_number LIKE ? OR ps.imei LIKE ? OR p.name LIKE ? OR c.name LIKE ? OR supp.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($customer_id)) {
    $sql .= " AND s.customer_id = ?";
    $params[] = $customer_id;
}

// Add warranty status filter using HAVING clause
$sql .= " ORDER BY ps.sale_date DESC";
$all_serials = db_query($sql, $params);

// Filter by warranty status in PHP since we can't use HAVING with our simple query builder
$serials = [];
if (!empty($all_serials)) {
    foreach ($all_serials as $serial) {
        if (empty($warranty_status) || $serial['warranty_status'] === $warranty_status) {
            $serials[] = $serial;
        }
    }
}

// Get customers for filter dropdown
$customers = db_query("SELECT id, name FROM customers ORDER BY name ASC");

$page_title = 'Warranty Serial Tracking';
$page_actions = '<button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print Report</button>';

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
    .row > div { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
}
.print-header { display: none; }
</style>
';

include __DIR__ . '/../../templates/header.php';
?>

<div class="print-header">
    <h2><?= htmlspecialchars(BUSINESS_NAME) ?></h2>
    <h4>Warranty Serial Tracking Report</h4>
    <p>Date: <?= date('d M Y') ?></p>
</div>

<!-- Filter Section -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Warranty Serials</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Serial, IMEI, Product, Customer" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Warranty Status</label>
                        <select name="warranty_status" class="form-control">
                            <option value="">All Warranties</option>
                            <option value="Active" <?= $warranty_status === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Expired" <?= $warranty_status === 'Expired' ? 'selected' : '' ?>>Expired</option>
                            <option value="Not Sold" <?= $warranty_status === 'Not Sold' ? 'selected' : '' ?>>Not Sold</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Customer</label>
                        <select name="customer_id" class="form-control select2">
                            <option value="">All Customers</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['id'] ?>" <?= $customer_id == $customer['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($customer['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="serial-list.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <?php
    $active_count = 0;
    $expired_count = 0;
    $expiring_soon_count = 0;
    $not_sold_count = 0;
    
    foreach ($all_serials as $s) {
        if ($s['warranty_status'] === 'Active') {
            $active_count++;
            if ($s['days_remaining'] <= 30 && $s['days_remaining'] > 0) {
                $expiring_soon_count++;
            }
        } elseif ($s['warranty_status'] === 'Expired') {
            $expired_count++;
        } elseif ($s['warranty_status'] === 'Not Sold') {
            $not_sold_count++;
        }
    }
    ?>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Warranties</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $active_count ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-shield-alt fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Expiring Soon (30 Days)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $expiring_soon_count ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Expired Warranties</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $expired_count ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Not Sold</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $not_sold_count ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-box fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Serials Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Warranty Serial List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="serialsTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Serial Number</th>
                        <th>Supplier</th>
                        <th>Customer</th>
                        <th>Sale Date</th>
                        <th>Warranty Period</th>
                        <th>Expiry Date</th>
                        <th>Days Remaining</th>
                        <th>Warranty Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($serials)): ?>
                        <tr>
                            <td colspan="9" class="text-center">No serials found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($serials as $serial): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($serial['product_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($serial['product_code']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($serial['serial_number'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($serial['supplier_name'] ?? '-') ?></td>
                                <td>
                                    <?php if ($serial['customer_name']): ?>
                                        <?= htmlspecialchars($serial['customer_name']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($serial['customer_phone'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $serial['sale_date'] ? format_date($serial['sale_date']) : '-' ?></td>
                                <td>
                                    <?= $serial['warranty_duration'] ?> <?= $serial['warranty_period'] ?>
                                </td>
                                <td>
                                    <?php if ($serial['warranty_expiry_date']): ?>
                                        <?= format_date($serial['warranty_expiry_date']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($serial['warranty_status'] === 'Active'): ?>
                                        <?php if ($serial['days_remaining'] <= 30): ?>
                                            <span class="badge badge-warning"><?= $serial['days_remaining'] ?> days</span>
                                        <?php else: ?>
                                            <?= $serial['days_remaining'] ?> days
                                        <?php endif; ?>
                                    <?php elseif ($serial['warranty_status'] === 'Expired'): ?>
                                        <span class="text-danger">Expired</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'Active' => 'success',
                                        'Expired' => 'danger',
                                        'Not Sold' => 'secondary'
                                    ];
                                    $badge_class = $status_badges[$serial['warranty_status']] ?? 'secondary';
                                    
                                    // Extra highlight for expiring soon
                                    if ($serial['warranty_status'] === 'Active' && $serial['days_remaining'] <= 30 && $serial['days_remaining'] > 0) {
                                        $badge_class = 'warning';
                                    }
                                    ?>
                                    <span class="badge badge-<?= $badge_class ?>">
                                        <?= $serial['warranty_status'] ?>
                                        <?php if ($serial['warranty_status'] === 'Active' && $serial['days_remaining'] <= 30 && $serial['days_remaining'] > 0): ?>
                                            (Soon)
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Only initialize DataTable if there are actual data rows
    var tableRows = $('#serialsTable tbody tr').length;
    var hasData = tableRows > 0 && !$('#serialsTable tbody tr td[colspan]').length;
    
    if (hasData) {
        // Check if DataTable already exists and destroy it
        if ($.fn.DataTable.isDataTable('#serialsTable')) {
            $('#serialsTable').DataTable().destroy();
        }
        
        // Initialize DataTable
        $('#serialsTable').DataTable({
            "pageLength": 25,
            "order": [[4, "desc"]], // Sort by Sale Date descending
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
    } else {
        console.log('No data in table - DataTables not initialized');
    }
});
</script>
