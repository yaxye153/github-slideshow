<?php
// monthly-running/index.php - monthly / recurring running costs
require_once __DIR__ . '/../auth/auth_check.php';

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}
$month = (int)($_GET['month'] ?? 0);
$month = ($month >= 1 && $month <= 12) ? $month : 0;

$where = 'WHERE cost_year = ?' . ($month ? ' AND cost_month = ?' : '');
$params = $month ? [$year, $month] : [$year];
$rows = db_all($pdo, "SELECT * FROM monthly_running_costs $where ORDER BY cost_month DESC, payment_date DESC, id DESC", $params);
$total = array_sum(array_column($rows, 'amount'));

// Total Monthly Running Cost for each month of the year
$perMonth = array_fill(1, 12, 0.0);
foreach (db_all($pdo, 'SELECT cost_month, SUM(amount) AS total FROM monthly_running_costs WHERE cost_year = ? GROUP BY cost_month', [$year]) as $m) {
    $perMonth[(int)$m['cost_month']] = (float)$m['total'];
}

$pageTitle = 'Monthly Running';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-calendar-month"></i> Monthly Running Costs</h1>
    <a class="btn btn-danger" href="form.php"><i class="bi bi-plus-lg"></i> Add Monthly Cost</a>
</div>
<p class="text-muted small">Recurring costs (rent, internet, security, subscriptions...). They are added to Expenses automatically on their payment date.</p>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-6 col-md-3"><select class="form-select" name="month"><option value="0">All months</option>
            <?php foreach (month_names() as $n => $name): ?><option value="<?= $n ?>" <?= $month === $n ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-6 col-md-3"><input class="form-control" type="number" name="year" min="2000" max="2100" value="<?= $year ?>"></div>
        <div class="col-12 col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </div>
</form>

<div class="card stat-card red shadow-sm mb-3" style="max-width: 420px"><div class="card-body">
    <div class="stat-label">Total Monthly Running Cost (<?= $month ? month_names()[$month] . ' ' : '' ?><?= $year ?>)</div>
    <div class="stat-value"><?= money($total) ?></div>
</div></div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Total per Month (<?= $year ?>)</strong></div>
            <table class="table table-sm mb-0">
                <?php foreach (month_names() as $n => $name): ?>
                    <tr><td><?= $name ?></td><td class="money"><?= money($perMonth[$n]) ?></td></tr>
                <?php endforeach; ?>
                <tr class="fw-bold"><td>Year Total</td><td class="money"><?= money(array_sum($perMonth)) ?></td></tr>
            </table>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Month</th><th>Category</th><th>Description</th><th>Paid On</th><th>Method</th><th class="money">Amount</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= month_names()[(int)$r['cost_month']] . ' ' . (int)$r['cost_year'] ?></td>
                    <td><?= e($r['category']) ?></td><td><?= e($r['description']) ?></td>
                    <td><?= show_date($r['payment_date']) ?></td><td><?= e($r['payment_method']) ?></td>
                    <td class="money"><?= money($r['amount']) ?></td>
                    <td class="actions text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php if (is_admin()): /* only the admin can delete */ ?><form method="post" action="delete.php" class="d-inline" data-confirm="Delete this cost? It will also be removed from expenses.">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                        </form><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No monthly running costs for this period.</td></tr><?php endif; ?>
            </tbody>
        </table></div></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
