<?php
/**
 * Service Updates
 * Send service status notifications to customers
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$success_message = '';

// Handle send
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $ticket_id = (int)$_POST['ticket_id'];
        $template_id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;
        
        // Get ticket info
        $ticket = db_select_one('service_tickets', ['id' => $ticket_id]);
        if ($ticket && !empty($ticket['customer_phone'])) {
            $customer_name = $ticket['customer_name'] ?? 'Customer';
            $ticket_no = $ticket['ticket_number'] ?? '#' . $ticket_id;
            $device = $ticket['device_name'] ?? 'your device';
            $status = ucfirst($ticket['status'] ?? 'received');
            
            // Get template or default
            $msg = '';
            if ($template_id) {
                $tpl = db_select_one('message_templates', ['id' => $template_id]);
                $msg = $tpl['content'] ?? '';
            }
            if (empty($msg)) {
                $status_map = [
                    'received' => "Dear {customer_name}, your device ({device}) has been received. Ticket: {ticket_no}.",
                    'in_progress' => "Dear {customer_name}, your device (Ticket: {ticket_no}) is being repaired.",
                    'ready' => "Dear {customer_name}, your device (Ticket: {ticket_no}) is ready for pickup!",
                    'delivered' => "Dear {customer_name}, your device (Ticket: {ticket_no}) has been delivered. Thank you!",
                ];
                $msg = $status_map[strtolower($ticket['status'])] ?? $status_map['received'];
            }
            
            $msg = str_replace(
                ['{customer_name}', '{ticket_no}', '{device}'],
                [$customer_name, $ticket_no, $device],
                $msg
            );
            
            db_insert('message_log', [
                'customer_id' => $ticket['customer_id'] ?? null,
                'phone' => $ticket['customer_phone'],
                'message' => $msg,
                'template_id' => $template_id,
                'type' => clean_input($_POST['channel'] ?? 'sms'),
                'category' => 'service',
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
                'created_by' => get_current_user_id()
            ]);
            $success_message = "Service update sent to {$customer_name}!";
        }
    }
}

// Get active service tickets
$tickets = db_query("SELECT * FROM service_tickets WHERE status != 'delivered' ORDER BY created_at DESC");
$delivered_tickets = db_query("SELECT * FROM service_tickets WHERE status = 'delivered' ORDER BY updated_at DESC LIMIT 20");
$service_templates = db_query("SELECT * FROM message_templates WHERE type = 'service' AND is_active = 1");

$status_colors = [
    'received' => 'warning',
    'diagnosing' => 'info',
    'in_progress' => 'primary',
    'waiting_parts' => 'secondary',
    'ready' => 'success',
    'delivered' => 'dark'
];

$page_title = 'Service Updates';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.ticket-card { border: 1px solid var(--border-color); border-radius: 10px; padding: 15px; margin-bottom: 12px; }
.ticket-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
</style>

<div class="container-fluid">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <h4 class="mb-4"><i class="fas fa-tools text-info"></i> Service Updates Notification</h4>

    <!-- Active Tickets -->
    <div class="card shadow-sm mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-wrench"></i> Active Service Tickets (<?= count($tickets) ?>)</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ticket #</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Device</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th width="200">Send Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No active service tickets</td></tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $t): 
                                $color = $status_colors[$t['status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['ticket_number'] ?? '#'.$t['id']) ?></strong></td>
                                <td><?= htmlspecialchars($t['customer_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($t['customer_phone'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($t['device_name'] ?? '-') ?></td>
                                <td><span class="badge bg-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $t['status'])) ?></span></td>
                                <td><small><?= date('d-M-Y', strtotime($t['created_at'])) ?></small></td>
                                <td>
                                    <?php if (!empty($t['customer_phone'])): ?>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                        <select name="template_id" class="form-select form-select-sm" style="max-width:120px;">
                                            <option value="">Default</option>
                                            <?php foreach ($service_templates as $st): ?>
                                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars(substr($st['name'], 0, 20)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="channel" class="form-select form-select-sm" style="max-width:90px;">
                                            <option value="sms">SMS</option>
                                            <option value="whatsapp">WA</option>
                                            <option value="telegram">TG</option>
                                            <option value="email">Email</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-info text-white">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                        <small class="text-muted">No phone</small>
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

    <!-- Recently Delivered -->
    <div class="card shadow-sm">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success"><i class="fas fa-check-double"></i> Recently Delivered</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Ticket</th>
                            <th>Customer</th>
                            <th>Device</th>
                            <th>Delivered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($delivered_tickets)): ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted">No delivered tickets yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($delivered_tickets as $dt): ?>
                            <tr>
                                <td><?= htmlspecialchars($dt['ticket_number'] ?? '#'.$dt['id']) ?></td>
                                <td><?= htmlspecialchars($dt['customer_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($dt['device_name'] ?? '-') ?></td>
                                <td><small><?= $dt['updated_at'] ? date('d-M-Y', strtotime($dt['updated_at'])) : '-' ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
