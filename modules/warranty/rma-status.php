<?php
/**
 * RMA Status Check Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$rma_details = null;
$error_message = null;
$search_rma = '';

if (is_get() && isset($_GET['rma_number'])) {
    $search_rma = clean_input($_GET['rma_number']);
    
    if (!empty($search_rma)) {
        $sql = "SELECT r.*, c.name as customer_name, c.phone as customer_phone, p.name as product_name, p.code as product_code, s.name as service_center_name
                FROM rma_requests r
                LEFT JOIN customers c ON r.customer_id = c.id
                LEFT JOIN products p ON r.product_id = p.id
                LEFT JOIN service_centers s ON r.service_center_id = s.id
                WHERE r.rma_number = ?";
        
        $rma_details = db_query_one($sql, [$search_rma]);
        
        if (!$rma_details) {
            $error_message = "RMA not found with number: " . htmlspecialchars($search_rma);
        }
    }
}

$page_title = 'Check RMA Status';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <!-- Search Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Track RMA Status</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="rma_number" class="form-control form-control-lg" placeholder="Enter RMA Number (e.g., RMA-000001)" value="<?= htmlspecialchars($search_rma) ?>" required>
                        <button class="btn btn-primary btn-lg" type="submit">
                            <i class="fas fa-search"></i> Check Status
                        </button>
                    </div>
                </form>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($rma_details): ?>
            <!-- Result Card -->
            <div class="card shadow mb-4 border-left-<?= get_status_color($rma_details['status']) ?>">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">RMA Details: <?= htmlspecialchars($rma_details['rma_number']) ?></h6>
                    <span class="badge bg-<?= get_status_color($rma_details['status']) ?> fs-6">
                        <?= ucfirst($rma_details['status']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Date Created:</strong><br>
                            <?= format_date($rma_details['created_date']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Last Updated:</strong><br>
                            <?= format_datetime($rma_details['updated_at'] ?? $rma_details['created_at']) ?>
                        </div>
                    </div>

                    <hr>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="font-weight-bold">Product Information</h6>
                            <p class="mb-1">Name: <?= htmlspecialchars($rma_details['product_name']) ?></p>
                            <p class="mb-1">Code: <?= htmlspecialchars($rma_details['product_code'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="font-weight-bold">Customer Information</h6>
                            <p class="mb-1">Name: <?= htmlspecialchars($rma_details['customer_name'] ?? 'N/A') ?></p>
                            <p class="mb-1">Phone: <?= htmlspecialchars($rma_details['customer_phone'] ?? 'N/A') ?></p>
                        </div>
                    </div>

                    <hr>

                    <div class="row mb-3">
                        <div class="col-12">
                            <h6 class="font-weight-bold">Problem Description</h6>
                            <p class="bg-light p-3 rounded"><?= nl2br(htmlspecialchars($rma_details['problem_description'])) ?></p>
                        </div>
                    </div>

                    <?php if ($rma_details['service_center_name']): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-tools"></i> Currently at: <strong><?= htmlspecialchars($rma_details['service_center_name']) ?></strong>
                    </div>
                    <?php endif; ?>

                    <!-- Timeline / Progress -->
                    <div class="mt-4">
                        <h6 class="font-weight-bold mb-3">Tracking History</h6>
                        <div class="position-relative">
                            <!-- Simple timeline visualization -->
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center <?= $rma_details['created_date'] ? 'list-group-item-success' : '' ?>">
                                    Request Received
                                    <span class="badge bg-white text-dark"><?= format_date($rma_details['created_date']) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center <?= $rma_details['sent_date'] ? 'list-group-item-success' : '' ?>">
                                    Sent to Service Center
                                    <?php if ($rma_details['sent_date']): ?>
                                        <span class="badge bg-white text-dark"><?= format_date($rma_details['sent_date']) ?></span>
                                    <?php endif; ?>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center <?= $rma_details['solved_date'] ? 'list-group-item-success' : '' ?>">
                                    Problem Solved
                                    <?php if ($rma_details['solved_date']): ?>
                                        <span class="badge bg-white text-dark"><?= format_date($rma_details['solved_date']) ?></span>
                                    <?php endif; ?>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center <?= $rma_details['delivered_date'] ? 'list-group-item-success' : '' ?>">
                                    Delivered to Customer
                                    <?php if ($rma_details['delivered_date']): ?>
                                        <span class="badge bg-white text-dark"><?= format_date($rma_details['delivered_date']) ?></span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <a href="rma-view.php?id=<?= $rma_details['id'] ?>" class="btn btn-info"><i class="fas fa-eye"></i> View Full Details</a>
                    <?php if (has_permission('warranty.edit')): ?>
                        <a href="rma-edit.php?id=<?= $rma_details['id'] ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Update Status</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Helper to map status to colors
function get_status_color($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'sent': return 'info';
        case 'solved': return 'success';
        case 'delivered': return 'primary';
        default: return 'secondary';
    }
}

include __DIR__ . '/../../templates/footer.php'; 
?>
