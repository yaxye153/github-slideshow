<?php
// backup/download.php - download a backup file
require_once __DIR__ . '/../auth/auth_check.php';

$file = basename((string)($_GET['file'] ?? ''));
$path = backup_dir() . '/' . $file;

// Only real backup files can be downloaded (no other system files)
if (!is_backup_filename($file) || !is_file($path)) {
    flash('danger', 'Backup file not found.');
    redirect('backup/index.php');
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
