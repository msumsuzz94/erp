<?php

/**
 * Brands List Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
$can_import_export = is_admin() || has_role(get_current_user_id(), 'Manager') || has_role(get_current_user_id(), 'Admin');

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $brand_id = (int)$_POST['delete_id'];
        if (db_delete('brands', ['id' => $brand_id])) {
            redirect_with_message($_SERVER['PHP_SELF'], 'Brand deleted successfully', 'success');
        } else {
            redirect_with_message($_SERVER['PHP_SELF'], 'Failed to delete brand', 'error');
        }
    }
}

$brands = db_select('brands', [], '*', 'name ASC');

$page_title = 'Product Brands';

$page_actions = '';
if ($can_import_export) {
    $page_actions .= '
        <a href="demo_brands.csv" class="btn btn-info me-2"><i class="fas fa-download"></i> Demo CSV</a>
        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-file-import"></i> Import CSV</button>
        <a href="export-brands.php" class="btn btn-warning me-2"><i class="fas fa-file-export"></i> Export CSV</a>';
}
$page_actions .= '
    <button onclick="window.print()" class="btn btn-success me-2"><i class="fas fa-print"></i> Print Report</button>
    <a href="brand-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Brand</a>';

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
    <h4>Product Brands Report</h4>
    <p>Date: <?= date('d M Y') ?></p>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Brands List</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="brandsTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Products Count</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($brands as $brand): ?>
                        <?php
                        $product_count = db_count('products', ['brand_id' => $brand['id']]);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($brand['name']) ?></td>
                            <td><?= htmlspecialchars($brand['description'] ?? '-') ?></td>
                            <td><?= $product_count ?></td>
                            <td><?= format_date($brand['created_at']) ?></td>
                            <td>
                                <a href="brand-add.php?id=<?= $brand['id'] ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($product_count == 0): ?>
                                    <button type="button" class="btn btn-sm btn-danger delete-brand" data-id="<?= $brand['id'] ?>" data-name="<?= htmlspecialchars($brand['name']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($can_import_export): ?>
    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Brands from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="import-brands.php" method="POST" enctype="multipart/form-data">
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
                                <li>Columns: Name, Description.</li>
                                <li>Brand Name is used to identify existing ones.</li>
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

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<?php
ob_start();
?>
<script>
    $(document).ready(function() {
        console.log('Brands List Script Initialized');

        // Initialize DataTable
        if ($.fn.DataTable) {
            $('#brandsTable').DataTable({
                "pageLength": 25,
                "order": [
                    [0, "asc"]
                ]
            });
        }

        // Use event delegation for delete button
        $(document).on('click', '.delete-brand', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const name = $(this).data('name');

            console.log('Delete button clicked for brand:', name, 'ID:', id);

            if (confirm('Are you sure you want to delete brand "' + name + '"?')) {
                const form = document.getElementById('deleteForm');
                const input = document.getElementById('delete_id');
                if (form && input) {
                    input.value = id;
                    console.log('Submitting delete form for ID:', id);
                    form.submit();
                } else {
                    console.error('Delete form or input not found');
                }
            }
        });
    });
</script>
<?php
$additional_js = ob_get_clean();
include __DIR__ . '/../../templates/footer.php';
?>