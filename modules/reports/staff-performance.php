<?php

/**
 * Staff Performance Tracking
 * Admin can view staff sales performance with filters, drill-down, and Top 10
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

$user_id = get_current_user_id();
if (!is_admin($user_id) && !user_has_menu_access($user_id, 'hr') && !user_has_menu_access($user_id, 'reports')) {
    redirect_with_message('../../index.php', 'Insufficient permissions to view staff performance.', 'danger');
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$preselect_staff_id = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : '';

$staff = db_query("SELECT id, name FROM staff WHERE status = 'active' ORDER BY name ASC") ?: [];
$cs = APP_CURRENCY_SYMBOL;

// Top 10 this month
$top10 = db_query(
    "SELECT st.name as username, COUNT(DISTINCT s.id) as orders, SUM(s.total_amount) as sales
    FROM sales s JOIN staff st ON s.staff_id = st.id
    WHERE s.sale_date BETWEEN ? AND ? AND s.status = 'completed'
    GROUP BY st.id, st.name ORDER BY sales DESC LIMIT 10",
    [date('Y-m-01'), date('Y-m-t')]
) ?: [];

$page_title = 'Staff Performance';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    /* Quick Filter Buttons */
    .quick-filters .btn {
        border-radius: 20px;
        font-size: 13px;
        padding: 6px 18px;
        font-weight: 600;
    }

    .quick-filters .btn.active {
        box-shadow: 0 2px 8px rgba(78, 115, 223, 0.3);
    }

    /* Top 10 Cards */
    .top10-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 10px;
    }

    .top10-card {
        padding: 12px;
        border-radius: 10px;
        background: linear-gradient(135deg, #f8f9fc, #eef1f8);
        border: 1px solid #e3e6f0;
        position: relative;
        transition: all 0.2s;
    }

    .top10-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .top10-card .rank {
        position: absolute;
        top: 8px;
        right: 10px;
        font-size: 11px;
        font-weight: 800;
        color: #b0b5c5;
    }

    .top10-card .rank.gold {
        color: #f6c23e;
        font-size: 14px;
    }

    .top10-card .rank.silver {
        color: #858796;
        font-size: 13px;
    }

    .top10-card .rank.bronze {
        color: #cd7f32;
        font-size: 12px;
    }

    .top10-card .t-name {
        font-weight: 700;
        font-size: 14px;
        color: #2e3a59;
    }

    .top10-card .t-sales {
        color: #4e73df;
        font-weight: 700;
        font-size: 15px;
    }

    .top10-card .t-orders {
        font-size: 11px;
        color: #858796;
    }

    /* Performance Table */
    .perf-table .staff-link {
        color: #4e73df;
        cursor: pointer;
        font-weight: 600;
        text-decoration: underline;
    }

    .perf-table .staff-link:hover {
        color: #2e59d9;
    }

    /* Modal detail */
    .detail-items-table th {
        font-size: 12px;
        text-transform: uppercase;
    }

    .profit-positive {
        color: #1cc88a;
        font-weight: 700;
    }

    .profit-negative {
        color: #e74a3b;
        font-weight: 700;
    }

    /* Print Styles */
    @media print {
        @page {
            margin: 0.25cm;
        }

        body {
            background: #fff !important;
            font-size: 12px;
        }

        #sidebar,
        .topbar,
        .navbar,
        .quick-filters,
        #customDateRange,
        .card.shadow.mb-3,
        .col-lg-4,
        .btn,
        .no-print,
        footer {
            display: none !important;
        }

        #content-wrapper,
        #content,
        .container-fluid {
            padding: 0 !important;
            margin: 0 !important;
        }

        .col-lg-8 {
            width: 100% !important;
            max-width: 100% !important;
            flex: 0 0 100% !important;
        }

        .card {
            box-shadow: none !important;
            border: none !important;
        }

        .card-header {
            background: none !important;
            border-bottom: 2px solid #333 !important;
        }

        .perf-table {
            font-size: 11px;
        }

        .perf-table .staff-link {
            color: #000 !important;
            text-decoration: none !important;
        }

        a[href]:after {
            content: none !important;
        }

        .print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }

        .print-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .print-header p {
            margin: 2px 0;
            font-size: 11px;
            color: #555;
        }

        .print-footer-fixed {
            display: block !important;
            position: fixed;
            bottom: 0;
            left: 0.25cm;
            right: 0.25cm;
            border-top: 1px solid #333;
            padding-top: 3px;
            font-size: 8px;
        }

        .print-footer-fixed .pf-l {
            float: left;
            font-weight: bold;
        }

        .print-footer-fixed .pf-r {
            float: right;
        }
    }
</style>

<!-- Print Header (hidden on screen) -->
<div class="print-header" style="display:none;">
    <h2><?= htmlspecialchars(BUSINESS_NAME) ?></h2>
    <p><?= htmlspecialchars(BUSINESS_ADDRESS) ?> | <?= htmlspecialchars(BUSINESS_PHONE) ?></p>
    <p><strong>Staff Performance Report</strong> | <span id="printDateRange"></span></p>
