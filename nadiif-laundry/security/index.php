<?php
// security/index.php - SECURITY REPORT (admin only)
// Hacking attempts, failed / blocked logins, forbidden pages and visits.
require_once __DIR__ . '/../auth/auth_check.php';

// Setting: keep tried passwords masked (safer) or full
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password_logging') {
    require_csrf('security/index.php');
    $mode = in_list((string)($_POST['mode'] ?? 'masked'), ['masked', 'full'], 'masked');
    save_setting($pdo, 'log_tried_passwords', $mode);
    log_security($pdo, 'setting_changed', $mode === 'full' ? 'warning' : 'info', 'Tried passwords are now recorded: ' . strtoupper($mode));
    flash('success', 'Saved. From now on tried passwords are recorded ' . ($mode === 'full' ? 'IN FULL.' : 'masked.'));
    redirect('security/index.php#passwords');
}

$from = get_date('from', date('Y-m-d', strtotime('-6 days')));
$to = get_date('to', date('Y-m-d'));
$severity = in_list((string)($_GET['severity'] ?? ''), ['danger', 'warning', 'info'], '');
$q = trim((string)($_GET['q'] ?? ''));
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];

$eventNames = [
    'login' => 'Login', 'logout' => 'Logout', 'login_failed' => 'Failed login', 'login_blocked' => 'Login BLOCKED (too many tries)',
    'login_inactive' => 'Switched-off user tried to log in', 'access_denied' => 'Forbidden page', 'delete_denied' => 'Delete without permission',
    'suspicious_input' => 'Hacking attempt (suspicious input)', 'bad_form_token' => 'Expired / repeated / foreign form',
    'not_logged_in' => 'Form sent without login', 'restore_rejected' => 'Dangerous backup file rejected', 'bad_file_request' => 'Request for a forbidden file',
    'restore' => 'Database restored', 'user_added' => 'User added', 'user_changed' => 'User changed', 'user_removed' => 'User removed',
    'password_changed' => 'Password changed', 'setting_changed' => 'Security setting changed', 'password_change_failed' => 'Wrong current password (My Account)',
];

// Summary cards (selected dates)
$count = function (string $where, array $params = []) use ($pdo, $range) {
    return (int)db_value($pdo, "SELECT COUNT(*) FROM security_log WHERE created_at BETWEEN ? AND ? AND $where", array_merge($range, $params));
};
$cards = [
    ['Hacking attempts', $count("event = 'suspicious_input'"), 'bi-bug', 'red'],
    ['Failed logins', $count("event = 'login_failed'"), 'bi-shield-x', 'orange'],
    ['Blocked logins', $count("event = 'login_blocked'"), 'bi-shield-lock', 'red'],
    ['Forbidden pages / deletes', $count("event IN ('access_denied', 'delete_denied')"), 'bi-sign-stop', 'orange'],
    ['Successful logins', $count("event = 'login'"), 'bi-box-arrow-in-right', 'green'],
];

