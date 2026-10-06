<?php
// dashboard.php - the main screen.
// It shows only what needs action now:
//   NEW orders (just received)  and  READY orders (waiting for the customer / delivery)
// plus important alarms. Money totals are in Profit & Loss and Reports.
require_once __DIR__ . '/auth/auth_check.php';
require_once APP_ROOT . '/includes/tracker.php';

$now = date('Y-m-d H:i:s');

// Counts for the strip at the top
$counts = ['Received' => 0, 'Washing' => 0, 'Drying' => 0, 'Ironing' => 0, 'Ready' => 0, 'Out for Delivery' => 0];
foreach (db_all($pdo, "SELECT status, COUNT(*) AS n FROM orders WHERE status NOT IN ('Delivered', 'Cancelled') GROUP BY status") as $r) {
    $counts[$r['status']] = (int)$r['n'];
}
$inWork = $counts['Washing'] + $counts['Drying'] + $counts['Ironing'];
$late = (int)db_value($pdo, "SELECT COUNT(*) FROM orders WHERE status IN ('Received', 'Washing', 'Drying', 'Ironing') AND ready_at < ?", [$now]);
$todayOrders = (int)db_value($pdo, 'SELECT COUNT(*) FROM orders WHERE order_date = ?', [date('Y-m-d')]);