</div>
<div class="print-footer-fixed" style="display:none;">
    <span class="pf-l">ERP Developed By : CITNBD | 01976-793351</span>
    <span class="pf-r">Printed: <?= date('d-M-Y, h:i A') ?></span>
</div>

<!-- Quick Filters -->
<div class="card shadow mb-3">
    <div class="card-body py-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="quick-filters d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-primary" data-range="today">📅 Today</button>
                    <button class="btn btn-outline-primary" data-range="week">📆 This Week</button>
                    <button class="btn btn-primary active" data-range="month">🗓️ This Month</button>
                    <button class="btn btn-outline-primary" data-range="custom">⚙️ Custom</button>
                </div>
            </div>
            <div class="col-md-6">
                <div id="customDateRange" class="row g-2" style="display:none;">
                    <div class="col-4">
                        <input type="date" id="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-4">
                        <input type="date" id="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-2">
                        <select id="staff_id" class="form-select form-select-sm">
                            <option value="">All Staff</option>
                            <?php foreach ($staff as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($preselect_staff_id == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-2">
                        <button id="applyFilter" class="btn btn-sm btn-primary w-100"><i class="fas fa-search"></i> Apply</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Table -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar"></i> Performance Results</h6>
                <div>
                    <span class="badge bg-light text-dark me-2" id="dateRangeLabel"></span>
                    <button class="btn btn-sm btn-outline-secondary no-print" onclick="printReport()"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover perf-table mb-0">
                        <thead>
                            <tr class="table-light">
                                <th>#</th>
                                <th>Staff</th>
                                <th class="text-center">Orders</th>
                                <th class="text-end">Total Sales</th>
                                <th class="text-end">Profit</th>
                                <th class="text-end">Commission (2%)</th>
                            </tr>
                        </thead>
                        <tbody id="perfBody">
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="spinner-border text-primary"></div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot id="perfFoot" style="display:none;" class="table-light">
                            <tr class="fw-bold">
                                <td colspan="2">Total</td>
                                <td class="text-center" id="sumOrders">0</td>
                                <td class="text-end" id="sumSales">0</td>
                                <td class="text-end" id="sumProfit">0</td>
                                <td class="text-end" id="sumComm">0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 10 Sidebar -->
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-trophy"></i> Top 10 Staff (This Month)</h6>
            </div>
            <div class="card-body p-3">
                <?php if (empty($top10)): ?>
                    <p class="text-muted text-center">No sales data this month</p>
                <?php else: ?>
                    <?php $rank = 1;
                    foreach ($top10 as $t):
                        $rc = $rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : ($rank == 3 ? 'bronze' : ''));
                    ?>
                        <div class="top10-card mb-2">
                            <span class="rank <?= $rc ?>">#<?= $rank ?></span>
                            <div class="t-name"><?= htmlspecialchars($t['username']) ?></div>
                            <div class="t-sales"><?= $cs ?><?= number_format((float)$t['sales'], 0) ?></div>
                            <div class="t-orders"><?= (int)$t['orders'] ?> orders</div>
                        </div>
                    <?php $rank++;
                    endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Staff Detail Modal -->
<div class="modal fade" id="staffDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-chart"></i> <span id="detailStaffName"></span> - Sales Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    const CS = '<?= $cs ?>';
    let currentRange = 'month';

    $(document).ready(function() {
        // Quick filter buttons
        $('.quick-filters .btn').click(function() {
            $('.quick-filters .btn').removeClass('active btn-primary').addClass('btn-outline-primary');
            $(this).addClass('active btn-primary').removeClass('btn-outline-primary');
            currentRange = $(this).data('range');

            if (currentRange === 'custom') {
                $('#customDateRange').slideDown(200);
            } else {
                $('#customDateRange').slideUp(200);
                const dates = getDateRange(currentRange);
                $('#start_date').val(dates.start);
                $('#end_date').val(dates.end);
                fetchData();
            }
        });

        $('#applyFilter').click(fetchData);
        fetchData(); // Initial load

        // Staff name click -> drill-down
        $(document).on('click', '.staff-link', function() {
            const userId = $(this).data('uid');
            const username = $(this).text();
            showStaffDetail(userId, username);
        });
    });

    function getDateRange(range) {
        const today = new Date();
        let start, end;
        const fmt = d => d.toISOString().split('T')[0];

        if (range === 'today') {
            start = end = fmt(today);
        } else if (range === 'week') {
            const day = today.getDay();
            const mon = new Date(today);
            mon.setDate(today.getDate() - (day === 0 ? 6 : day - 1));
            start = fmt(mon);
            end = fmt(today);
        } else {
            start = fmt(new Date(today.getFullYear(), today.getMonth(), 1));
            end = fmt(new Date(today.getFullYear(), today.getMonth() + 1, 0));
        }
        return {
            start,
            end
        };
    }

    function fetchData() {
        const sd = $('#start_date').val(),
            ed = $('#end_date').val(),
            sid = $('#staff_id').val();
        $('#dateRangeLabel').text(sd + ' → ' + ed);
        $('#perfBody').html('<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>');

        $.get('../../api/reports/staff-performance.php', {
            start_date: sd,
            end_date: ed,
            staff_id: sid
        }, function(res) {
            if (res.status && res.data) {
                if (res.data.length === 0) {
                    $('#perfBody').html('<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x d-block mb-2"></i>No sales data found for this period</td></tr>');
                    $('#perfFoot').hide();
                    return;
                }
                let html = '',
                    tO = 0,
                    tS = 0,
                    tP = 0,
                    tC = 0;
                res.data.forEach((r, i) => {
                    const sales = parseFloat(r.total_sales) || 0;
                    const profit = parseFloat(r.profit) || 0;
                    const comm = sales * 0.02;
                    const orders = parseInt(r.total_orders) || 0;
                    tO += orders;
                    tS += sales;
                    tP += profit;
                    tC += comm;

                    html += `<tr>
                    <td>${i + 1}</td>
                    <td><a class="staff-link" data-uid="${r.user_id}">${r.username}</a></td>
                    <td class="text-center">${orders}</td>
                    <td class="text-end">${CS}${sales.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                    <td class="text-end ${profit >= 0 ? 'profit-positive' : 'profit-negative'}">${CS}${profit.toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                    <td class="text-end">${CS}${comm.toFixed(2)}</td>
                </tr>`;
                });
                $('#perfBody').html(html);
                $('#sumOrders').text(tO);
                $('#sumSales').text(CS + tS.toLocaleString(undefined, {
                    minimumFractionDigits: 2
                }));
                $('#sumProfit').text(CS + tP.toLocaleString(undefined, {
                    minimumFractionDigits: 2
                }));
                $('#sumComm').text(CS + tC.toFixed(2));
                $('#perfFoot').show();
            } else {
                $('#perfBody').html(`<tr><td colspan="6" class="text-center text-danger">${res.message || 'API Error'}</td></tr>`);
            }
        }).fail(function() {
            $('#perfBody').html('<tr><td colspan="6" class="text-center text-danger">Network error</td></tr>');
        });
    }

    function showStaffDetail(userId, username) {
        $('#detailStaffName').text(username);
        $('#detailBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        $('#staffDetailModal').modal('show');

        const sd = $('#start_date').val(),
            ed = $('#end_date').val();
        $.get('../../api/reports/staff-performance.php', {
            start_date: sd,
            end_date: ed,
            staff_id: userId,
            detail: 1
        }, function(res) {
            if (res.status && res.detail) {
                let html = '<div class="table-responsive"><table class="table table-sm table-bordered detail-items-table"><thead><tr class="table-light">';
                html += '<th>#</th><th>Product</th><th>Category</th><th class="text-center">Qty Sold</th>';
                html += '<th class="text-end">Sales</th><th class="text-end">Cost</th><th class="text-end">Profit</th></tr></thead><tbody>';

                let totalProfit = 0,
                    totalSales = 0;
                if (res.detail.length === 0) {
                    html += '<tr><td colspan="7" class="text-center text-muted">No item data</td></tr>';
                } else {
                    res.detail.forEach((d, i) => {
                        const sales = parseFloat(d.total_sales) || 0;
                        const cost = parseFloat(d.total_cost) || 0;
                        const profit = sales - cost;
                        totalSales += sales;
                        totalProfit += profit;
                        html += `<tr>
                        <td>${i + 1}</td>
                        <td><strong>${d.product_name}</strong></td>
                        <td>${d.category_name || '-'}</td>
                        <td class="text-center">${d.total_qty}</td>
                        <td class="text-end">${CS}${sales.toFixed(2)}</td>
                        <td class="text-end">${CS}${cost.toFixed(2)}</td>
                        <td class="text-end ${profit >= 0 ? 'profit-positive' : 'profit-negative'}">${CS}${profit.toFixed(2)}</td>
                    </tr>`;
                    });
                }
                html += '</tbody><tfoot class="table-light"><tr class="fw-bold">';
                html += `<td colspan="4">Grand Total</td><td class="text-end">${CS}${totalSales.toFixed(2)}</td><td></td>`;
                html += `<td class="text-end ${totalProfit >= 0 ? 'profit-positive' : 'profit-negative'}">${CS}${totalProfit.toFixed(2)}</td>`;
                html += '</tr></tfoot></table></div>';
                $('#detailBody').html(html);
            } else {
                $('#detailBody').html('<p class="text-danger text-center">Failed to load details</p>');
            }
        }).fail(function() {
            $('#detailBody').html('<p class="text-danger text-center">Network error</p>');
        });
    }

    function printReport() {
        $('#printDateRange').text($('#start_date').val() + ' to ' + $('#end_date').val());
        window.print();
    }
</script>
