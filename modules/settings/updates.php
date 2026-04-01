<?php
/**
 * OTA Auto Updates Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if (!is_admin()) {
    redirect_with_message('../../index.php', 'Insufficient permissions.', 'danger');
}

$history = db_query("SELECT u.*, us.username 
                     FROM system_updates u 
                     LEFT JOIN users us ON u.applied_by = us.id 
                     ORDER BY applied_at DESC LIMIT 10");

$page_title = 'System Updates (OTA)';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow mb-4 border-left-primary">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-sync-alt"></i> Software Update</h6>
            </div>
            <div class="card-body text-center py-5">
                <h5 class="text-gray-800 font-weight-bold mb-3">Current Version: v<?= APP_VERSION ?></h5>
                <p class="text-muted mb-4">Check for the latest stability patches and features.</p>

                <button id="checkUpdateBtn" class="btn btn-primary btn-lg px-4 rounded-pill">
                    <i class="fas fa-search"></i> Check for Updates
                </button>
                
                <div id="loadingBox" class="mt-4" style="display:none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted" id="loadingText">Connecting to update server...</p>
                </div>
                
                <div id="updateInfoBox" class="mt-4 text-left p-3 border rounded bg-light" style="display:none;">
                    <h6 class="font-weight-bold text-success mb-2"><i class="fas fa-check-circle"></i> NEW UPDATE AVAILABLE!</h6>
                    <h6><strong>Version:</strong> <span id="newVer" class="badge bg-primary"></span></h6>
                    
                    <strong class="d-block mt-3 mb-1">What's new:</strong>
                    <div id="changelog" class="text-muted small p-2 border bg-white" style="max-height: 150px; overflow-y:auto;"></div>
                    
                    <form id="updateForm" class="mt-4">
                        <input type="hidden" name="csrf_token" id="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" id="verValue">
                        <input type="hidden" id="urlValue">
                        
                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle"></i> Do not close your browser or turn off the server during the update process.
                        </div>
                        <button type="submit" class="btn btn-success w-100 font-weight-bold" id="applyUpdateBtn">
                            <i class="fas fa-download"></i> Download & Install Update
                        </button>
                    </form>
                </div>
                
                <div id="upToDateBox" class="mt-4" style="display:none;">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                        <strong>You are on the latest version!</strong>
                    </div>
                </div>
                
                <div id="errorBox" class="mt-4 text-danger font-weight-bold" style="display:none;"></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Update History</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Version</th>
                                <th>Status</th>
                                <th>Applied By</th>
                                <th>Logs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No prior updates recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td><?= date('d M Y, h:i A', strtotime($h['applied_at'])) ?></td>
                                        <td><strong><?= htmlspecialchars($h['version']) ?></strong></td>
                                        <td>
                                            <?php if ($h['status'] === 'success'): ?>
                                                <span class="badge bg-success">Success</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($h['username'] ?: 'System') ?></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($h['log'] ?? '') ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#checkUpdateBtn').click(function() {
        $(this).hide();
        $('#loadingBox').slideDown();
        $('#updateInfoBox, #upToDateBox, #errorBox').hide();
        
        $.get('../../api/settings/check-update.php', function(res) {
            $('#loadingBox').hide();
            
            if (res.status) {
                if (res.update_available) {
                    $('#newVer').text('v' + res.new_version);
                    $('#changelog').html(res.changelog);
                    $('#verValue').val(res.new_version);
                    $('#urlValue').val(res.data.download_url);
                    $('#updateInfoBox').slideDown();
                } else {
                    $('#upToDateBox').slideDown();
                    $('#checkUpdateBtn').show().text('Check Again');
                }
            } else {
                $('#errorBox').text(res.message).slideDown();
                $('#checkUpdateBtn').show().text('Check Again');
            }
        }).fail(function() {
            $('#loadingBox').hide();
            $('#errorBox').text('Network error checking for updates.').slideDown();
            $('#checkUpdateBtn').show().text('Try Again');
        });
    });
    
    $('#updateForm').submit(function(e) {
        e.preventDefault();
        if (!confirm('Proceeding with update. System might be temporarily unresponsive. Ensure a backup exists. Continue?')) return;
        
        const btn = $('#applyUpdateBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Installing...');
        
        const payload = {
            csrf_token: $('#csrf_token').val(),
            version: $('#verValue').val(),
            download_url: $('#urlValue').val()
        };
        
        $.post('../../api/settings/apply-update.php', payload, function(res) {
            if (res.status) {
                btn.html('<i class="fas fa-check"></i> Update Complete!').removeClass('btn-success').addClass('btn-primary');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                btn.prop('disabled', false).html('<i class="fas fa-download"></i> Download & Install Update');
                alert('Update Failed: ' + res.message);
            }
        }).fail(function() {
            btn.prop('disabled', false).html('<i class="fas fa-download"></i> Download & Install Update');
            alert('Server error occurred during installation.');
        });
    });
});
</script>
