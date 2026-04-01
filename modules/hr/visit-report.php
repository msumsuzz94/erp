<?php
/**
 * Visit & Time Report Page
 * Shows which staff visited which geofence and for how long
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$staff_list = db_query("SELECT id, name FROM staff WHERE status = 'active' ORDER BY name");

$page_title = 'Visit & Time Report';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .report-stat { border-left: 4px solid; border-radius: 8px; }
    .visit-timeline { font-size: 0.85rem; }
    .staff-summary-card { transition: all 0.2s; cursor: pointer; }
    .staff-summary-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
</style>

<div class="container-fluid">
    <!-- Filters -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row align-items-end g-3">
                <div class="col-md-2">
                    <label class="form-label fw-bold"><i class="fas fa-filter"></i> Period</label>
                    <select id="filterPeriod" class="form-select form-select-sm" onchange="toggleCustomDate()">
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month" selected>This Month</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="col-md-2" id="customFrom" style="display:none;">
                    <label class="form-label fw-bold">From</label>
                    <input type="date" id="dateFrom" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-md-2" id="customTo" style="display:none;">
                    <label class="form-label fw-bold">To</label>
                    <input type="date" id="dateTo" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold"><i class="fas fa-user"></i> Staff</label>
                    <select id="filterStaff" class="form-select form-select-sm">
                        <option value="">All Staff</option>
                        <?php foreach ($staff_list ?: [] as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm w-100" onclick="loadReport()">
                        <i class="fas fa-search"></i> Generate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-3" id="summaryRow" style="display:none;">
        <div class="col-md-3 mb-2">
            <div class="card report-stat shadow-sm py-2" style="border-left-color:#4e73df;">
                <div class="card-body py-2 text-center">
                    <div class="small text-muted">Total Visits</div>
                    <div class="h3 mb-0 fw-bold text-primary" id="statVisits">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card report-stat shadow-sm py-2" style="border-left-color:#28a745;">
                <div class="card-body py-2 text-center">
                    <div class="small text-muted">Active Staff</div>
                    <div class="h3 mb-0 fw-bold text-success" id="statStaff">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card report-stat shadow-sm py-2" style="border-left-color:#ffc107;">
                <div class="card-body py-2 text-center">
                    <div class="small text-muted">Period</div>
                    <div class="h5 mb-0 fw-bold text-warning" id="statPeriod">-</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card report-stat shadow-sm py-2" style="border-left-color:#e74a3b;">
                <div class="card-body py-2 text-center">
                    <div class="small text-muted">Report Time</div>
                    <div class="h5 mb-0 fw-bold text-danger" id="statTime">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Staff Summary Cards -->
        <div class="col-lg-4 mb-3">
            <div class="card shadow">
                <div class="card-header py-2">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users"></i> Staff Summary</h6>
                </div>
                <div class="card-body p-0" id="staffSummaryContainer" style="max-height: 500px; overflow-y: auto;">
                    <div class="text-center py-4 text-muted">Click "Generate" to load report</div>
                </div>
            </div>
        </div>

        <!-- Visit Details Table -->
        <div class="col-lg-8 mb-3">
            <div class="card shadow">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-clipboard-list"></i> Visit Details</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm visit-timeline" id="visitTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff</th>
                                    <th>Location</th>
                                    <th>Type</th>
                                    <th>Entry</th>
                                    <th>Exit</th>
                                    <th>Duration</th>
                                </tr>
                            </thead>
                            <tbody id="visitTableBody">
                                <tr><td colspan="6" class="text-center text-muted">No data</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= BASE_URL ?>';

function toggleCustomDate() {
    const isCustom = document.getElementById('filterPeriod').value === 'custom';
    document.getElementById('customFrom').style.display = isCustom ? '' : 'none';
    document.getElementById('customTo').style.display = isCustom ? '' : 'none';
}

async function loadReport() {
    const period = document.getElementById('filterPeriod').value;
    const staffId = document.getElementById('filterStaff').value;
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;

    let url = `${BASE}/api/livemap/visit-report.php?period=${period}`;
    if (staffId) url += `&staff_id=${staffId}`;
    if (period === 'custom') url += `&from=${from}&to=${to}`;

    try {
        const resp = await fetch(url);
        const data = await resp.json();
        if (!data.success) return;

        // Stats
        document.getElementById('summaryRow').style.display = '';
        document.getElementById('statVisits').textContent = data.totals.total_visits;
        document.getElementById('statStaff').textContent = data.totals.total_staff;
        document.getElementById('statPeriod').textContent = data.from + ' → ' + data.to;
        document.getElementById('statTime').textContent = new Date().toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});

        // Staff Summary
        const sc = document.getElementById('staffSummaryContainer');
        if (data.staff_summary.length === 0) {
            sc.innerHTML = '<div class="text-center py-4 text-muted">No visit data found</div>';
        } else {
            let html = '';
            data.staff_summary.forEach(s => {
                const hours = Math.floor(s.total_minutes / 60);
                const mins = s.total_minutes % 60;
                html += `<div class="staff-summary-card p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold">${s.staff_name}</div>
                            <div class="text-muted small">${s.designation || ''}</div>
                        </div>
                        <div class="text-end">
                            <div class="badge bg-primary">${s.total_visits} visits</div>
                            <div class="small text-muted">${s.unique_locations} locations</div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mt-2 small">
                        <span>⏱️ ${hours}h ${mins}m</span>
                        <span>🚗 ${s.travel_km || 0} km</span>
                    </div>
                </div>`;
            });
            sc.innerHTML = html;
        }

        // Update table content
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#visitTable')) {
            $('#visitTable').DataTable().destroy();
        }

        const tbody = document.getElementById('visitTableBody');
        if (data.visits.length === 0) {
            tbody.innerHTML = '';
        } else {
            let html = '';
            data.visits.forEach(v => {
                const typeColors = { client: 'success', office: 'primary', zone: 'warning' };
                const entry = new Date(v.entered_at).toLocaleString('en-US', {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
                const exit = v.exited_at ? new Date(v.exited_at).toLocaleString('en-US', {hour:'2-digit', minute:'2-digit'}) : '<span class="text-warning">Ongoing</span>';
                const dur = v.duration_minutes ? `${v.duration_minutes} min` : '-';
                
                html += `<tr>
                    <td><strong>${v.staff_name}</strong></td>
                    <td>${v.geofence_name}<br><small class="text-muted">${v.geofence_address || ''}</small></td>
                    <td><span class="badge bg-${typeColors[v.geofence_type] || 'secondary'}">${v.geofence_type}</span></td>
                    <td>${entry}</td>
                    <td>${exit}</td>
                    <td><span class="badge bg-info">${dur}</span></td>
                </tr>`;
            });
            tbody.innerHTML = html;
        }

        // Init DataTable
        if ($.fn.DataTable) {
            $('#visitTable').DataTable({ order: [[3, 'desc']], pageLength: 25 });
        }

    } catch (err) {
        console.error(err);
        alert('Failed to load report');
    }
}

// Auto load
document.addEventListener('DOMContentLoaded', loadReport);
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
