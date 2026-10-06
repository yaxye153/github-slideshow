<?php
// settings/prices.php - PRICE LIST
// Enter the price of each item for each service ONE time, for each package:
//   Normal (e.g. 2 days), Silver (e.g. 12 hours), Gold (e.g. 4 hours).
// The New Order form fills in the price of the chosen package automatically.
require_once __DIR__ . '/../auth/auth_check.php';

$services = service_types($pdo);
$packages = ['Normal' => 'price', 'Silver' => 'price_silver', 'Gold' => 'price_gold'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('settings/prices.php');

    $itemNames = (array)($_POST['items'] ?? []);
    $prices = (array)($_POST['price'] ?? []);
    $errors = [];
    $save = [];   // [item, service, normal, silver, gold]  (null normal = remove)

    // Read one price box: '' = empty, null = invalid
    $read = function ($raw) {
        $raw = str_replace(',', '', trim((string)$raw));
        if ($raw === '') {
            return '';
        }
        return (is_numeric($raw) && (float)$raw >= 0) ? round((float)$raw, 2) : null;
    };

    foreach ($itemNames as $i => $item) {
        $item = mb_substr(trim((string)$item), 0, 100);
        if ($item === '') {
            continue;
        }
        foreach ($services as $j => $service) {
            $values = [];
            foreach ($packages as $package => $column) {
                $values[$package] = $read($prices[$package][$i][$j] ?? '');
                if ($values[$package] === null) {
                    $errors[] = $item . ' / ' . $service . ' (' . $package . '): please enter a price of 0 or more.';
                }
            }
            if ($values['Normal'] === '' && ($values['Silver'] !== '' || $values['Gold'] !== '')) {
                $errors[] = $item . ' / ' . $service . ': enter the Normal price first (Silver and Gold are extra prices).';
            }
            $save[] = [$item, $service, $values['Normal'] === '' ? null : $values['Normal'],
                       $values['Silver'] === '' ? null : $values['Silver'], $values['Gold'] === '' ? null : $values['Gold']];
        }
    }

    if ($errors) {
        foreach (array_slice($errors, 0, 5) as $err) {
            flash('danger', $err);
        }
        redirect('settings/prices.php');
    }

    // Save all prices together
    $pdo->beginTransaction();
    try {
        $upsert = $pdo->prepare('INSERT INTO price_list (item_name, service_type, price, price_silver, price_gold) VALUES (?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE price = VALUES(price), price_silver = VALUES(price_silver), price_gold = VALUES(price_gold)');
        $delete = $pdo->prepare('DELETE FROM price_list WHERE item_name = ? AND service_type = ?');
        foreach ($save as [$item, $service, $normal, $silver, $gold]) {
            if ($normal === null) {
                $delete->execute([$item, $service]);
            } else {
                $upsert->execute([$item, $service, $normal, $silver, $gold]);
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

// Current prices as $current['Shirt']['Wash']['price_silver'] = 2.00
$current = [];
foreach (db_all($pdo, 'SELECT * FROM price_list ORDER BY item_name') as $r) {
    $current[$r['item_name']][$r['service_type']] = $r;
}
// Rows: items that have prices + the common item names
$rows = array_keys($current);
foreach (laundry_item_names() as $name) {
    if ($name !== 'Other' && !in_array(mb_strtolower($name), array_map('mb_strtolower', $rows), true)) {
        $rows[] = $name;
    }
}
$speeds = service_speeds();

$pageTitle = 'Price List';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-tags"></i> Price List</h1>
    <a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Settings</a>
</div>
<p class="text-muted small">Each package has its own price. Fill in the <b>Normal</b> prices first. Silver and Gold are optional:
    an empty Silver/Gold box uses the Normal price. Changing a price here does <b>not</b> change old orders.</p>

<form method="post" class="card shadow-sm">
    <?= csrf_field() ?>
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <?php foreach ($packages as $package => $column): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link<?= $package === 'Normal' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $package ?>" type="button" role="tab">
                        <?= speed_badge($package) ?> <?= $speeds[$package]['hours'] >= 24 && $speeds[$package]['hours'] % 24 === 0 ? ($speeds[$package]['hours'] / 24) . ' days' : $speeds[$package]['hours'] . ' hours' ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="tab-content">
        <?php foreach ($packages as $package => $column): ?>
            <div class="tab-pane fade<?= $package === 'Normal' ? ' show active' : '' ?>" id="tab-<?= $package ?>" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th style="min-width: 150px">Item (<?= $package ?> price)</th>
                            <?php foreach ($services as $service): ?><th style="min-width: 100px"><?= e($service) ?></th><?php endforeach; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($rows as $i => $item): ?>
                            <tr>
                                <td><?php if ($package === 'Normal'): ?><input type="hidden" name="items[<?= $i ?>]" value="<?= e($item) ?>"><?php endif; ?><b><?= e($item) ?></b></td>
                                <?php foreach ($services as $j => $service): $v = $current[$item][$service][$column] ?? null; ?>
                                    <td><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="price[<?= $package ?>][<?= $i ?>][<?= $j ?>]"
                                               value="<?= $v !== null ? e($v) : '' ?>" placeholder="<?= $package === 'Normal' ? '-' : e($current[$item][$service]['price'] ?? '-') ?>"></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php for ($k = 0; $k < 3; $k++): $i = count($rows) + $k; ?>
                            <tr class="table-light">
                                <td><?php if ($package === 'Normal'): ?><input class="form-control form-control-sm" name="items[<?= $i ?>]" placeholder="New item name" maxlength="100"><?php else: ?><span class="text-muted small">new item <?= $k + 1 ?></span><?php endif; ?></td>
                                <?php foreach ($services as $j => $service): ?>
                                    <td><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="price[<?= $package ?>][<?= $i ?>][<?= $j ?>]" placeholder="-"></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card-body">
        <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check-lg"></i> Save Price List</button>
        <a class="btn btn-outline-secondary ms-2" href="services.php">Manage service types</a>
        <a class="btn btn-outline-secondary ms-2" href="index.php#speeds">Package hours</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
