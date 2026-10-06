<?php
// orders/form.php - create a new laundry order or edit an existing one.
// An order has many items. Item total = Quantity x Price.
// Prices are filled in automatically from the Price List (Settings),
// using the price of the chosen package (Normal / Silver / Gold).
// Order total = Subtotal - customer level discount.
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$services = service_types($pdo);
$speeds = service_speeds();
$tiers = customer_tiers();
// Customers with their level and the orders they already have in the shop
$customers = db_all($pdo, "SELECT c.id, c.customer_code, c.full_name, c.phone, c.address, c.tier,
        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.status NOT IN ('Delivered', 'Cancelled')) AS active_orders,
        (SELECT GROUP_CONCAT(DISTINCT o.shelf_number SEPARATOR ', ') FROM orders o
          WHERE o.customer_id = c.id AND o.status NOT IN ('Delivered', 'Cancelled') AND o.shelf_number <> '') AS shelves
    FROM customers c ORDER BY c.full_name");
$customerTier = array_column($customers, 'tier', 'id');

// Empty order (for "New Order")
$order = [
    'customer_id' => (int)($_GET['customer_id'] ?? 0), 'order_date' => date('Y-m-d'),
    'service_speed' => 'Normal', 'ready_at' => date('Y-m-d H:i', time() + $speeds['Normal']['hours'] * 3600), 'shelf_number' => '',
    'pickup_type' => 'Pickup', 'delivery_address' => '', 'delivery_phone' => '', 'status' => 'Received', 'notes' => '',
    'amount_paid' => 0, 'speed_percent' => null, 'discount_percent' => null,
];
$items = [];
$payment = ['amount_paid' => '', 'payment_method' => 'Cash'];

