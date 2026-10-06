<?php
// stock/index.php - STOCK: detergent, soap, starch... with alarms for low stock and expiry dates
require_once __DIR__ . '/../auth/auth_check.php';

$alerts = stock_alerts($pdo);
$showAll = !empty($_GET['all']);

// Quantity left = sum of what is left in every purchase (batch)
$items = db_all($pdo, "SELECT i.*, COALESCE(SUM(b.qty_left), 0) AS qty, COALESCE(SUM(b.qty_left * b.unit_cost), 0) AS value,
        MIN(CASE WHEN b.qty_left > 0 THEN b.expiry_date END) AS next_expiry
    FROM stock_items i LEFT JOIN stock_batches b ON b.item_id = i.id
    " . ($showAll ? '' : 'WHERE i.is_active = 1') . ' GROUP BY i.id ORDER BY i.category, i.name');
$totalValue = array_sum(array_column($items, 'value'));
[$mFrom, $mTo] = period_range('month');
$monthBought = (float)db_value($pdo, 'SELECT COALESCE(SUM(total_cost), 0) FROM stock_batches WHERE purchase_date BETWEEN ? AND ?', [$mFrom, $mTo]);

$pageTitle = 'Stock';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-box-seam"></i> Stock</h1>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-success" href="purchase.php"><i class="bi bi-cart-plus"></i> Buy Stock</a>
        <a class="btn btn-warning" href="use.php"><i class="bi bi-box-arrow-up"></i> Use Stock</a>
        <a class="btn btn-outline-primary" href="item_form.php"><i class="bi bi-plus-lg"></i> New Item</a>
        <a class="btn btn-outline-secondary" href="vendors.php"><i class="bi bi-shop"></i> Vendors</a>
    </div>
</div>

<?php if ($alerts['count']): ?>
    <div class="card border-danger shadow-sm mb-3" id="stock-alarms">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bell-fill"></i> Stock alarms (<?= $alerts['count'] ?>)</span>
            <button type="button" class="btn btn-sm btn-light" id="enable-alarms" style="display:none"><i class="bi bi-bell"></i> Turn on computer alarms</button>
        </div>
        <ul class="list-group list-group-flush">
            <?php foreach ($alerts['low'] as $l): ?>
                <li class="list-group-item d-flex justify-content-between flex-wrap gap-2"><span><span class="badge bg-danger">LOW</span> <b><?= e($l['name']) ?></b>:
                    <?= qty($l['qty']) ?> <?= e($l['unit']) ?> left (minimum <?= qty($l['min_quantity']) ?>)</span>
                    <a class="btn btn-sm btn-success" href="purchase.php?item_id=<?= $l['id'] ?>">Buy</a></li>
            <?php endforeach; ?>
            <?php foreach ($alerts['expired'] as $x): ?>
                <li class="list-group-item d-flex justify-content-between flex-wrap gap-2"><span><span class="badge bg-dark">EXPIRED</span> <b><?= e($x['name']) ?></b>:
                    <?= qty($x['qty_left']) ?> <?= e($x['unit']) ?> expired on <?= show_date($x['expiry_date']) ?></span>
                    <a class="btn btn-sm btn-outline-danger" href="use.php?batch_id=<?= $x['id'] ?>&reason=Expired">Throw away</a></li>
            <?php endforeach; ?>
            <?php foreach ($alerts['expiring'] as $x): ?>
                <li class="list-group-item"><span class="badge bg-warning text-dark">EXPIRES SOON</span> <b><?= e($x['name']) ?></b>:
                    <?= qty($x['qty_left']) ?> <?= e($x['unit']) ?> expires on <?= show_date($x['expiry_date']) ?> &mdash; use it first</li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="card stat-card shadow-sm"><div class="card-body"><div class="stat-label">Stock value (what is left)</div><div class="stat-value"><?= money($totalValue) ?></div></div></div></div>
    <div class="col-6 col-md-4"><div class="card stat-card red shadow-sm"><div class="card-body"><div class="stat-label">Bought this month</div><div class="stat-value"><?= money($monthBought) ?></div></div></div></div>
    <div class="col-12 col-md-4 d-flex align-items-center gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="purchases.php"><i class="bi bi-receipt"></i> Purchases</a>
        <a class="btn btn-outline-secondary" href="usage.php"><i class="bi bi-clock-history"></i> Usage history</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Item</th><th>Category</th><th class="money">In stock</th><th class="money">Minimum</th><th>Status</th><th>Next expiry</th><th class="money">Value</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $it):
                $state = $it['qty'] <= 0 ? ['OUT', 'danger'] : ($it['qty'] <= $it['min_quantity'] ? ['LOW', 'warning text-dark'] : ['OK', 'success']); ?>
                <tr class="<?= $it['is_active'] ? '' : 'text-muted' ?>">
                    <td><b><?= e($it['name']) ?></b></td>
                    <td><?= e($it['category']) ?></td>
                    <td class="money"><?= qty($it['qty']) ?> <?= e($it['unit']) ?></td>
                    <td class="money"><?= qty($it['min_quantity']) ?> <?= e($it['unit']) ?></td>
                    <td><span class="badge bg-<?= $state[1] ?>"><?= $state[0] ?></span></td>
                    <td><?= $it['next_expiry'] ? show_date($it['next_expiry']) : '-' ?></td>
                    <td class="money"><?= money($it['value']) ?></td>
                    <td class="actions text-end">
                        <a class="btn btn-sm btn-outline-success" href="purchase.php?item_id=<?= $it['id'] ?>" title="Buy"><i class="bi bi-cart-plus"></i></a>
                        <a class="btn btn-sm btn-outline-warning" href="use.php?item_id=<?= $it['id'] ?>" title="Use"><i class="bi bi-box-arrow-up"></i></a>
                        <a class="btn btn-sm btn-outline-secondary" href="item_form.php?id=<?= $it['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="8" class="text-center text-muted py-4">No stock items yet. Click "New Item" (e.g. Omo detergent, kg).</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="small mt-2"><a href="?all=1">Show switched-off items</a></p>

<script>
// Computer (desktop) alarms: ask once, then the system can pop up stock alarms
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('enable-alarms');
    if (btn && 'Notification' in window && Notification.permission === 'default') {
        btn.style.display = '';
        btn.addEventListener('click', function () {
            Notification.requestPermission().then(function () { btn.style.display = 'none'; });
        });
    }
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
