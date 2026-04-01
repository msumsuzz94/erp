<?php
/**
 * Advance RMA Search
 * Implementation based on legacy reference image with modern styling
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$page_title = 'Advance RMA Search';
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .rma-search-header {
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        color: white;
        padding: 15px 25px;
        border-radius: 8px 8px 0 0;
        margin-bottom: 20px;
    }
    .rma-search-header h2 {
        font-style: italic;
        font-weight: bold;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        margin: 0;
    }
    .rma-container {
        background-color: #f0f7ff;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #c9e2ff;
    }
    .rma-table-card {
        background: white;
        border-radius: 0;
        border: 1px solid #adb5bd;
        margin-bottom: 20px;
        height: 100%;
    }
    .rma-table-card .card-header {
        background-color: #3182ce;
        color: white;
        font-weight: bold;
        padding: 5px 10px;
        border-radius: 0;
        font-size: 0.9rem;
    }
    .rma-table-card .table {
        margin-bottom: 0;
    }
    .rma-table-card .table th {
        background-color: #e2e8f0;
        border-bottom: 2px solid #cbd5e0;
        font-size: 0.8rem;
        padding: 5px;
    }
    .rma-table-card .table td {
        font-size: 0.8rem;
        padding: 5px;
        height: 30px;
    }
    .search-row {
        background: white;
        padding: 10px;
        border: 1px solid #cbd5e0;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .search-btn {
        background-color: #cbd5e0;
        border: 1px solid #a0aec0;
        color: #2d3748;
        font-weight: bold;
        padding: 5px 20px;
    }
    .search-btn:hover {
        background-color: #a0aec0;
    }

    /* Dark Mode Overrides */
    [data-theme="dark"] .rma-container {
        background-color: #1e293b;
        border-color: #334155;
    }
    [data-theme="dark"] .search-row {
        background: #0f172a;
        border-color: #334155;
    }
    [data-theme="dark"] .search-row label {
        color: #e2e8f0 !important;
    }
    [data-theme="dark"] .search-row .form-control {
        background-color: #1e293b;
        border-color: #475569;
        color: #e2e8f0;
    }
    [data-theme="dark"] .search-btn {
        background-color: #334155;
        border-color: #475569;
        color: #e2e8f0;
    }
    [data-theme="dark"] .search-btn:hover {
        background-color: #475569;
    }
    [data-theme="dark"] .rma-table-card {
        background: #0f172a;
        border-color: #334155;
    }
    [data-theme="dark"] .rma-table-card .table th {
        background-color: #1e293b !important;
        border-color: #334155;
        color: #94a3b8;
    }
    [data-theme="dark"] .rma-table-card .table td {
        background-color: #0f172a;
        border-color: #1e293b;
        color: #e2e8f0;
    }
    [data-theme="dark"] .rma-table-card .table-striped tbody tr:nth-of-type(odd) td {
        background-color: #1e293b;
    }
</style>

<div class="rma-search-header">
    <h2>Advance RMA Search</h2>
</div>

<div class="rma-container shadow-sm">
    <!-- Search Box -->
    <div class="search-row">
        <label class="mb-0 font-weight-bold">Serial / RMA Number</label>
        <input type="text" id="serial_number" class="form-control" style="width: 300px;" placeholder="Enter Serial or RMA Number...">
        <button class="btn search-btn shadow-sm" id="btn_search">Search</button>
        <button class="btn search-btn shadow-sm" onclick="window.location.reload()">Close</button>
    </div>

    <div class="row">
        <!-- Complain Section -->
        <div class="col-md-6 mb-4">
            <div class="rma-table-card card">
                <div class="card-header">Complain</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table_complain">
                        <thead>
                            <tr>
                                <th>Complain No</th>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Serial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Product Testing Section -->
        <div class="col-md-6 mb-4">
            <div class="rma-table-card card">
                <div class="card-header">Product Testing</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table_testing">
                        <thead>
                            <tr>
                                <th>Complain No</th>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Serial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Replace Product Out Section -->
        <div class="col-md-6 mb-4">
            <div class="rma-table-card card">
                <div class="card-header">Replace Product Out</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table_replace_out">
                        <thead>
                            <tr>
                                <th>Replace Out No</th>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Serial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Replace Product In Section -->
        <div class="col-md-6 mb-4">
            <div class="rma-table-card card">
                <div class="card-header">Replace Product In</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table_replace_in">
                        <thead>
                            <tr>
                                <th>Replace In No</th>
                                <th>Date</th>
                                <th>Old Product</th>
                                <th>Old Serial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Replace Product Delivery Section -->
        <div class="col-md-6 mb-4">
            <div class="rma-table-card card">
                <div class="card-header">Replace Product Delivery</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table_delivery_old">
                        <thead>
                            <tr>
                                <th>Replace Delivery No</th>
                                <th>Date</th>
                                <th>Old Product</th>
                                <th>Old Serial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Replace Product In (New Serial) Section -->
        <div class="col-md-6 mb-4">
            <div class="rma-table-card card">
                <div class="card-header">Replace Product In (New Serial)</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table_replace_in_new">
                        <thead>
                            <tr>
                                <th>Replace In No</th>
                                <th>Date</th>
                                <th>New Product</th>
                                <th>New Serial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Replace Product Delivery (New Serial) Section -->
        <div class="col-md-6 mb-4">
            <div class="rma-table-card card">
                <div class="card-header">Replace Product Delivery (New Serial)</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table_delivery_new">
                        <thead>
                            <tr>
                                <th>Replace Delivery No</th>
                                <th>Date</th>
                                <th>New Product</th>
                                <th>New Serial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
