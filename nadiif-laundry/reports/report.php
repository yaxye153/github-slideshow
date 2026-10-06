<?php
// reports/report.php - shows one report as a table.
// Every report has: date filter, search, print and CSV (Excel) export.
// Example: reports/report.php?type=income&from=2026-10-01&to=2026-10-31
require_once __DIR__ . '/../auth/auth_check.php';

$type = (string)($_GET['type'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));

// Default dates: this month (some reports look further back)
[$monthFrom, $monthTo] = period_range('month');
$defaultFrom = $monthFrom;
if (in_array($type, ['yearly-sales', 'outstanding', 'customers', 'assets'], true)) {
    $defaultFrom = '2000-01-01';
}
$from = get_date('from', $defaultFrom);
$to = get_date('to', $monthTo);

// ---------------------------------------------------------------------
// Report definitions
//   sql   : the query (always uses ? ? for from/to dates)
//   money : columns shown as money
//   dates : columns shown as dates
//   sum   : columns added up in the TOTAL row
// ---------------------------------------------------------------------
$reports = [
    'daily-sales' => [
        'title' => 'Daily Sales',
        'note' => 'Orders by order date (cancelled orders not included). "Paid" is what has been paid on those orders so far.',
        'sql' => "SELECT order_date AS `Date`, COUNT(*) AS `Orders`, SUM(total_amount) AS `Sales`, SUM(amount_paid) AS `Paid`, SUM(balance) AS `Balance`
                  FROM orders WHERE status <> 'Cancelled' AND order_date BETWEEN ? AND ? GROUP BY order_date ORDER BY order_date",
        'money' => ['Sales', 'Paid', 'Balance'], 'dates' => ['Date'], 'sum' => ['Orders', 'Sales', 'Paid', 'Balance'],
    ],
    'monthly-sales' => [
        'title' => 'Monthly Sales',
        'note' => 'Orders by month (cancelled orders not included).',
        'sql' => "SELECT DATE_FORMAT(order_date, '%Y-%m') AS `Month`, COUNT(*) AS `Orders`, SUM(total_amount) AS `Sales`, SUM(amount_paid) AS `Paid`, SUM(balance) AS `Balance`
                  FROM orders WHERE status <> 'Cancelled' AND order_date BETWEEN ? AND ? GROUP BY DATE_FORMAT(order_date, '%Y-%m') ORDER BY `Month`",
        'money' => ['Sales', 'Paid', 'Balance'], 'dates' => [], 'sum' => ['Orders', 'Sales', 'Paid', 'Balance'],
    ],
    'yearly-sales' => [
        'title' => 'Yearly Sales',
        'note' => 'Orders by year (cancelled orders not included).',
        'sql' => "SELECT YEAR(order_date) AS `Year`, COUNT(*) AS `Orders`, SUM(total_amount) AS `Sales`, SUM(amount_paid) AS `Paid`, SUM(balance) AS `Balance`
                  FROM orders WHERE status <> 'Cancelled' AND order_date BETWEEN ? AND ? GROUP BY YEAR(order_date) ORDER BY `Year`",
        'money' => ['Sales', 'Paid', 'Balance'], 'dates' => [], 'sum' => ['Orders', 'Sales', 'Paid', 'Balance'],
    ],
    'income' => [
        'title' => 'Income Report',
        'note' => 'All income: order payments, delivery income and other income.',
        'sql' => "SELECT income_date AS `Date`, income_type AS `Type`, description AS `Description`, payment_method AS `Method`,
                         reference AS `Reference`, amount AS `Amount` FROM income WHERE income_date BETWEEN ? AND ? ORDER BY income_date, id",
        'money' => ['Amount'], 'dates' => ['Date'], 'sum' => ['Amount'],
    ],
    'expenses' => [
        'title' => 'Expense Report',
        'note' => 'All expenses: salaries, delivery costs, daily and monthly running costs and other expenses.',
        'sql' => "SELECT expense_date AS `Date`, category AS `Category`, description AS `Description`, payment_method AS `Method`,
                         reference AS `Reference`, amount AS `Amount` FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date, id",
        'money' => ['Amount'], 'dates' => ['Date'], 'sum' => ['Amount'],
    ],
    'salary' => [
        'title' => 'Salary Report',
        'sql' => "SELECT s.payment_date AS `Payment Date`, e.full_name AS `Employee`, e.position AS `Position`, s.salary_period AS `Period`,
                         s.payment_method AS `Method`, s.amount AS `Amount`
                  FROM salary_payments s JOIN employees e ON e.id = s.employee_id WHERE s.payment_date BETWEEN ? AND ? ORDER BY s.payment_date, s.id",
        'money' => ['Amount'], 'dates' => ['Payment Date'], 'sum' => ['Amount'],
    ],
    'delivery' => [
        'title' => 'Delivery Report',
        'note' => 'Profit = Delivery Income - Delivery Cost.',
        'sql' => "SELECT d.delivery_date AS `Date`, o.order_number AS `Order`, c.full_name AS `Customer`, d.delivery_person AS `Delivery Person`,
                         d.delivery_address AS `Address`, d.delivery_cost AS `Cost`, d.delivery_income AS `Income`, d.payment_status AS `Payment`,
                         (d.delivery_income - d.delivery_cost) AS `Profit`
                  FROM deliveries d LEFT JOIN orders o ON o.id = d.order_id LEFT JOIN customers c ON c.id = d.customer_id
                  WHERE d.delivery_date BETWEEN ? AND ? ORDER BY d.delivery_date, d.id",
        'money' => ['Cost', 'Income', 'Profit'], 'dates' => ['Date'], 'sum' => ['Cost', 'Income', 'Profit'],
    ],
    'daily-running' => [
        'title' => 'Daily Running Cost Report',
        'sql' => "SELECT cost_date AS `Date`, category AS `Category`, description AS `Description`, payment_method AS `Method`, amount AS `Amount`
                  FROM daily_running_costs WHERE cost_date BETWEEN ? AND ? ORDER BY cost_date, id",
        'money' => ['Amount'], 'dates' => ['Date'], 'sum' => ['Amount'],
    ],
    'monthly-running' => [
        'title' => 'Monthly Running Cost Report',
        'note' => 'Filtered by payment date.',
        'sql' => "SELECT CONCAT(cost_year, '-', LPAD(cost_month, 2, '0')) AS `For Month`, category AS `Category`, description AS `Description`,
                         payment_date AS `Paid On`, payment_method AS `Method`, amount AS `Amount`
                  FROM monthly_running_costs WHERE payment_date BETWEEN ? AND ? ORDER BY payment_date, id",
        'money' => ['Amount'], 'dates' => ['Paid On'], 'sum' => ['Amount'],
    ],
    'stock' => [
        'title' => 'Stock Purchase Report',
        'note' => 'Detergent, soap, starch... bought (also counted in Expenses).',
        'sql' => "SELECT b.purchase_date AS `Date`, i.name AS `Item`, v.name AS `Vendor`, CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM b.qty_in)), ' ', i.unit) AS `Quantity`,
                         b.expiry_date AS `Expiry`, b.unit_cost AS `Unit Cost`, b.total_cost AS `Total`
                  FROM stock_batches b JOIN stock_items i ON i.id = b.item_id LEFT JOIN vendors v ON v.id = b.vendor_id
                  WHERE b.purchase_date BETWEEN ? AND ? ORDER BY b.purchase_date, b.id",
        'money' => ['Unit Cost', 'Total'], 'dates' => ['Date', 'Expiry'], 'sum' => ['Total'],
    ],
    'assets' => [
        'title' => 'Company Assets Report',
        'note' => 'Assets bought between the dates (all assets: choose an early From date).',
        'sql' => "SELECT asset_code AS `Code`, name AS `Name`, category AS `Type`, location AS `Location`, purchase_date AS `Bought`,
                         condition_status AS `Condition`, IF(is_active, 'In use', 'Retired') AS `Status`, purchase_cost AS `Cost`
                  FROM assets WHERE COALESCE(purchase_date, '2000-01-01') BETWEEN ? AND ? ORDER BY category, asset_code, name",
        'money' => ['Cost'], 'dates' => ['Bought'], 'sum' => ['Cost'],
    ],
    'customers' => [
        'title' => 'Customer Report',
        'note' => 'All customers. Order totals only include orders inside the selected dates (cancelled orders not included).',
        'sql' => "SELECT c.customer_code AS `Code`, c.full_name AS `Customer`, c.tier AS `Level`, c.phone AS `Phone`, c.registration_date AS `Registered`,
                         COUNT(o.id) AS `Orders`, COALESCE(SUM(o.total_amount), 0) AS `Total Spent`, COALESCE(SUM(o.amount_paid), 0) AS `Paid`,
                         COALESCE(SUM(o.balance), 0) AS `Balance`
                  FROM customers c LEFT JOIN orders o ON o.customer_id = c.id AND o.status <> 'Cancelled' AND o.order_date BETWEEN ? AND ?
                  GROUP BY c.id ORDER BY c.full_name",
        'money' => ['Total Spent', 'Paid', 'Balance'], 'dates' => ['Registered'], 'sum' => ['Orders', 'Total Spent', 'Paid', 'Balance'],
    ],
    'orders' => [
        'title' => 'Laundry Order Report',
        'sql' => "SELECT o.order_number AS `Order`, o.order_date AS `Date`, c.full_name AS `Customer`, c.phone AS `Phone`, o.status AS `Status`,
                         o.service_speed AS `Package`, o.shelf_number AS `Shelf`, o.created_by_name AS `Created By`, o.payment_status AS `Payment`, o.total_amount AS `Total`, o.amount_paid AS `Paid`, o.balance AS `Balance`
                  FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.order_date BETWEEN ? AND ? ORDER BY o.order_date, o.id",
        'money' => ['Total', 'Paid', 'Balance'], 'dates' => ['Date'], 'sum' => ['Total', 'Paid', 'Balance'],
    ],
    'outstanding' => [
        'title' => 'Outstanding Balance Report',
        'note' => 'Orders that still have money to pay (cancelled orders not included).',
        'sql' => "SELECT o.order_number AS `Order`, o.order_date AS `Date`, c.full_name AS `Customer`, c.phone AS `Phone`, o.status AS `Status`,
                         o.total_amount AS `Total`, o.amount_paid AS `Paid`, o.balance AS `Balance`
                  FROM orders o JOIN customers c ON c.id = o.customer_id
                  WHERE o.balance > 0 AND o.status <> 'Cancelled' AND o.order_date BETWEEN ? AND ? ORDER BY o.order_date, o.id",
        'money' => ['Total', 'Paid', 'Balance'], 'dates' => ['Date'], 'sum' => ['Total', 'Paid', 'Balance'],
    ],
];

