<?php
// orders/notify.php - send the "your order is ready" message.
// It opens WhatsApp (or the phone's SMS app) with the message already written;
// the staff member only presses Send. The time is saved on the order.
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('orders/index.php');
}
$id = (int)($_POST['id'] ?? 0);
require_csrf('orders/view.php?id=' . $id);

$order = db_row($pdo, 'SELECT o.*, c.full_name, c.phone FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?', [$id]);
if (!$order) {
    flash('danger', 'Order not found.');
    redirect('orders/index.php');
}
$phone = international_phone($order['delivery_phone'] ?: $order['phone']);
if (strlen($phone) < 8) {
    flash('danger', 'The customer has no valid phone number.');
    redirect('orders/view.php?id=' . $id);
}

$via = ($_POST['via'] ?? '') === 'sms' ? 'SMS' : 'WhatsApp';
$text = ready_message_text($order);
db_query($pdo, 'UPDATE orders SET notified_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $id]);
log_order_step($pdo, $id, $order['status'], 'Ready message opened in ' . $via . ' (+' . $phone . ')');

if ($via === 'SMS') {
    header('Location: sms:+' . $phone . '?body=' . rawurlencode($text));
} else {
    header('Location: https://wa.me/' . $phone . '?text=' . rawurlencode($text));
}
exit;
