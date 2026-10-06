<?php
// =====================================================================
// STEP 1 - System Check
// Checks PHP version, MySQL/PDO, PHP extensions and folder permissions.
// =====================================================================
require __DIR__ . '/layout.php';

// Check PHP version (8.0 or newer)
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');

// Check PDO and the MySQL driver for PDO
$pdoOk = extension_loaded('pdo');
$mysqlDriverOk = extension_loaded('pdo_mysql');

// Try to reach MySQL with the normal XAMPP settings (just for information)
$mysqlOk = false;
if ($mysqlDriverOk) {
    try {
        new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [PDO::ATTR_TIMEOUT => 3]);
        $mysqlOk = true;
    } catch (Throwable $e) {
        $mysqlOk = false;
    }
}

// Other PHP extensions the system needs
$extensions = ['session', 'json', 'mbstring'];
$missing = array_filter($extensions, function ($ext) { return !extension_loaded($ext); });
$extOk = !$missing;

// Folders the system must be able to write to
$folders = [APP_ROOT . '/config', APP_ROOT . '/backup/files', APP_ROOT . '/backup/files/uploads'];
$notWritable = array_filter($folders, function ($dir) { return !is_dir($dir) || !is_writable($dir); });
$permOk = !$notWritable;

// MySQL being off here is only a warning - step 2 lets you enter other settings
$allOk = $phpOk && $pdoOk && $mysqlDriverOk && $extOk && $permOk;

install_header(1);
?>
<h2 class="h5 mb-3">Step 1 &mdash; System Check</h2>
<ul class="list-group mb-3">
    <li class="list-group-item d-flex justify-content-between"><span>PHP <?= e(PHP_VERSION) ?> (8.0 or newer)</span><?= check_mark($phpOk) ?></li>
    <li class="list-group-item d-flex justify-content-between"><span>MySQL <?= $mysqlOk ? '' : '<small class="text-muted">(not reached with root / no password - you can change this in step 2)</small>' ?></span><?= $mysqlOk ? check_mark(true) : '<span class="text-warning fw-bold">!</span>' ?></li>
    <li class="list-group-item d-flex justify-content-between"><span>PDO + PDO MySQL driver</span><?= check_mark($pdoOk && $mysqlDriverOk) ?></li>
    <li class="list-group-item d-flex justify-content-between"><span>Extensions (<?= e(implode(', ', $extensions)) ?>)</span><?= check_mark($extOk) ?></li>
    <li class="list-group-item d-flex justify-content-between"><span>Permissions (config, backup folders)</span><?= check_mark($permOk) ?></li>
</ul>

<?php if ($missing): ?>
    <div class="alert alert-danger">Missing PHP extensions: <?= e(implode(', ', $missing)) ?>. Enable them in php.ini and restart Apache.</div>
<?php endif; ?>
<?php if ($notWritable): ?>
    <div class="alert alert-danger">These folders must be writable:<br><?= implode('<br>', array_map('e', $notWritable)) ?></div>
<?php endif; ?>
<?php if (!$mysqlOk && $mysqlDriverOk): ?>
    <div class="alert alert-warning">Could not connect to MySQL. Open the XAMPP Control Panel and make sure <b>MySQL</b> is started.</div>
<?php endif; ?>

<?php if ($allOk): ?>
    <a class="btn btn-primary btn-lg w-100" href="database.php">Next: Database &raquo;</a>
<?php else: ?>
    <a class="btn btn-secondary btn-lg w-100" href="index.php">Check Again</a>
<?php endif; ?>
<?php install_footer(); ?>
