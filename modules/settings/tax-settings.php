<?php
/**
 * Tax Settings Page
 * Now allows updating the default tax rate in the database.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

// Handle form submission
if (is_post()) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $new_rate = clean_input($_POST['tax_rate']);
        
        if (is_numeric($new_rate) && $new_rate >= 0 && $new_rate <= 100) {
            // Update the business_settings table
            $data = ['tax_rate' => $new_rate];
            
            if (db_update('business_settings', $data, ['id' => 1])) {
                // Sync with invoice_settings as well for total compatibility
                db_update('invoice_settings', ['default_tax_rate' => $new_rate], ['id' => 1]);
                
                log_activity(get_current_user_id(), 'settings', "Updated default tax rate to $new_rate%");
                $_SESSION['success_message'] = 'Default tax rate updated successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to update settings. Please try again.';
            }
        } else {
            $_SESSION['error_message'] = 'Please enter a valid tax percentage (0-100).';
        }
    } else {
        $_SESSION['error_message'] = 'Security verification failed.';
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Get messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Re-fetch settings to show current value
try {
    $settings = db_select_one('business_settings', ['id' => 1]);
    $current_rate = $settings['tax_rate'] ?? 0;
} catch (Exception $e) {
    $current_rate = 0;
    $error_message = 'Error fetching settings: ' . $e->getMessage();
}

$page_title = 'Tax Settings';
include __DIR__ . '/../../templates/header.php';
?>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Tax Configuration</h6>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?= $success_message ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="form-group">
                        <label for="tax_rate">Default Tax Rate (%)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                                   value="<?= $current_rate ?>" step="0.01" min="0" max="100" required>
                            <div class="input-group-append">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <small class="form-text text-muted">
                            This rate will be applied to all new products by default.
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
                
                <hr>
                
                <div class="mt-4">
                    <h6 class="text-primary">Tax Calculation Example</h6>
                    <div class="border p-3 rounded bg-light">
                        <p class="mb-2"><strong>Product Price:</strong> <?= format_currency(1000) ?></p>
                        <p class="mb-2"><strong>Tax (<?= $current_rate ?>%):</strong> <?= format_currency(1000 * $current_rate / 100) ?></p>
                        <div class="border-top pt-2">
                            <p class="mb-0 font-weight-bold"><strong>Total Price:</strong> <?= format_currency(1000 + (1000 * $current_rate / 100)) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Tax Information</h6>
            </div>
            <div class="card-body">
                <h6 class="text-primary">How Tax Works</h6>
                <ul>
                    <li>The default tax rate is applied to all products unless specified otherwise.</li>
                    <li>Individual products can have custom tax rates set in product settings.</li>
                    <li>Tax is calculated on the selling price of products.</li>
                    <li>Tax amount is shown separately on invoices and receipts.</li>
                </ul>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Important:</strong> Changing the tax rate here will update the system-wide default. It will NOT retroactively change the tax rate on existing transactions or products that have a custom tax rate explicitly saved.
                </div>
            </div>
        </div>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Products with Custom Tax Rates</h6>
            </div>
            <div class="card-body p-0">
                <?php
                $sql = "SELECT name, code, tax_rate FROM products WHERE tax_rate != ? AND status = 'active' ORDER BY name ASC LIMIT 10";
                $custom_tax_products = db_query($sql, [$current_rate]);
                ?>
                
                <?php if (empty($custom_tax_products)): ?>
                    <div class="p-3 text-center text-muted">
                        No products with custom tax rates found.<br>
                        All products use the default rate.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th>Product Name</th>
                                    <th>Code</th>
                                    <th>Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($custom_tax_products as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                        <td><?= htmlspecialchars($product['code']) ?></td>
                                        <td><?= $product['tax_rate'] ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="p-2 text-center">
                            <small class="text-muted">Showing top 10 overrides</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
