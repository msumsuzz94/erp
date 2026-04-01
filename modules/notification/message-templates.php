<?php
/**
 * Message Templates
 * CRUD for notification message templates with dynamic variables
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$success_message = '';
$error_message = '';

// Handle actions
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create' || $action === 'update') {
            $data = [
                'name' => clean_input($_POST['name']),
                'type' => clean_input($_POST['type']),
                'content' => $_POST['content'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            
            if (empty($data['name'])) {
                $error_message = 'Template name is required';
            } else {
                if ($action === 'create') {
                    db_insert('message_templates', $data);
                    $success_message = 'Template created successfully!';
                } else {
                    db_update('message_templates', $data, ['id' => (int)$_POST['template_id']]);
                    $success_message = 'Template updated successfully!';
                }
            }
        } elseif ($action === 'delete') {
            db_query("DELETE FROM message_templates WHERE id = ?", [(int)$_POST['template_id']]);
            $success_message = 'Template deleted!';
        }
    }
}

$templates = db_query("SELECT * FROM message_templates ORDER BY type, name");

$type_badges = [
    'sales' => ['bg-success', 'fa-shopping-cart'],
    'due' => ['bg-danger', 'fa-money-bill'],
    'service' => ['bg-info', 'fa-tools'],
    'warranty' => ['bg-warning text-dark', 'fa-shield-alt'],
    'greeting' => ['bg-primary', 'fa-gift'],
    'payment' => ['bg-success', 'fa-hand-holding-usd'],
    'custom' => ['bg-secondary', 'fa-edit'],
];

$page_title = 'Message Templates';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.template-card { border: 1px solid var(--border-color); border-radius: 10px; padding: 15px; margin-bottom: 12px; transition: all 0.2s; }
.template-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); transform: translateY(-1px); }
.template-content { background: var(--card-bg); border: 1px dashed var(--border-color); border-radius: 8px; padding: 10px; font-size: 13px; color: var(--text-color); max-height: 80px; overflow: hidden; }
.var-tag { display: inline-block; background: rgba(78,115,223,0.1); color: #4e73df; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-family: monospace; margin: 2px; cursor: pointer; }
.var-tag:hover { background: rgba(78,115,223,0.2); }
</style>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-file-alt text-primary"></i> Message Templates</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="openCreate()">
            <i class="fas fa-plus"></i> New Template
        </button>
    </div>

    <!-- Available Variables Reference -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-2">
            <strong><i class="fas fa-code"></i> Available Variables:</strong>
            <span class="var-tag" onclick="copyVar(this)">{customer_name}</span>
            <span class="var-tag" onclick="copyVar(this)">{invoice_no}</span>
            <span class="var-tag" onclick="copyVar(this)">{total_amount}</span>
            <span class="var-tag" onclick="copyVar(this)">{due_amount}</span>
            <span class="var-tag" onclick="copyVar(this)">{paid_amount}</span>
            <span class="var-tag" onclick="copyVar(this)">{product_name}</span>
            <span class="var-tag" onclick="copyVar(this)">{serial_number}</span>
            <span class="var-tag" onclick="copyVar(this)">{expiry_date}</span>
            <span class="var-tag" onclick="copyVar(this)">{ticket_no}</span>
            <span class="var-tag" onclick="copyVar(this)">{phone}</span>
        </div>
    </div>

    <!-- Templates Grid -->
    <div class="row">
        <?php foreach ($templates as $t): 
            $badge = $type_badges[$t['type']] ?? $type_badges['custom'];
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="template-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="badge <?= $badge[0] ?> mb-1"><i class="fas <?= $badge[1] ?>"></i> <?= ucfirst($t['type']) ?></span>
                        <h6 class="mb-0"><?= htmlspecialchars($t['name']) ?></h6>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="openEdit(<?= htmlspecialchars(json_encode($t)) ?>)"><i class="fas fa-edit"></i> Edit</a></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteTemplate(<?= $t['id'] ?>)"><i class="fas fa-trash"></i> Delete</a></li>
                        </ul>
                    </div>
                </div>
                <div class="template-content"><?= htmlspecialchars($t['content']) ?></div>
                <div class="mt-2 d-flex justify-content-between align-items-center">
                    <small class="text-muted"><?= strlen($t['content']) ?> chars</small>
                    <span class="badge <?= $t['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $t['is_active'] ? 'Active' : 'Inactive' ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="templateForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="template_id" id="templateId">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus"></i> New Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Template Name</label>
                            <input type="text" name="name" id="tplName" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Type</label>
                            <select name="type" id="tplType" class="form-select">
                                <option value="sales">Sales</option>
                                <option value="due">Due Reminder</option>
                                <option value="service">Service</option>
                                <option value="warranty">Warranty</option>
                                <option value="payment">Payment</option>
                                <option value="greeting">Greeting</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message Content</label>
                        <textarea name="content" id="tplContent" class="form-control" rows="5" 
                                  placeholder="Dear {customer_name}, ..."></textarea>
                        <small class="text-muted">Use variables like {customer_name}, {invoice_no} etc. Click a variable tag above to copy.</small>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="tplActive" checked>
                        <label class="form-check-label" for="tplActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="template_id" id="deleteId">
</form>

<script>
function openCreate() {
    document.getElementById('formAction').value = 'create';
    document.getElementById('templateId').value = '';
    document.getElementById('tplName').value = '';
    document.getElementById('tplType').value = 'custom';
    document.getElementById('tplContent').value = '';
    document.getElementById('tplActive').checked = true;
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> New Template';
}

function openEdit(t) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('templateId').value = t.id;
    document.getElementById('tplName').value = t.name;
    document.getElementById('tplType').value = t.type;
    document.getElementById('tplContent').value = t.content;
    document.getElementById('tplActive').checked = t.is_active == 1;
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Template';
    new bootstrap.Modal(document.getElementById('templateModal')).show();
}

function deleteTemplate(id) {
    if (confirm('Are you sure you want to delete this template?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function copyVar(el) {
    const text = el.textContent;
    const ta = document.getElementById('tplContent');
    if (ta) {
        const pos = ta.selectionStart;
        ta.value = ta.value.slice(0, pos) + text + ta.value.slice(pos);
        ta.focus();
    }
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
