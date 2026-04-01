<?php
/**
 * Attendance Logs Viewer
 * Real-time attendance punch logs from devices
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Get filter parameters
$selected_date = get_param('date', date('Y-m-d'));
$staff_id = get_param('staff_id', '');
$device_id = get_param('device_id', '');
$status_filter = get_param('status', '');

// Get all staff for filter dropdown
$all_staff = db_select('staff', ['status' => 'active'], '*', 'name ASC');

// Get all devices for filter dropdown
$all_devices = db_select('attendance_devices', [], '*', 'device_name ASC');

// Get invoice settings for print header
$invoice_settings = db_select_one('invoice_settings', ['id' => 1]);
if (!$invoice_settings) {
    $invoice_settings = [
        'company_name' => defined('BUSINESS_NAME') ? BUSINESS_NAME : '',
        'company_address' => defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : '',
        'company_phone' => defined('BUSINESS_PHONE') ? BUSINESS_PHONE : '',
        'company_email' => defined('BUSINESS_EMAIL') ? BUSINESS_EMAIL : '',
        'company_website' => '',
        'company_logo' => 'assets/images/logo.png',
        'company_slogan' => ''
    ];
}

$page_title = 'Attendance Logs';
$page_actions = '<button class="btn btn-success" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="btn btn-info" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>';
include __DIR__ . '/../../templates/header.php';
?>

<style>
/* Print Area Styles (Hidden on Screen) */
#print-area { display: none; padding: 20px; background: #fff; color: #000; }

