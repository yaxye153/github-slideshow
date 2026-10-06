<?php
// security/activity.php - FOOTPRINTS (admin only)
// Every form saved in the system: who, what, when, from which IP, and the result.
require_once __DIR__ . '/../auth/auth_check.php';

$from = get_date('from', date('Y-m-d', strtotime('-6 days')));
$to = get_date('to', date('Y-m-d'));
$userId = (int)($_GET['user_id'] ?? 0);
$outcome = in_list((string)($_GET['outcome'] ?? ''), ['success', 'failed', 'error'], '');
$q = trim((string)($_GET['q'] ?? ''));

$where = ['created_at BETWEEN ? AND ?'];
$params = [$from . ' 00:00:00', $to . ' 23:59:59'];
if ($userId) { $where[] = 'user_id = ?'; $params[] = $userId; }
if ($outcome !== '') { $where[] = 'outcome = ?'; $params[] = $outcome; }
if ($q !== '') { $where[] = '(action LIKE ? OR details LIKE ? OR page LIKE ? OR username LIKE ? OR ip LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
$whereSql = 'WHERE ' . implode(' AND ', $where);

if (($_GET['export'] ?? '') === 'csv') {
    $rows = db_all($pdo, "SELECT created_at, username, ip, action, record_id, outcome, page, details FROM activity_log $whereSql ORDER BY id DESC", $params);
    send_csv('nadiif_footprints_' . $from . '_to_' . $to . '.csv', ['Time', 'User', 'IP', 'Action', 'Record ID', 'Result', 'Page', 'Details'], $rows);
}

$p = paginate((int)db_value($pdo, "SELECT COUNT(*) FROM activity_log $whereSql", $params), 50);
$rows = db_all($pdo, "SELECT * FROM activity_log $whereSql ORDER BY id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);
$byUser = db_all($pdo, "SELECT username, COUNT(*) AS n, SUM(action LIKE '%DELETE%') AS deletes FROM activity_log $whereSql GROUP BY username ORDER BY n DESC", $params);
$users = db_all($pdo, 'SELECT id, username FROM users ORDER BY username');

$pageTitle = 'Footprints';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-list-check"></i> Footprints (all events)</h1>
    <div class="d-flex gap-2 flex-wrap no-print">
        <a class="btn btn-outline-primary" href="index.php"><i class="bi bi-shield-check"></i> Security Report</a>
        <button class="btn btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <a class="btn btn-success" href="?<?= e(http_build_query(['from' => $from, 'to' => $to, 'user_id' => $userId ?: '', 'outcome' => $outcome, 'q' => $q, 'export' => 'csv'])) ?>"><i class="bi bi-filetype-csv"></i> CSV</a>
    </div>
</div>
<p class="text-muted small">Every save, change, payment, delete, login and logout is written here automatically. Passwords are never written.</p>

<form class="card card-body shadow-sm mb-3 filter-form" method="get">
    <div class="row g-2">
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-6 col-md-2"><select class="form-select" name="user_id"><option value="">All users</option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['username']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><select class="form-select" name="outcome"><option value="">All results</option><?= options(['success', 'failed', 'error'], $outcome) ?></select></div>
        <div class="col-12 col-md-2"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search (e.g. DELETE)"></div>
        <div class="col-12 col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Show</button></div>
    </div>
</form>

<?php if ($byUser): ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($byUser as $b): ?>
            <span class="badge bg-light text-dark border p-2"><?= e($b['username'] ?? '-') ?>: <?= (int)$b['n'] ?> actions<?= $b['deletes'] ? ', <span class="text-danger">' . (int)$b['deletes'] . ' deletes</span>' : '' ?></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Record</th><th>Result</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= strpos($r['action'], 'DELETE') !== false ? 'table-warning' : '' ?>">
                    <td class="small text-nowrap"><?= show_datetime($r['created_at']) ?></td>
                    <td><?= e($r['username']) ?></td>
                    <td><?= e($r['action']) ?></td>
                    <td class="small"><?= $r['record_id'] ? '#' . (int)$r['record_id'] : '' ?></td>
                    <td><span class="badge bg-<?= $r['outcome'] === 'success' ? 'success' : 'danger' ?>"><?= e($r['outcome']) ?></span></td>
                    <td class="small" style="max-width: 420px; word-break: break-word"><?= e($r['details']) ?></td>
                    <td class="small"><?= e($r['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No events for these dates.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= pagination_links($p) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
