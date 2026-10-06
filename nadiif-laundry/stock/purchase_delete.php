<?php
// stock/purchase_delete.php - delete a stock purchase that was not used yet (admin only)
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('stock/purchases.php');
}
require_csrf('stock/purchases.php');

$id = (int)($_POST['id'] ?? 0);
$batch = db_row($pdo, 'SELECT * FROM stock_batches WHERE id = ?', [$id]);
if (!$batch) {
    flash('danger', 'Purchase not found.');
} elseif ((float)$batch['qty_left'] < (float)$batch['qty_in']) {
    flash('danger', 'Part of this purchase was already used, so it cannot be deleted. Edit it instead.');
} else {
    $pdo->beginTransaction();
    try {
        db_query($pdo, 'DELETE FROM stock_batches WHERE id = ?', [$id]);
        ledger_expense_delete($pdo, 'stock', $id);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    flash('success', 'Purchase deleted. Its expense was also removed.');
}
redirect('stock/purchases.php');
