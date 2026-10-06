<?php
// customers/form.php - add a new customer or edit an existing one
require_once __DIR__ . '/../auth/auth_check.php';

$id = (int)($_GET['id'] ?? 0);
$customer = ['full_name' => '', 'tier' => 'Normal', 'phone' => '', 'alt_phone' => '', 'address' => '', 'notes' => '', 'registration_date' => date('Y-m-d')];

// Get customer information when editing
if ($id) {
    $customer = db_row($pdo, 'SELECT * FROM customers WHERE id = ?', [$id]);
    if (!$customer) {
        flash('danger', 'Customer not found.');
        redirect('customers/index.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer = [
        'full_name' => post_text('full_name', 100),
        'phone' => post_text('phone', 30),
        'tier' => in_list(post_text('tier', 10), array_keys(customer_tiers()), 'Normal'),
        'alt_phone' => post_text('alt_phone', 30),
        'address' => post_text('address', 255),
        'notes' => post_text('notes', 2000),
        'registration_date' => post_text('registration_date', 10),
    ] + $customer;

    // Validate
    if ($customer['full_name'] === '' || $customer['phone'] === '') {
        $errors[] = 'Please enter the required information (name and phone).';
    }
    if (!valid_date($customer['registration_date'])) {
        $errors[] = 'Please enter a valid registration date.';
    }

    if (!$errors) {
        require_csrf('customers/index.php');

        if ($id) {
            db_query($pdo, 'UPDATE customers SET full_name = ?, tier = ?, phone = ?, alt_phone = ?, address = ?, notes = ?, registration_date = ? WHERE id = ?',
                [$customer['full_name'], $customer['tier'], $customer['phone'], $customer['alt_phone'], $customer['address'], $customer['notes'], $customer['registration_date'], $id]);
            flash('success', 'Customer updated successfully.');
        } else {
            // Save customer, then give it a code like C0001
            $pdo->beginTransaction();
            db_query($pdo, 'INSERT INTO customers (full_name, tier, phone, alt_phone, address, notes, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$customer['full_name'], $customer['tier'], $customer['phone'], $customer['alt_phone'], $customer['address'], $customer['notes'], $customer['registration_date']]);
            $id = (int)$pdo->lastInsertId();
            db_query($pdo, 'UPDATE customers SET customer_code = ? WHERE id = ?', [sprintf('C%04d', $id), $id]);
            $pdo->commit();
            flash('success', 'Customer added successfully.');
        }

        // Came here from the "New Order" page? Go back there with this customer selected.
        if (($_GET['return'] ?? '') === 'order') {
            redirect('orders/form.php?customer_id=' . $id);
        }
        redirect('customers/view.php?id=' . $id);
    }
}

$pageTitle = $id ? 'Edit Customer' : 'Add Customer';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><?= $id ? 'Edit Customer' : 'Add Customer' ?></h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card card-body shadow-sm" style="max-width: 760px">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Full Name *</label>
            <input class="form-control" name="full_name" value="<?= e($customer['full_name']) ?>" required maxlength="100">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Customer Level</label>
            <select class="form-select" name="tier">
                <?php foreach (customer_tiers() as $tier => $discount): ?>
                    <option value="<?= $tier ?>" <?= $customer['tier'] === $tier ? 'selected' : '' ?>><?= $tier ?><?= $discount > 0 ? ' (' . (float)$discount . '% discount)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Registration Date</label>
            <input class="form-control" type="date" name="registration_date" value="<?= e($customer['registration_date']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Phone *</label>
            <input class="form-control" type="tel" name="phone" value="<?= e($customer['phone']) ?>" required maxlength="30">
        </div>
        <div class="col-md-6">
            <label class="form-label">Alternative Phone</label>
            <input class="form-control" type="tel" name="alt_phone" value="<?= e($customer['alt_phone']) ?>" maxlength="30">
        </div>
        <div class="col-12">
            <label class="form-label">Address</label>
            <input class="form-control" name="address" value="<?= e($customer['address']) ?>" maxlength="255">
        </div>
        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2"><?= e($customer['notes']) ?></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Customer</button>
        </div>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
