    </div>
    <footer class="text-center text-muted small py-3 no-print">
        &copy; <?= date('Y') ?> <?= e(setting('business_name')) ?>
    </footer>
</main>
<script src="<?= url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= url('assets/js/script.js') ?>"></script>
<?php if (email_backup_due()): ?>
<!-- Today's email backup is due: send it in the background (the page does not wait) -->
<script>fetch('<?= url('backup/auto.php') ?>', { method: 'POST', credentials: 'same-origin' }).catch(function () {});</script>
<?php endif; ?>
</body>
</html>
