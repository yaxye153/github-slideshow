<?php
// =====================================================================
// backup/restore.php - Restore database backup
//   1. Validate file      (upload: only .sql, size, content check)
//   2. Confirm it is a NADIIF LAUNDRY SQL backup
//   3. Show warning
//   4. Ask for confirmation
//   5. Make an automatic backup of the current database, then restore
//   6. Show success message
// =====================================================================
require_once __DIR__ . '/../auth/auth_check.php';
set_time_limit(300);

$step = (string)($_POST['step'] ?? '');
$uploadDir = backup_dir() . '/uploads';

// Remove a waiting uploaded file (never touches normal backups)
function clear_pending_restore(): void
{
    $pending = $_SESSION['pending_restore'] ?? null;
    if ($pending && $pending['temp'] && is_file($pending['path'])) {
        unlink($pending['path']);
    }
    unset($_SESSION['pending_restore']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('backup/index.php');

    // ----- Step 1+2: an uploaded file -----
    if ($step === 'upload') {
        $f = $_FILES['backup_file'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $tooBig = $f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            flash('danger', $tooBig ? 'The file is too large for the server upload limit (upload_max_filesize in php.ini).' : 'Please choose a backup file to upload.');
            redirect('backup/index.php');
        }
        if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'sql') {
            flash('danger', 'Only .sql backup files are allowed.');
            redirect('backup/index.php');
        }
        if ($f['size'] <= 0 || $f['size'] > 64 * 1024 * 1024) {
            flash('danger', 'The backup file is empty or larger than 64 MB.');
            redirect('backup/index.php');
        }
        $check = check_restore_sql((string)file_get_contents($f['tmp_name']));
        if ($check['error'] !== '') {
            log_security($pdo, 'restore_rejected', strpos($check['error'], 'not allowed') !== false ? 'danger' : 'warning',
                'File ' . basename($f['name']) . ': ' . $check['error']);
            flash('danger', 'Backup not accepted: ' . $check['error']);
            redirect('backup/index.php');
        }

        clear_pending_restore();
        $path = $uploadDir . '/restore_' . bin2hex(random_bytes(8)) . '.sql';
        if (!move_uploaded_file($f['tmp_name'], $path)) {
            flash('danger', 'The file could not be saved. Check that backup/files/uploads can be written to.');
            redirect('backup/index.php');
        }
        $_SESSION['pending_restore'] = ['path' => $path, 'label' => basename($f['name']), 'temp' => true];
        redirect('backup/restore.php');
    }

    // ----- Step 1+2: a backup from the Backup History list -----
    if ($step === 'choose') {
        $file = basename((string)($_POST['file'] ?? ''));
        if (!is_backup_filename($file) || !is_file(backup_dir() . '/' . $file)) {
            flash('danger', 'Backup file not found.');
            redirect('backup/index.php');
        }
        clear_pending_restore();
        $_SESSION['pending_restore'] = ['path' => backup_dir() . '/' . $file, 'label' => $file, 'temp' => false];
        redirect('backup/restore.php');
    }

    if ($step === 'cancel') {
        clear_pending_restore();
        flash('info', 'Restore cancelled. Nothing was changed.');
        redirect('backup/index.php');
    }

    // ----- Step 4-6: confirmed -> restore -----
    if ($step === 'confirm') {
        $pending = $_SESSION['pending_restore'] ?? null;
        if (!$pending || !is_file($pending['path'])) {
            flash('danger', 'There is no backup waiting to be restored. Please upload it again.');
            redirect('backup/index.php');
        }
        if (empty($_POST['i_understand'])) {
            flash('warning', 'Please tick the box to confirm that you understand the current data may be replaced.');
            redirect('backup/restore.php');
        }

        $check = check_restore_sql((string)file_get_contents($pending['path']));
        if ($check['error'] !== '') {
            clear_pending_restore();
            flash('danger', 'Backup not accepted: ' . $check['error']);
            redirect('backup/index.php');
        }

        // Automatic backup of the current database BEFORE restoring
        try {
            $safetyFile = create_backup($pdo, 'pre_restore');
        } catch (Throwable $e) {
            error_log('Pre-restore backup failed: ' . $e->getMessage());
            flash('danger', 'Restore stopped: the automatic safety backup could not be created. Nothing was changed.');
            redirect('backup/index.php');
        }

        try {
            run_restore($pdo, $check['statements']);
        } catch (Throwable $e) {
            error_log('Restore failed: ' . $e->getMessage());
            // Something went wrong: put the previous data back from the safety backup
            $recovered = false;
            try {
                $safety = check_restore_sql((string)file_get_contents(backup_dir() . '/' . $safetyFile));
                run_restore($pdo, $safety['statements']);
                $recovered = true;
            } catch (Throwable $e2) {
                error_log('Automatic recovery failed: ' . $e2->getMessage());
            }
            clear_pending_restore();
            flash('danger', 'The restore failed. ' . ($recovered
                ? 'Your previous data was put back automatically from ' . $safetyFile . '.'
                : 'Please restore ' . $safetyFile . ' from the Backup History list.'));
            redirect('backup/index.php');
        }

        clear_pending_restore();

        // The restored database has the OLD logs: write down who restored it, so this is never lost
        log_security($pdo, 'restore', 'warning', 'Database restored from ' . $pending['label'] . '. Old data saved as ' . $safetyFile);
        log_activity($pdo, 'Backup: restore', 'success', 'Database restored from ' . $pending['label'] . ' (old data saved as ' . $safetyFile . ')');

        // The users table may have changed, so log in again
        $_SESSION = [];
        session_regenerate_id(true);
        flash('success', 'Database restored successfully. A backup of the old data was saved as ' . $safetyFile . '. Please log in again.');
        redirect('login.php');
    }

    redirect('backup/index.php');
}

