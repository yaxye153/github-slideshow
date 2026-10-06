<?php
// expenses/index.php - all business expenses (manual, salaries, delivery, daily and monthly running costs)
require_once __DIR__ . '/../auth/auth_check.php';

[$defaultFrom, $defaultTo] = period_range('month');
$from = get_date('from', $defaultFrom);
$to = get_date('to', $defaultTo);
$category = in_list((string)($_GET['category'] ?? ''), expense_categories(), '');
$q = trim((string)($_GET['q'] ?? ''));

$where = ['expense_date BETWEEN ? AND ?'];
$params = [$from, $to];
if ($category !== '') { $where[] = 'category = ?'; $params[] = $category; }
if ($q !== '') {
    $where[] = '(description LIKE ? OR reference LIKE ? OR notes LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$filteredTotal = (float)db_value($pdo, "SELECT COALESCE(SUM(amount), 0) FROM expenses $whereSql", $params);
$p = paginate((int)db_value($pdo, "SELECT COUNT(*) FROM expenses $whereSql", $params));
$rows = db_all($pdo, "SELECT * FROM expenses $whereSql ORDER BY expense_date DESC, id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);

$pageTitle = 'Expenses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-graph-down-arrow"></i> Expenses</h1>
    <a class="btn btn-danger" href="form.php"><i class="bi bi-plus-lg"></i> Add Expense</a>
</div>
<p class="text-muted small">Salaries, delivery costs and running costs are added here automatically from their own pages.</p>

<?= summary_cards(period_totals($pdo, 'expenses'), 'red') ?>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-12 col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search description or reference"></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-6 col-md-2"><select class="form-select" name="category"><option value="">All categories</option><?= options(expense_categories(), $category) ?></select></div>
        <div class="col-6 col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-header bg-white">Total for selected period: <strong><?= money($filteredTotal) ?></strong></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Date</th><th>Category</th><th>Description</th><th>Method</th><th>Reference</th><th>Source</th><th class="money">Amount</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= show_date($r['expense_date']) ?></td>
                    <td><?= e($r['category']) ?></td>
                    <td><?= e($r['description']) ?></td>
                    <td><?= e($r['payment_method']) ?></td>
                    <td><?= e($r['reference']) ?></td>
                    <td><small class="text-muted"><?= e(source_label($r['source'])) ?></small></td>
                    <td class="money"><?= money($r['amount']) ?></td>
                    <td class="actions text-end">
                        <?php if ($r['source'] === 'manual'): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php if (is_admin()): /* only the admin can delete */ ?><form method="post" action="delete.php" class="d-inline" data-confirm="Delete this expense?">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                            </form><?php endif; ?>
                        <?php else: ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(source_link($r['source'])) ?>" title="Edit it in its own page"><i class="bi bi-link-45deg"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No expenses in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pagination_links($p) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
