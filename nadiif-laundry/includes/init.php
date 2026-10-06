<?php
// =====================================================================
// init.php - loaded at the top of every page.
// It starts the session, connects to MySQL and loads the settings.
// =====================================================================

// Folder where the system lives (e.g. C:\xampp\htdocs\nadiif-laundry)
define('APP_ROOT', dirname(__DIR__));
define('LOCK_FILE', APP_ROOT . '/config/installed.lock');

// Hide technical PHP errors from normal users (they are written to the PHP error log instead)
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once APP_ROOT . '/includes/functions.php';

// Work out the web address of the system, e.g. "/nadiif-laundry"
define('BASE_URL', detect_base_url());

// If the system has not been installed yet, open the installer
if (!file_exists(LOCK_FILE)) {
    header('Location: ' . BASE_URL . '/install/');
    exit;
}

require_once APP_ROOT . '/config/database.php';

// Show a friendly page instead of a PHP error if something goes wrong
set_exception_handler(function (Throwable $e) {
    error_log('NADIIF LAUNDRY error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Something went wrong</title><link rel="stylesheet" href="' . BASE_URL . '/assets/vendor/bootstrap/bootstrap.min.css"></head>'
        . '<body class="bg-light"><div class="container py-5" style="max-width:560px"><div class="alert alert-danger">'
        . '<h5>Sorry, something went wrong.</h5><p class="mb-2">The action could not be completed. No changes were saved.</p>'
        . '<p class="mb-0 small">If this keeps happening, make sure MySQL is running in the XAMPP Control Panel.</p></div>'
        . '<a class="btn btn-primary" href="' . BASE_URL . '/dashboard.php">Back to Dashboard</a></div></body></html>';
    exit;
});

// Start the session (used for login and messages)
start_app_session();

// Connect to MySQL
$pdo = db_connect(DB_HOST, DB_NAME, DB_USER, DB_PASS);

// Load business settings (name, currency, ...)
$SETTINGS = load_settings($pdo);
date_default_timezone_set(setting('timezone', 'Africa/Mogadishu'));
