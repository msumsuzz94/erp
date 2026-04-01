<?php
/**
 * Mobile GPS Tracker - PWA
 * Installed on staff phones, tracks GPS in background
 * Works offline with IndexedDB auto-sync
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$user = db_select_one('users', ['id' => get_current_user_id()]);
$staff = db_select_one('staff', ['user_id' => get_current_user_id()]);
if (!$staff) {
    $staff = db_select_one('staff', ['id' => get_current_user_id()]);
}

// Get tracking interval
$interval = 30;
$setting = db_query("SELECT setting_value FROM livemap_settings WHERE setting_key = 'tracking_interval_seconds'");
if (!empty($setting)) $interval = intval($setting[0]['setting_value']);

$staff_id = $staff['id'] ?? 0;
$staff_name = $staff['name'] ?? ($user['username'] ?? 'Unknown');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4e73df">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>GPS Tracker - <?= APP_NAME ?></title>
    <link rel="manifest" href="<?= BASE_URL ?>/manifest-tracker.json">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .tracker-app { max-width: 420px; margin: 0 auto; padding: 20px; }
        .tracker-header { text-align: center; padding: 30px 0 20px; }
        .tracker-header .logo { width: 60px; height: 60px; background: linear-gradient(135deg, #4e73df, #36b9cc); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; margin-bottom: 12px; }
        .tracker-header h1 { font-size: 1.4rem; font-weight: 700; }
        .tracker-header .subtitle { color: #94a3b8; font-size: 0.85rem; }
        .status-ring { width: 160px; height: 160px; border-radius: 50%; margin: 30px auto; display: flex; align-items: center; justify-content: center; flex-direction: column; position: relative; }
        .status-ring::before { content: ''; position: absolute; inset: 0; border-radius: 50%; border: 4px solid #1e293b; }
        .status-ring.active::before { border-color: #22c55e; box-shadow: 0 0 30px rgba(34,197,94,0.3); animation: pulse-ring 2s infinite; }
        .status-ring.inactive::before { border-color: #ef4444; }
        .status-ring .icon { font-size: 3rem; margin-bottom: 5px; }
        .status-ring .label { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        @keyframes pulse-ring { 0%,100% { box-shadow: 0 0 20px rgba(34,197,94,0.2); } 50% { box-shadow: 0 0 40px rgba(34,197,94,0.5); } }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 20px 0; }
        .info-card { background: #1e293b; border-radius: 12px; padding: 14px; text-align: center; }
        .info-card .value { font-size: 1.3rem; font-weight: 700; color: #fff; }
        .info-card .label { font-size: 0.7rem; color: #94a3b8; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-track { width: 100%; padding: 16px; border: none; border-radius: 14px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.3s; margin-top: 20px; }
        .btn-start { background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; }
        .btn-stop { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
        .btn-track:active { transform: scale(0.97); }
        .log-area { background: #1e293b; border-radius: 12px; padding: 12px; margin-top: 20px; max-height: 150px; overflow-y: auto; font-family: monospace; font-size: 0.7rem; color: #94a3b8; }
        .log-area .log-entry { padding: 2px 0; border-bottom: 1px solid #ffffff08; }
        .sync-bar { background: #1e293b; border-radius: 10px; padding: 10px 14px; margin-top: 15px; display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; }
        .sync-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .sync-dot.online { background: #22c55e; }
        .sync-dot.offline { background: #ef4444; }
    </style>
</head>
<body>
<div class="tracker-app">
    <div class="tracker-header">
        <div class="logo"><i class="fas fa-satellite-dish"></i></div>
        <h1>GPS Tracker</h1>
        <div class="subtitle"><?= htmlspecialchars($staff_name) ?> • ID: <?= $staff_id ?></div>
    </div>

    <div class="status-ring inactive" id="statusRing">
        <div class="icon" id="statusIcon">📍</div>
        <div class="label" id="statusLabel">Stopped</div>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <div class="value" id="batteryValue">--%</div>
            <div class="label">🔋 Battery</div>
        </div>
        <div class="info-card">
            <div class="value" id="accuracyValue">--m</div>
            <div class="label">📡 Accuracy</div>
        </div>
        <div class="info-card">
            <div class="value" id="pendingValue">0</div>
            <div class="label">📤 Pending Sync</div>
        </div>
        <div class="info-card">
            <div class="value" id="syncedValue">0</div>
            <div class="label">✅ Synced Today</div>
        </div>
    </div>

    <div class="sync-bar">
        <div><span class="sync-dot" id="connDot"></span><span id="connLabel">Checking...</span></div>
        <div id="lastSync">Last sync: --</div>
    </div>

    <button class="btn-track btn-start" id="btnTrack" onclick="toggleTracking()">
        <i class="fas fa-play"></i> START TRACKING
    </button>

    <div class="log-area" id="logArea">
        <div class="log-entry">Ready. Tap START to begin tracking.</div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const STAFF_ID = <?= $staff_id ?>;
const INTERVAL_SEC = <?= $interval ?>;
const DB_NAME = 'gps_tracker_db';
const STORE_NAME = 'pending_locations';

let isTracking = false;
let watchId = null;
let syncedToday = 0;
let db = null;

// =============== IndexedDB ===============
function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
            }
        };
        req.onsuccess = (e) => { db = e.target.result; resolve(db); };
        req.onerror = (e) => reject(e);
    });
}

function saveLocal(locationData) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).add(locationData);
        tx.oncomplete = resolve;
        tx.onerror = reject;
    });
}

function getPendingCount() {
    return new Promise((resolve) => {
        const tx = db.transaction(STORE_NAME, 'readonly');
        const req = tx.objectStore(STORE_NAME).count();
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => resolve(0);
    });
}

function getAllPending() {
    return new Promise((resolve) => {
        const tx = db.transaction(STORE_NAME, 'readonly');
        const req = tx.objectStore(STORE_NAME).getAll();
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => resolve([]);
    });
}

function clearPending(ids) {
    return new Promise((resolve) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        ids.forEach(id => store.delete(id));
        tx.oncomplete = resolve;
        tx.onerror = resolve;
    });
}

// =============== Battery API ===============
let batteryLevel = null;
let isCharging = false;

if (navigator.getBattery) {
    navigator.getBattery().then(battery => {
        batteryLevel = Math.round(battery.level * 100);
        isCharging = battery.charging;
        updateBatteryUI();
        battery.addEventListener('levelchange', () => {
            batteryLevel = Math.round(battery.level * 100);
            updateBatteryUI();
        });
        battery.addEventListener('chargingchange', () => {
            isCharging = battery.charging;
            updateBatteryUI();
        });
    });
}

function updateBatteryUI() {
    const el = document.getElementById('batteryValue');
    el.textContent = batteryLevel !== null ? batteryLevel + '%' : '--%';
    if (isCharging) el.textContent += ' ⚡';
}

// =============== GPS Tracking ===============
function toggleTracking() {
    if (isTracking) stopTracking();
    else startTracking();
}

function startTracking() {
    if (!navigator.geolocation) {
        addLog('❌ GPS not supported on this device');
        return;
    }

    isTracking = true;
    document.getElementById('btnTrack').className = 'btn-track btn-stop';
    document.getElementById('btnTrack').innerHTML = '<i class="fas fa-stop"></i> STOP TRACKING';
    document.getElementById('statusRing').className = 'status-ring active';
    document.getElementById('statusIcon').textContent = '🛰️';
    document.getElementById('statusLabel').textContent = 'Tracking';
    addLog('✅ Tracking started (every ' + INTERVAL_SEC + 's)');

    // Watch position
    watchId = navigator.geolocation.watchPosition(
        onPosition,
        onPositionError,
        { enableHighAccuracy: true, maximumAge: INTERVAL_SEC * 1000, timeout: 30000 }
    );
}

function stopTracking() {
    isTracking = false;
    if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
    document.getElementById('btnTrack').className = 'btn-track btn-start';
    document.getElementById('btnTrack').innerHTML = '<i class="fas fa-play"></i> START TRACKING';
    document.getElementById('statusRing').className = 'status-ring inactive';
    document.getElementById('statusIcon').textContent = '📍';
    document.getElementById('statusLabel').textContent = 'Stopped';
    addLog('⏹️ Tracking stopped');
}

async function onPosition(pos) {
    const location = {
        latitude: pos.coords.latitude,
        longitude: pos.coords.longitude,
        accuracy: Math.round(pos.coords.accuracy),
        speed: pos.coords.speed ? Math.round(pos.coords.speed * 3.6 * 10) / 10 : null, // m/s to km/h
        battery_level: batteryLevel,
        is_charging: isCharging ? 1 : 0,
        recorded_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
        device_info: navigator.userAgent.substring(0, 100)
    };

    document.getElementById('accuracyValue').textContent = location.accuracy + 'm';

    // Save locally first (always)
    await saveLocal(location);
    updatePendingCount();
    addLog(`📍 ${location.latitude.toFixed(5)}, ${location.longitude.toFixed(5)} (±${location.accuracy}m)`);

    // Try to sync immediately if online
    if (navigator.onLine) {
        await syncToServer();
    }
}

function onPositionError(err) {
    addLog('⚠️ GPS Error: ' + err.message);
}

// =============== Sync to Server ===============
async function syncToServer() {
    const pending = await getAllPending();
    if (pending.length === 0) return;

    const locations = pending.map(p => ({
        latitude: p.latitude,
        longitude: p.longitude,
        accuracy: p.accuracy,
        speed: p.speed,
        battery_level: p.battery_level,
        is_charging: p.is_charging,
        recorded_at: p.recorded_at,
        device_info: p.device_info
    }));

    try {
        const resp = await fetch(BASE_URL + '/api/livemap/update-location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ staff_id: STAFF_ID, locations })
        });
        const data = await resp.json();

        if (data.success) {
            const ids = pending.map(p => p.id);
            await clearPending(ids);
            syncedToday += data.inserted;
            document.getElementById('syncedValue').textContent = syncedToday;
            document.getElementById('lastSync').textContent = 'Last: ' + new Date().toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit'});
            addLog(`✅ Synced ${data.inserted} point(s)`);
            updatePendingCount();
        }
    } catch (err) {
        addLog('⚠️ Sync failed - will retry when online');
    }
}

async function updatePendingCount() {
    const count = await getPendingCount();
    document.getElementById('pendingValue').textContent = count;
}

// =============== Connection Status ===============
function updateConnectionStatus() {
    const dot = document.getElementById('connDot');
    const label = document.getElementById('connLabel');
    if (navigator.onLine) {
        dot.className = 'sync-dot online';
        label.textContent = 'Online';
    } else {
        dot.className = 'sync-dot offline';
        label.textContent = 'Offline';
    }
}

window.addEventListener('online', () => {
    updateConnectionStatus();
    addLog('🌐 Back online - syncing...');
    syncToServer();
});
window.addEventListener('offline', () => {
    updateConnectionStatus();
    addLog('📴 Went offline - saving locally');
});

// =============== Log ===============
function addLog(msg) {
    const area = document.getElementById('logArea');
    const time = new Date().toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    area.innerHTML = `<div class="log-entry">[${time}] ${msg}</div>` + area.innerHTML;
    // Keep last 50 entries
    const entries = area.querySelectorAll('.log-entry');
    if (entries.length > 50) entries[entries.length - 1].remove();
}

// =============== Init ===============
async function init() {
    await openDB();
    updateConnectionStatus();
    updateBatteryUI();
    updatePendingCount();
    addLog('App initialized. Staff ID: ' + STAFF_ID);

    // Periodic sync attempt (every 60s)
    setInterval(async () => {
        if (navigator.onLine && isTracking) await syncToServer();
    }, 60000);
}

// Register Service Worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(BASE_URL + '/sw-tracker.js')
        .then(() => addLog('Service Worker registered'))
        .catch(err => addLog('SW error: ' + err.message));
}

init();
</script>
</body>
</html>
