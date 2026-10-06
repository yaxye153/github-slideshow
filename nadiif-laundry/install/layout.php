<?php
// =====================================================================
// layout.php - shared code for the installer pages.
// =====================================================================
define('APP_ROOT', dirname(__DIR__));
define('LOCK_FILE', APP_ROOT . '/config/installed.lock');

ini_set('display_errors', '0');
require_once APP_ROOT . '/includes/functions.php';
define('BASE_URL', detect_base_url());
start_app_session();

// INSTALLATION LOCK: the installer can never run again once installed
if (file_exists(LOCK_FILE) && empty($_SESSION['install_just_finished'])) {
    install_header(0);
    echo '<div class="alert alert-info"><h5 class="mb-1">System has already been installed.</h5>'
       . 'You will be sent to the login page in a few seconds.</div>'
       . '<a class="btn btn-primary w-100" href="' . url('login.php') . '">GO TO LOGIN</a>'
       . '<meta http-equiv="refresh" content="3;url=' . e(url('login.php')) . '">';
    install_footer();
    exit;
}

// Installer page top. $current = step number (1-6)
function install_header(int $current): void
{
    $steps = [1 => 'System Check', 2 => 'Database', 3 => 'Admin Account', 4 => 'Install Database', 5 => 'Create Admin', 6 => 'Complete'];
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install NADIIF LAUNDRY</title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="login-page">
<div class="container" style="max-width: 640px">
    <div class="text-center mb-3">
        <div class="login-logo"><i class="bi bi-basket2-fill"></i></div>
        <h1 class="h3 fw-bold">NADIIF LAUNDRY</h1>
        <p class="text-muted mb-0">Installation Wizard</p>
    </div>
    <div class="card shadow-sm"><div class="card-body p-4">
    <?php if ($current > 0): ?>
        <div class="install-steps d-flex flex-wrap gap-1 mb-4">
            <?php foreach ($steps as $n => $label): ?>
                <span class="step <?= $n < $current ? 'done' : ($n === $current ? 'current' : '') ?>"><?= $n ?>. <?= e($label) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif;
}

// Installer page bottom
function install_footer(): void
{
    ?>
    </div></div>
</div>
<script src="<?= url('assets/js/script.js') ?>"></script>
</body>
</html>
    <?php
}

// Show a tick or a cross
function check_mark(bool $ok): string
{
    return $ok ? '<span class="text-success fw-bold">&#10003;</span>' : '<span class="text-danger fw-bold">&#10007;</span>';
}
