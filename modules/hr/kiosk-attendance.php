<?php

/**
 * Kiosk Mode Attendance
 * Full-screen webcam face recognition for office entrance
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('hr.kiosk_attendance');

// Load all active face descriptors
$biometrics = db_query("SELECT sb.staff_id, sb.face_descriptor, s.name as staff_name, s.designation 
    FROM staff_biometrics sb 
    JOIN staff s ON sb.staff_id = s.id 
    WHERE sb.biometric_type = 'face' AND sb.is_active = 1 AND sb.face_descriptor IS NOT NULL");

$face_data = [];
foreach ($biometrics ?: [] as $bio) {
    $face_data[] = [
        'staff_id' => $bio['staff_id'],
        'name' => $bio['staff_name'],
        'designation' => $bio['designation'] ?? '',
        'descriptor' => json_decode($bio['face_descriptor'], true)
    ];
}

$settings_rows = db_query("SELECT setting_key, setting_value FROM attendance_settings");
$att_settings = [];
foreach ($settings_rows ?: [] as $row) {
    $att_settings[$row['setting_key']] = $row['setting_value'];
}
$kiosk_pin = $att_settings['kiosk_pin'] ?? '1234';
$threshold = $att_settings['face_confidence_threshold'] ?? '0.5';
$play_sound = ($att_settings['kiosk_welcome_sound'] ?? '1') === '1';

$business = db_select_one('business_settings', ['id' => 1]);
$bname = $business['business_name'] ?? (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Company');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Attendance - <?= htmlspecialchars($bname) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0a0f1c;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            height: 100vh;
        }

        .kiosk-wrapper {
            display: flex;
            height: 100vh;
        }

        .camera-section {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #kioskVideo {
            width: 100%;
            height: 100vh;
            object-fit: cover;
        }

        #kioskOverlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .info-panel {
            width: 400px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            display: flex;
            flex-direction: column;
            padding: 30px;
        }

        .company-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .company-header h2 {
            font-size: 1.4rem;
            font-weight: 800;
        }

        .clock {
            text-align: center;
            margin: 20px 0;
        }

        .clock .time {
            font-size: 4rem;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .clock .date {
            font-size: 1.1rem;
            color: #94a3b8;
        }

        .welcome-msg {
            text-align: center;
            padding: 30px 20px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 16px;
            margin: 20px 0;
            display: none;
        }

        .welcome-msg h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: #34d399;
        }

        .welcome-msg p {
            color: #94a3b8;
        }

        .recent-list {
            flex: 1;
            overflow-y: auto;
        }

        .recent-item {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            margin-bottom: 8px;
        }

        .recent-item .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3b5bdb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: 700;
        }

        .recent-item .info {
            flex: 1;
        }

        .recent-item .info .name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .recent-item .info .time {
            font-size: 0.75rem;
            color: #64748b;
        }

        .recent-item .badge-type {
            font-size: 0.7rem;
        }

        .scanning-indicator {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            padding: 10px 25px;
            border-radius: 30px;
            font-size: 0.9rem;
        }

        .exit-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            opacity: 0.2;
            z-index: 10;
        }

        .exit-btn:hover {
            opacity: 1;
        }
    </style>
</head>

<body>
    <div class="kiosk-wrapper">
        <!-- Camera -->
        <div class="camera-section">
            <video id="kioskVideo" autoplay muted playsinline></video>
            <canvas id="kioskOverlay"></canvas>
            <div class="scanning-indicator" id="scanStatus">
                <i class="fas fa-circle-notch fa-spin"></i> Scanning for faces...
            </div>
            <button class="btn btn-sm btn-outline-light exit-btn" onclick="exitKiosk()">
                <i class="fas fa-times"></i> Exit
            </button>
        </div>

        <!-- Info Panel -->
        <div class="info-panel">
            <div class="company-header">
                <h2><?= htmlspecialchars($bname) ?></h2>
                <small class="text-muted">Attendance System</small>
            </div>

            <div class="clock">
                <div class="time" id="clockTime">00:00:00</div>
                <div class="date" id="clockDate"></div>
            </div>

            <div class="welcome-msg" id="welcomeMsg">
                <h3 id="welcomeName">Welcome!</h3>
                <p id="welcomeDesig"></p>
                <span class="badge bg-success" id="welcomeType">Check-In</span>
            </div>

            <h6 class="text-muted mb-2"><i class="fas fa-history"></i> Recent</h6>
            <div class="recent-list" id="recentList">
                <p class="text-muted text-center">No attendance yet</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <script>
        const BASE = '<?= BASE_URL ?>';
        const FACE_DATA = <?= json_encode($face_data) ?>;
        const THRESHOLD = <?= $threshold ?>;
        const KIOSK_PIN = '<?= $kiosk_pin ?>';
        const PLAY_SOUND = <?= $play_sound ? 'true' : 'false' ?>;
        let isProcessing = false;
        let recentCheckins = [];
        let labeledDescriptors = [];

        // Clock
        function updateClock() {
            const now = new Date();
            document.getElementById('clockTime').textContent = now.toLocaleTimeString('en-US', {
                hour12: false
            });
            document.getElementById('clockDate').textContent = now.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Initialize
        async function init() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: 1280,
                        height: 720,
                        facingMode: 'user'
                    }
                });
                document.getElementById('kioskVideo').srcObject = stream;

                const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
                await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
                await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
                await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

                // Build labeled descriptors
                FACE_DATA.forEach(fd => {
                    if (fd.descriptor && fd.descriptor.length === 128) {
                        labeledDescriptors.push(new faceapi.LabeledFaceDescriptors(
                            fd.staff_id + '|' + fd.name + '|' + fd.designation,
                            [new Float32Array(fd.descriptor)]
                        ));
                    }
                });

                document.getElementById('scanStatus').innerHTML = '<i class="fas fa-check-circle text-success"></i> Ready - Scanning for faces...';
                detectLoop();
            } catch (err) {
                document.getElementById('scanStatus').innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i> ' + err.message;
            }
        }

        async function detectLoop() {
            const video = document.getElementById('kioskVideo');
            if (video.paused || video.ended || isProcessing) {
                requestAnimationFrame(detectLoop);
                return;
            }

            if (labeledDescriptors.length === 0) {
                document.getElementById('scanStatus').innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i> No face data registered. Go to Biometric Registration.';
                setTimeout(detectLoop, 3000);
                return;
            }

            const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptors();

            if (detections.length > 0 && !isProcessing) {
                const matcher = new faceapi.FaceMatcher(labeledDescriptors, 1 - THRESHOLD);

                for (const det of detections) {
                    const match = matcher.findBestMatch(det.descriptor);
                    if (match.label !== 'unknown') {
                        const parts = match.label.split('|');
                        const staffId = parseInt(parts[0]);
                        const staffName = parts[1];
                        const staffDesig = parts[2] || '';

                        // Prevent duplicate within 60 seconds
                        const lastCheckin = recentCheckins.find(r => r.staff_id === staffId);
                        if (lastCheckin && (Date.now() - lastCheckin.time) < 60000) continue;

                        isProcessing = true;
                        await recordKioskPunch(staffId, staffName, staffDesig);
                        isProcessing = false;
                    }
                }
            }

            setTimeout(detectLoop, 1000); // Check every second
        }

        async function recordKioskPunch(staffId, name, designation) {
            try {
                const resp = await fetch(BASE + '/api/attendance/punch.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        staff_id: staffId,
                        punch_type: 'in',
                        source: 'webcam_kiosk',
                        verification_method: 'face',
                        device_info: 'Kiosk Mode'
                    })
                });
                const result = await resp.json();

                if (result.success) {
                    showWelcome(name, designation);
                    recentCheckins.push({
                        staff_id: staffId,
                        name: name,
                        time: Date.now()
                    });
                    updateRecentList();

                    if (PLAY_SOUND) {
                        try {
                            new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=').play();
                        } catch (e) {}
                    }
                }
            } catch (err) {
                console.error('Punch error:', err);
            }
        }

        function showWelcome(name, designation) {
            const el = document.getElementById('welcomeMsg');
            document.getElementById('welcomeName').textContent = 'Welcome, ' + name + '!';
            document.getElementById('welcomeDesig').textContent = designation;
            el.style.display = 'block';
            setTimeout(() => {
                el.style.display = 'none';
            }, 5000);
        }

        function updateRecentList() {
            const list = document.getElementById('recentList');
            const sorted = [...recentCheckins].sort((a, b) => b.time - a.time).slice(0, 10);
            let html = '';
            sorted.forEach(r => {
                const t = new Date(r.time);
                html += `<div class="recent-item">
                <div class="avatar">${r.name.charAt(0)}</div>
                <div class="info">
                    <div class="name">${r.name}</div>
                    <div class="time">${t.toLocaleTimeString()}</div>
                </div>
                <span class="badge bg-success badge-type">In</span>
            </div>`;
            });
            list.innerHTML = html || '<p class="text-muted text-center">No attendance yet</p>';
        }

        function exitKiosk() {
            const pin = prompt('Enter PIN to exit kiosk mode:');
            if (pin === KIOSK_PIN) {
                window.location.href = BASE + '/modules/hr/attendance.php';
            } else if (pin !== null) {
                alert('Incorrect PIN');
            }
        }

        init();
    </script>
</body>

</html>