// Load the order when editing
if ($id) {
    $order = db_row($pdo, 'SELECT * FROM orders WHERE id = ?', [$id]);
    if (!$order) {
        flash('danger', 'Order not found.');
        redirect('orders/index.php');
    }
    $items = db_all($pdo, 'SELECT item_name, quantity, service_type, price FROM order_items WHERE order_id = ? ORDER BY id', [$id]);
}
$original = $order; // the saved order (used to keep its % values when editing)

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order = [
        'customer_id' => (int)($_POST['customer_id'] ?? 0),
        'order_date' => post_text('order_date', 10),
        'service_speed' => in_list(post_text('service_speed', 10), array_keys($speeds), 'Normal'),
        'ready_at' => str_replace('T', ' ', post_text('ready_at', 16)),
        'shelf_number' => post_text('shelf_number', 20),
        'pickup_type' => in_list(post_text('pickup_type', 20), ['Pickup', 'Delivery'], 'Pickup'),
        'delivery_address' => post_text('delivery_address', 255),
        'delivery_phone' => post_text('delivery_phone', 30),
        'status' => in_list(post_text('status', 20), order_statuses(), 'Received'),
        'notes' => post_text('notes', 2000),
    ] + $order;

    // Read the item rows. Empty rows are ignored.
    $items = [];
    $names = (array)($_POST['item_name'] ?? []);
    foreach ($names as $i => $name) {
        $name = mb_substr(trim((string)$name), 0, 100);
        $qtyRaw = trim((string)($_POST['quantity'][$i] ?? ''));
        $priceRaw = str_replace(',', '', trim((string)($_POST['price'][$i] ?? '')));
        $service = mb_substr(trim((string)($_POST['service_type'][$i] ?? '')), 0, 50);
        if ($name === '' && $priceRaw === '') {
            continue;
        }
        if ($name === '' || $service === '' || !ctype_digit($qtyRaw) || (int)$qtyRaw < 1 || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
            $errors[] = 'Item row ' . ($i + 1) . ': enter the item name, a quantity of 1 or more, a service and a price of 0 or more.';
            continue;
        }
        $items[] = ['item_name' => $name, 'quantity' => (int)$qtyRaw, 'service_type' => $service, 'price' => round((float)$priceRaw, 2)];
    }

    // Validate the order
    if (!isset($customerTier[$order['customer_id']])) {
        $errors[] = 'Please choose a customer.';
    }
    if (!valid_date($order['order_date'])) {
        $errors[] = 'Please enter a valid order date.';
    }
    // Ready time: empty = now + the hours of the chosen speed
    if ($order['ready_at'] === '') {
        $order['ready_at'] = date('Y-m-d H:i', time() + $speeds[$order['service_speed']]['hours'] * 3600);
    }
    $readyTime = DateTime::createFromFormat('Y-m-d H:i', substr($order['ready_at'], 0, 16));
    if (!$readyTime) {
        $errors[] = 'Please enter a valid ready date and time.';
    }

    // Extra % (only old orders had one) and customer level discount.
    // New order: take them from Settings. Edited order: keep the saved % unless
    // the speed or the customer was changed.
    $order['speed_percent'] = ($id && $order['service_speed'] === $original['service_speed'])
        ? (float)$original['speed_percent'] : $speeds[$order['service_speed']]['percent'];
    $order['discount_percent'] = ($id && $order['customer_id'] === (int)$original['customer_id'])
        ? (float)$original['discount_percent'] : (float)($tiers[$customerTier[$order['customer_id']] ?? 'Standard'] ?? 0);

    // Calculate order total
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += $item['quantity'] * $item['price'];
    }
    $orderTotal = order_money($subtotal, $order['speed_percent'], $order['discount_percent'])['total'];
    if (!$items) {
        $errors[] = 'Please add at least one laundry item.';
    }

    if ($id) {
        // When editing: the new total can not be less than what was already paid
        if ($orderTotal < (float)$order['amount_paid']) {
            $errors[] = 'The new order total (' . money($orderTotal) . ') is less than the amount already paid ('
                      . money($order['amount_paid']) . '). Delete a payment first if money was returned.';
        }
    } else {
        // First payment when the order is created (optional, needs the Payments permission)
        $payment = [
            'amount_paid' => can('payments') ? post_money('amount_paid') : 0.0,
            'payment_method' => in_list(post_text('payment_method', 20), payment_methods(), 'Cash'),
        ];
        if ($payment['amount_paid'] === null || $payment['amount_paid'] < 0) {
            $errors[] = 'Please enter a valid amount paid.';
        } elseif ($payment['amount_paid'] > $orderTotal) {
            $errors[] = 'Amount paid can not be more than the order total (' . money($orderTotal) . ').';
        }
    }

    if (!$errors) {
        require_csrf('orders/index.php');

        // Save the order, its items and the payment together.
        // If anything fails, nothing is saved (ROLLBACK).
        $isNew = ($id === 0);
        $pdo->beginTransaction();
        try {
            $readyAt = $readyTime->format('Y-m-d H:i:00');
            $values = [$order['customer_id'], $order['order_date'], $readyTime->format('Y-m-d'), $readyAt, $order['service_speed'],
                       $order['shelf_number'], $order['speed_percent'], $order['discount_percent'], $order['pickup_type'],
                       $order['delivery_address'], $order['delivery_phone'], $order['status'], $order['notes']];
            $oldStatus = $original['status'] ?? null;
            if ($id) {
                db_query($pdo, 'UPDATE orders SET customer_id = ?, order_date = ?, expected_date = ?, ready_at = ?, service_speed = ?, shelf_number = ?,
                                speed_percent = ?, discount_percent = ?, pickup_type = ?, delivery_address = ?, delivery_phone = ?, status = ?, notes = ?
                                WHERE id = ?', array_merge($values, [$id]));
                db_query($pdo, 'DELETE FROM order_items WHERE order_id = ?', [$id]);
            } else {
                // Remember which user created the order
                db_query($pdo, 'INSERT INTO orders (customer_id, order_date, expected_date, ready_at, service_speed, shelf_number, speed_percent,
                                discount_percent, pickup_type, delivery_address, delivery_phone, status, notes, created_by, created_by_name)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($values, [$CURRENT_USER['id'], $CURRENT_USER['username']]));
                $id = (int)$pdo->lastInsertId();
                db_query($pdo, 'UPDATE orders SET order_number = ? WHERE id = ?', [sprintf('ORD-%06d', $id), $id]);
            }

            // Save each item with its total (Quantity x Price)
            $stmt = $pdo->prepare('INSERT INTO order_items (order_id, item_name, quantity, service_type, price, total) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                $stmt->execute([$id, $item['item_name'], $item['quantity'], $item['service_type'], $item['price'],
                                round($item['quantity'] * $item['price'], 2)]);
            }

            // Record the first payment (also copied to income automatically)
            if ($isNew && $payment['amount_paid'] > 0) {
                db_query($pdo, 'INSERT INTO payments (order_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?)',
                    [$id, $order['order_date'], $payment['amount_paid'], $payment['payment_method'], 'Paid when order was created']);
                $paymentId = (int)$pdo->lastInsertId();
                $number = sprintf('ORD-%06d', $id);
                ledger_income_save($pdo, 'order_payment', $paymentId, $order['order_date'], 'Laundry Order',
                    'Payment for order ' . $number, $payment['amount_paid'], $payment['payment_method'], $number);
            }

            // Order trace: write the step (new order, or status changed while editing)
            if ($isNew) {
                log_order_step($pdo, $id, $order['status'], 'Order created (' . $order['service_speed'] . ')');
            } elseif ($oldStatus !== $order['status']) {
                log_order_step($pdo, $id, $order['status'], 'Changed while editing the order');
            }

            // Update total, paid, balance and payment status
            recalc_order($pdo, $id);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        flash('success', 'Order saved successfully.');
        redirect('orders/view.php?id=' . $id);
    }
}

