<?php
// users/form.php - add a user or change a user (admin only):
// username, name, password, role, permissions, active / switched off
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$user = ['username' => '', 'full_name' => '', 'role' => 'staff', 'permissions' => json_encode(['customers', 'orders']), 'is_active' => 1];
if ($id) {
    $user = db_row($pdo, 'SELECT * FROM users WHERE id = ?', [$id]);
    if (!$user) {
        flash('danger', 'User not found.');
        redirect('users/index.php');
    }
}
$isSelf = $id === (int)$CURRENT_USER['id'];
$activeAdmins = (int)db_value($pdo, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $perms = array_values(array_intersect((array)($_POST['permissions'] ?? []), array_keys(permission_list())));
    $new = [
        'username' => post_text('username', 50),
        'full_name' => post_text('full_name', 100),
        'role' => in_list(post_text('role', 10), ['admin', 'staff'], 'staff'),
        'permissions' => json_encode($perms),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $new['username'])) {
        $errors[] = 'Username must be 3-50 characters: letters, numbers, dot, dash or underscore.';
    } elseif (db_value($pdo, 'SELECT id FROM users WHERE username = ? AND id <> ?', [$new['username'], $id])) {
        $errors[] = 'That username is already used.';
    }
    if ($new['full_name'] === '') {
        $errors[] = 'Please enter the full name.';
    }
    if (!$id && $password === '') {
        $errors[] = 'Please enter a password for the new user.';
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'The password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }
    // Never lock everyone out: the last active admin stays admin and active
    if ($id && $user['role'] === 'admin' && $user['is_active'] && $activeAdmins <= 1 && ($new['role'] !== 'admin' || !$new['is_active'])) {
        $errors[] = 'This is the only active administrator. Add another administrator first.';
    }
    if ($isSelf && (!$new['is_active'] || $new['role'] !== 'admin')) {
        $errors[] = 'You cannot switch off or remove admin rights from your own account.';
    }

    if (!$errors) {
        require_csrf('users/index.php');
        $values = [$new['username'], $new['full_name'], $new['role'], $new['permissions'], $new['is_active']];
        if ($id) {
            db_query($pdo, 'UPDATE users SET username = ?, full_name = ?, role = ?, permissions = ?, is_active = ? WHERE id = ?', array_merge($values, [$id]));
            if ($password !== '') {
                db_query($pdo, 'UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            log_security($pdo, 'user_changed', 'info', 'User ' . $new['username'] . ' changed: role ' . $new['role'] . ', '
                . ($new['is_active'] ? 'active' : 'SWITCHED OFF') . ', permissions ' . implode(',', $perms) . ($password !== '' ? ', password reset' : ''));
            flash('success', 'User updated.');
        } else {
            db_query($pdo, 'INSERT INTO users (username, full_name, role, permissions, is_active, password_hash) VALUES (?, ?, ?, ?, ?, ?)',
                array_merge($values, [password_hash($password, PASSWORD_DEFAULT)]));
            log_security($pdo, 'user_added', 'info', 'New user ' . $new['username'] . ' (' . $new['role'] . ') permissions ' . implode(',', $perms));
            flash('success', 'User added. They can now log in with their username and password.');
        }
        if ($isSelf) {
            $_SESSION['username'] = $new['username'];
        }
        redirect('users/index.php');
    }
    $user = array_merge($user, $new);
}
$userPerms = user_permissions($user);

$pageTitle = $id ? 'Edit User' : 'Add User';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-person-gear"></i> <?= $id ? 'Edit User' : 'Add User' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Users</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 860px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Username *</label><input class="form-control" name="username" value="<?= e($user['username']) ?>" required maxlength="50" autocomplete="off"></div>
        <div class="col-md-6"><label class="form-label">Full Name *</label><input class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required maxlength="100"></div>
        <div class="col-md-6"><label class="form-label">Password <?= $id ? '<small class="text-muted">(leave empty to keep)</small>' : '*' ?></label>
            <input class="form-control" type="password" name="password" autocomplete="new-password" <?= $id ? '' : 'required' ?>></div>
        <div class="col-md-6"><label class="form-label">Confirm Password</label><input class="form-control" type="password" name="confirm_password" autocomplete="new-password"></div>
        <div class="col-md-6">
            <label class="form-label">Role</label>
            <select class="form-select" name="role" id="role">
                <option value="staff" <?= $user['role'] !== 'admin' ? 'selected' : '' ?>>Staff (only the sections ticked below)</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator (everything, including delete)</option>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" <?= $user['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Active (can log in)</label>
            </div>
        </div>
        <div class="col-12" id="perm-box">
            <label class="form-label fw-bold">What can this user do?</label>
            <div class="row g-2">
                <?php foreach (permission_list() as $key => $label): ?>
                    <div class="col-md-6"><div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= $key ?>" id="p-<?= $key ?>" <?= in_array($key, $userPerms, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="p-<?= $key ?>"><?= e($label) ?></label>
                    </div></div>
                <?php endforeach; ?>
            </div>
            <div class="form-text">Staff can view, add and edit in the ticked sections. They can never delete, and never see Settings, Users,
                Backup, Security Report or Footprints.</div>
        </div>
        <div class="col-12"><button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save User</button></div>
    </div>
</form>

<?php if ($id && !$isSelf): ?>
    <form method="post" action="delete.php" class="mt-3" data-confirm="Remove this user? Their footprints (history) are kept.">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-person-x"></i> Remove User</button>
        <small class="text-muted ms-2">Tip: switching the user off (Active) is usually better &mdash; you can switch them on again later.</small>
    </form>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var role = document.getElementById('role');
    function toggle() { document.getElementById('perm-box').style.display = role.value === 'admin' ? 'none' : ''; }
    role.addEventListener('change', toggle); toggle();
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
