<?php
// daily-running/delete.php - delete a daily running cost and its expense copy
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('daily-running/index.php');
}
require_csrf('daily-running/index.php');

$id = (int)($_POST['id'] ?? 0);
$pdo->beginTransaction();
try {
    db_query($pdo, 'DELETE FROM daily_running_costs WHERE id = ?', [$id]);
    ledger_expense_delete($pdo, 'daily_running', $id);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    throw $ex;
}
flash('success', 'Record deleted.');
redirect('daily-running/index.php');
