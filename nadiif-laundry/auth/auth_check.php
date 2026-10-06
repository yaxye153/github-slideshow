<?php
// =====================================================================
// auth_check.php - put this at the top of every protected page.
// 1. Sends visitors who are not logged in to the login page
// 2. Checks the user's permissions for this page (admin = everything)
// 3. Only the admin may delete anything
// 4. Records visits, suspicious input and every saved form (footprints)
// =====================================================================
require_once __DIR__ . '/../includes/init.php';

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        log_security($pdo, 'not_logged_in', 'warning', 'Form sent without being logged in');
    }
    flash('warning', 'Please log in first.');
    redirect('login.php');
}

// Load the user again on every page, so a blocked or changed user takes effect at once
$CURRENT_USER = db_row($pdo, 'SELECT id, username, full_name, role, permissions, is_active FROM users WHERE id = ?', [(int)$_SESSION['user_id']]);
if (!$CURRENT_USER || !$CURRENT_USER['is_active']) {
    $_SESSION = [];
    session_regenerate_id(true);
    flash('warning', 'Your account is switched off or was removed. Ask the administrator.');
    redirect('login.php');
}

$page = current_page();

// Record possible hacking attempts in what was sent (the system is protected anyway)
$suspicious = suspicious_input();
if ($suspicious !== '') {
    log_security($pdo, 'suspicious_input', 'danger', $suspicious);
}

// Does this user have permission for this page?
$needed = page_permission($page);
if ($needed !== null && !can($needed)) {
    log_security($pdo, 'access_denied', 'warning', 'Tried to open ' . $page . ' (needs: ' . $needed . ')');
    flash('danger', 'You do not have permission to open that page. Ask the administrator.');
    redirect('dashboard.php');
}

// Only the admin may delete anything
if (strpos(basename($page), 'delete') !== false && !is_admin()) {
    log_security($pdo, 'delete_denied', 'warning', 'Non-admin tried to delete (' . $page . ', id ' . (int)($_POST['id'] ?? 0) . ')');
    flash('danger', 'Only the administrator can delete records.');
    redirect('dashboard.php');
}

// Count the visit (normal page opens only)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    count_visit($pdo, (int)$CURRENT_USER['id']);
}

// FOOTPRINT: when a form is sent, remember who, what and the result
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page !== 'backup/auto.php') {
    $flashBefore = count($_SESSION['flash'] ?? []);
    register_shutdown_function(function () use ($pdo, $page, $flashBefore) {
        // Messages shown to the user tell us what happened ("Order saved successfully.")
        $messages = array_slice($_SESSION['flash'] ?? [], $flashBefore);
        $outcome = 'success';
        $texts = [];
        foreach ($messages as $m) {
            $texts[] = $m['message'];
            if (in_array($m['type'], ['danger', 'warning'], true)) {
                $outcome = 'failed';
            }
        }
        if (!empty($GLOBALS['app_failed'])) {
            $outcome = 'error';
            $texts = ['System error - nothing was saved.'];
        } elseif (!$messages && !empty($GLOBALS['errors'])) {
            $outcome = 'failed';
            $texts = (array)$GLOBALS['errors'];
        }
        $recordId = (int)($_GET['id'] ?? $_POST['id'] ?? $_GET['order_id'] ?? $_POST['order_id'] ?? 0) ?: null;
        $details = trim(implode(' ', $texts) . ' | ' . safe_post_summary(), ' |');
        log_activity($pdo, page_action_name($page), $outcome, $details, $recordId);
    });
}
