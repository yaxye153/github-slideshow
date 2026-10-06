<?php
// orders/index.php - list and search laundry orders
require_once __DIR__ . '/../auth/auth_check.php';

// Search by order number, customer name, phone, date and status
$q = trim((string)($_GET['q'] ?? ''));
$status = in_list((string)($_GET['status'] ?? ''), order_statuses(), '');
$payStatus = in_list((string)($_GET['pay'] ?? ''), ['Paid', 'Partial', 'Unpaid'], '');
$from = get_date('from', '');
$to = get_date('to', '');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(o.order_number LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ? OR o.delivery_phone LIKE ? OR o.shelf_number = ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", $q);
}
if ($status !== '') { $where[] = 'o.status = ?'; $params[] = $status; }
if ($payStatus !== '') { $where[] = 'o.payment_status = ?'; $params[] = $payStatus; }
if ($from !== '') { $where[] = 'o.order_date >= ?'; $params[] = $from; }
if ($to !== '') { $where[] = 'o.order_date <= ?'; $params[] = $to; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$p = paginate((int)db_value($pdo, "SELECT COUNT(*) FROM orders o JOIN customers c ON c.id = o.customer_id $whereSql", $params));
$orders = db_all($pdo, "SELECT o.*, c.full_name, c.phone FROM orders o JOIN customers c ON c.id = o.customer_id
                        $whereSql ORDER BY o.order_date DESC, o.id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);

$pageTitle = 'Laundry Orders';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-basket"></i> Laundry Orders</h1>
    <a class="btn btn-primary" href="form.php"><i class="bi bi-plus-lg"></i> New Order</a>
</div>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-12 col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Order number, customer, phone or shelf"></div>
        <div class="col-6 col-md-2"><select class="form-select" name="status"><option value="">All statuses</option><?= options(order_statuses(), $status) ?></select></div>
        <div class="col-6 col-md-2"><select class="form-select" name="pay"><option value="">All payments</option><?= options(['Paid', 'Partial', 'Unpaid'], $payStatus) ?></select></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>" title="From date"></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="to" value="<?= e($to) ?>" title="To date"></div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
            <a class="btn btn-outline-secondary" href="index.php">Clear</a>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Ready By</th><th>Shelf</th><th>Status</th><th>Payment</th><th class="money">Total</th><th class="money">Balance</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="view.php?id=<?= $o['id'] ?>"><?= e($o['order_number']) ?></a>
                        <?= $o['pickup_type'] === 'Delivery' ? '<i class="bi bi-truck text-muted" title="Delivery"></i>' : '' ?>
                        <?= $o['service_speed'] !== 'Normal' ? speed_badge($o['service_speed']) : '' ?></td>
                    <td><?= e($o['full_name']) ?><br><small class="text-muted"><?= e($o['phone']) ?></small></td>
                    <td><?= show_date($o['order_date']) ?></td>
                    <td><?= $o['ready_at'] ? show_datetime($o['ready_at']) : show_date($o['expected_date']) ?> <?= ready_label($o) ?></td>
                    <td><?= e($o['shelf_number']) ?: '-' ?></td>
                    <td><?= badge($o['status']) ?></td>
                    <td><?= badge($o['payment_status']) ?></td>
                    <td class="money"><?= money($o['total_amount']) ?></td>
                    <td class="money"><?= money($o['balance']) ?></td>
                    <td class="actions text-end">
                        <a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= $o['id'] ?>" title="View"><i class="bi bi-eye"></i></a>
                        <?php if ($o['balance'] > 0 && $o['status'] !== 'Cancelled'): ?>
                            <a class="btn btn-sm btn-outline-success" href="../payments/add.php?order_id=<?= $o['id'] ?>" title="Add payment"><i class="bi bi-cash"></i></a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="../receipt/print.php?id=<?= $o['id'] ?>" target="_blank" title="Print receipt"><i class="bi bi-printer"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?><tr><td colspan="10" class="text-center text-muted py-4">No orders found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pagination_links($p) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
