<?php
// customers/view.php - customer details and order history
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);

// Get customer information
$customer = db_row($pdo, 'SELECT * FROM customers WHERE id = ?', [$id]);
if (!$customer) {
    flash('danger', 'Customer not found.');
    redirect('customers/index.php');
}

// Customer totals (cancelled orders are not counted as spending)
$totals = db_row($pdo, "SELECT COUNT(*) AS orders,
        COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN total_amount END), 0) AS spent,
        COALESCE(SUM(amount_paid), 0) AS paid,
        COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN balance END), 0) AS balance
    FROM orders WHERE customer_id = ?", [$id]);

$orders = db_all($pdo, 'SELECT * FROM orders WHERE customer_id = ? ORDER BY order_date DESC, id DESC', [$id]);

$pageTitle = $customer['full_name'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-person"></i> <?= e($customer['full_name']) ?></h1>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="../orders/form.php?customer_id=<?= $id ?>"><i class="bi bi-basket"></i> New Order</a>
        <a class="btn btn-outline-secondary" href="form.php?id=<?= $id ?>"><i class="bi bi-pencil"></i> Edit</a>
        <form method="post" action="delete.php" data-confirm="Delete this customer? This cannot be undone.">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete</button>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100"><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><th>Customer ID</th><td><?= (int)$customer['id'] ?></td></tr>
                <tr><th>Customer Code</th><td><?= e($customer['customer_code']) ?></td></tr>
                <tr><th>Phone</th><td><?= e($customer['phone']) ?></td></tr>
                <tr><th>Alternative Phone</th><td><?= e($customer['alt_phone']) ?: '-' ?></td></tr>
                <tr><th>Address</th><td><?= e($customer['address']) ?: '-' ?></td></tr>
                <tr><th>Registered</th><td><?= show_date($customer['registration_date']) ?></td></tr>
                <tr><th>Notes</th><td><?= nl2br(e($customer['notes'])) ?: '-' ?></td></tr>
            </table>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="row g-3">
            <div class="col-6"><div class="card stat-card shadow-sm"><div class="card-body"><div class="stat-label">Total Orders</div><div class="stat-value"><?= (int)$totals['orders'] ?></div></div></div></div>
            <div class="col-6"><div class="card stat-card teal shadow-sm"><div class="card-body"><div class="stat-label">Total Spent</div><div class="stat-value"><?= money($totals['spent']) ?></div></div></div></div>
            <div class="col-6"><div class="card stat-card green shadow-sm"><div class="card-body"><div class="stat-label">Total Paid</div><div class="stat-value"><?= money($totals['paid']) ?></div></div></div></div>
            <div class="col-6"><div class="card stat-card orange shadow-sm"><div class="card-body"><div class="stat-label">Outstanding Balance</div><div class="stat-value"><?= money($totals['balance']) ?></div></div></div></div>
        </div>
    </div>
</div>

<div class="section-title">Order History</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="money">Total</th><th class="money">Paid</th><th class="money">Balance</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="../orders/view.php?id=<?= $o['id'] ?>"><?= e($o['order_number']) ?></a></td>
                    <td><?= show_date($o['order_date']) ?></td>
                    <td><?= badge($o['status']) ?></td>
                    <td><?= badge($o['payment_status']) ?></td>
                    <td class="money"><?= money($o['total_amount']) ?></td>
                    <td class="money"><?= money($o['amount_paid']) ?></td>
                    <td class="money"><?= money($o['balance']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?><tr><td colspan="7" class="text-center text-muted py-4">This customer has no orders yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
