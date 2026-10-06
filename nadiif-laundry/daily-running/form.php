<?php
// daily-running/form.php - add or edit an everyday running cost.
// Each cost is copied to Expenses automatically (only once).
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$row = ['cost_date' => date('Y-m-d'), 'category' => 'Electricity', 'description' => '', 'amount' => '', 'payment_method' => 'Cash', 'notes' => ''];
if ($id) {
    $row = db_row($pdo, 'SELECT * FROM daily_running_costs WHERE id = ?', [$id]);
    if (!$row) {
        flash('danger', 'Record not found.');
        redirect('daily-running/index.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $row = [
        'cost_date' => post_text('cost_date', 10),
        'category' => in_list(post_text('category', 50), daily_running_categories(), 'Other'),
        'description' => post_text('description', 255),
        'amount' => post_money('amount'),
        'payment_method' => in_list(post_text('payment_method', 20), payment_methods(), 'Cash'),
        'notes' => post_text('notes', 2000),
    ];
    if (!valid_date($row['cost_date'])) { $errors[] = 'Please enter a valid date.'; }
    if ($row['amount'] === null || $row['amount'] <= 0) { $errors[] = 'Please enter an amount greater than 0.'; }

    if (!$errors) {
        require_csrf('daily-running/index.php');
        $pdo->beginTransaction();
        try {
            $values = [$row['cost_date'], $row['category'], $row['description'], $row['amount'], $row['payment_method'], $row['notes']];
            if ($id) {
                db_query($pdo, 'UPDATE daily_running_costs SET cost_date = ?, category = ?, description = ?, amount = ?, payment_method = ?, notes = ? WHERE id = ?',
                    array_merge($values, [$id]));
            } else {
                db_query($pdo, 'INSERT INTO daily_running_costs (cost_date, category, description, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)', $values);
                $id = (int)$pdo->lastInsertId();
            }
            // Copy to expenses
            ledger_expense_save($pdo, 'daily_running', $id, $row['cost_date'], $row['category'],
                'Daily running: ' . ($row['description'] !== '' ? $row['description'] : $row['category']), $row['amount'], $row['payment_method'], '', $row['notes']);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        flash('success', 'Expense recorded successfully.');
        redirect('daily-running/index.php');
    }
}

$pageTitle = $id ? 'Edit Daily Running Cost' : 'Add Daily Running Cost';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-calendar-day"></i> <?= $id ? 'Edit Daily Running Cost' : 'Add Daily Running Cost' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 760px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-6"><label class="form-label">Date *</label><input class="form-control" type="date" name="cost_date" value="<?= e($row['cost_date']) ?>" required></div>
        <div class="col-6"><label class="form-label">Category</label><select class="form-select" name="category"><?= options(daily_running_categories(), $row['category']) ?></select></div>
        <div class="col-12"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e($row['description']) ?>" maxlength="255"></div>
        <div class="col-6"><label class="form-label">Amount *</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" value="<?= e($row['amount'] ?? '') ?>" required></div>
        <div class="col-6"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><?= options(payment_methods(), $row['payment_method']) ?></select></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($row['notes']) ?></textarea></div>
        <div class="col-12"><button class="btn btn-danger btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save</button></div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
