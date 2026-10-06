<?php
// salaries/pay.php - record (or edit) a salary payment.
// Each salary payment is copied into Expenses automatically, only once.
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$employees = db_all($pdo, "SELECT id, full_name, salary, salary_type, status FROM employees ORDER BY status, full_name");

$pay = ['employee_id' => (int)($_GET['employee_id'] ?? 0), 'salary_period' => date('F Y'), 'amount' => '',
        'payment_date' => date('Y-m-d'), 'payment_method' => 'Cash', 'notes' => ''];
if ($id) {
    $pay = db_row($pdo, 'SELECT * FROM salary_payments WHERE id = ?', [$id]);
    if (!$pay) {
        flash('danger', 'Salary payment not found.');
        redirect('salaries/index.php');
    }
} elseif ($pay['employee_id']) {
    // Suggest the employee's normal salary
    foreach ($employees as $emp) {
        if ((int)$emp['id'] === $pay['employee_id']) {
            $pay['amount'] = $emp['salary'];
        }
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pay = [
        'employee_id' => (int)($_POST['employee_id'] ?? 0),
        'salary_period' => post_text('salary_period', 50),
        'amount' => post_money('amount'),
        'payment_date' => post_text('payment_date', 10),
        'payment_method' => in_list(post_text('payment_method', 20), payment_methods(), 'Cash'),
        'notes' => post_text('notes', 2000),
    ];
    $employee = db_row($pdo, 'SELECT * FROM employees WHERE id = ?', [$pay['employee_id']]);
    if (!$employee) { $errors[] = 'Please choose an employee.'; }
    if ($pay['salary_period'] === '') { $errors[] = 'Please enter the salary period (e.g. October 2026).'; }
    if ($pay['amount'] === null || $pay['amount'] <= 0) { $errors[] = 'Please enter an amount greater than 0.'; }
    if (!valid_date($pay['payment_date'])) { $errors[] = 'Please enter a valid payment date.'; }

    // Warn about paying the same person for the same period twice
    if (!$errors && empty($_POST['confirm_duplicate'])) {
        $same = (int)db_value($pdo, 'SELECT COUNT(*) FROM salary_payments WHERE employee_id = ? AND salary_period = ? AND id <> ?',
            [$pay['employee_id'], $pay['salary_period'], $id]);
        if ($same > 0) {
            $errors[] = $employee['full_name'] . ' already has a salary payment for "' . $pay['salary_period']
                      . '". If this is an extra payment, tick "This is an extra payment" and save again.';
            $askDuplicate = true;
        }
    }

    if (!$errors) {
        require_csrf('salaries/index.php');
        $pdo->beginTransaction();
        try {
            if ($id) {
                db_query($pdo, 'UPDATE salary_payments SET employee_id = ?, salary_period = ?, amount = ?, payment_date = ?, payment_method = ?, notes = ? WHERE id = ?',
                    [$pay['employee_id'], $pay['salary_period'], $pay['amount'], $pay['payment_date'], $pay['payment_method'], $pay['notes'], $id]);
            } else {
                db_query($pdo, 'INSERT INTO salary_payments (employee_id, salary_period, amount, payment_date, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)',
                    [$pay['employee_id'], $pay['salary_period'], $pay['amount'], $pay['payment_date'], $pay['payment_method'], $pay['notes']]);
                $id = (int)$pdo->lastInsertId();
            }
            // Copy to expenses (category Salary). Updating replaces the same expense row.
            ledger_expense_save($pdo, 'salary', $id, $pay['payment_date'], 'Salary',
                'Salary: ' . $employee['full_name'] . ' (' . $pay['salary_period'] . ')', $pay['amount'], $pay['payment_method'], '', $pay['notes']);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        flash('success', 'Salary payment recorded successfully. It was added to expenses.');
        redirect('salaries/index.php');
    }
}

$pageTitle = $id ? 'Edit Salary Payment' : 'Pay Salary';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-cash"></i> <?= $id ? 'Edit Salary Payment' : 'Pay Salary' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<?php if (!$employees): ?><div class="alert alert-info">Add an employee first: <a href="employee_form.php">Add Employee</a></div><?php endif; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 760px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Employee *</label>
            <select class="form-select" name="employee_id" required>
                <option value="">-- Choose employee --</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= (int)$pay['employee_id'] === (int)$emp['id'] ? 'selected' : '' ?>>
                        <?= e($emp['full_name'] . ' - ' . money($emp['salary']) . ' ' . $emp['salary_type'] . ($emp['status'] === 'Inactive' ? ' (Inactive)' : '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">Salary Period *</label><input class="form-control" name="salary_period" value="<?= e($pay['salary_period']) ?>" placeholder="e.g. October 2026 or Week 41" required maxlength="50"></div>
        <div class="col-6"><label class="form-label">Amount *</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" value="<?= e($pay['amount'] ?? '') ?>" required></div>
        <div class="col-6"><label class="form-label">Payment Date *</label><input class="form-control" type="date" name="payment_date" value="<?= e($pay['payment_date']) ?>" required></div>
        <div class="col-6"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><?= options(payment_methods(), $pay['payment_method']) ?></select></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($pay['notes']) ?></textarea></div>
        <?php if (!empty($askDuplicate)): ?>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_duplicate" value="1" id="dup">
                <label class="form-check-label" for="dup">This is an extra payment for the same period</label></div></div>
        <?php endif; ?>
        <div class="col-12"><button class="btn btn-success btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Salary Payment</button></div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
