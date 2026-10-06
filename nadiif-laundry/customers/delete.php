<?php
// customers/delete.php - delete a customer (only if they have no orders)
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customers/index.php');
}
require_csrf('customers/index.php');

$id = (int)($_POST['id'] ?? 0);
$orders = (int)db_value($pdo, 'SELECT COUNT(*) FROM orders WHERE customer_id = ?', [$id]);

if ($orders > 0) {
    // Deleting would lose real sales history, so we do not allow it
    flash('danger', 'This customer has ' . $orders . ' order(s) and cannot be deleted. Delete their orders first.');
    redirect('customers/view.php?id=' . $id);
}

db_query($pdo, 'DELETE FROM customers WHERE id = ?', [$id]);
flash('success', 'Customer deleted successfully.');
redirect('customers/index.php');
