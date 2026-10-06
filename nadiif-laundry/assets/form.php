<?php
// assets/form.php - add or edit a company asset
// If "record the cost as an expense" is ticked, the purchase cost is copied to Expenses (once).
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$a = ['asset_code' => '', 'name' => '', 'category' => (string)($_GET['category'] ?? 'Shelf'), 'location' => '', 'serial_number' => '',
      'purchase_date' => date('Y-m-d'), 'purchase_cost' => '', 'cost_is_expense' => 0, 'condition_status' => 'Good', 'is_active' => 1, 'notes' => ''];
if ($id) {
    $a = db_row($pdo, 'SELECT * FROM assets WHERE id = ?', [$id]);
    if (!$a) {
        flash('danger', 'Asset not found.');
        redirect('assets/index.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = [
        'asset_code' => post_text('asset_code', 30),
        'name' => post_text('name', 100),
        'category' => in_list(post_text('category', 30), asset_categories(), 'Other'),
        'location' => post_text('location', 100),
        'serial_number' => post_text('serial_number', 100),
        'purchase_date' => post_text('purchase_date', 10),
        'purchase_cost' => post_money('purchase_cost'),
        'cost_is_expense' => !empty($_POST['cost_is_expense']) ? 1 : 0,
        'condition_status' => in_list(post_text('condition_status', 20), asset_conditions(), 'Good'),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'notes' => post_text('notes', 2000),
    ];
    if ($a['name'] === '') { $errors[] = 'Please enter the asset name.'; }
    if ($a['purchase_date'] !== '' && !valid_date($a['purchase_date'])) { $errors[] = 'Please enter a valid purchase date.'; }
    if ($a['purchase_cost'] === null || $a['purchase_cost'] < 0) { $errors[] = 'Please enter a valid cost (0 or more).'; }
    if ($a['cost_is_expense'] && $a['purchase_date'] === '') { $errors[] = 'To record the cost as an expense, enter the purchase date.'; }
    if ($a['asset_code'] !== '' && db_value($pdo, 'SELECT id FROM assets WHERE asset_code = ? AND id <> ?', [$a['asset_code'], $id])) {
        $errors[] = 'Another asset already uses code ' . $a['asset_code'] . '.';
    }

    if (!$errors) {
        require_csrf('assets/index.php');
        $values = [$a['asset_code'], $a['name'], $a['category'], $a['location'], $a['serial_number'], $a['purchase_date'] !== '' ? $a['purchase_date'] : null,
                   $a['purchase_cost'], $a['cost_is_expense'], $a['condition_status'], $a['is_active'], $a['notes']];
        $pdo->beginTransaction();
        try {
            if ($id) {
                db_query($pdo, 'UPDATE assets SET asset_code = ?, name = ?, category = ?, location = ?, serial_number = ?, purchase_date = ?, purchase_cost = ?,
                                cost_is_expense = ?, condition_status = ?, is_active = ?, notes = ? WHERE id = ?', array_merge($values, [$id]));
            } else {
                db_query($pdo, 'INSERT INTO assets (asset_code, name, category, location, serial_number, purchase_date, purchase_cost, cost_is_expense,
                                condition_status, is_active, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $values);
                $id = (int)$pdo->lastInsertId();
            }
            // Asset purchase -> expenses (only once, linked by asset id)
            if ($a['cost_is_expense'] && $a['purchase_cost'] > 0) {
                ledger_expense_save($pdo, 'asset', $id, $a['purchase_date'], 'Equipment',
                    'Asset: ' . $a['name'] . ($a['asset_code'] !== '' ? ' (' . $a['asset_code'] . ')' : ''), $a['purchase_cost'], 'Cash', $a['serial_number']);
            } else {
                ledger_expense_delete($pdo, 'asset', $id);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        flash('success', 'Asset saved.');
        redirect('assets/index.php');
    }
}

$pageTitle = $id ? 'Edit Asset' : 'Add Asset';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-hdd-stack"></i> <?= $id ? 'Edit Asset' : 'Add Asset' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Assets</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 860px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Type</label><select class="form-select" name="category"><?= options(asset_categories(), $a['category']) ?></select></div>
        <div class="col-md-4"><label class="form-label">Code / Number</label><input class="form-control" name="asset_code" value="<?= e($a['asset_code']) ?>" placeholder="Shelf: A-12, PC-1" maxlength="30">
            <div class="form-text">For a shelf, use the same code you write on orders.</div></div>
        <div class="col-md-4"><label class="form-label">Name *</label><input class="form-control" name="name" value="<?= e($a['name']) ?>" required maxlength="100" placeholder="e.g. Shelf A row 12"></div>
        <div class="col-md-4"><label class="form-label">Location</label><input class="form-control" name="location" value="<?= e($a['location']) ?>" maxlength="100" placeholder="e.g. Front room"></div>
        <div class="col-md-4"><label class="form-label">Serial Number</label><input class="form-control" name="serial_number" value="<?= e($a['serial_number']) ?>" maxlength="100"></div>
        <div class="col-md-4"><label class="form-label">Condition</label><select class="form-select" name="condition_status"><?= options(asset_conditions(), $a['condition_status']) ?></select></div>
        <div class="col-md-4"><label class="form-label">Purchase Date</label><input class="form-control" type="date" name="purchase_date" value="<?= e($a['purchase_date']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Purchase Cost</label><input class="form-control" type="number" step="0.01" min="0" name="purchase_cost" value="<?= e($a['purchase_cost'] ?? '') ?>" placeholder="0.00"></div>
        <div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="cost_is_expense" value="1" id="cie" <?= $a['cost_is_expense'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="cie">Record the cost as an expense</label></div></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($a['notes']) ?></textarea></div>
        <div class="col-12"><div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="act" <?= $a['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="act">In use (switch off when sold, thrown away or lost)</label></div></div>
        <div class="col-12 small text-muted">Tick "Record the cost as an expense" only for a <b>new</b> purchase. For things you already owned before using this system, leave it off so old purchases do not change today's profit.</div>
        <div class="col-12"><button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Asset</button></div>
    </div>
</form>
<?php if ($id): ?>
    <?php if (is_admin()): /* only the admin can delete */ ?><form method="post" action="delete.php" class="mt-3" data-confirm="Delete this asset? Its expense (if any) is also removed.">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete Asset</button>
        <small class="text-muted ms-2">Usually better: switch off "In use" so the history is kept.</small>
    </form><?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
