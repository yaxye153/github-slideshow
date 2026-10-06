<?php
// assets/delete.php - delete an asset (admin only) and its expense copy
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('assets/index.php');
}
require_csrf('assets/index.php');

$id = (int)($_POST['id'] ?? 0);
$pdo->beginTransaction();
try {
    db_query($pdo, 'DELETE FROM assets WHERE id = ?', [$id]);
    ledger_expense_delete($pdo, 'asset', $id);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    throw $ex;
}
flash('success', 'Asset deleted.');
redirect('assets/index.php');
