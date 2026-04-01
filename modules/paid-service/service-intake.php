<?php

/**
 * Service Intake - Create New Service Ticket
 * Paid Service Module
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$errors = [];
$success = false;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $customer_name = clean_input($_POST['customer_name']);
        $customer_phone = clean_input($_POST['customer_phone']);
        $device_type = clean_input($_POST['device_type']);
        $brand = clean_input($_POST['brand']);
        $model = clean_input($_POST['model']);
        $serial_number = clean_input($_POST['serial_number']);
        $warranty_status = clean_input($_POST['warranty_status']);
        $problem_description = clean_input($_POST['problem_description']);
        $physical_condition = clean_input($_POST['physical_condition']);
        $estimated_delivery_date = !empty($_POST['estimated_delivery_date']) ? $_POST['estimated_delivery_date'] : null;

        // Validation
        if (empty($customer_name)) $errors[] = "Customer name is required.";
        if (empty($customer_phone)) $errors[] = "Customer phone is required.";
        if (empty($device_type)) $errors[] = "Device type is required.";
        if (empty($problem_description)) $errors[] = "Problem description is required.";

        if (empty($errors)) {
            try {
                db_begin_transaction();

                // Generate ticket number: SRV-XXXX
                $last = db_query_one("SELECT MAX(id) as last_id FROM service_tickets");
                $next_id = ($last['last_id'] ?? 0) + 1;
                $ticket_number = 'SRV-' . str_pad($next_id, 4, '0', STR_PAD_LEFT);

                $ticket_id = db_insert('service_tickets', [
                    'ticket_number' => $ticket_number,
                    'customer_name' => $customer_name,
                    'customer_phone' => $customer_phone,
                    'device_type' => $device_type,
                    'brand' => $brand,
                    'model' => $model,
                    'serial_number' => $serial_number,
                    'warranty_status' => $warranty_status,
                    'problem_description' => $problem_description,
                    'physical_condition' => $physical_condition,
                    'estimated_delivery_date' => $estimated_delivery_date,
                    'status' => 'Pending',
                    'created_by' => get_current_user_id()
                ]);

                // Log initial status
                db_insert('service_status_log', [
                    'ticket_id' => $ticket_id,
                    'old_status' => null,
                    'new_status' => 'Pending',
                    'notes' => 'Service ticket created',
                    'changed_by' => get_current_user_id()
                ]);

                log_activity(get_current_user_id(), 'service_intake', "Created service ticket #$ticket_number");
                db_commit();

                // Redirect to receipt
                header("Location: service-receipt.php?id=$ticket_id");
                exit;
            } catch (Exception $e) {
                db_rollback();
                $errors[] = "Error: " . $e->getMessage();
            }
        }
    } else {
        $errors[] = "Invalid CSRF token.";
    }
}

$page_title = 'Service Intake';

// Get distinct device types for dropdown
$default_devices = ['Laptop', 'Desktop PC', 'Printer', 'Monitor', 'UPS', 'Router', 'Other'];
$existing_devices = db_query("SELECT DISTINCT device_type FROM service_tickets WHERE device_type IS NOT NULL AND device_type != ''");
$db_devices = array_column($existing_devices, 'device_type');
$all_devices = array_unique(array_merge($default_devices, $db_devices));
sort($all_devices);

include __DIR__ . '/../../templates/header.php';
?>

<style>
    .intake-header {
        background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .intake-header h2 {
        margin: 0;
        font-weight: bold;
    }

    .intake-header small {
        opacity: 0.85;
    }

    .form-section {
        margin-bottom: 25px;
    }

    .form-section h5 {
        color: #14b8a6;
        border-bottom: 2px solid #14b8a6;
        padding-bottom: 8px;
        margin-bottom: 15px;
    }

    [data-theme="dark"] .form-section h5 {
        color: #2dd4bf;
        border-color: #2dd4bf;
    }
</style>

<div class="intake-header shadow">
    <h2><i class="fas fa-laptop-medical"></i> Service Intake</h2>
    <small>Register a new service job / repair ticket</small>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">New Service Ticket</h6>
                <a href="service-list.php" class="btn btn-sm btn-secondary"><i class="fas fa-list"></i> Service List</a>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" id="intakeForm">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="create_ticket" value="1">

                    <!-- Customer Info -->
                    <div class="form-section">
                        <h5><i class="fas fa-user"></i> Customer Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" class="form-control" required
                                    value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>"
                                    placeholder="Enter customer name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                <input type="text" name="customer_phone" class="form-control" required
                                    value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>"
                                    placeholder="01XXXXXXXXX">
                            </div>
                        </div>
                    </div>

                    <!-- Device Info -->
                    <div class="form-section">
                        <h5><i class="fas fa-laptop"></i> Device Information</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Device Type <span class="text-danger">*</span></label>
                                <select name="device_type" id="device_type" class="form-control" required>
                                    <option value="">-- Select or Type New --</option>
                                    <?php foreach ($all_devices as $dev): ?>
                                        <option value="<?= htmlspecialchars($dev) ?>" <?= (isset($_POST['device_type']) && $_POST['device_type'] == $dev) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dev) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Type and press enter to add a new device type</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" class="form-control"
                                    value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>"
                                    placeholder="e.g. HP, Dell, Lenovo">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" name="model" class="form-control"
                                    value="<?= htmlspecialchars($_POST['model'] ?? '') ?>"
                                    placeholder="e.g. ProBook 450 G8">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Serial / MAC / IMEI Number</label>
                                <input type="text" name="serial_number" class="form-control"
                                    value="<?= htmlspecialchars($_POST['serial_number'] ?? '') ?>"
                                    placeholder="Device serial or identifier">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warranty Status</label>
                                <select name="warranty_status" class="form-control">
                                    <option value="Out of Warranty">Out of Warranty</option>
                                    <option value="External Product">External Product</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Problem & Condition -->
                    <div class="form-section">
                        <h5><i class="fas fa-exclamation-circle"></i> Problem & Condition</h5>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Problem Description <span class="text-danger">*</span></label>
                                <textarea name="problem_description" class="form-control" rows="3" required
                                    placeholder="Describe the problem customer is facing..."><?= htmlspecialchars($_POST['problem_description'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Physical Condition</label>
                                <textarea name="physical_condition" class="form-control" rows="2"
                                    placeholder="Note any scratches, dents, broken parts etc. (for future reference)"><?= htmlspecialchars($_POST['physical_condition'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery -->
                    <div class="form-section">
                        <h5><i class="fas fa-calendar-alt"></i> Delivery Estimate</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estimated Delivery Date</label>
                                <input type="date" name="estimated_delivery_date" class="form-control"
                                    value="<?= htmlspecialchars($_POST['estimated_delivery_date'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="service-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Cancel</a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-check-circle"></i> Create Service Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#device_type').select2({
            theme: 'bootstrap-5',
            tags: true,
            placeholder: "-- Select or Type New --",
            allowClear: true,
            width: '100%'
        });
    });
</script>