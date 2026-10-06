<?php
// monthly-running/form.php - add or edit a monthly (recurring) running cost.
// Copied to Expenses automatically on its payment date (only once).
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$row = ['cost_month' => (int)date('n'), 'cost_year' => (int)date('Y'), 'category' => 'Rent', 'description' => '', 'amount' => '',
        'payment_date' => date('Y-m-d'), 'payment_method' => 'Cash', 'notes' => ''];
if ($id) {
    $row = db_row($pdo, 'SELECT * FROM monthly_running_costs WHERE id = ?', [$id]);
    if (!$row) {
        flash('danger', 'Record not found.');
        redirect('monthly-running/index.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $row = [
        'cost_month' => (int)($_POST['cost_month'] ?? 0),
        'cost_year' => (int)($_POST['cost_year'] ?? 0),
        'category' => in_list(post_text('category', 50), monthly_running_categories(), 'Other'),
        'description' => post_text('description', 255),
        'amount' => post_money('amount'),
        'payment_date' => post_text('payment_date', 10),
        'payment_method' => in_list(post_text('payment_method', 20), payment_methods(), 'Cash'),
        'notes' => post_text('notes', 2000),
    ];
    if ($row['cost_month'] < 1 || $row['cost_month'] > 12) { $errors[] = 'Please choose a month.'; }
    if ($row['cost_year'] < 2000 || $row['cost_year'] > 2100) { $errors[] = 'Please enter a valid year.'; }
    if ($row['amount'] === null || $row['amount'] <= 0) { $errors[] = 'Please enter an amount greater than 0.'; }
    if (!valid_date($row['payment_date'])) { $errors[] = 'Please enter a valid payment date.'; }

    // Warn about the same cost for the same month twice (e.g. rent paid twice)
    if (!$errors && empty($_POST['confirm_duplicate'])) {
        $same = (int)db_value($pdo, 'SELECT COUNT(*) FROM monthly_running_costs WHERE cost_month = ? AND cost_year = ? AND category = ? AND id <> ?',
            [$row['cost_month'], $row['cost_year'], $row['category'], $id]);
        if ($same > 0) {
            $errors[] = 'A "' . $row['category'] . '" cost is already recorded for ' . month_names()[$row['cost_month']] . ' ' . $row['cost_year']
                      . '. If this is a separate cost, tick the box below and save again.';
            $askDuplicate = true;
        }
    }

    if (!$errors) {
        require_csrf('monthly-running/index.php');
        $pdo->beginTransaction();
        try {
            $values = [$row['cost_month'], $row['cost_year'], $row['category'], $row['description'], $row['amount'], $row['payment_date'], $row['payment_method'], $row['notes']];
            if ($id) {
                db_query($pdo, 'UPDATE monthly_running_costs SET cost_month = ?, cost_year = ?, category = ?, description = ?, amount = ?, payment_date = ?,
                                payment_method = ?, notes = ? WHERE id = ?', array_merge($values, [$id]));
            } else {
                db_query($pdo, 'INSERT INTO monthly_running_costs (cost_month, cost_year, category, description, amount, payment_date, payment_method, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $values);
                $id = (int)$pdo->lastInsertId();
            }
            $period = month_names()[$row['cost_month']] . ' ' . $row['cost_year'];
            ledger_expense_save($pdo, 'monthly_running', $id, $row['payment_date'], $row['category'],
                'Monthly running (' . $period . '): ' . ($row['description'] !== '' ? $row['description'] : $row['category']),
                $row['amount'], $row['payment_method'], '', $row['notes']);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        flash('success', 'Expense recorded successfully.');
        redirect('monthly-running/index.php?year=' . $row['cost_year']);
    }
}

$pageTitle = $id ? 'Edit Monthly Running Cost' : 'Add Monthly Running Cost';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-calendar-month"></i> <?= $id ? 'Edit Monthly Running Cost' : 'Add Monthly Running Cost' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 760px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-6"><label class="form-label">Month *</label><select class="form-select" name="cost_month">
            <?php foreach (month_names() as $n => $name): ?><option value="<?= $n ?>" <?= (int)$row['cost_month'] === $n ? 'selected' : '' ?>><?= $name ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-6"><label class="form-label">Year *</label><input class="form-control" type="number" min="2000" max="2100" name="cost_year" value="<?= (int)$row['cost_year'] ?>" required></div>
        <div class="col-6"><label class="form-label">Category</label><select class="form-select" name="category"><?= options(monthly_running_categories(), $row['category']) ?></select></div>
        <div class="col-6"><label class="form-label">Amount *</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" value="<?= e($row['amount'] ?? '') ?>" required></div>
        <div class="col-12"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($row['description']) ?>" maxlength="255"></div>
        <div class="col-6"><label class="form-label">Payment Date *</label><input class="form-control" type="date" name="payment_date" value="<?= e($row['payment_date']) ?>" required></div>
        <div class="col-6"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><?= options(payment_methods(), $row['payment_method']) ?></select></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($row['notes']) ?></textarea></div>
        <?php if (!empty($askDuplicate)): ?>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_duplicate" value="1" id="dup">
                <label class="form-check-label" for="dup">This is a separate cost for the same month</label></div></div>
        <?php endif; ?>
        <div class="col-12 small text-muted">In Profit &amp; Loss, this cost is counted on its <b>payment date</b>.</div>
        <div class="col-12"><button class="btn btn-danger btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save</button></div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
