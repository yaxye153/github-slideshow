<?php
// customers/index.php - list and search customers
require_once __DIR__ . '/../auth/auth_check.php';

// Search by name, phone, customer code or customer ID
$search = trim((string)($_GET['q'] ?? ''));
$tier = in_list((string)($_GET['tier'] ?? ''), array_keys(customer_tiers()), '');
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(c.full_name LIKE ? OR c.phone LIKE ? OR c.alt_phone LIKE ? OR c.customer_code LIKE ? OR c.id = ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, ctype_digit($search) ? (int)$search : 0);
}
if ($tier !== '') {
    $conditions[] = 'c.tier = ?';
    $params[] = $tier;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$p = paginate((int)db_value($pdo, "SELECT COUNT(*) FROM customers c $where", $params));
$customers = db_all($pdo, "SELECT c.*,
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS order_count,
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.status NOT IN ('Delivered', 'Cancelled')) AS active_count,
        (SELECT COALESCE(SUM(o.balance), 0) FROM orders o WHERE o.customer_id = c.id AND o.status <> 'Cancelled') AS balance
    FROM customers c $where ORDER BY c.id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);

$pageTitle = 'Customers';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-people"></i> Customers</h1>
    <a class="btn btn-primary" href="form.php"><i class="bi bi-person-plus"></i> Add Customer</a>
</div>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="input-group">
        <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by name, phone or customer ID">
        <select class="form-select" name="tier" style="max-width: 140px"><option value="">All levels</option><?= options(array_keys(customer_tiers()), $tier) ?></select>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
        <?php if ($search !== '' || $tier !== ''): ?><a class="btn btn-outline-secondary" href="index.php">Clear</a><?php endif; ?>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Level</th><th>Phone</th><th>Address</th><th>Registered</th><th class="text-center">Orders</th><th class="money">Balance</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= e($c['customer_code']) ?></td>
                    <td><a href="view.php?id=<?= $c['id'] ?>"><?= e($c['full_name']) ?></a></td>
                    <td><?= tier_badge($c['tier']) ?></td>
                    <td><?= e($c['phone']) ?><?= $c['alt_phone'] ? '<br><small class="text-muted">' . e($c['alt_phone']) . '</small>' : '' ?></td>
                    <td><?= e($c['address']) ?></td>
                    <td><?= show_date($c['registration_date']) ?></td>
                    <td class="text-center"><?= (int)$c['order_count'] ?><?= $c['active_count'] ? '<br><a class="small" href="../tracking/index.php?customer_id=' . $c['id'] . '">' . (int)$c['active_count'] . ' active</a>' : '' ?></td>
                    <td class="money"><?= money($c['balance']) ?></td>
                    <td class="actions text-end">
                        <a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= $c['id'] ?>" title="View"><i class="bi bi-eye"></i></a>
                        <a class="btn btn-sm btn-outline-info" href="../tracking/index.php?customer_id=<?= $c['id'] ?>" title="Track orders"><i class="bi bi-geo-alt"></i></a>
                        <a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $c['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a class="btn btn-sm btn-outline-success" href="../orders/form.php?customer_id=<?= $c['id'] ?>" title="New order"><i class="bi bi-basket"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$customers): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No customers found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pagination_links($p) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
