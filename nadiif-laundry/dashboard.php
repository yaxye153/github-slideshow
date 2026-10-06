<?php
// dashboard.php - main overview of the business.
// Every number below comes directly from records saved in the database.
require_once __DIR__ . '/auth/auth_check.php';

$today = date('Y-m-d');
[$monthFrom, $monthTo] = period_range('month');
[$yearFrom, $yearTo] = period_range('year');

// Customers
$totalCustomers = (int)db_value($pdo, 'SELECT COUNT(*) FROM customers');

// Orders: count per status
$statusCounts = array_fill_keys(order_statuses(), 0);
foreach (db_all($pdo, 'SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
    $statusCounts[$row['status']] = (int)$row['n'];
}
$totalOrders = array_sum($statusCounts);
$inProcess = $statusCounts['Washing'] + $statusCounts['Drying'] + $statusCounts['Ironing'];
// Orders that should already be ready but are not
$overdue = (int)db_value($pdo, "SELECT COUNT(*) FROM orders WHERE status NOT IN ('Ready', 'Delivered', 'Cancelled') AND ready_at < ?", [date('Y-m-d H:i:s')]);
$expressActive = (int)db_value($pdo, "SELECT COUNT(*) FROM orders WHERE status NOT IN ('Delivered', 'Cancelled') AND service_speed <> 'Normal'");

// Financial: Calculate profit = income - expenses
$todayIncome = total_income($pdo, $today, $today);
$todayExpenses = total_expenses($pdo, $today, $today);
$monthIncome = total_income($pdo, $monthFrom, $monthTo);
$monthExpenses = total_expenses($pdo, $monthFrom, $monthTo);
$yearIncome = total_income($pdo, $yearFrom, $yearTo);
$yearExpenses = total_expenses($pdo, $yearFrom, $yearTo);

// Money customers still owe (cancelled orders are not counted)
$outstanding = (float)db_value($pdo, "SELECT COALESCE(SUM(balance), 0) FROM orders WHERE status <> 'Cancelled' AND balance > 0");

// Business costs this month (by where the expense came from)
$monthSalaries = total_expenses($pdo, $monthFrom, $monthTo, 'salary');
$monthDelivery = total_expenses($pdo, $monthFrom, $monthTo, 'delivery');
$monthDaily = total_expenses($pdo, $monthFrom, $monthTo, 'daily_running');
$monthMonthly = total_expenses($pdo, $monthFrom, $monthTo, 'monthly_running');
$todayDaily = total_expenses($pdo, $today, $today, 'daily_running');

// Latest orders
$latestOrders = db_all($pdo, 'SELECT o.id, o.order_number, o.order_date, o.status, o.total_amount, o.balance, c.full_name
                              FROM orders o JOIN customers c ON c.id = o.customer_id
                              ORDER BY o.id DESC LIMIT 8');

// Small helper to print one card
function stat_card(string $label, string $value, string $icon, string $color = ''): string
{
    $html = '<div class="col-6 col-md-4 col-xl-3"><div class="card stat-card shadow-sm h-100 ' . $color . '">'
          . '<div class="card-body d-flex justify-content-between align-items-center"><div>'
          . '<div class="stat-label">' . e($label) . '</div><div class="stat-value">' . $value . '</div></div>'
          . '<i class="bi ' . $icon . ' stat-icon"></i></div></div></div>';
    return $html;
}

function profit_value(float $p): string
{
    return '<span class="' . ($p < 0 ? 'text-loss' : 'text-profit') . '">' . money($p) . '</span> ' . profit_label($p);
}

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <h1>Dashboard</h1>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('orders')): ?><a class="btn btn-primary" href="<?= url('orders/form.php') ?>"><i class="bi bi-plus-lg"></i> New Order</a><?php endif; ?>
        <?php if (can('customers')): ?><a class="btn btn-outline-primary" href="<?= url('customers/form.php') ?>"><i class="bi bi-person-plus"></i> New Customer</a><?php endif; ?>
        <?php if (can('orders')): ?><a class="btn btn-outline-info" href="<?= url('tracking/index.php') ?>"><i class="bi bi-geo-alt"></i> Order Tracking</a><?php endif; ?>
    </div>
</div>

<?php if (!empty($_SESSION['default_password'])): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-shield-exclamation"></i> For better security, change your default admin password from Settings.</span>
        <a class="btn btn-sm btn-warning" href="<?= url('account.php') ?>">Change Password</a>
    </div>
<?php endif; ?>

<?php
// Warn when the daily email backup is switched on but is not working
$mailConfig = mail_config();
$lastEmail = strtotime(setting('email_backup_last_success', '')) ?: 0;
$emailProblem = $mailConfig['enabled'] && (strpos(setting('email_backup_last_message'), 'FAILED') === 0 || ($lastEmail && $lastEmail < strtotime('-2 days')));
?>
<?php if ($emailProblem && is_admin()): ?>
    <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-envelope-exclamation"></i> The daily email backup is not working. Last successful email: <?= $lastEmail ? show_datetime(date('Y-m-d H:i:s', $lastEmail)) : 'never' ?>.</span>
        <a class="btn btn-sm btn-danger" href="<?= url('backup/email.php') ?>">Check Email Backup</a>
    </div>
<?php endif; ?>

<?php
// Security alert for the admin: dangerous events in the last 24 hours
if (is_admin()):
    $dangerous = (int)db_value($pdo, "SELECT COUNT(*) FROM security_log WHERE severity = 'danger' AND created_at >= ?", [date('Y-m-d H:i:s', time() - 86400)]);
    $failedLogins = (int)db_value($pdo, "SELECT COUNT(*) FROM security_log WHERE event = 'login_failed' AND created_at >= ?", [date('Y-m-d H:i:s', time() - 86400)]);
    if ($dangerous > 0 || $failedLogins >= 5): ?>
        <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-shield-exclamation"></i> Security: <?= $dangerous ?> dangerous event(s) and <?= $failedLogins ?> failed login(s) in the last 24 hours.</span>
            <a class="btn btn-sm btn-danger" href="<?= url('security/index.php') ?>">Open Security Report</a>
        </div>
<?php endif; endif; ?>

<?php if (can('finance_dashboard')): ?>
<div class="section-title">Financial Summary</div>
<div class="row g-3">
    <?= stat_card("Today's Income", money($todayIncome), 'bi-graph-up-arrow', 'green') ?>
    <?= stat_card("Today's Expenses", money($todayExpenses), 'bi-graph-down-arrow', 'red') ?>
    <?= stat_card("Today's Profit/Loss", profit_value($todayIncome - $todayExpenses), 'bi-wallet2') ?>
    <?= stat_card('Outstanding Customer Balance', money($outstanding), 'bi-hourglass-split', 'orange') ?>
    <?= stat_card('Monthly Income', money($monthIncome), 'bi-graph-up-arrow', 'green') ?>
    <?= stat_card('Monthly Expenses', money($monthExpenses), 'bi-graph-down-arrow', 'red') ?>
    <?= stat_card('Monthly Profit/Loss', profit_value($monthIncome - $monthExpenses), 'bi-wallet2') ?>
    <?= stat_card('Yearly Profit/Loss', profit_value($yearIncome - $yearExpenses), 'bi-calendar3') ?>
</div>

<?php endif; ?>

<div class="section-title">Orders</div>
<div class="row g-3">
    <?= stat_card('Total Customers', (string)$totalCustomers, 'bi-people', 'teal') ?>
    <?= stat_card('Total Orders', (string)$totalOrders, 'bi-basket') ?>
    <?= stat_card('Pending (Received)', (string)$statusCounts['Received'], 'bi-inbox', 'orange') ?>
    <?= stat_card('Washing / Drying / Ironing', (string)$inProcess, 'bi-water') ?>
    <?= stat_card('Ready', (string)$statusCounts['Ready'], 'bi-check2-circle', 'orange') ?>
    <?= stat_card('Delivered', (string)$statusCounts['Delivered'], 'bi-truck', 'green') ?>
    <?= stat_card('Cancelled', (string)$statusCounts['Cancelled'], 'bi-x-circle', 'red') ?>
    <?= stat_card('Overdue (not ready in time)', (string)$overdue, 'bi-alarm', 'red') ?>
    <?= stat_card('Express / VIP in shop', (string)$expressActive, 'bi-lightning-charge', 'orange') ?>
</div>

<?php if (can('finance_dashboard')): ?>
<div class="section-title">Business Costs (<?= date('F Y') ?>)</div>
<div class="row g-3">
    <?= stat_card('Salaries', money($monthSalaries), 'bi-person-badge', 'red') ?>
    <?= stat_card('Delivery Costs', money($monthDelivery), 'bi-truck', 'red') ?>
    <?= stat_card('Daily Running (today)', money($todayDaily), 'bi-calendar-day', 'red') ?>
    <?= stat_card('Daily Running (month)', money($monthDaily), 'bi-calendar-day', 'red') ?>
    <?= stat_card('Monthly Running Costs', money($monthMonthly), 'bi-calendar-month', 'red') ?>
</div>

<?php endif; ?>

<?php if (can('orders')): ?>
<div class="section-title">Latest Orders</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Status</th><th class="money">Total</th><th class="money">Balance</th></tr></thead>
            <tbody>
            <?php foreach ($latestOrders as $o): ?>
                <tr>
                    <td><a href="<?= url('orders/view.php?id=' . $o['id']) ?>"><?= e($o['order_number']) ?></a></td>
                    <td><?= e($o['full_name']) ?></td>
                    <td><?= show_date($o['order_date']) ?></td>
                    <td><?= badge($o['status']) ?></td>
                    <td class="money"><?= money($o['total_amount']) ?></td>
                    <td class="money"><?= money($o['balance']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$latestOrders): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No orders yet. Click "New Order" to create the first one.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
