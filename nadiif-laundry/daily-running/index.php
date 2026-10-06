<?php
// daily-running/index.php - everyday running costs, with a total for each day
require_once __DIR__ . '/../auth/auth_check.php';

[$defaultFrom, $defaultTo] = period_range('month');
$from = get_date('from', $defaultFrom);
$to = get_date('to', $defaultTo);
$category = in_list((string)($_GET['category'] ?? ''), daily_running_categories(), '');

$where = 'WHERE cost_date BETWEEN ? AND ?' . ($category !== '' ? ' AND category = ?' : '');
$params = $category !== '' ? [$from, $to, $category] : [$from, $to];

$rows = db_all($pdo, "SELECT * FROM daily_running_costs $where ORDER BY cost_date DESC, id DESC", $params);
// Total Daily Running Cost for each day
$perDay = db_all($pdo, "SELECT cost_date, COUNT(*) AS items, SUM(amount) AS total FROM daily_running_costs $where GROUP BY cost_date ORDER BY cost_date DESC", $params);
$grandTotal = array_sum(array_column($rows, 'amount'));
$todayTotal = (float)db_value($pdo, 'SELECT COALESCE(SUM(amount), 0) FROM daily_running_costs WHERE cost_date = ?', [date('Y-m-d')]);

$pageTitle = 'Daily Running';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-calendar-day"></i> Daily Running Costs</h1>
    <a class="btn btn-danger" href="form.php"><i class="bi bi-plus-lg"></i> Add Daily Cost</a>
</div>
<p class="text-muted small">Everyday costs (electricity, water, fuel, tea, small repairs...). They are added to Expenses automatically.</p>

<div class="row g-3 mb-3">
    <div class="col-6"><div class="card stat-card red shadow-sm"><div class="card-body"><div class="stat-label">Today's Daily Running Cost</div><div class="stat-value"><?= money($todayTotal) ?></div></div></div></div>
    <div class="col-6"><div class="card stat-card red shadow-sm"><div class="card-body"><div class="stat-label">Selected Period Total</div><div class="stat-value"><?= money($grandTotal) ?></div></div></div></div>
</div>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-6 col-md-3"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-3"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-6 col-md-3"><select class="form-select" name="category"><option value="">All categories</option><?= options(daily_running_categories(), $category) ?></select></div>
        <div class="col-6 col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </div>
</form>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Total per Day</strong></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th class="text-center">Items</th><th class="money">Total</th></tr></thead>
                <tbody>
                <?php foreach ($perDay as $day): ?>
                    <tr><td><?= show_date($day['cost_date']) ?></td><td class="text-center"><?= (int)$day['items'] ?></td><td class="money"><?= money($day['total']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$perDay): ?><tr><td colspan="3" class="text-center text-muted py-3">No costs.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="table-responsive"><table class="table table-hover mb-0">
                <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Method</th><th class="money">Amount</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= show_date($r['cost_date']) ?></td><td><?= e($r['category']) ?></td><td><?= e($r['description']) ?></td>
                        <td><?= e($r['payment_method']) ?></td><td class="money"><?= money($r['amount']) ?></td>
                        <td class="actions text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php if (is_admin()): /* only the admin can delete */ ?><form method="post" action="delete.php" class="d-inline" data-confirm="Delete this cost? It will also be removed from expenses.">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                            </form><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No daily running costs in this period.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
