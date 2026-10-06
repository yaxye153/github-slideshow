<?php
// users/index.php - list of users (admin only)
require_once __DIR__ . '/../auth/auth_check.php';

$users = db_all($pdo, "SELECT u.*,
        (SELECT COALESCE(SUM(v.visits), 0) FROM page_visits v WHERE v.user_id = u.id) AS visits,
        (SELECT COUNT(*) FROM activity_log a WHERE a.user_id = u.id) AS actions
    FROM users u ORDER BY u.role = 'admin' DESC, u.username");
$labels = permission_list();

$pageTitle = 'Users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-person-gear"></i> Users</h1>
    <a class="btn btn-primary" href="form.php"><i class="bi bi-person-plus"></i> Add User</a>
</div>
<p class="text-muted small">Add as many users as you need and choose what each one can do. Only the administrator can delete records,
    manage users, see the Security Report and Footprints, change Settings and use Backup &amp; Restore.</p>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Permissions</th><th>Status</th><th>Last Login</th><th class="text-center">Visits</th><th class="text-center">Actions</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><b><?= e($u['username']) ?></b><?= (int)$u['id'] === (int)$CURRENT_USER['id'] ? ' <span class="badge bg-info text-dark">you</span>' : '' ?></td>
                    <td><?= e($u['full_name']) ?></td>
                    <td><?= $u['role'] === 'admin' ? '<span class="badge bg-danger">Administrator</span>' : '<span class="badge bg-secondary">Staff</span>' ?></td>
                    <td class="small"><?= $u['role'] === 'admin' ? 'Everything' : (e(implode(', ', array_map(function ($p) use ($labels) { return explode(' ', $labels[$p])[0]; }, user_permissions($u)))) ?: '<span class="text-muted">Dashboard only</span>') ?></td>
                    <td><?= badge($u['is_active'] ? 'Active' : 'Inactive') ?></td>
                    <td class="small"><?= show_datetime($u['last_login']) ?></td>
                    <td class="text-center"><?= (int)$u['visits'] ?></td>
                    <td class="text-center"><a href="../security/activity.php?user_id=<?= $u['id'] ?>"><?= (int)$u['actions'] ?></a></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="form.php?id=<?= $u['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
