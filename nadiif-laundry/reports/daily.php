<?php
// reports/daily.php - DAILY REPORT for one selected date
require_once __DIR__ . '/../auth/auth_check.php';

$date = get_date('date', date('Y-m-d'));
$pl = profit_and_loss($pdo, $date, $date);
$otherExpenses = $pl['by_source']['manual'] + $pl['by_source']['monthly_running'];

$pageTitle = 'Daily Report';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-calendar-day"></i> Daily Report</h1>
    <div class="d-flex gap-2 no-print">
        <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Reports</a>
        <button class="btn btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
    </div>
</div>
<form class="card card-body shadow-sm mb-3 filter-form" method="get" style="max-width: 760px">
    <div class="input-group">
        <input class="form-control" type="date" name="date" value="<?= e($date) ?>">
        <button class="btn btn-primary" type="submit">Show</button>
    </div>
</form>

<div class="card shadow-sm" style="max-width: 760px"><div class="card-body">
    <h2 class="h5"><?= e(setting('business_name')) ?> &mdash; Daily Report</h2>
    <table class="table mb-0">
        <tr><th>Date</th><td class="money"><?= show_date($date) ?></td></tr>
        <tr><th>Orders</th><td class="money"><?= $pl['orders'] ?> (value <?= money($pl['sales']) ?>)</td></tr>
        <tr><th>Income</th><td class="money text-profit"><?= money($pl['income']) ?></td></tr>
        <tr><th>Expenses (total)</th><td class="money text-loss"><?= money($pl['expenses']) ?></td></tr>
        <tr><td class="ps-4">Salary</td><td class="money"><?= money($pl['by_source']['salary']) ?></td></tr>
        <tr><td class="ps-4">Delivery Cost</td><td class="money"><?= money($pl['by_source']['delivery']) ?></td></tr>
        <tr><td class="ps-4">Daily Running Cost</td><td class="money"><?= money($pl['by_source']['daily_running']) ?></td></tr>
        <tr><td class="ps-4">Other expenses (incl. monthly costs paid today)</td><td class="money"><?= money($otherExpenses) ?></td></tr>
        <tr class="fs-5 fw-bold"><th>Net <?= $pl['profit'] < 0 ? 'Loss' : 'Profit' ?> <?= profit_label($pl['profit']) ?></th>
            <td class="money <?= $pl['profit'] < 0 ? 'text-loss' : 'text-profit' ?>"><?= money($pl['profit']) ?></td></tr>
    </table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
