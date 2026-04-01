<?php
/**
 * Yearly Closing Page
 * Manage annual financial closings
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle add or edit form submission
if (is_post() && isset($_POST['save_closing'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $closing_year = (int)$_POST['closing_year'];
        $closing_date = clean_input($_POST['closing_date']);
        $total_revenue = (float)$_POST['total_revenue'];
        $total_expenses = (float)$_POST['total_expenses'];
        $total_profit = $total_revenue - $total_expenses;
        $total_assets = (float)$_POST['total_assets'];
        $total_liabilities = (float)$_POST['total_liabilities'];
        $net_worth = $total_assets - $total_liabilities;
        $bank_balance = (float)$_POST['bank_balance'];
        $cash_balance = (float)$_POST['cash_balance'];
        $notes = clean_input($_POST['notes']);
        $status = clean_input($_POST['status']);
        
        // Check if year already exists for a different record
        $existing = db_query_one("SELECT id FROM yearly_closings WHERE closing_year = ? AND id != ?", [$closing_year, $id]);
        if ($existing) {
            redirect_with_message($_SERVER['PHP_SELF'], 'Yearly closing for ' . $closing_year . ' already exists', 'error');
        }
        
        $data = [
            'closing_year' => $closing_year,
            'closing_date' => $closing_date,
            'total_revenue' => $total_revenue,
            'total_expenses' => $total_expenses,
            'total_profit' => $total_profit,
            'total_assets' => $total_assets,
            'total_liabilities' => $total_liabilities,
            'net_worth' => $net_worth,
            'bank_balance' => $bank_balance,
            'cash_balance' => $cash_balance,
            'notes' => $notes,
            'status' => $status
        ];

        if ($id > 0) {
            // Update existing
            if (db_update('yearly_closings', $data, ['id' => $id])) {
                log_activity(get_current_user_id(), 'update_yearly_closing', "Updated yearly closing for $closing_year");
                redirect_with_message($_SERVER['PHP_SELF'], 'Yearly closing updated successfully', 'success');
            } else {
                redirect_with_message($_SERVER['PHP_SELF'], 'Failed to update yearly closing', 'error');
            }
        } else {
            // Insert new
            $data['closed_by'] = get_current_user_id();
            if (db_insert('yearly_closings', $data)) {
                log_activity(get_current_user_id(), 'create_yearly_closing', "Created yearly closing for $closing_year");
                redirect_with_message($_SERVER['PHP_SELF'], 'Yearly closing added successfully', 'success');
            } else {
                redirect_with_message($_SERVER['PHP_SELF'], 'Failed to add yearly closing', 'error');
            }
        }
    }
}

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $id = (int)$_POST['delete_id'];
        $closing = db_select_one('yearly_closings', ['id' => $id]);
        
        if (db_delete('yearly_closings', ['id' => $id])) {
            log_activity(get_current_user_id(), 'delete_yearly_closing', "Deleted yearly closing for {$closing['closing_year']}");
            redirect_with_message($_SERVER['PHP_SELF'], 'Yearly closing deleted successfully', 'success');
        } else {
            redirect_with_message($_SERVER['PHP_SELF'], 'Failed to delete yearly closing', 'error');
        }
    }
}

// Get all yearly closings
$closings = db_query("
    SELECT yc.*, u.username as closed_by_name
    FROM yearly_closings yc
    LEFT JOIN users u ON yc.closed_by = u.id
    ORDER BY yc.closing_year DESC
");

// Calculate statistics
$stats = db_query_one("
    SELECT 
        COUNT(*) as total_closings,
        AVG(total_profit) as avg_profit,
        SUM(CASE WHEN total_profit > 0 THEN 1 ELSE 0 END) as profitable_years,
        MAX(total_profit) as best_profit,
        MIN(total_profit) as worst_profit
    FROM yearly_closings
");

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

$page_title = 'Yearly Closing';
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
    .no-print, .btn, .card, .navbar, .sidebar, #sidebar, #accordionSidebar, .topbar, footer, .footer, #footer, .summary-cards, #standard-print-wrapper, #standard-print-footer, form, .modal, .alert {
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

    /* Handling Individual Print vs List Print */
    body.printing-individual #print-area { display: none !important; }
    
    #printableSection { display: none; }
    body.printing-individual #printableSection, 
    body.printing-individual #printableSection * { visibility: visible !important; }
    body.printing-individual #printableSection { 
        display: block !important; 
        position: absolute; 
        left: 0; 
        top: 0; 
        width: 100%; 
        visibility: visible !important;
        background: #fff;
        padding: 0 !important;
        margin: 0 !important;
    }
}
</style>
';

