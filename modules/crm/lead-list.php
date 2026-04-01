<?php

/**
 * Lead List
 * View all market data leads created by staff/SRs
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_once __DIR__ . '/../../includes/permissions.php';

$user_id = get_current_user_id();

// Build query depending on role
$where = "1=1";
$params = [];

// Check if User can Import/Export CSV
$can_import_export = is_admin() || has_role($user_id, 'Manager') || has_role($user_id, 'Admin');

// If standard SR, mostly just show their own. Let's make it configurable or just allow viewing all if they have CRM access
// Usually Market Data is open to staff to see or confined to their own. Easiest is confine to own unless Admin.
if (!is_admin($user_id) && !has_role($user_id, 'Manager')) {
    $where .= " AND l.collected_by = ?";
    $params[] = $user_id;
}

$sql = "SELECT l.*, c.name as category_name, u.username as staff_name
        FROM leads l
        LEFT JOIN lead_categories c ON l.category_id = c.id
        LEFT JOIN users u ON l.collected_by = u.id
        WHERE $where
        ORDER BY l.created_at DESC";

$leads = db_query($sql, $params);

$page_title = 'Leads (Market Data)';

$page_actions = '';
if ($can_import_export) {
    $page_actions .= '
        <a href="demo_leads.csv" class="btn btn-info me-2"><i class="fas fa-download"></i> Demo CSV</a>
        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-file-import"></i> Import CSV</button>
        <a href="export-leads.php" class="btn btn-warning me-2"><i class="fas fa-file-export"></i> Export CSV</a>';
}
$page_actions .= '
    <a href="lead-add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Lead</a>';

include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Leads Collection</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="leadTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Organization</th>
                        <th>Category</th>
                        <th>Contact Person</th>
                        <th>Mobile</th>
                        <th>Staff</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($lead['created_at'])) ?></td>
                            <td><?= htmlspecialchars($lead['organization_name']) ?></td>
                            <td><?= htmlspecialchars($lead['category_name']) ?></td>
                            <td><?= htmlspecialchars($lead['contact_person']) ?></td>
                            <td><?= htmlspecialchars($lead['mobile']) ?></td>
                            <td><?= htmlspecialchars($lead['staff_name']) ?></td>
                            <td>
                                <?php
                                $status_colors = [
                                    'new' => 'primary',
                                    'contacted' => 'info',
                                    'converted' => 'success',
                                    'closed' => 'secondary'
                                ];
                                $color = $status_colors[$lead['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $color ?>"><?= ucfirst($lead['status']) ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info view-btn" data-lead='<?= htmlspecialchars(json_encode($lead), ENT_QUOTES) ?>' title="View Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($lead['gps_latitude'] && $lead['gps_longitude']): ?>
                                    <a href="https://maps.google.com/?q=<?= $lead['gps_latitude'] ?>,<?= $lead['gps_longitude'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="View on Map">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lead Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewDetailsContent">
                <!-- JS injected content -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                    <h5 class="modal-title" id="importModalLabel">Import Leads from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="import-leads.php" method="POST" enctype="multipart/form-data">
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
                                <li>Columns: Organization Name, Contact Person, Mobile, Email, Address, Category, Status, Remarks, GPS Latitude, GPS Longitude.</li>
                                <li>Mobile number is used to update existing leads.</li>
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

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#leadTable').DataTable({
            order: [
                [0, 'desc']
            ]
        });

        $('.view-btn').click(function() {
            const lead = $(this).data('lead');
            let html = `
            <table class="table table-bordered">
                <tr><th>Organization</th><td>${lead.organization_name}</td></tr>
                <tr><th>Category</th><td>${lead.category_name || 'N/A'}</td></tr>
                <tr><th>Contact Person</th><td>${lead.contact_person}</td></tr>
                <tr><th>Mobile</th><td>${lead.mobile}</td></tr>
                <tr><th>Email</th><td>${lead.email || 'N/A'}</td></tr>
                <tr><th>Address</th><td>${lead.address || 'N/A'}</td></tr>
                <tr><th>Status</th><td><span class="badge bg-secondary">${lead.status.toUpperCase()}</span></td></tr>
                <tr><th>Collected By</th><td>${lead.staff_name} on ${lead.created_at}</td></tr>
                <tr><th>Remarks</th><td>${lead.remarks || 'N/A'}</td></tr>
        `;

            if (lead.gps_latitude && lead.gps_longitude) {
                html += `<tr><th>Location</th><td>
                <a href="https://maps.google.com/?q=${lead.gps_latitude},${lead.gps_longitude}" target="_blank">
                    <i class="fas fa-map-marker-alt"></i> View on Google Maps (${lead.gps_latitude}, ${lead.gps_longitude})
                </a>
            </td></tr>`;
            }

            html += `</table>`;

            $('#viewDetailsContent').html(html);
            $('#viewModal').modal('show');
        });
    });
</script>