<?php
// reports/index.php - list of all reports
require_once __DIR__ . '/../auth/auth_check.php';

$reportLinks = [
    ['report.php?type=daily-sales',    'Daily Sales',               'bi-calendar-day'],
    ['report.php?type=monthly-sales',  'Monthly Sales',             'bi-calendar-month'],
    ['report.php?type=yearly-sales',   'Yearly Sales',              'bi-calendar3'],
    ['report.php?type=income',         'Income Report',             'bi-graph-up-arrow'],
    ['report.php?type=expenses',       'Expense Report',            'bi-graph-down-arrow'],
    ['report.php?type=salary',         'Salary Report',             'bi-person-badge'],
    ['report.php?type=delivery',       'Delivery Report',           'bi-truck'],
    ['report.php?type=daily-running',  'Daily Running Cost Report', 'bi-calendar-day'],
    ['report.php?type=monthly-running','Monthly Running Cost Report','bi-calendar-month'],
    ['profit_loss.php',                'Profit & Loss Report',      'bi-bar-chart-line'],
    ['report.php?type=customers',      'Customer Report',           'bi-people'],
    ['report.php?type=orders',         'Laundry Order Report',      'bi-basket'],
    ['report.php?type=outstanding',    'Outstanding Balance Report','bi-hourglass-split'],
];
$summaryLinks = [
    ['daily.php',   'Daily Report',   'Orders, income, expenses and profit for one day', 'bi-calendar-day'],
    ['monthly.php', 'Monthly Report', 'Totals for a month and year',                     'bi-calendar-month'],
    ['yearly.php',  'Yearly Report',  'January to December breakdown',                   'bi-calendar3'],
];

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h1><i class="bi bi-file-earmark-text"></i> Reports</h1></div>

<div class="section-title">Summary Reports</div>
<div class="row g-3 mb-2">
    <?php foreach ($summaryLinks as [$link, $label, $desc, $icon]): ?>
        <div class="col-md-4">
            <a class="card shadow-sm h-100 text-decoration-none" href="<?= $link ?>"><div class="card-body">
                <h2 class="h6 mb-1"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></h2><small class="text-muted"><?= e($desc) ?></small>
            </div></a>
        </div>
    <?php endforeach; ?>
</div>

<div class="section-title">Detailed Reports</div>
<div class="row g-3">
    <?php foreach ($reportLinks as $i => [$link, $label, $icon]): ?>
        <div class="col-6 col-md-4 col-xl-3">
            <a class="card shadow-sm h-100 text-decoration-none" href="<?= $link ?>"><div class="card-body">
                <span class="text-muted small"><?= $i + 1 ?>.</span> <i class="bi <?= $icon ?>"></i> <?= e($label) ?>
            </div></a>
        </div>
    <?php endforeach; ?>
</div>
<p class="text-muted small mt-3">Every report has a date filter, search, print (and Save as PDF from the print window) and CSV export, which opens in Excel.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