if (!$items) {
    $items = [['item_name' => '', 'quantity' => 1, 'service_type' => $services[0] ?? 'Wash', 'price' => '']];
}
// Item names for the suggestion list: common names + names in the price list
$itemNames = array_values(array_unique(array_merge(laundry_item_names(),
    db_query($pdo, 'SELECT DISTINCT item_name FROM price_list ORDER BY item_name')->fetchAll(PDO::FETCH_COLUMN))));
// Shelves from Company Assets (category Shelf), to choose quickly
$shelves = db_query($pdo, "SELECT COALESCE(NULLIF(asset_code, ''), name) FROM assets WHERE category = 'Shelf' AND is_active = 1 ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
$readyValue = $order['ready_at'] ? date('Y-m-d\TH:i', strtotime($order['ready_at'])) : '';

// One row of the items table (also used as the template for "Add Item")
function item_row(array $item, array $services): string
{
    $serviceList = $services;
    if ($item['service_type'] !== '' && !in_array($item['service_type'], $serviceList, true)) {
        $serviceList[] = $item['service_type']; // keep an old service that was later switched off
    }
    return '<tr>'
        . '<td><input class="form-control" name="item_name[]" list="item-names" value="' . e($item['item_name']) . '" placeholder="e.g. Shirt" required></td>'
        . '<td style="width:90px"><input class="form-control item-qty" type="number" min="1" step="1" name="quantity[]" value="' . e($item['quantity']) . '" required></td>'
        . '<td><select class="form-select" name="service_type[]">' . options($serviceList, (string)$item['service_type']) . '</select></td>'
        . '<td style="width:120px"><input class="form-control item-price" type="number" min="0" step="0.01" name="price[]" value="' . e($item['price']) . '" required></td>'
        . '<td class="money item-total">' . money(0) . '</td>'
        . '<td><button type="button" class="btn btn-outline-danger btn-sm remove-item" title="Remove"><i class="bi bi-x-lg"></i></button></td>'
        . '</tr>';
}

$pageTitle = $id ? 'Edit Order' : 'New Laundry Order';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><?= $id ? 'Edit Order ' . e($order['order_number']) : 'New Laundry Order' ?></h1>
    <a class="btn btn-outline-secondary" href="<?= $id ? 'view.php?id=' . $id : 'index.php' ?>"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<?php if (!$customers): ?>
    <div class="alert alert-info">You have no customers yet. <a href="../customers/form.php?return=order">Add a customer first</a>.</div>
<?php endif; ?>

<form method="post">
    <?= csrf_field() ?>
    <div class="card shadow-sm mb-3"><div class="card-body">
        <div class="row g-3">
            <div class="col-lg-6">
                <label class="form-label">Customer *</label>
                <input class="form-control form-control-sm mb-1" placeholder="Type to search customer by name or phone..." data-filter-select="customer_id">
                <div class="input-group">
                    <select class="form-select" name="customer_id" id="customer_id" required>
                        <option value="">-- Choose customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" data-phone="<?= e($c['phone']) ?>" data-address="<?= e($c['address']) ?>"
                                    data-tier="<?= e($c['tier']) ?>" data-discount="<?= (float)($tiers[$c['tier']] ?? 0) ?>"
                                    data-active="<?= (int)$c['active_orders'] ?>" data-shelves="<?= e($c['shelves']) ?>"
                                    <?= (int)$order['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['full_name'] . ' - ' . $c['phone'] . ' (' . $c['customer_code'] . ')' . ($c['tier'] !== 'Standard' ? ' - ' . $c['tier'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a class="btn btn-outline-primary" href="../customers/form.php?return=order" title="Add new customer"><i class="bi bi-person-plus"></i></a>
                </div>
                <!-- Filled by JavaScript: customer level + orders already in the shop -->
                <div class="small mt-1" id="customer-info"></div>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Order Date *</label>
                <input class="form-control" type="date" name="order_date" value="<?= e($order['order_date']) ?>" required>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Shelf Number</label>
                <input class="form-control" name="shelf_number" value="<?= e($order['shelf_number']) ?>" placeholder="e.g. A-12" maxlength="20" list="shelf-list">
                <datalist id="shelf-list"><?= options($shelves) ?></datalist>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Service Speed</label>
                <select class="form-select" name="service_speed" id="service_speed">
                    <?php foreach ($speeds as $name => $sp): ?>
                        <option value="<?= $name ?>" data-hours="<?= $sp['hours'] ?>" data-percent="<?= $sp['percent'] ?>" <?= $order['service_speed'] === $name ? 'selected' : '' ?>>
                            <?= $name ?> (<?= $sp['hours'] >= 24 && $sp['hours'] % 24 === 0 ? ($sp['hours'] / 24) . ' days' : $sp['hours'] . ' hours' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Ready By (date &amp; time)</label>
                <input class="form-control" type="datetime-local" name="ready_at" id="ready_at" value="<?= e($readyValue) ?>" data-now="<?= date('Y-m-d\TH:i:s') ?>">
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Pickup / Delivery</label>
                <select class="form-select" name="pickup_type" id="pickup_type"><?= options(['Pickup', 'Delivery'], $order['pickup_type']) ?></select>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status"><?= options(order_statuses(), $order['status']) ?></select>
            </div>
            <div class="col-md-6 col-lg-3 delivery-field">
                <label class="form-label">Delivery Phone</label>
                <input class="form-control" type="tel" name="delivery_phone" id="delivery_phone" value="<?= e($order['delivery_phone']) ?>">
            </div>
            <div class="col-md-6 col-lg-3 delivery-field">
                <label class="form-label">Delivery Address</label>
                <input class="form-control" name="delivery_address" id="delivery_address" value="<?= e($order['delivery_address']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="2"><?= e($order['notes']) ?></textarea>
            </div>
        </div>
    </div></div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Laundry Items</strong>
            <button type="button" class="btn btn-sm btn-success" id="add-item"><i class="bi bi-plus-lg"></i> Add Item</button>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" id="items-table" data-currency="<?= e(setting('currency_symbol', '$')) ?>"
                   data-prices="<?= e(json_encode(price_list_map($pdo))) ?>"
                   data-edit="<?= $id ? '1' : '0' ?>"
                   data-original-speed="<?= e($original['service_speed'] ?? '') ?>" data-original-speed-percent="<?= (float)($original['speed_percent'] ?? 0) ?>"
                   data-original-customer="<?= (int)($original['customer_id'] ?? 0) ?>" data-original-discount="<?= (float)($original['discount_percent'] ?? 0) ?>">
                <thead><tr><th>Item Name</th><th>Qty</th><th>Service Type</th><th>Price</th><th class="money">Total</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($items as $item) { echo item_row($item, $services); } ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="text-end">Subtotal</td><td class="money" id="order-subtotal"><?= money(0) ?></td><td></td></tr>
                    <tr id="row-speed"><td colspan="4" class="text-end">Package extra charge <span id="speed-percent"></span></td><td class="money" id="order-speed"><?= money(0) ?></td><td></td></tr>
                    <tr id="row-discount"><td colspan="4" class="text-end">Customer level discount <span id="discount-percent"></span></td><td class="money" id="order-discount"><?= money(0) ?></td><td></td></tr>
                    <tr><th colspan="4" class="text-end">Order Total</th><th class="money fs-5" id="order-total"><?= money(0) ?></th><th></th></tr>
                </tfoot>
            </table>
        </div>
        <template id="item-row-template"><?= item_row(['item_name' => '', 'quantity' => 1, 'service_type' => $services[0] ?? '', 'price' => ''], $services) ?></template>
        <datalist id="item-names"><?= options($itemNames) ?></datalist>
        <div class="card-footer small text-muted">Prices come automatically from the <a href="../settings/prices.php">Price List</a> for the chosen package (Normal / Silver / Gold). You can still change a price for this order.</div>
    </div>

    <?php if (!$id && can('payments')): ?>
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label">Amount Paid Now</label>
                    <input class="form-control" type="number" min="0" step="0.01" name="amount_paid" id="amount_paid" value="<?= e($payment['amount_paid']) ?>" placeholder="0.00">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Payment Method</label>
                    <select class="form-select" name="payment_method"><?= options(payment_methods(), $payment['payment_method']) ?></select>
                </div>
                <div class="col-12 col-md-6 text-md-end">
                    <span class="text-muted">Balance:</span> <span class="fs-5 fw-bold" id="order-balance"><?= money(0) ?></span>
                </div>
            </div>
        </div></div>
    <?php elseif ($id): ?>
        <div class="alert alert-info">Already paid: <b><?= money($order['amount_paid']) ?></b>. To add money, use <b>Add Payment</b> on the order page.</div>
    <?php endif; ?>

    <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Order</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var type = document.getElementById('pickup_type');
    var customer = document.getElementById('customer_id');
    var speed = document.getElementById('service_speed');
    var readyAt = document.getElementById('ready_at');

    // Show delivery fields only for "Delivery", and fill them from the customer
    function toggle() {
        document.querySelectorAll('.delivery-field').forEach(function (el) { el.style.display = type.value === 'Delivery' ? '' : 'none'; });
        var opt = customer.selectedOptions[0];
        if (type.value === 'Delivery' && opt && opt.value) {
            var phone = document.getElementById('delivery_phone'), addr = document.getElementById('delivery_address');
            if (!phone.value) { phone.value = opt.dataset.phone || ''; }
            if (!addr.value) { addr.value = opt.dataset.address || ''; }
        }
    }

    // Show the customer's level and the orders they already have in the shop
    function customerInfo() {
        var box = document.getElementById('customer-info');
        var opt = customer.selectedOptions[0];
        if (!opt || !opt.value) { box.innerHTML = ''; return; }
        var html = 'Level: <b></b>';
        box.innerHTML = html;
        box.querySelector('b').textContent = opt.dataset.tier + (parseFloat(opt.dataset.discount) > 0 ? ' (' + opt.dataset.discount + '% discount)' : '');
        var active = parseInt(opt.dataset.active, 10) || 0;
        if (active > 0) {
            var link = document.createElement('a');
            link.href = '../tracking/index.php?customer_id=' + encodeURIComponent(opt.value);
            link.className = 'ms-2';
            link.textContent = active + ' order(s) already in the shop' + (opt.dataset.shelves ? ' - shelf ' + opt.dataset.shelves : '');
            box.appendChild(link);
        }
    }

    // Ready time = now + hours of the chosen speed.
    // "now" is the shop's time (Settings > Time Zone), not the phone's clock.
    var clockDiff = new Date(readyAt.dataset.now) - Date.now();
    function setReadyTime() {
        var hours = parseInt(speed.selectedOptions[0].dataset.hours, 10) || 48;
        var d = new Date(Date.now() + clockDiff + hours * 3600 * 1000);
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        readyAt.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    type.addEventListener('change', toggle);
    customer.addEventListener('change', function () { toggle(); customerInfo(); });
    speed.addEventListener('change', setReadyTime);
    toggle();
    customerInfo();
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
