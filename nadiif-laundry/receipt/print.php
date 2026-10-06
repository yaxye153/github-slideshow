<?php
// receipt/print.php - printable laundry receipt.
// ?size=a4    -> normal A4 page
// ?size=small -> 80mm receipt printer (also good on mobile)
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$size = in_list((string)($_GET['size'] ?? 'small'), ['small', 'a4'], 'small');

$order = db_row($pdo, 'SELECT o.*, c.full_name, c.phone, c.customer_code FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?', [$id]);
if (!$order) {
    flash('danger', 'Order not found.');
    redirect('orders/index.php');
}
$items = db_all($pdo, 'SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt <?= e($order['order_number']) ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #000; margin: 0; background: #f0f0f0; }
        .receipt { background: #fff; margin: 12px auto; padding: 14px; }
        .small .receipt { width: 76mm; max-width: 100%; font-size: 12px; box-sizing: border-box; }
        .a4 .receipt { width: 190mm; max-width: 100%; font-size: 14px; padding: 24px; box-sizing: border-box; }
        h1 { font-size: 1.5em; margin: 0; text-align: center; }
        .center { text-align: center; }
        .muted { color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 3px 2px; text-align: left; vertical-align: top; }
        .items th { border-bottom: 1px dashed #000; }
        .items td.r, .items th.r, .totals td.r { text-align: right; white-space: nowrap; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .totals td { padding: 2px; }
        .big { font-size: 1.2em; font-weight: bold; }
        .toolbar { text-align: center; padding: 10px; }
        .toolbar a, .toolbar button { display: inline-block; margin: 4px; padding: 10px 16px; font-size: 15px; border: 1px solid #0d4f8b;
            background: #fff; color: #0d4f8b; border-radius: 6px; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #0d4f8b; color: #fff; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .receipt { margin: 0; padding: 0; }
            .small .receipt { width: 72mm; }
            @page { margin: <?= $size === 'small' ? '3mm' : '12mm' ?>; <?= $size === 'small' ? 'size: 80mm auto;' : 'size: A4;' ?> }
        }
    </style>
</head>
<body class="<?= $size ?>">
<div class="toolbar">
    <button class="primary" onclick="window.print()">Print</button>
    <a href="?id=<?= $id ?>&size=small">Small receipt (80mm)</a>
    <a href="?id=<?= $id ?>&size=a4">A4 page</a>
    <a href="<?= url('orders/view.php?id=' . $id) ?>">Back to order</a>
</div>

<div class="receipt">
    <h1><?= e(setting('business_name')) ?></h1>
    <?php if (setting('business_address') !== ''): ?><div class="center muted"><?= e(setting('business_address')) ?></div><?php endif; ?>
    <?php if (setting('business_phone') !== ''): ?><div class="center muted">Tel: <?= e(setting('business_phone')) ?></div><?php endif; ?>
    <div class="line"></div>

    <table>
        <tr><td>Order Number</td><td><b><?= e($order['order_number']) ?></b></td></tr>
        <tr><td>Customer Name</td><td><?= e($order['full_name']) ?> (<?= e($order['customer_code']) ?>)</td></tr>
        <tr><td>Phone</td><td><?= e($order['phone']) ?></td></tr>
        <tr><td>Date</td><td><?= show_date($order['order_date']) ?></td></tr>
        <tr><td>Service</td><td><?= e($order['service_speed']) ?></td></tr>
        <?php if ($order['shelf_number'] !== null && $order['shelf_number'] !== ''): ?><tr><td>Shelf</td><td><b><?= e($order['shelf_number']) ?></b></td></tr><?php endif; ?>
        <?php if ($order['pickup_type'] === 'Delivery'): ?>
            <tr><td>Delivery</td><td><?= e($order['delivery_address']) ?> <?= e($order['delivery_phone']) ?></td></tr>
        <?php endif; ?>
    </table>
    <div class="line"></div>

    <table class="items">
        <thead><tr><th>Item</th><th class="r">Qty</th><th>Service</th><th class="r">Price</th><th class="r">Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr><td><?= e($it['item_name']) ?></td><td class="r"><?= (int)$it['quantity'] ?></td><td><?= e($it['service_type']) ?></td>
                <td class="r"><?= money($it['price']) ?></td><td class="r"><?= money($it['total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="line"></div>

    <table class="totals">
        <?php if ($order['speed_charge'] > 0 || $order['discount_amount'] > 0): ?>
            <tr><td>Subtotal</td><td class="r"><?= money($order['subtotal']) ?></td></tr>
            <?php if ($order['speed_charge'] > 0): ?><tr><td><?= e($order['service_speed']) ?> (+<?= (float)$order['speed_percent'] ?>%)</td><td class="r"><?= money($order['speed_charge']) ?></td></tr><?php endif; ?>
            <?php if ($order['discount_amount'] > 0): ?><tr><td>Discount (<?= (float)$order['discount_percent'] ?>%)</td><td class="r">-<?= money($order['discount_amount']) ?></td></tr><?php endif; ?>
        <?php endif; ?>
        <tr class="big"><td>Total Amount</td><td class="r"><?= money($order['total_amount']) ?></td></tr>
        <tr><td>Paid</td><td class="r"><?= money($order['amount_paid']) ?></td></tr>
        <tr class="big"><td>Balance</td><td class="r"><?= money($order['balance']) ?></td></tr>
    </table>
    <div class="line"></div>

    <table>
        <tr><td>Expected Date</td><td><?= $order['ready_at'] ? show_datetime($order['ready_at']) : show_date($order['expected_date']) ?></td></tr>
        <tr><td>Status</td><td><?= e($order['status']) ?> / <?= e($order['payment_status']) ?></td></tr>
    </table>
    <div class="line"></div>

    <div class="center"><b>Thank You</b></div>
    <?php if (setting('receipt_footer') !== ''): ?><div class="center muted"><?= nl2br(e(setting('receipt_footer'))) ?></div><?php endif; ?>
    <div class="center muted" style="font-size: .85em; margin-top: 6px;">Printed <?= date('d M Y H:i') ?></div>
</div>
</body>
</html>
