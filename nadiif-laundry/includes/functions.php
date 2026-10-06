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
    return ['Received', 'Washing', 'Drying', 'Ironing', 'Ready', 'Delivered', 'Cancelled'];
}

function status_color(string $status): string
{
    $colors = [
        'Received' => 'secondary', 'Washing' => 'info', 'Drying' => 'info', 'Ironing' => 'primary',
        'Ready' => 'warning', 'Delivered' => 'success', 'Cancelled' => 'danger',
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

// Recalculate an order's total, amount paid, balance and payment status
// from the real order_items and payments rows.
function recalc_order(PDO $pdo, int $orderId): void
{
    // Calculate order total = sum of all item totals
    $total = (float)db_value($pdo, 'SELECT COALESCE(SUM(total), 0) FROM order_items WHERE order_id = ?', [$orderId]);
    // Amount paid = sum of all payments for this order
    $paid = (float)db_value($pdo, 'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = ?', [$orderId]);
    // Balance = Total Amount - Amount Paid
    $balance = round($total - $paid, 2);

    db_query($pdo, 'UPDATE orders SET total_amount = ?, amount_paid = ?, balance = ?, payment_status = ? WHERE id = ?',
        [$total, $paid, $balance, payment_status($total, $paid), $orderId]);
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
    ];
    return $labels[$source] ?? $source;
}

// Page where an automatic row can be edited
function source_link(string $source): string
{
    $links = [
        'order_payment' => 'payments/index.php', 'delivery' => 'delivery/index.php', 'salary' => 'salaries/index.php',
        'daily_running' => 'daily-running/index.php', 'monthly_running' => 'monthly-running/index.php',
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
    return (bool)preg_match('/^nadiif_laundry_(backup|pre_restore)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(_\d+)?\.sql$/', $name);
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
    $bySource = ['salary' => 0.0, 'delivery' => 0.0, 'daily_running' => 0.0, 'monthly_running' => 0.0, 'manual' => 0.0];
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
