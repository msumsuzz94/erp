<?php
/**
 * System Data Cleanup Tool
 * Allows administrators to clear transactional data and reset specific modules.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Check if user is logged in
require_login();

// Check if user has permission (Only Super Admins should access this)
// Super Admin Role ID is 1
if (!isset($_SESSION['user_role_id']) || $_SESSION['user_role_id'] != 1) {
    $_SESSION['error'] = "You do not have permission to access the Data Cleanup tool.";
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$page_title = "Data Cleanup";
$success_msg = "";
$error_msg = "";

// Consolidate Cleanup Map
// This map defines both the processing logic and the UI generation.
$cleanup_map = [
    'sales' => [
        'label' => 'Sales & POS',
        'icon' => 'fas fa-shopping-cart',
        'desc' => 'All sales, payments, and return records',
        'tables' => ['sale_payments', 'bill_collections', 'sale_return_item_serial', 'sale_return_item_serials', 'sale_return_items', 'sales_returns', 'sale_items', 'sales', 'sales_requests']
    ],
    'purchases' => [
        'label' => 'Purchases & Returns',
        'icon' => 'fas fa-truck-loading',
        'desc' => 'All purchase orders and vendor returns',
        'tables' => ['purchase_payments', 'purchase_return_items', 'purchase_returns', 'purchase_items', 'purchases']
    ],
    'quotations' => [
        'label' => 'Quotation Data',
        'icon' => 'fas fa-file-invoice',
        'desc' => 'All quotation records and items',
        'tables' => ['quotation_items', 'quotations']
    ],
    'paid_service' => [
        'label' => 'Paid Service',
        'icon' => 'fas fa-tools',
        'desc' => 'Service tickets, parts, status logs',
        'tables' => ['service_status_logs', 'service_tickets', 'service_parts', 'service_ticket_serials']
    ],
    'expenses' => [
        'label' => 'Expense Records',
        'icon' => 'fas fa-money-bill-wave',
        'desc' => 'All expense records and categories',
        'tables' => ['expenses']
    ],
    'accounts' => [
        'label' => 'Accounts & Finance',
        'icon' => 'fas fa-coins',
        'desc' => 'Cash/bank transactions, closings',
        'tables' => ['bank_transactions', 'cash_transactions', 'daily_closings']
    ],
    'market_data' => [
        'label' => 'Market Data & Leads',
        'icon' => 'fas fa-chart-line',
        'desc' => 'All leads and lead tracking data',
        'tables' => ['leads', 'lead_categories']
    ],
    'hr' => [
        'label' => 'Human Resources (HR)',
        'icon' => 'fas fa-user-tie',
        'desc' => 'Attendance, leaves, salaries, staff docs',
        'tables' => [
            'attendance_logs', 'attendance_summary', 'attendance', 'device_heartbeat_log', 
            'attendance_devices', 'staff_documents', 'staff_departments', 'staff_roles', 'staff',
            'departments', 'leaves', 'salaries', 'staff_salary_payments'
        ]
    ],
    'marketing' => [
        'label' => 'Marketing',
        'icon' => 'fas fa-bullhorn',
        'desc' => 'Campaigns, SMS logs, templates',
        'tables' => ['marketing_campaigns', 'marketing_campaign_logs', 'marketing_settings', 'marketing_templates']
    ],
    'stock_transfers' => [
        'label' => 'Stock Transfers',
        'icon' => 'fas fa-exchange-alt',
        'desc' => 'Internal warehouse transfers',
        'tables' => ['stock_transfer_items', 'stock_transfers']
    ],
    'reports' => [
        'label' => 'Reports & History',
        'icon' => 'fas fa-file-contract',
        'desc' => 'Activity logs, backup history',
        'tables' => ['activity_logs', 'backup_history']
    ],
    'notification' => [
        'label' => 'Notifications & Logs',
        'icon' => 'fas fa-bell',
        'desc' => 'System notifications, message logs',
        'tables' => ['notifications', 'message_log', 'scheduled_messages', 'staff_broadcasts']
    ],
    'media' => [
        'label' => 'Media & Uploaded Files',
        'icon' => 'fas fa-images',
        'desc' => 'Deletes ALL files in the uploads folder',
        'tables' => [] // Handled by file system logic
    ],
    'live_map' => [
        'label' => 'Live Map & Tracking',
        'icon' => 'fas fa-map-marked-alt',
        'desc' => 'Staff GPS history, routes, and geofence logs',
        'tables' => ['staff_locations', 'geofence_visits', 'livemap_assigned_route_points', 'livemap_assigned_routes']
    ],
    'master_data' => [
        'label' => 'Master Data (Caution)',
        'icon' => 'fas fa-database',
        'desc' => 'Brands, categories, units, warehouses',
        'tables' => ['brands', 'categories', 'sub_categories', 'units', 'warehouses', 'expense_categories']
    ]
];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cleanup') {
    $password = $_POST['password'] ?? '';
    $selected_categories = $_POST['categories'] ?? [];
    $is_complete_reset = isset($_POST['complete_reset']);

    // Verify Password
    /** @var PDO $conn */
    global $conn;
    if (!$conn) {
        $error_msg = "Database connection error.";
    } else {
        $user_id = get_current_user_id();
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        try {
            $deleted_rows = 0;
            $files_deleted = 0;

            // Disable foreign key checks for bulk deletion
            db_query("SET FOREIGN_KEY_CHECKS = 0");

            if ($is_complete_reset) {
                // Determine ALL tables in the database dynamically
                $stmt = $conn->query("SHOW TABLES");
                $all_tables_db = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Tables to preserve (Users & Access, Settings, System Tables)
                $keep_tables = [
                    'users', 
                    'roles', 
                    'permissions', 
                    'role_permissions', 
                    'password_resets',
                    'menu_items', 
                    'business_settings', 
                    'channel_settings', 
                    'sms_settings', 
                    'invoice_settings', 
                    'backup_settings', 
                    'marketing_settings', 
                    'attendance_settings', 
                    'app_license', 
                    'server_licenses', 
                    'system_updates', 
                    'message_templates',
                    'livemap_settings',
                    'geofences'
                ];

                // Remove preserved tables from the delete list - everything else goes!
                $tables_to_delete = array_diff($all_tables_db, $keep_tables);

                foreach ($tables_to_delete as $table) {
                    db_query("DELETE FROM `$table`");
                    $deleted_rows++;
                }

                // File system cleanup
                $upload_dir = BASE_PATH . '/uploads';
                $files_deleted = delete_directory_contents_recursive($upload_dir);
                
                $success_msg = "Complete system reset successful! All data cleared except Users & Settings (" . count($tables_to_delete) . " tables cleared).";
            } else {
                // Category-wise cleanup
                foreach ($selected_categories as $key) {
                    if (isset($cleanup_map[$key])) {
                        foreach ($cleanup_map[$key]['tables'] as $table) {
                            db_query("DELETE FROM `$table` ");
                            $deleted_rows++;
                        }

                        // Special handling for Media
                        if ($key === 'media') {
                            $upload_dir = BASE_PATH . '/uploads';
                            $files_deleted += delete_directory_contents_recursive($upload_dir);
                        }
                    }
                }
                $success_msg = "Cleanup task completed! Modules processed. Files deleted: $files_deleted.";
            }

            db_query("SET FOREIGN_KEY_CHECKS = 1");
            log_activity($user_id, 'data_cleanup', "Executed data cleanup. Complete reset: " . ($is_complete_reset ? 'Yes' : 'No'));

        } catch (Exception $e) {
            db_query("SET FOREIGN_KEY_CHECKS = 1");
            $error_msg = "Error during cleanup: " . $e->getMessage();
        }
    } else {
        $error_msg = "Invalid administrative password. Access denied.";
    }
}
}

