<?php
/**
 * Quotation View Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$quotation_id = (int)get_param('id');

// Get quotation details
$sql = "SELECT q.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
        u.username as created_by_name
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.id = ?";
$quotation = db_query_one($sql, [$quotation_id]);

if (!$quotation) {
    redirect_with_message('quotations-list.php', 'Quotation not found', 'error');
}

// Get quotation items
$sql = "SELECT qi.*, p.name as product_name, p.code as product_code
        FROM quotation_items qi
        INNER JOIN products p ON qi.product_id = p.id
        WHERE qi.quotation_id = ?";
$quotation_items = db_query($sql, [$quotation_id]);

$page_title = 'Quotation #' . $quotation['quotation_number'];
$page_actions = '<a href="quotation-print.php?id=' . $quotation_id . '" target="_blank" class="btn btn-primary"><i class="fas fa-print"></i> Print</a>
                 <a href="quotations-list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>';
include __DIR__ . '/../../templates/header.php';
?>

<style>
@media print {
    .no-print { display: none; }
    .card { border: none; box-shadow: none; }
    .btn-block { display: none; }
}
</style>

<div class="row">
    <div class="col-lg-9">
        <div class="card shadow mb-4">
            <div class="card-body">
                <!-- Quotation Header -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h3><?= BUSINESS_NAME ?></h3>
                        <p class="mb-0"><?= BUSINESS_ADDRESS ?></p>
                        <p class="mb-0">Phone: <?= BUSINESS_PHONE ?></p>
                        <p class="mb-0">Email: <?= BUSINESS_EMAIL ?></p>
                    </div>
                    <div class="col-md-6 text-end">
                        <h2 class="text-primary">QUOTATION</h2>
                        <p class="mb-0"><strong>Quotation #:</strong> <?= htmlspecialchars($quotation['quotation_number']) ?></p>
                        <p class="mb-0"><strong>Date:</strong> <?= format_date($quotation['quotation_date']) ?></p>
                        <p class="mb-0"><strong>Valid Until:</strong> <?= format_date($quotation['expiration_date'] ?? '') ?></p>
                        <p class="mb-0"><strong>Status:</strong> 
                            <span class="badge bg-<?= ['draft' => 'secondary', 'pending' => 'warning', 'sent' => 'info', 'accepted' => 'success', 'rejected' => 'danger', 'converted' => 'primary', 'sales_complete' => 'primary'][$quotation['status']] ?? 'secondary' ?>">
                                <?= ucwords(str_replace('_', ' ', $quotation['status'])) ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <hr>
                
                <!-- Customer Info -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Quotation For:</h5>
                        <?php if ($quotation['customer_id']): ?>
                            <p class="mb-0"><strong><?= htmlspecialchars($quotation['customer_name']) ?></strong></p>
                            <?php if ($quotation['customer_phone']): ?>
                                <p class="mb-0">Phone: <?= htmlspecialchars($quotation['customer_phone']) ?></p>
                            <?php endif; ?>
                            <?php if ($quotation['customer_address']): ?>
                                <p class="mb-0"><?= htmlspecialchars($quotation['customer_address']) ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="mb-0">Walk-in Customer</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Code</th>
                                <th class="text-end">Price</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($quotation_items as $item): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                        <?php if (!empty($item['description'])): ?>
                                            <div class="small text-muted mt-1"><?= nl2br(htmlspecialchars($item['description'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($item['product_code']) ?></td>
                                    <td class="text-end"><?= format_currency($item['unit_price']) ?></td>
                                    <td class="text-center"><?= $item['quantity'] ?></td>
                                    <td class="text-end"><?= format_currency($item['subtotal']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end"><?= format_currency($quotation['total_amount'] - $quotation['tax_amount'] + $quotation['discount']) ?></td>
                            </tr>
                            <?php if ($quotation['discount'] > 0): ?>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Discount:</strong></td>
                                <td class="text-end">-<?= format_currency($quotation['discount']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Tax:</strong></td>
                                <td class="text-end"><?= format_currency($quotation['tax_amount']) ?></td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="5" class="text-end"><strong>Total Amount:</strong></td>
                                <td class="text-end"><strong><?= format_currency($quotation['total_amount']) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Notes -->
                <?php if (!empty($quotation['notes'])): ?>
                <div class="mb-4">
                    <strong>Notes/Terms:</strong>
                    <p><?= nl2br(htmlspecialchars($quotation['notes'])) ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Footer -->
                <div class="row mt-5">
                    <div class="col-md-12 text-center">
                        <p class="mb-0"><small>This is a computer generated quotation.</small></p>
                        <p class="mb-0"><small>Created by: <?= htmlspecialchars($quotation['created_by_name']) ?> on <?= format_datetime($quotation['created_at']) ?></small></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 no-print">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Actions</h6>
            </div>
            <div class="card-body">
                <?php if ($quotation['status'] === 'pending' || $quotation['status'] === 'draft'): ?>
                    <a href="quotation-edit.php?id=<?= $quotation['id'] ?>" class="btn btn-warning btn-block mb-2 w-100 text-start">
                        <i class="fas fa-edit"></i> Edit Quotation
                    </a>
                <?php endif; ?>
                
                <a href="quotation-print.php?id=<?= $quotation['id'] ?>" target="_blank" class="btn btn-primary btn-block mb-2 w-100 text-start">
                    <i class="fas fa-print"></i> Print Quotation
                </a>
                
                <?php if ($quotation['status'] !== 'converted' && $quotation['status'] !== 'sales_complete'): ?>
                    <a href="../sales/pos.php?quotation_id=<?= $quotation['id'] ?>" class="btn btn-success btn-block mb-2 w-100 text-start">
                        <i class="fas fa-exchange-alt"></i> Convert to Invoice (POS)
                    </a>
                <?php endif; ?>
                
                <hr>
                
                <div class="form-group mb-3">
                    <label>Update Status</label>
                    <form method="POST" action="quotation-status.php">
                        <input type="hidden" name="quotation_id" value="<?= $quotation['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <select name="status" class="form-control mb-2" onchange="this.form.submit()">
                            <option value="pending" <?= $quotation['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="sent" <?= $quotation['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
                            <option value="accepted" <?= $quotation['status'] === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                            <option value="rejected" <?= $quotation['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="sales_complete" <?= $quotation['status'] === 'sales_complete' ? 'selected' : '' ?>>Sales Complete</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
