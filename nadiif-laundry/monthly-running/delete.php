<?php
// monthly-running/delete.php - delete a monthly running cost and its expense copy
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('monthly-running/index.php');
}
require_csrf('monthly-running/index.php');

$id = (int)($_POST['id'] ?? 0);
$pdo->beginTransaction();
try {
    db_query($pdo, 'DELETE FROM monthly_running_costs WHERE id = ?', [$id]);
    ledger_expense_delete($pdo, 'monthly_running', $id);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    throw $ex;
}
flash('success', 'Record deleted.');
redirect('monthly-running/index.php');