/**
 * Robust recursive deletion for directory contents
 */
function delete_directory_contents_recursive($dir) {
    if (!is_dir($dir)) return 0;
    
    $count = 0;
    $files = array_diff(scandir($dir), array('.', '..', '.gitignore', 'index.html'));
    
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            $count += delete_directory_contents_recursive($path);
            @rmdir($path);
        } else {
            @unlink($path);
            $count++;
        }
    }
    return $count;
}

include BASE_PATH . '/templates/header.php';
?>

<div class="container-fluid px-4 pt-4">
    <div class="row">
        <div class="col-xl-9 col-lg-11 mx-auto">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h5 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-broom me-2"></i> System Data Cleanup
                        </h5>
                        <p class="text-muted small mb-0 mt-1">Permanently remove transactional data and reset system modules</p>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if ($success_msg): ?>
                        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4">
                            <i class="fas fa-check-circle fs-4 me-3"></i>
                            <div><?= $success_msg ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_msg): ?>
                        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4">
                            <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                            <div><?= $error_msg ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-warning border-0 shadow-sm mb-4" style="background-color: #fff9db; color: #856404;">
                        <div class="d-flex">
                            <i class="fas fa-info-circle fs-4 me-3 mt-1"></i>
                            <div>
                                <h6 class="font-weight-bold">Before You Proceed:</h6>
                                <p class="mb-0 small">This action is <strong>irreversible</strong>. It will permanently delete the selected data from your database. We strongly recommend creating a full database backup before running this tool.</p>
                            </div>
                        </div>
                    </div>

                    <form action="" method="POST" id="cleanupForm">
                        <input type="hidden" name="action" value="cleanup">
                        
                        <div class="row g-4">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <h6 class="text-uppercase text-muted fw-bold small mb-3">Core Modules</h6>
                                <?php 
                                $keys = array_keys($cleanup_map);
                                for ($i = 0; $i < ceil(count($keys) / 2); $i++): 
                                    $key = $keys[$i];
                                    $cat = $cleanup_map[$key];
                                ?>
                                    <div class="category-item mb-3 p-3 rounded border">
                                        <div class="form-check d-flex align-items-center">
                                            <input type="checkbox" class="form-check-input category-check fs-5 me-3 mt-0" name="categories[]" value="<?= $key ?>" id="cat_<?= $key ?>">
                                            <label class="form-check-label w-100" for="cat_<?= $key ?>">
                                                <div class="d-flex align-items-center mb-1">
                                                    <div class="icon-box me-2 text-primary">
                                                        <i class="<?= $cat['icon'] ?>"></i>
                                                    </div>
                                                    <span class="fw-bold fs-6"><?= $cat['label'] ?></span>
                                                </div>
                                                <small class="text-muted d-block"><?= $cat['desc'] ?></small>
                                            </label>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-6">
                                <h6 class="text-uppercase text-muted fw-bold small mb-3">System & Support</h6>
                                <?php 
                                for ($i = ceil(count($keys) / 2); $i < count($keys); $i++): 
                                    $key = $keys[$i];
                                    $cat = $cleanup_map[$key];
                                ?>
                                    <div class="category-item mb-3 p-3 rounded border">
                                        <div class="form-check d-flex align-items-center">
                                            <input type="checkbox" class="form-check-input category-check fs-5 me-3 mt-0" name="categories[]" value="<?= $key ?>" id="cat_<?= $key ?>">
                                            <label class="form-check-label w-100" for="cat_<?= $key ?>">
                                                <div class="d-flex align-items-center mb-1">
                                                    <div class="icon-box me-2 text-primary">
                                                        <i class="<?= $cat['icon'] ?>"></i>
                                                    </div>
                                                    <span class="fw-bold fs-6"><?= $cat['label'] ?></span>
                                                </div>
                                                <small class="text-muted d-block"><?= $cat['desc'] ?></small>
                                            </label>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="reset-area mt-4 p-4 rounded bg-light border-dashed">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input fs-4 me-2" type="checkbox" role="switch" name="complete_reset" id="completeReset">
                                        <label class="form-check-label fw-bold text-danger pt-1" for="completeReset">
                                            COMPLETE SYSTEM RESET
                                        </label>
                                    </div>
                                    <p class="text-muted small mb-0 ps-5">This will wipe ALL transactional data, activity logs, and uploaded media files. <strong>System settings, users, and permissions will be preserved.</strong></p>
                                </div>
                                <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                    <div class="d-inline-block p-2 bg-success bg-opacity-10 text-success rounded small border border-success border-opacity-25">
                                        <i class="fas fa-shield-alt me-1"></i> Settings & Users are safe
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="auth-section mt-5 border-top pt-4">
                            <div class="row">
                                <div class="col-md-8 offset-md-2">
                                    <div class="card bg-light border-0">
                                        <div class="card-body p-4 text-center">
                                            <h6 class="fw-bold mb-3">Authorization Required</h6>
                                            <div class="input-group mb-3 mx-auto" style="max-width: 400px;">
                                                <span class="input-group-text bg-white border-end-0">
                                                    <i class="fas fa-lock text-muted"></i>
                                                </span>
                                                <input type="password" name="password" class="form-control border-start-0 ps-0" required placeholder="Enter administrative password">
                                            </div>
                                            <button type="submit" class="btn btn-danger btn-lg px-5 shadow-sm" onclick="return confirmAction()">
                                                <i class="fas fa-trash-alt me-2"></i> Execute Cleanup Task
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary: #4e73df;
}
.category-item {
    transition: all 0.2s ease-in-out;
    background: #fff;
}
.category-item:hover {
    background: #f8f9fc;
    border-color: var(--primary) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}
