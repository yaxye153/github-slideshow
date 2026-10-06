<?php
// backup/email.php - EMAIL BACKUP (Gmail)
// Sends a backup of the whole database to Gmail once every day.
require_once __DIR__ . '/../auth/auth_check.php';

$config = mail_config();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $new = [
            'enabled' => !empty($_POST['enabled']),
            'gmail' => strtolower(post_text('gmail', 100)),
            // Empty box = keep the saved App Password
            'app_password' => trim((string)($_POST['app_password'] ?? '')) !== '' ? str_replace(' ', '', trim((string)$_POST['app_password'])) : $config['app_password'],
            'send_to' => strtolower(post_text('send_to', 100)),
            'send_hour' => max(0, min(23, (int)($_POST['send_hour'] ?? 20))),
        ];
        $new = array_merge($config, $new);   // keep hand-edited extras (smtp_host / smtp_port)
        if ($new['gmail'] !== '' && !filter_var($new['gmail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid Gmail address.';
        }
        if ($new['send_to'] !== '' && !filter_var($new['send_to'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid "Send backup to" email address.';
        }
        if ($new['app_password'] !== '' && !preg_match('/^[A-Za-z]{16}$/', $new['app_password'])) {
            $errors[] = 'The App Password must be the 16 letters Google gave you (spaces are fine).';
        }
        if ($new['enabled'] && ($new['gmail'] === '' || $new['app_password'] === '')) {
            $errors[] = 'To switch on the daily email backup, enter the Gmail address and App Password.';
        }
        if (!$errors) {
            require_csrf('backup/email.php');
            try {
                save_mail_config($new);
                flash('success', 'Email backup settings saved.' . ($new['enabled'] ? ' Click "Send test now" to check that it works.' : ''));
            } catch (Throwable $e) {
                flash('danger', 'Could not save the settings. Check that the config folder can be written to.');
            }
            redirect('backup/email.php');
        }
        $config = $new;
    }

    if ($action === 'test') {
        require_csrf('backup/email.php');
        set_time_limit(300);
        [$sent, $message] = run_email_backup($pdo, true, true);
        flash($sent ? 'success' : 'danger', $sent ? 'Test successful: ' . $message . ' Check the inbox.' : 'Sending failed: ' . $message);
        redirect('backup/email.php');
    }
}

$lastMessage = setting('email_backup_last_message');
$lastSuccess = setting('email_backup_last_success');

$pageTitle = 'Email Backup';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-envelope-at"></i> Email Backup (Gmail)</h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Backup &amp; Restore</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" class="card shadow-sm">
            <div class="card-header bg-white"><strong>Settings</strong></div>
            <div class="card-body">
                <?= csrf_field() ?><input type="hidden" name="action" value="save">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="enabled" <?= $config['enabled'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="enabled">Send a backup to Gmail every day</label>
                </div>
                <div class="mb-3">
                    <label class="form-label">Gmail address (sends the email)</label>
                    <input class="form-control" type="email" name="gmail" value="<?= e($config['gmail']) ?>" placeholder="yourshop@gmail.com" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Gmail App Password <?= $config['app_password'] !== '' ? '<span class="badge bg-success">saved</span>' : '' ?></label>
                    <input class="form-control" type="password" name="app_password" placeholder="<?= $config['app_password'] !== '' ? 'Leave empty to keep the saved one' : 'abcd efgh ijkl mnop' ?>" autocomplete="new-password">
                    <div class="form-text">Not your normal Gmail password. See the steps on the right.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Send backup to <small class="text-muted">(empty = same Gmail)</small></label>
                    <input class="form-control" type="email" name="send_to" value="<?= e($config['send_to']) ?>" placeholder="owner@gmail.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Send after (time of day)</label>
                    <select class="form-select" name="send_hour" style="max-width: 160px">
                        <?php for ($h = 0; $h < 24; $h++): ?><option value="<?= $h ?>" <?= (int)$config['send_hour'] === $h ? 'selected' : '' ?>><?= sprintf('%02d:00', $h) ?></option><?php endfor; ?>
                    </select>
                    <div class="form-text">Choose a time when the shop computer is on, for example near closing time.</div>
                </div>
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save</button>
            </div>
        </form>

        <div class="card shadow-sm mt-3"><div class="card-body">
            <h2 class="h6">Status</h2>
            <p class="mb-1">Last successful email: <b><?= $lastSuccess ? show_datetime($lastSuccess) : 'never' ?></b></p>
            <?php if ($lastMessage !== ''): ?>
                <p class="mb-2 small <?= strpos($lastMessage, 'FAILED') === 0 ? 'text-danger' : 'text-success' ?>"><?= e($lastMessage) ?></p>
            <?php endif; ?>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="test">
                <button class="btn btn-success" type="submit" <?= $config['gmail'] === '' || $config['app_password'] === '' ? 'disabled' : '' ?>><i class="bi bi-send"></i> Send test now</button>
                <small class="text-muted ms-2">Creates a backup and emails it right away.</small>
            </form>
        </div></div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm"><div class="card-body small">
            <h2 class="h6">How to get a Gmail App Password</h2>
            <ol class="ps-3">
                <li>Open <b>myaccount.google.com</b> with the Gmail account.</li>
                <li><b>Security</b> &rarr; turn on <b>2-Step Verification</b>.</li>
                <li>Search for <b>App passwords</b>, type a name (e.g. <i>Nadiif Laundry</i>) and click <b>Create</b>.</li>
                <li>Copy the <b>16 letters</b> and paste them here.</li>
            </ol>
            <h2 class="h6 mt-3">How the daily sending works</h2>
            <ul class="ps-3 mb-2">
                <li>After the chosen time, the first page anyone opens in the system sends today's backup in the background (once per day).</li>
                <li>If the computer is <b>off</b> or the system is not opened that day, no email is sent that day.
                    For sending even when nobody uses the system, set up <b>Windows Task Scheduler</b> (see README, file <code>backup/daily-email-backup.bat</code>).</li>
                <li>The file is compressed (<code>.sql.gz</code>). Open it with 7-Zip/WinRAR to get the <code>.sql</code> file for <b>UPLOAD BACKUP</b>.</li>
                <li>The last 14 automatic backups are also kept on this computer.</li>
            </ul>
            <div class="alert alert-warning mb-0 p-2">The backup contains <b>all business data</b>. Send it only to an email account you control and protect it with 2-Step Verification.</div>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
