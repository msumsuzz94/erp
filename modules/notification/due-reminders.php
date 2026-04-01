<?php
/**
 * Due Payment Reminders
 * View customers with dues and send reminders
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$success_message = '';
$error_message = '';

// Handle send reminder
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send_reminder') {
            $customer_ids = $_POST['customer_ids'] ?? [];
            $template_id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;
            $channel = clean_input($_POST['channel'] ?? 'sms');
            
            // Get template content
            $template_content = '';
            if ($template_id) {
                $tpl = db_select_one('message_templates', ['id' => $template_id]);
                $template_content = $tpl['content'] ?? '';
            }
            if (empty($template_content)) {
                $template_content = 'Dear {customer_name}, you have a due balance of {due_amount} Tk. Please clear your dues at your earliest convenience. Thank you.';
            }
            
            $sent = 0;
            foreach ($customer_ids as $cid) {
                $customer = db_select_one('customers', ['id' => (int)$cid]);
                if (!$customer || empty($customer['phone'])) continue;
                
                // Get total due
                $due_info = db_query_one("SELECT SUM(due_amount) as total_due, GROUP_CONCAT(invoice_number SEPARATOR ', ') as invoices FROM sales WHERE customer_id = ? AND due_amount > 0", [$cid]);
                
                $msg = str_replace(
                    ['{customer_name}', '{due_amount}', '{invoice_no}', '{phone}'],
                    [$customer['name'] ?? 'Customer', number_format($due_info['total_due'] ?? 0, 2), $due_info['invoices'] ?? '', $customer['phone']],
                    $template_content
                );
                
                db_insert('message_log', [
                    'customer_id' => $cid,
                    'phone' => $customer['phone'],
                    'message' => $msg,
                    'template_id' => $template_id,
                    'type' => $channel,
                    'category' => 'due',
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s'),
                    'created_by' => get_current_user_id()
                ]);
                $sent++;
            }
            $success_message = "Due reminder sent to {$sent} customer(s)!";
        } elseif ($action === 'send_payment_confirm') {
            $cid = (int)$_POST['customer_id'];
            $sale_id = (int)$_POST['sale_id'];
            $customer = db_select_one('customers', ['id' => $cid]);
            $sale = db_select_one('sales', ['id' => $sale_id]);
            
            if ($customer && $sale && !empty($customer['phone'])) {
                $msg = "Dear {$customer['name']}, we have received your payment. Invoice: {$sale['invoice_number']}. Remaining due: " . number_format($sale['due_amount'], 2) . " Tk. Thank you!";
                
                db_insert('message_log', [
                    'customer_id' => $cid, 'phone' => $customer['phone'],
                    'message' => $msg, 'type' => clean_input($_POST['channel'] ?? 'sms'), 'category' => 'payment',
                    'status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'),
                    'created_by' => get_current_user_id()
                ]);
                $success_message = "Payment confirmation sent to {$customer['name']}!";
            }
        }
    }
}

// Get customers with dues
$due_customers = db_query("
    SELECT c.id, c.name, c.phone, 
           SUM(s.due_amount) as total_due, 
           COUNT(s.id) as invoice_count,
           MAX(s.sale_date) as last_sale_date,
           GROUP_CONCAT(s.invoice_number SEPARATOR ', ') as invoices
    FROM customers c
    INNER JOIN sales s ON c.id = s.customer_id
    WHERE s.due_amount > 0
    GROUP BY c.id
    ORDER BY total_due DESC
");

$due_templates = db_query("SELECT * FROM message_templates WHERE type = 'due' AND is_active = 1");

$page_title = 'Due Reminders';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.due-card { border-left: 4px solid #dc3545; }
.stat-box { text-align: center; padding: 15px; border-radius: 10px; }
.stat-box h3 { margin: 0; font-weight: 700; }
.stat-box p { margin: 5px 0 0; font-size: 13px; }
</style>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-bell text-danger"></i> Due Payment Reminders</h4>
    </div>

    <!-- Summary Cards -->
    <?php
    $total_due_amount = array_sum(array_column($due_customers, 'total_due'));
    $total_due_customers = count($due_customers);
    ?>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-box" style="background: rgba(220,53,69,0.1);">
                <h3 class="text-danger"><?= number_format($total_due_amount, 0) ?>৳</h3>
                <p class="text-muted">Total Due Amount</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-box" style="background: rgba(255,193,7,0.1);">
                <h3 class="text-warning"><?= $total_due_customers ?></h3>
                <p class="text-muted">Customers with Due</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-box" style="background: rgba(13,110,253,0.1);">
                <?php $today_reminders = db_query_one("SELECT COUNT(*) as cnt FROM message_log WHERE category='due' AND DATE(created_at)=CURDATE()"); ?>
                <h3 class="text-primary"><?= $today_reminders['cnt'] ?? 0 ?></h3>
                <p class="text-muted">Reminders Sent Today</p>
            </div>
        </div>
    </div>

    <form method="POST" id="reminderForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="action" value="send_reminder">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <select name="template_id" class="form-select">
                    <option value="">Default Reminder Template</option>
                    <?php foreach ($due_templates as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="channel" class="form-select">
                    <option value="sms">📱 SMS</option>
                    <option value="whatsapp">💬 WhatsApp</option>
                    <option value="telegram">✈️ Telegram</option>
                    <option value="email">📧 Email</option>
                </select>
            </div>
            <div class="col-md-6 text-end">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAllDue()">Select All</button>
                <button type="submit" class="btn btn-danger" id="sendReminderBtn" disabled>
                    <i class="fas fa-bell"></i> Send Reminder (<span id="dueCount">0</span>)
                </button>
            </div>
        </div>

        <div class="card shadow-sm due-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40"><input type="checkbox" id="checkAllDue" onchange="toggleAllDue(this)"></th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Invoices</th>
                                <th class="text-end">Due Amount</th>
                                <th>Last Sale</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($due_customers)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-check-circle fa-2x mb-2 d-block text-success"></i>No customers with due payments!</td></tr>
                            <?php else: ?>
                                <?php foreach ($due_customers as $dc): ?>
                                <tr>
                                    <td><input type="checkbox" name="customer_ids[]" value="<?= $dc['id'] ?>" class="due-check" onchange="updateDueCount()"></td>
                                    <td><strong><?= htmlspecialchars($dc['name'] ?? 'N/A') ?></strong></td>
                                    <td><?= htmlspecialchars($dc['phone'] ?? 'No phone') ?></td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars(substr($dc['invoices'], 0, 50)) ?><?= strlen($dc['invoices']) > 50 ? '...' : '' ?></small>
                                        <span class="badge bg-info ms-1"><?= $dc['invoice_count'] ?></span>
                                    </td>
                                    <td class="text-end"><strong class="text-danger"><?= number_format($dc['total_due'], 0) ?>৳</strong></td>
                                    <td><small><?= $dc['last_sale_date'] ? date('d-M-Y', strtotime($dc['last_sale_date'])) : '-' ?></small></td>
                                    <td>
                                        <?php if (!empty($dc['phone'])): ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                                    onclick="quickRemind(<?= $dc['id'] ?>, '<?= htmlspecialchars($dc['name']) ?>')">
                                                <i class="fas fa-bell"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function updateDueCount() {
    const count = document.querySelectorAll('.due-check:checked').length;
    document.getElementById('dueCount').textContent = count;
    document.getElementById('sendReminderBtn').disabled = count === 0;
}
function selectAllDue() {
    document.querySelectorAll('.due-check').forEach(c => c.checked = true);
    updateDueCount();
}
function toggleAllDue(el) {
    document.querySelectorAll('.due-check').forEach(c => c.checked = el.checked);
    updateDueCount();
}
function quickRemind(id, name) {
    if (confirm('Send due reminder to ' + name + '?')) {
        const form = document.getElementById('reminderForm');
        document.querySelectorAll('.due-check').forEach(c => c.checked = (c.value == id));
        updateDueCount();
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
