<?php
// settings/services.php - manage service types (Wash, Wash & Iron, ...)
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('settings/services.php');
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = post_text('name', 50);
        if ($name === '') {
            flash('danger', 'Please enter the service name.');
        } elseif (db_value($pdo, 'SELECT id FROM service_types WHERE name = ?', [$name])) {
            flash('warning', 'That service already exists.');
        } else {
            db_query($pdo, 'INSERT INTO service_types (name) VALUES (?)', [$name]);
            flash('success', 'Service added.');
        }
    } elseif ($action === 'toggle') {
        // Switching a service off hides it from new orders. Old orders keep it.
        db_query($pdo, 'UPDATE service_types SET is_active = 1 - is_active WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        flash('success', 'Service updated.');
    } elseif ($action === 'rename') {
        $name = post_text('name', 50);
        $id = (int)($_POST['id'] ?? 0);
        if ($name === '' || db_value($pdo, 'SELECT id FROM service_types WHERE name = ? AND id <> ?', [$name, $id])) {
            flash('danger', 'Please enter a new, unused name.');
        } else {
            db_query($pdo, 'UPDATE service_types SET name = ? WHERE id = ?', [$name, $id]);
            flash('success', 'Service renamed. Existing orders keep the old name.');
        }
    }
    redirect('settings/services.php');
}

$services = db_all($pdo, 'SELECT * FROM service_types ORDER BY id');

$pageTitle = 'Service Types';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-list-check"></i> Service Types</h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Settings</a>
</div>

<form method="post" class="card card-body shadow-sm mb-3" style="max-width: 640px">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="input-group">
        <input class="form-control" name="name" placeholder="New service name, e.g. Steam Press" required maxlength="50">
        <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Add</button>
    </div>
</form>

<div class="card shadow-sm" style="max-width: 640px">
    <ul class="list-group list-group-flush">
        <?php foreach ($services as $sv): ?>
            <li class="list-group-item">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <form method="post" class="d-flex gap-2 flex-grow-1">
                        <?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= $sv['id'] ?>">
                        <input class="form-control" name="name" value="<?= e($sv['name']) ?>" maxlength="50">
                        <button class="btn btn-outline-secondary" type="submit" title="Rename"><i class="bi bi-check-lg"></i></button>
                    </form>
                    <?= badge($sv['is_active'] ? 'Active' : 'Inactive') ?>
                    <form method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $sv['id'] ?>">
                        <button class="btn btn-sm btn-outline-<?= $sv['is_active'] ? 'danger' : 'success' ?>" type="submit"><?= $sv['is_active'] ? 'Switch off' : 'Switch on' ?></button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
