<?php
/**
 * Create Marketing Campaign
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_once __DIR__ . '/../../includes/permissions.php';

if (!is_admin() && !has_role(get_current_user_id(), 'Manager')) {
    redirect_with_message('../../index.php', 'Insufficient permissions.', 'danger');
}

$templates = db_query("SELECT id, name, channel FROM marketing_templates WHERE status = 'active' ORDER BY name ASC");
$lead_categories = db_query("SELECT id, name FROM lead_categories WHERE status = 'active' ORDER BY name ASC");

$page_title = 'Create Campaign';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Start New Campaign</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info border-left-info">
                    <i class="fas fa-info-circle"></i> Once a campaign starts, it will process in the background. Please do not close the window immediately after clicking Send.
                </div>
                
                <form id="campaignForm">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="form-group mb-3">
                        <label>Campaign Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. Eid SMS Campaign 2024">
                    </div>
                    
                    <div class="form-group mb-4">
                        <label>Select Template <span class="text-danger">*</span></label>
                        <select name="template_id" id="template_id" class="form-control select2" required>
                            <option value="">Choose a Template</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= $t['id'] ?>" data-channel="<?= $t['channel'] ?>">
                                    <?= htmlspecialchars($t['name']) ?> [<?= strtoupper($t['channel']) ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <hr>
                    <h6 class="font-weight-bold text-gray-800 mb-3">Target Audience</h6>
                    
                    <div class="form-group mb-3">
                        <label>Target Group <span class="text-danger">*</span></label>
                        <select name="target_type" id="target_type" class="form-control" required>
                            <option value="all_customers">All Customers</option>
                            <option value="lead_category">Specific Lead Category</option>
                            <option value="custom">Custom Filter (Current Balance / Location)</option>
                        </select>
                    </div>
                    
                    <div id="leadCategoryGroup" class="form-group mb-3" style="display:none;">
                        <label>Select Lead Category <span class="text-danger">*</span></label>
                        <select name="target_category_id" id="target_category_id" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($lead_categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="customGroup" class="row mb-3 p-3 bg-light border rounded" style="display:none;">
                        <div class="col-md-6 form-group">
                            <label>Minimum Due Balance</label>
                            <input type="number" name="min_due" id="min_due" class="form-control" value="0" step="0.01">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Include Customers without Phone?</label>
                            <select name="include_no_phone" id="include_no_phone" class="form-control">
                                <option value="no">No (Skip them)</option>
                                <option value="yes">Yes</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Analytics pre-check -->
                    <div class="mb-4">
                        <button type="button" class="btn btn-outline-info w-100" id="calcAudienceBtn">
                            <i class="fas fa-calculator"></i> Calculate Audience Size
                        </button>
                        <div id="audienceResult" class="mt-2 font-weight-bold text-success" style="display:none;">
                            Estimated Recipients: <span id="recipientCount">0</span>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-primary btn-lg w-100" id="startCampaignBtn" disabled>
                        <i class="fas fa-paper-plane"></i> Start Campaign
                    </button>
                    
                    <div class="progress mt-3" style="height: 25px; display: none;" id="campaignProgressBox">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="campaignProgressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                    <div id="campaignStatusText" class="text-center mt-1 text-muted" style="display:none;">Initializing...</div>
                    
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap-5' });
    
    $('#target_type').change(function() {
        if ($(this).val() === 'lead_category') {
            $('#leadCategoryGroup').slideDown();
            $('#customGroup').slideUp();
        } else if ($(this).val() === 'custom') {
            $('#leadCategoryGroup').slideUp();
            $('#customGroup').slideDown();
        } else {
            $('#leadCategoryGroup').slideUp();
            $('#customGroup').slideUp();
        }
        
        // Reset calculation when target changes
        $('#audienceResult').hide();
        $('#startCampaignBtn').prop('disabled', true);
    });
    
    // Simulate calculating audience via API (We'll just add the API handler next)
    $('#calcAudienceBtn').click(function() {
        const payload = {
            target_type: $('#target_type').val(),
            target_category_id: $('#target_category_id').val(),
            min_due: $('#min_due').val(),
            channel: $('#template_id option:selected').data('channel') || 'sms'
        };
        
        const btn = $(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i> Calculating...');
        
        // API Call to get audience count
        $.get('../../api/marketing/audience.php', payload, function(res) {
            if (res.status) {
                $('#recipientCount').text(res.count);
                $('#audienceResult').slideDown();
                
                if (res.count > 0 && $('#template_id').val() && $('#name').val()) {
                    $('#startCampaignBtn').prop('disabled', false);
                } else {
                    $('#startCampaignBtn').prop('disabled', true);
                    if (res.count === 0) alert('No recipients found for this target.');
                }
            } else {
                alert('Error calculating audience: ' + res.message);
            }
        }).fail(function() {
            alert('Failed to connect to server.');
        }).always(function() {
            btn.html('<i class="fas fa-calculator"></i> Calculate Audience Size');
        });
    });
    
    // Check if ready to send repeatedly just in case
    $('#name, #template_id').on('change keyup', function() {
        if ($('#recipientCount').text() !== "0" && $('#recipientCount').text() !== "" && $('#name').val() && $('#template_id').val()) {
            $('#startCampaignBtn').prop('disabled', false);
        } else {
            $('#startCampaignBtn').prop('disabled', true);
        }
    });

    $('#startCampaignBtn').click(function() {
        if (!confirm('Are you sure you want to start this campaign now? Depending on the audience size, this might take a while.')) return;
        
        $(this).prop('disabled', true);
        $('#calcAudienceBtn').prop('disabled', true);
        $('#campaignProgressBox, #campaignStatusText').show();
        
        const payload = {
            csrf_token: $('#csrf_token').val(),
            name: $('#name').val(),
            template_id: $('#template_id').val(),
            target_type: $('#target_type').val(),
            target_category_id: $('#target_category_id').val(),
            min_due: $('#min_due').val()
        };
        
        // Begin Campaign Trigger
        $.post('../../api/marketing/send.php', payload, function(res) {
            // Because PHP execution might timeout, real-world we'd queue it.
            // For now, assume it's synchronous or sets up a batch.
            if (res.status) {
                $('#campaignProgressBar').css('width', '100%').text('100%').removeClass('progress-bar-animated').addClass('bg-success');
                $('#campaignStatusText').text('Campaign completed! Redirecting...');
                setTimeout(() => {
                    window.location.href = 'campaign-view.php?id=' + res.campaign_id;
                }, 1500);
            } else {
                $('#campaignStatusText').html('<span class="text-danger">Failed: ' + res.message + '</span>');
                $('#campaignProgressBar').removeClass('progress-bar-animated').addClass('bg-danger');
                $('#startCampaignBtn').prop('disabled', false);
            }
        }, 'json').fail(function() {
            $('#campaignStatusText').html('<span class="text-danger">Network or server error during send.</span>');
            $('#startCampaignBtn').prop('disabled', false);
        });
    });
});
</script>
