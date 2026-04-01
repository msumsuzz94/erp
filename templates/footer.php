<?php
/**
 * Footer Template
 * Included in all pages for closing tags and scripts
 */

if (!defined('BASE_URL')) {
    die('Direct access not permitted');
}
?>

<!-- Standardized Print Footer (Visible only when printing) -->
<div id="standard-print-footer" class="clearfix">
    <div class="footer-left">ERP Developed By : CITNBD | 01976-793351</div>
    <div class="footer-right">Date: <?= date('d-M-Y') ?> | Time: <?= date('h:i A') ?></div>
</div>

        </div>
        <!-- End Container-fluid -->
        
        <!-- Footer -->
        <footer class="sticky-footer mt-5" style="background-color: var(--card-bg); border-top: 1px solid var(--border-color);">
            <div class="container-fluid">
                <div class="copyright text-center my-auto py-3">
                    <?php 
                    // Get invoice settings for footer text
                    $inv_footer_settings = db_select_one('invoice_settings', ['id' => 1]);
                    $inv_footer_text = $inv_footer_settings['footer_text'] ?? '';
                    
                    // Get copyright text from settings
                    $footer_text = '';
                    if (isset($settings['footer_copyright_text']) && !empty($settings['footer_copyright_text'])) {
                        $footer_text = $settings['footer_copyright_text'];
                    } else {
                        // Fallback to query if settings not available in variable scope (e.g. some pages might not load it)
                        $footer_settings = db_select_one('business_settings', ['id' => 1]);
                        if ($footer_settings && !empty($footer_settings['footer_copyright_text'])) {
                            $footer_text = $footer_settings['footer_copyright_text'];
                        }
                    }
                    ?>
                    
                    <?php if (!empty($inv_footer_text)): ?>
                        <div class="mb-1 text-muted small"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($inv_footer_text) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($footer_text)): ?>
                        <span><?= $footer_text ?></span>
                    <?php else: ?>
                        <span class="text-muted">
                            Copyright &copy; <?= date('Y') ?> <?= defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Business Management System' ?>. 
                            Designed & Developed By <a href="http://citnbd.com/" target="_blank" class="text-primary fw-bold text-decoration-none">CITNBD</a> | 01976-793351
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </footer>
        
    </div>
    <!-- End Content -->
    
</div>
<!-- End Wrapper -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Global JavaScript -->
<script>
$(document).ready(function() {
    // Initialize DataTables with default options
    if ($.fn.DataTable) {
        $('.datatable').DataTable({
            "pageLength": <?= RECORDS_PER_PAGE ?>,
            "responsive": true,
            "language": {
                "search": "Search:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "paginate": {
                    "first": "First",
                    "last": "Last",
                    "next": "Next",
                    "previous": "Previous"
                }
            }
        });
    }
    
    // Initialize Select2
    if ($.fn.select2) {
        $.fn.select2.defaults.set("theme", "bootstrap-5");
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Confirm delete actions
    $('.btn-delete').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Number formatting
    $('.currency-input').on('blur', function() {
        var value = parseFloat($(this).val()) || 0;
        $(this).val(value.toFixed(2));
    });
    
    // Print functionality
    $('.btn-print').on('click', function() {
        window.print();
    });
});

// Global helper functions
function formatCurrency(amount) {
    return '<?= APP_CURRENCY_SYMBOL ?>' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function showAlert(message, type = 'success') {
    Swal.fire({
        icon: type,
        title: type.charAt(0).toUpperCase() + type.slice(1),
        text: message,
        timer: 3000,
        showConfirmButton: false
    });
}

function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return Swal.fire({
        title: 'Are you sure?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    });
}
</script>

<!-- Theme Manager with Cache Busting -->
<script src="<?= asset_url('assets/js/theme-manager.js') ?>"></script>

<?php if (isset($additional_js)): ?>
    <?= $additional_js ?>
<?php endif; ?>

</body>
</html>
