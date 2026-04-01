<?php
/**
 * Salary Management Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle salary payment
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $salary_data = [
            'staff_id' => (int)$_POST['staff_id'],
            'month' => (int)$_POST['month'],
            'year' => (int)$_POST['year'],
            'basic_salary' => (float)$_POST['basic_salary'],
            'allowances' => (float)$_POST['allowances'],
            'deductions' => (float)$_POST['deductions'],
            'net_salary' => (float)$_POST['basic_salary'] + (float)$_POST['allowances'] - (float)$_POST['deductions'],
            'payment_date' => clean_input($_POST['payment_date']),
            'payment_method' => clean_input($_POST['payment_method']),
            'status' => 'paid'
        ];
        
        if (db_insert('salaries', $salary_data)) {
            // Get the ID of the inserted salary to print it
            $salary_id = db_query("SELECT id FROM salaries WHERE staff_id=? AND month=? AND year=? ORDER BY id DESC LIMIT 1", [$salary_data['staff_id'], $salary_data['month'], $salary_data['year']])[0]['id'] ?? 0;
            
            log_activity(get_current_user_id(), 'salary_payment', "Paid salary for staff ID: {$salary_data['staff_id']}");
            
            if ($salary_id) {
                $_SESSION['print_salary_id'] = $salary_id;
            }
            
            redirect_with_message($_SERVER['PHP_SELF'], 'Salary recorded successfully', 'success');
        }
    }
}

// Get all staff
$staff_list = db_select('staff', ['status' => 'active'], '*', 'name ASC');

// Get current month/year
$current_month = date('n');
$current_year = date('Y');

// Get salary records
$sql = "SELECT s.*, st.name as staff_name 
        FROM salaries s 
        INNER JOIN staff st ON s.staff_id = st.id 
        ORDER BY s.year DESC, s.month DESC, st.name ASC 
        LIMIT 100";
$salaries = db_query($sql);

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$page_title = 'Salary Management';
$page_actions = '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#salaryModal"><i class="fas fa-plus"></i> Pay Salary</button>';
include __DIR__ . '/../../templates/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Salary Records</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="salariesTable">
                <thead>
                    <tr>
                        <th>Staff Name</th>
                        <th>Month/Year</th>
                        <th>Basic Salary</th>
                        <th>Allowances</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                        <th>Payment Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($salaries)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No salary records found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($salaries as $salary): ?>
                            <tr>
                                <td><?= htmlspecialchars($salary['staff_name']) ?></td>
                                <td><?= $months[$salary['month']] ?> <?= $salary['year'] ?></td>
                                <td><?= format_currency($salary['basic_salary']) ?></td>
                                <td><?= format_currency($salary['allowances']) ?></td>
                                <td><?= format_currency($salary['deductions']) ?></td>
                                <td><strong><?= format_currency($salary['net_salary']) ?></strong></td>
                                <td><?= $salary['payment_date'] ? format_date($salary['payment_date']) : '-' ?></td>
                                <td>
                                    <span class="badge bg-<?= $salary['status'] === 'paid' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($salary['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/modules/hr/print-salary.php?id=<?= $salary['id'] ?>" target="_blank" class="btn btn-sm btn-info" title="Print Payslip">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Salary Modal -->
<div class="modal fade" id="salaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="salaryForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Pay Salary</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>Staff <span class="text-danger">*</span></label>
                                <select name="staff_id" class="form-control" required>
                                    <option value="">Select Staff</option>
                                    <?php foreach ($staff_list as $staff): ?>
                                        <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group mb-3">
                                <label>Month <span class="text-danger">*</span></label>
                                <select name="month" class="form-control" required>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?= $num ?>" <?= $num == $current_month ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-3">
                                <label>Year <span class="text-danger">*</span></label>
                                <input type="number" name="year" class="form-control" value="<?= $current_year ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-3">
                                <label>Working Days</label>
                                <input type="number" name="monthly_working_days" id="monthly_working_days" class="form-control" value="30" min="1" max="31">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>Basic Salary (<span id="attendance_info" class="text-info font-weight-bold">Select staff to compute</span>) <span class="text-danger">*</span></label>
                                <input type="number" name="basic_salary" id="basic_salary" class="form-control" step="0.01" required readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>Allowances</label>
                                <input type="number" name="allowances" id="allowances" class="form-control" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <label>Deductions</label>
                                <input type="number" name="deductions" id="deductions" class="form-control" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label>Net Salary</label>
                        <input type="text" id="net_salary" class="form-control" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label>Payment Method <span class="text-danger">*</span></label>
                                <select name="payment_method" class="form-control" required>
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Pay Salary</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
$(document).ready(function() {
    // Only initialize DataTable if there are records
    <?php if (!empty($salaries)): ?>
    $('#salariesTable').DataTable({
        "pageLength": 25,
        "order": [[1, "desc"]]
    });
    <?php endif; ?>
    
    $('#basic_salary, #allowances, #deductions').on('input', calculateNetSalary);
    
    // Store latest attendance res globally for re-calculation
    var lastAttendanceData = null;

    function applySalaryCalculation() {
        var daysInput = parseInt($('#monthly_working_days').val()) || 30;
        if (lastAttendanceData && lastAttendanceData.success) {
            $('#attendance_info').html(lastAttendanceData.present_days + ' days present');
            if (lastAttendanceData.standard_basic_salary > 0) {
                var computed_basic = (lastAttendanceData.standard_basic_salary / daysInput) * lastAttendanceData.present_days;
                $('#basic_salary').val(computed_basic.toFixed(2));
                calculateNetSalary();
            } else {
                $('#basic_salary').val('0.00');
                calculateNetSalary();
            }
        }
    }
    
    $('#monthly_working_days').on('input', applySalaryCalculation);
    
    // Auto-compute basic salary from attendance
    $('[name="staff_id"], [name="month"], [name="year"]').on('change select2:select', function() {
        var staff_id = $('[name="staff_id"]').val();
        var month = $('[name="month"]').val();
        var year = $('[name="year"]').val();
        
        if (staff_id && month && year) {
            $('#attendance_info').text('Calculating...');
            $.ajax({
                url: '<?= BASE_URL ?>/api/hr/calculate-salary.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({staff_id: staff_id, month: month, year: year}),
                success: function(res) {
                    lastAttendanceData = res;
                    if (lastAttendanceData.success) {
                        if (lastAttendanceData.monthly_working_days > 0) {
                            $('#monthly_working_days').val(lastAttendanceData.monthly_working_days);
                        }
                        applySalaryCalculation();
                    } else {
                        $('#attendance_info').text('Error fetching attendance: ' + res.message);
                    }
                },
                error: function(err) {
                    $('#attendance_info').text('AJAX Error occurred');
                }
            });
        } else {
            $('#attendance_info').text('Select staff to compute');
            $('#basic_salary').val('');
            lastAttendanceData = null;
            calculateNetSalary();
        }
    });
});

function calculateNetSalary() {
    var basic = parseFloat($('#basic_salary').val()) || 0;
    var allowances = parseFloat($('#allowances').val()) || 0;
    var deductions = parseFloat($('#deductions').val()) || 0;
    var net = basic + allowances - deductions;
    
    $('#net_salary').val('<?= APP_CURRENCY_SYMBOL ?>' + net.toFixed(2));
}
<?php
// Handle auto-print after payment
if (isset($_SESSION['print_salary_id'])) {
    $print_id = $_SESSION['print_salary_id'];
    unset($_SESSION['print_salary_id']);
    echo "<script>
        $(document).ready(function() {
            window.open('" . BASE_URL . "/modules/hr/print-salary.php?id=" . $print_id . "', '_blank');
        });
    </script>";
}
?>
</script>
<?php
$additional_js = ob_get_clean();
include __DIR__ . '/../../templates/footer.php';
?>
