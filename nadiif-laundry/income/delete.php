<?php
// income/delete.php - delete income that was entered by hand
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('income/index.php');
}
require_csrf('income/index.php');

// Only manual records. Automatic records are removed from their own page.
$deleted = db_query($pdo, "DELETE FROM income WHERE id = ? AND source = 'manual'", [(int)($_POST['id'] ?? 0)])->rowCount();
flash($deleted ? 'success' : 'danger', $deleted ? 'Income record deleted.' : 'This record can not be deleted here.');
redirect('income/index.php');
