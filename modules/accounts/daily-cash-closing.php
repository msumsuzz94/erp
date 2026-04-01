<?php
/**
 * Daily Cash Closing Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle deletion
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $delete_id = (int)$_POST['delete_id'];
        
        // Get details for log
        $closing = db_select_one('cash_closings', ['id' => $delete_id]);
        
        if ($closing && db_delete('cash_closings', ['id' => $delete_id])) {
            log_activity(get_current_user_id(), 'delete_cash_closing', "Deleted cash closing for " . $closing['closing_date']);
            redirect_with_message($_SERVER['PHP_SELF'], 'Cash closing deleted successfully', 'success');
        }
    }
}

// Handle cash closing submission
if (is_post() && isset($_POST['closing_date']) && !isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $closing_data = [
            'closing_date' => clean_input($_POST['closing_date']),
            'expected_cash' => (float)$_POST['expected_cash'],
            'actual_cash' => (float)$_POST['actual_cash'],
            'variance' => (float)$_POST['actual_cash'] - (float)$_POST['expected_cash'],
            'notes' => clean_input($_POST['notes']),
            'closed_by' => get_current_user_id()
        ];
        
        // Check if closing already exists for this date? 
        // The original code didn't check, so we'll allow multiple or maybe it's fine.
        // But typically one per day. For now, logic stays same as original (insert).
        
        if (db_insert('cash_closings', $closing_data)) {
            log_activity(get_current_user_id(), 'cash_closing', "Cash closing for " . $closing_data['closing_date']);
            redirect_with_message($_SERVER['PHP_SELF'], 'Cash closing recorded successfully', 'success');
        }
    }
}

// Get today's expected cash (from cash transactions)
$today = date('Y-m-d');
$sql = "SELECT 
            SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) -
            SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as net_cash
        FROM cash_transactions 
        WHERE transaction_date = ?";
$result = db_query_one($sql, [$today]);
$expected_cash = $result['net_cash'] ?? 0;

// Get business settings for header
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

// Get filters
$from_date = get_param('from_date', '');
$to_date = get_param('to_date', '');

// Get recent closings with filters
$sql = "SELECT cc.*, u.username as closed_by_name 
        FROM cash_closings cc 
        LEFT JOIN users u ON cc.closed_by = u.id 
        WHERE 1=1";

$params = [];
if (!empty($from_date)) {
    $sql .= " AND cc.closing_date >= ?";
    $params[] = $from_date;
}
if (!empty($to_date)) {
    $sql .= " AND cc.closing_date <= ?";
    $params[] = $to_date;
}

$sql .= " ORDER BY cc.closing_date DESC";
// Removed LIMIT 30 to allow full history via filters/pagination
$closings = db_query($sql, $params);

$page_title = 'Daily Cash Closing';
$page_actions = '<button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Print Report</button>';

$additional_css = '
<style>
/* Print Area Styles (Hidden on Screen) */
#print-area {
    display: none;
    padding: 20px;
    background: #fff;
    color: #000;
}

