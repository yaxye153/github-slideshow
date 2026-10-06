<?php
// =====================================================================
// backup/cron.php - send today's email backup from Windows Task Scheduler.
// It runs from the command line only (not from the browser):
//   C:\xampp\php\php.exe C:\xampp\htdocs\nadiif-laundry\backup\cron.php
// =====================================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

define('APP_ROOT', dirname(__DIR__));
define('BASE_URL', '');
require APP_ROOT . '/includes/functions.php';

if (!file_exists(APP_ROOT . '/config/installed.lock')) {
    fwrite(STDERR, "NADIIF LAUNDRY is not installed yet.\n");
    exit(1);
}
require APP_ROOT . '/config/database.php';

$pdo = db_connect(DB_HOST, DB_NAME, DB_USER, DB_PASS);
$SETTINGS = load_settings($pdo);
if ((int)setting('db_version', '1') < APP_DB_VERSION) {
    run_upgrades($pdo);
    $SETTINGS = load_settings($pdo);
}
date_default_timezone_set(setting('timezone', 'Africa/Mogadishu'));

if (!mail_config()['enabled']) {
    echo "Email backup is switched off (Backup & Restore > Email Backup).\n";
    exit(0);
}
// The Task Scheduler decides the time, so the "send after" hour is ignored here
if (!email_backup_due(null, true)) {
    echo "Nothing to do: today's backup was already sent (or a failed try was less than 1 hour ago).\n";
    exit(0);
}
[$sent, $message] = run_email_backup($pdo, true);
echo ($sent ? 'OK: ' : 'FAILED: ') . $message . "\n";
exit($sent ? 0 : 1);
