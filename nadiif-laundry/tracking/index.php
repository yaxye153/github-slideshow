<?php
// tracking/index.php - ORDER TRACKING
// Choose a customer to see how many orders they have in the shop,
// the shelf number of each order and where it is now
// (Received -> Washing -> Drying -> Ironing -> Ready -> Delivered).
require_once __DIR__ . '/../auth/auth_check.php';

$customerId = (int)($_GET['customer_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = in_list((string)($_GET['status'] ?? ''), order_statuses(), '');
$speedFilter = in_list((string)($_GET['speed'] ?? ''), array_keys(service_speeds()), '');

$steps = ['Received' => 'bi-inbox', 'Washing' => 'bi-water', 'Drying' => 'bi-wind', 'Ironing' => 'bi-thermometer-high',
          'Ready' => 'bi-check2-circle', 'Delivered' => 'bi-truck'];
$stepNames = array_keys($steps);

// Find the customer by phone, name or code
if (!$customerId && $q !== '') {
    $found = db_all($pdo, 'SELECT id FROM customers WHERE phone LIKE ? OR alt_phone LIKE ? OR full_name LIKE ? OR customer_code = ? LIMIT 2',
        ["%$q%", "%$q%", "%$q%", $q]);
    if (count($found) === 1) {
        $customerId = (int)$found[0]['id'];
    }
}

$customer = $customerId ? db_row($pdo, 'SELECT * FROM customers WHERE id = ?', [$customerId]) : null;
$customers = db_all($pdo, "SELECT c.id, c.full_name, c.phone, c.customer_code,
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.status NOT IN ('Delivered', 'Cancelled')) AS active
    FROM customers c ORDER BY c.full_name");

// Orders still in the shop (not Delivered / Cancelled)
$where = ["o.status NOT IN ('Delivered', 'Cancelled')"];
$params = [];
if ($customer) {
    $where[] = 'o.customer_id = ?';
    $params[] = $customer['id'];
} elseif ($q !== '') {
    $where[] = '(c.full_name LIKE ? OR c.phone LIKE ? OR o.order_number LIKE ? OR o.shelf_number LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($statusFilter !== '') { $where[] = 'o.status = ?'; $params[] = $statusFilter; }
if ($speedFilter !== '') { $where[] = 'o.service_speed = ?'; $params[] = $speedFilter; }

// VIP first, then the order that must be ready soonest
$activeOrders = db_all($pdo, "SELECT o.*, c.full_name, c.phone, c.tier,
        (SELECT GROUP_CONCAT(CONCAT(i.quantity, ' ', i.item_name) SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) AS items
    FROM orders o JOIN customers c ON c.id = o.customer_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY FIELD(o.service_speed, 'VIP', 'Express', 'Normal'), o.ready_at IS NULL, o.ready_at, o.id", $params);

// Counts per status (whole shop)
$counts = array_fill_keys(array_slice($stepNames, 0, 5), 0);
foreach (db_all($pdo, "SELECT status, COUNT(*) AS n FROM orders WHERE status NOT IN ('Delivered', 'Cancelled') GROUP BY status") as $r) {
    $counts[$r['status']] = (int)$r['n'];
}
$overdue = (int)db_value($pdo, "SELECT COUNT(*) FROM orders WHERE status NOT IN ('Ready', 'Delivered', 'Cancelled') AND ready_at < ?", [date('Y-m-d H:i:s')]);

// Last delivered orders of this customer
$delivered = $customer ? db_all($pdo, "SELECT * FROM orders WHERE customer_id = ? AND status = 'Delivered' ORDER BY id DESC LIMIT 5", [$customer['id']]) : [];

$pageTitle = 'Order Tracking';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-geo-alt"></i> Order Tracking</h1>
    <a class="btn btn-primary" href="../orders/form.php<?= $customer ? '?customer_id=' . $customer['id'] : '' ?>"><i class="bi bi-plus-lg"></i> New Order</a>
</div>

<!-- Choose a customer -->
<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-12 col-lg-5">
            <input class="form-control form-control-sm mb-1" placeholder="Type to search customer..." data-filter-select="track_customer">
            <select class="form-select" name="customer_id" id="track_customer" onchange="this.form.submit()">
                <option value="">-- All customers --</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $customer && (int)$customer['id'] === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= e($c['full_name'] . ' - ' . $c['phone'] . ($c['active'] ? ' (' . $c['active'] . ' in shop)' : '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-3"><input class="form-control" name="q" value="<?= e($customer ? '' : $q) ?>" placeholder="Phone, name, order or shelf"></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="status"><option value="">All steps</option><?= options(array_slice($stepNames, 0, 5), $statusFilter) ?></select></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="speed"><option value="">All speeds</option><?= options(array_keys(service_speeds()), $speedFilter) ?></select></div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Show</button>
            <a class="btn btn-outline-secondary" href="index.php">Clear</a>
        </div>
    </div>
</form>

<!-- How many orders are at each step -->
<div class="row g-2 mb-3">
    <?php foreach ($counts as $status => $n): ?>
        <div class="col-4 col-md-2">
            <a class="card stat-card shadow-sm text-decoration-none h-100" href="?status=<?= $status ?>"><div class="card-body p-2 text-center">
                <i class="bi <?= $steps[$status] ?>"></i><div class="stat-value"><?= $n ?></div><div class="stat-label"><?= $status ?></div>
            </div></a>
        </div>
    <?php endforeach; ?>
    <div class="col-4 col-md-2">
        <div class="card stat-card red shadow-sm h-100"><div class="card-body p-2 text-center">
            <i class="bi bi-alarm"></i><div class="stat-value text-loss"><?= $overdue ?></div><div class="stat-label">Overdue</div>
        </div></div>
    </div>
</div>

<?php if ($customer): ?>
    <div class="card shadow-sm mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <div class="flex-grow-1">
            <h2 class="h5 mb-1"><a href="../customers/view.php?id=<?= $customer['id'] ?>"><?= e($customer['full_name']) ?></a> <?= tier_badge($customer['tier']) ?></h2>
            <div class="text-muted small"><?= e($customer['phone']) ?> &middot; <?= e($customer['customer_code']) ?></div>
        </div>
        <div class="text-center"><div class="stat-value"><?= count($activeOrders) ?></div><div class="stat-label">orders in the shop</div></div>
        <div class="text-center"><div class="stat-value"><?= money(array_sum(array_column($activeOrders, 'balance'))) ?></div><div class="stat-label">balance to pay</div></div>
    </div></div>
<?php endif; ?>

<?php foreach ($activeOrders as $o):
    $currentIndex = array_search($o['status'], $stepNames, true);
    $next = $stepNames[$currentIndex + 1] ?? null;
?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <div>
                    <a class="fw-bold" href="../orders/view.php?id=<?= $o['id'] ?>"><?= e($o['order_number']) ?></a>
                    <?= speed_badge($o['service_speed']) ?> <?= ready_label($o) ?>
                    <?php if (!$customer): ?><span class="ms-1"><?= e($o['full_name']) ?> <?= tier_badge($o['tier']) ?></span><?php endif; ?>
                    <div class="small text-muted"><?= e($o['items']) ?></div>
                </div>
                <div class="text-end">
                    <div>Shelf: <span class="shelf"><?= e($o['shelf_number']) ?: '-' ?></span></div>
                    <div class="small text-muted">Ready by <?= $o['ready_at'] ? show_datetime($o['ready_at']) : show_date($o['expected_date']) ?></div>
                </div>
            </div>

            <!-- Where the order is now -->
            <div class="track-steps mb-2">
                <?php foreach ($steps as $step => $icon):
                    $i = array_search($step, $stepNames, true);
                    $class = $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : '');
                ?>
                    <div class="step <?= $class ?>"><i class="bi <?= $icon ?>"></i><?= $step ?></div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if ($next): ?>
                    <form method="post" action="../orders/status.php">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $o['id'] ?>"><input type="hidden" name="status" value="<?= $next ?>">
                        <input type="hidden" name="return" value="tracking"><input type="hidden" name="customer_id" value="<?= $customer ? $customer['id'] : 0 ?>">
                        <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-arrow-right-circle"></i> Move to <?= $next ?></button>
                    </form>
                <?php endif; ?>
                <form method="post" action="../orders/status.php" class="d-flex gap-1">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $o['id'] ?>"><input type="hidden" name="status" value="<?= e($o['status']) ?>">
                    <input type="hidden" name="return" value="tracking"><input type="hidden" name="customer_id" value="<?= $customer ? $customer['id'] : 0 ?>">
                    <input class="form-control form-control-sm" name="shelf_number" value="<?= e($o['shelf_number']) ?>" placeholder="Shelf" style="width: 90px" maxlength="20">
                    <button class="btn btn-outline-secondary btn-sm" type="submit" title="Save shelf"><i class="bi bi-check-lg"></i></button>
                </form>
                <span class="ms-auto small">Balance: <b class="<?= $o['balance'] > 0 ? 'text-loss' : 'text-profit' ?>"><?= money($o['balance']) ?></b></span>
                <?php if ($o['balance'] > 0): ?><a class="btn btn-outline-success btn-sm" href="../payments/add.php?order_id=<?= $o['id'] ?>"><i class="bi bi-cash"></i> Pay</a><?php endif; ?>
                <a class="btn btn-outline-dark btn-sm" href="../receipt/print.php?id=<?= $o['id'] ?>" target="_blank"><i class="bi bi-printer"></i></a>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!$activeOrders): ?>
    <div class="alert alert-light border text-center py-4"><?= $customer ? 'This customer has no orders in the shop right now.' : 'No orders found.' ?></div>
<?php endif; ?>

<?php if ($delivered): ?>
    <div class="section-title">Recently delivered</div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Order</th><th>Date</th><th>Shelf</th><th class="money">Total</th><th class="money">Balance</th></tr></thead>
        <?php foreach ($delivered as $d): ?>
            <tr><td><a href="../orders/view.php?id=<?= $d['id'] ?>"><?= e($d['order_number']) ?></a></td><td><?= show_date($d['order_date']) ?></td>
                <td><?= e($d['shelf_number']) ?: '-' ?></td><td class="money"><?= money($d['total_amount']) ?></td><td class="money"><?= money($d['balance']) ?></td></tr>
        <?php endforeach; ?>
    </table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
