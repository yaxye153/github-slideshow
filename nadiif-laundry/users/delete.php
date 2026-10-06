<?php
// users/delete.php - remove a user (admin only).
// Their footprints stay in the logs (the username is kept there).
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('users/index.php');
}
require_csrf('users/index.php');

$id = (int)($_POST['id'] ?? 0);
$user = db_row($pdo, 'SELECT * FROM users WHERE id = ?', [$id]);
$activeAdmins = (int)db_value($pdo, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");

if (!$user) {
    flash('danger', 'User not found.');
} elseif ($id === (int)$CURRENT_USER['id']) {
    flash('danger', 'You cannot remove your own account.');
} elseif ($user['role'] === 'admin' && $user['is_active'] && $activeAdmins <= 1) {
    flash('danger', 'You cannot remove the only active administrator.');
} else {
    db_query($pdo, 'DELETE FROM users WHERE id = ?', [$id]);
    log_security($pdo, 'user_removed', 'warning', 'User ' . $user['username'] . ' (' . $user['role'] . ') was removed');
    flash('success', 'User ' . $user['username'] . ' removed. Their history is kept in Footprints.');
}
redirect('users/index.php');