// ----- Step 3: show the warning and ask for confirmation -----
$pending = $_SESSION['pending_restore'] ?? null;
if (!$pending || !is_file($pending['path'])) {
    redirect('backup/index.php');
}
$info = check_restore_sql((string)file_get_contents($pending['path']));

$pageTitle = 'Confirm Restore';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h1><i class="bi bi-exclamation-triangle text-warning"></i> Confirm Restore</h1></div>

<div class="card shadow-sm border-danger" style="max-width: 720px"><div class="card-body">
    <div class="alert alert-danger fw-bold">WARNING: Restoring a backup may replace current data.</div>
    <table class="table table-sm">
        <tr><th>Backup file</th><td><?= e($pending['label']) ?></td></tr>
        <tr><th>Size</th><td><?= human_size((int)filesize($pending['path'])) ?></td></tr>
        <tr><th>Tables inside</th><td><?= e(implode(', ', $info['tables'] ?? [])) ?></td></tr>
        <tr><th>Check</th><td><?= $info['error'] === '' ? '<span class="text-success">Valid NADIIF LAUNDRY SQL backup</span>' : '<span class="text-danger">' . e($info['error']) . '</span>' ?></td></tr>
    </table>
    <p>Before restoring, the system will automatically save a backup of your <b>current</b> data, so you can go back if needed.
        After the restore you will need to log in again (with the username and password stored in the backup).</p>

    <?php if ($info['error'] === ''): ?>
        <form method="post" data-confirm="Are you sure you want to restore this backup now?">
            <?= csrf_field() ?><input type="hidden" name="step" value="confirm">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="i_understand" value="1" id="iu" required>
                <label class="form-check-label" for="iu">I understand that the current data may be replaced.</label>
            </div>
            <button class="btn btn-danger btn-lg" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Yes, Restore Database</button>
        </form>
    <?php endif; ?>
    <form method="post" class="mt-2">
        <?= csrf_field() ?><input type="hidden" name="step" value="cancel">
        <button class="btn btn-outline-secondary" type="submit">Cancel</button>
    </form>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
