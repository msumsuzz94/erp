<?php
/**
 * Add New Lead (Market Data)
 * Smart form with auto-complete, GPS, image upload, quick category add
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $errors = [];
        
        $org_name = clean_input($_POST['organization_name']);
        $contact = clean_input($_POST['contact_person']);
        $mobile = clean_input($_POST['mobile']);
        
        if (empty($org_name)) $errors[] = "Organization name is required.";
        if (empty($contact)) $errors[] = "Contact person is required.";
        if (empty($mobile)) $errors[] = "Mobile number is required.";
        
        // Check duplicate mobile
        if (empty($errors)) {
            $existing = db_query_one("SELECT id FROM leads WHERE mobile = ?", [$mobile]);
            if ($existing) {
                $errors[] = "A lead with this mobile number already exists.";
            }
        }
        
        if (empty($errors)) {
            // Handle quick category add
            $category_id = null;
            if (!empty($_POST['new_category_name'])) {
                $new_cat = clean_input($_POST['new_category_name']);
                $existing_cat = db_query_one("SELECT id FROM lead_categories WHERE name = ?", [$new_cat]);
                if ($existing_cat) {
                    $category_id = $existing_cat['id'];
                } else {
                    $category_id = db_insert('lead_categories', [
                        'name' => $new_cat,
                        'description' => '',
                        'status' => 'active'
                    ]);
                }
            } elseif (!empty($_POST['category_id'])) {
                $category_id = (int)$_POST['category_id'];
            }
            
            $data = [
                'category_id' => $category_id,
                'organization_name' => $org_name,
                'contact_person' => $contact,
                'mobile' => $mobile,
                'email' => clean_input($_POST['email'] ?? ''),
                'address' => clean_input($_POST['address'] ?? ''),
                'remarks' => clean_input($_POST['remarks'] ?? ''),
                'collected_by' => get_current_user_id(),
                'gps_latitude' => clean_input($_POST['gps_latitude'] ?? ''),
                'gps_longitude' => clean_input($_POST['gps_longitude'] ?? ''),
                'status' => clean_input($_POST['lead_status'] ?? 'new')
            ];
            
            $id = db_insert('leads', $data);
            if ($id) {
                redirect_with_message('lead-list.php', 'Lead added successfully!', 'success');
            } else {
                $errors[] = "Failed to save lead to database.";
            }
        }
    }
}

$categories = db_query("SELECT * FROM lead_categories WHERE status = 'active' ORDER BY name ASC");

$page_title = 'Add New Lead';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.lead-form .form-label { font-weight: 600; font-size: 13px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
.lead-form .form-control:focus, .lead-form .form-select:focus { border-color: #4e73df; box-shadow: 0 0 0 0.15rem rgba(78,115,223,.25); }
.gps-card { background: linear-gradient(135deg, #667eea33, #764ba233); border: none; }
.quick-cat-btn { cursor: pointer; border: 1px dashed #aaa; border-radius: 6px; padding: 6px 12px; font-size: 12px; transition: all 0.2s; }
.quick-cat-btn:hover { border-color: #4e73df; color: #4e73df; background: rgba(78,115,223,.05); }
.status-option { cursor: pointer; padding: 8px 16px; border: 2px solid #dee2e6; border-radius: 8px; text-align: center; transition: all 0.2s; }
.status-option:hover { border-color: #4e73df; }
.status-option.selected { border-color: #4e73df; background: rgba(78,115,223,.08); }
.status-option input { display: none; }
</style>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <strong><i class="fas fa-exclamation-triangle"></i> Error!</strong>
                <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" accept-charset="UTF-8" class="lead-form" id="leadForm">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <!-- Section 1: Organization Info -->
            <div class="card shadow-sm mb-3">
                <div class="card-header py-3" style="border-left: 4px solid #4e73df;">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-building"></i> Organization Info</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Organization / Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="organization_name" class="form-control form-control-lg" placeholder="Enter organization name" required autofocus>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <div class="input-group">
                                <select name="category_id" id="categorySelect" class="form-select">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-success" id="addCatBtn" title="Quick Add Category">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <input type="text" name="new_category_name" id="newCatInput" class="form-control mt-1" placeholder="New category name..." style="display:none;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Contact Info -->
            <div class="card shadow-sm mb-3">
                <div class="card-header py-3" style="border-left: 4px solid #1cc88a;">
                    <h6 class="m-0 fw-bold text-success"><i class="fas fa-user"></i> Contact Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" name="contact_person" class="form-control" placeholder="Full name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mobile <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" name="mobile" class="form-control" placeholder="01XXXXXXXXX" required pattern="[0-9+]{10,15}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="email@example.com">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Location -->
            <div class="card shadow-sm mb-3">
                <div class="card-header py-3 gps-card">
                    <h6 class="m-0 fw-bold" style="color:#764ba2;"><i class="fas fa-map-marker-alt"></i> Location & Address</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Full address..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">GPS Location</label>
                            <div class="d-grid gap-1">
                                <button type="button" class="btn btn-outline-primary" id="getLocationBtn">
                                    <i class="fas fa-crosshairs"></i> Capture GPS
                                </button>
                                <div class="row g-1">
                                    <div class="col-6">
                                        <input type="text" name="gps_latitude" id="lat" class="form-control form-control-sm" placeholder="Lat" readonly>
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="gps_longitude" id="lng" class="form-control form-control-sm" placeholder="Lng" readonly>
                                    </div>
                                </div>
                                <small id="geoStatus" class="text-muted text-center">Click to capture location</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4: Status & Notes -->
            <div class="card shadow-sm mb-3">
                <div class="card-header py-3" style="border-left: 4px solid #f6c23e;">
                    <h6 class="m-0 fw-bold text-warning"><i class="fas fa-clipboard"></i> Status & Notes</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Lead Status</label>
                            <div class="d-flex flex-wrap gap-2">
                                <label class="status-option selected">
                                    <input type="radio" name="lead_status" value="new" checked>
                                    <i class="fas fa-star text-primary"></i> New
                                </label>
                                <label class="status-option">
                                    <input type="radio" name="lead_status" value="contacted">
                                    <i class="fas fa-phone text-info"></i> Contacted
                                </label>
                                <label class="status-option">
                                    <input type="radio" name="lead_status" value="converted">
                                    <i class="fas fa-check text-success"></i> Converted
                                </label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Remarks / Notes</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Additional details about this lead..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary btn-lg flex-grow-1" id="submitBtn">
                    <i class="fas fa-save"></i> Save Lead
                </button>
                <button type="submit" class="btn btn-success btn-lg" name="save_and_new" value="1">
                    <i class="fas fa-plus"></i> Save & Add Another
                </button>
                <a href="lead-list.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Quick add category toggle
    let showNewCat = false;
    $('#addCatBtn').click(function() {
        showNewCat = !showNewCat;
        if (showNewCat) {
            $('#newCatInput').slideDown().focus();
            $('#categorySelect').prop('disabled', true);
            $(this).html('<i class="fas fa-times"></i>').removeClass('btn-outline-success').addClass('btn-outline-danger');
        } else {
            $('#newCatInput').slideUp().val('');
            $('#categorySelect').prop('disabled', false);
            $(this).html('<i class="fas fa-plus"></i>').removeClass('btn-outline-danger').addClass('btn-outline-success');
        }
    });
    
    // Status option toggle
    $('.status-option').click(function() {
        $('.status-option').removeClass('selected');
        $(this).addClass('selected');
    });
    
    // GPS capture
    $('#getLocationBtn').click(function() {
        const btn = $(this);
        const status = $('#geoStatus');
        
        if (!navigator.geolocation) {
            status.html('<span class="text-danger">Geolocation not supported</span>');
            return;
        }
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Getting...');
        status.html('<span class="text-primary">Acquiring location...</span>');
        
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                $('#lat').val(pos.coords.latitude.toFixed(6));
                $('#lng').val(pos.coords.longitude.toFixed(6));
                status.html('<span class="text-success"><i class="fas fa-check"></i> Location captured!</span>');
                btn.prop('disabled', false).html('<i class="fas fa-crosshairs"></i> Recapture');
            },
            function(err) {
                const msgs = {
                    1: 'Permission denied',
                    2: 'Location unavailable',
                    3: 'Timeout'
                };
                status.html('<span class="text-danger">' + (msgs[err.code] || 'Error') + '</span>');
                btn.prop('disabled', false).html('<i class="fas fa-crosshairs"></i> Retry');
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    });
    
    // Auto-format mobile
    $('input[name="mobile"]').on('input', function() {
        let v = $(this).val().replace(/[^0-9+]/g, '');
        $(this).val(v);
    });
    
    // Auto-capture GPS on page load (if mobile)
    if (/Android|iPhone|iPad/i.test(navigator.userAgent)) {
        setTimeout(() => $('#getLocationBtn').click(), 1000);
    }
});
</script>