@media print {
    @page { margin: 0.25cm; size: auto; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; width: 100% !important; }
    
    /* Hide regular UI AND standard print header/footer */
    .no-print, .btn, .card, .navbar, .sidebar, #sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, .summary-cards, #standard-print-wrapper, #standard-print-footer, form, .modal {
        display: none !important;
    }
    
    #print-area { display: block !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
    
    .print-container { 
        width: 100% !important; 
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        box-sizing: border-box !important; 
        font-family: \'Segoe UI\', Arial, sans-serif; 
        font-size: 11px; 
    }
    
    /* 3-Column Header */
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .header-table td { vertical-align: top; border: none !important; padding: 0; }
    .logo-cell { width: 10%; text-align: left; }
    .logo-cell img { max-width: 80px; height: auto; display: block; }
    
    .title-cell { width: 65%; text-align: center; padding: 0 10px; }
    .company-name { font-size: 22px; font-weight: 900; color: #000; margin: 0; text-transform: uppercase; border-bottom: 2px solid #000; display: inline-block; line-height: 1.2; padding-bottom: 2px; }
    .company-slogan { font-size: 11px; color: #000; font-weight: bold; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .info-cell { width: 25%; text-align: left; line-height: 1.4; font-size: 9px; border: 1px solid #000; padding: 5px 8px; box-sizing: border-box; }
    .info-cell p { margin: 0; margin-bottom: 2px; }
    .info-cell p:last-child { margin-bottom: 0; }
    
    .report-main-title { text-align: center; font-size: 16px; color: #000; margin: 12px 0; font-weight: bold; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 0; }

    /* Simple Bordered Table */
    .print-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .print-table th, .print-table td { border: 1px solid #333 !important; padding: 8px; text-align: left; }
    .print-table th { background: #f2f2f2 !important; font-weight: bold; text-transform: uppercase; }
    .text-end { text-align: right !important; }
    
    /* Fixed Footer at bottom of EVERY page */
    .print-footer { 
        position: fixed;
        bottom: 0.3cm;
        left: 0;
        right: 0;
        border-top: 1px solid #333; 
        padding-top: 10px; 
        display: block;
        width: 100%;
        font-size: 10px; 
        background: #fff;
        z-index: 9999;
    }
    .footer-left { float: left; width: 50%; font-weight: bold; text-align: left; padding-left: 10px; }
    .footer-right { float: right; width: 50%; text-align: right; padding-right: 10px; }
    .clearfix::after { content: ""; clear: both; display: table; }
}
</style>
';

include __DIR__ . '/../../templates/header.php';
?>


<!-- Hidden Print Area -->
<div id="print-area">
    <div class="print-container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <?php 
                    $logo_url = !empty($invoice_settings['company_logo']) ? $invoice_settings['company_logo'] : 'assets/images/logo.png';
                    if (!preg_match('~^(?:f|ht)tps?://~i', $logo_url)) {
                        $logo_url = BASE_URL . '/' . ltrim($logo_url, '/');
                    }
                    ?>
                    <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo">
                </td>
                <td class="title-cell">
                    <h1 class="company-name"><?= htmlspecialchars($invoice_settings['company_name'] ?? '') ?></h1><br>
                    <div class="company-slogan"><?= htmlspecialchars($invoice_settings['company_slogan'] ?? '') ?></div>
                </td>
                <td class="info-cell">
                    <p>Address: <?= htmlspecialchars($invoice_settings['company_address'] ?? '') ?></p>
                    <p>Tel: <?= htmlspecialchars($invoice_settings['company_phone'] ?? '') ?></p>
                    <p>Email: <?= htmlspecialchars($invoice_settings['company_email'] ?? '') ?></p>
                    <p>Website: <?= htmlspecialchars($invoice_settings['company_website'] ?? '') ?></p>
                </td>
            </tr>
        </table>

        <div class="report-main-title">Daily Cash Closing Report (Date: <?= date('d M Y') ?>)</div>

        <!-- Data Table -->
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-end">Expected</th>
                    <th class="text-end">Actual</th>
                    <th class="text-end">Variance</th>
                    <th>Notes</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($closings as $closing): ?>
                    <tr>
                        <td><?= format_date($closing['closing_date']) ?></td>
                        <td class="text-end"><?= format_currency($closing['expected_cash']) ?></td>
                        <td class="text-end"><?= format_currency($closing['actual_cash']) ?></td>
                        <td class="text-end"><?= format_currency($closing['variance']) ?></td>
                        <td><?= htmlspecialchars($closing['notes'] ?? '') ?></td>
                        <td><?= htmlspecialchars($closing['closed_by_name'] ?? 'Unknown') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<div class="no-print">

<div class="row">
    <!-- Cash Closing Form -->
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Record Cash Closing</h6>
            </div>
            <div class="card-body">
                <form method="POST" id="closingForm">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="form-group mb-3">
                        <label>Closing Date <span class="text-danger">*</span></label>
                        <input type="date" name="closing_date" class="form-control" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Expected Cash</label>
                        <input type="number" name="expected_cash" id="expected_cash" 
                               class="form-control" step="0.01" 
                               value="<?= $expected_cash ?>" readonly>
                        <small class="text-muted">Calculated from today's transactions</small>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Actual Cash <span class="text-danger">*</span></label>
                        <input type="number" name="actual_cash" id="actual_cash" 
                               class="form-control" step="0.01" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Variance</label>
                        <input type="text" id="variance" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Record Closing
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Recent Closings -->
    <div class="col-md-8">
        <!-- Filter Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filter Reports</h6>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label class="small">From Date</label>
                                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label class="small">To Date</label>
                                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-group mb-0 w-100">
                                <button type="submit" class="btn btn-sm btn-primary btn-block"><i class="fas fa-filter"></i> Filter</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- List Card -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Closing History</h6>
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-sm btn-secondary no-print"><i class="fas fa-redo"></i> Reset</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm" id="closingsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Expected</th>
                                <th>Actual</th>
                                <th>Variance</th>
                                <th>Notes</th>
                                <th>Recorded By</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($closings as $closing): ?>
                                <tr>
                                    <td><?= format_date($closing['closing_date']) ?></td>
                                    <td><?= format_currency($closing['expected_cash']) ?></td>
                                    <td><?= format_currency($closing['actual_cash']) ?></td>
                                    <td class="<?= $closing['variance'] < 0 ? 'text-danger' : ($closing['variance'] > 0 ? 'text-success' : '') ?>">
                                        <?= format_currency($closing['variance']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($closing['notes'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($closing['closed_by_name'] ?? 'Unknown') ?></td>
                                    <td class="no-print text-center">
                                        <button type="button" class="btn btn-sm btn-danger delete-closing" 
                                                data-id="<?= $closing['id'] ?>" 
                                                data-date="<?= format_date($closing['closing_date']) ?>">
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
    </div>
</div>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#closingsTable').DataTable({
            "order": [[0, "desc"]],
            "pageLength": 25,
             "columnDefs": [
                { "orderable": false, "targets": 6 }
            ]
        });
    }

    // Variance calculation
    $('#actual_cash').on('input', function() {
        var expected = parseFloat($('#expected_cash').val()) || 0;
        var actual = parseFloat($(this).val()) || 0;
        var variance = actual - expected;
        
        $('#variance').val('<?= APP_CURRENCY_SYMBOL ?>' + variance.toFixed(2));
        
        if (variance < 0) {
            $('#variance').removeClass('text-success').addClass('text-danger');
        } else if (variance > 0) {
            $('#variance').removeClass('text-danger').addClass('text-success');
        } else {
            $('#variance').removeClass('text-danger text-success');
        }
    });

    // Handle Delete
    $('.delete-closing').click(function() {
        const id = $(this).data('id');
        const date = $(this).data('date');
        if (confirm('Are you sure you want to delete the cash closing record for ' + date + '?')) {
            $('#delete_id').val(id);
            $('#deleteForm').submit();
        }
    });
});
</script>
