<?php

/**
 * Mobile Check-in Page
 * WebAuthn (Fingerprint/FaceID) + Face Camera + GPS
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('hr.mobile_checkin');
$user = db_select_one('users', ['id' => get_current_user_id()]);
$staff = db_select_one('staff', ['user_id' => get_current_user_id()]);
if (!$staff) {
    $staff = db_select_one('staff', ['id' => get_current_user_id()]);
}

if (!$staff) {
    $page_title = 'Attendance Error';
    include __DIR__ . '/../../templates/header.php';
    echo "<div class='container mt-5 mb-5'><div class='alert alert-danger shadow'><h4 class='alert-heading'><i class='fas fa-user-slash'></i> Staff Link Missing</h4><p>আপনার ইউজার একাউন্টটি কোনো 'Staff' প্রোফাইলের সাথে যুক্ত নয়। দয়া করে অ্যাডমিন প্যানেল থেকে আপনার ইউজার একাউন্টটি আপনার স্টাফ প্রোফাইলের সাথে লিঙ্ক করুন (Settings > Users বা HR > Staff Edit এ গিয়ে User ID সেট করুন)।</p><hr><p class='mb-0 small text-muted'>User ID: " . get_current_user_id() . "</p></div></div>";
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

// Get settings
$settings_rows = db_query("SELECT setting_key, setting_value FROM attendance_settings");
$att_settings = [];
foreach ($settings_rows ?: [] as $row) {
    $att_settings[$row['setting_key']] = $row['setting_value'];
}

$gps_enabled = ($att_settings['gps_enabled'] ?? '1') === '1';
$gps_lat = $att_settings['gps_latitude'] ?? '0';
$gps_lng = $att_settings['gps_longitude'] ?? '0';
$gps_radius = $att_settings['gps_radius_meters'] ?? '100';
$webauthn_enabled = ($att_settings['webauthn_enabled'] ?? '1') === '1';
$face_enabled = ($att_settings['face_detection_enabled'] ?? '1') === '1';
$face_threshold = $att_settings['face_confidence_threshold'] ?? '0.5';

// Check today's attendance
$today = date('Y-m-d');
$staff_id = $staff['id'] ?? 0;
$today_logs = db_query("SELECT * FROM attendance_logs WHERE staff_id = ? AND DATE(punch_time) = ? ORDER BY punch_time DESC", [$staff_id, $today]);

// Check if biometric is already registered
$bio_reg = db_select_one('staff_biometrics', ['staff_id' => $staff_id, 'biometric_type' => 'webauthn', 'is_active' => 1]);
$is_bio_registered = !empty($bio_reg);

$page_title = 'Mobile Check-in';
include __DIR__ . '/../../templates/header.php';
?>

<?php
// Show warning if not on HTTPS and not on localhost
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']);
if (!$isSecure && !$isLocalhost):
?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>HTTPS প্রয়োজন:</strong> মোবাইল থেকে Fingerprint/Camera ব্যবহার করতে হলে HTTPS সংযোগ লাগবে।
        আপনার সার্ভারটি <code><?= $_SERVER['HTTP_HOST'] ?></code> থেকে HTTPS দিয়ে অ্যাক্সেস করুন, অথবা <code>localhost</code> থেকে ব্যবহার করুন।
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<style>
    .checkin-container {
        max-width: 600px;
        margin: 0 auto;
    }

    .method-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .method-card:hover {
        border-color: var(--primary-color);
        transform: translateY(-3px);
    }

    .method-card.active {
        border-color: var(--success-color);
        background: rgba(12, 166, 120, 0.05);
    }

    .method-icon {
        font-size: 3rem;
        margin-bottom: 10px;
    }

    .gps-status {
        font-size: 0.85rem;
        padding: 8px 15px;
        border-radius: 20px;
        display: inline-block;
    }

    .gps-ok {
        background: rgba(12, 166, 120, 0.1);
        color: var(--success-color);
    }

    .gps-fail {
        background: rgba(224, 49, 49, 0.1);
        color: var(--danger-color);
    }

    .gps-wait {
        background: rgba(240, 140, 0, 0.1);
        color: var(--warning-color);
    }

    #videoContainer {
        position: relative;
        width: 100%;
        max-width: 400px;
        margin: 0 auto;
    }

    #videoContainer video {
        width: 100%;
        border-radius: 12px;
    }

    #videoContainer canvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }

    .today-log {
        font-size: 0.85rem;
    }

    .pulse-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.3;
        }
    }
</style>

<div class="checkin-container">
    <!-- GPS Status -->
    <?php if ($gps_enabled): ?>
        <div class="text-center mb-3">
            <span class="gps-status gps-wait" id="gpsStatus">
                <i class="fas fa-map-marker-alt"></i> <span id="gpsText">Locating...</span>
            </span>
        </div>
    <?php endif; ?>

    <!-- Staff Info -->
    <div class="card shadow mb-4">
        <div class="card-body text-center py-4">
            <div style="width:80px;height:80px;background:var(--primary-color);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;margin-bottom:10px;">
                <i class="fas fa-user"></i>
            </div>
            <h5 class="mb-1"><?= htmlspecialchars($staff['name'] ?? $user['full_name'] ?? 'Staff') ?></h5>
            <p class="text-muted mb-0"><?= htmlspecialchars($staff['designation'] ?? '') ?></p>
            <p class="text-muted mb-0"><small><?= date('l, d M Y') ?></small></p>
        </div>
    </div>

    <!-- Check-in Methods -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-fingerprint"></i> Check-in Method</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php if ($webauthn_enabled): ?>
                    <div class="col-4">
                        <div class="card method-card text-center p-2" onclick="selectMethod('biometric')" id="methodBiometric">
                            <div class="method-icon text-primary mt-1" style="font-size: 1.5rem;"><i class="fas fa-fingerprint"></i></div>
                            <strong style="font-size: 0.9rem;">Finger</strong>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($face_enabled): ?>
                    <div class="col-4">
                        <div class="card method-card text-center p-2" onclick="selectMethod('face')" id="methodFace">
                            <div class="method-icon text-info mt-1" style="font-size: 1.5rem;"><i class="fas fa-camera"></i></div>
                            <strong style="font-size: 0.9rem;">Face</strong>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="col-4">
                    <div class="card method-card text-center p-2" onclick="selectMethod('card')" id="methodCard">
                        <div class="method-icon text-warning mt-1" style="font-size: 1.5rem;"><i class="fas fa-id-card"></i></div>
                        <strong style="font-size: 0.9rem;">Card</strong>
                    </div>
                </div>
            </div>

            <!-- Biometric Section -->
            <div id="biometricSection" class="mt-4" style="display:none;">
                <div class="text-center">
                    <p class="text-muted" id="bioStatusText">
                        <?= $is_bio_registered ? 'আপনার নিবন্ধিত বায়োমেট্রিক ব্যবহার করুন' : 'আপনার ডিভাইসের ফিঙ্গারপ্রিন্ট বা ফেস-আইডি নিবন্ধিত করুন' ?>
                    </p>
                    <button class="btn btn-lg btn-primary" id="btnBiometricCheckin" onclick="doBiometricCheckin()">
                        <i class="fas fa-fingerprint me-2"></i>
                        <span id="btnBioText"><?= $is_bio_registered ? 'Check-In Now' : 'Register Fingerprint' ?></span>
                    </button>
                </div>
            </div>

            <!-- Card Section -->
            <div id="cardSection" class="mt-4" style="display:none;">
                <div class="text-center">
                    <p class="text-muted">আপনার আইডি কার্ড নম্বরটি প্রবেশ করুন বা স্ক্যান করুন</p>
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-warning text-white"><i class="fas fa-id-card"></i></span>
                        <input type="text" id="mobileCardId" class="form-control form-control-lg" placeholder="Card Number / RFID">
                    </div>
                    <button class="btn btn-lg btn-warning w-100" id="btnCardCheckin" onclick="doCardCheckin()">
                        <i class="fas fa-sign-in-alt me-2"></i> Check-In with Card
                    </button>
                </div>
            </div>

            <!-- Face Section -->
            <div id="faceSection" class="mt-4" style="display:none;">
                <div id="videoContainer" class="mb-3">
                    <video id="video" autoplay muted playsinline></video>
                    <canvas id="overlay"></canvas>
                </div>
                <div class="text-center">
                    <button class="btn btn-lg btn-info" id="btnFaceCheckin" onclick="doFaceCheckin()" disabled>
                        <i class="fas fa-camera me-2"></i> Capture & Check-In
                    </button>
                    <p class="text-muted mt-2" id="faceStatus">Loading face detection...</p>
                </div>
            </div>

            <!-- Action Selection -->
            <div class="text-center mt-3">
                <div class="btn-group flex-wrap gap-2" role="group">
                    <input type="radio" class="btn-check" name="punchType" id="punchAuto" value="auto" checked>
                    <label class="btn btn-outline-primary" for="punchAuto"><i class="fas fa-magic"></i> Auto (Smart In/Out)</label>

                    <input type="radio" class="btn-check" name="punchType" id="punchOutDuty" value="out_duty">
                    <label class="btn btn-outline-warning" for="punchOutDuty" title="কাজের জন্য বাইরে"><i class="fas fa-briefcase"></i> Out for Work</label>

                    <input type="radio" class="btn-check" name="punchType" id="punchReturnDuty" value="return_duty">
                    <label class="btn btn-outline-info" for="punchReturnDuty"><i class="fas fa-undo"></i> Return from Work</label>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Logs -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history"></i> Today's Attendance</h6>
        </div>
        <div class="card-body">
            <?php if (empty($today_logs)): ?>
                <p class="text-muted text-center mb-0">No attendance recorded today</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm today-log">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_logs as $log): ?>
                                <tr>
                                    <td><?= date('h:i A', strtotime($log['punch_time'])) ?></td>
                                    <td>
                                        <?php if ($log['punch_type'] === 'in'): ?>
                                            <span class="badge bg-success">In</span>
                                        <?php elseif ($log['punch_type'] === 'out_duty'): ?>
                                            <span class="badge bg-warning text-dark">Out (Duty)</span>
                                        <?php elseif ($log['punch_type'] === 'return_duty'): ?>
                                            <span class="badge bg-info text-dark">Return</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Out</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= $log['source'] ?? $log['verification_method'] ?? 'manual' ?></span></td>
                                    <td>
                                        <span class="pulse-dot" style="background:<?= ($log['status'] ?? 'valid') === 'valid' ? 'var(--success-color)' : 'var(--warning-color)' ?>"></span>
                                        <?= ucfirst($log['status'] ?? 'valid') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- face-api.js CDN -->
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
    const BASE = '<?= BASE_URL ?>';
    const STAFF_ID = <?= $staff['id'] ?? 0 ?>;
    const GPS_ENABLED = <?= $gps_enabled ? 'true' : 'false' ?>;
    const GPS_LAT = <?= $gps_lat ?>;
    const GPS_LNG = <?= $gps_lng ?>;
    const GPS_RADIUS = <?= $gps_radius ?>;
    const FACE_THRESHOLD = <?= $face_threshold ?>;
    let currentGPS = null;
    let gpsValid = false;
    let faceModelsLoaded = false;
    let videoStream = null;

    // GPS
    if (GPS_ENABLED && navigator.geolocation) {
        navigator.geolocation.watchPosition(pos => {
            currentGPS = {
                lat: pos.coords.latitude,
                lng: pos.coords.longitude
            };
            const dist = getDistanceFromLatLng(currentGPS.lat, currentGPS.lng, GPS_LAT, GPS_LNG);
            gpsValid = (GPS_LAT == 0 && GPS_LNG == 0) || dist <= GPS_RADIUS;
            const el = document.getElementById('gpsStatus');
            const txt = document.getElementById('gpsText');
            if (gpsValid) {
                el.className = 'gps-status gps-ok';
                txt.textContent = 'Location OK (' + Math.round(dist) + 'm)';
            } else {
                el.className = 'gps-status gps-fail';
                txt.textContent = 'Out of range (' + Math.round(dist) + 'm)';
            }
        }, err => {
            document.getElementById('gpsStatus').className = 'gps-status gps-fail';
            document.getElementById('gpsText').textContent = 'GPS unavailable';
        }, {
            enableHighAccuracy: true
        });
    } else if (!GPS_ENABLED) {
        gpsValid = true;
    }

    function getDistanceFromLatLng(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function selectMethod(method) {
        document.querySelectorAll('.method-card').forEach(c => c.classList.remove('active'));
        document.getElementById('biometricSection').style.display = 'none';
        document.getElementById('faceSection').style.display = 'none';
        document.getElementById('cardSection').style.display = 'none'; // Added this line

        if (method === 'biometric') {
            document.getElementById('methodBiometric').classList.add('active');
            document.getElementById('biometricSection').style.display = 'block';
        } else if (method === 'face') {
            document.getElementById('methodFace').classList.add('active');
            document.getElementById('faceSection').style.display = 'block';
            startCamera();
        } else if (method === 'card') { // Added this block
            document.getElementById('methodCard').classList.add('active');
            document.getElementById('cardSection').style.display = 'block';
        }
    }

    // WebAuthn Biometric (Enrollment + Authentication)
    async function doBiometricCheckin() {
        const btn = document.getElementById('btnBiometricCheckin');
        const btnText = document.getElementById('btnBioText');
        btn.disabled = true;

        // Check if WebAuthn is available (requires HTTPS on non-localhost)
        if (!window.PublicKeyCredential) {
            showAlert('Fingerprint/Biometric এই ডিভাইসে কাজ করছে না। মোবাইল থেকে ব্যবহার করতে হলে HTTPS সংযোগ প্রয়োজন। দয়া করে Face বা Card মেথড ব্যবহার করুন।', 'error');
            btn.disabled = false;
            btnText.innerText = 'Try Again';
            return;
        }

        try {
            // 1. Get Challenge from server
            const challengeResp = await fetch(BASE + '/api/attendance/webauthn-challenge.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    staff_id: STAFF_ID,
                    action: 'authenticate'
                }) // 'authenticate' is fine as it checks status
            });
            const data = await challengeResp.json();
            if (!data.success) throw new Error(data.message);

            const challenge = new Uint8Array(data.challenge);

            // 2. REGISTRATION FLOW
            if (data.status === 'needs_registration') {
                btnText.innerText = 'Registering...';

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
                    // Send registration to server
                    const regResp = await fetch(BASE + '/api/attendance/webauthn-register.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            staff_id: STAFF_ID,
                            credential_id: bufferToBase64(credential.rawId),
                            public_key: bufferToBase64(credential.response.getPublicKey())
                        })
                    });
                    const regResult = await regResp.json();
                    if (regResult.success) {
                        showAlert('Biometric registered! Now you can check-in.', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else throw new Error(regResult.message);
                }
            }
            // 3. AUTHENTICATION FLOW
            else {
                btnText.innerText = 'Verifying...';

                // Decode credential ID from server
                const rawId = base64ToBuffer(data.credential_id);

                const assertion = await navigator.credentials.get({
                    publicKey: {
                        challenge: challenge,
                        allowCredentials: [{
                            id: rawId,
                            type: 'public-key'
                        }],
                        userVerification: 'required',
                        timeout: 60000
                    }
                });

                if (assertion) {
                    await recordPunch('web_biometric');
                }
            }

        } catch (err) {
            console.error(err);
            showAlert(err.message || 'Operation failed', 'error');
            btn.disabled = false;
            btnText.innerText = 'Try Again';
        }
    }

    async function doCardCheckin() {
        const cardInput = document.getElementById('mobileCardId');
        const cardId = cardInput.value.trim();
        if (!cardId) {
            showAlert('দয়া করে কার্ড আইডি প্রদান করুন', 'warning');
            return;
        }

        const btn = document.getElementById('btnCardCheckin');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Verifying...';

        try {
            // Check if card matches staff's registered card
            if (cardId !== '<?= $staff['card_id'] ?>') {
                throw new Error('ভুল কার্ড আইডি! আপনার প্রোফাইলে নিবন্ধিত কার্ডটি ব্যবহার করুন।');
            }

            await recordPunch('card'); // Custom source for card punch
        } catch (err) {
            showAlert(err.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i> Check-In with Card';
        }
    }

    function bufferToBase64(buffer) {
        return btoa(String.fromCharCode(...new Uint8Array(buffer)));
    }

    function base64ToBuffer(base64) {
        const binary = atob(base64);
        const buffer = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) buffer[i] = binary.charCodeAt(i);
        return buffer.buffer;
    }

    // Face Check-in
    async function startCamera() {
        try {
            videoStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: 400,
                    height: 300
                }
            });
            document.getElementById('video').srcObject = videoStream;

            // Load face-api models
            if (!faceModelsLoaded && typeof faceapi !== 'undefined') {
                const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
                await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
                await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
                await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
                faceModelsLoaded = true;
            }

            document.getElementById('btnFaceCheckin').disabled = false;
            document.getElementById('faceStatus').textContent = 'Camera ready. Position your face and click Capture.';
        } catch (err) {
            document.getElementById('faceStatus').textContent = 'Camera error: ' + err.message;
        }
    }

    async function doFaceCheckin() {
        const btn = document.getElementById('btnFaceCheckin');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Detecting...';

        try {
            const video = document.getElementById('video');

            // Capture frame
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const photoData = canvas.toDataURL('image/jpeg', 0.8);

            // Detect face
            let descriptor = null;
            if (faceModelsLoaded && typeof faceapi !== 'undefined') {
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection) {
                    descriptor = Array.from(detection.descriptor);
                    document.getElementById('faceStatus').textContent = 'Face detected! Verifying...';
                } else {
                    document.getElementById('faceStatus').textContent = 'No face detected. Try again.';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-camera me-2"></i> Capture & Check-In';
                    return;
                }
            }

            // Send to server
            const punchType = document.querySelector('input[name="punchType"]:checked').value;
            const resp = await fetch(BASE + '/api/attendance/face-verify.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    staff_id: STAFF_ID,
                    photo: photoData,
                    descriptor: descriptor,
                    punch_type: punchType,
                    gps: currentGPS,
                    threshold: FACE_THRESHOLD
                })
            });

            const result = await resp.json();
            if (result.success) {
                showAlert('Attendance recorded! ' + (punchType === 'in' ? 'Welcome!' : 'Goodbye!'), 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert(result.message || 'Face verification failed', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-camera me-2"></i> Capture & Check-In';
            }
        } catch (err) {
            showAlert('Error: ' + err.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-camera me-2"></i> Capture & Check-In';
        }
    }

    async function recordPunch(source) {
        const punchType = document.querySelector('input[name="punchType"]:checked').value;
        const resp = await fetch(BASE + '/api/attendance/punch.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                staff_id: STAFF_ID,
                punch_type: punchType,
                source: source,
                gps_latitude: currentGPS?.lat || null,
                gps_longitude: currentGPS?.lng || null,
                device_info: navigator.userAgent
            })
        });
        const result = await resp.json();
        if (result.success) {
            showAlert('Attendance recorded! ' + (punchType === 'in' ? 'Welcome!' : 'Goodbye!'), 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showAlert(result.message || 'Failed to record', 'error');
        }
    }
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>