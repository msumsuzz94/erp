<?php
/**
 * Send Message
 * Send SMS/WhatsApp to individual, group, or all customers with template support
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$success_message = '';
$error_message = '';

// Handle send
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? 'send';
        
        if ($action === 'send') {
            $customer_ids = $_POST['customer_ids'] ?? [];
            $message = $_POST['message'] ?? '';
            $category = clean_input($_POST['category'] ?? 'custom');
            $channel = clean_input($_POST['channel'] ?? 'sms');
            $template_id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;
            
            if (empty($customer_ids)) {
                $error_message = 'Please select at least one customer';
            } elseif (empty($message)) {
                $error_message = 'Message cannot be empty';
            } else {
                $sent_count = 0;
                $failed_count = 0;
                
                // Load notification functions
                if (!function_exists('send_notification')) {
                    require_once __DIR__ . '/../../includes/sms_functions.php';
                }
                
                foreach ($customer_ids as $cid) {
                    $customer = db_select_one('customers', ['id' => (int)$cid]);
                    if ($customer && !empty($customer['phone'])) {
                        // Replace variables
                        $personalized = str_replace(
                            ['{customer_name}', '{phone}'],
                            [$customer['name'] ?? 'Customer', $customer['phone']],
                            $message
                        );
                        
                        // Determine recipient based on channel
                        $recipient = ($channel === 'email') ? ($customer['email'] ?? '') : $customer['phone'];
                        if (empty($recipient)) continue;
                        
                        // Actually send the message via gateway
                        $result = send_notification($channel, $recipient, $personalized, 'Notification');
                        
                        // Log the message with actual result
                        db_insert('message_log', [
                            'customer_id' => $cid,
                            'phone' => $customer['phone'],
                            'message' => $personalized,
                            'template_id' => $template_id,
                            'type' => $channel,
                            'category' => $category,
                            'status' => $result['status'] ? 'sent' : 'failed',
                            'sent_at' => date('Y-m-d H:i:s'),
                            'created_by' => get_current_user_id()
                        ]);
                        
                        if ($result['status']) {
                            $sent_count++;
                        } else {
                            $failed_count++;
                        }
                    }
                }
                
                if ($failed_count > 0) {
                    $success_message = "Sent: {$sent_count}, Failed: {$failed_count}. Check Notification > Channel Configuration.";
                } else {
                    $success_message = "Message sent to {$sent_count} customer(s)!";
                }
            }
        }
    }
}

// Get data
$templates = db_query("SELECT * FROM message_templates WHERE is_active = 1 ORDER BY type, name");
$customers = db_query("SELECT c.*, 
    (SELECT SUM(due_amount) FROM sales WHERE customer_id = c.id AND due_amount > 0) as total_due,
    (SELECT COUNT(*) FROM sales WHERE customer_id = c.id) as total_purchases
    FROM customers c WHERE c.phone IS NOT NULL AND c.phone != '' ORDER BY c.name");

$page_title = 'Send Message';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.customer-table td { vertical-align: middle; }
.filter-section { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 15px; margin-bottom: 15px; }
.msg-preview { background: #e8f5e9; border-radius: 10px; padding: 15px; border-left: 4px solid #4CAF50; min-height: 80px; font-size: 14px; }
.char-count { font-size: 12px; color: #666; }
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

    <h4 class="mb-4"><i class="fas fa-paper-plane text-primary"></i> Send Message</h4>

    <form method="POST" id="sendForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="action" value="send">
        <div class="row">
            <!-- Left: Message Compose -->
            <div class="col-lg-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-pen"></i> Compose Message</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">Channel</label>
                                <select name="channel" class="form-select">
                                    <option value="sms">📱 SMS</option>
                                    <option value="whatsapp">💬 WhatsApp</option>
                                    <option value="telegram">✈️ Telegram</option>
                                    <option value="email">📧 Email</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Category</label>
                                <select name="category" class="form-select">
                                    <option value="custom">Custom</option>
                                    <option value="sales">Sales</option>
                                    <option value="due">Due Reminder</option>
                                    <option value="payment">Payment</option>
                                    <option value="greeting">Greeting</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Load Template</label>
                            <select name="template_id" id="templateSelect" class="form-select" onchange="loadTemplate()">
                                <option value="">— Write custom —</option>
                                <?php foreach ($templates as $t): ?>
                                    <option value="<?= $t['id'] ?>" data-content="<?= htmlspecialchars($t['content']) ?>"><?= htmlspecialchars($t['name']) ?> (<?= ucfirst($t['type']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Message</label>
                            <textarea name="message" id="messageText" class="form-control" rows="6" 
                                      placeholder="Dear {customer_name}, ..." oninput="updatePreview()"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <span class="char-count"><span id="charCount">0</span> characters</span>
                                <span class="char-count"><span id="smsCount">0</span> SMS part(s)</span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100" id="sendBtn">
                            <i class="fas fa-paper-plane"></i> Send Message (<span id="selectedCount">0</span> selected)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right: Customer Selection -->
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users"></i> Select Customers</h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">Deselect All</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Filters -->
                        <div class="filter-section m-3">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="text" id="searchCustomer" class="form-control form-control-sm" placeholder="🔍 Search name/phone..." oninput="filterCustomers()">
                                </div>
                                <div class="col-md-3">
                                    <select id="filterDue" class="form-select form-select-sm" onchange="filterCustomers()">
                                        <option value="">All Customers</option>
                                        <option value="due">With Due Only</option>
                                        <option value="no-due">No Due</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select id="filterPurchase" class="form-select form-select-sm" onchange="filterCustomers()">
                                        <option value="">All Activities</option>
                                        <option value="active">Has Purchases</option>
                                        <option value="inactive">No Purchases</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                            <table class="table table-hover customer-table mb-0" id="customerTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th width="40"><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
                                        <th>Customer</th>
                                        <th>Phone</th>
                                        <th>Purchases</th>
                                        <th>Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $c): ?>
                                    <tr data-name="<?= strtolower($c['name'] ?? '') ?>" data-phone="<?= $c['phone'] ?>" 
                                        data-due="<?= $c['total_due'] > 0 ? 'due' : 'no-due' ?>" 
                                        data-purchase="<?= $c['total_purchases'] > 0 ? 'active' : 'inactive' ?>">
                                        <td><input type="checkbox" name="customer_ids[]" value="<?= $c['id'] ?>" class="customer-check" onchange="updateCount()"></td>
                                        <td><strong><?= htmlspecialchars($c['name'] ?? 'N/A') ?></strong></td>
                                        <td><?= htmlspecialchars($c['phone']) ?></td>
                                        <td><span class="badge bg-info"><?= $c['total_purchases'] ?? 0 ?></span></td>
                                        <td>
                                            <?php if ($c['total_due'] > 0): ?>
                                                <span class="badge bg-danger"><?= number_format($c['total_due'], 0) ?>৳</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function loadTemplate() {
    const sel = document.getElementById('templateSelect');
    const opt = sel.options[sel.selectedIndex];
    if (opt.dataset.content) {
        document.getElementById('messageText').value = opt.dataset.content;
        updatePreview();
    }
}

function updatePreview() {
    const text = document.getElementById('messageText').value;
    document.getElementById('charCount').textContent = text.length;
    document.getElementById('smsCount').textContent = Math.ceil(text.length / 160) || 0;
}

function updateCount() {
    const checked = document.querySelectorAll('.customer-check:checked').length;
    document.getElementById('selectedCount').textContent = checked;
}

function selectAll() {
    document.querySelectorAll('#customerTable tbody tr:not([style*="display: none"]) .customer-check').forEach(c => c.checked = true);
    updateCount();
}
function deselectAll() {
    document.querySelectorAll('.customer-check').forEach(c => c.checked = false);
    updateCount();
}
function toggleAll(el) {
    document.querySelectorAll('#customerTable tbody tr:not([style*="display: none"]) .customer-check').forEach(c => c.checked = el.checked);
    updateCount();
}

function filterCustomers() {
    const search = document.getElementById('searchCustomer').value.toLowerCase();
    const due = document.getElementById('filterDue').value;
    const purchase = document.getElementById('filterPurchase').value;
    
    document.querySelectorAll('#customerTable tbody tr').forEach(row => {
        const name = row.dataset.name;
        const phone = row.dataset.phone;
        const rowDue = row.dataset.due;
        const rowPurchase = row.dataset.purchase;
        
        let show = true;
        if (search && !name.includes(search) && !phone.includes(search)) show = false;
        if (due && rowDue !== due) show = false;
        if (purchase && rowPurchase !== purchase) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

updatePreview();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
