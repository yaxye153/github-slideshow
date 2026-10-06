<?php
// orders/status.php - change the status of an order and/or its shelf number.
// Every change is written to the order trace (who + when).
// Delivered = the customer received the order (picked up, or delivered to them).
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
} elseif (($_POST['return'] ?? '') === 'dashboard') {
    $back = 'dashboard.php';
}
require_csrf($back);

$status = (string)($_POST['status'] ?? '');
if (!in_array($status, order_statuses(), true)) {
    flash('danger', 'Please choose a valid status.');
    redirect($back);
}
$order = db_row($pdo, 'SELECT id, order_number, status, pickup_type, shelf_number FROM orders WHERE id = ?', [$id]);
if (!$order) {
    flash('danger', 'Order not found.');
    redirect($back);
}

$receivedBy = post_text('received_by', 100);
$note = post_text('note', 200);

$pdo->beginTransaction();
try {
    if (isset($_POST['shelf_number'])) {
        $shelf = post_text('shelf_number', 20);
        db_query($pdo, 'UPDATE orders SET shelf_number = ? WHERE id = ?', [$shelf, $id]);
        if ($shelf !== (string)$order['shelf_number']) {
            log_order_step($pdo, $id, $order['status'], 'Shelf: ' . ($shelf !== '' ? $shelf : '(none)'));
        }
    }
    if ($status !== $order['status']) {
        db_query($pdo, 'UPDATE orders SET status = ? WHERE id = ?', [$status, $id]);
        if ($status === 'Delivered') {
            // Handover: when and to whom
            db_query($pdo, 'UPDATE orders SET handed_over_at = ?, received_by = ? WHERE id = ?', [date('Y-m-d H:i:s'), $receivedBy, $id]);
            $note = trim(($order['pickup_type'] === 'Delivery' ? 'Delivered to customer' : 'Picked up by customer')
                  . ($receivedBy !== '' ? ', received by ' . $receivedBy : '') . ($note !== '' ? '. ' . $note : ''));
        }
        log_order_step($pdo, $id, $status, $note);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    throw $ex;
}

flash('success', 'Order ' . $order['order_number'] . ' updated: ' . $status . '.');
if ($status === 'Ready' && $order['status'] !== 'Ready') {
    flash('info', 'Order is ready - send the customer a message with the WhatsApp / SMS button.');
}
redirect($back);
