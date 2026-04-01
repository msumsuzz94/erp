<?php

/**
 * Attendance Devices Management
 * Register and manage fingerprint/card readers
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('hr.attendance-devices');

// Get all devices
$devices = db_select('attendance_devices', [], '*', 'created_at DESC');

// Calculate online status
foreach ($devices as &$device) {
    if ($device['last_heartbeat']) {
        $last_heartbeat = strtotime($device['last_heartbeat']);
        $now = time();
        $device['is_online'] = ($now - $last_heartbeat) < 300; // Online if heartbeat within 5 minutes
    } else {
        $device['is_online'] = false;
    }
}

$page_title = 'Attendance Devices';
$page_actions = '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                    <i class="fas fa-plus"></i> Add Device
                </button>';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Registered Devices</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered datatable">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Device Name</th>
                        <th>Type</th>
                        <th>Serial Number</th>
                        <th>Location</th>
                        <th>IP Address</th>
                        <th>Last Heartbeat</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $device): ?>
                        <tr>
                            <td class="text-center">
                                <?php if ($device['is_online']): ?>
                                    <span class="badge bg-success" title="Online"><i class="fas fa-circle"></i> Online</span>
                                <?php else: ?>
                                    <span class="badge bg-danger" title="Offline"><i class="fas fa-circle"></i> Offline</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fas fa-<?= $device['device_type'] === 'fingerprint' ? 'fingerprint' : ($device['device_type'] === 'card' ? 'id-card' : 'face-smile') ?>"></i>
                                <?= htmlspecialchars($device['device_name']) ?>
                            </td>
                            <td><?= ucfirst($device['device_type']) ?></td>
                            <td><code><?= htmlspecialchars($device['device_sn']) ?></code></td>
                            <td><?= htmlspecialchars($device['location'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($device['device_ip'] ?? '-') ?></td>
                            <td>
                                <?php if ($device['last_heartbeat']): ?>
                                    <small><?= timeago($device['last_heartbeat']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Never</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewDevice(<?= $device['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-primary" onclick="showAPIKey(<?= $device['id'] ?>)">
                                    <i class="fas fa-key"></i> API Key
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editDevice(<?= $device['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteDevice(<?= $device['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Device Modal -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addDeviceForm">
                    <div class="mb-3">
                        <label class="form-label">Device Name <span class="text-danger">*</span></label>
                        <input type="text" name="device_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Device Type <span class="text-danger">*</span></label>
                        <select name="device_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="fingerprint">Fingerprint Scanner</option>
                            <option value="card">Card Reader (RFID/NFC)</option>
                            <option value="face">Face Recognition</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                        <input type="text" name="device_sn" class="form-control" required>
                        <small class="text-muted">Unique identifier from device</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g., Main Gate, Office Floor 1">
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">IP Address</label>
                                <input type="text" name="device_ip" class="form-control" placeholder="192.168.1.100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Port</label>
                                <input type="number" name="device_port" class="form-control" placeholder="80">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveDevice()">
                    <i class="fas fa-save"></i> Save Device
                </button>
            </div>
        </div>
    </div>
</div>

<!-- API Key Modal -->
<div class="modal fade" id="apiKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Device API Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Keep this key secure! It's used to authenticate the device.
                </div>
                <div class="mb-3">
                    <label class="form-label">API Endpoint:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="apiEndpoint" readonly value="<?= BASE_URL ?>/api/attendance/punch.php">
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('apiEndpoint')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">API Key:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="apiKeyValue" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('apiKeyValue')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div id="deviceSN" class="mb-3">
                    <label class="form-label">Device Serial Number:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="deviceSNValue" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('deviceSNValue')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    function saveDevice() {
        const form = document.getElementById('addDeviceForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        fetch('<?= BASE_URL ?>/api/attendance/register-device.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showAlert('Device registered successfully! API Key: ' + result.api_key, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert(result.message, 'error');
                }
            })
            .catch(error => showAlert('Error: ' + error.message, 'error'));
    }

    function showAPIKey(deviceId) {
        fetch('<?= BASE_URL ?>/api/attendance/register-device.php')
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    const device = result.devices.find(d => d.id == deviceId);
                    if (device) {
                        document.getElementById('apiKeyValue').value = device.api_key;
                        document.getElementById('deviceSNValue').value = device.device_sn;
                        new bootstrap.Modal(document.getElementById('apiKeyModal')).show();
                    }
                }
            });
    }

    function copyToClipboard(elementId) {
        const input = document.getElementById(elementId);
        input.select();
        document.execCommand('copy');
        showAlert('Copied to clipboard!', 'success');
    }

    function deleteDevice(deviceId) {
        if (!confirm('Are you sure you want to delete this device?')) return;

        fetch('<?= BASE_URL ?>/api/attendance/register-device.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    device_id: deviceId
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showAlert('Device deleted successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message, 'error');
                }
            });
    }

    function editDevice(deviceId) {
        // TODO: Implement edit functionality
        showAlert('Edit functionality coming soon!', 'info');
    }

    function viewDevice(deviceId) {
        // TODO: Implement view details
        showAlert('View details coming soon!', 'info');
    }
</script>