$(document).ready(function() {
    function searchRMA() {
        const serial = $('#serial_number').val().trim();
        if (serial === '') return;

        $('#btn_search').prop('disabled', true).text('Searching...');

        $.get('../../api/warranty/advance-rma-search.php', { serial: serial }, function(res) {
            $('#btn_search').prop('disabled', false).text('Search');
            
            if (res.status) {
                const data = res.data;
                
                // 1. Complain
                const complain = data.complain;
                if (complain) {
                    $('#table_complain tbody').html(`<tr>
                        <td>${complain.rma_number}</td>
                        <td>${complain.created_date}</td>
                        <td>${complain.product_name}</td>
                        <td>${complain.serial_number}</td>
                    </tr>`);
                } else {
                    $('#table_complain tbody').html('<tr><td colspan="4" class="text-center">No data</td></tr>');
                }

                // 2. Testing
                if (data.testing_date) {
                    $('#table_testing tbody').html(`<tr>
                        <td>${complain.rma_number}</td>
                        <td>${data.testing_date}</td>
                        <td>${complain.product_name}</td>
                        <td>${complain.serial_number}</td>
                    </tr>`);
                } else {
                    $('#table_testing tbody').html('<tr><td colspan="4" class="text-center">No data</td></tr>');
                }

                // 3. Replace Out
                if (data.replace_out_number) {
                    $('#table_replace_out tbody').html(`<tr>
                        <td>${data.replace_out_number}</td>
                        <td>${data.replace_out_date}</td>
                        <td>${complain.product_name}</td>
                        <td>${complain.serial_number}</td>
                    </tr>`);
                } else {
                    $('#table_replace_out tbody').html('<tr><td colspan="4" class="text-center">No data</td></tr>');
                }

                // 4. Replace In (Old/Repaired)
                if (data.replace_in_number && data.replace_in_type === 'repair') {
                    $('#table_replace_in tbody').html(`<tr>
                        <td>${data.replace_in_number}</td>
                        <td>${data.replace_in_date}</td>
                        <td>${complain.product_name}</td>
                        <td>${complain.serial_number}</td>
                    </tr>`);
                } else {
                    $('#table_replace_in tbody').html('<tr><td colspan="4" class="text-center">No data</td></tr>');
                }

                // 5. Delivery (Old)
                if (data.delivery_number && data.replace_in_type === 'repair') {
                    $('#table_delivery_old tbody').html(`<tr>
                        <td>${data.delivery_number}</td>
                        <td>${data.delivery_date}</td>
                        <td>${complain.product_name}</td>
                        <td>${complain.serial_number}</td>
                    </tr>`);
                } else {
                    $('#table_delivery_old tbody').html('<tr><td colspan="4" class="text-center">No data</td></tr>');
                }

                // 6. Replace In (New)
                if (data.replace_in_number && data.replace_in_type === 'new') {
                    $('#table_replace_in_new tbody').html(`<tr>
                        <td>${data.replace_in_number}</td>
                        <td>${data.replace_in_date}</td>
                        <td>${data.new_product_name || complain.product_name}</td>
                        <td>${data.new_serial_number}</td>
                    </tr>`);
                } else {
                    $('#table_replace_in_new tbody').html('<tr><td colspan="4" class="text-center">No data</td></tr>');
                }

                // 7. Delivery (New)
                if (data.delivery_number && data.replace_in_type === 'new') {
                    $('#table_delivery_new tbody').html(`<tr>
                        <td>${data.delivery_number}</td>
                        <td>${data.delivery_date}</td>
                        <td>${data.new_product_name || complain.product_name}</td>
                        <td>${data.new_serial_number}</td>
                    </tr>`);
                } else {
                    $('#table_delivery_new tbody').html('<tr><td colspan="4" class="text-center">No data</td></tr>');
                }

            } else {
                alert(res.message);
                // Clear all tables
                $('table tbody').html('<tr><td colspan="4" class="text-center">No data</td></tr>');
            }
        });
    }

    $('#btn_search').click(searchRMA);
    $('#serial_number').on('keypress', function(e) {
        if (e.which == 13) searchRMA();
    });
});
</script>
