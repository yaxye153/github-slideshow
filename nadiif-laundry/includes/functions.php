<?php
// =====================================================================
// functions.php - small helper functions used by every page.
// Each function does ONE simple job. Read the comment above it.
// =====================================================================


// ---------------------------------------------------------------------
// SYSTEM / SESSION / DATABASE
// ---------------------------------------------------------------------

// Work out the web address of the system folder (e.g. "/nadiif-laundry").
// This lets you rename the folder without changing any code.
function detect_base_url(): string
{
    $root = str_replace('\\', '/', realpath(dirname(__DIR__)));
    $script = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '');
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    if ($script !== '' && stripos($script, $root) === 0) {
        $relative = substr($script, strlen($root));            // e.g. "/customers/index.php"
        if ($relative !== '' && substr($scriptName, -strlen($relative)) === $relative) {
            return rtrim(substr($scriptName, 0, -strlen($relative)), '/');
        }
    }
    return '';
}

// Start the PHP session with safe cookie settings
function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('NADIIFSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Connect to MySQL using PDO
function db_connect(string $host, string $name, string $user, string $pass): PDO
{
    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // stop on SQL errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows as ['column' => value]
        PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
    ]);
}

// Run a query with parameters and return the statement
function db_query(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Return one value (e.g. a SUM or COUNT)
function db_value(PDO $pdo, string $sql, array $params = [])
{
    return db_query($pdo, $sql, $params)->fetchColumn();
}

// Return one row or null
function db_row(PDO $pdo, string $sql, array $params = []): ?array
{
    $row = db_query($pdo, $sql, $params)->fetch();
    return $row === false ? null : $row;
}

// Return all rows
function db_all(PDO $pdo, string $sql, array $params = []): array
{
    return db_query($pdo, $sql, $params)->fetchAll();
}


// ---------------------------------------------------------------------
// SETTINGS
// ---------------------------------------------------------------------

// Default settings (used when a value has not been saved yet)
function default_settings(): array
{
    return [
        'business_name'   => 'NADIIF LAUNDRY',
        'business_phone'  => '',
        'business_address'=> '',
        'currency_code'   => 'USD',
        'currency_symbol' => '$',
        'receipt_footer'  => 'Thank you for choosing NADIIF LAUNDRY!',
        'timezone'        => 'Africa/Mogadishu',
        'theme'           => 'blue',
        // Service packages: how many hours until the order is ready
        'speed_normal_hours'    => '48',
        'speed_silver_hours'    => '12',
        'speed_gold_hours'      => '4',
        // Customer levels: discount in %
        'tier_standard_discount'=> '0',
        'tier_premium_discount' => '0',
        'tier_vip_discount'     => '0',
        // Message to the customer when the order is ready (WhatsApp / SMS)
        'country_code'          => '252',
        'ready_message'         => 'Hello {customer}, your laundry order {order} is READY at {business}. Balance: {balance}. Shelf: {shelf}. Thank you!',
        // Security report: keep tried passwords masked (safer) or full
        'log_tried_passwords'   => 'masked',
    ];
}

// Load all settings from the database into an array
function load_settings(PDO $pdo): array
{
    $settings = default_settings();
    foreach (db_all($pdo, 'SELECT setting_key, setting_value FROM settings') as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

// Read one setting
function setting(string $key, string $default = ''): string
{
    global $SETTINGS;
    return (string)($SETTINGS[$key] ?? $default);
}

// Save one setting
function save_setting(PDO $pdo, string $key, string $value): void
{
    db_query($pdo, 'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
}


// ---------------------------------------------------------------------
// OUTPUT / NAVIGATION / MESSAGES
// ---------------------------------------------------------------------

// Escape text before showing it in HTML (protects against XSS)
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Build a link inside the system, e.g. url('customers/index.php')
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

// Go to another page and stop
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// Save a message to show on the next page (type: success, danger, warning, info)
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

// Show and clear the saved messages
function show_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
              . e($f['message'])
              . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// Format money, e.g. money(12.5) -> "$12.50"
function money($amount): string
{
    $amount = (float)$amount;
    $sign = $amount < 0 ? '-' : '';
    return $sign . setting('currency_symbol', '$') . number_format(abs($amount), 2);
}

// Format a date for display, e.g. 2026-10-06 -> 06 Oct 2026
function show_date(?string $date): string
{
    if (!$date || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d M Y', $ts) : e($date);
}

// ---------------------------------------------------------------------
// SECURITY: CSRF + DOUBLE-SUBMIT PROTECTION
// ---------------------------------------------------------------------
// Every form gets a one-time secret token. When the form is submitted
// the token is used up. If the user clicks "Save" twice, the second
// submit has an already-used token and is ignored, so no duplicate
// record is created. The token also blocks forged requests (CSRF).

// Print the hidden token field inside a <form>
function csrf_field(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['form_tokens'][$token] = time();

    // Keep only the newest 300 tokens so the session stays small
    if (count($_SESSION['form_tokens']) > 300) {
        $_SESSION['form_tokens'] = array_slice($_SESSION['form_tokens'], -300, null, true);
    }
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

// Check (and use up) the token of a submitted form
function verify_csrf(): bool
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token !== '' && isset($_SESSION['form_tokens'][$token])) {
        unset($_SESSION['form_tokens'][$token]);
        return true;
    }
    return false;
}

// Stop if the token is missing/used, and send the user back with a message
function require_csrf(string $backTo): void
{
    if (!verify_csrf()) {
        global $pdo;
        if ($pdo instanceof PDO) {
            log_security($pdo, 'bad_form_token', 'info', 'Form sent twice, expired, or sent from another website');
        }
        flash('warning', 'This form was already submitted or has expired. Please check the list before trying again.');
        redirect($backTo);
    }
}


// ---------------------------------------------------------------------
// READING FORM INPUT
// ---------------------------------------------------------------------

// Get a trimmed text value from the submitted form
function post_text(string $name, int $maxLength = 255): string
{
    $value = trim((string)($_POST[$name] ?? ''));
    return mb_substr($value, 0, $maxLength);
}

// Get a money value (returns null if it is not a valid number)
function post_money(string $name): ?float
{
    $value = str_replace(',', '', trim((string)($_POST[$name] ?? '')));
    if ($value === '') {
        return 0.0;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return round((float)$value, 2);
}

// Check a date written as YYYY-MM-DD
function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Get a date from the URL (?from=2026-01-01) or use a default
function get_date(string $name, string $default): string
{
    $value = (string)($_GET[$name] ?? '');
    return valid_date($value) ? $value : $default;
}

// Make sure a value is one of the allowed choices
function in_list(string $value, array $allowed, string $default): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}


// ---------------------------------------------------------------------
// LISTS USED IN DROPDOWNS
// ---------------------------------------------------------------------

function order_statuses(): array
{
    return ['Received', 'Washing', 'Drying', 'Ironing', 'Ready', 'Out for Delivery', 'Delivered', 'Cancelled'];
}

function status_color(string $status): string
{
    $colors = [
        'Received' => 'secondary', 'Washing' => 'info', 'Drying' => 'info', 'Ironing' => 'primary',
        'Ready' => 'warning', 'Out for Delivery' => 'primary', 'Delivered' => 'success', 'Cancelled' => 'danger',
        'Paid' => 'success', 'Partial' => 'warning', 'Unpaid' => 'danger',
        'Active' => 'success', 'Inactive' => 'secondary',
    ];
    return $colors[$status] ?? 'secondary';
}

function badge(string $text): string
{
    $color = status_color($text);
    $textClass = in_array($color, ['warning', 'info'], true) ? ' text-dark' : '';
    return '<span class="badge bg-' . $color . $textClass . '">' . e($text) . '</span>';
}

function payment_methods(): array
{
    return ['Cash', 'EVC Plus', 'Bank', 'Other'];
}

// Income types typed by hand. "Laundry Order" income is NOT typed here:
// it is created automatically from order payments (prevents double counting).
function manual_income_types(): array
{
    return ['Delivery Income', 'Other Income'];
}

function all_income_types(): array
{
    return ['Laundry Order', 'Delivery Income', 'Other Income'];
}

// All expense categories
function expense_categories(): array
{
    return ['Salary', 'Delivery', 'Electricity', 'Water', 'Rent', 'Detergent', 'Soap', 'Packaging', 'Fuel',
            'Maintenance', 'Equipment', 'Internet', 'Phone', 'Cleaning Materials', 'Other'];
}

// Expense categories typed by hand. Salary and Delivery are recorded in
// their own modules and copied to expenses automatically.
function manual_expense_categories(): array
{
    return array_values(array_diff(expense_categories(), ['Salary', 'Delivery']));
}

function daily_running_categories(): array
{
    return ['Electricity', 'Water', 'Fuel', 'Tea/Coffee', 'Cleaning Materials', 'Small Repairs', 'Transport',
            'Packaging', 'Other'];
}

function monthly_running_categories(): array
{
    return ['Rent', 'Electricity', 'Water', 'Internet', 'Security', 'Equipment Maintenance', 'Subscriptions', 'Other'];
}

function salary_types(): array
{
    return ['Monthly', 'Weekly', 'Daily'];
}

function laundry_item_names(): array
{
    return ['Shirt', 'Trousers', 'Dress', 'Suit', 'Jacket', 'Abaya', 'T-Shirt', 'Bedsheet', 'Blanket', 'Curtain', 'Other'];
}

function month_names(): array
{
    return [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September',
            'October', 'November', 'December'];
}

// Active service types from the database (Wash, Wash & Iron, ...)
function service_types(PDO $pdo): array
{
    return db_query($pdo, 'SELECT name FROM service_types WHERE is_active = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
}

// Print <option> tags for a dropdown
function options(array $values, string $selected = ''): string
{
    $html = '';
    foreach ($values as $v) {
        $html .= '<option value="' . e($v) . '"' . ((string)$v === $selected ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $html;
}


// ---------------------------------------------------------------------
// ORDERS
// ---------------------------------------------------------------------

// Work out the payment status from the total and the amount paid
function payment_status(float $total, float $paid): string
{
    if ($paid <= 0) {
        return $total <= 0 ? 'Paid' : 'Unpaid';
    }
    return $paid >= $total ? 'Paid' : 'Partial';
}

// Recalculate an order's money from the real order_items and payments rows.
//   Subtotal     = sum of item totals (Quantity x Price)
//   Speed charge = Subtotal x speed % (only old orders; packages now use their own prices)
//   Discount     = (Subtotal + Speed charge) x customer level %
//   Total        = Subtotal + Speed charge - Discount
//   Balance      = Total - Amount Paid
// The % values are saved on the order, so changing Settings later
// never changes the price of old orders.
function recalc_order(PDO $pdo, int $orderId): void
{
    $order = db_row($pdo, 'SELECT speed_percent, discount_percent FROM orders WHERE id = ?', [$orderId]);
    $subtotal = (float)db_value($pdo, 'SELECT COALESCE(SUM(total), 0) FROM order_items WHERE order_id = ?', [$orderId]);
    $money = order_money($subtotal, (float)$order['speed_percent'], (float)$order['discount_percent']);

    // Amount paid = sum of all payments for this order
    $paid = (float)db_value($pdo, 'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = ?', [$orderId]);
    $balance = round($money['total'] - $paid, 2);

    db_query($pdo, 'UPDATE orders SET subtotal = ?, speed_charge = ?, discount_amount = ?, total_amount = ?, amount_paid = ?, balance = ?,
                    payment_status = ? WHERE id = ?',
        [$money['subtotal'], $money['speed_charge'], $money['discount'], $money['total'], $paid, $balance,
         payment_status($money['total'], $paid), $orderId]);
}

// Calculate order total from the subtotal, speed % and discount %
function order_money(float $subtotal, float $speedPercent, float $discountPercent): array
{
    $subtotal = round($subtotal, 2);
    $speedCharge = round($subtotal * $speedPercent / 100, 2);
    $discount = round(($subtotal + $speedCharge) * $discountPercent / 100, 2);
    return ['subtotal' => $subtotal, 'speed_charge' => $speedCharge, 'discount' => $discount,
            'total' => round($subtotal + $speedCharge - $discount, 2)];
}


// ---------------------------------------------------------------------
// SERVICE PACKAGES (Normal / Silver / Gold) AND CUSTOMER LEVELS
// ---------------------------------------------------------------------

// ['Normal' => ['hours' => 48], 'Silver' => ['hours' => 12], 'Gold' => ['hours' => 4]]
// Each package has its own prices in the Price List.
function service_speeds(): array
{
    $speeds = [];
    foreach (['Normal' => ['normal', '48'], 'Silver' => ['silver', '12'], 'Gold' => ['gold', '4']] as $name => [$key, $default]) {
        $speeds[$name] = ['hours' => max(1, (int)setting('speed_' . $key . '_hours', $default)), 'percent' => 0.0];
    }
    return $speeds;
}

// Customer levels with their automatic discount %
function customer_tiers(): array
{
    return [
        'Standard' => (float)setting('tier_standard_discount', '0'),
        'Premium'  => (float)setting('tier_premium_discount', '0'),
        'VIP'      => (float)setting('tier_vip_discount', '0'),
    ];
}

function tier_badge(string $tier): string
{
    $colors = ['VIP' => 'background:#6f42c1;color:#fff', 'Premium' => 'background:#0dcaf0;color:#000', 'Standard' => 'background:#e9ecef;color:#495057'];
    $icon = $tier === 'Standard' ? '' : '<i class="bi bi-star-fill"></i> ';
    return '<span class="badge" style="' . ($colors[$tier] ?? $colors['Standard']) . '">' . $icon . e($tier) . '</span>';
}

function speed_badge(string $speed): string
{
    $styles = ['Gold' => 'background:#d4a017;color:#000', 'Silver' => 'background:#adb5bd;color:#000', 'Normal' => 'background:#f8f9fa;color:#495057;border:1px solid #dee2e6'];
    $icon = $speed === 'Normal' ? '' : '<i class="bi bi-lightning-charge-fill"></i> ';
    return '<span class="badge" style="' . ($styles[$speed] ?? $styles['Normal']) . '">' . $icon . e($speed) . '</span>';
}

// Show when an order will be ready: "in 5 h", "OVERDUE 2 h", ...
function ready_label(array $order): string
{
    if (in_array($order['status'], ['Delivered', 'Cancelled'], true)) {
        return '';
    }
    $when = $order['ready_at'] ?? null;
    if (!$when) {
        return '';
    }
    $diff = strtotime($when) - time();
    $hours = (int)floor(abs($diff) / 3600);
    $text = $hours >= 48 ? floor($hours / 24) . ' days' : ($hours > 0 ? $hours . ' h' : floor(abs($diff) / 60) . ' min');
    if ($diff < 0 && $order['status'] !== 'Ready') {
        return '<span class="badge bg-danger">OVERDUE ' . $text . '</span>';
    }
    return $diff < 0 ? '' : '<span class="badge bg-info text-dark">in ' . $text . '</span>';
}

// Show date + time, e.g. 06 Oct 2026 14:30
function show_datetime(?string $value): string
{
    $ts = $value ? strtotime($value) : false;
    return $ts ? date('d M Y H:i', $ts) : '-';
}


// ---------------------------------------------------------------------
// PRICE LIST
// ---------------------------------------------------------------------

// All saved prices: ['shirt|wash' => ['Normal' => 1.5, 'Silver' => 2.5, 'Gold' => 4], ...]
// (lower case keys; an empty Silver/Gold price uses the Normal price)
function price_list_map(PDO $pdo): array
{
    $map = [];
    foreach (db_all($pdo, 'SELECT item_name, service_type, price, price_silver, price_gold FROM price_list') as $r) {
        $map[mb_strtolower($r['item_name'] . '|' . $r['service_type'])] = [
            'Normal' => (float)$r['price'],
            'Silver' => $r['price_silver'] !== null ? (float)$r['price_silver'] : (float)$r['price'],
            'Gold' => $r['price_gold'] !== null ? (float)$r['price_gold'] : (float)$r['price'],
        ];
    }
    return $map;
}


// ---------------------------------------------------------------------
// THEMES
// ---------------------------------------------------------------------

function themes(): array
{
    return ['blue' => 'Blue (Classic)', 'green' => 'Green (Fresh)', 'dark' => 'Dark (Night)'];
}

// Attributes for the <html> tag, e.g. data-theme="green"
function theme_attributes(): string
{
    $theme = array_key_exists(setting('theme', 'blue'), themes()) ? setting('theme', 'blue') : 'blue';
    return 'data-theme="' . $theme . '"' . ($theme === 'dark' ? ' data-bs-theme="dark"' : '');
}


// ---------------------------------------------------------------------
// DATABASE UPGRADES
// ---------------------------------------------------------------------
// When a new version adds tables or columns, they are added here
// automatically. Existing data is never deleted.

const APP_DB_VERSION = 4;

function column_exists(PDO $pdo, string $table, string $column): bool
{
    return (bool)db_value($pdo, 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]);
}

function run_upgrades(PDO $pdo): void
{
    // Version 2: price list, customer levels, service speed, shelf number
    $pdo->exec("CREATE TABLE IF NOT EXISTS `price_list` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `item_name` VARCHAR(100) NOT NULL, `service_type` VARCHAR(50) NOT NULL,
        `price` DECIMAL(12,2) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uq_price_item_service` (`item_name`, `service_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = [
        ['customers', 'tier', "VARCHAR(10) NOT NULL DEFAULT 'Normal' AFTER `phone`"],
        ['orders', 'ready_at', 'DATETIME NULL AFTER `expected_date`'],
        ['orders', 'service_speed', "VARCHAR(10) NOT NULL DEFAULT 'Normal' AFTER `ready_at`"],
        ['orders', 'shelf_number', 'VARCHAR(20) NULL AFTER `service_speed`'],
        ['orders', 'subtotal', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `status`'],
        ['orders', 'speed_percent', 'DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`'],
        ['orders', 'speed_charge', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `speed_percent`'],
        ['orders', 'discount_percent', 'DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `speed_charge`'],
        ['orders', 'discount_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `discount_percent`'],
    ];
    foreach ($columns as [$table, $column, $definition]) {
        if (!column_exists($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            if ($table === 'orders' && $column === 'subtotal') {
                // Old orders: their total was the item subtotal
                $pdo->exec('UPDATE orders SET subtotal = total_amount');
            }
        }
    }
    if (!db_value($pdo, "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_ready'")) {
        $pdo->exec('ALTER TABLE orders ADD KEY `idx_orders_ready` (`ready_at`)');
    }

    // Version 3: users with permissions, activity log (footprints), security log, visits
    $userColumns = [
        ['permissions', 'TEXT NULL AFTER `role`'],
        ['is_active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `permissions`'],
        ['last_login', 'DATETIME NULL AFTER `is_active`'],
    ];
    foreach ($userColumns as [$column, $definition]) {
        if (!column_exists($pdo, 'users', $column)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `$column` $definition");
        }
    }
    // Version 3+4: create any missing tables from the structure file
    $schema = (string)file_get_contents(APP_ROOT . '/database/nadiif_laundry.sql');
    foreach (sql_split_statements($schema) as $statement) {
        if (preg_match('/^CREATE TABLE IF NOT EXISTS `(activity_log|security_log|page_visits|order_history|assets|vendors|stock_items|stock_batches|stock_moves)`/', $statement)) {
            $pdo->exec($statement);
        }
    }

    // Version 4: who created the order, handover, messages, package prices, tried passwords
    $v4 = [
        ['orders', 'created_by', 'INT UNSIGNED NULL AFTER `notes`'],
        ['orders', 'created_by_name', 'VARCHAR(50) NULL AFTER `created_by`'],
        ['orders', 'received_by', 'VARCHAR(100) NULL AFTER `created_by_name`'],
        ['orders', 'handed_over_at', 'DATETIME NULL AFTER `received_by`'],
        ['orders', 'notified_at', 'DATETIME NULL AFTER `handed_over_at`'],
        ['price_list', 'price_silver', 'DECIMAL(12,2) NULL AFTER `price`'],
        ['price_list', 'price_gold', 'DECIMAL(12,2) NULL AFTER `price_silver`'],
        ['security_log', 'tried_password', 'VARCHAR(100) NULL AFTER `details`'],
    ];
    foreach ($v4 as [$table, $column, $definition]) {
        if (!column_exists($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
    // Renamed packages and customer levels (old names -> new names)
    $pdo->exec("UPDATE orders SET service_speed = 'Silver' WHERE service_speed = 'Express'");
    $pdo->exec("UPDATE orders SET service_speed = 'Gold' WHERE service_speed = 'VIP'");
    $pdo->exec("UPDATE customers SET tier = CASE tier WHEN 'Silver' THEN 'Premium' WHEN 'Gold' THEN 'VIP' WHEN 'Normal' THEN 'Standard' ELSE tier END");
    $pdo->exec("ALTER TABLE customers ALTER COLUMN tier SET DEFAULT 'Standard'");
    $renames = ['tier_normal_discount' => 'tier_standard_discount', 'tier_silver_discount' => 'tier_premium_discount', 'tier_gold_discount' => 'tier_vip_discount'];
    foreach ($renames as $old => $new) {
        db_query($pdo, 'INSERT IGNORE INTO settings (setting_key, setting_value) SELECT ?, setting_value FROM settings WHERE setting_key = ?', [$new, $old]);
    }
    // Express/VIP hours: keep a value the owner changed, otherwise use the new 12 h / 4 h
    $oldExpress = db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key = 'speed_express_hours'");
    $oldVip = db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key = 'speed_vip_hours'");
    if ($oldExpress !== false && $oldExpress !== '24') {
        db_query($pdo, "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('speed_silver_hours', ?)", [$oldExpress]);
    }
    if ($oldVip !== false && $oldVip !== '6') {
        db_query($pdo, "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('speed_gold_hours', ?)", [$oldVip]);
    }

    save_setting($pdo, 'db_version', (string)APP_DB_VERSION);
}

// ---------------------------------------------------------------------
// MONEY LEDGER (income + expenses)
// ---------------------------------------------------------------------
// Payments, salaries, delivery and running costs are copied into the
// income / expenses tables. The UNIQUE (source, source_id) key means a
// record can only ever be copied ONCE - updating it replaces the copy.

// Save (or update) the income copy of a record
function ledger_income_save(PDO $pdo, string $source, int $sourceId, string $date, string $type,
                            string $description, float $amount, string $method, string $reference = '', string $notes = ''): void
{
    db_query($pdo, 'INSERT INTO income (income_date, income_type, description, amount, payment_method, reference, notes, source, source_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE income_date = VALUES(income_date), income_type = VALUES(income_type),
                        description = VALUES(description), amount = VALUES(amount), payment_method = VALUES(payment_method),
                        reference = VALUES(reference), notes = VALUES(notes)',
        [$date, $type, $description, $amount, $method, $reference, $notes, $source, $sourceId]);
}

// Remove the income copy of a record
function ledger_income_delete(PDO $pdo, string $source, int $sourceId): void
{
    db_query($pdo, 'DELETE FROM income WHERE source = ? AND source_id = ?', [$source, $sourceId]);
}

// Save (or update) the expense copy of a record
function ledger_expense_save(PDO $pdo, string $source, int $sourceId, string $date, string $category,
                             string $description, float $amount, string $method, string $reference = '', string $notes = ''): void
{
    db_query($pdo, 'INSERT INTO expenses (expense_date, category, description, amount, payment_method, reference, notes, source, source_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE expense_date = VALUES(expense_date), category = VALUES(category),
                        description = VALUES(description), amount = VALUES(amount), payment_method = VALUES(payment_method),
                        reference = VALUES(reference), notes = VALUES(notes)',
        [$date, $category, $description, $amount, $method, $reference, $notes, $source, $sourceId]);
}

// Remove the expense copy of a record
function ledger_expense_delete(PDO $pdo, string $source, int $sourceId): void
{
    db_query($pdo, 'DELETE FROM expenses WHERE source = ? AND source_id = ?', [$source, $sourceId]);
}

// Friendly name of where an income/expense row came from
function source_label(string $source): string
{
    $labels = [
        'manual' => 'Entered by hand', 'order_payment' => 'Order payment', 'delivery' => 'Delivery',
        'salary' => 'Salary', 'daily_running' => 'Daily running', 'monthly_running' => 'Monthly running',
        'stock' => 'Stock purchase', 'asset' => 'Asset purchase',
    ];
    return $labels[$source] ?? $source;
}

// Page where an automatic row can be edited
function source_link(string $source): string
{
    $links = [
        'order_payment' => 'payments/index.php', 'delivery' => 'delivery/index.php', 'salary' => 'salaries/index.php',
        'daily_running' => 'daily-running/index.php', 'monthly_running' => 'monthly-running/index.php',
        'stock' => 'stock/purchases.php', 'asset' => 'assets/index.php',
    ];
    return url($links[$source] ?? 'dashboard.php');
}

// Total income between two dates (from real income records only)
function total_income(PDO $pdo, string $from, string $to): float
{
    return (float)db_value($pdo, 'SELECT COALESCE(SUM(amount), 0) FROM income WHERE income_date BETWEEN ? AND ?', [$from, $to]);
}

// Total expenses between two dates. Optional: only one source (e.g. 'salary')
function total_expenses(PDO $pdo, string $from, string $to, ?string $source = null): float
{
    $sql = 'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?';
    $params = [$from, $to];
    if ($source !== null) {
        $sql .= ' AND source = ?';
        $params[] = $source;
    }
    return (float)db_value($pdo, $sql, $params);
}

// Show a profit as a green PROFIT or red LOSS label
function profit_label(float $profit): string
{
    if ($profit < 0) {
        return '<span class="badge bg-danger">LOSS</span>';
    }
    return '<span class="badge bg-success">PROFIT</span>';
}


// ---------------------------------------------------------------------
// DATE PERIODS
// ---------------------------------------------------------------------

// Returns [from, to] dates for: today, week, month, year
function period_range(string $period, ?string $date = null): array
{
    $ts = strtotime($date ?? date('Y-m-d'));
    switch ($period) {
        case 'week':   // Monday to Sunday
            $monday = strtotime('monday this week', $ts);
            return [date('Y-m-d', $monday), date('Y-m-d', strtotime('+6 days', $monday))];
        case 'month':
            return [date('Y-m-01', $ts), date('Y-m-t', $ts)];
        case 'year':
            return [date('Y-01-01', $ts), date('Y-12-31', $ts)];
        default:       // today
            return [date('Y-m-d', $ts), date('Y-m-d', $ts)];
    }
}


// ---------------------------------------------------------------------
// LISTS: SIMPLE PAGINATION
// ---------------------------------------------------------------------

// Work out which page of a long list to show
function paginate(int $totalRows, int $perPage = 25): array
{
    $pages = max(1, (int)ceil($totalRows / $perPage));
    $page = max(1, min($pages, (int)($_GET['page'] ?? 1)));
    return ['page' => $page, 'pages' => $pages, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $totalRows];
}

// Print "Previous / Next" links that keep the current search filters
function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $query = $_GET;
    $html = '<nav class="d-flex justify-content-between align-items-center mt-3 no-print"><span class="text-muted small">Page '
          . $p['page'] . ' of ' . $p['pages'] . ' (' . $p['total'] . ' records)</span><div class="btn-group">';
    if ($p['page'] > 1) {
        $query['page'] = $p['page'] - 1;
        $html .= '<a class="btn btn-outline-secondary" href="?' . e(http_build_query($query)) . '">&laquo; Previous</a>';
    }
    if ($p['page'] < $p['pages']) {
        $query['page'] = $p['page'] + 1;
        $html .= '<a class="btn btn-outline-secondary" href="?' . e(http_build_query($query)) . '">Next &raquo;</a>';
    }
    return $html . '</div></nav>';
}


// ---------------------------------------------------------------------
// CSV EXPORT
// ---------------------------------------------------------------------

// Send rows as a CSV file (opens in Excel)
function send_csv(string $filename, array $headings, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // lets Excel read special characters correctly
    fputcsv($out, $headings);
    foreach ($rows as $row) {
        // Stop spreadsheet formulas being run from data (CSV injection)
        $row = array_map(function ($v) {
            $v = (string)$v;
            return ($v !== '' && strpos('=+-@', $v[0]) !== false && !is_numeric($v)) ? "'" . $v : $v;
        }, array_values($row));
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}


// ---------------------------------------------------------------------
// SQL FILES (used by the installer, backup and restore)
// ---------------------------------------------------------------------

// Split an SQL file into single statements.
// Understands quotes and comments, so a ";" inside text does not break it.
function sql_split_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $length = strlen($sql);
    $quote = null;

    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($quote !== null) {                          // inside '...', "..." or `...`
            $current .= $ch;
            if ($ch === '\\' && $quote !== '`') {        // escaped character
                $current .= $next;
                $i++;
            } elseif ($ch === $quote) {
                if ($next === $quote) {                 // doubled quote ''
                    $current .= $next;
                    $i++;
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $current .= $ch;
        } elseif (($ch === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $ch === '#') {
            $end = strpos($sql, "\n", $i);              // skip line comment
            $i = $end === false ? $length : $end;
            $current .= "\n";
        } elseif ($ch === '/' && $next === '*' && ($sql[$i + 2] ?? '') !== '!') {
            $end = strpos($sql, '*/', $i + 2);          // skip block comment
            $i = $end === false ? $length : $end + 1;
            $current .= ' ';
        } elseif ($ch === ';') {
            if (trim($current) !== '') {
                $statements[] = trim($current);
            }
            $current = '';
        } else {
            $current .= $ch;
        }
    }
    if (trim($current) !== '') {
        $statements[] = trim($current);
    }
    return $statements;
}


// ---------------------------------------------------------------------
// BACKUP
// ---------------------------------------------------------------------

// Folder where backup files are saved
function backup_dir(): string
{
    return APP_ROOT . '/backup/files';
}

// Only these file names may be downloaded or deleted
function is_backup_filename(string $name): bool
{
    return (bool)preg_match('/^nadiif_laundry_(backup|pre_restore|auto)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(_\d+)?\.sql$/', $name);
}

// Create database backup: writes every table (structure + data) to a .sql file.
// Returns the file name.
function create_backup(PDO $pdo, string $prefix = 'backup'): string
{
    $dir = backup_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('The backup folder is not writable: ' . $dir);
    }

    $name = 'nadiif_laundry_' . $prefix . '_' . date('Y-m-d_H-i-s') . '.sql';
    $n = 1;
    while (file_exists($dir . '/' . $name)) {     // two backups in the same second
        $name = 'nadiif_laundry_' . $prefix . '_' . date('Y-m-d_H-i-s') . '_' . $n++ . '.sql';
    }
    $path = $dir . '/' . $name;
    $tmp = $path . '.part';
    $fh = fopen($tmp, 'w');

    fwrite($fh, "-- NADIIF LAUNDRY BACKUP\n");
    fwrite($fh, '-- Created: ' . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, '-- Database: ' . DB_NAME . "\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $t) {
        $table = $t[0];
        $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_NUM);
        fwrite($fh, "-- Table: $table\n");
        fwrite($fh, 'DROP TABLE IF EXISTS `' . $table . "`;\n");
        fwrite($fh, $create[1] . ";\n\n");

        // Write the rows, 100 per INSERT statement
        $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
        $batch = [];
        $columns = null;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = '`' . implode('`, `', array_keys($row)) . '`';
            }
            $values = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string)$v);
            }, array_values($row));
            $batch[] = '(' . implode(', ', $values) . ')';
            if (count($batch) === 100) {
                fwrite($fh, 'INSERT INTO `' . $table . "` ($columns) VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            fwrite($fh, 'INSERT INTO `' . $table . "` ($columns) VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        fwrite($fh, "\n");
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n-- END OF BACKUP\n");
    fclose($fh);
    rename($tmp, $path);   // only a complete file gets the .sql name
    return $name;
}

// List saved backup files, newest first
function list_backups(): array
{
    $files = [];
    foreach (glob(backup_dir() . '/*.sql') ?: [] as $path) {
        $name = basename($path);
        if (is_backup_filename($name)) {
            $files[] = ['name' => $name, 'size' => filesize($path), 'time' => filemtime($path)];
        }
    }
    usort($files, function ($a, $b) {
        return $b['time'] <=> $a['time'] ?: strcmp($b['name'], $a['name']);
    });
    return $files;
}

// Show a file size like "12.4 KB"
function human_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    return number_format($bytes / 1024, 1) . ' KB';
}


// ---------------------------------------------------------------------
// SUMMARY CARDS (Income page, Expenses page)
// ---------------------------------------------------------------------

// Today / this week / this month / this year / all time totals
// $table is 'income' or 'expenses' (fixed values, never user input)
function period_totals(PDO $pdo, string $table): array
{
    $dateColumn = $table === 'income' ? 'income_date' : 'expense_date';
    $result = [];
    foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $key => $label) {
        [$from, $to] = period_range($key);
        $result[$label] = (float)db_value($pdo, "SELECT COALESCE(SUM(amount), 0) FROM $table WHERE $dateColumn BETWEEN ? AND ?", [$from, $to]);
    }
    $result['All Time'] = (float)db_value($pdo, "SELECT COALESCE(SUM(amount), 0) FROM $table");
    return $result;
}

// Print the five summary cards
function summary_cards(array $totals, string $color): string
{
    $html = '<div class="row g-3 mb-3">';
    foreach ($totals as $label => $amount) {
        $html .= '<div class="col-6 col-md-4 col-xl"><div class="card stat-card ' . $color . ' shadow-sm h-100"><div class="card-body">'
               . '<div class="stat-label">' . e($label) . '</div><div class="stat-value">' . money($amount) . '</div></div></div></div>';
    }
    return $html . '</div>';
}


// ---------------------------------------------------------------------
// PROFIT & LOSS
// ---------------------------------------------------------------------

// Calculate profit for a period, using real income and expense records only.
// Net Profit = Total Income - Total Expenses
function profit_and_loss(PDO $pdo, string $from, string $to): array
{
    // Income split by type (Laundry Order, Delivery Income, Other Income)
    $incomeByType = [];
    foreach (db_all($pdo, 'SELECT income_type, SUM(amount) AS total FROM income WHERE income_date BETWEEN ? AND ?
                           GROUP BY income_type ORDER BY income_type', [$from, $to]) as $r) {
        $incomeByType[$r['income_type']] = (float)$r['total'];
    }

    // Expenses from each module
    $bySource = ['salary' => 0.0, 'delivery' => 0.0, 'daily_running' => 0.0, 'monthly_running' => 0.0, 'stock' => 0.0, 'asset' => 0.0, 'manual' => 0.0];
    foreach (db_all($pdo, 'SELECT source, SUM(amount) AS total FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY source', [$from, $to]) as $r) {
        $bySource[$r['source']] = (float)$r['total'];
    }

    // Other (manual) expenses split by category (Rent, Electricity, Detergent...)
    $manualByCategory = [];
    foreach (db_all($pdo, "SELECT category, SUM(amount) AS total FROM expenses WHERE source = 'manual' AND expense_date BETWEEN ? AND ?
                           GROUP BY category ORDER BY category", [$from, $to]) as $r) {
        $manualByCategory[$r['category']] = (float)$r['total'];
    }

    $income = array_sum($incomeByType);
    $expenses = array_sum($bySource);
    $orders = db_row($pdo, "SELECT COUNT(*) AS n, COALESCE(SUM(total_amount), 0) AS sales FROM orders
                            WHERE status <> 'Cancelled' AND order_date BETWEEN ? AND ?", [$from, $to]);

    return [
        'from' => $from, 'to' => $to,
        'orders' => (int)$orders['n'], 'sales' => (float)$orders['sales'],
        'income' => $income, 'income_by_type' => $incomeByType,
        'expenses' => $expenses, 'by_source' => $bySource, 'manual_by_category' => $manualByCategory,
        'profit' => round($income - $expenses, 2),
    ];
}


// ---------------------------------------------------------------------
// RESTORE
// ---------------------------------------------------------------------

// Check an SQL backup before restoring it.
// Returns ['statements' => [...], 'error' => '', 'tables' => [...]].
// Only normal backup statements are allowed (CREATE TABLE, INSERT, ...).
// Dangerous statements (users, grants, files, DROP DATABASE...) are refused.
function check_restore_sql(string $sql): array
{
    if (strpos($sql, "\0") !== false || !mb_check_encoding($sql, 'UTF-8')) {
        return ['statements' => [], 'error' => 'This file is not a text SQL backup.'];
    }

    $allowed = '/^(SET|DROP\s+TABLE|CREATE\s+TABLE|INSERT\s+INTO|REPLACE\s+INTO|ALTER\s+TABLE|LOCK\s+TABLES|UNLOCK\s+TABLES|START\s+TRANSACTION|BEGIN|COMMIT)\b/i';
    $skipped = '/^(CREATE\s+DATABASE|USE)\b/i';   // the system always restores into its own database
    $result = [];
    $tables = [];

    foreach (sql_split_statements($sql) as $statement) {
        // Look inside MySQL "/*!40101 ... */" version comments
        $check = trim(preg_replace('/^\/\*!\d*\s*|\s*\*\/$/', '', $statement));
        if ($check === '') {
            continue;
        }
        if (preg_match($skipped, $check)) {
            continue;
        }
        if (!preg_match($allowed, $check)) {
            return ['statements' => [], 'error' => 'The file contains a statement that is not allowed in a backup: "'
                . mb_substr(preg_replace('/\s+/', ' ', $check), 0, 60) . '..."'];
        }
        // Remove text values, then look for file access commands
        $code = preg_replace(["/'(?:[^'\\\\]|\\\\.|'')*'/s", '/"(?:[^"\\\\]|\\\\.|"")*"/s'], "''", $check);
        if ($code === null || preg_match('/\b(INTO\s+(OUTFILE|DUMPFILE)|LOAD_FILE\s*\()/i', $code)) {
            return ['statements' => [], 'error' => 'The file contains file commands that are not allowed.'];
        }
        if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $check, $m)) {
            $tables[] = strtolower($m[1]);
        }
        $result[] = $statement;
    }

    // It must be a NADIIF LAUNDRY backup: these tables must be inside
    foreach (['users', 'settings', 'customers', 'orders'] as $required) {
        if (!in_array($required, $tables, true)) {
            return ['statements' => [], 'error' => 'This does not look like a NADIIF LAUNDRY backup (table "' . $required . '" is missing).'];
        }
    }
    return ['statements' => $result, 'error' => '', 'tables' => array_values(array_unique($tables))];
}

// Restore database backup: run the checked statements
function run_restore(PDO $pdo, array $statements): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}


// ---------------------------------------------------------------------
// DAILY EMAIL BACKUP (Gmail)
// ---------------------------------------------------------------------
// Settings are kept in config/mail.php (NOT in the database), so the
// Gmail App Password is never put inside a backup file or an email.

function mail_config_file(): string
{
    return APP_ROOT . '/config/mail.php';
}

// Read the email backup settings
function mail_config(): array
{
    $defaults = ['enabled' => false, 'gmail' => '', 'app_password' => '', 'send_to' => '', 'send_hour' => 20];
    $file = mail_config_file();
    if (function_exists('opcache_invalidate') && is_file($file)) {
        opcache_invalidate($file, true);    // always read the newest saved settings
    }
    $saved = is_file($file) ? include $file : [];
    return array_merge($defaults, is_array($saved) ? $saved : []);
}

// Save the email backup settings
function save_mail_config(array $config): void
{
    $php = "<?php\n// Email backup settings - written by Backup & Restore > Email Backup.\n"
         . "// Keep this file private: it contains the Gmail App Password.\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents(mail_config_file(), $php, LOCK_EX) === false) {
        throw new RuntimeException('Could not write config/mail.php');
    }
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate(mail_config_file(), true);
    }
}

// Is it time to send today's email backup?
function email_backup_due(?array $config = null, bool $ignoreHour = false): bool
{
    $config = $config ?? mail_config();
    if (!$config['enabled'] || $config['gmail'] === '' || $config['app_password'] === '') {
        return false;
    }
    if (setting('email_backup_last_date') === date('Y-m-d')) {
        return false;                                   // already sent today
    }
    if (!$ignoreHour && (int)date('G') < (int)$config['send_hour']) {
        return false;                                   // too early today
    }
    // After a failed try, wait 1 hour before trying again
    $lastTry = strtotime(setting('email_backup_last_attempt', '')) ?: 0;
    return time() - $lastTry >= 3600;
}

// Create a backup and email it. Returns [true/false, message].
// $force = send now even if already sent today (the "Send test now" button)
function run_email_backup(PDO $pdo, bool $ignoreHour = false, bool $force = false): array
{
    global $SETTINGS;
    require_once APP_ROOT . '/includes/mailer.php';
    $config = mail_config();
    if ($config['gmail'] === '' || $config['app_password'] === '') {
        return [false, 'Enter the Gmail address and App Password first.'];
    }

    // Only one send at a time (two open pages could try together)
    if (!(int)db_value($pdo, "SELECT GET_LOCK('nadiif_email_backup', 0)")) {
        return [false, 'An email backup is already being sent.'];
    }
    $gzPath = null;
    try {
        $SETTINGS = load_settings($pdo);                // fresh values (another page may have just sent it)
        if (!$force && !email_backup_due($config, $ignoreHour)) {
            return [false, 'Not due.'];
        }
        save_setting($pdo, 'email_backup_last_attempt', date('Y-m-d H:i:s'));

        // Create database backup, then compress it (much smaller email)
        $file = create_backup($pdo, 'auto');
        $gzPath = backup_dir() . '/uploads/' . $file . '.gz';
        file_put_contents($gzPath, gzencode((string)file_get_contents(backup_dir() . '/' . $file), 9));
        if (filesize($gzPath) > 18 * 1024 * 1024) {
            throw new RuntimeException('The backup is too large for Gmail (limit 25 MB). Download it from Backup & Restore instead.');
        }

        $to = $config['send_to'] !== '' ? $config['send_to'] : $config['gmail'];
        $business = setting('business_name', 'NADIIF LAUNDRY');
        $text = "Daily backup of $business.\r\n\r\n"
              . 'Created: ' . date('d M Y H:i') . "\r\nFile: $file.gz\r\n\r\n"
              . "To restore: unzip the .gz file (7-Zip or WinRAR) to get the .sql file, then open\r\n"
              . "Backup & Restore > UPLOAD BACKUP in the system.\r\n\r\n"
              . "Keep this email private: it contains all business data.\r\n";
        // smtp_host / smtp_port can be added by hand in config/mail.php to use another provider
        smtp_send($config, $to, $business . ' - daily backup ' . date('Y-m-d'), $text, $gzPath, $file . '.gz',
            ['host' => $config['smtp_host'] ?? 'smtp.gmail.com', 'port' => $config['smtp_port'] ?? 587]);

        save_setting($pdo, 'email_backup_last_date', date('Y-m-d'));
        save_setting($pdo, 'email_backup_last_success', date('Y-m-d H:i:s'));
        save_setting($pdo, 'email_backup_last_message', 'Sent to ' . $to . ' (' . human_size((int)filesize($gzPath)) . ')');
        delete_old_auto_backups(14);
        return [true, 'Backup emailed to ' . $to . '.'];
    } catch (Throwable $e) {
        error_log('Email backup failed: ' . $e->getMessage());
        $message = $e instanceof RuntimeException ? $e->getMessage() : 'Unexpected error (see the PHP error log).';
        save_setting($pdo, 'email_backup_last_message', 'FAILED ' . date('d M Y H:i') . ': ' . $message);
        return [false, $message];
    } finally {
        if ($gzPath && is_file($gzPath)) {
            unlink($gzPath);
        }
        db_value($pdo, "SELECT RELEASE_LOCK('nadiif_email_backup')");
    }
}

// Keep only the newest automatic backups on this computer
function delete_old_auto_backups(int $keep): void
{
    $auto = array_values(array_filter(list_backups(), function ($b) {
        return strpos($b['name'], 'nadiif_laundry_auto_') === 0;
    }));
    foreach (array_slice($auto, $keep) as $old) {
        unlink(backup_dir() . '/' . $old['name']);
    }
}


// ---------------------------------------------------------------------
// USERS AND PERMISSIONS
// ---------------------------------------------------------------------
// The admin has every permission. Other users only get the sections the
// admin ticks for them. Deleting anything is ALWAYS admin only.

// Sections a user can be allowed to use
function permission_list(): array
{
    return [
        'customers'        => 'Customers',
        'orders'           => 'Laundry Orders, Order Tracking, Receipts',
        'payments'         => 'Payments (take money from customers)',
        'income'           => 'Income',
        'expenses'         => 'Expenses',
        'salaries'         => 'Salaries & Employees',
        'delivery'         => 'Delivery',
        'running'          => 'Daily & Monthly Running Costs',
        'stock'            => 'Stock (detergent, soap...) and Vendors',
        'assets'           => 'Company Assets (shelves, computers...)',
        'reports'          => 'Reports and Profit & Loss',
        'finance_dashboard'=> 'See money totals on the Dashboard',
    ];
}

// Which permission each folder needs ('admin' = administrator only)
function page_permission(string $page): ?string
{
    if ($page === 'backup/auto.php') {
        return null;   // background daily tasks: started by any logged-in user
    }
    $map = [
        'stock/' => 'stock', 'assets/' => 'assets',
        'customers/' => 'customers', 'orders/' => 'orders', 'tracking/' => 'orders', 'receipt/' => 'orders',
        'payments/' => 'payments', 'income/' => 'income', 'expenses/' => 'expenses', 'salaries/' => 'salaries',
        'delivery/' => 'delivery', 'daily-running/' => 'running', 'monthly-running/' => 'running', 'reports/' => 'reports',
        'backup/' => 'admin', 'settings/' => 'admin', 'users/' => 'admin', 'security/' => 'admin',
    ];
    foreach ($map as $folder => $permission) {
        if (strpos($page, $folder) === 0) {
            return $permission;
        }
    }
    return null;   // dashboard, My Account, logout: every logged-in user
}

function is_admin(): bool
{
    global $CURRENT_USER;
    return !empty($CURRENT_USER) && $CURRENT_USER['role'] === 'admin';
}

// Can the logged-in user use this section?
function can(string $permission): bool
{
    global $CURRENT_USER;
    if (empty($CURRENT_USER)) {
        return false;
    }
    if ($CURRENT_USER['role'] === 'admin') {
        return true;
    }
    if ($permission === 'admin') {
        return false;
    }
    return in_array($permission, user_permissions($CURRENT_USER), true);
}

function user_permissions(array $user): array
{
    $list = json_decode((string)($user['permissions'] ?? ''), true);
    return is_array($list) ? array_values(array_intersect($list, array_keys(permission_list()))) : [];
}

// This page, e.g. "orders/form.php"
function current_page(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return mb_substr(ltrim(BASE_URL !== '' && strpos($script, BASE_URL) === 0 ? substr($script, strlen(BASE_URL)) : $script, '/'), 0, 100);
}


// ---------------------------------------------------------------------
// SECURITY LOG, ACTIVITY LOG (FOOTPRINTS) AND VISITS
// ---------------------------------------------------------------------

// The visitor's IP address (only REMOTE_ADDR: other headers can be faked)
function client_ip(): string
{
    return mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
}

// Save a security event. Severity: info, warning, danger
function log_security(PDO $pdo, string $event, string $severity, string $details = '', ?string $username = null, ?string $triedPassword = null): void
{
    global $CURRENT_USER;
    try {
        db_query($pdo, 'INSERT INTO security_log (created_at, event, severity, user_id, username, ip, user_agent, page, details, tried_password)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [date('Y-m-d H:i:s'), $event, $severity, $CURRENT_USER['id'] ?? null,
             mb_substr($username ?? ($CURRENT_USER['username'] ?? ''), 0, 50), client_ip(),
             mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), current_page(), mb_substr($details, 0, 2000),
             $triedPassword !== null ? tried_password_for_log($triedPassword) : null]);
    } catch (Throwable $e) {
        error_log('Could not write security log: ' . $e->getMessage());
    }
}

// Save one footprint (who did what, when, from where)
function log_activity(PDO $pdo, string $action, string $outcome = 'success', string $details = '', ?int $recordId = null): void
{
    global $CURRENT_USER;
    try {
        db_query($pdo, 'INSERT INTO activity_log (created_at, user_id, username, ip, page, action, record_id, outcome, details)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [date('Y-m-d H:i:s'), $CURRENT_USER['id'] ?? null, $CURRENT_USER['username'] ?? null, client_ip(), current_page(),
             mb_substr($action, 0, 100), $recordId, $outcome, mb_substr($details, 0, 2000)]);
    } catch (Throwable $e) {
        error_log('Could not write activity log: ' . $e->getMessage());
    }
}

// Count one visit of this page by this user today
function count_visit(PDO $pdo, int $userId): void
{
    try {
        db_query($pdo, 'INSERT INTO page_visits (visit_date, user_id, page, visits) VALUES (?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE visits = visits + 1', [date('Y-m-d'), $userId, current_page()]);
    } catch (Throwable $e) {
        error_log('Could not count visit: ' . $e->getMessage());
    }
}

// Friendly name of the action of a page, e.g. "orders/form.php" -> "Laundry Orders: save"
function page_action_name(string $page): string
{
    $sections = ['customers' => 'Customers', 'orders' => 'Laundry Orders', 'tracking' => 'Order Tracking', 'payments' => 'Payments',
                 'income' => 'Income', 'expenses' => 'Expenses', 'salaries' => 'Salaries', 'delivery' => 'Delivery',
                 'daily-running' => 'Daily Running', 'monthly-running' => 'Monthly Running', 'reports' => 'Reports',
                 'backup' => 'Backup', 'settings' => 'Settings', 'users' => 'Users', 'security' => 'Security', 'auth' => 'Login'];
    $parts = explode('/', $page);
    $section = count($parts) > 1 ? ($sections[$parts[0]] ?? $parts[0]) : 'System';
    $file = basename($page, '.php');
    $verbs = ['form' => 'save', 'add' => 'add', 'delete' => 'DELETE', 'employee_delete' => 'DELETE employee', 'status' => 'change status',
              'pay' => 'pay salary', 'employee_form' => 'save employee', 'create' => 'create backup', 'restore' => 'restore',
              'prices' => 'save prices', 'services' => 'service types', 'index' => 'update', 'email' => 'email backup settings', 'account' => 'my account'];
    return $section . ': ' . ($verbs[$file] ?? $file);
}

// Form fields to keep in the footprint (never passwords or tokens)
function safe_post_summary(): string
{
    $hidden = ['csrf_token', 'password', 'current_password', 'new_password', 'confirm_password', 'confirm', 'app_password'];
    $parts = [];
    foreach ($_POST as $key => $value) {
        if (in_array($key, $hidden, true) || stripos((string)$key, 'password') !== false) {
            continue;
        }
        if (is_array($value)) {
            $value = implode(', ', array_map(function ($v) { return is_array($v) ? '[...]' : (string)$v; }, array_slice($value, 0, 20)));
        }
        $value = trim((string)$value);
        if ($value !== '') {
            $parts[] = $key . '=' . mb_substr($value, 0, 80);
        }
    }
    return mb_substr(implode('; ', $parts), 0, 1500);
}

// Look for common hacking patterns in what was sent (the system is protected
// against them anyway - this only records the attempt for the admin)
function suspicious_input(): string
{
    $patterns = [
        'SQL injection' => '/(\bunion\b[\s\S]{0,40}\bselect\b|\bor\b\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+|\bsleep\s*\(\s*\d|\bbenchmark\s*\(|information_schema|;\s*drop\s+table|--\s*$|\/\*!)/i',
        'Script injection (XSS)' => '/(<\s*script\b|javascript\s*:|\bon(error|load|mouseover|click)\s*=|<\s*iframe\b|<\s*svg\b[^>]*on)/i',
        'Path traversal' => '/(\.\.[\/\\\\]){2,}|\/etc\/passwd|c:\\\\windows/i',
    ];
    $values = [];
    array_walk_recursive($_GET, function ($v) use (&$values) { $values[] = (string)$v; });
    array_walk_recursive($_POST, function ($v, $k) use (&$values) {
        if (stripos((string)$k, 'password') === false && $k !== 'csrf_token') { $values[] = (string)$v; }
    });
    foreach ($values as $value) {
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $value)) {
                return $name . ': ' . mb_substr($value, 0, 200);
            }
        }
    }
    return '';
}

// Too many wrong passwords? Returns minutes to wait (0 = allowed)
// Rule: 5 failures from the same IP, or 10 for the same username, in 15 minutes
function login_blocked_minutes(PDO $pdo, string $username): int
{
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    $byIp = db_row($pdo, "SELECT COUNT(*) AS n, MAX(created_at) AS last FROM security_log
                          WHERE event = 'login_failed' AND ip = ? AND created_at >= ?", [client_ip(), $since]);
    $byUser = db_row($pdo, "SELECT COUNT(*) AS n, MAX(created_at) AS last FROM security_log
                            WHERE event = 'login_failed' AND username = ? AND created_at >= ?", [$username, $since]);
    $last = null;
    if ((int)$byIp['n'] >= 5) {
        $last = $byIp['last'];
    }
    if ((int)$byUser['n'] >= 10 && (!$last || $byUser['last'] > $last)) {
        $last = $byUser['last'];
    }
    if (!$last) {
        return 0;
    }
    return max(1, (int)ceil((strtotime($last) + 15 * 60 - time()) / 60));
}


// ---------------------------------------------------------------------
// ORDER HISTORY (trace of every step: who and when)
// ---------------------------------------------------------------------

function log_order_step(PDO $pdo, int $orderId, string $status, string $note = ''): void
{
    global $CURRENT_USER;
    db_query($pdo, 'INSERT INTO order_history (order_id, status, changed_at, user_id, username, note) VALUES (?, ?, ?, ?, ?, ?)',
        [$orderId, $status, date('Y-m-d H:i:s'), $CURRENT_USER['id'] ?? null, $CURRENT_USER['username'] ?? null, mb_substr($note, 0, 255)]);
}

// The work steps and the handover steps of an order
function work_steps(): array
{
    return ['Received' => 'bi-inbox', 'Washing' => 'bi-water', 'Drying' => 'bi-wind', 'Ironing' => 'bi-thermometer-high', 'Ready' => 'bi-check2-circle'];
}

function handover_steps(string $pickupType): array
{
    return $pickupType === 'Delivery'
        ? ['Ready' => 'bi-check2-circle', 'Out for Delivery' => 'bi-truck', 'Delivered' => 'bi-house-check']
        : ['Ready' => 'bi-check2-circle', 'Delivered' => 'bi-person-check'];
}

// Next status after the current one (null = finished)
function next_status(array $order): ?string
{
    $flow = ['Received' => 'Washing', 'Washing' => 'Drying', 'Drying' => 'Ironing', 'Ironing' => 'Ready',
             'Ready' => $order['pickup_type'] === 'Delivery' ? 'Out for Delivery' : 'Delivered', 'Out for Delivery' => 'Delivered'];
    return $flow[$order['status']] ?? null;
}

// Button text for the next step
function next_status_label(array $order, string $next): string
{
    if ($next === 'Delivered') {
        return $order['pickup_type'] === 'Delivery' ? 'Delivered to customer' : 'Picked up by customer';
    }
    return 'Move to ' . $next;
}


// ---------------------------------------------------------------------
// MESSAGE TO THE CUSTOMER (WhatsApp / SMS)
// ---------------------------------------------------------------------

// 0615123456 -> 252615123456 (international format, digits only)
function international_phone(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    $cc = preg_replace('/\D/', '', setting('country_code', '252'));
    if ($digits === '') {
        return '';
    }
    if (strpos($digits, '00') === 0) {
        return substr($digits, 2);
    }
    if ($cc !== '' && strpos($digits, $cc) === 0 && strlen($digits) > 9) {
        return $digits;
    }
    return $cc . ltrim($digits, '0');
}

// The "order is ready" text, with {customer}, {order}, {balance}, {shelf}, {business} filled in
function ready_message_text(array $order): string
{
    return strtr(setting('ready_message'), [
        '{customer}' => $order['full_name'] ?? '',
        '{order}' => $order['order_number'] ?? '',
        '{balance}' => money($order['balance'] ?? 0),
        '{total}' => money($order['total_amount'] ?? 0),
        '{shelf}' => ($order['shelf_number'] ?? '') !== '' ? $order['shelf_number'] : '-',
        '{business}' => setting('business_name'),
        '{phone}' => setting('business_phone'),
    ]);
}


// ---------------------------------------------------------------------
// TRIED PASSWORDS (security report)
// ---------------------------------------------------------------------

// Masked: "ad••••56 (8)"  -  Full: the password as typed
function tried_password_for_log(string $password): string
{
    $password = mb_substr($password, 0, 60);
    if (setting('log_tried_passwords', 'masked') === 'full') {
        return $password;
    }
    $len = mb_strlen($password);
    if ($len <= 4) {
        return str_repeat('•', $len) . ' (' . $len . ')';
    }
    return mb_substr($password, 0, 2) . str_repeat('•', max(2, $len - 4)) . mb_substr($password, -2) . ' (' . $len . ')';
}


// ---------------------------------------------------------------------
// STOCK (detergent, soap, starch...)
// ---------------------------------------------------------------------

function stock_categories(): array
{
    return ['Detergent', 'Soap', 'Starch', 'Softener', 'Bleach', 'Stain Remover', 'Packaging', 'Hangers', 'Other'];
}

function stock_units(): array
{
    return ['kg', 'g', 'L', 'ml', 'pcs', 'box', 'bag', 'bottle'];
}

// Expense category used when stock is bought
function stock_expense_category(string $category): string
{
    $map = ['Detergent' => 'Detergent', 'Soap' => 'Soap', 'Packaging' => 'Packaging', 'Hangers' => 'Packaging'];
    return $map[$category] ?? 'Cleaning Materials';
}

// Stock alerts: low stock, expired, expiring within 30 days
function stock_alerts(PDO $pdo): array
{
    $today = date('Y-m-d');
    $low = db_all($pdo, 'SELECT i.id, i.name, i.unit, i.min_quantity, COALESCE(SUM(b.qty_left), 0) AS qty
        FROM stock_items i LEFT JOIN stock_batches b ON b.item_id = i.id
        WHERE i.is_active = 1 GROUP BY i.id HAVING qty <= i.min_quantity ORDER BY qty');
    $expired = db_all($pdo, 'SELECT b.id, b.expiry_date, b.qty_left, i.name, i.unit FROM stock_batches b JOIN stock_items i ON i.id = b.item_id
        WHERE b.qty_left > 0 AND b.expiry_date IS NOT NULL AND b.expiry_date < ? ORDER BY b.expiry_date', [$today]);
    $expiring = db_all($pdo, 'SELECT b.id, b.expiry_date, b.qty_left, i.name, i.unit FROM stock_batches b JOIN stock_items i ON i.id = b.item_id
        WHERE b.qty_left > 0 AND b.expiry_date IS NOT NULL AND b.expiry_date BETWEEN ? AND ? ORDER BY b.expiry_date',
        [$today, date('Y-m-d', strtotime('+30 days'))]);
    return ['low' => $low, 'expired' => $expired, 'expiring' => $expiring, 'count' => count($low) + count($expired) + count($expiring)];
}

// Show a stock quantity without useless zeros: 2.500 -> 2.5
function qty($value): string
{
    return rtrim(rtrim(number_format((float)$value, 3, '.', ','), '0'), '.');
}


// ---------------------------------------------------------------------
// CRASH PROTECTION
// ---------------------------------------------------------------------

// Make a local backup once a day (kept: newest 14), even without Gmail.
// Runs at most once per day; any error is only logged, never shown.
function daily_local_backup(PDO $pdo): void
{
    if (setting('last_auto_local_backup') === date('Y-m-d')) {
        return;
    }
    if (!(int)db_value($pdo, "SELECT GET_LOCK('nadiif_local_backup', 0)")) {
        return;
    }
    try {
        if ((string)db_value($pdo, "SELECT setting_value FROM settings WHERE setting_key = 'last_auto_local_backup'") !== date('Y-m-d')) {
            save_setting($pdo, 'last_auto_local_backup', date('Y-m-d'));
            create_backup($pdo, 'auto');
            delete_old_auto_backups(14);
        }
    } catch (Throwable $e) {
        error_log('Daily local backup failed: ' . $e->getMessage());
    } finally {
        db_value($pdo, "SELECT RELEASE_LOCK('nadiif_local_backup')");
    }
}


// ---------------------------------------------------------------------
// COMPANY ASSETS
// ---------------------------------------------------------------------

function asset_categories(): array
{
    return ['Shelf', 'Computer', 'Printer', 'Phone', 'Washing Machine', 'Dryer', 'Iron', 'Vehicle', 'Furniture', 'Other'];
}

function asset_conditions(): array
{
    return ['Good', 'Needs Repair', 'Broken'];
}
