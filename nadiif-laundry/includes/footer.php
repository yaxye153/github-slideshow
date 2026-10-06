    </div>
    <footer class="text-center text-muted small py-3 no-print">
        &copy; <?= date('Y') ?> <?= e(setting('business_name')) ?>
    </footer>
</main>
<script src="<?= url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('assets/js/script.js') ?>"></script>
<?php if (!empty($navStockAlerts)): ?>
<!-- Stock alarm: pop-up on the computer (only if the user allowed notifications on the Stock page) -->
<script>
(function () {
    try {
        if (!('Notification' in window) || Notification.permission !== 'granted' || sessionStorage.getItem('stockAlarmShown')) { return; }
        sessionStorage.setItem('stockAlarmShown', '1');
        var n = new Notification(<?= json_encode(setting('business_name') . ' - stock alarm') ?>, { body: <?= json_encode($navStockAlerts . ' stock item(s) are low, expired or expiring soon.') ?> });
        n.onclick = function () { window.focus(); location.href = <?= json_encode(url('stock/index.php')) ?>; };
    } catch (e) {}
})();
</script>
<?php endif; ?>
<?php if (email_backup_due() || setting('last_auto_local_backup') !== date('Y-m-d')): ?>
<!-- Daily tasks are due (local backup / email backup): run them in the background (the page does not wait) -->
<script>fetch('<?= url('backup/auto.php') ?>', { method: 'POST', credentials: 'same-origin' }).catch(function () {});</script>
<?php endif; ?>
</body>
</html>