// The two lists: Gold first, then the one that must be ready soonest
$newOrders = can('orders') ? db_all($pdo, 'SELECT ' . tracker_select() . " FROM orders o JOIN customers c ON c.id = o.customer_id
    WHERE o.status = 'Received' ORDER BY FIELD(o.service_speed, 'Gold', 'Silver', 'Normal'), o.ready_at IS NULL, o.ready_at, o.id LIMIT 30") : [];
$readyOrders = can('orders') ? db_all($pdo, 'SELECT ' . tracker_select() . " FROM orders o JOIN customers c ON c.id = o.customer_id
    WHERE o.status IN ('Ready', 'Out for Delivery') ORDER BY o.status = 'Out for Delivery', o.notified_at IS NOT NULL, o.ready_at, o.id LIMIT 30") : [];

// Alarms
$stock = can('stock') ? stock_alerts($pdo) : ['count' => 0, 'low' => [], 'expired' => [], 'expiring' => []];
$security = ['danger' => 0, 'failed' => 0];
$emailProblem = false;
if (is_admin()) {
    $since = date('Y-m-d H:i:s', time() - 86400);
    $security['danger'] = (int)db_value($pdo, "SELECT COUNT(*) FROM security_log WHERE severity = 'danger' AND created_at >= ?", [$since]);
    $security['failed'] = (int)db_value($pdo, "SELECT COUNT(*) FROM security_log WHERE event = 'login_failed' AND created_at >= ?", [$since]);
    $mailConfig = mail_config();
    $lastEmail = strtotime(setting('email_backup_last_success', '')) ?: 0;
    $emailProblem = $mailConfig['enabled'] && (strpos(setting('email_backup_last_message'), 'FAILED') === 0 || ($lastEmail && $lastEmail < strtotime('-2 days')));
}

// Greeting
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// One compact order row with its quick buttons
function dash_order(array $o): string
{
    ob_start();
    $hidden = csrf_field() . '<input type="hidden" name="id" value="' . (int)$o['id'] . '"><input type="hidden" name="return" value="dashboard">';
    ?>
    <div class="dash-order">
        <div class="d-flex justify-content-between gap-2">
            <div class="min-w-0">
                <a class="fw-bold" href="<?= url('orders/view.php?id=' . (int)$o['id']) ?>"><?= e($o['order_number']) ?></a>
                <?= speed_badge($o['service_speed']) ?> <?= ready_label($o) ?>
                <?= $o['status'] === 'Out for Delivery' ? '<span class="badge bg-primary"><i class="bi bi-truck"></i> on the way</span>' : '' ?>
                <div class="text-truncate"><?= e($o['full_name']) ?> <span class="text-muted small"><?= e($o['phone']) ?></span></div>
                <div class="small text-muted text-truncate"><?= e($o['items']) ?></div>
            </div>
            <div class="text-end flex-shrink-0">
                <span class="shelf"><?= e($o['shelf_number']) ?: '-' ?></span>
                <div class="small <?= $o['balance'] > 0 ? 'text-loss' : 'text-profit' ?>"><?= $o['balance'] > 0 ? 'owes ' . money($o['balance']) : 'paid' ?></div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1 mt-2">
            <?php if ($o['status'] === 'Received'): ?>
                <form method="post" action="<?= url('orders/status.php') ?>"><?= $hidden ?><input type="hidden" name="status" value="Washing">
                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-water"></i> Start washing</button></form>
            <?php else: ?>
                <form method="post" action="<?= url('orders/notify.php') ?>" target="_blank"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                    <button class="btn btn-sm <?= $o['notified_at'] ? 'btn-outline-success' : 'btn-success' ?>" name="via" value="whatsapp" type="submit"><i class="bi bi-whatsapp"></i><?= $o['notified_at'] ? ' told' : ' Tell customer' ?></button></form>
                <?php $next = next_status($o); ?>
                <?php if ($next === 'Out for Delivery'): ?>
                    <form method="post" action="<?= url('orders/status.php') ?>"><?= $hidden ?><input type="hidden" name="status" value="Out for Delivery">
                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-truck"></i> Send out</button></form>
                <?php elseif ($next === 'Delivered'): ?>
                    <form method="post" action="<?= url('orders/status.php') ?>" class="d-flex gap-1"><?= $hidden ?><input type="hidden" name="status" value="Delivered">
                        <input class="form-control form-control-sm" name="received_by" placeholder="Received by" style="width: 110px" maxlength="100">
                        <button class="btn btn-sm btn-outline-success text-nowrap" type="submit"><i class="bi bi-person-check"></i> <?= $o['pickup_type'] === 'Delivery' ? 'Delivered' : 'Picked up' ?></button></form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($o['balance'] > 0 && can('payments')): ?><a class="btn btn-sm btn-outline-success" href="<?= url('payments/add.php?order_id=' . (int)$o['id']) ?>" title="Take payment"><i class="bi bi-cash"></i></a><?php endif; ?>
        </div>
    </div>
    <?php
    return (string)ob_get_clean();
}

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="dash-hero mb-3">
    <div>
        <div class="small opacity-75"><?= date('l, d F Y') ?></div>
        <h1 class="h3 mb-0"><?= e($greeting) ?>, <?= e($CURRENT_USER['full_name'] ?: $CURRENT_USER['username']) ?></h1>
        <div class="small opacity-75"><?= $todayOrders ?> new order(s) today</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('orders')): ?><a class="btn btn-light btn-lg" href="<?= url('orders/form.php') ?>"><i class="bi bi-plus-lg"></i> New Order</a>
            <a class="btn btn-outline-light" href="<?= url('tracking/index.php') ?>"><i class="bi bi-geo-alt"></i> Tracking</a><?php endif; ?>
        <?php if (can('customers')): ?><a class="btn btn-outline-light" href="<?= url('customers/form.php') ?>"><i class="bi bi-person-plus"></i> Customer</a><?php endif; ?>
        <?php if (can('reports')): ?><a class="btn btn-outline-light" href="<?= url('reports/profit_loss.php') ?>"><i class="bi bi-bar-chart-line"></i> Profit &amp; Loss</a><?php endif; ?>
    </div>
</div>

<?php // ---------- Alarms (only when there is something) ---------- ?>
<?php if (!empty($_SESSION['default_password'])): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
        <span><i class="bi bi-shield-exclamation"></i> For better security, change your default admin password.</span>
        <a class="btn btn-sm btn-warning" href="<?= url('account.php') ?>">Change Password</a>
    </div>
<?php endif; ?>
<?php if ($security['danger'] > 0 || $security['failed'] >= 5): ?>
    <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
        <span><i class="bi bi-shield-exclamation"></i> Security: <?= $security['danger'] ?> dangerous event(s) and <?= $security['failed'] ?> failed login(s) in the last 24 hours.</span>
        <a class="btn btn-sm btn-danger" href="<?= url('security/index.php') ?>">Security Report</a>
    </div>
<?php endif; ?>
<?php if ($emailProblem): ?>
    <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
        <span><i class="bi bi-envelope-exclamation"></i> The daily email backup is not working.</span>
        <a class="btn btn-sm btn-danger" href="<?= url('backup/email.php') ?>">Check Email Backup</a>
    </div>
<?php endif; ?>
<?php if ($stock['count']): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
        <span><i class="bi bi-bell-fill"></i> Stock alarm:
            <?= e(implode(', ', array_filter([count($stock['low']) ? count($stock['low']) . ' low' : '', count($stock['expired']) ? count($stock['expired']) . ' expired' : '',
                count($stock['expiring']) ? count($stock['expiring']) . ' expiring soon' : '']))) ?>
            <?= $stock['low'] ? '&mdash; ' . e(implode(', ', array_slice(array_column($stock['low'], 'name'), 0, 4))) : '' ?></span>
        <a class="btn btn-sm btn-warning" href="<?= url('stock/index.php') ?>">Open Stock</a>
    </div>
<?php endif; ?>

<?php if (can('orders')): ?>
    <?php // ---------- Strip: where the orders are ---------- ?>
    <div class="row g-2 mb-3">
        <?php foreach ([['New', $counts['Received'], 'bi-inbox', 'status=Received', ''], ['In work', $inWork, 'bi-water', 'view=work', ''],
                        ['Ready', $counts['Ready'], 'bi-check2-circle', 'status=Ready', 'orange'], ['On the way', $counts['Out for Delivery'], 'bi-truck', 'status=Out+for+Delivery', ''],
                        ['Late', $late, 'bi-alarm', 'view=work', $late ? 'red' : 'green']] as [$label, $n, $icon, $link, $color]): ?>
            <div class="col">
                <a class="card stat-card <?= $color ?> shadow-sm text-decoration-none h-100" href="<?= url('tracking/index.php?' . $link) ?>"><div class="card-body p-2 text-center">
                    <i class="bi <?= $icon ?>"></i><div class="stat-value"><?= $n ?></div><div class="stat-label"><?= $label ?></div>
                </div></a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php // ---------- The two lists ---------- ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 dash-panel">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-inbox"></i> <b>New Orders</b> <span class="badge bg-secondary"><?= $counts['Received'] ?></span></span>
                    <a class="small" href="<?= url('tracking/index.php?status=Received') ?>">See all</a>
                </div>
                <div class="card-body p-2">
                    <?php foreach ($newOrders as $o) { echo dash_order($o); } ?>
                    <?php if (!$newOrders): ?><div class="text-center text-muted py-4"><i class="bi bi-emoji-smile fs-3 d-block"></i>No new orders waiting.</div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 dash-panel ready">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-check2-circle"></i> <b>Ready Orders</b> <span class="badge bg-warning text-dark"><?= $counts['Ready'] + $counts['Out for Delivery'] ?></span></span>
                    <a class="small" href="<?= url('tracking/index.php?view=handover') ?>">See all</a>
                </div>
                <div class="card-body p-2">
                    <?php foreach ($readyOrders as $o) { echo dash_order($o); } ?>
                    <?php if (!$readyOrders): ?><div class="text-center text-muted py-4"><i class="bi bi-check-all fs-3 d-block"></i>No orders waiting for the customer.</div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-light border">Use the menu to open the sections you have permission for.</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
