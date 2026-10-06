<?php
// navbar.php - the menu.
// Large screens: menu on the left. Phones/tablets: hamburger button.
$menu = [
    ['dashboard.php',             'Dashboard',        'bi-speedometer2'],
    ['customers/index.php',       'Customers',        'bi-people'],
    ['orders/index.php',          'Laundry Orders',   'bi-basket'],
    ['payments/index.php',        'Payments',         'bi-cash-coin'],
    ['income/index.php',          'Income',           'bi-graph-up-arrow'],
    ['expenses/index.php',        'Expenses',         'bi-graph-down-arrow'],
    ['salaries/index.php',        'Salaries',         'bi-person-badge'],
    ['delivery/index.php',        'Delivery',         'bi-truck'],
    ['daily-running/index.php',   'Daily Running',    'bi-calendar-day'],
    ['monthly-running/index.php', 'Monthly Running',  'bi-calendar-month'],
    ['reports/profit_loss.php',   'Profit & Loss',    'bi-bar-chart-line'],
    ['reports/index.php',         'Reports',          'bi-file-earmark-text'],
    ['backup/index.php',          'Backup & Restore', 'bi-database-down'],
    ['settings/index.php',        'Settings',         'bi-gear'],
];
$script = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<!-- Top bar (phones and tablets) -->
<nav class="navbar navbar-dark bg-brand d-lg-none sticky-top no-print">
    <div class="container-fluid">
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand fw-bold me-auto ms-2" href="<?= url('dashboard.php') ?>"><?= e(setting('business_name')) ?></a>
    </div>
</nav>

<!-- Side menu -->
<aside class="offcanvas-lg offcanvas-start sidebar bg-brand text-white no-print" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarLabel"><?= e(setting('business_name')) ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
        <a class="sidebar-brand d-none d-lg-flex" href="<?= url('dashboard.php') ?>">
            <i class="bi bi-basket2-fill me-2"></i><span><?= e(setting('business_name')) ?></span>
        </a>
        <ul class="nav flex-column w-100 py-2">
            <?php foreach ($menu as [$link, $label, $icon]):
                // Highlight the current section of the menu
                $folder = strpos($link, '/') !== false ? dirname($link) . '/' : $link;
                $active = $link === 'reports/profit_loss.php'
                    ? strpos($script, '/reports/profit_loss.php') !== false
                    : (strpos($script, BASE_URL . '/' . $folder) === 0 && strpos($script, '/reports/profit_loss.php') === false);
            ?>
                <li class="nav-item">
                    <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= url($link) ?>"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></a>
                </li>
            <?php endforeach; ?>
            <li class="nav-item mt-2 border-top border-secondary pt-2">
                <a class="nav-link" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
        <div class="small text-white-50 px-3 pb-3 mt-auto">Logged in as <?= e($_SESSION['username'] ?? '') ?></div>
    </div>
</aside>
