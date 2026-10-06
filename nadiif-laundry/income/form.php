<?php
// income/form.php - add or edit income that is NOT an order payment
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$row = ['income_date' => date('Y-m-d'), 'income_type' => 'Other Income', 'description' => '', 'amount' => '',
        'payment_method' => 'Cash', 'reference' => '', 'notes' => ''];

if ($id) {
    $row = db_row($pdo, "SELECT * FROM income WHERE id = ? AND source = 'manual'", [$id]);
    if (!$row) {
        flash('danger', 'This income record can not be edited here (automatic records are edited in their own page).');
        redirect('income/index.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $row = [
        'income_date' => post_text('income_date', 10),
        'income_type' => in_list(post_text('income_type', 50), manual_income_types(), 'Other Income'),
        'description' => post_text('description', 255),
        'amount' => post_money('amount'),
        'payment_method' => in_list(post_text('payment_method', 20), payment_methods(), 'Cash'),
        'reference' => post_text('reference', 100),
        'notes' => post_text('notes', 2000),
    ];
    if (!valid_date($row['income_date'])) { $errors[] = 'Please enter a valid date.'; }
    if ($row['amount'] === null || $row['amount'] <= 0) { $errors[] = 'Please enter an amount greater than 0.'; }
    if ($row['description'] === '') { $errors[] = 'Please enter a description.'; }

    if (!$errors) {
        require_csrf('income/index.php');
        $values = [$row['income_date'], $row['income_type'], $row['description'], $row['amount'], $row['payment_method'], $row['reference'], $row['notes']];
        if ($id) {
            db_query($pdo, "UPDATE income SET income_date = ?, income_type = ?, description = ?, amount = ?, payment_method = ?, reference = ?, notes = ?
                            WHERE id = ? AND source = 'manual'", array_merge($values, [$id]));
        } else {
            db_query($pdo, "INSERT INTO income (income_date, income_type, description, amount, payment_method, reference, notes, source)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'manual')", $values);
        }
        flash('success', 'Income recorded successfully.');
        redirect('income/index.php');
    }
}

$pageTitle = $id ? 'Edit Income' : 'Add Income';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><?= $id ? 'Edit Income' : 'Add Other Income' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="alert alert-info small" style="max-width: 760px">
    Laundry order payments are added to income automatically. <b>Do not</b> enter them here, or they will be counted twice.
    To record an order payment use <a href="../payments/add.php">Add Payment</a>.
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 760px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-6"><label class="form-label">Date *</label><input class="form-control" type="date" name="income_date" value="<?= e($row['income_date']) ?>" required></div>
        <div class="col-6"><label class="form-label">Income Type</label><select class="form-select" name="income_type"><?= options(manual_income_types(), $row['income_type']) ?></select></div>
        <div class="col-12"><label class="form-label">Description *</label><input class="form-control" name="description" value="<?= e($row['description']) ?>" required maxlength="255"></div>
        <div class="col-6"><label class="form-label">Amount *</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" value="<?= e($row['amount'] ?? '') ?>" required></div>
        <div class="col-6"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><?= options(payment_methods(), $row['payment_method']) ?></select></div>
        <div class="col-12"><label class="form-label">Reference</label><input class="form-control" name="reference" value="<?= e($row['reference']) ?>" maxlength="100"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($row['notes']) ?></textarea></div>
        <div class="col-12"><button class="btn btn-success btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Income</button></div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
