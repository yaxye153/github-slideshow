<?php
// reports/yearly.php - YEARLY REPORT with a month-by-month breakdown
require_once __DIR__ . '/../auth/auth_check.php';

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) { $year = (int)date('Y'); }

// Income and expenses for each month (from real records)
$months = [];
foreach (month_names() as $n => $name) {
    $months[$n] = ['name' => $name, 'income' => 0.0, 'expenses' => 0.0];
}
foreach (db_all($pdo, 'SELECT MONTH(income_date) AS m, SUM(amount) AS total FROM income WHERE YEAR(income_date) = ? GROUP BY MONTH(income_date)', [$year]) as $r) {
    $months[(int)$r['m']]['income'] = (float)$r['total'];
}
foreach (db_all($pdo, 'SELECT MONTH(expense_date) AS m, SUM(amount) AS total FROM expenses WHERE YEAR(expense_date) = ? GROUP BY MONTH(expense_date)', [$year]) as $r) {
    $months[(int)$r['m']]['expenses'] = (float)$r['total'];
}
$totalIncome = array_sum(array_column($months, 'income'));
$totalExpenses = array_sum(array_column($months, 'expenses'));
$totalProfit = $totalIncome - $totalExpenses;

if (($_GET['export'] ?? '') === 'csv') {
    $rows = [];
    foreach ($months as $m) {
        $rows[] = [$m['name'], round($m['income'], 2), round($m['expenses'], 2), round($m['income'] - $m['expenses'], 2)];
    }
    $rows[] = ['TOTAL', round($totalIncome, 2), round($totalExpenses, 2), round($totalProfit, 2)];
    send_csv('nadiif_yearly_report_' . $year . '.csv', ['Month', 'Income', 'Expenses', 'Profit/Loss'], $rows);
}

$pageTitle = 'Yearly Report';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-calendar3"></i> Yearly Report <?= $year ?></h1>
    <div class="d-flex gap-2 flex-wrap no-print">
        <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Reports</a>
        <button class="btn btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
        <a class="btn btn-success" href="?year=<?= $year ?>&export=csv"><i class="bi bi-filetype-csv"></i> CSV / Excel</a>
    </div>
</div>
<form class="card card-body shadow-sm mb-3 filter-form" method="get" style="max-width: 760px">
    <div class="input-group">
        <input class="form-control" type="number" name="year" min="2000" max="2100" value="<?= $year ?>">
        <button class="btn btn-primary" type="submit">Show</button>
    </div>
</form>

<div class="card shadow-sm" style="max-width: 860px">
    <div class="card-body pb-0 print-only"><h2 class="h5"><?= e(setting('business_name')) ?> &mdash; Yearly Report <?= $year ?></h2></div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead><tr><th>Month</th><th class="money">Income</th><th class="money">Expenses</th><th class="money">Profit/Loss</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($months as $n => $m): $p = $m['income'] - $m['expenses']; ?>
                <tr>
                    <td><a href="monthly.php?month=<?= $n ?>&year=<?= $year ?>"><?= $m['name'] ?></a></td>
                    <td class="money"><?= money($m['income']) ?></td>
                    <td class="money"><?= money($m['expenses']) ?></td>
                    <td class="money <?= $p < 0 ? 'text-loss' : 'text-profit' ?>"><?= money($p) ?></td>
                    <td><?= ($m['income'] || $m['expenses']) ? profit_label($p) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-bold">
                <td>TOTAL</td><td class="money"><?= money($totalIncome) ?></td><td class="money"><?= money($totalExpenses) ?></td>
                <td class="money <?= $totalProfit < 0 ? 'text-loss' : 'text-profit' ?>"><?= money($totalProfit) ?></td><td><?= profit_label($totalProfit) ?></td>
            </tr></tfoot>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
