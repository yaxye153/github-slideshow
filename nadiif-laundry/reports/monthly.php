<?php
// reports/monthly.php - MONTHLY REPORT for a selected month and year
require_once __DIR__ . '/../auth/auth_check.php';

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) { $month = (int)date('n'); }
if ($year < 2000 || $year > 2100) { $year = (int)date('Y'); }

[$from, $to] = period_range('month', sprintf('%04d-%02d-01', $year, $month));
$pl = profit_and_loss($pdo, $from, $to);

$pageTitle = 'Monthly Report';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-calendar-month"></i> Monthly Report</h1>
    <div class="d-flex gap-2 no-print">
        <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Reports</a>
        <button class="btn btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
    </div>
</div>
<form class="card card-body shadow-sm mb-3 filter-form" method="get" style="max-width: 760px">
    <div class="row g-2">
        <div class="col-6 col-md-5"><select class="form-select" name="month">
            <?php foreach (month_names() as $n => $name): ?><option value="<?= $n ?>" <?= $month === $n ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-6 col-md-4"><input class="form-control" type="number" name="year" min="2000" max="2100" value="<?= $year ?>"></div>
        <div class="col-12 col-md-3"><button class="btn btn-primary w-100" type="submit">Show</button></div>
    </div>
</form>

<div class="card shadow-sm" style="max-width: 760px"><div class="card-body">
    <h2 class="h5"><?= e(setting('business_name')) ?> &mdash; <?= month_names()[$month] . ' ' . $year ?></h2>
    <table class="table mb-0">
        <tr><th>Total Orders</th><td class="money"><?= $pl['orders'] ?> (value <?= money($pl['sales']) ?>)</td></tr>
        <tr><th>Total Income</th><td class="money text-profit"><?= money($pl['income']) ?></td></tr>
        <tr><th>Total Expenses</th><td class="money text-loss"><?= money($pl['expenses']) ?></td></tr>
        <tr><td class="ps-4">Total Salary</td><td class="money"><?= money($pl['by_source']['salary']) ?></td></tr>
        <tr><td class="ps-4">Total Delivery Cost</td><td class="money"><?= money($pl['by_source']['delivery']) ?></td></tr>
        <tr><td class="ps-4">Total Daily Running Cost</td><td class="money"><?= money($pl['by_source']['daily_running']) ?></td></tr>
        <tr><td class="ps-4">Total Monthly Running Cost</td><td class="money"><?= money($pl['by_source']['monthly_running']) ?></td></tr>
        <tr><td class="ps-4">Other Expenses</td><td class="money"><?= money($pl['by_source']['manual']) ?></td></tr>
        <tr class="fs-5 fw-bold"><th>Net <?= $pl['profit'] < 0 ? 'Loss' : 'Profit' ?> <?= profit_label($pl['profit']) ?></th>
            <td class="money <?= $pl['profit'] < 0 ? 'text-loss' : 'text-profit' ?>"><?= money($pl['profit']) ?></td></tr>
    </table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
