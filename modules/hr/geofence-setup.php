<?php
/**
 * Geofence Setup Page
 * Create, edit, and manage geofenced areas on a map
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$map_settings = [];
$settings_rows = db_query("SELECT setting_key, setting_value FROM livemap_settings");
foreach ($settings_rows ?: [] as $row) {
    $map_settings[$row['setting_key']] = $row['setting_value'];
}

$default_lat = $map_settings['map_default_lat'] ?? '23.8103';
$default_lng = $map_settings['map_default_lng'] ?? '90.4125';

$page_title = 'Geofence / Area Setup';
include __DIR__ . '/../../templates/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    #geoMap { height: 450px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
    .geo-card { border-left: 4px solid #4e73df; transition: all 0.2s; cursor: pointer; }
    .geo-card:hover { transform: translateX(4px); box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .geo-card.type-client { border-left-color: #28a745; }
    .geo-card.type-office { border-left-color: #4e73df; }
    .geo-card.type-zone { border-left-color: #ffc107; }
    .geo-inactive { opacity: 0.5; }
</style>

<div class="container-fluid">
    <div class="row">
        <!-- Map -->
        <div class="col-lg-8 mb-3">
            <div class="card shadow">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-draw-polygon"></i> Map — Click to add</h6>
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" id="mapSearchInput" class="form-control" placeholder="Search location...">
                        <button class="btn btn-primary" type="button" id="mapSearchBtn" title="Search"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="geoMap"></div>
                </div>
            </div>
        </div>

        <!-- Form & List -->
        <div class="col-lg-4 mb-3">
            <!-- Add/Edit Form -->
            <div class="card shadow mb-3">
                <div class="card-header py-2">
                    <h6 class="m-0 font-weight-bold text-primary" id="formTitle"><i class="fas fa-plus-circle"></i> Add Geofence</h6>
                </div>
                <div class="card-body">
                    <form id="geofenceForm">
                        <input type="hidden" id="geoId" value="0">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Name *</label>
                            <input type="text" id="geoName" class="form-control form-control-sm" required placeholder="e.g. Rahim Store">
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Latitude</label>
                                <input type="text" id="geoLat" class="form-control form-control-sm" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Longitude</label>
                                <input type="text" id="geoLng" class="form-control form-control-sm" readonly>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Radius (meters)</label>
                            <input type="range" id="geoRadius" class="form-range" min="10" max="500" value="50">
                            <div class="text-center small text-muted"><span id="radiusDisplay">50</span>m</div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Type</label>
                            <select id="geoType" class="form-select form-select-sm">
                                <option value="client">Client / Shop</option>
                                <option value="office">Office</option>
                                <option value="zone">Zone / Area</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Address</label>
                            <input type="text" id="geoAddress" class="form-control form-control-sm" placeholder="Optional address">
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Contact Person</label>
                                <input type="text" id="geoContact" class="form-control form-control-sm">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Phone</label>
                                <input type="text" id="geoPhone" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-save"></i> Save</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="resetForm()"><i class="fas fa-times"></i> Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Geofence List -->
            <div class="card shadow">
                <div class="card-header py-2">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Geofences (<span id="geoCount">0</span>)</h6>
                </div>
                <div class="card-body p-0" id="geoListContainer" style="max-height: 350px; overflow-y: auto;">
                    <div class="text-center py-3 text-muted">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const BASE = '<?= BASE_URL ?>';
const map = L.map('geoMap').setView([<?= $default_lat ?>, <?= $default_lng ?>], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap', maxZoom: 19
}).addTo(map);

let geoCircles = {};
let geoMarkers = {};
let previewCircle = null;
let previewMarker = null;

// Radius slider
document.getElementById('geoRadius').addEventListener('input', function() {
    document.getElementById('radiusDisplay').textContent = this.value;
    if (previewCircle) previewCircle.setRadius(parseInt(this.value));
});

// Map click to place geofence
map.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(8);
    const lng = e.latlng.lng.toFixed(8);
    document.getElementById('geoLat').value = lat;
    document.getElementById('geoLng').value = lng;
    
    const radius = parseInt(document.getElementById('geoRadius').value);
    
    if (previewCircle) map.removeLayer(previewCircle);
    if (previewMarker) map.removeLayer(previewMarker);
    
    previewCircle = L.circle([lat, lng], { radius, color: '#4e73df', fillOpacity: 0.15, weight: 2, dashArray: '5,5' }).addTo(map);
    previewMarker = L.marker([lat, lng]).addTo(map);
});

// Location Search
document.getElementById('mapSearchBtn').addEventListener('click', searchLocation);
document.getElementById('mapSearchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchLocation();
    }
});

async function searchLocation() {
    const query = document.getElementById('mapSearchInput').value.trim();
    if (!query) return;
    
    const btn = document.getElementById('mapSearchBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const resp = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`);
        const data = await resp.json();
        
        if (data && data.length > 0) {
            const lat = parseFloat(data[0].lat);
            const lon = parseFloat(data[0].lon);
            const displayName = data[0].display_name;
            
            map.setView([lat, lon], 16);
            
            // Show a temporary marker with popup to make it clear
            L.marker([lat, lon]).addTo(map)
             .bindPopup(`<b>Searched Location:</b><br>${displayName}<br><i>Click anywhere near here to set geofence</i>`)
             .openPopup();
        } else {
            alert('Location not found. Try variations like city or area name.');
        }
    } catch (err) {
        console.error(err);
        alert('Search request failed. Please check internet connection.');
    }
    
    btn.innerHTML = '<i class="fas fa-search"></i>';
}

// Form submit
document.getElementById('geofenceForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        id: document.getElementById('geoId').value,
        name: document.getElementById('geoName').value,
        latitude: document.getElementById('geoLat').value,
        longitude: document.getElementById('geoLng').value,
        radius: document.getElementById('geoRadius').value,
        type: document.getElementById('geoType').value,
        address: document.getElementById('geoAddress').value,
        contact_person: document.getElementById('geoContact').value,
        contact_phone: document.getElementById('geoPhone').value,
        is_active: 1
    };
    
    if (!data.latitude || !data.longitude) { alert('Click on the map to set location first'); return; }
    
    try {
        const resp = await fetch(BASE + '/api/livemap/geofence-crud.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
        });
        const result = await resp.json();
        if (result.success) {
            resetForm();
            loadGeofences();
        } else {
            alert(result.message);
        }
    } catch (err) { alert('Error saving geofence'); }
});

function resetForm() {
    document.getElementById('geoId').value = 0;
    document.getElementById('geoName').value = '';
    document.getElementById('geoLat').value = '';
    document.getElementById('geoLng').value = '';
    document.getElementById('geoRadius').value = 50;
    document.getElementById('radiusDisplay').textContent = '50';
    document.getElementById('geoType').value = 'client';
    document.getElementById('geoAddress').value = '';
    document.getElementById('geoContact').value = '';
    document.getElementById('geoPhone').value = '';
    document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add Geofence';
    if (previewCircle) { map.removeLayer(previewCircle); previewCircle = null; }
    if (previewMarker) { map.removeLayer(previewMarker); previewMarker = null; }
}

function editGeofence(geo) {
    document.getElementById('geoId').value = geo.id;
    document.getElementById('geoName').value = geo.name;
    document.getElementById('geoLat').value = geo.latitude;
    document.getElementById('geoLng').value = geo.longitude;
    document.getElementById('geoRadius').value = geo.radius;
    document.getElementById('radiusDisplay').textContent = geo.radius;
    document.getElementById('geoType').value = geo.type;
    document.getElementById('geoAddress').value = geo.address || '';
    document.getElementById('geoContact').value = geo.contact_person || '';
    document.getElementById('geoPhone').value = geo.contact_phone || '';
    document.getElementById('formTitle').innerHTML = '<i class="fas fa-edit"></i> Edit: ' + geo.name;
    
    map.setView([geo.latitude, geo.longitude], 16);
    
    if (previewCircle) map.removeLayer(previewCircle);
    if (previewMarker) map.removeLayer(previewMarker);
    previewCircle = L.circle([geo.latitude, geo.longitude], { radius: parseInt(geo.radius), color: '#ffc107', fillOpacity: 0.2, weight: 2, dashArray: '5,5' }).addTo(map);
    previewMarker = L.marker([geo.latitude, geo.longitude]).addTo(map);
}

async function deleteGeofence(id, name) {
    if (!confirm('Delete geofence "' + name + '"?')) return;
    try {
        const resp = await fetch(BASE + '/api/livemap/geofence-crud.php?id=' + id, { method: 'DELETE' });
        const result = await resp.json();
        if (result.success) loadGeofences();
        else alert(result.message);
    } catch (err) { alert('Error deleting geofence'); }
}

function focusGeofence(id) {
    if (geoCircles[id]) {
        map.fitBounds(geoCircles[id].getBounds(), { padding: [30,30] });
    }
}

async function loadGeofences() {
    // Clear existing
    Object.values(geoCircles).forEach(c => map.removeLayer(c));
    Object.values(geoMarkers).forEach(m => map.removeLayer(m));
    geoCircles = {};
    geoMarkers = {};

    try {
        const resp = await fetch(BASE + '/api/livemap/geofence-crud.php');
        const data = await resp.json();
        if (!data.success) return;

        document.getElementById('geoCount').textContent = data.geofences.length;
        
        const colors = { client: '#28a745', office: '#4e73df', zone: '#ffc107' };
        
        // Draw on map
        data.geofences.forEach(g => {
            const color = colors[g.type] || '#4e73df';
            geoCircles[g.id] = L.circle([g.latitude, g.longitude], {
                radius: parseInt(g.radius), color, fillOpacity: g.is_active == 1 ? 0.15 : 0.05, weight: 2
            }).addTo(map).bindPopup(`<b>${g.name}</b><br>${g.address || ''}<br>Radius: ${g.radius}m<br>Visits: ${g.visit_count}`);
        });

        // Build list
        const container = document.getElementById('geoListContainer');
        if (data.geofences.length === 0) {
            container.innerHTML = '<div class="text-center py-3 text-muted">No geofences yet. Click on the map to create one.</div>';
            return;
        }

        let html = '';
        data.geofences.forEach(g => {
            const typeIcons = { client: '🏪', office: '🏢', zone: '📍' };
            const icon = typeIcons[g.type] || '📍';
            html += `<div class="geo-card p-2 mx-2 my-2 rounded type-${g.type} ${g.is_active != 1 ? 'geo-inactive' : ''}" onclick="focusGeofence(${g.id})">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold small">${icon} ${g.name}</div>
                        <div class="text-muted" style="font-size:0.7rem;">${g.address || 'No address'} • ${g.radius}m radius</div>
                        <div class="text-muted" style="font-size:0.7rem;">Visits: ${g.visit_count}</div>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary btn-sm px-2" onclick="event.stopPropagation();editGeofence(${JSON.stringify(g).replace(/"/g, '&quot;')})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-danger btn-sm px-2" onclick="event.stopPropagation();deleteGeofence(${g.id},'${g.name}')"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>`;
        });
        container.innerHTML = html;

    } catch (err) { console.error(err); }
}

loadGeofences();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
