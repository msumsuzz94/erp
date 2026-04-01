<?php
/**
 * Attendance Settings Page
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

if (is_post() && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $fields = [
        'gps_enabled','gps_latitude','gps_longitude','gps_radius_meters',
        'face_confidence_threshold','face_detection_enabled','webauthn_enabled',
        'kiosk_pin','kiosk_welcome_sound',
        'cctv_away_timeout','cctv_python_url',
        'late_notify_enabled','absent_notify_enabled','away_notify_enabled',
        'max_checkins_per_day', 'allow_web_face', 'allow_web_biometric', 'allow_manual', 'monthly_working_days'
    ];
    foreach ($fields as $key) {
        $val = $_POST[$key] ?? '0';
        $existing = db_query("SELECT id FROM attendance_settings WHERE setting_key = ?", [$key]);
        if (!empty($existing)) {
            db_query("UPDATE attendance_settings SET setting_value = ? WHERE setting_key = ?", [$val, $key]);
        } else {
            db_insert('attendance_settings', ['setting_key' => $key, 'setting_value' => $val, 'setting_group' => 'general']);
        }
    }
    redirect_with_message('attendance-settings.php', 'Settings saved successfully', 'success');
}

// Load current settings
$sr = db_query("SELECT setting_key, setting_value FROM attendance_settings");
$s = [];
foreach ($sr ?: [] as $r) $s[$r['setting_key']] = $r['setting_value'];

$page_title = 'Attendance Settings';
include __DIR__ . '/../../templates/header.php';
?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    
    <div class="row">
        <!-- General Attendance Settings -->
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-cogs"></i> General Attendance Settings</h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maximum Daily Check-ins Per Staff</label>
                            <input type="number" name="max_checkins_per_day" class="form-control" value="<?= htmlspecialchars($s['max_checkins_per_day'] ?? '0') ?>">
                            <small class="text-muted">Enter 0 for unlimited check-ins per day.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Full Working Days per Month</label>
                            <input type="number" name="monthly_working_days" class="form-control" value="<?= htmlspecialchars($s['monthly_working_days'] ?? '30') ?>" min="1" max="31">
                            <small class="text-muted">Used to proportionally calculate staff salaries.</small>
                        </div>
                    </div>
                    
                    <h6 class="font-weight-bold mt-3 border-bottom pb-2">Allowed Mediums</h6>
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="allow_web_face" value="1" id="allowFace" <?= ($s['allow_web_face'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allowFace">Web Face Login</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="allow_web_biometric" value="1" id="allowWebAuthn" <?= ($s['allow_web_biometric'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allowWebAuthn">WebAuthn (Fingerprint)</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="allow_manual" value="1" id="allowManual" <?= ($s['allow_manual'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allowManual">Manual Attendance (Admin)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GPS Settings -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-map-marker-alt"></i> GPS / Location</h6></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="gps_enabled" value="1" id="gpsEnabled" <?= ($s['gps_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="gpsEnabled">Enable GPS Verification</label>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Office Latitude</label>
                            <input type="text" name="gps_latitude" class="form-control" value="<?= htmlspecialchars($s['gps_latitude'] ?? '0') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Office Longitude</label>
                            <input type="text" name="gps_longitude" class="form-control" value="<?= htmlspecialchars($s['gps_longitude'] ?? '0') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Allowed Radius (meters)</label>
                        <input type="number" name="gps_radius_meters" class="form-control" value="<?= htmlspecialchars($s['gps_radius_meters'] ?? '100') ?>">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="getMyLocation()"><i class="fas fa-crosshairs"></i> Set My Current Location</button>
                </div>
            </div>
        </div>

        <!-- Face / Biometric -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-camera"></i> Face & Biometric</h6></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="face_detection_enabled" value="1" id="faceEnabled" <?= ($s['face_detection_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="faceEnabled">Enable Face Detection</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Face Match Confidence Threshold (0-1)</label>
                        <input type="number" step="0.05" min="0" max="1" name="face_confidence_threshold" class="form-control" value="<?= htmlspecialchars($s['face_confidence_threshold'] ?? '0.5') ?>">
                        <small class="text-muted">Lower = more lenient, Higher = more strict</small>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="webauthn_enabled" value="1" id="webauthnEnabled" <?= ($s['webauthn_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="webauthnEnabled">Enable WebAuthn (Fingerprint/FaceID)</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kiosk -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-desktop"></i> Kiosk Mode</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Exit PIN</label>
                        <input type="text" name="kiosk_pin" class="form-control" value="<?= htmlspecialchars($s['kiosk_pin'] ?? '1234') ?>">
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="kiosk_welcome_sound" value="1" id="kioskSound" <?= ($s['kiosk_welcome_sound'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="kioskSound">Play Welcome Sound</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- CCTV -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-video"></i> CCTV Middleware</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Away Timeout (minutes)</label>
                        <input type="number" name="cctv_away_timeout" class="form-control" value="<?= htmlspecialchars($s['cctv_away_timeout'] ?? '5') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Python Service URL</label>
                        <input type="url" name="cctv_python_url" class="form-control" value="<?= htmlspecialchars($s['cctv_python_url'] ?? 'http://localhost:5050') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-bell"></i> Notifications</h6></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="late_notify_enabled" value="1" id="lateNotify" <?= ($s['late_notify_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="lateNotify">Notify on Late Arrival</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="absent_notify_enabled" value="1" id="absentNotify" <?= ($s['absent_notify_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="absentNotify">Notify on Absence</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="away_notify_enabled" value="1" id="awayNotify" <?= ($s['away_notify_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="awayNotify">Notify on CCTV Away Detection</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-end mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save All Settings</button>
    </div>
</form>

<script>
function getMyLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            document.querySelector('[name="gps_latitude"]').value = pos.coords.latitude.toFixed(8);
            document.querySelector('[name="gps_longitude"]').value = pos.coords.longitude.toFixed(8);
            showAlert('Location set!', 'success');
        }, err => showAlert('GPS error: ' + err.message, 'error'));
    }
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
