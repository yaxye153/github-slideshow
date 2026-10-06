<?php
// =====================================================================
// STEP 2 - Database
// Tests the MySQL connection and creates the database if needed.
// =====================================================================
require __DIR__ . '/layout.php';

// Default XAMPP settings
$db = $_SESSION['install_db'] ?? ['host' => 'localhost', 'name' => 'nadiif_laundry', 'user' => 'root', 'pass' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = [
        'host' => trim((string)($_POST['host'] ?? '')),
        'name' => trim((string)($_POST['name'] ?? '')),
        'user' => trim((string)($_POST['user'] ?? '')),
        'pass' => (string)($_POST['pass'] ?? ''),
    ];

    if ($db['host'] === '' || $db['name'] === '' || $db['user'] === '') {
        $error = 'Please enter the required information.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db['name'])) {
        $error = 'The database name may only contain letters, numbers and underscores (_).';
    } else {
        try {
            // Connect to MySQL (without choosing a database yet)
            $server = new PDO('mysql:host=' . $db['host'] . ';charset=utf8mb4', $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5,
            ]);
            // Create the database automatically (if it does not exist)
            $server->exec('CREATE DATABASE IF NOT EXISTS `' . $db['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            $_SESSION['install_db'] = $db;
            header('Location: install.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Could not connect to MySQL with these settings. Check that MySQL is running in XAMPP and that the username and password are correct.';
        }
    }
}

install_header(2);
?>
<h2 class="h5 mb-3">Step 2 &mdash; Database</h2>
<p class="text-muted small">For a normal XAMPP installation you can keep these values and click Next. The database will be created automatically.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post">
    <div class="mb-3">
        <label class="form-label">Host</label>
        <input class="form-control" name="host" value="<?= e($db['host']) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Database name</label>
        <input class="form-control" name="name" value="<?= e($db['name']) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">MySQL username</label>
        <input class="form-control" name="user" value="<?= e($db['user']) ?>" required>
    </div>
    <div class="mb-4">
        <label class="form-label">MySQL password <small class="text-muted">(empty on XAMPP)</small></label>
        <input class="form-control" type="password" name="pass" value="<?= e($db['pass']) ?>">
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-lg" href="index.php">&laquo; Back</a>
        <button class="btn btn-primary btn-lg flex-grow-1" type="submit">Create Database &amp; Continue &raquo;</button>
    </div>
</form>
<?php install_footer(); ?>
