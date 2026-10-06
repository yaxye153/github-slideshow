<?php
// delivery/form.php - record (or edit) a delivery.
// Delivery cost  -> added to Expenses (when "business expense" is ticked)
// Delivery income -> added to Income  (when payment status is Paid)
// Delivery Profit = Delivery Income - Delivery Cost
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$orders = db_all($pdo, "SELECT o.id, o.order_number, o.customer_id, o.delivery_address, c.full_name, c.address
                        FROM orders o JOIN customers c ON c.id = o.customer_id
                        WHERE o.status <> 'Cancelled' ORDER BY o.id DESC LIMIT 500");
$customers = db_all($pdo, 'SELECT id, full_name, phone FROM customers ORDER BY full_name');

$d = ['order_id' => (int)($_GET['order_id'] ?? 0), 'customer_id' => 0, 'delivery_person' => '', 'delivery_date' => date('Y-m-d'),
      'delivery_address' => '', 'delivery_cost' => '', 'cost_is_expense' => 1, 'delivery_income' => '', 'payment_status' => 'Unpaid',
      'payment_method' => 'Cash', 'notes' => ''];

if ($id) {
    $d = db_row($pdo, 'SELECT * FROM deliveries WHERE id = ?', [$id]);
    if (!$d) {
        flash('danger', 'Delivery not found.');
        redirect('delivery/index.php');
    }
} elseif ($d['order_id']) {
    // Fill customer and address from the order
    $o = db_row($pdo, 'SELECT o.customer_id, o.delivery_address, c.address FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?', [$d['order_id']]);
    if ($o) {
        $d['customer_id'] = (int)$o['customer_id'];
        $d['delivery_address'] = $o['delivery_address'] ?: (string)$o['address'];
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [
        'order_id' => (int)($_POST['order_id'] ?? 0),
        'customer_id' => (int)($_POST['customer_id'] ?? 0),
        'delivery_person' => post_text('delivery_person', 100),
        'delivery_date' => post_text('delivery_date', 10),
        'delivery_address' => post_text('delivery_address', 255),
        'delivery_cost' => post_money('delivery_cost'),
        'cost_is_expense' => isset($_POST['cost_is_expense']) ? 1 : 0,
        'delivery_income' => post_money('delivery_income'),
        'payment_status' => in_list(post_text('payment_status', 10), ['Paid', 'Unpaid'], 'Unpaid'),
        'payment_method' => in_list(post_text('payment_method', 20), payment_methods(), 'Cash'),
        'notes' => post_text('notes', 2000),
    ];

    // Order and customer are optional, but must exist if chosen
    $order = $d['order_id'] ? db_row($pdo, 'SELECT id, order_number, customer_id FROM orders WHERE id = ?', [$d['order_id']]) : null;
    if ($d['order_id'] && !$order) { $errors[] = 'The chosen order does not exist.'; }
    if ($order && !$d['customer_id']) { $d['customer_id'] = (int)$order['customer_id']; }
    $customer = $d['customer_id'] ? db_row($pdo, 'SELECT id, full_name FROM customers WHERE id = ?', [$d['customer_id']]) : null;
    if ($d['customer_id'] && !$customer) { $errors[] = 'The chosen customer does not exist.'; }

    if (!valid_date($d['delivery_date'])) { $errors[] = 'Please enter a valid delivery date.'; }
    if ($d['delivery_cost'] === null || $d['delivery_cost'] < 0) { $errors[] = 'Please enter a valid delivery cost (0 or more).'; }
    if ($d['delivery_income'] === null || $d['delivery_income'] < 0) { $errors[] = 'Please enter a valid delivery income (0 or more).'; }

    if (!$errors) {
        require_csrf('delivery/index.php');
        $values = [$d['order_id'] ?: null, $d['customer_id'] ?: null, $d['delivery_person'], $d['delivery_date'], $d['delivery_address'],
                   $d['delivery_cost'], $d['cost_is_expense'], $d['delivery_income'], $d['payment_status'], $d['payment_method'], $d['notes']];

        $pdo->beginTransaction();
        try {
            if ($id) {
                db_query($pdo, 'UPDATE deliveries SET order_id = ?, customer_id = ?, delivery_person = ?, delivery_date = ?, delivery_address = ?,
                                delivery_cost = ?, cost_is_expense = ?, delivery_income = ?, payment_status = ?, payment_method = ?, notes = ? WHERE id = ?',
                    array_merge($values, [$id]));
            } else {
                db_query($pdo, 'INSERT INTO deliveries (order_id, customer_id, delivery_person, delivery_date, delivery_address, delivery_cost,
                                cost_is_expense, delivery_income, payment_status, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $values);
                $id = (int)$pdo->lastInsertId();
            }

            $label = 'Delivery #' . $id . ($order ? ' - order ' . $order['order_number'] : '') . ($customer ? ' - ' . $customer['full_name'] : '');

            // Delivery cost -> expenses (only once, linked by delivery id)
            if ($d['cost_is_expense'] && $d['delivery_cost'] > 0) {
                ledger_expense_save($pdo, 'delivery', $id, $d['delivery_date'], 'Delivery', $label, $d['delivery_cost'], $d['payment_method'],
                    $order['order_number'] ?? '', $d['delivery_person']);
            } else {
                ledger_expense_delete($pdo, 'delivery', $id);
            }

            // Delivery income -> income (only when the customer has paid it)
            if ($d['payment_status'] === 'Paid' && $d['delivery_income'] > 0) {
                ledger_income_save($pdo, 'delivery', $id, $d['delivery_date'], 'Delivery Income', $label, $d['delivery_income'], $d['payment_method'],
                    $order['order_number'] ?? '');
            } else {
                ledger_income_delete($pdo, 'delivery', $id);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        flash('success', 'Delivery saved successfully.');
        redirect('delivery/index.php');
    }
}

$pageTitle = $id ? 'Edit Delivery' : 'Record Delivery';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-truck"></i> <?= $id ? 'Edit Delivery' : 'Record Delivery' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 860px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Order <small class="text-muted">(optional)</small></label>
            <select class="form-select" name="order_id">
                <option value="">-- No order --</option>
                <?php foreach ($orders as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= (int)$d['order_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['order_number'] . ' - ' . $o['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Customer <small class="text-muted">(filled from the order if empty)</small></label>
            <select class="form-select" name="customer_id">
                <option value="">-- Choose customer --</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int)$d['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['full_name'] . ' - ' . $c['phone']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">Delivery Person</label><input class="form-control" name="delivery_person" value="<?= e($d['delivery_person']) ?>" maxlength="100"></div>
        <div class="col-md-6"><label class="form-label">Delivery Date *</label><input class="form-control" type="date" name="delivery_date" value="<?= e($d['delivery_date']) ?>" required></div>
        <div class="col-12"><label class="form-label">Delivery Address</label><input class="form-control" name="delivery_address" value="<?= e($d['delivery_address']) ?>" maxlength="255"></div>

        <div class="col-6 col-md-3"><label class="form-label">Delivery Cost</label><input class="form-control" type="number" step="0.01" min="0" name="delivery_cost" value="<?= e($d['delivery_cost'] ?? '') ?>" placeholder="0.00"></div>
        <div class="col-6 col-md-3 d-flex align-items-end"><div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="cost_is_expense" value="1" id="cie" <?= $d['cost_is_expense'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="cie">Record cost as business expense</label></div></div>
        <div class="col-6 col-md-3"><label class="form-label">Delivery Income</label><input class="form-control" type="number" step="0.01" min="0" name="delivery_income" value="<?= e($d['delivery_income'] ?? '') ?>" placeholder="0.00"></div>
        <div class="col-6 col-md-3"><label class="form-label">Income Payment Status</label><select class="form-select" name="payment_status"><?= options(['Unpaid', 'Paid'], $d['payment_status']) ?></select></div>
        <div class="col-6 col-md-3"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><?= options(payment_methods(), $d['payment_method']) ?></select></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($d['notes']) ?></textarea></div>
        <div class="col-12 small text-muted">
            Delivery income is the delivery fee the customer pays you <b>separately</b>. If you already added the delivery fee as an item in the order,
            leave Delivery Income at 0 so it is not counted twice. Income is added only when the status is <b>Paid</b>.
        </div>
        <div class="col-12"><button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Delivery</button></div>
    </div>
</form>
<?php if ($id): ?>
    <form method="post" action="delete.php" class="mt-3" data-confirm="Delete this delivery? Its cost and income will also be removed.">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete Delivery</button>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
