<?php
// tracking/index.php - ORDER TRACKING
// Two kinds of tracking for every order:
//   1. WORK:     Received -> Washing -> Drying -> Ironing -> Ready
//   2. HANDOVER: Ready -> Out for Delivery -> Delivered  (or: Picked up by the customer)
// Choose a customer to see how many orders they have in the shop and the shelf of each one.
require_once __DIR__ . '/../auth/auth_check.php';
require_once APP_ROOT . '/includes/tracker.php';

$customerId = (int)($_GET['customer_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$active = array_values(array_diff(order_statuses(), ['Delivered', 'Cancelled']));
$statusFilter = in_list((string)($_GET['status'] ?? ''), $active, '');
$speedFilter = in_list((string)($_GET['speed'] ?? ''), array_keys(service_speeds()), '');
$view = in_list((string)($_GET['view'] ?? ''), ['work', 'handover'], '');

// Find the customer by phone, name or code
if (!$customerId && $q !== '') {
    $found = db_all($pdo, 'SELECT id FROM customers WHERE phone LIKE ? OR alt_phone LIKE ? OR full_name LIKE ? OR customer_code = ? LIMIT 2',
        ["%$q%", "%$q%", "%$q%", $q]);
    if (count($found) === 1) {
        $customerId = (int)$found[0]['id'];
    }
}

$customer = $customerId ? db_row($pdo, 'SELECT * FROM customers WHERE id = ?', [$customerId]) : null;
$customers = db_all($pdo, "SELECT c.id, c.full_name, c.phone,
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
if ($view === 'work') { $where[] = "o.status IN ('Received', 'Washing', 'Drying', 'Ironing')"; }
if ($view === 'handover') { $where[] = "o.status IN ('Ready', 'Out for Delivery')"; }

// Gold first, then Silver, then the order that must be ready soonest
$activeOrders = db_all($pdo, 'SELECT ' . tracker_select() . "
    FROM orders o JOIN customers c ON c.id = o.customer_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY FIELD(o.service_speed, 'Gold', 'Silver', 'Normal'), o.ready_at IS NULL, o.ready_at, o.id", $params);

// Counts per step (whole shop)
$counts = array_fill_keys($active, 0);
foreach (db_all($pdo, "SELECT status, COUNT(*) AS n FROM orders WHERE status NOT IN ('Delivered', 'Cancelled') GROUP BY status") as $r) {
    $counts[$r['status']] = (int)$r['n'];
}
$overdue = (int)db_value($pdo, "SELECT COUNT(*) FROM orders WHERE status IN ('Received', 'Washing', 'Drying', 'Ironing') AND ready_at < ?", [date('Y-m-d H:i:s')]);
$icons = work_steps() + ['Out for Delivery' => 'bi-truck'];

// Last handed-over orders of this customer (with who received them)
$delivered = $customer ? db_all($pdo, "SELECT * FROM orders WHERE customer_id = ? AND status = 'Delivered' ORDER BY handed_over_at DESC, id DESC LIMIT 5", [$customer['id']]) : [];
$shelves = db_query($pdo, "SELECT COALESCE(NULLIF(asset_code, ''), name) FROM assets WHERE category = 'Shelf' AND is_active = 1 ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Order Tracking';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-geo-alt"></i> Order Tracking</h1>
    <a class="btn btn-primary" href="../orders/form.php<?= $customer ? '?customer_id=' . $customer['id'] : '' ?>"><i class="bi bi-plus-lg"></i> New Order</a>
</div>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= $view === '' ? ' active' : '' ?>" href="index.php<?= $customer ? '?customer_id=' . $customer['id'] : '' ?>">All in shop</a></li>
    <li class="nav-item"><a class="nav-link<?= $view === 'work' ? ' active' : '' ?>" href="?view=work"><i class="bi bi-gear"></i> 1. Work (washing, drying, ironing)</a></li>
    <li class="nav-item"><a class="nav-link<?= $view === 'handover' ? ' active' : '' ?>" href="?view=handover"><i class="bi bi-box-arrow-right"></i> 2. Handover (ready, delivery)</a></li>
</ul>

<!-- Choose a customer -->
<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <?php if ($view): ?><input type="hidden" name="view" value="<?= e($view) ?>"><?php endif; ?>
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
        <div class="col-6 col-lg-2"><select class="form-select" name="status"><option value="">All steps</option><?= options($active, $statusFilter) ?></select></div>
        <div class="col-6 col-lg-2"><select class="form-select" name="speed"><option value="">All packages</option><?= options(array_keys(service_speeds()), $speedFilter) ?></select></div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Show</button>
            <a class="btn btn-outline-secondary" href="index.php">Clear</a>
        </div>
    </div>
</form>

<!-- How many orders are at each step -->
<div class="row g-2 mb-3">
    <?php foreach ($counts as $status => $n): ?>
        <div class="col-4 col-md">
            <a class="card stat-card shadow-sm text-decoration-none h-100" href="?status=<?= urlencode($status) ?>"><div class="card-body p-2 text-center">
                <i class="bi <?= $icons[$status] ?? 'bi-circle' ?>"></i><div class="stat-value"><?= $n ?></div><div class="stat-label"><?= e($status) ?></div>
            </div></a>
        </div>
    <?php endforeach; ?>
    <div class="col-4 col-md">
        <div class="card stat-card red shadow-sm h-100"><div class="card-body p-2 text-center">
            <i class="bi bi-alarm"></i><div class="stat-value text-loss"><?= $overdue ?></div><div class="stat-label">Late</div>
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
        <div class="text-center"><div class="stat-value"><?= e(implode(', ', array_unique(array_filter(array_column($activeOrders, 'shelf_number')))) ?: '-') ?></div><div class="stat-label">shelves</div></div>
        <div class="text-center"><div class="stat-value"><?= money(array_sum(array_column($activeOrders, 'balance'))) ?></div><div class="stat-label">balance to pay</div></div>
    </div></div>
<?php endif; ?>

<datalist id="shelf-list"><?= options($shelves) ?></datalist>
<?php foreach ($activeOrders as $o) { echo order_tracker_card($o, 'tracking', $customer ? (int)$customer['id'] : 0, !$customer); } ?>

<?php if (!$activeOrders): ?>
    <div class="alert alert-light border text-center py-4"><?= $customer ? 'This customer has no orders in the shop right now.' : 'No orders found.' ?></div>
<?php endif; ?>

<?php if ($delivered): ?>
    <div class="section-title">Recently handed over</div>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Order</th><th>Handed over</th><th>How</th><th>Received by</th><th class="money">Total</th><th class="money">Balance</th></tr></thead>
        <?php foreach ($delivered as $d): ?>
            <tr><td><a href="../orders/view.php?id=<?= $d['id'] ?>"><?= e($d['order_number']) ?></a></td>
                <td><?= show_datetime($d['handed_over_at']) ?></td><td><?= $d['pickup_type'] === 'Delivery' ? 'Delivered' : 'Picked up' ?></td>
                <td><?= e($d['received_by']) ?: '-' ?></td><td class="money"><?= money($d['total_amount']) ?></td><td class="money"><?= money($d['balance']) ?></td></tr>
        <?php endforeach; ?>
    </table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
