<table class="table table-bordered table-sm parts-table">
    <thead>
        <tr>
            <th>Part Name</th>
            <th class="text-center">Qty</th>
            <th class="text-end">Unit Price</th>
            <th class="text-end">Total</th>
            <?php if ($ticket['status'] !== 'Delivered'): ?><th></th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($parts)): ?>
            <tr><td colspan="5" class="text-center text-muted">No parts added yet</td></tr>
        <?php else: ?>
            <?php foreach ($parts as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['product_name']) ?> <?= $p['product_id'] ? '<small class="text-muted">(Inventory)</small>' : '' ?></td>
                    <td class="text-center"><?= $p['quantity'] ?></td>
                    <td class="text-end"><?= format_currency($p['unit_price']) ?></td>
                    <td class="text-end"><?= format_currency($p['total_price']) ?></td>
                    <?php if ($ticket['status'] !== 'Delivered'): ?>
                    <td class="text-center">
                        <button class="btn btn-sm btn-danger btn-remove-part" data-part-id="<?= $p['id'] ?>" title="Remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="text-end fw-bold">Parts Total:</td>
            <td class="text-end fw-bold"><?= format_currency($ticket['total_parts_cost']) ?></td>
            <td></td>
        </tr>
    </tfoot>
</table>