// Which IP addresses cause the most trouble
$topIps = db_all($pdo, "SELECT ip, COUNT(*) AS n, SUM(severity = 'danger') AS dangerous, MAX(created_at) AS last,
        GROUP_CONCAT(DISTINCT NULLIF(username, '') ORDER BY username SEPARATOR ', ') AS usernames
    FROM security_log WHERE created_at BETWEEN ? AND ? AND severity IN ('warning', 'danger')
    GROUP BY ip ORDER BY dangerous DESC, n DESC LIMIT 10", $range);

// Passwords tried in failed / blocked logins
$tried = db_all($pdo, "SELECT created_at, event, username, tried_password, ip, details FROM security_log
    WHERE created_at BETWEEN ? AND ? AND event IN ('login_failed', 'login_blocked') AND tried_password IS NOT NULL
    ORDER BY id DESC LIMIT 100", $range);

// Visits (how many times the system was opened)
[$monthFrom, $monthTo] = period_range('month');
$visitsToday = (int)db_value($pdo, 'SELECT COALESCE(SUM(visits), 0) FROM page_visits WHERE visit_date = ?', [date('Y-m-d')]);
$visitsMonth = (int)db_value($pdo, 'SELECT COALESCE(SUM(visits), 0) FROM page_visits WHERE visit_date BETWEEN ? AND ?', [$monthFrom, $monthTo]);
$visitsRange = (int)db_value($pdo, 'SELECT COALESCE(SUM(visits), 0) FROM page_visits WHERE visit_date BETWEEN ? AND ?', [$from, $to]);
$visitsByUser = db_all($pdo, "SELECT COALESCE(u.username, CONCAT('removed user #', v.user_id)) AS username, SUM(v.visits) AS visits, COUNT(DISTINCT v.visit_date) AS days
    FROM page_visits v LEFT JOIN users u ON u.id = v.user_id WHERE v.visit_date BETWEEN ? AND ? GROUP BY v.user_id ORDER BY visits DESC", [$from, $to]);
$visitsByPage = db_all($pdo, 'SELECT page, SUM(visits) AS visits FROM page_visits WHERE visit_date BETWEEN ? AND ? GROUP BY page ORDER BY visits DESC LIMIT 10', [$from, $to]);
$visitsByDay = db_all($pdo, 'SELECT visit_date, SUM(visits) AS visits FROM page_visits WHERE visit_date BETWEEN ? AND ? GROUP BY visit_date ORDER BY visit_date DESC', [$from, $to]);

// Event list
$where = ['created_at BETWEEN ? AND ?'];
$params = $range;
if ($severity !== '') { $where[] = 'severity = ?'; $params[] = $severity; }
if ($q !== '') { $where[] = '(ip LIKE ? OR username LIKE ? OR details LIKE ? OR event LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
$whereSql = 'WHERE ' . implode(' AND ', $where);
$p = paginate((int)db_value($pdo, "SELECT COUNT(*) FROM security_log $whereSql", $params), 50);
$events = db_all($pdo, "SELECT * FROM security_log $whereSql ORDER BY id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);

if (($_GET['export'] ?? '') === 'csv') {
    $rows = db_all($pdo, "SELECT created_at, severity, event, username, tried_password, ip, page, details, user_agent FROM security_log $whereSql ORDER BY id DESC", $params);
    send_csv('nadiif_security_' . $from . '_to_' . $to . '.csv', ['Time', 'Level', 'Event', 'Username', 'Password tried', 'IP', 'Page', 'Details', 'Browser'], $rows);
}

$pageTitle = 'Security Report';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-shield-check"></i> Security Report</h1>
    <div class="d-flex gap-2 flex-wrap no-print">
        <a class="btn btn-outline-primary" href="activity.php"><i class="bi bi-list-check"></i> Footprints</a>
        <a class="btn btn-outline-primary" href="health.php"><i class="bi bi-heart-pulse"></i> System Health</a>
        <button class="btn btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <a class="btn btn-success" href="?<?= e(http_build_query(['from' => $from, 'to' => $to, 'severity' => $severity, 'q' => $q, 'export' => 'csv'])) ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
    </div>
</div>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-6 col-md-2"><select class="form-select" name="severity"><option value="">All levels</option>
            <?php foreach (['danger' => 'Danger', 'warning' => 'Warning', 'info' => 'Info'] as $k => $l): ?><option value="<?= $k ?>" <?= $severity === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-4"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="IP, username or text"></div>
        <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Show</button></div>
    </div>
</form>

<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $n, $icon, $color]): ?>
        <div class="col-6 col-md-4 col-xl"><div class="card stat-card <?= $color ?> shadow-sm h-100"><div class="card-body d-flex justify-content-between">
            <div><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= $n ?></div></div><i class="bi <?= $icon ?> stat-icon"></i>
        </div></div></div>
    <?php endforeach; ?>
</div>

<div class="section-title" id="passwords">Passwords tried (failed logins)</div>
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
            <thead><tr><th>Time</th><th>Username tried</th><th>Password tried</th><th>IP</th><th>Result</th></tr></thead>
            <?php foreach ($tried as $t): ?>
                <tr class="<?= $t['event'] === 'login_blocked' ? 'table-danger' : '' ?>">
                    <td class="small text-nowrap"><?= show_datetime($t['created_at']) ?></td>
                    <td><?= e($t['username']) ?></td>
                    <td><code><?= e($t['tried_password']) ?></code></td>
                    <td class="small"><?= e($t['ip']) ?></td>
                    <td class="small"><?= $t['event'] === 'login_blocked' ? '<span class="text-danger">BLOCKED</span>' : e($t['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tried): ?><tr><td colspan="5" class="text-center text-muted py-3">No failed logins for these dates.</td></tr><?php endif; ?>
        </table></div></div>
    </div>
    <div class="col-lg-4">
        <form method="post" class="card shadow-sm h-100 no-print"><div class="card-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="password_logging">
            <h3 class="h6">How to record tried passwords</h3>
            <div class="form-check"><input class="form-check-input" type="radio" name="mode" value="masked" id="pm" <?= setting('log_tried_passwords', 'masked') !== 'full' ? 'checked' : '' ?>>
                <label class="form-check-label" for="pm"><b>Masked</b> (recommended), e.g. <code>ad••••56 (8)</code></label></div>
            <div class="form-check mb-2"><input class="form-check-input" type="radio" name="mode" value="full" id="pf" <?= setting('log_tried_passwords', 'masked') === 'full' ? 'checked' : '' ?>>
                <label class="form-check-label" for="pf"><b>Full</b> password as typed</label></div>
            <div class="small text-muted mb-2">Warning: when your own staff make a small typing mistake, their real password (which they may also use for
                Gmail, EVC Plus or the bank) would be saved in full. Masked still shows the length and the first/last letters of a guess.
                Successful logins never record a password.</div>
            <button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
        </div></form>
    </div>
</div>

<div class="section-title">Visits</div>
<div class="row g-3 mb-3">
    <div class="col-4"><div class="card stat-card teal shadow-sm"><div class="card-body"><div class="stat-label">Today</div><div class="stat-value"><?= $visitsToday ?></div></div></div></div>
    <div class="col-4"><div class="card stat-card teal shadow-sm"><div class="card-body"><div class="stat-label">This month</div><div class="stat-value"><?= $visitsMonth ?></div></div></div></div>
    <div class="col-4"><div class="card stat-card teal shadow-sm"><div class="card-body"><div class="stat-label">Selected dates</div><div class="stat-value"><?= $visitsRange ?></div></div></div></div>
    <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-header bg-white"><strong>Visits per user</strong></div>
        <table class="table table-sm mb-0"><thead><tr><th>User</th><th class="text-center">Days</th><th class="money">Pages opened</th></tr></thead>
        <?php foreach ($visitsByUser as $v): ?><tr><td><?= e($v['username']) ?></td><td class="text-center"><?= (int)$v['days'] ?></td><td class="money"><?= (int)$v['visits'] ?></td></tr><?php endforeach; ?>
        </table></div></div>
    <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-header bg-white"><strong>Most opened pages</strong></div>
        <table class="table table-sm mb-0"><?php foreach ($visitsByPage as $v): ?><tr><td class="small"><?= e($v['page']) ?></td><td class="money"><?= (int)$v['visits'] ?></td></tr><?php endforeach; ?></table></div></div>
    <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-header bg-white"><strong>Visits per day</strong></div>
        <table class="table table-sm mb-0"><?php foreach ($visitsByDay as $v): ?><tr><td><?= show_date($v['visit_date']) ?></td><td class="money"><?= (int)$v['visits'] ?></td></tr><?php endforeach; ?></table></div></div>
</div>

<?php if ($topIps): ?>
    <div class="section-title">IP addresses with warnings</div>
    <div class="card shadow-sm mb-3"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>IP address</th><th class="text-center">Warnings</th><th class="text-center">Danger</th><th>Usernames tried</th><th>Last time</th></tr></thead>
        <?php foreach ($topIps as $ip): ?>
            <tr class="<?= $ip['dangerous'] ? 'table-danger' : '' ?>"><td><a href="?<?= e(http_build_query(['from' => $from, 'to' => $to, 'q' => $ip['ip']])) ?>"><?= e($ip['ip']) ?></a></td>
                <td class="text-center"><?= (int)$ip['n'] ?></td><td class="text-center"><?= (int)$ip['dangerous'] ?></td>
                <td class="small"><?= e($ip['usernames']) ?></td><td class="small"><?= show_datetime($ip['last']) ?></td></tr>
        <?php endforeach; ?>
    </table></div></div>
<?php endif; ?>

<div class="section-title">Security events</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Time</th><th>Level</th><th>Event</th><th>Username</th><th>Password tried</th><th>IP</th><th>Page</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev): ?>
                <tr class="<?= $ev['severity'] === 'danger' ? 'table-danger' : ($ev['severity'] === 'warning' ? 'table-warning' : '') ?>">
                    <td class="small text-nowrap"><?= show_datetime($ev['created_at']) ?></td>
                    <td><span class="badge bg-<?= $ev['severity'] === 'danger' ? 'danger' : ($ev['severity'] === 'warning' ? 'warning text-dark' : 'secondary') ?>"><?= e(strtoupper($ev['severity'])) ?></span></td>
                    <td><?= e($eventNames[$ev['event']] ?? $ev['event']) ?></td>
                    <td><?= e($ev['username']) ?></td>
                    <td><?= $ev['tried_password'] !== null ? '<code>' . e($ev['tried_password']) . '</code>' : '' ?></td>
                    <td class="small"><?= e($ev['ip']) ?></td>
                    <td class="small"><?= e($ev['page']) ?></td>
                    <td class="small" style="max-width: 360px; word-break: break-word"><?= e($ev['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$events): ?><tr><td colspan="8" class="text-center text-muted py-4">No security events for these dates.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pagination_links($p) ?>
<p class="small text-muted mt-2">Logins are blocked for 15 minutes after 5 wrong passwords from the same IP address (or 10 for the same username).
    "Hacking attempts" are records of suspicious text that was sent; the system rejects it safely (prepared statements, output escaping), the record lets you see who tried.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
