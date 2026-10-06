<?php
// =====================================================================
// auth_check.php - put this at the top of every protected page.
// It loads the system and sends visitors who are not logged in to the
// login page.
// =====================================================================
require_once __DIR__ . '/../includes/init.php';

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    flash('warning', 'Please log in first.');
    redirect('login.php');
}

// Make sure the user still exists (for example after restoring a backup)
$CURRENT_USER = db_row($pdo, 'SELECT id, username, full_name FROM users WHERE id = ?', [(int)$_SESSION['user_id']]);
if (!$CURRENT_USER) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . url('login.php'));
    exit;
}
