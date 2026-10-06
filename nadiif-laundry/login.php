<?php
// login.php - the login page
require_once __DIR__ . '/includes/init.php';

// Already logged in? Go straight to the dashboard
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= e(setting('business_name')) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="login-page">
<div class="container">
    <div class="login-box mx-auto">
        <div class="text-center mb-4">
            <div class="login-logo"><i class="bi bi-basket2-fill"></i></div>
            <h1 class="h3 fw-bold mt-2"><?= e(setting('business_name')) ?></h1>
            <p class="text-muted mb-0">Laundry Management System</p>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <?= show_flash() ?>
                <form method="post" action="<?= url('auth/login_check.php') ?>" class="js-once">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control form-control-lg" id="username" name="username" required autofocus autocomplete="username">
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control form-control-lg" type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="<?= url('assets/js/script.js') ?>"></script>
<script src="<?= url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
