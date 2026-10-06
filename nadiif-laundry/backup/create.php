<?php
// backup/create.php - Create database backup
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('backup/index.php');
}
require_csrf('backup/index.php');

try {
    $file = create_backup($pdo, 'backup');
    flash('success', 'Backup created successfully: ' . $file);
} catch (Throwable $e) {
    error_log('Backup failed: ' . $e->getMessage());
    flash('danger', 'The backup could not be created. Check that the folder backup/files can be written to.');
}
redirect('backup/index.php');
