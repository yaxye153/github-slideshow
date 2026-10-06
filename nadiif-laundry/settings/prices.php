<?php
// settings/prices.php - PRICE LIST
// Enter the normal price of each item for each service ONE time.
// The New Order form then fills in these prices automatically.
require_once __DIR__ . '/../auth/auth_check.php';

$services = service_types($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('settings/prices.php');

    $itemNames = (array)($_POST['items'] ?? []);
    $prices = (array)($_POST['price'] ?? []);
    $errors = [];
    $save = [];   // [item, service, price or null]

    foreach ($itemNames as $i => $item) {
        $item = mb_substr(trim((string)$item), 0, 100);
        if ($item === '') {
            continue;
        }
        foreach ($services as $j => $service) {
            $raw = str_replace(',', '', trim((string)($prices[$i][$j] ?? '')));
            if ($raw === '') {
                $save[] = [$item, $service, null];          // empty = no price
            } elseif (!is_numeric($raw) || (float)$raw < 0) {
                $errors[] = $item . ' / ' . $service . ': please enter a price of 0 or more.';
            } else {
                $save[] = [$item, $service, round((float)$raw, 2)];
            }
        }
    }

    if ($errors) {
        foreach ($errors as $err) {
            flash('danger', $err);
        }
        redirect('settings/prices.php');
    }

    // Save all prices together
    $pdo->beginTransaction();
    try {
        $upsert = $pdo->prepare('INSERT INTO price_list (item_name, service_type, price) VALUES (?, ?, ?)
                                 ON DUPLICATE KEY UPDATE price = VALUES(price)');
        $delete = $pdo->prepare('DELETE FROM price_list WHERE item_name = ? AND service_type = ?');
        foreach ($save as [$item, $service, $price]) {
            if ($price === null) {
                $delete->execute([$item, $service]);
            } else {
                $upsert->execute([$item, $service, $price]);
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    flash('success', 'Price list saved. New orders will use these prices automatically.');
    redirect('settings/prices.php');
}

// Current prices as $current['Shirt']['Wash'] = 1.50
$current = [];
foreach (db_all($pdo, 'SELECT item_name, service_type, price FROM price_list ORDER BY item_name') as $r) {
    $current[$r['item_name']][$r['service_type']] = $r['price'];
}
// Rows: items that have prices + the common item names
$rows = array_keys($current);
foreach (laundry_item_names() as $name) {
    if ($name !== 'Other' && !in_array(mb_strtolower($name), array_map('mb_strtolower', $rows), true)) {
        $rows[] = $name;
    }
}

$pageTitle = 'Price List';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-tags"></i> Price List</h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Settings</a>
</div>
<p class="text-muted small">Enter each price once. When you add an item to a new order and choose the service, the price is filled in automatically.
    Leave a box empty if you do not offer that service for the item. Changing a price here does <b>not</b> change old orders.</p>

<form method="post" class="card shadow-sm">
    <?= csrf_field() ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th style="min-width: 150px">Item</th>
                <?php foreach ($services as $service): ?><th style="min-width: 100px"><?= e($service) ?></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $item): ?>
                <tr>
                    <td><input type="hidden" name="items[<?= $i ?>]" value="<?= e($item) ?>"><b><?= e($item) ?></b></td>
                    <?php foreach ($services as $j => $service): ?>
                        <td><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="price[<?= $i ?>][<?= $j ?>]"
                                   value="<?= isset($current[$item][$service]) ? e($current[$item][$service]) : '' ?>" placeholder="-"></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php for ($k = 0; $k < 3; $k++): $i = count($rows) + $k; ?>
                <tr class="table-light">
                    <td><input class="form-control form-control-sm" name="items[<?= $i ?>]" placeholder="New item name" maxlength="100"></td>
                    <?php foreach ($services as $j => $service): ?>
                        <td><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="price[<?= $i ?>][<?= $j ?>]" placeholder="-"></td>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Price List</button>
        <a class="btn btn-outline-secondary ms-2" href="services.php">Manage service types</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
