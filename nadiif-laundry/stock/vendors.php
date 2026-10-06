<?php
// stock/vendors.php - VENDORS (shops / suppliers where stock is bought)
require_once __DIR__ . '/../auth/auth_check.php';

$editId = (int)($_GET['id'] ?? 0);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $v = ['name' => post_text('name', 100), 'phone' => post_text('phone', 30), 'address' => post_text('address', 255), 'notes' => post_text('notes', 1000)];
    if ($v['name'] === '') { $errors[] = 'Please enter the vendor name.'; }
    elseif (db_value($pdo, 'SELECT id FROM vendors WHERE name = ? AND id <> ?', [$v['name'], $id])) { $errors[] = 'This vendor already exists.'; }
    if (!$errors) {
        require_csrf('stock/vendors.php');
        if ($id) {
            db_query($pdo, 'UPDATE vendors SET name = ?, phone = ?, address = ?, notes = ? WHERE id = ?', [$v['name'], $v['phone'], $v['address'], $v['notes'], $id]);
        } else {
            db_query($pdo, 'INSERT INTO vendors (name, phone, address, notes) VALUES (?, ?, ?, ?)', [$v['name'], $v['phone'], $v['address'], $v['notes']]);
        }
        flash('success', 'Vendor saved.');
        redirect('stock/vendors.php');
    }
}

$vendors = db_all($pdo, 'SELECT v.*, COUNT(b.id) AS purchases, COALESCE(SUM(b.total_cost), 0) AS spent, MAX(b.purchase_date) AS last_purchase
    FROM vendors v LEFT JOIN stock_batches b ON b.vendor_id = v.id GROUP BY v.id ORDER BY v.name');
$edit = $editId ? db_row($pdo, 'SELECT * FROM vendors WHERE id = ?', [$editId]) : null;
$edit = $edit ?? ['id' => 0, 'name' => '', 'phone' => '', 'address' => '', 'notes' => ''];

$pageTitle = 'Vendors';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-shop"></i> Vendors</h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Stock</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<div class="row g-3">
    <div class="col-lg-4">
        <form method="post" class="card card-body shadow-sm">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <h2 class="h6"><?= $edit['id'] ? 'Edit Vendor' : 'Add Vendor' ?></h2>
            <div class="mb-2"><label class="form-label">Name *</label><input class="form-control" name="name" value="<?= e($edit['name']) ?>" required maxlength="100"></div>
            <div class="mb-2"><label class="form-label">Phone</label><input class="form-control" type="tel" name="phone" value="<?= e($edit['phone']) ?>" maxlength="30"></div>
            <div class="mb-2"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= e($edit['address']) ?>" maxlength="255"></div>
            <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?= e($edit['notes']) ?></textarea></div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save Vendor</button>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
            <thead><tr><th>Vendor</th><th>Phone</th><th>Address</th><th class="text-center">Purchases</th><th class="money">Total spent</th><th>Last</th><th></th></tr></thead>
            <?php foreach ($vendors as $v): ?>
                <tr><td><b><?= e($v['name']) ?></b></td><td><?= e($v['phone']) ?></td><td class="small"><?= e($v['address']) ?></td>
                    <td class="text-center"><?= (int)$v['purchases'] ?></td><td class="money"><?= money($v['spent']) ?></td><td><?= show_date($v['last_purchase']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="?id=<?= $v['id'] ?>"><i class="bi bi-pencil"></i></a></td></tr>
            <?php endforeach; ?>
            <?php if (!$vendors): ?><tr><td colspan="7" class="text-center text-muted py-4">No vendors yet.</td></tr><?php endif; ?>
        </table></div></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
