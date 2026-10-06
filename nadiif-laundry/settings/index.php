<?php
// settings/index.php - business settings and admin account
require_once __DIR__ . '/../auth/auth_check.php';

$action = (string)($_POST['action'] ?? '');
$errors = [];

// ----- Save business settings -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'business') {
    $values = [
        'business_name' => post_text('business_name', 100),
        'business_phone' => post_text('business_phone', 50),
        'business_address' => post_text('business_address', 255),
        'currency_code' => strtoupper(post_text('currency_code', 10)),
        'currency_symbol' => post_text('currency_symbol', 10),
        'receipt_footer' => post_text('receipt_footer', 500),
        'timezone' => post_text('timezone', 64),
        'theme' => in_list(post_text('theme', 10), array_keys(themes()), 'blue'),
    ];
    if ($values['business_name'] === '' || $values['currency_symbol'] === '') {
        $errors[] = 'Please enter the required information (business name and currency symbol).';
    }
    if (!in_array($values['timezone'], timezone_identifiers_list(), true)) {
        $errors[] = 'Please choose a valid time zone.';
    }
    if (!$errors) {
        require_csrf('settings/index.php');
        foreach ($values as $key => $value) {
            save_setting($pdo, $key, $value);
        }
        flash('success', 'Settings saved successfully.');
        redirect('settings/index.php');
    }
}

// ----- Save service speeds and customer levels -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'speeds') {
    $values = [];
    foreach (['normal' => 'Normal', 'express' => 'Express', 'vip' => 'VIP'] as $key => $label) {
        $hours = trim((string)($_POST['speed_' . $key . '_hours'] ?? ''));
        $percent = trim((string)($_POST['speed_' . $key . '_percent'] ?? ''));
        if (!ctype_digit($hours) || (int)$hours < 1 || (int)$hours > 720) {
            $errors[] = $label . ': hours must be a whole number from 1 to 720.';
        }
        if (!is_numeric($percent) || (float)$percent < 0 || (float)$percent > 500) {
            $errors[] = $label . ': extra charge must be from 0 to 500 %.';
        }
        $values['speed_' . $key . '_hours'] = (string)(int)$hours;
        $values['speed_' . $key . '_percent'] = (string)round((float)$percent, 2);
    }
    foreach (['normal' => 'Normal', 'silver' => 'Silver', 'gold' => 'Gold'] as $key => $label) {
        $discount = trim((string)($_POST['tier_' . $key . '_discount'] ?? ''));
        if (!is_numeric($discount) || (float)$discount < 0 || (float)$discount > 100) {
            $errors[] = $label . ' customers: discount must be from 0 to 100 %.';
        }
        $values['tier_' . $key . '_discount'] = (string)round((float)$discount, 2);
    }
    if (!$errors) {
        require_csrf('settings/index.php');
        foreach ($values as $key => $value) {
            save_setting($pdo, $key, $value);
        }
        flash('success', 'Service speeds and customer levels saved. They apply to NEW orders only.');
        redirect('settings/index.php#speeds');
    }
}

