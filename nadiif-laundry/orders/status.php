<?php
// orders/status.php - change the status of an order (Received -> Washing -> ... -> Delivered)
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('orders/index.php');
}
$id = (int)($_POST['id'] ?? 0);
require_csrf('orders/view.php?id=' . $id);

$status = (string)($_POST['status'] ?? '');
if (!in_array($status, order_statuses(), true)) {
    flash('danger', 'Please choose a valid status.');
    redirect('orders/view.php?id=' . $id);
}

db_query($pdo, 'UPDATE orders SET status = ? WHERE id = ?', [$status, $id]);
flash('success', 'Order status changed to ' . $status . '.');
redirect('orders/view.php?id=' . $id);
