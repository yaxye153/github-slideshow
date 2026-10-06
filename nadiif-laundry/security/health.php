<?php
// security/health.php - SYSTEM HEALTH (admin only)
// Checks the things that most often make a small XAMPP system crash or lose data:
// disk full, damaged database tables, no recent backup, low PHP limits, errors.
require_once __DIR__ . '/../auth/auth_check.php';

$checks = [];   // [label, status ok|warning|danger, text]

// PHP
$checks[] = ['PHP version', version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'danger', PHP_VERSION];
$memory = ini_get('memory_limit');
$checks[] = ['PHP memory limit', ((int)$memory >= 128 || $memory === '-1') ? 'ok' : 'warning', $memory . ' (128M or more recommended)'];
$checks[] = ['Upload limit (for restoring backups)', 'ok', ini_get('upload_max_filesize') . ' (post_max_size ' . ini_get('post_max_size') . ')'];

// Disk space: a full disk is the most common cause of MySQL crashes and damaged tables
$free = @disk_free_space(APP_ROOT);
if ($free !== false) {
    $gb = $free / 1073741824;
    $checks[] = ['Free disk space', $gb < 1 ? 'danger' : ($gb < 5 ? 'warning' : 'ok'), number_format($gb, 1) . ' GB free' . ($gb < 5 ? ' - free some space soon' : '')];
}

// MySQL
$checks[] = ['MySQL / MariaDB version', 'ok', (string)db_value($pdo, 'SELECT VERSION()')];
$size = (float)db_value($pdo, 'SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()');
$checks[] = ['Database size', 'ok', human_size((int)$size)];

// Backups
$backups = list_backups();
$lastBackup = $backups[0]['time'] ?? 0;
$age = $lastBackup ? (time() - $lastBackup) / 86400 : null;
$checks[] = ['Last backup on this computer', $age === null ? 'danger' : ($age > 2 ? 'warning' : 'ok'),
             $lastBackup ? date('d M Y H:i', $lastBackup) . ' (' . count($backups) . ' backup files)' : 'No backup yet!'];
$checks[] = ['Backup folder can be written', is_writable(backup_dir()) ? 'ok' : 'danger', backup_dir()];
$mail = mail_config();
$lastEmail = setting('email_backup_last_success');
$checks[] = ['Daily email backup (Gmail)', !$mail['enabled'] ? 'warning' : (strpos(setting('email_backup_last_message'), 'FAILED') === 0 ? 'danger' : 'ok'),
             !$mail['enabled'] ? 'Switched off - a copy outside this computer is strongly recommended' : 'Last sent: ' . ($lastEmail ? show_datetime($lastEmail) : 'never')];

// Check every table (finds damaged tables after a power cut)
$tableResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_tables') {
    require_csrf('security/health.php');
    set_time_limit(300);
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $rows = $pdo->query('CHECK TABLE `' . str_replace('`', '``', $table) . '`')->fetchAll();
        $last = end($rows);
        $tableResults[] = ['table' => $table, 'status' => $last['Msg_text'] ?? '?', 'ok' => strtolower($last['Msg_text'] ?? '') === 'ok'];
    }
    $bad = count(array_filter($tableResults, function ($r) { return !$r['ok']; }));
    log_activity($pdo, 'System Health: check tables', $bad ? 'failed' : 'success', $bad . ' table(s) with problems');
}

// Recent PHP errors (if the error log can be read)
$errorLines = [];
$logFile = ini_get('error_log');
if ($logFile && is_file($logFile) && is_readable($logFile)) {
    $fh = fopen($logFile, 'r');
    fseek($fh, max(0, filesize($logFile) - 20000));
    $lines = explode("\n", (string)stream_get_contents($fh));
    fclose($fh);
    $errorLines = array_slice(array_values(array_filter($lines, function ($l) { return stripos($l, 'NADIIF') !== false; })), -15);
}

$logCounts = [
    'Footprints' => (int)db_value($pdo, 'SELECT COUNT(*) FROM activity_log'),
    'Security events' => (int)db_value($pdo, 'SELECT COUNT(*) FROM security_log'),
    'Order trace steps' => (int)db_value($pdo, 'SELECT COUNT(*) FROM order_history'),
];

$problems = count(array_filter($checks, function ($c) { return $c[1] === 'danger'; }));
$warnings = count(array_filter($checks, function ($c) { return $c[1] === 'warning'; }));

$pageTitle = 'System Health';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-heart-pulse"></i> System Health</h1>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="../backup/index.php"><i class="bi bi-database-down"></i> Backup now</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="check_tables">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Check database tables</button></form>
    </div>
</div>

<div class="alert alert-<?= $problems ? 'danger' : ($warnings ? 'warning' : 'success') ?>">
    <?= $problems ? '<b>' . $problems . ' problem(s)</b> need attention now.' : ($warnings ? $warnings . ' warning(s).' : '<b>Everything looks healthy.</b>') ?>
</div>

<div class="card shadow-sm mb-3"><div class="table-responsive"><table class="table mb-0">
    <?php foreach ($checks as [$label, $status, $text]): ?>
        <tr><td style="width: 34px"><i class="bi <?= $status === 'ok' ? 'bi-check-circle-fill text-success' : ($status === 'warning' ? 'bi-exclamation-triangle-fill text-warning' : 'bi-x-octagon-fill text-danger') ?>"></i></td>
            <th><?= e($label) ?></th><td><?= e($text) ?></td></tr>
    <?php endforeach; ?>
</table></div></div>

<?php if ($tableResults): ?>
    <div class="section-title">Database tables</div>
    <div class="card shadow-sm mb-3"><div class="table-responsive"><table class="table table-sm mb-0">
        <?php foreach ($tableResults as $r): ?><tr class="<?= $r['ok'] ? '' : 'table-danger' ?>"><td><?= e($r['table']) ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?>
    </table></div></div>
    <?php if (array_filter($tableResults, function ($r) { return !$r['ok']; })): ?>
        <div class="alert alert-danger">Some tables are damaged. Do not keep working: restore the newest good backup from Backup &amp; Restore
            (the system makes one automatically every day).</div>
    <?php endif; ?>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100"><div class="card-body small">
            <h2 class="h6">How the system protects itself</h2>
            <ul class="mb-0 ps-3">
                <li>Every important save uses a <b>database transaction</b>: if something fails half-way, nothing is saved.</li>
                <li>A <b>local backup is made automatically every day</b> (the newest 14 are kept), plus the optional Gmail backup.</li>
                <li>Errors never show a broken page: users see a friendly message, details go to the PHP error log.</li>
                <li>Double clicks and repeated forms are blocked; restores make a safety backup first.</li>
                <li>Power cuts are the biggest danger for XAMPP. Use a <b>UPS</b> for the computer, and always stop MySQL in the XAMPP Control Panel before shutting down.</li>
            </ul>
        </div></div>
    </div>
    <div class="col-lg-3">
        <div class="card shadow-sm h-100"><div class="card-body small">
            <h2 class="h6">Log sizes</h2>
            <?php foreach ($logCounts as $label => $n): ?><div class="d-flex justify-content-between"><span><?= e($label) ?></span><b><?= number_format($n) ?></b></div><?php endforeach; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100"><div class="card-body small">
            <h2 class="h6">Recent system errors</h2>
            <?php if ($errorLines): ?>
                <pre class="small mb-0" style="white-space: pre-wrap; max-height: 220px; overflow:auto"><?= e(implode("\n", $errorLines)) ?></pre>
            <?php else: ?>
                <span class="text-muted"><?= $logFile ? 'No recent errors.' : 'The PHP error log is not set up (error_log in php.ini).' ?></span>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
