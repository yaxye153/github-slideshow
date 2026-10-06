<?php
// payments/add.php - record a customer payment for an order.
// The payment is also copied into Income automatically (only once).
require_once __DIR__ . '/../auth/auth_check.php';

$orderId = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);

// Orders that still have money to pay (for the dropdown)
$openOrders = db_all($pdo, "SELECT o.id, o.order_number, o.balance, c.full_name FROM orders o JOIN customers c ON c.id = o.customer_id
                            WHERE o.balance > 0 AND o.status <> 'Cancelled' ORDER BY o.order_date DESC, o.id DESC");

$payment = ['payment_date' => date('Y-m-d'), 'amount' => '', 'payment_method' => 'Cash', 'reference' => '', 'notes' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment = [
        'payment_date' => post_text('payment_date', 10),
        'amount' => post_money('amount'),
        'payment_method' => in_list(post_text('payment_method', 20), payment_methods(), 'Cash'),
        'reference' => post_text('reference', 100),
        'notes' => post_text('notes', 2000),
    ];
    if (!valid_date($payment['payment_date'])) {
        $errors[] = 'Please enter a valid payment date.';
    }
    if ($payment['amount'] === null || $payment['amount'] <= 0) {
        $errors[] = 'Please enter an amount greater than 0.';
    }

    if (!$errors) {
        require_csrf('payments/add.php?order_id=' . $orderId);

        $pdo->beginTransaction();
        try {
            // Lock the order row so two payments at the same time can not overpay it
            $order = db_row($pdo, 'SELECT id, order_number, balance, status FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$order) {
                $errors[] = 'Please choose an order.';
            } elseif ($order['status'] === 'Cancelled') {
                $errors[] = 'This order is cancelled. Payments can not be added.';
            } elseif ($payment['amount'] > (float)$order['balance']) {
                // Payment greater than the balance is not allowed
                $errors[] = 'The payment (' . money($payment['amount']) . ') is more than the balance (' . money($order['balance']) . ').';
            }

            if ($errors) {
                $pdo->rollBack();
            } else {
                // Save the payment
                db_query($pdo, 'INSERT INTO payments (order_id, payment_date, amount, payment_method, reference, notes) VALUES (?, ?, ?, ?, ?, ?)',
                    [$orderId, $payment['payment_date'], $payment['amount'], $payment['payment_method'], $payment['reference'], $payment['notes']]);
                $paymentId = (int)$pdo->lastInsertId();

                // Copy it to income (source = order_payment, linked by payment id)
                ledger_income_save($pdo, 'order_payment', $paymentId, $payment['payment_date'], 'Laundry Order',
                    'Payment for order ' . $order['order_number'], $payment['amount'], $payment['payment_method'],
                    $payment['reference'] !== '' ? $payment['reference'] : $order['order_number'], $payment['notes']);

                // Update paid amount, balance and payment status
                recalc_order($pdo, $orderId);
                $pdo->commit();

                flash('success', 'Payment recorded successfully.');
                redirect('orders/view.php?id=' . $orderId);
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
    }
}

$selected = $orderId ? db_row($pdo, 'SELECT o.*, c.full_name FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?', [$orderId]) : null;

$pageTitle = 'Add Payment';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-cash-coin"></i> Add Payment</h1>
    <a class="btn btn-outline-secondary" href="<?= $orderId ? '../orders/view.php?id=' . $orderId : 'index.php' ?>"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<?php if ($selected): ?>
    <div class="alert alert-light border">
        Order <b><?= e($selected['order_number']) ?></b> &mdash; <?= e($selected['full_name']) ?><br>
        Total <?= money($selected['total_amount']) ?> &middot; Paid <?= money($selected['amount_paid']) ?> &middot;
        <b>Balance <?= money($selected['balance']) ?></b>
    </div>
<?php endif; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 640px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Order *</label>
            <select class="form-select" name="order_id" required>
                <option value="">-- Choose order --</option>
                <?php foreach ($openOrders as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $orderId === (int)$o['id'] ? 'selected' : '' ?>>
                        <?= e($o['order_number'] . ' - ' . $o['full_name'] . ' - balance ' . money($o['balance'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$openOrders): ?><small class="text-muted">There are no orders with an unpaid balance.</small><?php endif; ?>
        </div>
        <div class="col-6">
            <label class="form-label">Amount *</label>
            <input class="form-control" type="number" step="0.01" min="0.01" name="amount" value="<?= e($payment['amount'] ?? '') ?>"
                   <?= $selected ? 'max="' . e($selected['balance']) . '"' : '' ?> required>
        </div>
        <div class="col-6">
            <label class="form-label">Payment Date *</label>
            <input class="form-control" type="date" name="payment_date" value="<?= e($payment['payment_date']) ?>" required>
        </div>
        <div class="col-6">
            <label class="form-label">Payment Method</label>
            <select class="form-select" name="payment_method"><?= options(payment_methods(), $payment['payment_method']) ?></select>
        </div>
        <div class="col-6">
            <label class="form-label">Reference</label>
            <input class="form-control" name="reference" value="<?= e($payment['reference']) ?>" placeholder="e.g. EVC transaction ID">
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <input class="form-control" name="notes" value="<?= e($payment['notes']) ?>">
        </div>
        <div class="col-12">
            <button class="btn btn-success btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Payment</button>
        </div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
