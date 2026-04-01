<?php

/**
 * Unified Biometric Registration
 * Manage Face, Fingerprint, and Card for all staff
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('hr.face_register');

// Get all active staff and their biometric status
$query = "SELECT s.id, s.name, s.designation, s.employee_id, s.card_id, 
    (SELECT face_photo FROM staff_biometrics WHERE staff_id = s.id AND biometric_type = 'face' AND is_active = 1 LIMIT 1) as face_photo,
    (SELECT id FROM staff_biometrics WHERE staff_id = s.id AND biometric_type = 'face' AND is_active = 1 LIMIT 1) as has_face,
    (SELECT id FROM staff_biometrics WHERE staff_id = s.id AND biometric_type = 'webauthn' AND is_active = 1 LIMIT 1) as has_finger
    FROM staff s 
    WHERE s.status = 'active' 
    ORDER BY s.name ASC";

$staff_list = db_query($query);

// For the "Register New" dropdown (get all active staff)
$all_staff = db_select('staff', ['status' => 'active'], '*', 'name ASC');

$page_title = 'Biometric Management';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    #regVideoContainer {
        position: relative;
        max-width: 400px;
        margin: 0 auto;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
    }

    #regVideoContainer video {
        width: 100%;
        display: block;
    }

    .staff-photo {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    .status-badge {
        font-size: 0.8rem;
        padding: 0.3rem 0.6rem;
    }
</style>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users-cog"></i> Staff Biometric Management</h6>
        <button class="btn btn-sm btn-primary" onclick="openRegisterNew()"><i class="fas fa-plus"></i> Register New</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover datatable">
                <thead>
                    <tr>
                        <th>Staff Name</th>
                        <th>Employee ID</th>
                        <th>Face</th>
                        <th>Fingerprint</th>
                        <th>Card ID</th>
                        <th width="150">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_list ?: [] as $s): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if ($s['face_photo'] && file_exists(BASE_PATH . '/' . $s['face_photo'])): ?>
                                        <img src="<?= BASE_URL . '/' . $s['face_photo'] ?>" class="staff-photo me-2" alt="">
                                    <?php else: ?>
                                        <div class="staff-photo me-2 bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-user text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($s['name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($s['designation'] ?? '') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($s['employee_id'] ?? '-') ?></td>
                            <td class="text-center">
                                <?= $s['has_face'] ? '<span class="status-badge badge bg-success"><i class="fas fa-check-circle"></i> Enrolled</span>' : '<span class="status-badge badge bg-light text-muted">Missing</span>' ?>
                            </td>
                            <td class="text-center">
                                <?= $s['has_finger'] ? '<span class="status-badge badge bg-primary"><i class="fas fa-fingerprint"></i> Registered</span>' : '<span class="status-badge badge bg-light text-muted">Missing</span>' ?>
                            </td>
                            <td>
                                <code class="text-dark"><?= htmlspecialchars($s['card_id'] ?: '-') ?></code>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary w-100" onclick='openBiometricModal(<?= json_encode($s) ?>)'>
                                    <i class="fas fa-edit"></i> Edit Biometric
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Unified Registration Modal -->
<div class="modal fade" id="biometricModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="fas fa-id-card"></i> Manage Biometrics: <span id="modalStaffName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="stopRegCamera()"></button>
            </div>
            <div class="modal-body">
                <div id="staffSelectContainer" class="mb-4" style="display:none;">
                    <label class="form-label font-weight-bold">Select Staff to Register <span class="text-danger">*</span></label>
                    <select id="regStaffSelector" class="form-select select2" style="width:100%" onchange="onStaffSelectChange()">
                        <option value="">-- Search Staff --</option>
                        <?php foreach ($all_staff ?: [] as $as): ?>
                            <option value='<?= json_encode($as) ?>'><?= htmlspecialchars($as['name']) ?> (<?= htmlspecialchars($as['employee_id'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="hidden" id="modalStaffId">

                <div class="row">
                    <!-- Face Registration -->
                    <div class="col-md-6 border-end">
                        <div class="text-center mb-3">
                            <h6 class="fw-bold text-info"><i class="fas fa-camera"></i> Face ID</h6>
                        </div>
                        <div id="regVideoContainer" class="mb-3">
                            <video id="regVideo" autoplay muted playsinline></video>
                        </div>
                        <div class="text-center">
                            <button class="btn btn-sm btn-info mb-2" onclick="startRegCamera()"><i class="fas fa-video"></i> Start Camera</button>
                            <button class="btn btn-success d-block w-100" id="btnCapture" onclick="captureAndRegister()" disabled>
                                <i class="fas fa-camera"></i> Capture & Save Face
                            </button>
                            <small class="text-muted" id="faceStatus">Camera required for Face ID</small>
                        </div>
                    </div>

                    <!-- Fingerprint & Card Section -->
                    <div class="col-md-6">
                        <!-- Fingerprint -->
                        <div class="mb-4">
                            <div class="text-center mb-3">
                                <h6 class="fw-bold text-primary"><i class="fas fa-fingerprint"></i> Fingerprint (WebAuthn)</h6>
                            </div>
                            <div class="text-center p-3 border rounded bg-light">
                                <p class="small text-muted mb-3">ডিভাইসের বায়োমেট্রিক কি ব্যবহার করে এই স্টাফের জন্য সিকিউর ফিঙ্গারপ্রিন্ট সেট করুন।</p>
                                <button class="btn btn-primary w-100" id="btnFingerprint" onclick="doFingerprintRegister()">
                                    <i class="fas fa-fingerprint me-2"></i> Register Fingerprint
                                </button>
                                <div id="fingerprintStatus" class="mt-2 small"></div>
                            </div>
                        </div>

                        <!-- Card ID -->
                        <div>
                            <div class="text-center mb-3">
                                <h6 class="fw-bold text-warning"><i class="fas fa-id-card"></i> ID Card / RFID</h6>
                            </div>
                            <div class="p-3 border rounded bg-light">
                                <label class="form-label small">Card Number / RFID ID</label>
                                <div class="input-group">
                                    <input type="text" id="regCardId" class="form-control" placeholder="Scan or enter card ID">
                                    <button class="btn btn-warning" onclick="saveCardId()">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Enter manual ID or scan via RFID reader</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="stopRegCamera()">Close</button>
            </div>
        </div>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
    let regModelsLoaded = false;
    let regStream = null;
    let activeStaff = null;

    function openBiometricModal(staff) {
        activeStaff = staff;
        document.getElementById('modalStaffId').value = staff.id;
        document.getElementById('modalStaffName').innerText = staff.name;
        document.getElementById('regCardId').value = staff.card_id || '';
        document.getElementById('staffSelectContainer').style.display = 'none';

        // Update indicators
        document.getElementById('faceStatus').innerHTML = (staff.has_face || staff.face_template) ?
            '<span class="text-success"><i class="fas fa-check"></i> Face already enrolled</span>' :
            'Camera required for Face ID';

        document.getElementById('fingerprintStatus').innerHTML = (staff.has_finger) ?
            '<span class="text-primary font-weight-bold"><i class="fas fa-check"></i> Fingerprint Registered</span>' :
            '<span class="text-muted">No fingerprint registered</span>';

        const modalElement = document.getElementById('biometricModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        modal.show();
    }

    function openRegisterNew() {
        document.getElementById('modalStaffName').innerText = 'New Registration';
        document.getElementById('staffSelectContainer').style.display = 'block';
        document.getElementById('modalStaffId').value = '';
        document.getElementById('regCardId').value = '';
        document.getElementById('faceStatus').innerHTML = 'Select a staff member first';
        document.getElementById('fingerprintStatus').innerHTML = '';

        const modalElement = document.getElementById('biometricModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        modal.show();
    }

    function onStaffSelectChange() {
        const val = document.getElementById('regStaffSelector').value;
        if (!val) return;
        const staff = JSON.parse(val);

        // We need to fetch full biometric status for this staff potentially, 
        // but for now let's just use the basic info and let the user override
        activeStaff = staff;
        document.getElementById('modalStaffId').value = staff.id;
        document.getElementById('modalStaffName').innerText = staff.name;
        document.getElementById('regCardId').value = staff.card_id || '';
        document.getElementById('faceStatus').innerHTML = 'Ready for new Face capture';
    }

    async function startRegCamera() {
        try {
            regStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user'
                }
            });
            document.getElementById('regVideo').srcObject = regStream;
            document.getElementById('faceStatus').textContent = 'Loading AI models...';

            if (!regModelsLoaded && typeof faceapi !== 'undefined') {
                const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
                await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
                await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
                await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
                regModelsLoaded = true;
            }

            document.getElementById('btnCapture').disabled = false;
            document.getElementById('faceStatus').textContent = 'Camera Ready. Frame your face.';
        } catch (err) {
            document.getElementById('faceStatus').innerHTML = '<span class="text-danger">Error: ' + err.message + '</span>';
        }
    }

    function stopRegCamera() {
        if (regStream) {
            regStream.getTracks().forEach(t => t.stop());
            regStream = null;
        }
    }

    async function captureAndRegister() {
        const btn = document.getElementById('btnCapture');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const video = document.getElementById('regVideo');
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const photoData = canvas.toDataURL('image/jpeg', 0.9);

            let descriptor = null;
            if (regModelsLoaded && typeof faceapi !== 'undefined') {
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks().withFaceDescriptor();
                if (detection) {
                    descriptor = Array.from(detection.descriptor);
                } else {
                    showAlert('No face detected. Please position yourself correctly.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-camera"></i> Capture & Save Face';
                    return;
                }
            }

            const resp = await fetch('<?= BASE_URL ?>/api/attendance/face-register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    staff_id: activeStaff.id,
                    photo: photoData,
                    descriptor: descriptor
                })
            });
            const result = await resp.json();
            if (result.success) {
                showAlert('Face ID saved!', 'success');
                stopRegCamera();
                setTimeout(() => location.reload(), 1000);
            } else throw new Error(result.message);
        } catch (err) {
            showAlert(err.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-camera"></i> Capture & Save Face';
        }
    }

    async function doFingerprintRegister() {
        const btn = document.getElementById('btnFingerprint');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Enrollment...';

        try {
            const challengeResp = await fetch('<?= BASE_URL ?>/api/attendance/webauthn-challenge.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    staff_id: activeStaff.id,
                    action: 'register'
                })
            });
            const data = await challengeResp.json();
            if (!data.success) throw new Error(data.message);

            const challenge = new Uint8Array(data.challenge);
            const credential = await navigator.credentials.create({
                publicKey: {
                    challenge: challenge,
                    rp: {
                        name: 'ERP Attendance',
                        id: window.location.hostname
                    },
                    user: {
                        id: new Uint8Array(data.user.id),
                        name: data.user.name,
                        displayName: data.user.display_name
                    },
                    pubKeyCredParams: [{
                        alg: -7,
                        type: 'public-key'
                    }, {
                        alg: -257,
                        type: 'public-key'
                    }],
                    authenticatorSelection: {
                        authenticatorAttachment: 'platform',
                        userVerification: 'required'
                    },
                    timeout: 60000
                }
            });

            if (credential) {
                const regResp = await fetch('<?= BASE_URL ?>/api/attendance/webauthn-register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        staff_id: activeStaff.id,
                        credential_id: bufferToBase64(credential.rawId),
                        public_key: bufferToBase64(credential.response.getPublicKey())
                    })
                });
                const regResult = await regResp.json();
                if (regResult.success) {
                    showAlert('Fingerprint registered!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else throw new Error(regResult.message);
            }
        } catch (err) {
            showAlert(err.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-fingerprint me-2"></i> Register Fingerprint';
        }
    }

    async function saveCardId() {
        const cardId = document.getElementById('regCardId').value;
        try {
            const resp = await fetch('<?= BASE_URL ?>/api/attendance/card-register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    staff_id: activeStaff.id,
                    card_id: cardId
                })
            });
            const result = await resp.json();
            if (result.success) {
                showAlert('Card ID updated!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else throw new Error(result.message);
        } catch (err) {
            showAlert(err.message, 'error');
        }
    }

    function bufferToBase64(buffer) {
        return btoa(String.fromCharCode(...new Uint8Array(buffer)));
    }
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>