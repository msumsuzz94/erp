<?php
/**
 * Live Tracking Page
 * Real-time map showing all staff member positions
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get departments and roles for filter
$departments = db_query("SELECT id, name FROM staff_departments ORDER BY name");
$roles = db_query("SELECT id, role_name FROM staff_roles ORDER BY role_name");

// Get map settings
$map_settings = [];
$settings_rows = db_query("SELECT setting_key, setting_value FROM livemap_settings");
foreach ($settings_rows ?: [] as $row) {
    $map_settings[$row['setting_key']] = $row['setting_value'];
}

$default_lat = $map_settings['map_default_lat'] ?? '23.8103';
$default_lng = $map_settings['map_default_lng'] ?? '90.4125';
$default_zoom = $map_settings['map_default_zoom'] ?? '13';
$refresh_interval = intval($map_settings['tracking_interval_seconds'] ?? 30) * 1000;

$page_title = 'Live Tracking';
include __DIR__ . '/../../templates/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    #liveMap { height: calc(100vh - 200px); min-height: 500px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
    .staff-marker-popup { min-width: 220px; }
    .staff-marker-popup .popup-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
    .staff-marker-popup .popup-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; }
    .staff-marker-popup .popup-name { font-weight: 700; font-size: 1rem; }
    .staff-marker-popup .popup-role { color: #666; font-size: 0.8rem; }
    .staff-marker-popup .popup-detail { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px solid #eee; font-size: 0.85rem; }
    .staff-marker-popup .popup-detail:last-child { border-bottom: none; }
    .battery-icon { font-size: 1rem; }
    .battery-good { color: #28a745; }
    .battery-medium { color: #ffc107; }
    .battery-low { color: #dc3545; }
    .status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: 600; }
    .status-online { background: rgba(40,167,69,0.15); color: #28a745; }
    .status-offline { background: rgba(220,53,69,0.15); color: #dc3545; }
    .status-no_data { background: rgba(108,117,125,0.15); color: #6c757d; }
    .summary-card { border-left: 4px solid; border-radius: 8px; }
    .filter-bar { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 10px; padding: 12px 16px; }
    .staff-list-sidebar { max-height: calc(100vh - 260px); overflow-y: auto; }
    .staff-list-item { cursor: pointer; transition: all 0.2s; border-left: 3px solid transparent; }
    .staff-list-item:hover { background: rgba(78,115,223,0.05); border-left-color: var(--primary-color); }
</style>

<div class="container-fluid">
    <!-- Summary Cards -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-2">
            <div class="card summary-card shadow-sm h-100 py-2" style="border-left-color: #28a745;">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color:#28a745;">Online</div>
                            <div class="h4 mb-0 font-weight-bold" id="countOnline">0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-signal fa-2x" style="color:#28a74533;"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-2">
            <div class="card summary-card shadow-sm h-100 py-2" style="border-left-color: #dc3545;">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color:#dc3545;">Offline</div>
                            <div class="h4 mb-0 font-weight-bold" id="countOffline">0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-wifi-slash fa-2x" style="color:#dc354533;"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-2">
            <div class="card summary-card shadow-sm h-100 py-2" style="border-left-color: #6c757d;">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color:#6c757d;">No Data</div>
                            <div class="h4 mb-0 font-weight-bold" id="countNoData">0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-question-circle fa-2x" style="color:#6c757d33;"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-2">
            <div class="card summary-card shadow-sm h-100 py-2" style="border-left-color: #4e73df;">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1" style="color:#4e73df;">Total Staff</div>
                            <div class="h4 mb-0 font-weight-bold" id="countTotal">0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x" style="color:#4e73df33;"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Map -->
        <div class="col-lg-9 mb-3">
            <!-- Filter Bar -->
            <div class="filter-bar shadow-sm mb-3 d-flex flex-wrap align-items-center gap-3">
                <div>
                    <select id="filterDept" class="form-select form-select-sm" style="min-width:150px;">
                        <option value="">All Departments</option>
                        <?php foreach ($departments ?: [] as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <select id="filterRole" class="form-select form-select-sm" style="min-width:150px;">
                        <option value="">All Roles</option>
                        <?php foreach ($roles ?: [] as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <span class="badge bg-success" id="refreshBadge"><i class="fas fa-sync-alt fa-spin"></i> Live</span>
                    <small class="text-muted" id="lastUpdate">Updating...</small>
                </div>
            </div>

            <div id="liveMap"></div>
        </div>

        <!-- Staff List Sidebar -->
        <div class="col-lg-3 mb-3">
            <div class="card shadow">
                <div class="card-header py-2">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Staff List</h6>
                </div>
                <div class="card-body p-0 staff-list-sidebar" id="staffListSidebar">
                    <div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const BASE = '<?= BASE_URL ?>';
const DEFAULT_LAT = <?= $default_lat ?>;
const DEFAULT_LNG = <?= $default_lng ?>;
const DEFAULT_ZOOM = <?= $default_zoom ?>;
const REFRESH_MS = <?= $refresh_interval ?>;

// Initialize map
const map = L.map('liveMap').setView([DEFAULT_LAT, DEFAULT_LNG], DEFAULT_ZOOM);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

let markers = {};

function getBatteryIcon(level) {
    if (level === null) return '<i class="fas fa-battery-empty text-muted"></i>';
    if (level >= 60) return `<i class="fas fa-battery-full battery-good"></i> ${level}%`;
    if (level >= 30) return `<i class="fas fa-battery-half battery-medium"></i> ${level}%`;
    return `<i class="fas fa-battery-quarter battery-low"></i> ${level}%`;
}

function getStatusBadge(status) {
    const labels = { online: 'Online', offline: 'Offline', no_data: 'No Data' };
    return `<span class="status-badge status-${status}">${labels[status]}</span>`;
}

function getMarkerColor(status) {
    if (status === 'online') return '#28a745';
    if (status === 'offline') return '#dc3545';
    return '#6c757d';
}

function createMarkerIcon(color, name) {
    const initial = name.charAt(0).toUpperCase();
    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="background:${color};width:36px;height:36px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:14px;">${initial}</div>`,
        iconSize: [36, 36],
        iconAnchor: [18, 18],
        popupAnchor: [0, -20]
    });
}

function buildPopup(p) {
    const ago = p.minutes_ago !== null ? (p.minutes_ago < 1 ? 'Just now' : p.minutes_ago + ' min ago') : 'N/A';
    const speed = p.speed ? p.speed.toFixed(1) + ' km/h' : '-';
    const charging = p.is_charging ? ' ⚡' : '';
    
    return `<div class="staff-marker-popup">
        <div class="popup-header">
            <div class="popup-avatar">${p.name.charAt(0)}</div>
            <div>
                <div class="popup-name">${p.name}</div>
                <div class="popup-role">${p.designation || p.role || ''}</div>
            </div>
        </div>
        <div class="popup-detail"><span>Status</span> ${getStatusBadge(p.status)}</div>
        <div class="popup-detail"><span>📍 Location</span> <span>${p.latitude?.toFixed(5)}, ${p.longitude?.toFixed(5)}</span></div>
        <div class="popup-detail"><span>🔋 Battery</span> <span>${getBatteryIcon(p.battery_level)}${charging}</span></div>
        <div class="popup-detail"><span>🏃 Speed</span> <span>${speed}</span></div>
        <div class="popup-detail"><span>🕐 Last Update</span> <span>${ago}</span></div>
        <div class="mt-2 text-center">
            <a href="route-history.php?staff_id=${p.staff_id}&date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-route"></i> View Route
            </a>
        </div>
    </div>`;
}

function buildStaffList(positions) {
    const container = document.getElementById('staffListSidebar');
    if (!positions.length) {
        container.innerHTML = '<div class="text-center py-3 text-muted">No staff data</div>';
        return;
    }
    
    let html = '';
    positions.forEach(p => {
        const statusDot = p.status === 'online' ? '🟢' : (p.status === 'offline' ? '🔴' : '⚫');
        const battery = p.battery_level !== null ? `🔋${p.battery_level}%` : '';
        const ago = p.minutes_ago !== null ? (p.minutes_ago < 1 ? 'now' : p.minutes_ago + 'm') : '-';
        
        html += `<div class="staff-list-item px-3 py-2 border-bottom" onclick="focusStaff(${p.staff_id})">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <strong class="small">${statusDot} ${p.name}</strong>
                    <div class="text-muted" style="font-size:0.7rem;">${p.designation || p.role || '-'}</div>
                </div>
                <div class="text-end" style="font-size:0.7rem;">
                    <div>${battery}</div>
                    <div class="text-muted">${ago}</div>
                </div>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function focusStaff(staffId) {
    if (markers[staffId]) {
        map.setView(markers[staffId].getLatLng(), 17);
        markers[staffId].openPopup();
    }
}

async function loadPositions() {
    const dept = document.getElementById('filterDept').value;
    const role = document.getElementById('filterRole').value;
    let url = BASE + '/api/livemap/get-live-positions.php?_=' + Date.now();
    if (dept) url += '&department_id=' + dept;
    if (role) url += '&role_id=' + role;

    try {
        const resp = await fetch(url);
        const data = await resp.json();
        
        if (!data.success) return;

        // Update counters
        document.getElementById('countOnline').textContent = data.summary.online;
        document.getElementById('countOffline').textContent = data.summary.offline;
        document.getElementById('countNoData').textContent = data.summary.no_data;
        document.getElementById('countTotal').textContent = data.summary.total;
        document.getElementById('lastUpdate').textContent = 'Updated: ' + new Date().toLocaleTimeString('en-US');

        // Update markers
        const activeIds = new Set();
        
        data.positions.forEach(p => {
            if (p.latitude === null || p.longitude === null) return;
            
            activeIds.add(p.staff_id);
            const color = getMarkerColor(p.status);
            const icon = createMarkerIcon(color, p.name);
            
            if (markers[p.staff_id]) {
                markers[p.staff_id].setLatLng([p.latitude, p.longitude]);
                markers[p.staff_id].setIcon(icon);
                markers[p.staff_id].setPopupContent(buildPopup(p));
            } else {
                markers[p.staff_id] = L.marker([p.latitude, p.longitude], { icon })
                    .addTo(map)
                    .bindPopup(buildPopup(p));
            }
        });

        // Remove markers for staff no longer tracked
        Object.keys(markers).forEach(id => {
            if (!activeIds.has(parseInt(id))) {
                map.removeLayer(markers[id]);
                delete markers[id];
            }
        });

        // Build sidebar
        buildStaffList(data.positions);

    } catch (err) {
        console.error('Failed to load positions:', err);
    }
}

// Initial load and auto-refresh
loadPositions();
setInterval(loadPositions, REFRESH_MS);

// Filter change handlers
document.getElementById('filterDept').addEventListener('change', loadPositions);
document.getElementById('filterRole').addEventListener('change', loadPositions);
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
