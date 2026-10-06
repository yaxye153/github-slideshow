<?php
// stock/use.php - USE STOCK (or throw away expired / damaged stock).
// The quantity is taken from the purchase that expires FIRST, so old stock is used first.
require_once __DIR__ . '/../auth/auth_check.php';

$reasons = ['Used', 'Expired', 'Damaged', 'Lost'];
$batchId = (int)($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0);
$batch = $batchId ? db_row($pdo, 'SELECT b.*, i.name, i.unit FROM stock_batches b JOIN stock_items i ON i.id = b.item_id WHERE b.id = ?', [$batchId]) : null;

$move = ['item_id' => (int)($_GET['item_id'] ?? ($batch['item_id'] ?? 0)), 'quantity' => $batch ? qty($batch['qty_left']) : '',
         'move_date' => date('Y-m-d'), 'reason' => in_list((string)($_GET['reason'] ?? 'Used'), $reasons, 'Used'), 'notes' => ''];
$items = db_all($pdo, 'SELECT i.id, i.name, i.unit, COALESCE(SUM(b.qty_left), 0) AS qty FROM stock_items i
    LEFT JOIN stock_batches b ON b.item_id = i.id GROUP BY i.id HAVING qty > 0 ORDER BY i.name');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qtyRaw = str_replace(',', '', trim((string)($_POST['quantity'] ?? '')));
    $move = [
        'item_id' => (int)($_POST['item_id'] ?? 0),
        'quantity' => $qtyRaw,
        'move_date' => post_text('move_date', 10),
        'reason' => in_list(post_text('reason', 30), $reasons, 'Used'),
        'notes' => post_text('notes', 255),
    ];
    if (!valid_date($move['move_date'])) { $errors[] = 'Please enter a valid date.'; }
    if (!is_numeric($qtyRaw) || (float)$qtyRaw <= 0) { $errors[] = 'Please enter a quantity greater than 0.'; }

    if (!$errors) {
        require_csrf('stock/index.php');
        $need = round((float)$qtyRaw, 3);
        $pdo->beginTransaction();
        try {
            // Lock the batches so two people can not use the same stock at the same time
            if ($batch) {
                $batches = db_all($pdo, 'SELECT id, qty_left FROM stock_batches WHERE id = ? AND qty_left > 0 FOR UPDATE', [$batch['id']]);
                $move['item_id'] = (int)$batch['item_id'];
            } else {
                // Expiring first, then oldest purchase first
                $batches = db_all($pdo, 'SELECT id, qty_left FROM stock_batches WHERE item_id = ? AND qty_left > 0
                                         ORDER BY expiry_date IS NULL, expiry_date, purchase_date, id FOR UPDATE', [$move['item_id']]);
            }
            $available = array_sum(array_map('floatval', array_column($batches, 'qty_left')));
            if ($need > $available + 0.0005) {
                $pdo->rollBack();
                $errors[] = 'Only ' . qty($available) . ' is in stock. You can not use more than that.';
            } else {
                $left = $need;
                foreach ($batches as $bt) {
                    if ($left <= 0) {
                        break;
                    }
                    $take = min($left, (float)$bt['qty_left']);
                    db_query($pdo, 'UPDATE stock_batches SET qty_left = qty_left - ? WHERE id = ?', [$take, $bt['id']]);
                    db_query($pdo, 'INSERT INTO stock_moves (item_id, batch_id, move_date, quantity, reason, notes, created_by_name) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$move['item_id'], $bt['id'], $move['move_date'], $take, $move['reason'], $move['notes'], $CURRENT_USER['username']]);
                    $left = round($left - $take, 3);
                }
                $pdo->commit();
                flash('success', 'Stock updated: ' . qty($need) . ' ' . strtolower($move['reason']) . '.');
                redirect('stock/index.php');
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
    }
}

$pageTitle = 'Use Stock';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-box-arrow-up"></i> <?= $batch ? 'Throw Away Stock' : 'Use Stock' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Stock</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<?php if ($batch): ?>
    <div class="alert alert-warning"><b><?= e($batch['name']) ?></b> bought <?= show_date($batch['purchase_date']) ?>, expiry <?= show_date($batch['expiry_date']) ?>:
        <?= qty($batch['qty_left']) ?> <?= e($batch['unit']) ?> left.</div>
<?php endif; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 760px">
    <?= csrf_field() ?><?php if ($batch): ?><input type="hidden" name="batch_id" value="<?= $batch['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <?php if (!$batch): ?>
            <div class="col-md-6"><label class="form-label">Item *</label>
                <select class="form-select" name="item_id" required><option value="">-- Choose item --</option>
                    <?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>" <?= (int)$move['item_id'] === (int)$it['id'] ? 'selected' : '' ?>><?= e($it['name'] . ' - ' . qty($it['qty']) . ' ' . $it['unit'] . ' in stock') ?></option><?php endforeach; ?>
                </select></div>
        <?php endif; ?>
        <div class="col-6 col-md-3"><label class="form-label">Quantity *</label><input class="form-control" type="number" step="0.001" min="0.001" name="quantity" value="<?= e($move['quantity']) ?>" required></div>
        <div class="col-6 col-md-3"><label class="form-label">Date</label><input class="form-control" type="date" name="move_date" value="<?= e($move['move_date']) ?>" required></div>
        <div class="col-6 col-md-3"><label class="form-label">Reason</label><select class="form-select" name="reason"><?= options($reasons, $move['reason']) ?></select></div>
        <div class="col-md-9"><label class="form-label">Notes</label><input class="form-control" name="notes" value="<?= e($move['notes']) ?>" maxlength="255"></div>
        <div class="col-12 small text-muted">The cost was already counted as an expense when the stock was bought, so using it does not add a new expense.</div>
        <div class="col-12"><button class="btn btn-warning btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save</button></div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
