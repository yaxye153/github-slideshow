<?php
// logout.php - ends the session and returns to the login page
require_once __DIR__ . '/includes/init.php';

if (!empty($_SESSION['user_id'])) {
    $CURRENT_USER = ['id' => (int)$_SESSION['user_id'], 'username' => (string)($_SESSION['username'] ?? '')];
    log_security($pdo, 'logout', 'info', 'Logged out');
    log_activity($pdo, 'Logout', 'success', 'Logged out');
}

// Forget the logged-in user and replace the session id with a new one
// (the old session is deleted on the server)
$_SESSION = [];
session_regenerate_id(true);

flash('success', 'You have been logged out.');
redirect('login.php');