.icon-box {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.border-dashed {
    border: 2px dashed #e3e6f0 !important;
}
.bg-light-danger {
    background-color: rgba(231, 74, 59, 0.05);
}

/* Dark Mode Overrides */
[data-theme="dark"] .card {
    background-color: #1a1a1a;
    color: #e0e0e0;
}
[data-theme="dark"] .bg-white {
    background-color: #1a1a1a !important;
}
[data-theme="dark"] .border {
    border-color: #333 !important;
}
[data-theme="dark"] .category-item {
    background-color: #222;
}
[data-theme="dark"] .category-item:hover {
    background-color: #2a2a2a;
    border-color: var(--primary) !important;
}
[data-theme="dark"] .bg-light {
    background-color: #222 !important;
}
[data-theme="dark"] .input-group-text {
    background-color: #333 !important;
    border-color: #444 !important;
    color: #ccc;
}
[data-theme="dark"] .form-control {
    background-color: #222 !important;
    border-color: #444 !important;
    color: #eee !important;
}
</style>

<script>
function confirmAction() {
    const isComplete = document.getElementById('completeReset').checked;
    const selectedCount = document.querySelectorAll('.category-check:checked').length;
    
    if (!isComplete && selectedCount === 0) {
        alert("Please select at least one module or file category to cleanup.");
        return false;
    }
    
    let message = isComplete 
        ? "CRITICAL WARNING: You have selected COMPLETE SYSTEM RESET.\nThis will permanently delete ALL transactional data and uploaded files.\nAre you absolutely sure?" 
        : "Are you sure you want to delete the selected data categories permanently?\nThis action cannot be undone.";
        
    return confirm(message);
}

// Linked UI behavior
document.getElementById('completeReset').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.category-check');
    checkboxes.forEach(cb => {
        cb.disabled = this.checked;
        if (this.checked) cb.checked = true;
    });
});
</script>

<?php include BASE_PATH . '/templates/footer.php'; ?>