if (!isset($reports[$type])) {
    redirect('reports/index.php');
}
$report = $reports[$type];

// Run the report using real database records
$rows = db_all($pdo, $report['sql'], [$from, $to]);

// Search: keep only rows that contain the search text in any column
if ($q !== '') {
    $rows = array_values(array_filter($rows, function ($row) use ($q) {
        return stripos(implode(' ', array_map('strval', $row)), $q) !== false;
    }));
}

// Add up the total row
$totals = [];
foreach ($report['sum'] as $col) {
    $totals[$col] = array_sum(array_map('floatval', array_column($rows, $col)));
}
$columns = $rows ? array_keys($rows[0]) : [];

// CSV export (opens in Excel)
if (($_GET['export'] ?? '') === 'csv') {
    if (!$columns) {
        // Get the column names even when there are no rows
        $stmt = db_query($pdo, $report['sql'], [$from, $to]);
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $columns[] = $stmt->getColumnMeta($i)['name'];
        }
    }
    if ($totals) {
        $totalRow = [];
        foreach ($columns as $i => $col) {
            $totalRow[] = isset($totals[$col]) ? round($totals[$col], 2) : ($i === 0 ? 'TOTAL' : '');
        }
        $rows[] = array_combine($columns, $totalRow);
    }
    send_csv('nadiif_' . $type . '_' . $from . '_to_' . $to . '.csv', $columns, $rows);
}

