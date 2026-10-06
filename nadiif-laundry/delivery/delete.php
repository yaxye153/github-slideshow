<?php
// delivery/delete.php - delete a delivery and its income/expense copies
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('delivery/index.php');
}
require_csrf('delivery/index.php');

$id = (int)($_POST['id'] ?? 0);
$pdo->beginTransaction();
try {
    db_query($pdo, 'DELETE FROM deliveries WHERE id = ?', [$id]);
    ledger_expense_delete($pdo, 'delivery', $id);
    ledger_income_delete($pdo, 'delivery', $id);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    throw $ex;
}
flash('success', 'Delivery deleted.');
redirect('delivery/index.php');
