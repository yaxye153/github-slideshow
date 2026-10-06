<?php
// stock/purchase.php - BUY STOCK (or edit a purchase).
// Each purchase is a "batch" with its own expiry date.
// The cost is copied to Expenses automatically (only once).
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$items = db_all($pdo, 'SELECT id, name, unit, category FROM stock_items WHERE is_active = 1 OR id = ? ORDER BY name', [(int)($_GET['item_id'] ?? 0)]);
$vendors = db_all($pdo, 'SELECT id, name FROM vendors ORDER BY name');

$b = ['item_id' => (int)($_GET['item_id'] ?? 0), 'vendor_id' => 0, 'purchase_date' => date('Y-m-d'), 'expiry_date' => '', 'qty_in' => '',
      'qty_left' => 0, 'total_cost' => '', 'payment_method' => 'Cash', 'reference' => '', 'notes' => ''];
$used = 0.0;
if ($id) {
    $b = db_row($pdo, 'SELECT * FROM stock_batches WHERE id = ?', [$id]);
    if (!$b) {
        flash('danger', 'Purchase not found.');
        redirect('stock/purchases.php');
    }
    $used = round((float)$b['qty_in'] - (float)$b['qty_left'], 3);
    $items = db_all($pdo, 'SELECT id, name, unit, category FROM stock_items WHERE is_active = 1 OR id = ? ORDER BY name', [$b['item_id']]);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qtyRaw = str_replace(',', '', trim((string)($_POST['qty_in'] ?? '')));
    $b = array_merge($b, [
        'item_id' => (int)($_POST['item_id'] ?? 0),
        'vendor_id' => (int)($_POST['vendor_id'] ?? 0),
        'purchase_date' => post_text('purchase_date', 10),
        'expiry_date' => post_text('expiry_date', 10),
        'qty_in' => $qtyRaw,
        'total_cost' => post_money('total_cost'),
        'payment_method' => in_list(post_text('payment_method', 20), payment_methods(), 'Cash'),
        'reference' => post_text('reference', 100),
        'notes' => post_text('notes', 2000),
    ]);
    $newVendor = post_text('new_vendor', 100);
    $item = db_row($pdo, 'SELECT * FROM stock_items WHERE id = ?', [$b['item_id']]);

    if (!$item) { $errors[] = 'Please choose the item.'; }
    if (!valid_date($b['purchase_date'])) { $errors[] = 'Please enter a valid purchase date.'; }
    if ($b['expiry_date'] !== '' && !valid_date($b['expiry_date'])) { $errors[] = 'Please enter a valid expiry date.'; }
    if (!is_numeric($qtyRaw) || (float)$qtyRaw <= 0) { $errors[] = 'Please enter a quantity greater than 0.'; }
    elseif ((float)$qtyRaw < $used) { $errors[] = 'Quantity can not be less than what was already used (' . qty($used) . ').'; }
    if ($b['total_cost'] === null || $b['total_cost'] < 0) { $errors[] = 'Please enter the total cost (0 or more).'; }

    if (!$errors) {
        require_csrf('stock/index.php');
        $quantity = round((float)$qtyRaw, 3);
        $pdo->beginTransaction();
        try {
            // A new vendor typed by hand is saved automatically
            if ($newVendor !== '') {
                $existing = db_value($pdo, 'SELECT id FROM vendors WHERE name = ?', [$newVendor]);
                if ($existing) {
                    $b['vendor_id'] = (int)$existing;
                } else {
                    db_query($pdo, 'INSERT INTO vendors (name) VALUES (?)', [$newVendor]);
                    $b['vendor_id'] = (int)$pdo->lastInsertId();
                }
            }
            $unitCost = $quantity > 0 ? round($b['total_cost'] / $quantity, 2) : 0;
            $values = [$b['item_id'], $b['vendor_id'] ?: null, $b['purchase_date'], $b['expiry_date'] !== '' ? $b['expiry_date'] : null,
                       $quantity, round($quantity - $used, 3), $unitCost, $b['total_cost'], $b['payment_method'], $b['reference'], $b['notes']];
            if ($id) {
                db_query($pdo, 'UPDATE stock_batches SET item_id = ?, vendor_id = ?, purchase_date = ?, expiry_date = ?, qty_in = ?, qty_left = ?, unit_cost = ?,
                                total_cost = ?, payment_method = ?, reference = ?, notes = ? WHERE id = ?', array_merge($values, [$id]));
            } else {
                db_query($pdo, 'INSERT INTO stock_batches (item_id, vendor_id, purchase_date, expiry_date, qty_in, qty_left, unit_cost, total_cost,
                                payment_method, reference, notes, created_by_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    array_merge($values, [$CURRENT_USER['username']]));
                $id = (int)$pdo->lastInsertId();
            }
            // Save expense: the purchase cost goes to Expenses (once, linked by purchase id)
            $vendorName = $b['vendor_id'] ? (string)db_value($pdo, 'SELECT name FROM vendors WHERE id = ?', [$b['vendor_id']]) : '';
            if ($b['total_cost'] > 0) {
                ledger_expense_save($pdo, 'stock', $id, $b['purchase_date'], stock_expense_category($item['category']),
                    'Stock: ' . $item['name'] . ' ' . qty($quantity) . ' ' . $item['unit'] . ($vendorName !== '' ? ' from ' . $vendorName : ''),
                    $b['total_cost'], $b['payment_method'], $b['reference'], $b['notes']);
            } else {
                ledger_expense_delete($pdo, 'stock', $id);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        flash('success', 'Stock purchase saved. The cost was added to expenses.');
        redirect('stock/index.php');
    }
}

$pageTitle = $id ? 'Edit Stock Purchase' : 'Buy Stock';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-cart-plus"></i> <?= $id ? 'Edit Stock Purchase' : 'Buy Stock' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Stock</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<?php if (!$items): ?><div class="alert alert-info">First add a stock item: <a href="item_form.php">New Item</a></div><?php endif; ?>
<?php if ($used > 0): ?><div class="alert alert-info"><?= qty($used) ?> of this purchase was already used.</div><?php endif; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 860px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Item *</label>
            <select class="form-select" name="item_id" required><option value="">-- Choose item --</option>
                <?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>" <?= (int)$b['item_id'] === (int)$it['id'] ? 'selected' : '' ?>><?= e($it['name'] . ' (' . $it['unit'] . ')') ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-6"><label class="form-label">Vendor</label>
            <div class="input-group">
                <select class="form-select" name="vendor_id"><option value="">-- Choose vendor --</option>
                    <?php foreach ($vendors as $v): ?><option value="<?= $v['id'] ?>" <?= (int)$b['vendor_id'] === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?>
                </select>
                <input class="form-control" name="new_vendor" placeholder="or new vendor name" maxlength="100">
            </div></div>
        <div class="col-6 col-md-3"><label class="form-label">Quantity *</label><input class="form-control" type="number" step="0.001" min="0.001" name="qty_in" value="<?= e($b['qty_in']) ?>" required></div>
        <div class="col-6 col-md-3"><label class="form-label">Total Cost (paid) *</label><input class="form-control" type="number" step="0.01" min="0" name="total_cost" value="<?= e($b['total_cost'] ?? '') ?>" required></div>
        <div class="col-6 col-md-3"><label class="form-label">Purchase Date *</label><input class="form-control" type="date" name="purchase_date" value="<?= e($b['purchase_date']) ?>" required></div>
        <div class="col-6 col-md-3"><label class="form-label">Expiry Date</label><input class="form-control" type="date" name="expiry_date" value="<?= e($b['expiry_date']) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><?= options(payment_methods(), $b['payment_method']) ?></select></div>
        <div class="col-6 col-md-3"><label class="form-label">Reference</label><input class="form-control" name="reference" value="<?= e($b['reference']) ?>" maxlength="100" placeholder="Invoice no."></div>
        <div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" name="notes" value="<?= e($b['notes']) ?>"></div>
        <div class="col-12 small text-muted">The total cost is added to <b>Expenses</b> automatically. Do not enter this purchase again in Expenses.</div>
        <div class="col-12"><button class="btn btn-success btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Purchase</button></div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