include __DIR__ . '/../../templates/header.php';
?>

<!-- Hidden Print Area (Main List) -->
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

        <div class="report-main-title">Yearly Closing History Report (Date: <?= date('d M Y') ?>)</div>

         <table class="print-table">
            <thead>
                <tr>
                    <th>Year</th>
                    <th>Date</th>
                    <th class="text-end">Revenue</th>
                    <th class="text-end">Expenses</th>
                    <th class="text-end">Profit/Loss</th>
                    <th class="text-end">Net Worth</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($closings as $closing): ?>
                    <tr>
                        <td><strong><?= $closing['closing_year'] ?></strong></td>
                        <td><?= format_date($closing['closing_date']) ?></td>
                        <td class="text-end"><?= format_currency($closing['total_revenue']) ?></td>
                        <td class="text-end"><?= format_currency($closing['total_expenses']) ?></td>
                        <td class="text-end"><?= format_currency($closing['total_profit']) ?></td>
                        <td class="text-end"><?= format_currency($closing['net_worth']) ?></td>
                        <td><?= ucfirst($closing['status']) ?></td>
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

<!-- Statistics Cards -->
<div class="row mb-4 no-print">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Closings</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_closings'] ?? 0 ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Average Profit</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($stats['avg_profit'] ?? 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Profitable Years</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['profitable_years'] ?? 0 ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-thumbs-up fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Best Year Profit</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= format_currency($stats['best_profit'] ?? 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-trophy fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Add Closing Form -->
    <div class="col-md-4 no-print">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Record Yearly Closing</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="save_closing" value="1">
                    <input type="hidden" name="id" id="closing_id" value="0">
                    
                    <div class="form-group">
                        <label>Year <span class="text-danger">*</span></label>
                        <select name="closing_year" class="form-control" required>
                            <option value="">Select Year</option>
                            <?php for($y = date('Y') + 10; $y >= 2000; $y--): ?>
                                <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Closing Date <span class="text-danger">*</span></label>
                        <input type="date" name="closing_date" class="form-control" required value="<?= date('Y-12-31') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Total Revenue <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="total_revenue" class="form-control" id="revenue" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Total Expenses <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="total_expenses" class="form-control" id="expenses" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Profit/Loss (Auto-calculated)</label>
                        <input type="text" class="form-control" id="profit" readonly>
                    </div>
                    
                    <hr>
                    
                    <div class="form-group">
                        <label>Total Assets <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="total_assets" class="form-control" id="assets" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Total Liabilities <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="total_liabilities" class="form-control" id="liabilities" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Net Worth (Auto-calculated)</label>
                        <input type="text" class="form-control" id="netWorth" readonly>
                    </div>
                    
                    <hr>
                    
                    <div class="form-group">
                        <label>Bank Balance <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="bank_balance" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Cash Balance <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="cash_balance" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="draft">Draft</option>
                            <option value="finalized">Finalized</option>
                            <option value="audited">Audited</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" name="save_closing" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> <span id="submit_btn_text">Record Closing</span>
                    </button>
                    <button type="button" id="cancel_edit" class="btn btn-secondary btn-block" style="display:none;">
                        Cancel Edit
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Closings List -->
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Yearly Closings History</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="closingsTable">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Date</th>
                                <th>Revenue</th>
                                <th>Expenses</th>
                                <th>Profit/Loss</th>
                                <th>Net Worth</th>
                                <th>Status</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($closings as $closing): ?>
                                <tr>
                                    <td><strong><?= $closing['closing_year'] ?></strong></td>
                                    <td><?= format_date($closing['closing_date']) ?></td>
                                    <td><?= format_currency($closing['total_revenue']) ?></td>
                                    <td><?= format_currency($closing['total_expenses']) ?></td>
                                    <td>
                                        <?php if ($closing['total_profit'] >= 0): ?>
                                            <span class="text-success font-weight-bold">
                                                <?= format_currency($closing['total_profit']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-danger font-weight-bold">
                                                <?= format_currency($closing['total_profit']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= format_currency($closing['net_worth']) ?></td>
                                    <td>
                                        <?php
                                        $badge_class = [
                                            'draft' => 'secondary',
                                            'finalized' => 'primary',
                                            'audited' => 'success'
                                        ];
                                        ?>
                                        <span class="badge badge-<?= $badge_class[$closing['status']] ?>">
                                            <?= ucfirst($closing['status']) ?>
                                        </span>
                                    </td>
                                    <td class="no-print">
                                        <button type="button" class="btn btn-sm btn-info" 
                                                onclick="viewDetails(<?= htmlspecialchars(json_encode($closing)) ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" 
                                                onclick="editClosing(<?= htmlspecialchars(json_encode($closing)) ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="printIndividual(<?= htmlspecialchars(json_encode($closing)) ?>)" title="Print">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="deleteClosing(<?= $closing['id'] ?>, <?= $closing['closing_year'] ?>)" title="Delete">
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

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yearly Closing Details</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Printable Individual Section -->
<div id="printableSection" style="display:none;">
    <div class="print-container">
        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-cell">
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

        <div class="report-main-title">Yearly Financial Closing Report (Year: <span id="print_year"></span>)</div>
        
        <table class="print-table">
            <tr><th width="40%">Closing Date</th><td id="print_date"></td></tr>
            <tr><th>Total Revenue</th><td id="print_revenue"></td></tr>
            <tr><th>Total Expenses</th><td id="print_expenses"></td></tr>
            <tr><th>Profit / Loss</th><td id="print_profit" style="font-weight:bold;"></td></tr>
            <tr><td colspan="2" style="background:#f8f9fa;"></td></tr>
            <tr><th>Total Assets</th><td id="print_assets"></td></tr>
            <tr><th>Total Liabilities</th><td id="print_liabilities"></td></tr>
            <tr><th>Net Worth</th><td id="print_net_worth" style="font-weight:bold;"></td></tr>
            <tr><td colspan="2" style="background:#f8f9fa;"></td></tr>
            <tr><th>Bank Balance</th><td id="print_bank"></td></tr>
            <tr><th>Cash Balance</th><td id="print_cash"></td></tr>
            <tr><th>Status</th><td id="print_status"></td></tr>
            <tr><th>Notes</th><td id="print_notes"></td></tr>
        </table>
        
        <!-- Print Footer -->
        <div class="print-footer clearfix">
            <div class="footer-left">Authorized Signature</div>
            <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_id" id="delete_id">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#closingsTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]]
    });
    
    // Auto-calculate profit
    $('#revenue, #expenses').on('input', function() {
        const revenue = parseFloat($('#revenue').val()) || 0;
        const expenses = parseFloat($('#expenses').val()) || 0;
        const profit = revenue - expenses;
        $('#profit').val(profit.toFixed(2));
        $('#profit').css('color', profit >= 0 ? 'green' : 'red');
    });
    
    // Auto-calculate net worth
    $('#assets, #liabilities').on('input', function() {
        const assets = parseFloat($('#assets').val()) || 0;
        const liabilities = parseFloat($('#liabilities').val()) || 0;
        const netWorth = assets - liabilities;
        $('#netWorth').val(netWorth.toFixed(2));
        $('#netWorth').css('color', netWorth >= 0 ? 'green' : 'red');
    });
});

