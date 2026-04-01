<?php
/**
 * Route Assignment Page
 * Assign specific locations/waypoints for a staff member to visit on a specific date.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get active staff
$staff_list = db_query("SELECT id, name, designation FROM staff WHERE status = 'active' ORDER BY name");

// Map Settings
$map_settings = [];
$settings_rows = db_query("SELECT setting_key, setting_value FROM livemap_settings");
foreach ($settings_rows ?: [] as $row) {
    $map_settings[$row['setting_key']] = $row['setting_value'];
}
$default_lat = $map_settings['map_default_lat'] ?? '23.8103';
$default_lng = $map_settings['map_default_lng'] ?? '90.4125';

$page_title = 'Assign Routes';
include __DIR__ . '/../../templates/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #assignMap { height: 500px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .waypoint-item { background: #f8f9fc; border-left: 3px solid #4e73df; padding: 10px; margin-bottom: 8px; border-radius: 4px; display: flex; align-items: center; justify-content: space-between; }
    .waypoint-number { background: #4e73df; color: #fff; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; margin-right: 10px; }
    .btn-remove-pt { color: #e74a3b; cursor: pointer; border: none; background: none; }
    .btn-remove-pt:hover { color: #c0392b; }
</style>

<div class="container-fluid">
    <div class="row">
        <!-- Configuration Panel -->
        <div class="col-lg-4 mb-3">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tasks"></i> Plan Route</h6>
                </div>
                <div class="card-body">
                    <form id="assignForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Select Date</label>
                            <input type="date" id="routeDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Select Staff</label>
                            <select id="staffId" class="form-select" required>
                                <option value="">-- Choose Staff --</option>
                                <?php foreach ($staff_list ?: [] as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['designation'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr>
                        <h6 class="fw-bold small mb-2 text-primary">Waypoints (Click map to add)</h6>
                        
                        <div id="waypointsContainer" style="max-height: 250px; overflow-y: auto; margin-bottom: 15px;">
                            <div class="text-center text-muted small py-3" id="emptyWaypoints">No points added yet. Use the map to add destinations.</div>
                        </div>

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-success" id="btnSaveRoute" disabled>
                                <i class="fas fa-save"></i> Save Assigned Route
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Map Panel -->
        <div class="col-lg-8 mb-3">
            <div class="card shadow">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-map"></i> Map Editor</h6>
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" id="mapSearchInput" class="form-control" placeholder="Search area...">
                        <button class="btn btn-primary" type="button" id="mapSearchBtn"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="assignMap"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Assignments List -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="card shadow mb-4 border-left-success">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-list"></i> Recent Assignments</h6>
                    <button class="btn btn-sm btn-outline-success" onclick="loadAllAssignments()"><i class="fas fa-sync-alt"></i></button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover w-100 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th width="15%">Date</th>
                                    <th width="35%">Staff Member</th>
                                    <th width="20%">Total Points</th>
                                    <th width="30%">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="assignmentsBody">
                                <tr><td colspan="4" class="text-center py-4">Loading list...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const BASE = '<?= BASE_URL ?>';
const map = L.map('assignMap').setView([<?= $default_lat ?>, <?= $default_lng ?>], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

let waypoints = [];
let routePolyline = null;
let markersLayer = L.layerGroup().addTo(map);
let searchMarker = null;

// Handle map click to add point
map.on('click', async function(e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;
    
    // Auto reverse geocode for name
    let ptName = `Point ${waypoints.length + 1}`;
    try {
        const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
        const data = await resp.json();
        if (data && data.display_name) {
            // Take first few parts of address
            ptName = data.display_name.split(',').slice(0, 2).join(',');
        }
    } catch (err) {}

    addWaypoint(lat, lng, ptName);
});

// Search Location logic
document.getElementById('mapSearchBtn').addEventListener('click', searchLocation);
document.getElementById('mapSearchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); searchLocation(); }
});

async function searchLocation() {
    const q = document.getElementById('mapSearchInput').value.trim();
    if (!q) return;
    try {
        const resp = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`);
        const data = await resp.json();
        if (data.length > 0) {
            const lat = parseFloat(data[0].lat);
            const lon = parseFloat(data[0].lon);
            map.setView([lat, lon], 16);
            if (searchMarker) map.removeLayer(searchMarker);
            searchMarker = L.marker([lat, lon]).addTo(map).bindPopup(data[0].display_name).openPopup();
        } else alert('Location not found');
    } catch(err) { alert('Search failed'); }
}

function addWaypoint(lat, lng, name, id = null) {
    waypoints.push({ id, lat, lng, name, radius: 50 });
    renderWaypoints();
    drawRoute();
}

function removeWaypoint(index) {
    waypoints.splice(index, 1);
    renderWaypoints();
    drawRoute();
}

function renderWaypoints() {
    const container = document.getElementById('waypointsContainer');
    const emptyMsg = document.getElementById('emptyWaypoints');
    const btnSave = document.getElementById('btnSaveRoute');
    
    // Clear list
    container.querySelectorAll('.waypoint-item').forEach(el => el.remove());
    
    if (waypoints.length === 0) {
        if(emptyMsg) emptyMsg.style.display = 'block';
        btnSave.disabled = true;
        return;
    }
    
    if(emptyMsg) emptyMsg.style.display = 'none';
    btnSave.disabled = false;
    
    let html = '';
    waypoints.forEach((wp, i) => {
        html += `<div class="waypoint-item flex-column align-items-stretch">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center" style="width: 90%;">
                    <div class="waypoint-number">${i + 1}</div>
                    <input type="text" class="form-control form-control-sm" value="${wp.name}" 
                           onchange="updateWaypointName(${i}, this.value)" placeholder="Spot name">
                </div>
                <button type="button" class="btn-remove-pt" onclick="removeWaypoint(${i})" title="Remove"><i class="fas fa-trash"></i></button>
            </div>
            <div class="d-flex align-items-center ps-4 pt-1">
                <label class="small text-muted me-2 mb-0" style="font-size: 0.75rem;">Area Radius (m):</label>
                <input type="number" class="form-control form-control-sm" style="width: 80px;" value="${wp.radius || 50}" 
                       onchange="updateWaypointRadius(${i}, this.value)" min="10" max="1000">
            </div>
        </div>`;
    });
    container.insertAdjacentHTML('beforeend', html);
}

function updateWaypointName(index, val) {
    if (waypoints[index]) waypoints[index].name = val;
}

function updateWaypointRadius(index, val) {
    if (waypoints[index]) {
        waypoints[index].radius = parseInt(val) || 50;
        drawRoute(); // Redraw map to show new circle size
    }
}

function drawRoute() {
    markersLayer.clearLayers();
    if (routePolyline) map.removeLayer(routePolyline);
    
    const latlngs = [];
    waypoints.forEach((wp, i) => {
        latlngs.push([wp.lat, wp.lng]);
        
        // Custom numbered marker icon
        const iconHtml = `<div style="background:#4e73df;color:white;border-radius:50%;width:24px;height:24px;text-align:center;line-height:24px;font-weight:bold;border:2px solid #fff;box-shadow:0 0 4px rgba(0,0,0,0.4);">${i+1}</div>`;
        const icon = L.divIcon({ html: iconHtml, className: '', iconSize: [24, 24], iconAnchor: [12, 12] });
        
        L.marker([wp.lat, wp.lng], { icon }).addTo(markersLayer).bindPopup(`<b>Point ${i+1}:</b> ${wp.name}`);
        
        // Draw acceptance radius circle
        L.circle([wp.lat, wp.lng], { radius: wp.radius || 50, color: '#4e73df', fillOpacity: 0.1, weight: 1, dashArray: '3,3' }).addTo(markersLayer);
    });
    
    if (latlngs.length > 1) {
        routePolyline = L.polyline(latlngs, { color: '#4e73df', weight: 3, dashArray: '5,5' }).addTo(map);
    }
}

// Load existing route on date/staff change
async function fetchExistingRoute() {
    const date = document.getElementById('routeDate').value;
    const staffId = document.getElementById('staffId').value;
    
    // Clear current
    waypoints = [];
    renderWaypoints();
    drawRoute();
    
    if (!date || !staffId) return;
    
    try {
        const resp = await fetch(`${BASE}/api/livemap/route-assignment.php?action=get&date=${date}&staff_id=${staffId}`);
        const data = await resp.json();
        
        if (data.success && data.routes.length > 0) {
            const route = data.routes[0];
            if (route.points && route.points.length > 0) {
                route.points.forEach(pt => {
                    waypoints.push({
                        id: pt.id,
                        lat: parseFloat(pt.latitude),
                        lng: parseFloat(pt.longitude),
                        name: pt.name,
                        radius: parseInt(pt.radius_meters)
                    });
                });
                renderWaypoints();
                drawRoute();
                // Zoom map to fit route bounds
                if (waypoints.length > 0) {
                    const group = new L.featureGroup(Object.values(markersLayer._layers));
                    map.fitBounds(group.getBounds(), { padding: [50, 50] });
                }
            }
        }
    } catch(err) { console.error('Error fetching route', err); }
}

document.getElementById('routeDate').addEventListener('change', fetchExistingRoute);
document.getElementById('staffId').addEventListener('change', fetchExistingRoute);

// Save Route
document.getElementById('assignForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (waypoints.length === 0) return;
    
    const staff_id = document.getElementById('staffId').value;
    const route_date = document.getElementById('routeDate').value;
    
    const payload = {
        staff_id, route_date,
        title: `Route for ${route_date}`,
        notes: '',
        points: waypoints.map(wp => ({ name: wp.name, latitude: wp.lat, longitude: wp.lng, radius: wp.radius }))
    };
    
    const btn = document.getElementById('btnSaveRoute');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;
    
    try {
        const resp = await fetch(`${BASE}/api/livemap/route-assignment.php?action=save`, {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const result = await resp.json();
        alert(result.message);
        loadAllAssignments(); // Refresh table
    } catch (err) {
        alert('Failed to save route. Check network.');
    }
    
    btn.innerHTML = '<i class="fas fa-save"></i> Save Assigned Route';
    btn.disabled = false;
});

// Load All Assignments List
async function loadAllAssignments() {
    const tbody = document.getElementById('assignmentsBody');
    try {
        const resp = await fetch(`${BASE}/api/livemap/route-assignment.php?action=list_all`);
        const data = await resp.json();
        
        if (data.success && data.routes.length > 0) {
            let html = '';
            data.routes.forEach(r => {
                const dtStr = new Date(r.route_date).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'});
                html += `<tr>
                    <td class="fw-bold">${dtStr}</td>
                    <td>${r.staff_name} <span class="text-muted small">(${r.designation})</span></td>
                    <td><span class="badge bg-info">${r.point_count} Points</span></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="editAssignment('${r.route_date}', ${r.staff_id})" title="Edit / View"><i class="fas fa-edit"></i> Edit</button>
                        <button class="btn btn-sm btn-danger ml-2" onclick="deleteAssignment(${r.id})" title="Delete"><i class="fas fa-trash"></i> Delete</button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No route assignments found.</td></tr>';
        }
    } catch(err) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Failed to load list.</td></tr>'; }
}

function editAssignment(date, staffId) {
    document.getElementById('routeDate').value = date;
    document.getElementById('staffId').value = staffId;
    fetchExistingRoute();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function deleteAssignment(id) {
    if(!confirm('Are you absolutely sure you want to completely delete this Assigned Route?')) return;
    try {
        const resp = await fetch(`${BASE}/api/livemap/route-assignment.php?id=${id}`, {method: 'DELETE'});
        const data = await resp.json();
        if(data.success) {
            loadAllAssignments();
            fetchExistingRoute(); // To clear map if current one was deleted
        } else alert(data.message);
    } catch(err) { alert('Network error deleting route'); }
}

// Initial Load
document.addEventListener('DOMContentLoaded', loadAllAssignments);

</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
