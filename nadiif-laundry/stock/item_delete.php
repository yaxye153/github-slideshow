<?php
// stock/item_delete.php - delete a stock item that was never bought (admin only)
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('stock/index.php');
}
require_csrf('stock/index.php');

$id = (int)($_POST['id'] ?? 0);
if ((int)db_value($pdo, 'SELECT COUNT(*) FROM stock_batches WHERE item_id = ?', [$id]) > 0) {
    flash('danger', 'This item has purchases and cannot be deleted. Switch it off instead.');
    redirect('stock/item_form.php?id=' . $id);
}
db_query($pdo, 'DELETE FROM stock_items WHERE id = ?', [$id]);
flash('success', 'Stock item deleted.');
redirect('stock/index.php');
