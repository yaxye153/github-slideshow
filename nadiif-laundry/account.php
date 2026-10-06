<?php
// account.php - MY ACCOUNT: every user can change their own password here
require_once __DIR__ . '/auth/auth_check.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $hash = (string)db_value($pdo, 'SELECT password_hash FROM users WHERE id = ?', [$CURRENT_USER['id']]);

    if (!password_verify($current, $hash)) {
        $errors[] = 'Your current password is not correct.';
        log_security($pdo, 'password_change_failed', 'warning', 'Wrong current password on My Account');
    }
    if (strlen($new) < 6) {
        $errors[] = 'The new password must be at least 6 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }
    if (!$errors) {
        require_csrf('account.php');
        db_query($pdo, 'UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $CURRENT_USER['id']]);
        session_regenerate_id(true);
        $_SESSION['default_password'] = false;
        log_security($pdo, 'password_changed', 'info', 'Changed own password');
        flash('success', 'Your password was changed.');
        redirect('account.php');
    }
}

$me = db_row($pdo, 'SELECT username, full_name, role, permissions, last_login FROM users WHERE id = ?', [$CURRENT_USER['id']]);
$labels = permission_list();

$pageTitle = 'My Account';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header"><h1><i class="bi bi-person-circle"></i> My Account</h1></div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm"><div class="card-body">
            <h2 class="h5"><?= e($me['full_name']) ?></h2>
            <p class="mb-1">Username: <b><?= e($me['username']) ?></b></p>
            <p class="mb-1">Role: <?= $me['role'] === 'admin' ? '<span class="badge bg-danger">Administrator</span>' : '<span class="badge bg-secondary">Staff</span>' ?></p>
            <p class="mb-2 small text-muted">Last login: <?= show_datetime($me['last_login']) ?></p>
            <p class="mb-1 fw-bold">You can use:</p>
            <ul class="small mb-0">
                <?php if ($me['role'] === 'admin'): ?><li>Everything</li><?php else: ?>
                    <li>Dashboard</li>
                    <?php foreach (user_permissions($me) as $perm): ?><li><?= e($labels[$perm]) ?></li><?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div></div>
    </div>
    <div class="col-lg-7">
        <form method="post" class="card shadow-sm" id="password">
            <div class="card-header bg-white"><strong>Change My Password</strong></div>
            <div class="card-body">
                <?= csrf_field() ?>
                <div class="mb-3"><label class="form-label">Current Password</label><input class="form-control" type="password" name="current_password" required autocomplete="current-password"></div>
                <div class="mb-3"><label class="form-label">New Password (6+ characters)</label><input class="form-control" type="password" name="new_password" required autocomplete="new-password"></div>
                <div class="mb-3"><label class="form-label">Confirm New Password</label><input class="form-control" type="password" name="confirm_password" required autocomplete="new-password"></div>
                <button class="btn btn-warning" type="submit"><i class="bi bi-shield-lock"></i> Change Password</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
