<?php
// stock/purchases.php - list of stock purchases (batches)
require_once __DIR__ . '/../auth/auth_check.php';

[$defaultFrom, $defaultTo] = period_range('year');
$from = get_date('from', $defaultFrom);
$to = get_date('to', $defaultTo);
$itemId = (int)($_GET['item_id'] ?? 0);
$where = ['b.purchase_date BETWEEN ? AND ?'];
$params = [$from, $to];
if ($itemId) { $where[] = 'b.item_id = ?'; $params[] = $itemId; }

$rows = db_all($pdo, 'SELECT b.*, i.name, i.unit, v.name AS vendor FROM stock_batches b JOIN stock_items i ON i.id = b.item_id
    LEFT JOIN vendors v ON v.id = b.vendor_id WHERE ' . implode(' AND ', $where) . ' ORDER BY b.purchase_date DESC, b.id DESC', $params);
$total = array_sum(array_column($rows, 'total_cost'));
$items = db_all($pdo, 'SELECT id, name FROM stock_items ORDER BY name');

$pageTitle = 'Stock Purchases';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-receipt"></i> Stock Purchases</h1>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Stock</a>
        <a class="btn btn-success" href="purchase.php"><i class="bi bi-cart-plus"></i> Buy Stock</a></div>
</div>
<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-12 col-md-4"><select class="form-select" name="item_id"><option value="">All items</option>
            <?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>" <?= $itemId === (int)$it['id'] ? 'selected' : '' ?>><?= e($it['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-3"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
    </div>
</form>
<div class="card shadow-sm">
    <div class="card-header bg-white">Total bought: <strong><?= money($total) ?></strong></div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Item</th><th>Vendor</th><th class="money">Bought</th><th class="money">Left</th><th>Expiry</th><th class="money">Unit cost</th><th class="money">Total</th><th>By</th><th></th></tr></thead>
        <?php foreach ($rows as $r): $expired = $r['expiry_date'] && $r['expiry_date'] < date('Y-m-d') && $r['qty_left'] > 0; ?>
            <tr class="<?= $expired ? 'table-danger' : '' ?>">
                <td><?= show_date($r['purchase_date']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['vendor'] ?? '-') ?></td>
                <td class="money"><?= qty($r['qty_in']) ?> <?= e($r['unit']) ?></td><td class="money"><?= qty($r['qty_left']) ?></td>
                <td><?= $r['expiry_date'] ? show_date($r['expiry_date']) . ($expired ? ' <span class="badge bg-danger">expired</span>' : '') : '-' ?></td>
                <td class="money"><?= money($r['unit_cost']) ?></td><td class="money"><?= money($r['total_cost']) ?></td><td class="small"><?= e($r['created_by_name']) ?></td>
                <td class="actions text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="purchase.php?id=<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                    <?php if (is_admin()): /* only the admin can delete */ ?><form method="post" action="purchase_delete.php" class="d-inline" data-confirm="Delete this purchase? Its expense is also removed.">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                    </form><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">No purchases in this period.</td></tr><?php endif; ?>
    </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
