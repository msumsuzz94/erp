<?php
/**
 * Staff Edit Page - Comprehensive Form
 * Edit existing staff member details matching staff-add.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$errors = [];
$success_message = '';

// Get staff ID from URL
$staff_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get staff data
$staff = db_select_one('staff', ['id' => $staff_id]);

if (!$staff) {
    header('Location: staff-list.php');
    exit;
}

// Handle form submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $staff_data = [
            // Personal
            'name' => clean_input($_POST['name']),
            'father_name' => clean_input($_POST['father_name']),
            'mother_name' => clean_input($_POST['mother_name']),
            'email' => clean_input($_POST['email']),
            'phone' => clean_input($_POST['phone']),
            'address' => clean_input($_POST['address']),
            'date_of_birth' => !empty($_POST['date_of_birth']) ? clean_input($_POST['date_of_birth']) : null,
            'gender' => clean_input($_POST['gender']),
            'marital_status' => clean_input($_POST['marital_status']),
            'blood_group' => clean_input($_POST['blood_group']),
            'nid_number' => clean_input($_POST['nid_number']),
            
            // Employment
            'employee_id' => clean_input($_POST['employee_id']),
            'designation' => clean_input($_POST['designation']),
            'department_id' => !empty($_POST['department_id']) ? (int)$_POST['department_id'] : NULL,
            'role_id' => !empty($_POST['role_id']) ? (int)$_POST['role_id'] : NULL,
            'joining_date' => !empty($_POST['joining_date']) ? clean_input($_POST['joining_date']) : null,
            'job_type' => clean_input($_POST['job_type']),
            'shift_id' => !empty($_POST['shift_id']) ? (int)$_POST['shift_id'] : NULL,
            'contract_end_date' => !empty($_POST['contract_end_date']) ? clean_input($_POST['contract_end_date']) : null,
            'status' => clean_input($_POST['status']),
            
            // Financial
            'salary' => (float)($_POST['salary'] ?? 0),
            'bank_name' => clean_input($_POST['bank_name']),
            'bank_branch' => clean_input($_POST['bank_branch']),
            'bank_account_name' => clean_input($_POST['bank_account_name']),
            'bank_account_number' => clean_input($_POST['bank_account_number']),
            
            // Emergency
            'emergency_contact_name' => clean_input($_POST['emergency_contact_name']),
            'emergency_contact_phone' => clean_input($_POST['emergency_contact_phone']),
            'emergency_contact_relation' => clean_input($_POST['emergency_contact_relation']),
        ];

        // Validation
        if (empty($staff_data['name'])) $errors[] = 'Full Name is required';
        if (empty($staff_data['phone'])) $errors[] = 'Phone Number is required';
        if (empty($staff_data['designation'])) $errors[] = 'Designation is required';
        if (empty($staff_data['joining_date'])) $errors[] = 'Joining Date is required';
        
        // Employee ID uniqueness check (excluding current staff member)
        if (!empty($staff_data['employee_id'])) {
            $existing = db_select_one('staff', ['employee_id' => $staff_data['employee_id']]);
            if ($existing && $existing['id'] != $staff_id) {
                $errors[] = "Employee ID '{$staff_data['employee_id']}' is already in use by another staff member.";
            }
        }
        
        // Handle Photo Upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/staff/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $new_filename = 'staff_' . time() . '_' . uniqid() . '.' . $file_ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_filename)) {
                    // Delete old photo
                    if (!empty($staff['photo']) && file_exists(__DIR__ . '/../../' . $staff['photo'])) {
                        unlink(__DIR__ . '/../../' . $staff['photo']);
                    }
                    $staff_data['photo'] = 'uploads/staff/' . $new_filename;
                }
            } else {
                $errors[] = 'Invalid photo format (JPG, PNG, GIF only)';
            }
        }

        if (empty($errors)) {
            $updated = db_update('staff', $staff_data, ['id' => $staff_id]);
            if ($updated !== false) {
                // Handle Documents
                if (isset($_FILES['documents'])) {
                    $doc_dir = __DIR__ . '/../../uploads/documents/';
                    if (!is_dir($doc_dir)) mkdir($doc_dir, 0755, true);
                    
                    foreach ($_FILES['documents']['name'] as $key => $name) {
                        if ($_FILES['documents']['error'][$key] == UPLOAD_ERR_OK) {
                            $tmp_name = $_FILES['documents']['tmp_name'][$key];
                            $doc_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                            $doc_filename = 'doc_' . $staff_id . '_' . uniqid() . '.' . $doc_ext;
                            
                            if (move_uploaded_file($tmp_name, $doc_dir . $doc_filename)) {
                                db_insert('staff_documents', [
                                    'staff_id' => $staff_id,
                                    'document_type' => 'Other',
                                    'file_name' => $name,
                                    'file_path' => 'uploads/documents/' . $doc_filename
                                ]);
                            }
                        }
                    }
                }
                
                log_activity(get_current_user_id(), 'staff_update', "Updated staff: {$staff_data['name']}");
                redirect_with_message('staff-list.php', 'Staff member updated successfully!', 'success');
            } else {
                $errors[] = "Database Error: Failed to update staff member.";
            }
        }
    } else {
        $errors[] = "Invalid CSRF Token";
    }
}

// Fetch lists
$roles = db_query("SELECT * FROM staff_roles ORDER BY role_name ASC");
$departments = db_query("SELECT * FROM staff_departments ORDER BY name ASC");
if (!$departments) $departments = [];
$shifts = db_query("SELECT * FROM shifts WHERE is_active = 1 ORDER BY start_time ASC");
if (!$shifts) $shifts = [];

// Get existing documents
$documents = db_query("SELECT * FROM staff_documents WHERE staff_id = ? ORDER BY id DESC", [$staff_id]);
if (!$documents) $documents = [];

$page_title = 'Edit Staff - ' . htmlspecialchars($staff['name']);
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-edit"></i> Edit Staff Member</h1>
            <div>
                <a href="staff-view.php?id=<?= $staff_id ?>" class="btn btn-info shadow-sm"><i class="fas fa-eye"></i> View Details</a>
                <a href="staff-list.php" class="btn btn-secondary shadow-sm"><i class="fas fa-arrow-left"></i> Back to List</a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i> <?= $success_message ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 pl-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Staff Information Form</h6>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="staffForm">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <!-- Form Tabs -->
                    <ul class="nav nav-pills nav-justified mb-4" id="staffTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="personal-tab" data-toggle="pill" href="#personal" role="tab" aria-controls="personal" aria-selected="true">
                                <i class="fas fa-user mr-2"></i>Personal Info
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="employment-tab" data-toggle="pill" href="#employment" role="tab" aria-controls="employment" aria-selected="false">
                                <i class="fas fa-briefcase mr-2"></i>Employment
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="financial-tab" data-toggle="pill" href="#financial" role="tab" aria-controls="financial" aria-selected="false">
                                <i class="fas fa-money-check-alt mr-2"></i>Financial & Bank
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="docs-tab" data-toggle="pill" href="#docs" role="tab" aria-controls="docs" aria-selected="false">
                                <i class="fas fa-file-upload mr-2"></i>Documents
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="staffTabsContent">
                        
                        <!-- Personal Information -->
                        <div class="tab-pane fade show active" id="personal" role="tabpanel">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label>Full Name <span class="text-danger">*</span></label>
                                            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($staff['name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Father's Name</label>
                                            <input type="text" name="father_name" class="form-control" value="<?= htmlspecialchars($staff['father_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Mother's Name</label>
                                            <input type="text" name="mother_name" class="form-control" value="<?= htmlspecialchars($staff['mother_name'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label>Date of Birth</label>
                                            <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($staff['date_of_birth'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Gender</label>
                                            <select name="gender" class="form-control">
                                                <option value="">Select Gender</option>
                                                <option value="male" <?= ($staff['gender'] ?? '') == 'male' ? 'selected' : '' ?>>Male</option>
                                                <option value="female" <?= ($staff['gender'] ?? '') == 'female' ? 'selected' : '' ?>>Female</option>
                                                <option value="other" <?= ($staff['gender'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Marital Status</label>
                                            <select name="marital_status" class="form-control">
                                                <option value="">Select Status</option>
                                                <option value="single" <?= ($staff['marital_status'] ?? '') == 'single' ? 'selected' : '' ?>>Single</option>
                                                <option value="married" <?= ($staff['marital_status'] ?? '') == 'married' ? 'selected' : '' ?>>Married</option>
                                                <option value="divorced" <?= ($staff['marital_status'] ?? '') == 'divorced' ? 'selected' : '' ?>>Divorced</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label>Blood Group</label>
                                            <select name="blood_group" class="form-control">
                                                <option value="">Select Group</option>
                                                <?php foreach(['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                                                    <option value="<?= $bg ?>" <?= ($staff['blood_group'] ?? '') == $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>NID / Passport No</label>
                                            <input type="text" name="nid_number" class="form-control" value="<?= htmlspecialchars($staff['nid_number'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Phone Number <span class="text-danger">*</span></label>
                                            <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($staff['phone'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label>Email Address</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($staff['email'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label>Present Address</label>
                                            <textarea name="address" class="form-control" rows="1"><?= htmlspecialchars($staff['address'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <label class="d-block text-left">Profile Photo</label>
                                    <div class="border rounded p-2 mb-2 bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                        <?php if (!empty($staff['photo'])): ?>
                                            <img id="photoPreview" src="<?= BASE_URL ?>/<?= htmlspecialchars($staff['photo']) ?>" class="img-fluid" style="max-height: 100%;">
                                            <div id="photoPlaceholder" class="text-muted" style="display: none;">
                                                <i class="fas fa-camera fa-3x mb-2"></i><br>No Image
                                            </div>
                                        <?php else: ?>
                                            <img id="photoPreview" src="../../assets/img/default-user.png" class="img-fluid" style="max-height: 100%; display: none;"> 
                                            <div id="photoPlaceholder" class="text-muted">
                                                <i class="fas fa-camera fa-3x mb-2"></i><br>No Image
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="custom-file text-left">
                                        <input type="file" class="custom-file-input" id="photo" name="photo" accept="image/*">
                                        <label class="custom-file-label" for="photo">Choose file...</label>
                                    </div>
                                    <small class="form-text text-muted">Leave empty to keep current photo</small>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Details -->
                        <div class="tab-pane fade" id="employment" role="tabpanel">
                             <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Employee ID</label>
                                    <input type="text" name="employee_id" class="form-control" value="<?= htmlspecialchars($staff['employee_id'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Role</label>
                                    <select name="role_id" class="form-control">
                                        <option value="">Select Role (Optional)</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= $role['id'] ?>" <?= ($staff['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>><?= $role['role_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Department</label>
                                    <select name="department_id" class="form-control">
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['id'] ?>" <?= ($staff['department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>><?= $dept['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Designation <span class="text-danger">*</span></label>
                                    <input type="text" name="designation" class="form-control" required value="<?= htmlspecialchars($staff['designation'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Joining Date <span class="text-danger">*</span></label>
                                    <input type="date" name="joining_date" class="form-control" required value="<?= htmlspecialchars($staff['joining_date'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Job Type</label>
                                    <select name="job_type" class="form-control">
                                        <option value="permanent" <?= ($staff['job_type'] ?? '') == 'permanent' ? 'selected' : '' ?>>Permanent</option>
                                        <option value="contract" <?= ($staff['job_type'] ?? '') == 'contract' ? 'selected' : '' ?>>Contractual</option>
                                        <option value="probation" <?= ($staff['job_type'] ?? '') == 'probation' ? 'selected' : '' ?>>Probation</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Work Shift</label>
                                    <select name="shift_id" class="form-control">
                                        <option value="">Select Shift</option>
                                        <?php foreach ($shifts as $shift): ?>
                                            <option value="<?= $shift['id'] ?>" <?= ($staff['shift_id'] ?? '') == $shift['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($shift['name']) ?> (<?= date('h:i A', strtotime($shift['start_time'])) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Contract End Date</label>
                                    <input type="date" name="contract_end_date" class="form-control" value="<?= htmlspecialchars($staff['contract_end_date'] ?? '') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="active" <?= ($staff['status'] ?? '') == 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= ($staff['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="alert alert-info mt-2">
                                <i class="fas fa-info-circle"></i> <strong>Info:</strong>
                                <ul class="mb-0 mt-1">
                                    <li>Created: <?= date('d M Y, h:i A', strtotime($staff['created_at'])) ?></li>
                                    <?php if (!empty($staff['updated_at'])): ?>
                                        <li>Last Updated: <?= date('d M Y, h:i A', strtotime($staff['updated_at'])) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Financial & Banking -->
                        <div class="tab-pane fade" id="financial" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="text-primary font-weight-bold">Salary Information</label>
                                    <div class="form-group">
                                        <label>Basic Salary</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?? '$' ?></span>
                                            </div>
                                            <input type="number" name="salary" class="form-control" step="0.01" value="<?= htmlspecialchars($staff['salary'] ?? '0.00') ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-primary font-weight-bold">Bank Account Details</label>
                                    <div class="form-group mb-2">
                                        <label>Bank Name</label>
                                        <input type="text" name="bank_name" class="form-control" placeholder="e.g. Dutch Bangla Bank" value="<?= htmlspecialchars($staff['bank_name'] ?? '') ?>">
                                    </div>
                                    <div class="form-group mb-2">
                                        <label>Branch Name</label>
                                        <input type="text" name="bank_branch" class="form-control" value="<?= htmlspecialchars($staff['bank_branch'] ?? '') ?>">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 form-group">
                                            <label>Account Name</label>
                                            <input type="text" name="bank_account_name" class="form-control" value="<?= htmlspecialchars($staff['bank_account_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6 form-group">
                                            <label>Account Number</label>
                                            <input type="text" name="bank_account_number" class="form-control" value="<?= htmlspecialchars($staff['bank_account_number'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents & Emergency -->
                        <div class="tab-pane fade" id="docs" role="tabpanel">
                            <h6 class="text-primary">Emergency Contact</h6>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label>Contact Name</label>
                                    <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($staff['emergency_contact_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label>Relation</label>
                                    <input type="text" name="emergency_contact_relation" class="form-control" placeholder="e.g. Spouse, Father" value="<?= htmlspecialchars($staff['emergency_contact_relation'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label>Phone Number</label>
                                    <input type="text" name="emergency_contact_phone" class="form-control" value="<?= htmlspecialchars($staff['emergency_contact_phone'] ?? '') ?>">
                                </div>
                            </div>

                            <?php if (!empty($documents)): ?>
                                <h6 class="text-primary">Existing Documents</h6>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>File Name</th>
                                                <th>Type</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($documents as $doc): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($doc['file_name']) ?></td>
                                                    <td><?= htmlspecialchars($doc['document_type']) ?></td>
                                                    <td>
                                                        <a href="<?= BASE_URL ?>/<?= $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-info">
                                                            <i class="fas fa-download"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <h6 class="text-primary">Upload New Documents</h6>
                            <div class="form-group">
                                <label>Upload Documents (Resume, NID, Certificates)</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="documents[]" id="documents" multiple>
                                    <label class="custom-file-label" for="documents">Choose files...</label>
                                </div>
                                <small class="text-muted">You can select multiple files</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-white text-right">
                <button type="button" class="btn btn-secondary mr-2" id="prevBtn" style="display:none;" onclick="changeTab('prev')">
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeTab('next')">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" form="staffForm" class="btn btn-success" id="submitBtn" style="display:none;">
                    <i class="fas fa-save"></i> Update Staff Member
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Photo Preview
    $('#photo').on('change', function() {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#photoPreview').attr('src', e.target.result).show();
                $('#photoPlaceholder').hide();
            }
            reader.readAsDataURL(file);
            $('.custom-file-label[for="photo"]').text(file.name);
        }
    });

    // Multiple File Label
    $('#documents').on('change', function() {
        var files = this.files;
        var label = files.length > 1 ? files.length + ' files selected' : (files.length == 1 ? files[0].name : 'Choose files...');
        $('.custom-file-label[for="documents"]').text(label);
    });
});

// Tab Navigation
var tabs = ['personal', 'employment', 'financial', 'docs'];
var currentTab = 0;

function changeTab(direction) {
    if (direction === 'next') {
        if (currentTab < tabs.length - 1) {
            currentTab++;
        }
    } else {
        if (currentTab > 0) {
            currentTab--;
        }
    }
    
    // Show tab
    $('#' + tabs[currentTab] + '-tab').tab('show');
    
    // Update buttons
    $('#prevBtn').toggle(currentTab > 0);
    if (currentTab === tabs.length - 1) {
        $('#nextBtn').hide();
        $('#submitBtn').show();
    } else {
        $('#nextBtn').show();
        $('#submitBtn').hide();
    }
}

// Add click listener to tabs to update buttons state if user clicks directly
$('.nav-link').on('shown.bs.tab', function(e) {
    var target = $(e.target).attr("href").substring(1); // #personal -> personal
    currentTab = tabs.indexOf(target);
    
    $('#prevBtn').toggle(currentTab > 0);
    if (currentTab === tabs.length - 1) {
        $('#nextBtn').hide();
        $('#submitBtn').show();
    } else {
        $('#nextBtn').show();
        $('#submitBtn').hide();
    }
});
</script>
