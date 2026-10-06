<?php
// orders/delete.php - delete an order that has NO payments.
// Orders with payments must keep their history: delete the payments first
// (for example when money was returned), or set the status to Cancelled.
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('orders/index.php');
}
$id = (int)($_POST['id'] ?? 0);
require_csrf('orders/view.php?id=' . $id);

$payments = (int)db_value($pdo, 'SELECT COUNT(*) FROM payments WHERE order_id = ?', [$id]);
if ($payments > 0) {
    flash('danger', 'This order has payments and cannot be deleted. Change its status to Cancelled, or delete the payments first.');
    redirect('orders/view.php?id=' . $id);
}

// Items are deleted automatically (ON DELETE CASCADE). Deliveries keep their record without the order link.
db_query($pdo, 'DELETE FROM orders WHERE id = ?', [$id]);
flash('success', 'Order deleted successfully.');
redirect('orders/index.php');
