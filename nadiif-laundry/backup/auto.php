<?php
// backup/auto.php - called in the background by the browser (see includes/footer.php).
// Sends today's email backup if it is due. Nothing happens if it is not due.
require_once __DIR__ . '/../auth/auth_check.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !email_backup_due()) {
    echo json_encode(['sent' => false, 'message' => 'Not due']);
    exit;
}
session_write_close();          // do not block other pages while sending
ignore_user_abort(true);        // keep going even if the user leaves the page
set_time_limit(300);

[$sent, $message] = run_email_backup($pdo);
echo json_encode(['sent' => $sent, 'message' => $message]);
