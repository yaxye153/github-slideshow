<?php
// expenses/form.php - add or edit an expense entered by hand.
// Salaries, delivery costs and running costs are added automatically
// from their own pages, so they are not entered here.
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$row = ['expense_date' => date('Y-m-d'), 'category' => 'Detergent', 'description' => '', 'amount' => '',
        'payment_method' => 'Cash', 'reference' => '', 'notes' => ''];

if ($id) {
    $row = db_row($pdo, "SELECT * FROM expenses WHERE id = ? AND source = 'manual'", [$id]);
    if (!$row) {
        flash('danger', 'This expense can not be edited here (automatic records are edited in their own page).');
        redirect('expenses/index.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $row = [
        'expense_date' => post_text('expense_date', 10),
        'category' => in_list(post_text('category', 50), manual_expense_categories(), 'Other'),
        'description' => post_text('description', 255),
        'amount' => post_money('amount'),
        'payment_method' => in_list(post_text('payment_method', 20), payment_methods(), 'Cash'),
        'reference' => post_text('reference', 100),
        'notes' => post_text('notes', 2000),
    ];
    if (!valid_date($row['expense_date'])) { $errors[] = 'Please enter a valid date.'; }
    if ($row['amount'] === null || $row['amount'] <= 0) { $errors[] = 'Please enter an amount greater than 0.'; }
    if ($row['description'] === '') { $errors[] = 'Please enter a description.'; }

    if (!$errors) {
        require_csrf('expenses/index.php');
        // Save expense
        $values = [$row['expense_date'], $row['category'], $row['description'], $row['amount'], $row['payment_method'], $row['reference'], $row['notes']];
        if ($id) {
            db_query($pdo, "UPDATE expenses SET expense_date = ?, category = ?, description = ?, amount = ?, payment_method = ?, reference = ?, notes = ?
                            WHERE id = ? AND source = 'manual'", array_merge($values, [$id]));
        } else {
            db_query($pdo, "INSERT INTO expenses (expense_date, category, description, amount, payment_method, reference, notes, source)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'manual')", $values);
        }
        flash('success', 'Expense recorded successfully.');
        redirect('expenses/index.php');
    }
}

$pageTitle = $id ? 'Edit Expense' : 'Add Expense';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><?= $id ? 'Edit Expense' : 'Add Expense' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="alert alert-info small" style="max-width: 760px">
    Do not enter here: <b>salaries</b> (use <a href="../salaries/index.php">Salaries</a>), <b>delivery costs</b> (use <a href="../delivery/index.php">Delivery</a>),
    or costs already entered in <a href="../daily-running/index.php">Daily Running</a> / <a href="../monthly-running/index.php">Monthly Running</a>.
    Those are added to expenses automatically, so entering them again would count them twice.
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 760px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-6"><label class="form-label">Date *</label><input class="form-control" type="date" name="expense_date" value="<?= e($row['expense_date']) ?>" required></div>
        <div class="col-6"><label class="form-label">Category</label><select class="form-select" name="category"><?= options(manual_expense_categories(), $row['category']) ?></select></div>
        <div class="col-12"><label class="form-label">Description *</label><input class="form-control" name="description" value="<?= e($row['description']) ?>" required maxlength="255"></div>
        <div class="col-6"><label class="form-label">Amount *</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" value="<?= e($row['amount'] ?? '') ?>" required></div>
        <div class="col-6"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><?= options(payment_methods(), $row['payment_method']) ?></select></div>
        <div class="col-12"><label class="form-label">Reference</label><input class="form-control" name="reference" value="<?= e($row['reference']) ?>" maxlength="100"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($row['notes']) ?></textarea></div>
        <div class="col-12"><button class="btn btn-danger btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Expense</button></div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