function viewDetails(closing) {
    const html = `
        <table class="table table-bordered">
            <tr><th>Year</th><td>${closing.closing_year}</td></tr>
            <tr><th>Closing Date</th><td>${closing.closing_date}</td></tr>
            <tr><th>Total Revenue</th><td>${formatCurrency(closing.total_revenue)}</td></tr>
            <tr><th>Total Expenses</th><td>${formatCurrency(closing.total_expenses)}</td></tr>
            <tr><th>Profit/Loss</th><td class="${closing.total_profit >= 0 ? 'text-success' : 'text-danger'}">${formatCurrency(closing.total_profit)}</td></tr>
            <tr><th>Total Assets</th><td>${formatCurrency(closing.total_assets)}</td></tr>
            <tr><th>Total Liabilities</th><td>${formatCurrency(closing.total_liabilities)}</td></tr>
            <tr><th>Net Worth</th><td class="${closing.net_worth >= 0 ? 'text-success' : 'text-danger'}">${formatCurrency(closing.net_worth)}</td></tr>
            <tr><th>Bank Balance</th><td>${formatCurrency(closing.bank_balance)}</td></tr>
            <tr><th>Cash Balance</th><td>${formatCurrency(closing.cash_balance)}</td></tr>
            <tr><th>Status</th><td><span class="badge badge-primary">${closing.status}</span></td></tr>
            <tr><th>Closed By</th><td>${closing.closed_by_name || 'N/A'}</td></tr>
            <tr><th>Notes</th><td>${closing.notes || 'No notes'}</td></tr>
        </table>
    `;
    $('#modalBody').html(html);
    $('#detailsModal').modal('show');
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: '<?= CURRENCY_CODE ?>',
        minimumFractionDigits: 2
    }).format(amount);
}

