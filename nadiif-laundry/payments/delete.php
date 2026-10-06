<?php
// payments/delete.php - delete a payment (for example a mistake or a refund).
// The matching income record is removed at the same time.
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('payments/index.php');
}
require_csrf('payments/index.php');

$id = (int)($_POST['id'] ?? 0);
$payment = db_row($pdo, 'SELECT * FROM payments WHERE id = ?', [$id]);
if (!$payment) {
    flash('danger', 'Payment not found.');
    redirect('payments/index.php');
}

$pdo->beginTransaction();
try {
    db_query($pdo, 'DELETE FROM payments WHERE id = ?', [$id]);
    ledger_income_delete($pdo, 'order_payment', $id);
    recalc_order($pdo, (int)$payment['order_id']);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    throw $ex;
}

flash('success', 'Payment deleted. The order balance and income were updated.');
redirect('orders/view.php?id=' . $payment['order_id']);
