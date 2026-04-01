<?php

/**
 * Units List with Quick Add
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('products.units');

// Check if User can Import/Export CSV
$can_import_export = is_admin() || has_role(get_current_user_id(), 'Manager') || has_role(get_current_user_id(), 'Admin');

// Handle quick add
if (is_post() && isset($_POST['add_unit'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $name = clean_input(post('unit_name'));
        $short_name = clean_input(post('short_name'));
        if (!empty($name) && !empty($short_name)) {
            if (!db_exists('units', ['name' => $name])) {
                db_insert('units', ['name' => $name, 'short_name' => $short_name, 'created_at' => date('Y-m-d H:i:s')]);
                redirect_with_message('units-list.php', 'Unit added successfully', 'success');
            } else {
                redirect_with_message('units-list.php', 'Unit already exists', 'warning');
            }
        }
    }
}

// Handle edit unit
if (is_post() && isset($_POST['edit_unit'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $id = clean_input(post('unit_id'));
        $name = clean_input(post('edit_unit_name'));
        $short_name = clean_input(post('edit_short_name'));
        
        if (!empty($id) && !empty($name) && !empty($short_name)) {
            // Check for duplicates excluding current unit
            $conn = getDB();
            $stmt = $conn->prepare("SELECT id FROM units WHERE name = ? AND id != ?");
            $stmt->execute([$name, $id]);
            
            if ($stmt->fetch()) {
                redirect_with_message('units-list.php', 'Unit name already exists', 'warning');
            } else {
                db_update('units', ['name' => $name, 'short_name' => $short_name], ['id' => $id]);
                redirect_with_message('units-list.php', 'Unit updated successfully', 'success');
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    db_delete('units', ['id' => $_GET['delete']]);
    redirect_with_message('units-list.php', 'Unit deleted', 'success');
}

$units = db_select('units', [], '*', 'name ASC');

$page_title = 'Units';

$page_actions = '';
if ($can_import_export) {
    $page_actions .= '
        <a href="demo_units.csv" class="btn btn-info me-2"><i class="fas fa-download"></i> Demo CSV</a>
        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-file-import"></i> Import CSV</button>
        <a href="export-units.php" class="btn btn-warning me-2"><i class="fas fa-file-export"></i> Export CSV</a>';
}
$page_actions .= '
    <button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print Report</button>';

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
    .col-md-4 { display: none !important; }
    .col-md-8 { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
}
.print-header { display: none; }
</style>
';

include __DIR__ . '/../../templates/header.php';
?>

<div class="print-header">
    <h2><?= htmlspecialchars(BUSINESS_NAME) ?></h2>
    <h4>Units List Report</h4>
    <p>Date: <?= date('d M Y') ?></p>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Add New Unit</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <div class="mb-3">
                        <label>Unit Name</label>
                        <input type="text" name="unit_name" class="form-control" placeholder="e.g., Piece" required>
                    </div>
                    <div class="mb-3">
                        <label>Short Name</label>
                        <input type="text" name="short_name" class="form-control" placeholder="e.g., Pcs" required>
                    </div>
                    <button type="submit" name="add_unit" class="btn btn-primary w-100">
                        <i class="fas fa-plus"></i> Add Unit
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">All Units (<?= count($units) ?>)</h6>
            </div>
            <div class="card-body">
                <table class="table datatable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Short Name</th>
                            <th>Created</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($units as $unit): ?>
                            <tr>
                                <td><?= $unit['id'] ?></td>
                                <td><?= htmlspecialchars($unit['name']) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($unit['short_name']) ?></span></td>
                                <td><?= format_date($unit['created_at']) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info btn-edit" 
                                            data-id="<?= $unit['id'] ?>" 
                                            data-name="<?= htmlspecialchars($unit['name']) ?>" 
                                            data-short="<?= htmlspecialchars($unit['short_name']) ?>"
                                            data-bs-toggle="modal" data-bs-target="#editModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?= $unit['id'] ?>" class="btn btn-sm btn-danger btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($can_import_export): ?>
    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Units from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="import-units.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <div class="form-group mb-3">
                            <label for="csv_file" class="form-label">Select CSV File</label>
                            <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        <div class="alert alert-info small">
                            <strong>Instructions:</strong>
                            <ul class="mb-0">
                                <li>Ensure the file is in CSV format.</li>
                                <li>Columns: Name, Short Name.</li>
                                <li>Unit Name is used to identify existing ones.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="import_csv" class="btn btn-success">Start Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Unit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="unit_id" id="edit_unit_id">
                    <div class="mb-3">
                        <label>Unit Name</label>
                        <input type="text" name="edit_unit_name" id="edit_unit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Short Name</label>
                        <input type="text" name="edit_short_name" id="edit_short_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_unit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.btn-edit');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_unit_id').value = this.dataset.id;
            document.getElementById('edit_unit_name').value = this.dataset.name;
            document.getElementById('edit_short_name').value = this.dataset.short;
        });
    });
});
</script>

<a href="products-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Products</a>

<?php include __DIR__ . '/../../templates/footer.php'; ?>