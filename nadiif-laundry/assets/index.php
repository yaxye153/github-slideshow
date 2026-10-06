<?php
// assets/index.php - COMPANY ASSETS (shelves, computers, machines, vehicles...)
require_once __DIR__ . '/../auth/auth_check.php';

$category = in_list((string)($_GET['category'] ?? ''), asset_categories(), '');
$condition = in_list((string)($_GET['condition'] ?? ''), asset_conditions(), '');
$showRetired = !empty($_GET['retired']);
$q = trim((string)($_GET['q'] ?? ''));

$where = [$showRetired ? '1 = 1' : 'a.is_active = 1'];
$params = [];
if ($category !== '') { $where[] = 'a.category = ?'; $params[] = $category; }
if ($condition !== '') { $where[] = 'a.condition_status = ?'; $params[] = $condition; }
if ($q !== '') { $where[] = '(a.name LIKE ? OR a.asset_code LIKE ? OR a.serial_number LIKE ? OR a.location LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }

// For shelves: how many orders are on the shelf right now
$assets = db_all($pdo, "SELECT a.*,
        (SELECT COUNT(*) FROM orders o WHERE a.category = 'Shelf' AND o.status NOT IN ('Delivered', 'Cancelled')
            AND o.shelf_number <> '' AND o.shelf_number = COALESCE(NULLIF(a.asset_code, ''), a.name)) AS orders_on_shelf
    FROM assets a WHERE " . implode(' AND ', $where) . ' ORDER BY a.category, a.asset_code, a.name', $params);

$summary = db_all($pdo, "SELECT category, COUNT(*) AS n, SUM(purchase_cost) AS value, SUM(condition_status <> 'Good') AS problems
    FROM assets WHERE is_active = 1 GROUP BY category ORDER BY category");
$totalValue = array_sum(array_column($summary, 'value'));

$pageTitle = 'Company Assets';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-hdd-stack"></i> Company Assets</h1>
    <a class="btn btn-primary" href="form.php"><i class="bi bi-plus-lg"></i> Add Asset</a>
</div>
<p class="text-muted small">Everything the business owns: shelves, computers, machines, vehicles, furniture. Shelves you add here appear as choices for the shelf number of an order.</p>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card stat-card shadow-sm h-100"><div class="card-body"><div class="stat-label">Total value (purchase cost)</div><div class="stat-value"><?= money($totalValue) ?></div></div></div></div>
    <?php foreach ($summary as $s): ?>
        <div class="col-6 col-md-3 col-xl-2"><a class="card stat-card shadow-sm h-100 text-decoration-none<?= $s['problems'] ? ' orange' : ' green' ?>" href="?category=<?= urlencode($s['category']) ?>"><div class="card-body">
            <div class="stat-label"><?= e($s['category']) ?></div><div class="stat-value"><?= (int)$s['n'] ?></div>
            <?php if ($s['problems']): ?><div class="small text-danger"><?= (int)$s['problems'] ?> need repair</div><?php endif; ?>
        </div></a></div>
    <?php endforeach; ?>
</div>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-12 col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Name, code, serial number or location"></div>
        <div class="col-6 col-md-2"><select class="form-select" name="category"><option value="">All types</option><?= options(asset_categories(), $category) ?></select></div>
        <div class="col-6 col-md-2"><select class="form-select" name="condition"><option value="">Any condition</option><?= options(asset_conditions(), $condition) ?></select></div>
        <div class="col-6 col-md-2 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="retired" value="1" id="ret" <?= $showRetired ? 'checked' : '' ?>><label class="form-check-label" for="ret">Show retired</label></div></div>
        <div class="col-6 col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Location</th><th>Serial No.</th><th>Bought</th><th class="money">Cost</th><th>Condition</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($assets as $a): ?>
                <tr class="<?= $a['is_active'] ? '' : 'text-muted' ?>">
                    <td><b><?= e($a['asset_code']) ?></b></td>
                    <td><?= e($a['name']) ?><?= $a['category'] === 'Shelf' ? '<br><a class="small" href="../tracking/index.php?q=' . urlencode($a['asset_code'] ?: $a['name']) . '">' . (int)$a['orders_on_shelf'] . ' order(s) on this shelf</a>' : '' ?></td>
                    <td><?= e($a['category']) ?></td>
                    <td><?= e($a['location']) ?></td>
                    <td class="small"><?= e($a['serial_number']) ?></td>
                    <td><?= show_date($a['purchase_date']) ?></td>
                    <td class="money"><?= money($a['purchase_cost']) ?></td>
                    <td><span class="badge bg-<?= $a['condition_status'] === 'Good' ? 'success' : ($a['condition_status'] === 'Broken' ? 'danger' : 'warning text-dark') ?>"><?= e($a['condition_status']) ?></span>
                        <?= $a['is_active'] ? '' : '<span class="badge bg-secondary">Retired</span>' ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $a['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$assets): ?><tr><td colspan="9" class="text-center text-muted py-4">No assets yet. Click "Add Asset".</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
