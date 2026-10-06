<?php
// income/index.php - all business income (order payments, delivery income and other income)
require_once __DIR__ . '/../auth/auth_check.php';

[$defaultFrom, $defaultTo] = period_range('month');
$from = get_date('from', $defaultFrom);
$to = get_date('to', $defaultTo);
$type = in_list((string)($_GET['type'] ?? ''), all_income_types(), '');
$q = trim((string)($_GET['q'] ?? ''));

$where = ['income_date BETWEEN ? AND ?'];
$params = [$from, $to];
if ($type !== '') { $where[] = 'income_type = ?'; $params[] = $type; }
if ($q !== '') {
    $where[] = '(description LIKE ? OR reference LIKE ? OR notes LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$filteredTotal = (float)db_value($pdo, "SELECT COALESCE(SUM(amount), 0) FROM income $whereSql", $params);
$p = paginate((int)db_value($pdo, "SELECT COUNT(*) FROM income $whereSql", $params));
$rows = db_all($pdo, "SELECT * FROM income $whereSql ORDER BY income_date DESC, id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);

$pageTitle = 'Income';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-graph-up-arrow"></i> Income</h1>
    <a class="btn btn-success" href="form.php"><i class="bi bi-plus-lg"></i> Add Other Income</a>
</div>
<p class="text-muted small">Order payments and delivery income are added here automatically. Use "Add Other Income" only for money that is not an order payment.</p>

<?= summary_cards(period_totals($pdo, 'income'), 'green') ?>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-12 col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search description or reference"></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-6 col-md-2"><select class="form-select" name="type"><option value="">All types</option><?= options(all_income_types(), $type) ?></select></div>
        <div class="col-6 col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-header bg-white">Total for selected period: <strong><?= money($filteredTotal) ?></strong></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Date</th><th>Type</th><th>Description</th><th>Method</th><th>Reference</th><th>Source</th><th class="money">Amount</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= show_date($r['income_date']) ?></td>
                    <td><?= e($r['income_type']) ?></td>
                    <td><?= e($r['description']) ?></td>
                    <td><?= e($r['payment_method']) ?></td>
                    <td><?= e($r['reference']) ?></td>
                    <td><small class="text-muted"><?= e(source_label($r['source'])) ?></small></td>
                    <td class="money"><?= money($r['amount']) ?></td>
                    <td class="actions text-end">
                        <?php if ($r['source'] === 'manual'): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php if (is_admin()): /* only the admin can delete */ ?><form method="post" action="delete.php" class="d-inline" data-confirm="Delete this income record?">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                            </form><?php endif; ?>
                        <?php else: ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(source_link($r['source'])) ?>" title="Edit it in its own page"><i class="bi bi-link-45deg"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No income in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pagination_links($p) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
