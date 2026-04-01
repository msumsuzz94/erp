<?php
/**
 * Message History & Delivery Report
 * Full log of all sent messages with filters and stats
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Filters
$filter_from = $_GET['from'] ?? date('Y-m-01');
$filter_to = $_GET['to'] ?? date('Y-m-d');
$filter_status = $_GET['status'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_search = $_GET['search'] ?? '';

// Build query
$where = "WHERE DATE(ml.created_at) BETWEEN ? AND ?";
$params = [$filter_from, $filter_to];

if ($filter_status) {
    $where .= " AND ml.status = ?";
    $params[] = $filter_status;
}
if ($filter_category) {
    $where .= " AND ml.category = ?";
    $params[] = $filter_category;
}
if ($filter_search) {
    $where .= " AND (ml.phone LIKE ? OR ml.message LIKE ? OR c.name LIKE ?)";
    $s = "%{$filter_search}%";
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
}

$messages = db_query("
    SELECT ml.*, c.name as customer_name, mt.name as template_name
    FROM message_log ml
    LEFT JOIN customers c ON ml.customer_id = c.id
    LEFT JOIN message_templates mt ON ml.template_id = mt.id
    {$where}
    ORDER BY ml.created_at DESC
    LIMIT 500
", $params);

// Stats
$stats_sent = db_query_one("SELECT COUNT(*) as cnt FROM message_log WHERE status IN ('sent','delivered') AND DATE(created_at) BETWEEN ? AND ?", [$filter_from, $filter_to]);
$stats_failed = db_query_one("SELECT COUNT(*) as cnt FROM message_log WHERE status = 'failed' AND DATE(created_at) BETWEEN ? AND ?", [$filter_from, $filter_to]);
$stats_pending = db_query_one("SELECT COUNT(*) as cnt FROM message_log WHERE status = 'pending' AND DATE(created_at) BETWEEN ? AND ?", [$filter_from, $filter_to]);
$stats_by_cat = db_query("SELECT category, COUNT(*) as cnt FROM message_log WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY category ORDER BY cnt DESC", [$filter_from, $filter_to]);

$status_badges = ['sent' => 'bg-success', 'delivered' => 'bg-primary', 'failed' => 'bg-danger', 'pending' => 'bg-warning text-dark'];
$cat_badges = ['sales' => 'bg-success', 'due' => 'bg-danger', 'service' => 'bg-info', 'warranty' => 'bg-warning text-dark', 'greeting' => 'bg-primary', 'payment' => 'bg-success', 'custom' => 'bg-secondary'];

$page_title = 'Message History';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.stat-card { text-align: center; padding: 20px 15px; border-radius: 12px; }
.stat-card h2 { font-weight: 800; margin: 0; }
.stat-card p { margin: 5px 0 0; font-size: 13px; }
.msg-preview-cell { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>

<div class="container-fluid">
    <h4 class="mb-4"><i class="fas fa-history text-primary"></i> Message History & Report</h4>

    <!-- Stats Row -->
    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="stat-card" style="background: rgba(25,135,84,0.1);">
                <h2 class="text-success"><?= $stats_sent['cnt'] ?? 0 ?></h2>
                <p class="text-muted"><i class="fas fa-check-circle"></i> Sent / Delivered</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: rgba(220,53,69,0.1);">
                <h2 class="text-danger"><?= $stats_failed['cnt'] ?? 0 ?></h2>
                <p class="text-muted"><i class="fas fa-times-circle"></i> Failed</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: rgba(255,193,7,0.1);">
                <h2 class="text-warning"><?= $stats_pending['cnt'] ?? 0 ?></h2>
                <p class="text-muted"><i class="fas fa-hourglass-half"></i> Pending</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="background: rgba(13,110,253,0.1);">
                <h2 class="text-primary"><?= count($messages) ?></h2>
                <p class="text-muted"><i class="fas fa-list"></i> Total (filtered)</p>
            </div>
        </div>
    </div>

    <!-- Category Breakdown -->
    <?php if (!empty($stats_by_cat)): ?>
    <div class="mb-4">
        <?php foreach ($stats_by_cat as $sc): ?>
            <span class="badge <?= $cat_badges[$sc['category']] ?? 'bg-secondary' ?> me-1" style="font-size:13px;padding:6px 12px;">
                <?= ucfirst($sc['category']) ?>: <?= $sc['cnt'] ?>
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="<?= $filter_from ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="<?= $filter_to ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="sent" <?= $filter_status === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="delivered" <?= $filter_status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="failed" <?= $filter_status === 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="sales" <?= $filter_category === 'sales' ? 'selected' : '' ?>>Sales</option>
                        <option value="due" <?= $filter_category === 'due' ? 'selected' : '' ?>>Due</option>
                        <option value="service" <?= $filter_category === 'service' ? 'selected' : '' ?>>Service</option>
                        <option value="warranty" <?= $filter_category === 'warranty' ? 'selected' : '' ?>>Warranty</option>
                        <option value="payment" <?= $filter_category === 'payment' ? 'selected' : '' ?>>Payment</option>
                        <option value="greeting" <?= $filter_category === 'greeting' ? 'selected' : '' ?>>Greeting</option>
                        <option value="custom" <?= $filter_category === 'custom' ? 'selected' : '' ?>>Custom</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Name/Phone..." value="<?= htmlspecialchars($filter_search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Messages Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Category</th>
                            <th>Message</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No messages found for this period</td></tr>
                        <?php else: ?>
                            <?php foreach ($messages as $i => $m): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><small><?= date('d-M-Y H:i', strtotime($m['created_at'])) ?></small></td>
                                <td><strong><?= htmlspecialchars($m['customer_name'] ?? 'Unknown') ?></strong></td>
                                <td><?= htmlspecialchars($m['phone']) ?></td>
                                <td><span class="badge <?= $cat_badges[$m['category']] ?? 'bg-secondary' ?>"><?= ucfirst($m['category']) ?></span></td>
                                <td class="msg-preview-cell" title="<?= htmlspecialchars($m['message']) ?>">
                                    <?= htmlspecialchars(substr($m['message'], 0, 60)) ?><?= strlen($m['message']) > 60 ? '...' : '' ?>
                                </td>
                                <td><span class="badge <?= $status_badges[$m['status']] ?? 'bg-secondary' ?>"><?= ucfirst($m['status']) ?></span></td>
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
