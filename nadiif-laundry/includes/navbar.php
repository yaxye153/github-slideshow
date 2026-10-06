<?php
// navbar.php - the menu.
// Large screens: menu on the left. Phones/tablets: hamburger button.
$navMenu = [
    // [link, label, icon, permission needed (null = everyone)]
    ['dashboard.php',             'Dashboard',        'bi-speedometer2',     null],
    ['customers/index.php',       'Customers',        'bi-people',           'customers'],
    ['orders/index.php',          'Laundry Orders',   'bi-basket',           'orders'],
    ['tracking/index.php',        'Order Tracking',   'bi-geo-alt',          'orders'],
    ['payments/index.php',        'Payments',         'bi-cash-coin',        'payments'],
    ['income/index.php',          'Income',           'bi-graph-up-arrow',   'income'],
    ['expenses/index.php',        'Expenses',         'bi-graph-down-arrow', 'expenses'],
    ['salaries/index.php',        'Salaries',         'bi-person-badge',     'salaries'],
    ['delivery/index.php',        'Delivery',         'bi-truck',            'delivery'],
    ['daily-running/index.php',   'Daily Running',    'bi-calendar-day',     'running'],
    ['monthly-running/index.php', 'Monthly Running',  'bi-calendar-month',   'running'],
    ['stock/index.php',           'Stock',            'bi-box-seam',         'stock'],
    ['assets/index.php',          'Company Assets',   'bi-hdd-stack',        'assets'],
    ['reports/profit_loss.php',   'Profit & Loss',    'bi-bar-chart-line',   'reports'],
    ['reports/index.php',         'Reports',          'bi-file-earmark-text','reports'],
    ['users/index.php',           'Users',            'bi-person-gear',      'admin'],
    ['security/index.php',        'Security Report',  'bi-shield-exclamation', 'admin'],
    ['security/activity.php',     'Footprints',       'bi-list-check',       'admin'],
    ['security/health.php',       'System Health',    'bi-heart-pulse',      'admin'],
    ['backup/index.php',          'Backup & Restore', 'bi-database-down',    'admin'],
    ['settings/index.php',        'Settings',         'bi-gear',             'admin'],
    ['account.php',               'My Account',       'bi-person-circle',    null],
];
$navScript = $_SERVER['SCRIPT_NAME'] ?? '';
// Red number next to "Stock" when there are stock alarms
$navStockAlerts = can('stock') ? stock_alerts($pdo)['count'] : 0;
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
            <?php foreach ($navMenu as [$navLink, $navLabel, $navIcon, $navNeeds]):
                if ($navNeeds !== null && !can($navNeeds)) { continue; } // hide sections the user may not use
                // Highlight the current section of the menu
                $navFolder = strpos($navLink, '/') !== false ? dirname($navLink) . '/' : $navLink;
                $navExact = in_array($navLink, ['reports/profit_loss.php', 'security/index.php', 'security/activity.php', 'security/health.php'], true);
                $navActive = $navExact
                    ? $navScript === BASE_URL . '/' . $navLink
                    : (strpos($navScript, BASE_URL . '/' . $navFolder) === 0 && strpos($navScript, '/reports/profit_loss.php') === false);
            ?>
                <li class="nav-item">
                    <a class="nav-link<?= $navActive ? ' active' : '' ?>" href="<?= url($navLink) ?>"><i class="bi <?= $navIcon ?>"></i> <?= e($navLabel) ?><?= $navLink === 'stock/index.php' && $navStockAlerts ? ' <span class="badge bg-danger rounded-pill">' . $navStockAlerts . '</span>' : '' ?></a>
                </li>
            <?php endforeach; ?>
            <li class="nav-item mt-2 border-top border-secondary pt-2">
                <a class="nav-link" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
        <div class="small text-white-50 px-3 pb-3 mt-auto">Logged in as <?= e($_SESSION['username'] ?? '') ?><?= is_admin() ? ' (admin)' : '' ?></div>
    </div>
</aside>
