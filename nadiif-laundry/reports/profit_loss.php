<?php
// reports/profit_loss.php - PROFIT & LOSS
// Gross Income - Expenses = Net Profit (or Net Loss)
require_once __DIR__ . '/../auth/auth_check.php';

$period = in_list((string)($_GET['period'] ?? 'month'), ['today', 'week', 'month', 'year', 'custom'], 'month');
$date = get_date('date', date('Y-m-d'));

if ($period === 'custom') {
    [$mFrom, $mTo] = period_range('month');
    $from = get_date('from', $mFrom);
    $to = get_date('to', $mTo);
} else {
    [$from, $to] = period_range($period, $date);
}
$pl = profit_and_loss($pdo, $from, $to);

$labels = ['today' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly', 'custom' => 'Custom'];

$pageTitle = 'Profit & Loss';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-bar-chart-line"></i> Profit &amp; Loss</h1>
    <button class="btn btn-outline-dark no-print" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
</div>

<ul class="nav nav-pills mb-3 no-print">
    <?php foreach ($labels as $key => $label): if ($key === 'custom') { continue; } ?>
        <li class="nav-item"><a class="nav-link<?= $period === $key ? ' active' : '' ?>" href="?period=<?= $key ?>&date=<?= e($date) ?>"><?= $label ?></a></li>
    <?php endforeach; ?>
</ul>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label small mb-1">Period</label>
            <select class="form-select" name="period" id="period">
                <?php foreach ($labels as $key => $label): ?><option value="<?= $key ?>" <?= $period === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 single-date"><label class="form-label small mb-1">Any date in the period</label><input class="form-control" type="date" name="date" value="<?= e($date) ?>"></div>
        <div class="col-6 col-md-2 custom-date"><label class="form-label small mb-1">From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-2 custom-date"><label class="form-label small mb-1">To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit">Show</button></div>
    </div>
</form>

<div class="card shadow-sm" style="max-width: 760px">
    <div class="card-body">
        <h2 class="h5"><?= e(setting('business_name')) ?> &mdash; <?= $labels[$period] ?> Profit &amp; Loss</h2>
        <p class="text-muted"><?= show_date($from) ?><?= $from !== $to ? ' to ' . show_date($to) : '' ?></p>

        <table class="table">
            <tr class="table-light"><th colspan="2">INCOME</th></tr>
            <?php foreach ($pl['income_by_type'] as $type => $amount): ?>
                <tr><td class="ps-4"><?= e($type) ?></td><td class="money"><?= money($amount) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$pl['income_by_type']): ?><tr><td class="ps-4 text-muted">No income recorded</td><td class="money"><?= money(0) ?></td></tr><?php endif; ?>
            <tr class="fw-bold"><td>Gross Income</td><td class="money text-profit"><?= money($pl['income']) ?></td></tr>

            <tr class="table-light"><th colspan="2">EXPENSES</th></tr>
            <tr><td class="ps-4">Salaries</td><td class="money"><?= money($pl['by_source']['salary']) ?></td></tr>
            <tr><td class="ps-4">Delivery Costs</td><td class="money"><?= money($pl['by_source']['delivery']) ?></td></tr>
            <tr><td class="ps-4">Daily Running Costs</td><td class="money"><?= money($pl['by_source']['daily_running']) ?></td></tr>
            <tr><td class="ps-4">Monthly Running Costs</td><td class="money"><?= money($pl['by_source']['monthly_running']) ?></td></tr>
            <tr><td class="ps-4">Stock Purchases (detergent, soap...)</td><td class="money"><?= money($pl['by_source']['stock']) ?></td></tr>
            <?php if ($pl['by_source']['asset'] > 0): ?><tr><td class="ps-4">Asset Purchases</td><td class="money"><?= money($pl['by_source']['asset']) ?></td></tr><?php endif; ?>
            <?php foreach ($pl['manual_by_category'] as $cat => $amount): ?>
                <tr><td class="ps-4"><?= e($cat) ?> <small class="text-muted">(other expenses)</small></td><td class="money"><?= money($amount) ?></td></tr>
            <?php endforeach; ?>
            <tr class="fw-bold"><td>Total Expenses</td><td class="money text-loss"><?= money($pl['expenses']) ?></td></tr>

            <tr class="fs-5 fw-bold border-top border-2">
                <td>NET <?= $pl['profit'] < 0 ? 'LOSS' : 'PROFIT' ?> <?= profit_label($pl['profit']) ?></td>
                <td class="money <?= $pl['profit'] < 0 ? 'text-loss' : 'text-profit' ?>"><?= money($pl['profit']) ?></td>
            </tr>
        </table>
        <p class="small text-muted mb-0">Calculated only from recorded income and expenses: Total Income - Total Expenses = Net Profit.
            Orders in this period: <?= $pl['orders'] ?> (value <?= money($pl['sales']) ?>). Unpaid order balances are not income until they are paid.</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var period = document.getElementById('period');
    function toggle() {
        var custom = period.value === 'custom';
        document.querySelectorAll('.custom-date').forEach(function (el) { el.style.display = custom ? '' : 'none'; });
        document.querySelectorAll('.single-date').forEach(function (el) { el.style.display = custom ? 'none' : ''; });
    }
    period.addEventListener('change', toggle);
    toggle();
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
