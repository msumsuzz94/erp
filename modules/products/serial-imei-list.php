<?php
/**
 * Serial/IMEI List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get filter parameters
$status = get_param('status', '');
$search = get_param('search', '');
$stock_type = get_param('stock_type', '');

// Build query
$sql = "SELECT ps.*, p.name as product_name, p.code as product_code, 
               p.warranty_duration, p.warranty_period,
               pur.id as purchase_id, sup.name as supplier_name
        FROM product_serials ps 
        INNER JOIN products p ON ps.product_id = p.id 
        LEFT JOIN purchases pur ON ps.purchase_id = pur.id
        LEFT JOIN suppliers sup ON pur.supplier_id = sup.id
        WHERE 1=1";
$params = [];

if (!empty($status)) {
    $sql .= " AND ps.status = ?";
    $params[] = $status;
}

if (!empty($stock_type)) {
    if ($stock_type === 'current') {
        // For current stock, check either stock_type is null/empty or explicitly 'current'
        $sql .= " AND (ps.stock_type IS NULL OR ps.stock_type = '' OR ps.stock_type = 'current')";
    } else {
        // For RMA or damaged stock, match exact stock_type
        $sql .= " AND ps.stock_type = ?";
        $params[] = $stock_type;
    }
}

if (!empty($search)) {
    $sql .= " AND (ps.serial_number LIKE ? OR p.name LIKE ? OR sup.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY ps.created_at DESC";
$serials = db_query($sql, $params);

$page_title = 'Serial/IMEI Tracking';
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
    .card-body { padding: 0 !important; }
}
</style>
';

include __DIR__ . '/../../templates/header.php';

// Include centralized print header
include __DIR__ . '/../../templates/print-header.php';
?>

<!-- Filter Section -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filter Serials</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Serial Number, Product Name, Supplier" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="in_stock" <?= $status === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                            <option value="sold" <?= $status === 'sold' ? 'selected' : '' ?>>Sold</option>
                            <option value="returned" <?= $status === 'returned' ? 'selected' : '' ?>>Returned</option>
                            <option value="defective" <?= $status === 'defective' ? 'selected' : '' ?>>Defective</option>
                            <option value="rma" <?= $status === 'rma' ? 'selected' : '' ?>>RMA</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Stock Type</label>
                        <select name="stock_type" class="form-control">
                            <option value="">All Stock Types</option>
                            <option value="current" <?= $stock_type === 'current' ? 'selected' : '' ?>>Current Stock</option>
                            <option value="rma" <?= $stock_type === 'rma' ? 'selected' : '' ?>>RMA Stock</option>
                            <option value="damaged" <?= $stock_type === 'damaged' ? 'selected' : '' ?>>Damaged Stock</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="serial-imei-list.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Serials Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Serial/IMEI List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="serialsTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Product Code</th>
                        <th>Serial Number</th>
                        <th>Supplier</th>
                        <th>Warranty Period</th>
                        <th>Purchase Date</th>
                        <th>Sale Date</th>
                        <th>Stock Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($serials)): ?>
                        <?php foreach ($serials as $serial): ?>
                            <tr>
                                <td><?= htmlspecialchars($serial['product_name']) ?></td>
                                <td><?= htmlspecialchars($serial['product_code']) ?></td>
                                <td><?= htmlspecialchars($serial['serial_number'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($serial['supplier_name'] ?? '-') ?></td>
                                <td><?= $serial['warranty_duration'] ?> <?= $serial['warranty_period'] ?></td>
                                <td><?= $serial['purchase_date'] ? format_date($serial['purchase_date']) : '-' ?></td>
                                <td><?= $serial['sale_date'] ? format_date($serial['sale_date']) : '-' ?></td>
                                <td>
                                    <?php
                                    $type = $serial['stock_type'] ?: 'current';
                                    $type_badge = [
                                        'current' => 'secondary',
                                        'rma' => 'info',
                                        'damaged' => 'warning'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $type_badge[$type] ?? 'light' ?> text-<?= $type === 'current' ? 'white' : 'dark' ?>">
                                        <?= ucfirst($type) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $badge_class = [
                                        'in_stock' => 'success',
                                        'sold' => 'primary',
                                        'returned' => 'warning',
                                        'defective' => 'danger',
                                        'rma' => 'info'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $badge_class[$serial['status']] ?? 'secondary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $serial['status'])) ?>
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
    $('#serialsTable').DataTable({
        "pageLength": 25,
        "order": [[0, "asc"]]
    });
});
</script>
