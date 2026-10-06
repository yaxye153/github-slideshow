<?php
// expenses/delete.php - delete an expense that was entered by hand
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('expenses/index.php');
}
require_csrf('expenses/index.php');

// Only manual records. Automatic records are removed from their own page.
$deleted = db_query($pdo, "DELETE FROM expenses WHERE id = ? AND source = 'manual'", [(int)($_POST['id'] ?? 0)])->rowCount();
flash($deleted ? 'success' : 'danger', $deleted ? 'Expense deleted.' : 'This record can not be deleted here.');
redirect('expenses/index.php');
