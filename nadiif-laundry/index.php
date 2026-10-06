<?php
// index.php - start page.
// Not installed yet -> installer. Logged in -> dashboard. Otherwise -> login.
require_once __DIR__ . '/includes/init.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}
redirect('login.php');