// ----- Change admin username / password -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'account') {
    $username = post_text('username', 50);
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    $user = db_row($pdo, 'SELECT * FROM users WHERE id = ?', [$CURRENT_USER['id']]);
    if (!password_verify($current, $user['password_hash'])) {
        $errors[] = 'Your current password is not correct.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters: letters, numbers, dot, dash or underscore.';
    } elseif (db_value($pdo, 'SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $user['id']])) {
        $errors[] = 'That username is already used.';
    }
    if ($new !== '' && strlen($new) < 6) {
        $errors[] = 'The new password must be at least 6 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }

    if (!$errors) {
        require_csrf('settings/index.php');
        if ($new !== '') {
            // Store only the hash of the password, never the password itself
            db_query($pdo, 'UPDATE users SET username = ?, password_hash = ? WHERE id = ?', [$username, password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $_SESSION['default_password'] = false;
        } else {
            db_query($pdo, 'UPDATE users SET username = ? WHERE id = ?', [$username, $user['id']]);
        }
        $_SESSION['username'] = $username;
        flash('success', $new !== '' ? 'Username and password updated.' : 'Username updated.');
        redirect('settings/index.php');
    }
}

$s = $SETTINGS;
if (in_array($action, ['business', 'speeds'], true) && $errors) {
    $s = array_merge($s, $values);
}

$pageTitle = 'Settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-gear"></i> Settings</h1>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="prices.php"><i class="bi bi-tags"></i> Price List</a>
        <a class="btn btn-outline-primary" href="services.php"><i class="bi bi-list-check"></i> Service Types</a>
    </div>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" class="card shadow-sm">
            <div class="card-header bg-white"><strong>Business</strong></div>
            <div class="card-body">
                <?= csrf_field() ?><input type="hidden" name="action" value="business">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Business Name *</label><input class="form-control" name="business_name" value="<?= e($s['business_name']) ?>" required maxlength="100"></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="business_phone" value="<?= e($s['business_phone']) ?>" maxlength="50"></div>
                    <div class="col-md-6"><label class="form-label">Address</label><input class="form-control" name="business_address" value="<?= e($s['business_address']) ?>" maxlength="255"></div>
                    <div class="col-6 col-md-3"><label class="form-label">Currency</label><input class="form-control" name="currency_code" value="<?= e($s['currency_code']) ?>" maxlength="10"></div>
                    <div class="col-6 col-md-3"><label class="form-label">Symbol *</label><input class="form-control" name="currency_symbol" value="<?= e($s['currency_symbol']) ?>" required maxlength="10"></div>
                    <div class="col-md-6"><label class="form-label">Time Zone</label>
                        <select class="form-select" name="timezone"><?= options(timezone_identifiers_list(), $s['timezone']) ?></select></div>
                    <div class="col-12">
                        <label class="form-label">Theme (colours)</label>
                        <div class="d-flex flex-wrap gap-3">
                            <?php $swatches = ['blue' => '#0d4f8b', 'green' => '#13795b', 'dark' => '#1f2633']; ?>
                            <?php foreach (themes() as $key => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="theme" id="theme-<?= $key ?>" value="<?= $key ?>" <?= ($s['theme'] ?? 'blue') === $key ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="theme-<?= $key ?>"><span class="theme-swatch" style="background: <?= $swatches[$key] ?>"></span><?= e($label) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12"><label class="form-label">Receipt Footer</label><textarea class="form-control" name="receipt_footer" rows="2" maxlength="500"><?= e($s['receipt_footer']) ?></textarea></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save Settings</button></div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5" id="password">
        <form method="post" class="card shadow-sm">
            <div class="card-header bg-white"><strong>Admin Account</strong></div>
            <div class="card-body">
                <?= csrf_field() ?><input type="hidden" name="action" value="account">
                <div class="mb-3"><label class="form-label">Admin Username</label><input class="form-control" name="username" value="<?= e($CURRENT_USER['username']) ?>" required autocomplete="username"></div>
                <div class="mb-3"><label class="form-label">Current Password *</label><input class="form-control" type="password" name="current_password" required autocomplete="current-password"></div>
                <div class="mb-3"><label class="form-label">New Password <small class="text-muted">(leave empty to keep)</small></label><input class="form-control" type="password" name="new_password" autocomplete="new-password"></div>
                <div class="mb-3"><label class="form-label">Confirm New Password</label><input class="form-control" type="password" name="confirm_password" autocomplete="new-password"></div>
                <button class="btn btn-warning" type="submit"><i class="bi bi-shield-lock"></i> Update Account</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mt-1" id="speeds">
    <div class="col-12">
        <form method="post" class="card shadow-sm">
            <div class="card-header bg-white"><strong>Service Speed &amp; Customer Levels</strong></div>
            <div class="card-body">
                <?= csrf_field() ?><input type="hidden" name="action" value="speeds">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <h3 class="h6">Service speed (how fast the order is ready)</h3>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Speed</th><th>Ready in (hours)</th><th>Extra charge (%)</th></tr></thead>
                            <?php foreach (['normal' => 'Normal', 'express' => 'Express', 'vip' => 'VIP'] as $key => $label): ?>
                                <tr><td><?= speed_badge($label) ?></td>
                                    <td><input class="form-control form-control-sm" type="number" min="1" max="720" step="1" name="speed_<?= $key ?>_hours" value="<?= e($s['speed_' . $key . '_hours']) ?>" required></td>
                                    <td><input class="form-control form-control-sm" type="number" min="0" max="500" step="0.01" name="speed_<?= $key ?>_percent" value="<?= e($s['speed_' . $key . '_percent']) ?>" required></td></tr>
                            <?php endforeach; ?>
                        </table></div>
                        <div class="form-text">Example: Express 24 hours +50% means a $10 order costs $15 and must be ready in 24 hours.</div>
                    </div>
                    <div class="col-lg-5">
                        <h3 class="h6">Customer levels (automatic discount)</h3>
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Level</th><th>Discount (%)</th></tr></thead>
                            <?php foreach (['normal' => 'Normal', 'silver' => 'Silver', 'gold' => 'Gold'] as $key => $label): ?>
                                <tr><td><?= tier_badge($label) ?></td>
                                    <td><input class="form-control form-control-sm" type="number" min="0" max="100" step="0.01" name="tier_<?= $key ?>_discount" value="<?= e($s['tier_' . $key . '_discount']) ?>" required></td></tr>
                            <?php endforeach; ?>
                        </table>
                        <div class="form-text">Choose the level of each customer in the customer form.</div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save Speeds &amp; Levels</button>
                        <small class="text-muted ms-2">Changes apply to new orders only. Old orders keep their price.</small>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
