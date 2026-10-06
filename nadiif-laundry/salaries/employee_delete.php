<?php
// salaries/employee_delete.php - delete an employee with no salary history
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('salaries/employees.php');
}
require_csrf('salaries/employees.php');

$id = (int)($_POST['id'] ?? 0);
if ((int)db_value($pdo, 'SELECT COUNT(*) FROM salary_payments WHERE employee_id = ?', [$id]) > 0) {
    flash('danger', 'This employee has salary payments and cannot be deleted. Set the status to Inactive instead.');
    redirect('salaries/employee_form.php?id=' . $id);
}
db_query($pdo, 'DELETE FROM employees WHERE id = ?', [$id]);
flash('success', 'Employee deleted.');
redirect('salaries/employees.php');
