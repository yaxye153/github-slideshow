<?php
// salaries/employee_form.php - add or edit an employee
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$emp = ['full_name' => '', 'phone' => '', 'position' => '', 'salary' => '', 'salary_type' => 'Monthly',
        'start_date' => date('Y-m-d'), 'status' => 'Active', 'notes' => ''];
if ($id) {
    $emp = db_row($pdo, 'SELECT * FROM employees WHERE id = ?', [$id]);
    if (!$emp) {
        flash('danger', 'Employee not found.');
        redirect('salaries/employees.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp = [
        'full_name' => post_text('full_name', 100),
        'phone' => post_text('phone', 30),
        'position' => post_text('position', 50),
        'salary' => post_money('salary'),
        'salary_type' => in_list(post_text('salary_type', 10), salary_types(), 'Monthly'),
        'start_date' => post_text('start_date', 10),
        'status' => in_list(post_text('status', 10), ['Active', 'Inactive'], 'Active'),
        'notes' => post_text('notes', 2000),
    ];
    if ($emp['full_name'] === '') { $errors[] = 'Please enter the employee name.'; }
    if ($emp['salary'] === null || $emp['salary'] < 0) { $errors[] = 'Please enter a valid salary.'; }
    if ($emp['start_date'] !== '' && !valid_date($emp['start_date'])) { $errors[] = 'Please enter a valid start date.'; }

    if (!$errors) {
        require_csrf('salaries/employees.php');
        $values = [$emp['full_name'], $emp['phone'], $emp['position'], $emp['salary'], $emp['salary_type'],
                   $emp['start_date'] !== '' ? $emp['start_date'] : null, $emp['status'], $emp['notes']];
        if ($id) {
            db_query($pdo, 'UPDATE employees SET full_name = ?, phone = ?, position = ?, salary = ?, salary_type = ?, start_date = ?, status = ?, notes = ? WHERE id = ?',
                array_merge($values, [$id]));
            flash('success', 'Employee updated successfully.');
        } else {
            db_query($pdo, 'INSERT INTO employees (full_name, phone, position, salary, salary_type, start_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $values);
            flash('success', 'Employee added successfully.');
        }
        redirect('salaries/employees.php');
    }
}

$pageTitle = $id ? 'Edit Employee' : 'Add Employee';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><?= $id ? 'Edit Employee' : 'Add Employee' ?></h1>
    <a class="btn btn-outline-secondary" href="employees.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 760px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Full Name *</label><input class="form-control" name="full_name" value="<?= e($emp['full_name']) ?>" required maxlength="100"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" type="tel" name="phone" value="<?= e($emp['phone']) ?>" maxlength="30"></div>
        <div class="col-md-6"><label class="form-label">Position</label><input class="form-control" name="position" value="<?= e($emp['position']) ?>" placeholder="e.g. Washer, Ironer, Driver" maxlength="50"></div>
        <div class="col-md-6"><label class="form-label">Start Date</label><input class="form-control" type="date" name="start_date" value="<?= e($emp['start_date']) ?>"></div>
        <div class="col-6 col-md-4"><label class="form-label">Salary *</label><input class="form-control" type="number" step="0.01" min="0" name="salary" value="<?= e($emp['salary'] ?? '') ?>" required></div>
        <div class="col-6 col-md-4"><label class="form-label">Salary Type</label><select class="form-select" name="salary_type"><?= options(salary_types(), $emp['salary_type']) ?></select></div>
        <div class="col-12 col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><?= options(['Active', 'Inactive'], $emp['status']) ?></select></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($emp['notes']) ?></textarea></div>
        <div class="col-12"><button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Employee</button></div>
    </div>
</form>
<?php if ($id): ?>
    <?php if (is_admin()): /* only the admin can delete */ ?><form method="post" action="employee_delete.php" class="mt-3" data-confirm="Delete this employee?">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete Employee</button>
        <small class="text-muted ms-2">Employees with salary payments can not be deleted &mdash; set them to Inactive.</small>
    </form><?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