$pageTitle = $report['title'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-file-earmark-text"></i> <?= e($report['title']) ?></h1>
    <div class="d-flex gap-2 flex-wrap no-print">
        <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Reports</a>
        <button class="btn btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>
        <a class="btn btn-success" href="?<?= e(http_build_query(['type' => $type, 'from' => $from, 'to' => $to, 'q' => $q, 'export' => 'csv'])) ?>"><i class="bi bi-filetype-csv"></i> CSV / Excel</a>
    </div>
</div>

<div class="print-only mb-3">
    <h2 class="h4 mb-0"><?= e(setting('business_name')) ?></h2>
    <div><?= e($report['title']) ?> &mdash; <?= show_date($from) ?> to <?= show_date($to) ?><?= $q !== '' ? ' &mdash; search: "' . e($q) . '"' : '' ?></div>
    <small>Printed <?= date('d M Y H:i') ?></small>
</div>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <div class="row g-2">
        <div class="col-6 col-md-3"><label class="form-label small mb-1">From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-12 col-md-4"><label class="form-label small mb-1">Search</label><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search in this report"></div>
        <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Show</button></div>
    </div>
</form>

<?php if (!empty($report['note'])): ?><p class="text-muted small"><?= e($report['note']) ?></p><?php endif; ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
            <thead><tr><?php foreach ($columns as $col): ?><th class="<?= in_array($col, $report['money'], true) ? 'money' : '' ?>"><?= e($col) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row as $col => $value): ?>
                        <?php if (in_array($col, $report['money'], true)): ?>
                            <td class="money"><?= money($value) ?></td>
                        <?php elseif (in_array($col, $report['dates'], true)): ?>
                            <td><?= show_date($value) ?></td>
                        <?php else: ?>
                            <td><?= e($value) ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td class="text-center text-muted py-4">No records found for these dates.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($rows && $totals): ?>
                <tfoot><tr class="fw-bold">
                    <?php foreach ($columns as $i => $col): ?>
                        <td class="<?= in_array($col, $report['money'], true) ? 'money' : '' ?>">
                            <?php if (isset($totals[$col])): ?>
                                <?= in_array($col, $report['money'], true) ? money($totals[$col]) : e((string)$totals[$col]) ?>
                            <?php elseif ($i === 0): ?>TOTAL<?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<p class="small text-muted mt-2 no-print"><?= count($rows) ?> record(s). To save as PDF, click "Print / PDF" and choose "Save as PDF".</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
