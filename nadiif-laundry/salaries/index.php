<?php
// salaries/index.php - salary payments list and totals
require_once __DIR__ . '/../auth/auth_check.php';

[$defaultFrom, $defaultTo] = period_range('month');
$from = get_date('from', $defaultFrom);
$to = get_date('to', $defaultTo);
$employeeId = (int)($_GET['employee_id'] ?? 0);

$where = ['s.payment_date BETWEEN ? AND ?'];
$params = [$from, $to];
if ($employeeId) { $where[] = 's.employee_id = ?'; $params[] = $employeeId; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$payments = db_all($pdo, "SELECT s.*, e.full_name, e.position FROM salary_payments s JOIN employees e ON e.id = s.employee_id
                          $whereSql ORDER BY s.payment_date DESC, s.id DESC", $params);
$periodTotal = array_sum(array_column($payments, 'amount'));

[$mFrom, $mTo] = period_range('month');
[$yFrom, $yTo] = period_range('year');
$monthTotal = (float)db_value($pdo, 'SELECT COALESCE(SUM(amount), 0) FROM salary_payments WHERE payment_date BETWEEN ? AND ?', [$mFrom, $mTo]);
$yearTotal = (float)db_value($pdo, 'SELECT COALESCE(SUM(amount), 0) FROM salary_payments WHERE payment_date BETWEEN ? AND ?', [$yFrom, $yTo]);
$activeCount = (int)db_value($pdo, "SELECT COUNT(*) FROM employees WHERE status = 'Active'");
$employees = db_all($pdo, 'SELECT id, full_name FROM employees ORDER BY full_name');

$pageTitle = 'Salaries';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-person-badge"></i> Salaries</h1>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="employees.php"><i class="bi bi-people"></i> Employees</a>
        <a class="btn btn-success" href="pay.php"><i class="bi bi-cash"></i> Pay Salary</a>
    </div>
</div>
<p class="text-muted small">Every salary payment is added to Expenses automatically (category "Salary"). Do not add it again in Expenses.</p>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="card stat-card shadow-sm"><div class="card-body"><div class="stat-label">Active Employees</div><div class="stat-value"><?= $activeCount ?></div></div></div></div>
    <div class="col-6 col-md-4"><div class="card stat-card red shadow-sm"><div class="card-body"><div class="stat-label">Salaries This Month</div><div class="stat-value"><?= money($monthTotal) ?></div></div></div></div>
    <div class="col-12 col-md-4"><div class="card stat-card red shadow-sm"><div class="card-body"><div class="stat-label">Salaries This Year</div><div class="stat-value"><?= money($yearTotal) ?></div></div></div></div>
</div>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-12 col-md-4"><select class="form-select" name="employee_id"><option value="">All employees</option>
            <?php foreach ($employees as $emp): ?><option value="<?= $emp['id'] ?>" <?= $employeeId === (int)$emp['id'] ? 'selected' : '' ?>><?= e($emp['full_name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-6 col-md-3"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-3"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-header bg-white">Total for selected period: <strong><?= money($periodTotal) ?></strong></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Payment Date</th><th>Employee</th><th>Period</th><th>Method</th><th>Notes</th><th class="money">Amount</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payments as $s): ?>
                <tr>
                    <td><?= show_date($s['payment_date']) ?></td>
                    <td><?= e($s['full_name']) ?><br><small class="text-muted"><?= e($s['position']) ?></small></td>
                    <td><?= e($s['salary_period']) ?></td>
                    <td><?= e($s['payment_method']) ?></td>
                    <td><?= e($s['notes']) ?></td>
                    <td class="money"><?= money($s['amount']) ?></td>
                    <td class="actions text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="pay.php?id=<?= $s['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="post" action="delete.php" class="d-inline" data-confirm="Delete this salary payment? It will also be removed from expenses.">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="7" class="text-center text-muted py-4">No salary payments in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
