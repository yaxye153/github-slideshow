<?php
// orders/status.php - change the status (Received -> Washing -> ... -> Delivered)
// and/or the shelf number of an order
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('orders/index.php');
}
$id = (int)($_POST['id'] ?? 0);

// Where to go back to after saving
$back = 'orders/view.php?id=' . $id;
if (($_POST['return'] ?? '') === 'tracking') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $back = 'tracking/index.php' . ($customerId ? '?customer_id=' . $customerId : '');
}
require_csrf($back);

$status = (string)($_POST['status'] ?? '');
if (!in_array($status, order_statuses(), true)) {
    flash('danger', 'Please choose a valid status.');
    redirect($back);
}
$number = db_value($pdo, 'SELECT order_number FROM orders WHERE id = ?', [$id]);
if (!$number) {
    flash('danger', 'Order not found.');
    redirect($back);
}

if (isset($_POST['shelf_number'])) {
    db_query($pdo, 'UPDATE orders SET status = ?, shelf_number = ? WHERE id = ?', [$status, post_text('shelf_number', 20), $id]);
} else {
    db_query($pdo, 'UPDATE orders SET status = ? WHERE id = ?', [$status, $id]);
}
flash('success', 'Order ' . $number . ' updated: ' . $status . '.');
redirect($back);
