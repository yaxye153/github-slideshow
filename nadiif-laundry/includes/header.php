<?php
// header.php - top of every page (HTML head + menu).
// Set $pageTitle before including this file.
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - <?= e(setting('business_name')) ?></title>
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<?php require __DIR__ . '/navbar.php'; ?>
<main class="main-content">
    <div class="container-fluid py-3 px-3 px-lg-4">
        <?= show_flash() ?>
