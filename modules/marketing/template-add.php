<?php
/**
 * Add Marketing Template
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

if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $name = clean_input($_POST['name']);
        $channel = clean_input($_POST['channel']);
        $subject = clean_input($_POST['subject'] ?? '');
        $body = clean_input($_POST['body']);
        $status = clean_input($_POST['status'] ?? 'active');

        $errors = [];
        if (empty($name)) $errors[] = "Template name is required.";
        if (empty($body)) $errors[] = "Message body is required.";
        if (empty($channel)) $errors[] = "Channel is required.";
        if ($channel === 'email' && empty($subject)) $errors[] = "Subject is required for Email templates.";

        if (empty($errors)) {
            // Find variables in body (e.g., {name}, {balance})
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $body, $matches);
            $variables = array_unique($matches[1]);

            db_insert('marketing_templates', [
                'name' => $name,
                'channel' => $channel,
                'subject' => ($channel === 'email') ? $subject : null,
                'body' => $body,
                'variables' => json_encode(array_values($variables)),
                'status' => $status
            ]);
            
            redirect_with_message('templates.php', 'Template created successfully', 'success');
        }
    }
}

$page_title = 'Add Template';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">New Marketing Template</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="form-group mb-3">
                        <label>Template Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Eid Discount SMS">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 form-group">
                            <label>Communication Channel <span class="text-danger">*</span></label>
                            <select name="channel" id="channelSelect" class="form-control" required>
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="telegram">Telegram</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3" id="subjectGroup" style="display:none;">
                        <label>Email Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" id="subjectInput" class="form-control" placeholder="Promotional Offer Inside!">
                    </div>
                    
                    <div class="form-group mb-2">
                        <label>Message Body <span class="text-danger">*</span></label>
                        <textarea name="body" id="bodyInput" class="form-control" rows="6" required></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <small class="text-muted">
                            <strong>Available Variables (Dynamic Data):</strong><br>
                            <button type="button" class="btn btn-sm btn-outline-secondary insert-var" data-var="{name}">Name</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary insert-var" data-var="{store_name}">Store Name</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary insert-var" data-var="{balance}">Due Balance</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary insert-var" data-var="{phone}">Phone</button>
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg"><i class="fas fa-save"></i> Save Template</button>
                    <a href="templates.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#channelSelect').change(function() {
        if ($(this).val() === 'email') {
            $('#subjectGroup').slideDown();
            $('#subjectInput').prop('required', true);
        } else {
            $('#subjectGroup').slideUp();
            $('#subjectInput').prop('required', false);
        }
    });
    
    $('.insert-var').click(function() {
        const textToInsert = $(this).data('var');
        const input = document.getElementById('bodyInput');
        const startPos = input.selectionStart;
        const endPos = input.selectionEnd;
        
        input.value = input.value.substring(0, startPos)
            + textToInsert
            + input.value.substring(endPos, input.value.length);
            
        // Move cursor past the inserted variable
        input.focus();
        input.selectionStart = startPos + textToInsert.length;
        input.selectionEnd = startPos + textToInsert.length;
    });
});
</script>