function editClosing(closing) {
    $('#closing_id').val(closing.id);
    $('[name="closing_year"]').val(closing.closing_year);
    $('[name="closing_date"]').val(closing.closing_date);
    $('[name="total_revenue"]').val(closing.total_revenue);
    $('[name="total_expenses"]').val(closing.total_expenses);
    $('[name="total_assets"]').val(closing.total_assets);
    $('[name="total_liabilities"]').val(closing.total_liabilities);
    $('[name="bank_balance"]').val(closing.bank_balance);
    $('[name="cash_balance"]').val(closing.cash_balance);
    $('[name="status"]').val(closing.status);
    $('[name="notes"]').val(closing.notes);
    
    // Trigger auto-calculate
    $('#revenue').trigger('input');
    $('#assets').trigger('input');
    
    // UI Update
    $('#submit_btn_text').text('Update Closing');
    $('#cancel_edit').show();
    $('.card-header .text-primary').text('Edit Records for ' + closing.closing_year);
    
    // Scroll to form
    $('html, body').animate({
        scrollTop: $(".col-md-4").offset().top - 20
    }, 500);
}

$('#cancel_edit').on('click', function() {
    $('#closing_id').val('0');
    $('[name="closing_year"]').val('');
    $('[name="closing_date"]').val('<?= date('Y-12-31') ?>');
    $('[name="total_revenue"]').val('');
    $('[name="total_expenses"]').val('');
    $('[name="total_assets"]').val('');
    $('[name="total_liabilities"]').val('');
    $('[name="bank_balance"]').val('');
    $('[name="cash_balance"]').val('');
    $('[name="status"]').val('draft');
    $('[name="notes"]').val('');
    
    $('#profit').val('');
    $('#netWorth').val('');
    
    $('#submit_btn_text').text('Record Closing');
    $('#cancel_edit').hide();
    $('.card-header .text-primary').text('Record Yearly Closing');
});

function printIndividual(closing) {
    $('#print_year').text(closing.closing_year);
    $('#print_date').text(formatDateStr(closing.closing_date));
    $('#print_revenue').text(formatCurrency(closing.total_revenue));
    $('#print_expenses').text(formatCurrency(closing.total_expenses));
    $('#print_profit').text(formatCurrency(closing.total_profit)).css('color', closing.total_profit >= 0 ? 'green' : 'red');
    $('#print_assets').text(formatCurrency(closing.total_assets));
    $('#print_liabilities').text(formatCurrency(closing.total_liabilities));
    $('#print_net_worth').text(formatCurrency(closing.net_worth)).css('color', closing.net_worth >= 0 ? 'green' : 'red');
    $('#print_bank').text(formatCurrency(closing.bank_balance));
    $('#print_cash').text(formatCurrency(closing.cash_balance));
    $('#print_status').text(closing.status.toUpperCase());
    $('#print_notes').text(closing.notes || 'N/A');

    $('body').addClass('printing-individual');
    window.print();
    // Use a small timeout to ensure the class is removed after the print dialog is handled
    setTimeout(function() {
        $('body').removeClass('printing-individual');
    }, 500);
}

function formatDateStr(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function deleteClosing(id, year) {
    if (confirm('Are you sure you want to delete the yearly closing for ' + year + '?')) {
        $('#delete_id').val(id);
        $('#deleteForm').submit();
    }
}
</script>
