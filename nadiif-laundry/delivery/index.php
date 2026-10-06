<?php
// delivery/index.php - list of deliveries with cost, income and profit
require_once __DIR__ . '/../auth/auth_check.php';

[$defaultFrom, $defaultTo] = period_range('month');
$from = get_date('from', $defaultFrom);
$to = get_date('to', $defaultTo);
$q = trim((string)($_GET['q'] ?? ''));

$where = ['d.delivery_date BETWEEN ? AND ?'];
$params = [$from, $to];
if ($q !== '') {
    $where[] = '(c.full_name LIKE ? OR o.order_number LIKE ? OR d.delivery_person LIKE ? OR d.delivery_address LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$rows = db_all($pdo, "SELECT d.*, o.order_number, c.full_name FROM deliveries d
                      LEFT JOIN orders o ON o.id = d.order_id LEFT JOIN customers c ON c.id = d.customer_id
                      $whereSql ORDER BY d.delivery_date DESC, d.id DESC", $params);
$totalCost = array_sum(array_column($rows, 'delivery_cost'));
$totalIncome = array_sum(array_column($rows, 'delivery_income'));
// Delivery Profit = Delivery Income - Delivery Cost
$profit = $totalIncome - $totalCost;

$pageTitle = 'Delivery';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-truck"></i> Delivery</h1>
    <a class="btn btn-primary" href="form.php"><i class="bi bi-plus-lg"></i> Record Delivery</a>
</div>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-12 col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Customer, order, delivery person or address"></div>
        <div class="col-6 col-md-3"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-3"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="card stat-card red shadow-sm"><div class="card-body"><div class="stat-label">Delivery Cost</div><div class="stat-value"><?= money($totalCost) ?></div></div></div></div>
    <div class="col-6 col-md-4"><div class="card stat-card green shadow-sm"><div class="card-body"><div class="stat-label">Delivery Income</div><div class="stat-value"><?= money($totalIncome) ?></div></div></div></div>
    <div class="col-12 col-md-4"><div class="card stat-card shadow-sm"><div class="card-body"><div class="stat-label">Delivery Profit</div><div class="stat-value <?= $profit < 0 ? 'text-loss' : 'text-profit' ?>"><?= money($profit) ?> <?= profit_label($profit) ?></div></div></div></div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Date</th><th>Order</th><th>Customer</th><th>Delivery Person</th><th>Address</th><th class="money">Cost</th><th class="money">Income</th><th>Payment</th><th class="money">Profit</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $d): $p = $d['delivery_income'] - $d['delivery_cost']; ?>
                <tr>
                    <td><?= (int)$d['id'] ?></td>
                    <td><?= show_date($d['delivery_date']) ?></td>
                    <td><?= $d['order_id'] ? '<a href="../orders/view.php?id=' . (int)$d['order_id'] . '">' . e($d['order_number']) . '</a>' : '-' ?></td>
                    <td><?= e($d['full_name'] ?? '-') ?></td>
                    <td><?= e($d['delivery_person']) ?></td>
                    <td><?= e($d['delivery_address']) ?></td>
                    <td class="money"><?= money($d['delivery_cost']) ?><?= !$d['cost_is_expense'] && $d['delivery_cost'] > 0 ? '<br><small class="text-muted">not an expense</small>' : '' ?></td>
                    <td class="money"><?= money($d['delivery_income']) ?></td>
                    <td><?= badge($d['payment_status']) ?></td>
                    <td class="money <?= $p < 0 ? 'text-loss' : 'text-profit' ?>"><?= money($p) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $d['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="11" class="text-center text-muted py-4">No deliveries in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
