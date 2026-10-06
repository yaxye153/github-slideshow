<?php
// stock/usage.php - history of stock used / thrown away
require_once __DIR__ . '/../auth/auth_check.php';

[$defaultFrom, $defaultTo] = period_range('month');
$from = get_date('from', $defaultFrom);
$to = get_date('to', $defaultTo);
$rows = db_all($pdo, 'SELECT m.*, i.name, i.unit, b.unit_cost FROM stock_moves m JOIN stock_items i ON i.id = m.item_id
    JOIN stock_batches b ON b.id = m.batch_id WHERE m.move_date BETWEEN ? AND ? ORDER BY m.move_date DESC, m.id DESC', [$from, $to]);
$byItem = db_all($pdo, 'SELECT i.name, i.unit, SUM(m.quantity) AS qty, SUM(m.quantity * b.unit_cost) AS value FROM stock_moves m
    JOIN stock_items i ON i.id = m.item_id JOIN stock_batches b ON b.id = m.batch_id
    WHERE m.move_date BETWEEN ? AND ? GROUP BY i.id ORDER BY value DESC', [$from, $to]);

$pageTitle = 'Stock Usage';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-clock-history"></i> Stock Usage</h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Stock</a>
</div>
<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-6 col-md-4"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-4"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-12 col-md-4"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
    </div>
</form>
<div class="row g-3">
    <div class="col-lg-4"><div class="card shadow-sm"><div class="card-header bg-white"><strong>Used per item</strong></div>
        <table class="table table-sm mb-0"><thead><tr><th>Item</th><th class="money">Quantity</th><th class="money">Value</th></tr></thead>
        <?php foreach ($byItem as $r): ?><tr><td><?= e($r['name']) ?></td><td class="money"><?= qty($r['qty']) ?> <?= e($r['unit']) ?></td><td class="money"><?= money($r['value']) ?></td></tr><?php endforeach; ?>
        </table></div></div>
    <div class="col-lg-8"><div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Date</th><th>Item</th><th class="money">Quantity</th><th>Reason</th><th>By</th><th>Notes</th></tr></thead>
        <?php foreach ($rows as $r): ?>
            <tr><td><?= show_date($r['move_date']) ?></td><td><?= e($r['name']) ?></td><td class="money"><?= qty($r['quantity']) ?> <?= e($r['unit']) ?></td>
                <td><span class="badge bg-<?= $r['reason'] === 'Used' ? 'secondary' : 'danger' ?>"><?= e($r['reason']) ?></span></td><td class="small"><?= e($r['created_by_name']) ?></td><td class="small"><?= e($r['notes']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No usage in this period.</td></tr><?php endif; ?>
    </table></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
