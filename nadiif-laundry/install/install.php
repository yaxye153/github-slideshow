<?php
// =====================================================================
// STEP 3 - Admin Account (form)
// STEP 4 - Install Database (create all tables)
// STEP 5 - Create Admin (with a hashed password)
// STEP 6 - Complete Installation
// =====================================================================
require __DIR__ . '/layout.php';

// ----- STEP 6: finished -----
if (isset($_GET['done']) && !empty($_SESSION['install_just_finished'])) {
    $log = $_SESSION['install_log'] ?? [];
    unset($_SESSION['install_just_finished'], $_SESSION['install_log'], $_SESSION['install_db']);
    install_header(6);
    ?>
    <ul class="list-group mb-3">
        <?php foreach ($log as $line): ?>
            <li class="list-group-item d-flex justify-content-between"><span><?= e($line) ?></span><?= check_mark(true) ?></li>
        <?php endforeach; ?>
    </ul>
    <div class="alert alert-success text-center fw-bold">NADIIF LAUNDRY HAS BEEN INSTALLED SUCCESSFULLY</div>
    <a class="btn btn-success btn-lg w-100" href="<?= url('login.php') ?>">GO TO LOGIN</a>
    <?php
    install_footer();
    exit;
}

// Step 2 must be finished first
if (empty($_SESSION['install_db'])) {
    header('Location: database.php');
    exit;
}
$db = $_SESSION['install_db'];

$username = 'admin';
$error = '';

// Is there already NADIIF data in this database? (It will be kept.)
try {
    $pdo = db_connect($db['host'], $db['name'], $db['user'], $db['pass']);
} catch (Throwable $e) {
    unset($_SESSION['install_db']);
    header('Location: database.php');
    exit;
}
$hasData = (bool)$pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter the required information.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = 'Username must be 3-50 characters: letters, numbers, dot, dash or underscore.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } elseif (strlen($password) < 4) {
        $error = 'Password must be at least 4 characters.';
    } else {
        try {
            $log = [];

            // ----- STEP 4: Install database (create every table) -----
            $schema = file_get_contents(APP_ROOT . '/database/nadiif_laundry.sql');
            foreach (sql_split_statements($schema) as $statement) {
                $pdo->exec($statement);
            }
            $log[] = 'Database tables created';

            // Default settings (existing values are kept)
            $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
            foreach (default_settings() as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            // Default service types (only when the list is empty)
            if ((int)$pdo->query('SELECT COUNT(*) FROM service_types')->fetchColumn() === 0) {
                $stmt = $pdo->prepare('INSERT INTO service_types (name) VALUES (?)');
                foreach (['Wash', 'Wash & Iron', 'Iron Only', 'Dry Clean', 'Special Cleaning'] as $service) {
                    $stmt->execute([$service]);
                }
            }
            $log[] = 'Default settings and service types saved';

            // ----- STEP 5: Create admin with a secure hashed password -----
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, 'Administrator', 'admin')
                                   ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
            $stmt->execute([$username, $hash]);
            $log[] = 'Admin account "' . $username . '" created (password is stored hashed)';

            // Save the database settings to config/database.php
            $config = "<?php\n"
                . "// =====================================================================\n"
                . "// Database settings - written by the installer on " . date('Y-m-d H:i:s') . "\n"
                . "// =====================================================================\n"
                . 'define(\'DB_HOST\', ' . var_export($db['host'], true) . ");\n"
                . 'define(\'DB_NAME\', ' . var_export($db['name'], true) . ");\n"
                . 'define(\'DB_USER\', ' . var_export($db['user'], true) . ");\n"
                . 'define(\'DB_PASS\', ' . var_export($db['pass'], true) . ");\n";
            if (file_put_contents(APP_ROOT . '/config/database.php', $config) === false) {
                throw new RuntimeException('Could not write config/database.php');
            }
            $log[] = 'Configuration file saved';

            // INSTALLATION LOCK - stops the installer from running again
            if (file_put_contents(LOCK_FILE, 'Installed on ' . date('Y-m-d H:i:s') . "\n") === false) {
                throw new RuntimeException('Could not write the installation lock file');
            }
            $log[] = 'Installation locked';

            $_SESSION['install_log'] = $log;
            $_SESSION['install_just_finished'] = true;
            header('Location: install.php?done=1');
            exit;
        } catch (Throwable $e) {
            error_log('NADIIF install error: ' . $e->getMessage());
            $error = 'Installation failed: ' . $e->getMessage();
        }
    }
}

install_header(3);
?>
<h2 class="h5 mb-3">Step 3 &mdash; Admin Account</h2>
<?php if ($hasData): ?>
    <div class="alert alert-info small">Existing NADIIF LAUNDRY data was found in database <b><?= e($db['name']) ?></b>. It will be <b>kept</b>.
        If the username below already exists, its password will be reset.</div>
<?php endif; ?>
<p class="text-muted small">Default login is <b>admin / admin</b>. You can change it now or later from Settings.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post">
    <div class="mb-3">
        <label class="form-label">Admin username</label>
        <input class="form-control" name="username" value="<?= e($username) ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Admin password</label>
        <input class="form-control" type="password" name="password" value="admin" required>
    </div>
    <div class="mb-4">
        <label class="form-label">Confirm password</label>
        <input class="form-control" type="password" name="confirm" value="admin" required>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-lg" href="database.php">&laquo; Back</a>
        <button class="btn btn-success btn-lg flex-grow-1" type="submit">Install Now</button>
    </div>
    <p class="small text-muted mt-3 mb-0">Clicking "Install Now" runs Step 4 (create tables) and Step 5 (create admin).</p>
</form>
<?php install_footer(); ?>
