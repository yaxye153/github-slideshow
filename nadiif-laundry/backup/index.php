<?php
// backup/index.php - BACKUP & RESTORE
require_once __DIR__ . '/../auth/auth_check.php';

$backups = list_backups();

$pageTitle = 'Backup & Restore';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h1><i class="bi bi-database-down"></i> Backup &amp; Restore</h1></div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100"><div class="card-body">
            <h2 class="h5"><i class="bi bi-download"></i> Backup Database</h2>
            <p class="text-muted small">Saves everything (customers, orders, payments, income, expenses, salaries, deliveries, settings, users)
                into one .sql file. Download it and keep a copy on a USB stick or in the cloud.</p>
            <form method="post" action="create.php">
                <?= csrf_field() ?>
                <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-database-down"></i> BACKUP DATABASE</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 border-warning"><div class="card-body">
            <h2 class="h5"><i class="bi bi-upload"></i> Restore Database</h2>
            <p class="small text-danger fw-bold mb-2">WARNING: Restoring a backup may replace current data.</p>
            <form method="post" action="restore.php" enctype="multipart/form-data">
                <?= csrf_field() ?><input type="hidden" name="step" value="upload">
                <input class="form-control mb-2" type="file" name="backup_file" accept=".sql" required>
                <button class="btn btn-warning" type="submit"><i class="bi bi-upload"></i> UPLOAD BACKUP</button>
                <div class="form-text">Only .sql files. You will see a confirmation page before anything is changed.</div>
            </form>
        </div></div>
    </div>
</div>

<?php
$mailConfig = mail_config();
$lastEmail = setting('email_backup_last_success');
$lastEmailMessage = setting('email_backup_last_message');
?>
<div class="card shadow-sm mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
    <div class="flex-grow-1">
        <h2 class="h5 mb-1"><i class="bi bi-envelope-at"></i> Daily Email Backup (Gmail)
            <?= $mailConfig['enabled'] ? '<span class="badge bg-success">ON</span>' : '<span class="badge bg-secondary">OFF</span>' ?></h2>
        <div class="small text-muted">Last sent: <?= $lastEmail ? show_datetime($lastEmail) : 'never' ?>
            <?php if (strpos($lastEmailMessage, 'FAILED') === 0): ?><br><span class="text-danger"><?= e($lastEmailMessage) ?></span><?php endif; ?></div>
    </div>
    <a class="btn btn-outline-primary" href="email.php"><i class="bi bi-gear"></i> Email Backup Settings</a>
</div></div>

<div class="section-title">Backup History</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Backup File</th><th>Date</th><th>Size</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($backups as $b): ?>
                <tr>
                    <td><i class="bi bi-file-earmark-code"></i> <?= e($b['name']) ?>
                        <?= strpos($b['name'], 'pre_restore') !== false ? '<span class="badge bg-info text-dark">automatic, before restore</span>' : '' ?>
                        <?= strpos($b['name'], 'nadiif_laundry_auto_') === 0 ? '<span class="badge bg-secondary">daily email backup</span>' : '' ?></td>
                    <td><?= date('d M Y H:i', $b['time']) ?></td>
                    <td><?= human_size($b['size']) ?></td>
                    <td class="actions text-end">
                        <a class="btn btn-sm btn-outline-primary" href="download.php?file=<?= urlencode($b['name']) ?>"><i class="bi bi-download"></i> Download</a>
                        <form method="post" action="restore.php" class="d-inline">
                            <?= csrf_field() ?><input type="hidden" name="step" value="choose"><input type="hidden" name="file" value="<?= e($b['name']) ?>">
                            <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>
                        </form>
                        <?php if (is_admin()): /* only the admin can delete */ ?><form method="post" action="delete.php" class="d-inline" data-confirm="Delete this backup file? This cannot be undone.">
                            <?= csrf_field() ?><input type="hidden" name="file" value="<?= e($b['name']) ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete</button>
                        </form><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$backups): ?><tr><td colspan="4" class="text-center text-muted py-4">No backups yet. Click "BACKUP DATABASE".</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">Backup files are stored in: <code><?= e(backup_dir()) ?></code></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
