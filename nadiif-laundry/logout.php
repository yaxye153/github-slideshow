<?php
// logout.php - ends the session and returns to the login page
require_once __DIR__ . '/includes/init.php';

// Forget the logged-in user and replace the session id with a new one
// (the old session is deleted on the server)
$_SESSION = [];
session_regenerate_id(true);

flash('success', 'You have been logged out.');
redirect('login.php');
