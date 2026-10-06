<?php
// orders/view.php - full details of one order: items, payments and status
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$order = db_row($pdo, 'SELECT o.*, c.full_name, c.phone, c.customer_code, c.tier FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?', [$id]);
if (!$order) {
    flash('danger', 'Order not found.');
    redirect('orders/index.php');
}
$items = db_all($pdo, 'SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id]);
$payments = db_all($pdo, 'SELECT * FROM payments WHERE order_id = ? ORDER BY payment_date, id', [$id]);
$deliveries = db_all($pdo, 'SELECT * FROM deliveries WHERE order_id = ? ORDER BY delivery_date', [$id]);
// Order trace: every step with who and when
$history = db_all($pdo, 'SELECT * FROM order_history WHERE order_id = ? ORDER BY changed_at, id', [$id]);

$pageTitle = 'Order ' . $order['order_number'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-basket"></i> Order <?= e($order['order_number']) ?> <?= speed_badge($order['service_speed']) ?></h1>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($order['balance'] > 0 && $order['status'] !== 'Cancelled' && can('payments')): ?>
            <a class="btn btn-success" href="../payments/add.php?order_id=<?= $id ?>"><i class="bi bi-cash"></i> Add Payment</a>
        <?php endif; ?>
        <?php if (in_array($order['status'], ['Ready', 'Out for Delivery'], true)): ?>
            <form method="post" action="notify.php" target="_blank" class="d-flex gap-1">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                <button class="btn <?= $order['notified_at'] ? 'btn-outline-success' : 'btn-success' ?>" name="via" value="whatsapp" type="submit"><i class="bi bi-whatsapp"></i> WhatsApp</button>
                <button class="btn btn-outline-primary" name="via" value="sms" type="submit"><i class="bi bi-chat-dots"></i> SMS</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-outline-dark" href="../receipt/print.php?id=<?= $id ?>" target="_blank"><i class="bi bi-printer"></i> Receipt</a>
        <a class="btn btn-outline-secondary" href="form.php?id=<?= $id ?>"><i class="bi bi-pencil"></i> Edit</a>
        <?php if ($order['pickup_type'] === 'Delivery' && can('delivery')): ?>
            <a class="btn btn-outline-primary" href="../delivery/form.php?order_id=<?= $id ?>"><i class="bi bi-truck"></i> Record Delivery</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100"><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><th>Customer</th><td><a href="../customers/view.php?id=<?= $order['customer_id'] ?>"><?= e($order['full_name']) ?></a> (<?= e($order['customer_code']) ?>) <?= tier_badge($order['tier']) ?></td></tr>
                <tr><th>Phone</th><td><?= e($order['phone']) ?></td></tr>
                <tr><th>Order Date</th><td><?= show_date($order['order_date']) ?></td></tr>
                <tr><th>Created By</th><td><?= e($order['created_by_name'] ?: '-') ?> <span class="text-muted small"><?= show_datetime($order['created_at']) ?></span></td></tr>
                <?php if ($order['status'] === 'Delivered'): ?>
                    <tr><th><?= $order['pickup_type'] === 'Delivery' ? 'Delivered' : 'Picked Up' ?></th><td><?= show_datetime($order['handed_over_at']) ?><?= $order['received_by'] ? ' &middot; received by <b>' . e($order['received_by']) . '</b>' : '' ?></td></tr>
                <?php endif; ?>
                <?php if ($order['notified_at']): ?><tr><th>Customer Told</th><td><?= show_datetime($order['notified_at']) ?></td></tr><?php endif; ?>
                <tr><th>Service Speed</th><td><?= speed_badge($order['service_speed']) ?></td></tr>
                <tr><th>Ready By</th><td><?= $order['ready_at'] ? show_datetime($order['ready_at']) : show_date($order['expected_date']) ?> <?= ready_label($order) ?></td></tr>
                <tr><th>Shelf Number</th><td><span class="shelf"><?= e($order['shelf_number']) ?: '-' ?></span></td></tr>
                <tr><th>Pickup/Delivery</th><td><?= e($order['pickup_type']) ?></td></tr>
                <?php if ($order['pickup_type'] === 'Delivery'): ?>
                    <tr><th>Delivery Address</th><td><?= e($order['delivery_address']) ?: '-' ?></td></tr>
                    <tr><th>Delivery Phone</th><td><?= e($order['delivery_phone']) ?: '-' ?></td></tr>
                <?php endif; ?>
                <tr><th>Notes</th><td><?= nl2br(e($order['notes'])) ?: '-' ?></td></tr>
            </table>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="d-flex justify-content-between mb-2"><span>Status</span><?= badge($order['status']) ?></div>
            <!-- Change status quickly -->
            <form method="post" action="status.php" class="d-flex gap-2">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                <select class="form-select" name="status"><?= options(order_statuses(), $order['status']) ?></select>
                <input class="form-control" name="received_by" placeholder="Received by" style="max-width: 130px" maxlength="100" title="Name of the person who received the order (when Delivered)">
                <input class="form-control" name="shelf_number" value="<?= e($order['shelf_number']) ?>" placeholder="Shelf" style="max-width: 110px" maxlength="20" title="Shelf number">
                <button class="btn btn-primary" type="submit">Update</button>
            </form>
        </div></div>
        <div class="card shadow-sm"><div class="card-body">
            <?php if ($order['speed_charge'] > 0 || $order['discount_amount'] > 0): ?>
                <div class="d-flex justify-content-between small"><span>Subtotal (items)</span><span><?= money($order['subtotal']) ?></span></div>
                <?php if ($order['speed_charge'] > 0): ?><div class="d-flex justify-content-between small"><span><?= e($order['service_speed']) ?> charge (<?= (float)$order['speed_percent'] ?>%)</span><span>+<?= money($order['speed_charge']) ?></span></div><?php endif; ?>
                <?php if ($order['discount_amount'] > 0): ?><div class="d-flex justify-content-between small"><span>Customer level discount (<?= (float)$order['discount_percent'] ?>%)</span><span>-<?= money($order['discount_amount']) ?></span></div><?php endif; ?>
            <?php endif; ?>
            <div class="d-flex justify-content-between"><span>Total Amount</span><strong><?= money($order['total_amount']) ?></strong></div>
            <div class="d-flex justify-content-between"><span>Amount Paid</span><strong class="text-success"><?= money($order['amount_paid']) ?></strong></div>
            <div class="d-flex justify-content-between fs-5"><span>Balance</span><strong class="text-danger"><?= money($order['balance']) ?></strong></div>
            <div class="d-flex justify-content-between mt-1"><span>Payment Status</span><?= badge($order['payment_status']) ?></div>
        </div></div>
    </div>
</div>

<div class="section-title">Items</div>
<div class="card shadow-sm mb-3">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Item</th><th class="text-center">Qty</th><th>Service</th><th class="money">Price</th><th class="money">Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr><td><?= e($it['item_name']) ?></td><td class="text-center"><?= (int)$it['quantity'] ?></td><td><?= e($it['service_type']) ?></td>
                    <td class="money"><?= money($it['price']) ?></td><td class="money"><?= money($it['total']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="4" class="text-end">Items Subtotal</th><th class="money"><?= money($order['subtotal']) ?></th></tr></tfoot>
        </table>
    </div>
</div>

<div class="section-title">Payments</div>
<div class="card shadow-sm mb-3">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Notes</th><th class="money">Amount</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payments as $pay): ?>
                <tr>
                    <td><?= show_date($pay['payment_date']) ?></td><td><?= e($pay['payment_method']) ?></td>
                    <td><?= e($pay['reference']) ?></td><td><?= e($pay['notes']) ?></td>
                    <td class="money"><?= money($pay['amount']) ?></td>
                    <td class="text-end">
                        <?php if (is_admin()): /* only the admin can delete */ ?><form method="post" action="../payments/delete.php" data-confirm="Delete this payment? It will also be removed from income.">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $pay['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete payment"><i class="bi bi-trash"></i></button>
                        </form><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="6" class="text-center text-muted py-3">No payments yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="section-title">Order Trace</div>
<div class="card shadow-sm mb-3"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Time</th><th>Step</th><th>By</th><th>Note</th></tr></thead>
    <?php foreach ($history as $h): ?>
        <tr><td class="small text-nowrap"><?= show_datetime($h['changed_at']) ?></td><td><?= badge($h['status']) ?></td><td><?= e($h['username']) ?: '-' ?></td><td class="small"><?= e($h['note']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$history): ?><tr><td colspan="4" class="text-muted text-center py-3">No steps recorded yet (orders made before this version have no trace).</td></tr><?php endif; ?>
</table></div></div>

<?php if ($deliveries): ?>
    <div class="section-title">Deliveries</div>
    <div class="card shadow-sm mb-3"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Date</th><th>Delivery Person</th><th class="money">Cost</th><th class="money">Income</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($deliveries as $d): ?>
            <tr><td><?= show_date($d['delivery_date']) ?></td><td><?= e($d['delivery_person']) ?></td>
                <td class="money"><?= money($d['delivery_cost']) ?></td><td class="money"><?= money($d['delivery_income']) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="../delivery/form.php?id=<?= $d['id'] ?>"><i class="bi bi-pencil"></i></a></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
<?php endif; ?>

<?php if (is_admin()): /* only the admin can delete */ ?><form method="post" action="delete.php" class="mt-4" data-confirm="Delete this order? This cannot be undone.">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn btn-outline-danger" type="submit" <?= $payments ? 'disabled title="Orders with payments cannot be deleted"' : '' ?>><i class="bi bi-trash"></i> Delete Order</button>
    <?php if ($payments): ?><small class="text-muted ms-2">Orders with payments cannot be deleted. Use status "Cancelled" instead.</small><?php endif; ?>
</form><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
