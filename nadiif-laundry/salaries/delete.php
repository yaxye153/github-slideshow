<?php
// salaries/delete.php - delete a salary payment and its expense copy
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('salaries/index.php');
}
require_csrf('salaries/index.php');

$id = (int)($_POST['id'] ?? 0);
$pdo->beginTransaction();
try {
    db_query($pdo, 'DELETE FROM salary_payments WHERE id = ?', [$id]);
    ledger_expense_delete($pdo, 'salary', $id);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    throw $ex;
}
flash('success', 'Salary payment deleted. It was also removed from expenses.');
redirect('salaries/index.php');
