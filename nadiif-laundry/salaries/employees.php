<?php
// salaries/employees.php - list of employees
require_once __DIR__ . '/../auth/auth_check.php';

$q = trim((string)($_GET['q'] ?? ''));
$params = [];
$where = '';
if ($q !== '') {
    $where = 'WHERE e.full_name LIKE ? OR e.phone LIKE ? OR e.position LIKE ?';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$employees = db_all($pdo, "SELECT e.*, (SELECT COALESCE(SUM(amount), 0) FROM salary_payments s WHERE s.employee_id = e.id) AS total_paid
                           FROM employees e $where ORDER BY e.status, e.full_name", $params);

$pageTitle = 'Employees';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-people"></i> Employees</h1>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Salary Payments</a>
        <a class="btn btn-primary" href="employee_form.php"><i class="bi bi-person-plus"></i> Add Employee</a>
    </div>
</div>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="input-group">
        <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search by name, phone or position">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Name</th><th>Phone</th><th>Position</th><th class="money">Salary</th><th>Type</th><th>Start Date</th><th>Status</th><th class="money">Total Paid</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr>
                    <td><?= (int)$emp['id'] ?></td>
                    <td><?= e($emp['full_name']) ?></td>
                    <td><?= e($emp['phone']) ?></td>
                    <td><?= e($emp['position']) ?></td>
                    <td class="money"><?= money($emp['salary']) ?></td>
                    <td><?= e($emp['salary_type']) ?></td>
                    <td><?= show_date($emp['start_date']) ?></td>
                    <td><?= badge($emp['status']) ?></td>
                    <td class="money"><?= money($emp['total_paid']) ?></td>
                    <td class="actions text-end">
                        <a class="btn btn-sm btn-success" href="pay.php?employee_id=<?= $emp['id'] ?>" title="Pay salary"><i class="bi bi-cash"></i> Pay</a>
                        <a class="btn btn-sm btn-outline-secondary" href="employee_form.php?id=<?= $emp['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$employees): ?><tr><td colspan="10" class="text-center text-muted py-4">No employees yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