@media print {
    @page { margin: 0.25cm; size: portrait; } /* Portrait might be better for attendance, but user said 'Date, list, footer'. I'll use landscape to match other reports */
    @page { margin: 0.25cm; size: landscape; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; width: 100% !important; max-width: 100% !important; color: #000 !important; }
    
    .btn, .sidebar, #sidebar, .topbar, .navbar, .card-header, .no-print, form, #standard-print-wrapper, #standard-print-footer, footer, .footer, .summary-cards, 
    #statisticsCards { display: none !important; }
    
    /* Strong overrides for global print.css padding */
    body #content, body .container-fluid, body .wrapper { 
        padding: 0 !important; 
        margin: 0 !important; 
        width: 100% !important; 
        max-width: 100% !important; 
        box-sizing: border-box !important; 
        display: block !important;
    }
    
    .card { border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
    .card-body { padding: 0 !important; }
    
    .table-responsive { overflow: visible !important; width: 100% !important; margin: 0 !important; }
    .table, .print-table { width: 100% !important; max-width: 100% !important; border-collapse: collapse !important; margin-bottom: 20px !important; }
    
    /* Hard override table styles */
    body .table-bordered th, body .table-bordered td, .table th, .table td, .print-table th, .print-table td { 
        border: 1px solid #000 !important; 
        padding: 4px 6px !important; 
        color: #000 !important; 
        font-size: 13px !important; 
    }
    body .table thead th, .table th, .print-table th { 
        background-color: #e0e0e0 !important; 
        font-weight: bold !important; 
        font-size: 14px !important; 
        text-transform: uppercase !important; 
        color: #000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Hide specific filters */
    .row.mb-4:not(#statisticsCards) .col-md-3:nth-child(2),
    .row.mb-4:not(#statisticsCards) .col-md-3:nth-child(3),
    .row.mb-4:not(#statisticsCards) .col-md-3:nth-child(4) { display: none !important; }
    
    /* Style the Date filter to look like text in print */
    .row.mb-4:not(#statisticsCards) .col-md-3:nth-child(1) { width: 100% !important; margin-bottom: 15px !important; display: block !important; }
    .row.mb-4:not(#statisticsCards) .col-md-3:nth-child(1) label { display: inline-block !important; font-weight: bold; margin-right: 10px; color: #000 !important; }
    .row.mb-4:not(#statisticsCards) .col-md-3:nth-child(1) input { display: inline-block !important; border: 0 !important; padding: 0 !important; outline: none; background: transparent; font-size: 16px; font-weight: bold; color: #000 !important; width: 200px; }
    
    /* Show print-specific area */
    #print-area { display: block !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    #print-area * { box-sizing: border-box !important; color: #000 !important; }
    
    .print-header-table, .header-table { width: 100% !important; border-collapse: collapse; margin-bottom: 15px; font-family: "Segoe UI", Arial, sans-serif; font-size: 13px; }
    .print-header-table td, .header-table td { vertical-align: top; border: none !important; padding: 0 !important; }
    .logo-cell { width: 10%; text-align: left; }
    .logo-cell img { max-width: 90px; height: auto; display: block; }
    .title-cell { width: 65%; text-align: center; padding: 0 10px; }
    .company-name { font-size: 26px; font-weight: 900; color: #000; margin: 0; text-transform: uppercase; border-bottom: 2px solid #000; display: inline-block; line-height: 1.2; padding-bottom: 4px; }
    .company-slogan { font-size: 14px; color: #000; font-weight: bold; margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-cell { width: 25%; text-align: left; line-height: 1.5; font-size: 12px; border: 1px solid #000 !important; padding: 6px 10px !important; }
    .info-cell p { margin: 0; margin-bottom: 3px; font-weight: bold; }
    .report-main-title { text-align: center; font-size: 20px; color: #000; margin: 15px 0; font-weight: bold; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 8px 0; text-transform: uppercase; }
    
    .print-footer { 
        position: fixed; bottom: 0.25cm; left: 0; right: 0; border-top: 2px solid #000 !important; padding-top: 10px !important; 
        display: block !important; width: 100% !important; font-size: 12px !important; background: #fff !important; z-index: 9999; color: #000 !important;
    }
    .footer-left { float: left; width: 50%; font-weight: bold; text-align: left; padding-left: 10px; }
    .footer-right { float: right; width: 50%; text-align: right; padding-right: 10px; }
    .clearfix::after { content: ""; clear: both; display: table; }

    /* Hide Actions column in table */
    .table th:nth-child(10), .table td:nth-child(10) { display: none !important; }
}
</style>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-clock"></i> Real-Time Attendance Logs
            <span class="badge bg-info ms-2" id="autoRefreshStatus">Auto-refresh: ON</span>
        </h6>
    </div>
    <div class="card-body">
        
        <!-- Print Header -->
        <div id="print-area">
            <table class="print-header-table">
                <tr>
                    <td class="logo-cell">
                        <?php if(!empty($invoice_settings['company_logo']) && file_exists(__DIR__ . '/../../' . $invoice_settings['company_logo'])): ?>
                            <img src="<?= BASE_URL ?>/<?= $invoice_settings['company_logo'] ?>" alt="Logo">
                        <?php endif; ?>
                    </td>
                    <td class="title-cell">
                        <h2 class="company-name"><?= htmlspecialchars($invoice_settings['company_name']) ?></h2>
                        <?php if(!empty($invoice_settings['company_slogan'])): ?>
                            <div class="company-slogan"><?= htmlspecialchars($invoice_settings['company_slogan']) ?></div>
                        <?php endif; ?>
                        <div class="report-main-title">Attendance Logs</div>
                    </td>
                    <td class="info-cell">
                        <p><?= nl2br(htmlspecialchars($invoice_settings['company_address'])) ?></p>
                        <p>Phone: <?= htmlspecialchars($invoice_settings['company_phone']) ?></p>
                        <p>Email: <?= htmlspecialchars($invoice_settings['company_email']) ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" id="filterDate" class="form-control" value="<?= $selected_date ?>" onchange="applyFilters()">
            </div>
            <div class="col-md-3">
                <label class="form-label">Staff</label>
                <select id="filterStaff" class="form-select" onchange="applyFilters()">
                    <option value="">All Staff</option>
                    <?php foreach ($all_staff as $staff): ?>
                        <option value="<?= $staff['id'] ?>" <?= $staff_id == $staff['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($staff['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Device</label>
                <select id="filterDevice" class="form-select" onchange="applyFilters()">
                    <option value="">All Devices</option>
                    <?php foreach ($all_devices as $device): ?>
                        <option value="<?= $device['id'] ?>" <?= $device_id == $device['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($device['device_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select id="filterStatus" class="form-select" onchange="applyFilters()">
                    <option value="">All Status</option>
                    <option value="valid" <?= $status_filter === 'valid' ? 'selected' : '' ?>>Valid</option>
                    <option value="duplicate" <?= $status_filter === 'duplicate' ? 'selected' : '' ?>>Duplicate</option>
                    <option value="invalid" <?= $status_filter === 'invalid' ? 'selected' : '' ?>>Invalid</option>
                </select>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4" id="statisticsCards">
            <div class="col-md-3">
                <div class="card border-left-primary">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Punches</div>
                        <div class="h5 mb-0 font-weight-bold" id="statTotalPunches">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-success">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Unique Staff</div>
                        <div class="h5 mb-0 font-weight-bold" id="statUniqueStaff">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-info">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Valid Punches</div>
                        <div class="h5 mb-0 font-weight-bold" id="statValidPunches">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-left-warning">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Duplicates</div>
                        <div class="h5 mb-0 font-weight-bold" id="statDuplicates">0</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="logsTable">
                <thead class="table-light">
                    <tr>
                        <th>Time</th>
                        <th>Staff</th>
                        <th>Designation</th>
                        <th>Device</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Temperature</th>
                        <th>Status</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody">
                    <tr>
                        <td colspan="10" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Print Footer -->
        <div class="print-footer">
            <div class="footer-left">
                Printed by: <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?><br>
                Print Time: <?= date('d M Y, h:i A') ?>
            </div>
            <div class="footer-right">
                <br>
                _______________________<br>
                Authorized Signature
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
let autoRefreshInterval = null;
let isAutoRefreshEnabled = true;

// Load logs on page load
$(document).ready(function() {
    loadLogs();
    startAutoRefresh();
});

function loadLogs() {
    const date = $('#filterDate').val();
    const staffId = $('#filterStaff').val();
    const deviceId = $('#filterDevice').val();
    const status = $('#filterStatus').val();
    
    const params = new URLSearchParams({
        date_from: date,
        date_to: date,
        ...(staffId && { staff_id: staffId }),
        ...(deviceId && { device_id: deviceId }),
        ...(status && { status: status })
    });
    
    fetch(`<?= BASE_URL ?>/api/attendance/get-logs.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatistics(data.statistics);
                renderLogs(data.logs);
            } else {
                showAlert('Failed to load logs', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading logs:', error);
            showAlert('Error loading logs', 'error');
        });
}

function updateStatistics(stats) {
    $('#statTotalPunches').text(stats.total_punches || 0);
    $('#statUniqueStaff').text(stats.unique_staff || 0);
    $('#statValidPunches').text(stats.valid_punches || 0);
    $('#statDuplicates').text(stats.duplicate_punches || 0);
}

function renderLogs(logs) {
    const tbody = $('#logsTableBody');
    
    if (logs.length === 0) {
        tbody.html('<tr><td colspan="10" class="text-center">No logs found for selected filters</td></tr>');
        return;
    }
    
    let html = '';
    logs.forEach(log => {
        const punchTime = new Date(log.punch_time);
        const timeStr = punchTime.toLocaleTimeString();
        
        let statusBadge = '';
        switch (log.status) {
            case 'valid':
                statusBadge = '<span class="badge bg-success">Valid</span>';
                break;
            case 'duplicate':
                statusBadge = '<span class="badge bg-warning">Duplicate</span>';
                break;
            case 'invalid':
                statusBadge = '<span class="badge bg-danger">Invalid</span>';
                break;
            default:
                statusBadge = '<span class="badge bg-secondary">' + log.status + '</span>';
        }
        
        let punchTypeBadge = '';
        switch (log.punch_type) {
            case 'in':
                punchTypeBadge = '<span class="badge bg-success"><i class="fas fa-sign-in-alt"></i> In</span>';
                break;
            case 'out':
                punchTypeBadge = '<span class="badge bg-danger"><i class="fas fa-sign-out-alt"></i> Out</span>';
                break;
            case 'out_duty':
                punchTypeBadge = '<span class="badge bg-warning text-dark"><i class="fas fa-briefcase"></i> Out (Duty)</span>';
                break;
            case 'return_duty':
                punchTypeBadge = '<span class="badge bg-info text-dark"><i class="fas fa-undo"></i> Return</span>';
                break;
            case 'break_out':
                punchTypeBadge = '<span class="badge bg-warning"><i class="fas fa-coffee"></i> Break</span>';
                break;
            case 'break_in':
                punchTypeBadge = '<span class="badge bg-success"><i class="fas fa-arrow-left"></i> Back</span>';
                break;
            default:
                punchTypeBadge = '<span class="badge bg-secondary">' + log.punch_type + '</span>';
        }
        
        const temperatureCell = log.temperature ? `${log.temperature}°C` : '-';
        const photoCell = log.photo_path ? `<a href="<?= BASE_URL ?>/${log.photo_path}" target="_blank"><i class="fas fa-image"></i></a>` : '-';
        
        html += `
            <tr>
                <td><strong>${timeStr}</strong><br><small class="text-muted">${log.punch_time.split(' ')[0]}</small></td>
                <td>${log.staff_name}<br><small class="text-muted">${log.employee_code || ''}</small></td>
                <td>${log.designation || '-'}</td>
                <td><i class="fas fa-${log.device_type === 'fingerprint' ? 'fingerprint' : 'id-card'}"></i> ${log.device_name}<br><small class="text-muted">${log.device_location || ''}</small></td>
                <td>${punchTypeBadge}</td>
                <td><span class="badge bg-secondary">${log.verification_method}</span></td>
                <td>${temperatureCell}</td>
                <td>${statusBadge}</td>
                <td>${photoCell}</td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="viewDetails(${log.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.html(html);
}

function applyFilters() {
    loadLogs();
}

function startAutoRefresh() {
    autoRefreshInterval = setInterval(() => {
        if (isAutoRefreshEnabled) {
            loadLogs();
        }
    }, 30000); // Refresh every 30 seconds
}

function toggleAutoRefresh() {
    isAutoRefreshEnabled = !isAutoRefreshEnabled;
    $('#autoRefreshStatus').text('Auto-refresh: ' + (isAutoRefreshEnabled ? 'ON' : 'OFF'));
}

function exportToExcel() {
    const date = $('#filterDate').val();
    window.location.href = `<?= BASE_URL ?>/api/attendance/export-logs.php?date=${date}`;
}

function viewDetails(logId) {
    // TODO: Implement view details modal
    showAlert('View details coming soon!', 'info');
}
</script>

<style>
.border-left-primary { border-left: 4px solid #4e73df !important; }
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-info { border-left: 4px solid #36b9cc !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }
</style>
