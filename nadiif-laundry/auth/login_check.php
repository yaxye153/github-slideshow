<?php
// =====================================================================
// login_check.php - receives the login form and checks the password.
// =====================================================================
require_once __DIR__ . '/../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

// Check the hidden form token
if (!verify_csrf()) {
    flash('warning', 'Your login form expired. Please try again.');
    redirect('login.php');
}

$username = post_text('username', 50);
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    flash('danger', 'Please enter the required information.');
    redirect('login.php');
}

// Get the user and compare the password with the stored hash
$user = db_row($pdo, 'SELECT id, username, password_hash FROM users WHERE username = ?', [$username]);

if (!$user || !password_verify($password, $user['password_hash'])) {
    sleep(1); // slow down password guessing
    flash('danger', 'Invalid username or password.');
    redirect('login.php');
}

// Login OK: create a fresh session id (protects against session theft)
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];

// Remind the admin to change the default password
$_SESSION['default_password'] = ($password === 'admin');

redirect('dashboard.php');
