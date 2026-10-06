<?php
// =====================================================================
// login_check.php - receives the login form and checks the password.
// Too many wrong passwords = blocked for 15 minutes (and recorded).
// =====================================================================
require_once __DIR__ . '/../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

// Check the hidden form token
if (!verify_csrf()) {
    log_security($pdo, 'bad_form_token', 'info', 'Login form expired or sent from another website');
    flash('warning', 'Your login form expired. Please try again.');
    redirect('login.php');
}

$username = post_text('username', 50);
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    flash('danger', 'Please enter the required information.');
    redirect('login.php');
}

// Blocked because of too many wrong passwords?
$wait = login_blocked_minutes($pdo, $username);
if ($wait > 0) {
    log_security($pdo, 'login_blocked', 'danger', 'Login refused: too many wrong passwords', $username, $password);
    flash('danger', 'Too many wrong passwords. For security, login is blocked for ' . $wait . ' minute(s).');
    redirect('login.php');
}

// Get the user and compare the password with the stored hash
$user = db_row($pdo, 'SELECT id, username, password_hash, is_active FROM users WHERE username = ?', [$username]);

if (!$user || !password_verify($password, $user['password_hash'])) {
    log_security($pdo, 'login_failed', 'warning', $user ? 'Wrong password' : 'Unknown username', $username, $password);
    sleep(1); // slow down password guessing
    flash('danger', 'Invalid username or password.');
    redirect('login.php');
}

if (!$user['is_active']) {
    log_security($pdo, 'login_inactive', 'warning', 'Switched-off account tried to log in', $username);
    flash('danger', 'This account is switched off. Ask the administrator.');
    redirect('login.php');
}

// Login OK: create a fresh session id (protects against session theft)
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];

// Remind the user to change the default password
$_SESSION['default_password'] = ($password === 'admin');

db_query($pdo, 'UPDATE users SET last_login = ? WHERE id = ?', [date('Y-m-d H:i:s'), $user['id']]);
$CURRENT_USER = ['id' => (int)$user['id'], 'username' => $user['username']];
log_security($pdo, 'login', 'info', 'Logged in');
log_activity($pdo, 'Login', 'success', 'Logged in');

redirect('dashboard.php');
