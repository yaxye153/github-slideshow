<?php
// backup/delete.php - delete a backup file.
// Only files named like a backup can be deleted, so system files are safe.
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('backup/index.php');
}
require_csrf('backup/index.php');

$file = basename((string)($_POST['file'] ?? ''));
$path = backup_dir() . '/' . $file;

if (!is_backup_filename($file) || !is_file($path)) {
    log_security($pdo, 'bad_file_request', 'danger', 'Tried to delete file: ' . mb_substr((string)($_POST['file'] ?? ''), 0, 200));
    flash('danger', 'This file cannot be deleted.');
} elseif (unlink($path)) {
    flash('success', 'Backup deleted.');
} else {
    flash('danger', 'The file could not be deleted.');
}
redirect('backup/index.php');
