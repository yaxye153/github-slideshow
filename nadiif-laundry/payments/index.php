<?php
// payments/index.php - list of all customer payments
require_once __DIR__ . '/../auth/auth_check.php';

[$defaultFrom, $defaultTo] = period_range('month');
$from = get_date('from', $defaultFrom);
$to = get_date('to', $defaultTo);
$q = trim((string)($_GET['q'] ?? ''));
$method = in_list((string)($_GET['method'] ?? ''), payment_methods(), '');

$where = ['p.payment_date BETWEEN ? AND ?'];
$params = [$from, $to];
if ($q !== '') {
    $where[] = '(o.order_number LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ? OR p.reference LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($method !== '') { $where[] = 'p.payment_method = ?'; $params[] = $method; }
$whereSql = 'WHERE ' . implode(' AND ', $where);
$base = "FROM payments p JOIN orders o ON o.id = p.order_id JOIN customers c ON c.id = o.customer_id $whereSql";

$total = (float)db_value($pdo, "SELECT COALESCE(SUM(p.amount), 0) $base", $params);
$byMethod = db_all($pdo, "SELECT p.payment_method, SUM(p.amount) AS total $base GROUP BY p.payment_method", $params);
$p = paginate((int)db_value($pdo, "SELECT COUNT(*) $base", $params));
$payments = db_all($pdo, "SELECT p.*, o.order_number, c.full_name $base ORDER BY p.payment_date DESC, p.id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);

$pageTitle = 'Payments';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-cash-coin"></i> Payments</h1>
    <a class="btn btn-success" href="add.php"><i class="bi bi-plus-lg"></i> Add Payment</a>
</div>
<p class="text-muted small">Every payment here is automatically included in Income. Do not add it again in the Income page.</p>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-12 col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Order number, customer, phone, reference"></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-6 col-md-2"><select class="form-select" name="method"><option value="">All methods</option><?= options(payment_methods(), $method) ?></select></div>
        <div class="col-6 col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card stat-card green shadow-sm"><div class="card-body"><div class="stat-label">Total Received</div><div class="stat-value"><?= money($total) ?></div></div></div></div>
    <?php foreach ($byMethod as $m): ?>
        <div class="col-6 col-md-3"><div class="card stat-card shadow-sm"><div class="card-body"><div class="stat-label"><?= e($m['payment_method']) ?></div><div class="stat-value"><?= money($m['total']) ?></div></div></div></div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Order</th><th>Customer</th><th>Method</th><th>Reference</th><th class="money">Amount</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payments as $pay): ?>
                <tr>
                    <td><?= show_date($pay['payment_date']) ?></td>
                    <td><a href="../orders/view.php?id=<?= $pay['order_id'] ?>"><?= e($pay['order_number']) ?></a></td>
                    <td><?= e($pay['full_name']) ?></td>
                    <td><?= e($pay['payment_method']) ?></td>
                    <td><?= e($pay['reference']) ?></td>
                    <td class="money"><?= money($pay['amount']) ?></td>
                    <td class="text-end">
                        <form method="post" action="delete.php" data-confirm="Delete this payment? It will also be removed from income.">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $pay['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="7" class="text-center text-muted py-4">No payments in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pagination_links($p) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
