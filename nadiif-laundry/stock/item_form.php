<?php
// stock/item_form.php - add or edit a stock item (e.g. "Omo detergent", kg, minimum 5)
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$item = ['name' => '', 'category' => 'Detergent', 'unit' => 'kg', 'min_quantity' => '0', 'is_active' => 1, 'notes' => ''];
if ($id) {
    $item = db_row($pdo, 'SELECT * FROM stock_items WHERE id = ?', [$id]);
    if (!$item) {
        flash('danger', 'Item not found.');
        redirect('stock/index.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $min = str_replace(',', '', trim((string)($_POST['min_quantity'] ?? '0')));
    $item = [
        'name' => post_text('name', 100),
        'category' => in_list(post_text('category', 30), stock_categories(), 'Other'),
        'unit' => in_list(post_text('unit', 10), stock_units(), 'pcs'),
        'min_quantity' => $min,
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'notes' => post_text('notes', 2000),
    ];
    if ($item['name'] === '') { $errors[] = 'Please enter the item name.'; }
    if (!is_numeric($min) || (float)$min < 0) { $errors[] = 'The minimum must be 0 or more.'; }
    if (db_value($pdo, 'SELECT id FROM stock_items WHERE name = ? AND id <> ?', [$item['name'], $id])) { $errors[] = 'An item with this name already exists.'; }

    if (!$errors) {
        require_csrf('stock/index.php');
        $values = [$item['name'], $item['category'], $item['unit'], round((float)$min, 3), $item['is_active'], $item['notes']];
        if ($id) {
            db_query($pdo, 'UPDATE stock_items SET name = ?, category = ?, unit = ?, min_quantity = ?, is_active = ?, notes = ? WHERE id = ?', array_merge($values, [$id]));
        } else {
            db_query($pdo, 'INSERT INTO stock_items (name, category, unit, min_quantity, is_active, notes) VALUES (?, ?, ?, ?, ?, ?)', $values);
            $id = (int)$pdo->lastInsertId();
        }
        flash('success', 'Stock item saved.');
        redirect(isset($_GET['id']) ? 'stock/index.php' : 'stock/purchase.php?item_id=' . $id);
    }
}

$pageTitle = $id ? 'Edit Stock Item' : 'New Stock Item';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-box-seam"></i> <?= $id ? 'Edit Stock Item' : 'New Stock Item' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Stock</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 760px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Item Name *</label><input class="form-control" name="name" value="<?= e($item['name']) ?>" required maxlength="100" placeholder="e.g. Omo detergent"></div>
        <div class="col-md-6"><label class="form-label">Category</label><select class="form-select" name="category"><?= options(stock_categories(), $item['category']) ?></select></div>
        <div class="col-6"><label class="form-label">Unit</label><select class="form-select" name="unit"><?= options(stock_units(), $item['unit']) ?></select></div>
        <div class="col-6"><label class="form-label">Low stock alarm at</label><input class="form-control" type="number" step="0.001" min="0" name="min_quantity" value="<?= e($item['min_quantity']) ?>">
            <div class="form-text">When the quantity is this or less, the system shows an alarm.</div></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($item['notes']) ?></textarea></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="act" <?= $item['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="act">Active (switch off if you stop using this item)</label></div></div>
        <div class="col-12"><button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Item</button></div>
    </div>
</form>
<?php if ($id && is_admin()): ?>
    <form method="post" action="item_delete.php" class="mt-3" data-confirm="Delete this item? Only possible when it was never bought.">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete Item</button>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
