<?php
/**
 * Route History Page
 * View staff member's GPS trail on map for a specific date
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$staff_list = db_query("SELECT id, name, designation FROM staff WHERE status = 'active' ORDER BY name");
$selected_staff = intval($_GET['staff_id'] ?? 0);
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Map settings
$map_settings = [];
$settings_rows = db_query("SELECT setting_key, setting_value FROM livemap_settings");
foreach ($settings_rows ?: [] as $row) {
    $map_settings[$row['setting_key']] = $row['setting_value'];
}

$default_lat = $map_settings['map_default_lat'] ?? '23.8103';
$default_lng = $map_settings['map_default_lng'] ?? '90.4125';

$page_title = 'Route History';
include __DIR__ . '/../../templates/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    #routeMap { height: calc(100vh - 280px); min-height: 450px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
    .timeline-item { position: relative; padding-left: 30px; padding-bottom: 15px; border-left: 2px solid #e0e0e0; }
    .timeline-item:last-child { border-left: 2px solid transparent; }
    .timeline-item::before { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #4e73df; border: 2px solid #fff; box-shadow: 0 0 0 2px #4e73df; }
    .timeline-item:first-child::before { background: #28a745; box-shadow: 0 0 0 2px #28a745; }
    .timeline-item:last-child::before { background: #dc3545; box-shadow: 0 0 0 2px #dc3545; }
    .stat-badge { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #4e73df15, #4e73df05); border: 1px solid #4e73df30; padding: 8px 16px; border-radius: 8px; font-weight: 600; }
    .visit-card { border-left: 4px solid #28a745; transition: all 0.2s; }
    .visit-card:hover { transform: translateX(4px); }
    .halt-card { border-left: 4px solid #f6c23e; background: #fffdf5; padding: 10px; margin-bottom: 8px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .assign-card { border-left: 4px solid #858796; padding: 10px; margin-bottom: 8px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .assign-card.visited { border-left-color: #1cc88a; background: #f3fdf8; }
    .assign-card.missed { border-left-color: #e74a3b; background: #fdf3f3; }
</style>

<div class="container-fluid">
    <!-- Filters -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row align-items-end g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold"><i class="fas fa-user"></i> Staff Member</label>
                    <select id="staffSelect" class="form-select">
                        <option value="">-- Select Staff --</option>
                        <?php foreach ($staff_list ?: [] as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_staff ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?> <?= $s['designation'] ? "({$s['designation']})" : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Date</label>
                    <input type="date" id="dateSelect" class="form-control" value="<?= $selected_date ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="loadRoute()">
                        <i class="fas fa-search"></i> View Route
                    </button>
                </div>
                <div class="col-md-3 text-end">
                    <div class="stat-badge" id="distanceBadge" style="display:none;">
                        <i class="fas fa-road text-primary"></i> <span id="totalDistance">0</span> km
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Map -->
        <div class="col-lg-8 mb-3">
            <div id="routeMap"></div>
        </div>

        <!-- Timeline & Visits -->
        <div class="col-lg-4 mb-3">
            <!-- Stats -->
            <div class="row mb-3" id="statsRow" style="display:none;">
                <div class="col-6">
                    <div class="card shadow-sm text-center py-2">
                        <div class="small text-muted">Points</div>
                        <div class="h5 mb-0 fw-bold text-primary" id="totalPoints">0</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card shadow-sm text-center py-2">
                        <div class="small text-muted">Duration</div>
                        <div class="h5 mb-0 fw-bold text-success" id="totalDuration">-</div>
                    </div>
                </div>
            </div>

            <!-- Automated Halts -->
            <div class="card shadow mb-3 border-left-warning">
                <div class="card-header py-2 d-flex justify-content-between">
                    <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-hand-paper"></i> Detected Stops (>10m)</h6>
                    <span class="badge bg-warning text-dark" id="haltCount">0</span>
                </div>
                <div class="card-body p-2" id="haltsContainer" style="max-height: 250px; overflow-y: auto;">
                    <p class="text-muted text-center py-2 mb-0" style="font-size: 0.85rem;">Select staff & date to analyze stops</p>
                </div>
            </div>

            <!-- Assigned Route Validation -->
            <div class="card shadow mb-3 border-left-info">
                <div class="card-header py-2 d-flex justify-content-between">
                    <h6 class="m-0 font-weight-bold text-info"><i class="fas fa-tasks"></i> Assigned Route</h6>
                    <span class="badge bg-info" id="assignCount">0</span>
                </div>
                <div class="card-body p-2" id="assignedContainer" style="max-height: 250px; overflow-y: auto;">
                    <p class="text-muted text-center py-2 mb-0" style="font-size: 0.85rem;">No route assigned for this day</p>
                </div>
            </div>

            <!-- Raw Timeline -->
            <div class="card shadow">
                <div class="card-header py-2">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-clock"></i> Raw GPS Timeline</h6>
                </div>
                <div class="card-body" id="timelineContainer" style="max-height: 200px; overflow-y: auto;">
                    <p class="text-muted text-center mb-0" style="font-size: 0.85rem;">Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const BASE = '<?= BASE_URL ?>';
const map = L.map('routeMap').setView([<?= $default_lat ?>, <?= $default_lng ?>], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap', maxZoom: 19
}).addTo(map);

let routeLine = null;
let routeMarkers = [];

function clearRoute() {
    if (routeLine) { map.removeLayer(routeLine); routeLine = null; }
    routeMarkers.forEach(m => map.removeLayer(m));
    routeMarkers = [];
}

async function loadRoute() {
    const staffId = document.getElementById('staffSelect').value;
    const date = document.getElementById('dateSelect').value;
    
    if (!staffId) { alert('Please select a staff member'); return; }

    clearRoute();

    try {
        const resp = await fetch(`${BASE}/api/livemap/get-route-history.php?staff_id=${staffId}&date=${date}`);
        const data = await resp.json();

        if (!data.success) { alert(data.message); return; }

        // Stats
        document.getElementById('totalDistance').textContent = data.total_distance_km;
        document.getElementById('totalPoints').textContent = data.total_points;
        document.getElementById('distanceBadge').style.display = data.total_points > 0 ? '' : 'none';
        document.getElementById('statsRow').style.display = data.total_points > 0 ? '' : 'none';

        if (data.first_record && data.last_record) {
            const start = new Date(data.first_record);
            const end = new Date(data.last_record);
            const hours = Math.floor((end - start) / 3600000);
            const mins = Math.floor(((end - start) % 3600000) / 60000);
            document.getElementById('totalDuration').textContent = `${hours}h ${mins}m`;
        }

        // Draw route
        if (data.trail.length > 0) {
            // Gradient route line
            routeLine = L.polyline(data.trail, {
                color: '#4e73df', weight: 4, opacity: 0.8,
                dashArray: null, lineJoin: 'round'
            }).addTo(map);

            // Start marker (green)
            const startIcon = L.divIcon({
                className: '', html: '<div style="background:#28a745;width:20px;height:20px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
                iconSize: [20,20], iconAnchor: [10,10]
            });
            routeMarkers.push(L.marker(data.trail[0], {icon: startIcon}).addTo(map).bindPopup(`<b>Start</b><br>${data.first_record}`));

            // End marker (red)
            const endIcon = L.divIcon({
                className: '', html: '<div style="background:#dc3545;width:20px;height:20px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
                iconSize: [20,20], iconAnchor: [10,10]
            });
            const lastPoint = data.trail[data.trail.length - 1];
            routeMarkers.push(L.marker(lastPoint, {icon: endIcon}).addTo(map).bindPopup(`<b>Last Known</b><br>${data.last_record}`));

            map.fitBounds(routeLine.getBounds(), { padding: [30,30] });
        }

        // Timeline
        const tc = document.getElementById('timelineContainer');
        if (data.timeline.length > 0) {
            let html = '';
            data.timeline.forEach(t => {
                const battery = t.battery ? `🔋${t.battery}%` : '';
                const speed = t.speed ? `🏃${parseFloat(t.speed).toFixed(1)} km/h` : '';
                html += `<div class="timeline-item">
                    <div class="fw-bold">${t.time} ${t.label ? `<span class="badge bg-primary">${t.label}</span>` : ''}</div>
                    <div class="text-muted small">${battery} ${speed}</div>
                </div>`;
            });
            tc.innerHTML = html;
        } else {
            tc.innerHTML = '<p class="text-muted text-center">No tracking data for this date</p>';
        }

        // Fetch Halt Analytics & Assigned Route Match
        try {
            const haltResp = await fetch(`${BASE}/api/livemap/halt-report.php?staff_id=${staffId}&date=${date}`);
            const haltData = await haltResp.json();
            
            // Draw Halts on Map
            const hc = document.getElementById('haltsContainer');
            if (haltData.success && haltData.halts.length > 0) {
                document.getElementById('haltCount').textContent = haltData.halts.length;
                let hHtml = '';
                
                const haltIcon = L.divIcon({
                    className: '', html: '<div style="background:#f6c23e;width:24px;height:24px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 6px rgba(0,0,0,0.5);text-align:center;line-height:20px;color:white;font-size:10px;"><i class="fas fa-hand-paper"></i></div>',
                    iconSize: [24,24], iconAnchor: [12,12]
                });

                haltData.halts.forEach((h, i) => {
                    // Marker
                    routeMarkers.push(L.marker([h.lat, h.lng], {icon: haltIcon}).addTo(map)
                        .bindPopup(`<b>Stop ${i+1}</b><br>${h.address}<br>From: ${h.start_time} to ${h.end_time}<br>Duration: ${h.duration} mins`));
                    
                    // List
                    hHtml += `
                    <div class="halt-card" onclick="map.setView([${h.lat}, ${h.lng}], 17)">
                        <div class="fw-bold text-warning" style="font-size:0.9rem;">Stop ${i+1} <span class="badge bg-warning text-dark float-end">${h.duration} min</span></div>
                        <div class="small fw-bold">${h.start_time} - ${h.end_time}</div>
                        <div class="text-muted" style="font-size:0.8rem;"><i class="fas fa-map-marker-alt"></i> ${h.address}</div>
                    </div>`;
                });
                hc.innerHTML = hHtml;
            } else {
                document.getElementById('haltCount').textContent = '0';
                hc.innerHTML = '<p class="text-muted text-center py-2 mb-0" style="font-size:0.85rem;">No long stops detected</p>';
            }

            // Draw Assigned Route Match
            const ac = document.getElementById('assignedContainer');
            if (haltData.success && haltData.assigned_route && haltData.assigned_route.length > 0) {
                document.getElementById('assignCount').textContent = haltData.assigned_route.length;
                let aHtml = '';
                
                haltData.assigned_route.forEach(ar => {
                    const isV = ar.status === 'Visited';
                    aHtml += `
                    <div class="assign-card ${isV ? 'visited' : 'missed'}" onclick="map.setView([${ar.lat}, ${ar.lng}], 16)">
                        <div class="d-flex justify-content-between">
                            <strong style="font-size:0.9rem;">${ar.name}</strong>
                            <span class="badge ${isV ? 'bg-success' : 'bg-danger'}">${ar.status}</span>
                        </div>
                        <div class="text-muted mt-1" style="font-size:0.8rem;">
                            ${isV ? `<i class="fas fa-check-circle text-success"></i> Visited at ${ar.visit_time} (${ar.duration} min)` : `<i class="fas fa-times-circle text-danger"></i> Not visited`}
                        </div>
                    </div>`;
                });
                ac.innerHTML = aHtml;
            } else {
                document.getElementById('assignCount').textContent = '0';
                ac.innerHTML = '<p class="text-muted text-center py-2 mb-0" style="font-size:0.85rem;">No route assigned for this day</p>';
            }
            
        } catch(e) { console.error('Halt API error', e); }

    } catch (err) {
        console.error(err);
        alert('Failed to load route data');
    }
}

// Auto-load if staff_id is in URL
<?php if ($selected_staff > 0): ?>
document.addEventListener('DOMContentLoaded', loadRoute);
<?php endif; ?>
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
