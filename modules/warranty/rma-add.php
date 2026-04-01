<?php

/**
 * Add New RMA Claim
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$errors = [];
$success = false;

// Handle Form Submission
if (is_post() && isset($_POST['add_rma'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $product_id = (int)$_POST['product_id'];
        $serial_id = !empty($_POST['serial_id']) ? (int)$_POST['serial_id'] : null;
        $problem_description = clean_input($_POST['problem_description']);
        $service_center_id = !empty($_POST['service_center_id']) ? (int)$_POST['service_center_id'] : null;
        $notes = clean_input($_POST['notes']);
        $created_date = date('Y-m-d');

        if (empty($product_id)) $errors[] = "Product is required.";
        if (empty($problem_description)) $errors[] = "Problem description is required.";

        if (empty($errors)) {
            try {
                dbBeginTransaction();

                // Generate RMA Number
                $last_rma = db_query_one("SELECT MAX(id) as last_id FROM rma_requests");
                $rma_number = "RMA-" . str_pad(($last_rma['last_id'] ?? 0) + 1, 6, '0', STR_PAD_LEFT);

                $rma_id = db_insert('rma_requests', [
                    'rma_number' => $rma_number,
                    'customer_id' => $customer_id,
                    'product_id' => $product_id,
                    'serial_id' => $serial_id,
                    'problem_description' => $problem_description,
                    'status' => 'pending',
                    'service_center_id' => $service_center_id,
                    'created_date' => $created_date,
                    'notes' => $notes,
                    'created_by' => get_current_user_id()
                ]);

                // Update Serial Status if applicable
                if ($serial_id) {
                    // Set status to 'rma' and stock_type to 'rma'
                    db_update('product_serials', [
                        'status' => 'rma',
                        'stock_type' => 'rma'
                    ], ['id' => $serial_id]);
                }

                log_activity(get_current_user_id(), 'rma_add', "Created RMA Claim #$rma_number");
                dbCommit();

                redirect_with_message('rma-list.php', "RMA #$rma_number created successfully.", 'success');
            } catch (Exception $e) {
                dbRollback();
                $errors[] = "Error: " . $e->getMessage();
            }
        }
    } else {
        $errors[] = "Invalid CSRF token.";
    }
}

// Fetch all service centers for dropdown
$service_centers = db_select('service_centers', [], '*', 'name ASC');

$page_title = 'Add New RMA Claim';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .bg-success-soft {
        background-color: #e8f5e9;
        color: #155724;
    }

    .bg-danger-soft {
        background-color: #ffebee;
        color: #721c24;
    }

    [data-theme="dark"] .bg-success-soft {
        background-color: rgba(25, 135, 84, 0.15) !important;
        color: #75b798 !important;
    }

    [data-theme="dark"] .bg-danger-soft {
        background-color: rgba(220, 53, 69, 0.15) !important;
        color: #ea868f !important;
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Warranty Claim Details</h6>
                <a href="rma-list.php" class="btn btn-sm btn-secondary">Back to List</a>
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

                <form action="" method="POST" id="rmaForm">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="add_rma" value="1">

                    <!-- Serial Search Section -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="p-3 bg-light border rounded">
                                <label class="form-label font-weight-bold"><i class="fas fa-barcode"></i> Search by Serial / IMEI Number</label>
                                <div class="input-group">
                                    <input type="text" id="serialSearch" class="form-control" placeholder="Type serial number and press enter..." autofocus>
                                    <button class="btn btn-primary" type="button" id="btnSearch">Search</button>
                                </div>
                                <small class="text-muted">Enter the serial number from the sales invoice to auto-fill details.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Inputs for logic -->
                    <input type="hidden" name="customer_id" id="hidden_customer_id">
                    <input type="hidden" name="product_id" id="hidden_product_id">
                    <input type="hidden" name="serial_id" id="hidden_serial_id">

                    <div id="claimDetails" style="display: none;">
                        <div class="row">
                            <!-- Left: Product & Warranty Info -->
                            <div class="col-md-6 border-right">
                                <h5 class="text-info border-bottom pb-2">Product & Warranty</h5>

                                <div class="mb-3">
                                    <label class="form-label">Product Name</label>
                                    <input type="text" id="display_product_name" class="form-control" readonly>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-6">
                                        <label class="form-label">Sale Date</label>
                                        <input type="text" id="display_sale_date" class="form-control" readonly>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Invoice #</label>
                                        <input type="text" id="display_invoice_no" class="form-control" readonly>
                                    </div>
                                </div>

                                <div class="p-3 rounded mb-3" id="warrantyStatusBox">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="font-weight-bold">Warranty Status:</span>
                                        <span id="warrantyBadge" class="badge">Checking...</span>
                                    </div>
                                    <small id="expiryInfo" class="d-block mt-1"></small>
                                </div>
                            </div>

                            <!-- Right: Customer & Service Center -->
                            <div class="col-md-6">
                                <h5 class="text-info border-bottom pb-2">Claim Information</h5>

                                <div class="mb-3">
                                    <label class="form-label">Customer Name</label>
                                    <input type="text" id="display_customer_name" class="form-control" readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Service Center (Optional)</label>
                                    <select name="service_center_id" class="form-control select2">
                                        <option value="">-- Local Store / Internal --</option>
                                        <?php foreach ($service_centers as $sc): ?>
                                            <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="rmaFormFields">
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label font-weight-bold">Problem Description <span class="text-danger">*</span></label>
                                        <textarea name="problem_description" class="form-control" rows="4" required placeholder="Describe the issue reported by the customer..."></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Additional Notes</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="Internal notes..."></textarea>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-success px-5">Submit Claim</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="expiredMessage" class="mt-4 p-4 border rounded bg-light text-center" style="display: none;">
                            <h5 class="text-danger mb-3"><i class="fas fa-exclamation-triangle"></i> Warranty Expired</h5>
                            <p class="text-muted">This product is out of warranty. You cannot create an RMA claim for it.</p>
                            <a href="<?= BASE_URL ?>/modules/paid-service/service-intake.php" class="btn btn-warning mt-2">
                                <i class="fas fa-tools"></i> Create Paid Service Ticket
                            </a>
                        </div>
                    </div>

                    <div id="noResult" class="text-center py-5 border rounded bg-light" style="display: none;">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>No Serial Found</h5>
                        <p class="text-muted">Type a valid serial number above to see product details.</p>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#rmaForm')
        });

        const searchInput = $('#serialSearch');
        const claimDetails = $('#claimDetails');
        const noResult = $('#noResult');

        function performSearch() {
            const serial = searchInput.val().trim();
            if (serial === '') return;

            $('#btnSearch').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.get('../../api/warranty/get-serial-info.php', {
                serial: serial
            }, function(res) {
                $('#btnSearch').prop('disabled', false).text('Search');

                if (res.status) {
                    noResult.hide();
                    claimDetails.fadeIn();

                    // Fill details
                    $('#hidden_customer_id').val(res.data.customer_id);
                    $('#hidden_product_id').val(res.data.product_id);
                    $('#hidden_serial_id').val(res.data.id);

                    $('#display_product_name').val(res.data.product_name + ' (' + res.data.product_code + ')');
                    $('#display_customer_name').val(res.data.customer_name || 'Walk-in Customer');
                    $('#display_sale_date').val(res.data.sale_date);
                    $('#display_invoice_no').val(res.data.invoice_number);

                    // Warranty Badge
                    const badge = $('#warrantyBadge');
                    const box = $('#warrantyStatusBox');
                    if (res.warranty.is_valid) {
                        badge.text('ACTIVE').removeClass('bg-danger').addClass('bg-success');
                        box.removeClass('bg-danger-soft').addClass('bg-success-soft');
                        $('#rmaFormFields').show();
                        $('#expiredMessage').hide();
                    } else {
                        badge.text('EXPIRED').removeClass('bg-success').addClass('bg-danger');
                        box.removeClass('bg-success-soft').addClass('bg-danger-soft');
                        $('#rmaFormFields').hide();
                        $('#expiredMessage').show();

                        // Since it's a paid service, append customer and device details to the url if possible, 
                        // or just let them go to the plain form. The button href is static for now.
                    }
                    $('#expiryInfo').html('Expires on: <strong>' + res.warranty.expiry_date + '</strong> (' + res.warranty.months + ' months total)');

                } else {
                    claimDetails.hide();
                    noResult.fadeIn();
                    alert(res.message);
                }
            }).fail(function() {
                $('#btnSearch').prop('disabled', false).text('Search');
                alert('Server error occurred.');
            });
        }

        $('#btnSearch').click(performSearch);
        searchInput.keypress(function(e) {
            if (e.which == 13) {
                e.preventDefault();
                performSearch();
            }
        });

        // Handle initial state
        noResult.show();
    });
</script>