# NADIIF LAUNDRY — Laundry Management & Business Accounting System

A simple laundry management and accounting system built with plain **PHP 8 + MySQL (PDO) + Bootstrap 5**.
It doesn't use a framework or Node.js, and it doesn't need internet: Bootstrap and its icons are included in `assets/vendor/`.

---

## 1. Installation guide (XAMPP)

1. Install **XAMPP** (PHP 8.0 or newer).
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Copy the `nadiif-laundry` folder to `C:\xampp\htdocs\`.
4. Open **http://localhost/nadiif-laundry/** in your browser.
5. The installer opens on its own:
   - **Step 1 – System Check:** PHP, MySQL, PDO, extensions and folder permissions.
   - **Step 2 – Database:** the default XAMPP values are already filled in (host `localhost`, database `nadiif_laundry`, user `root`, empty password). The database is created for you.
   - **Step 3 – Admin Account:** the default is `admin` / `admin`. You can change it here.
   - **Steps 4 and 5:** clicking **Install Now** creates all tables and the admin account. The password is stored with `password_hash()`.
   - **Step 6:** *NADIIF LAUNDRY HAS BEEN INSTALLED SUCCESSFULLY* → **GO TO LOGIN**.
6. Log in with **admin / admin**. Then go to **Settings → Admin Account** and change the password. The dashboard shows a reminder until you do.

You don't need to create any tables by hand.

**Installation lock:** when installation finishes, the installer writes `config/installed.lock`. After that, opening `/install/` shows *"System has already been installed."* and sends you to the login page.
To reinstall on purpose, delete `config/installed.lock`. Your existing data is **kept**: tables are only created if they are missing, and the admin password is reset to the one you enter.

> Run the installer straight after copying the files. Until it finishes, anyone who can reach the computer can open it.

---

## 2. User guide

| Menu | What it is for |
|---|---|
| **Dashboard** | Today's, this month's and this year's income, expenses and profit/loss; order counts by status; outstanding balance; this month's business costs |
| **Order Tracking** | Pick a customer, or search by phone, name, order number or shelf. You see how many of their orders are in the shop, the **shelf number** of each one, and where it is now: Received → Washing → Drying → Ironing → Ready → Delivered. **Move to next step** and the shelf number can be changed with one tap. VIP and Express orders come first, and **OVERDUE** orders are marked in red |
| **Customers** | Add, edit, view, search (name, phone, customer ID or code) and delete customers. *View* shows the customer's history: total orders, spent, paid, outstanding balance and every order |
| **Laundry Orders** | **New Order** → choose a customer, add items (Item × Qty × Service × Price), and optionally enter the amount paid now. Totals update as you type. Search by order number, customer name, phone, date and status. Change the status from the order page. Print the receipt |
| **Payments** | Record payments for orders. A payment can't be more than the balance. Each payment goes into Income automatically |
| **Income** | All income: order payments, delivery income and "Other Income". Shows today, this week, this month, this year and all-time totals |
| **Expenses** | All expenses, including the ones created automatically. Use **Add Expense** only for things that are *not* salaries, deliveries or running costs |
| **Salaries** | Add employees, then **Pay Salary**. Each salary payment is added to Expenses automatically. You get a warning if you pay the same person twice for the same period |
| **Delivery** | Record deliveries: cost, income and profit. The cost goes to Expenses when "Record cost as business expense" is ticked. The income goes to Income only when its status is **Paid** |
| **Daily Running** | Everyday costs (tea, fuel, small repairs…) with a total for each day. They are added to Expenses automatically |
| **Monthly Running** | Recurring costs (rent, internet, security…) by month and year. They are added to Expenses on the **payment date** |
| **Profit & Loss** | Daily, weekly, monthly, yearly or custom dates: Gross Income − Expenses = Net **PROFIT** or **LOSS** |
| **Reports** | Daily, monthly and yearly summary reports, plus 13 detailed reports. Each one has a date filter, search, Print / PDF and CSV export |
| **Backup & Restore** | Create, download, delete and restore backups |
| **Settings** | Business name, phone, address, currency, time zone, receipt footer, **theme** (Blue, Green or Dark), admin username and password, service types, **Price List**, and **Service Speed and Customer Levels** |

### Price list, service speed and customer levels

- **Price List** (Settings → Price List): enter the price of each item for each service once, for example Shirt / Wash = 1.50. In New Order, typing the item and choosing the service fills in the price automatically. You can still change it for one order. If you type a price yourself, the system does not overwrite it.
- **Service speed:** each order is **Normal**, **Express** or **VIP**. Each speed has a number of hours (the *Ready By* time is calculated from it) and an extra charge in %. Defaults: Normal 48 h, Express 24 h, VIP 6 h, **0% extra**. Set your own extra charge in Settings.
- **Customer levels:** each customer is **Normal**, **Silver** or **Gold**. Each level has an automatic discount in %. The **default discount is 0%**, so set it yourself in Settings.
- **How the order total is calculated:**
  `Subtotal (items) + Express/VIP charge − level discount = Total`.
  Example: subtotal $14, Express +50% → $21, Gold −10% → **$18.90**.
- The % values are saved inside each order. If you change Settings later, **old orders keep their price**.

### Common tasks

- **New order with payment:** Laundry Orders → New Order → add items → type *Amount Paid Now* → Save Order → Receipt.
- **Customer pays the rest later:** open the order → **Add Payment**.
- **Refund or payment entered by mistake:** open the order and delete the payment. It is removed from Income as well.
- **Cancelled order:** set its status to *Cancelled*. Cancelled orders don't count in sales or outstanding balance. Money already paid stays in Income until you delete that payment.
- **Printing the receipt:** the order page → **Receipt**. Choose *Small receipt (80mm)* for a receipt printer or phone, or *A4 page*, then click **Print**.
- **PDF:** click **Print / PDF** on any report and choose **Save as PDF** in the print window.
- **Excel:** click **CSV / Excel**. The `.csv` file opens directly in Excel.

### Backup & restore

- **BACKUP DATABASE** saves `nadiif_laundry_backup_YYYY-MM-DD_HH-MM-SS.sql` in `backup/files/`. It contains the structure and data of every table. **Download it and keep a copy somewhere other than this computer** (USB stick, cloud). A backup that only lives on the same hard disk is lost if that disk fails.
- **UPLOAD BACKUP** works like this:
  1. The file is checked: it must be `.sql`, 64 MB or smaller, and text.
  2. The system confirms it is a NADIIF LAUNDRY backup: it must contain the `users`, `settings`, `customers` and `orders` tables, and only normal backup statements. Statements such as `GRANT`, `DROP DATABASE` or file access are refused.
  3. A warning page appears.
  4. You tick a box and confirm.
  5. A safety backup of the current data is saved automatically as `nadiif_laundry_pre_restore_….sql`. Then the backup is restored. If the restore fails part-way, the system loads the safety backup again on its own.
  6. You see a success message and log in again, using the users stored in the backup.
- You can also restore any file from **Backup History**.
- XAMPP's default upload limit is about 40 MB. For bigger backups, raise `upload_max_filesize` and `post_max_size` in `php.ini`.

---

## 3. How the money is calculated (important)

There is **one income table** and **one expense table**. Every money figure in the dashboard, Profit & Loss and the reports is a `SUM()` over these real records. Nothing is estimated or invented.

| Recorded in | Copied automatically to | Link |
|---|---|---|
| Payments (order payment) | `income` (type *Laundry Order*) | `source='order_payment'`, `source_id = payments.id` |
| Delivery income (when Paid) | `income` (type *Delivery Income*) | `source='delivery'`, `source_id = deliveries.id` |
| Salary payment | `expenses` (category *Salary*) | `source='salary'` |
| Delivery cost (when ticked) | `expenses` (category *Delivery*) | `source='delivery'` |
| Daily running cost | `expenses` | `source='daily_running'` |
| Monthly running cost | `expenses` | `source='monthly_running'` |

**How double counting is prevented:**

- A **UNIQUE key on `(source, source_id)`** means a record can be copied only once. Editing it updates the same copy; deleting it deletes the copy.
- Automatic rows can't be edited or deleted from the Income or Expenses pages. You change them in their own module.
- The manual Income form does not offer "Laundry Order". The manual Expense form does not offer "Salary" or "Delivery".
- Each form carries a one-time token, and the Save button is disabled after the first click. Clicking **Save** twice never creates two records.
- Orders, payments, salaries, deliveries and running costs are saved inside a **database transaction**. If any part fails, nothing is saved.
- A payment locks its order row (`SELECT … FOR UPDATE`), so two payments at the same moment can't overpay an order.

**Accounting rules to know:**

- Income is counted on the **date money is received**. An unpaid order balance is *not* income until it is paid. It appears under *Outstanding Balance*.
- Monthly running costs count on their **payment date**, not the month they are for.
- If you add a delivery fee as an item in the order **and** as Delivery Income, it is counted twice. Use one or the other.
- If you type the same electricity bill in Daily Running, Monthly Running *and* Expenses, it is counted three times. Each cost belongs in one place.

---

## 4. Database explanation

Database name: `nadiif_laundry`. The full structure is in `database/nadiif_laundry.sql` and has comments. All tables use InnoDB and utf8mb4.

| Table | Purpose | Main relationships |
|---|---|---|
| `users` | Login accounts (`password_hash` only) | — |
| `settings` | Key/value business settings | — |
| `service_types` | Wash, Wash & Iron, Iron Only, Dry Clean, Special Cleaning… | — |
| `customers` | Customers (`customer_code` C0001…, `tier` Normal/Silver/Gold) | — |
| `price_list` | Normal price of each item + service (filled into new orders) | UNIQUE (item, service) |
| `orders` | Orders (`order_number` ORD-000001…), `shelf_number`, `service_speed`, `ready_at`, subtotal, speed charge, discount, total, paid, balance and payment status | `customer_id` → customers (RESTRICT) |
| `order_items` | Items in an order: qty × price = total | `order_id` → orders (CASCADE) |
| `payments` | Customer payments | `order_id` → orders (RESTRICT) |
| `income` | **All** income | `source` + `source_id` (UNIQUE) |
| `expenses` | **All** expenses | `source` + `source_id` (UNIQUE) |
| `employees` | Staff | — |
| `salary_payments` | Salary payments | `employee_id` → employees (RESTRICT) |
| `deliveries` | Delivery cost and income | `order_id` → orders, `customer_id` → customers (SET NULL) |
| `daily_running_costs` | Everyday costs | — |
| `monthly_running_costs` | Monthly costs (month, year, payment date) | — |

The order's `total_amount`, `amount_paid`, `balance` and `payment_status` are recalculated from `order_items` and `payments` every time something changes (`recalc_order()` in `includes/functions.php`):

```
Item Total     = Quantity × Price
Subtotal       = sum of item totals
Speed charge   = Subtotal × speed %            (Express / VIP)
Discount       = (Subtotal + Speed charge) × customer level %
Order Total    = Subtotal + Speed charge − Discount
Balance        = Total Amount − Amount Paid
Payment Status = Paid (paid ≥ total) / Partial (some paid) / Unpaid
Net Profit     = Total Income − Total Expenses   (negative = LOSS)
Delivery Profit = Delivery Income − Delivery Cost
```

Indexes are on every date, status, name and phone column that is used for searching and reports.

---

## 5. Folder structure

```
nadiif-laundry/
├── index.php              start page: installer → login → dashboard
├── login.php  logout.php  dashboard.php
├── install/               installation wizard
│   ├── index.php          step 1: system check
│   ├── database.php       step 2: database settings, creates the database
│   ├── install.php        steps 3-6: admin account, create tables, admin, done
│   └── layout.php         installer page layout + installation lock
├── config/database.php    database settings (written by the installer)
├── auth/
│   ├── auth_check.php     put at the top of every protected page
│   └── login_check.php    checks username + password
├── customers/             index (list/search), form (add/edit), view (history), delete
├── orders/                index (list/search), form (new/edit with items), view, status, delete
├── tracking/              index (order tracking: shelf + washing/drying/ironing steps)
├── payments/              index, add, delete
├── income/                index, form, delete
├── expenses/              index, form, delete
├── salaries/              index (payments), pay, delete, employees, employee_form, employee_delete
├── delivery/              index, form, delete
├── daily-running/         index, form, delete
├── monthly-running/       index, form, delete
├── reports/               index, report (13 reports), daily, monthly, yearly, profit_loss
├── backup/                index, create, download, delete, restore; files/ = backups (web access blocked)
├── settings/              index (business, theme, speeds, levels, admin account), services, prices (price list)
├── receipt/print.php      printable receipt (80mm / A4 / mobile)
├── includes/              init.php, functions.php, header.php, navbar.php, footer.php
├── assets/                css/style.css, js/script.js, vendor/ (Bootstrap 5 + icons, offline)
└── database/nadiif_laundry.sql   table structure used by the installer
```

Each page follows the same simple pattern:
`require auth_check.php` → read and validate the form → save with prepared statements → `flash()` a message → `redirect()`.

---

## 6. Security

- PDO with **prepared statements** everywhere (`ATTR_EMULATE_PREPARES = false`).
- `htmlspecialchars()` on every value shown on a page (the `e()` helper).
- `password_hash()` / `password_verify()`. The session ID is regenerated on login, logout and restore. Session cookies are `HttpOnly` and `SameSite=Lax`.
- One-time **CSRF tokens** on every form. Every change uses POST.
- Upload checks: `.sql` only, size limit, content check against a whitelist of statements, stored under a random name in a folder the browser can't open (`.htaccess`).
- Backup download and delete accept only file names that match the backup pattern, so other system files can't be reached.
- PHP errors are hidden from users and written to the PHP error log. Users see a friendly message.
- `config/`, `includes/`, `database/` and `backup/files/` are blocked from the browser with `.htaccess`. This needs Apache `AllowOverride`, which XAMPP enables by default.

---

## 7. Limitations

- There is **one admin role**. Staff accounts with limited permissions are not included.
- "Excel export" is **CSV**, which Excel opens directly. "PDF export" uses the browser's **Print → Save as PDF**. No PDF or Excel library is bundled.
- MySQL can't undo table changes (`DROP` / `CREATE`) inside a transaction. Restore safety therefore comes from the automatic pre-restore backup and automatic re-import if the restore fails, not from a rollback.
- Restore checks each file against a list of allowed statements. A phpMyAdmin or `mysqldump` export of this database usually passes. A dump that contains triggers or stored procedures is refused.
- The system is designed for one shop on a local computer or network. To put it on the internet you need HTTPS and stronger passwords.

---

## 8. Testing instructions

Test these after installing:

1. **Installation:** fresh install → success page → `/install/` now says *already installed*.
2. **Authentication:** open `dashboard.php` while logged out → sent to login. Wrong password → *Invalid username or password.* Log in with `admin` / `admin` → dashboard with the change-password notice. Logout.
3. **Customers:** add, edit, search by name, phone and ID, view. Delete a customer with no orders: it works. Delete a customer with orders: it is refused.
4. **Orders:** create an order with 2+ items. The total updates as you type. Check *Quantity × Price* and the order total. Entering more paid than the total is refused. Double-click **Save Order** → only one order is created. Edit the items. Change the status. Search by number, name, phone, date and status.
5. **Payments:** add a payment. The balance and status change Unpaid → Partial → Paid. A payment bigger than the balance is refused. Delete a payment → the balance goes back up and the income disappears.
6. **Income / Expenses:** add manual records and check the today, week, month, year and total cards.
7. **Salaries:** add an employee and pay a salary → it appears in Expenses once. Edit it → the expense row changes and no second row is added.
8. **Delivery:** cost 2, income 3, Paid → profit 1. Expenses +2, Income +3.
9. **Running costs:** add a daily cost (check the per-day total) and a monthly cost.
10. **Profit & Loss:** check daily, weekly, monthly and yearly. Income − Expenses must equal the Net line, with a PROFIT or LOSS label.
11. **Reports:** open each report. Try the date filter, search, Print and CSV.
12. **Receipt:** print in 80mm and A4. Open it on a phone.
13. **Backup:** create and download a backup. Change some data. Upload the backup → warning → confirm → data is back. A `pre_restore` backup appears in the history. Uploading a `.txt` file or an unrelated `.sql` file is refused.
14. **New features:** set Express +50% and Gold 10%. Add a Gold customer and enter prices in the Price List. In New Order, the prices fill in and the total = (subtotal +50%) −10%. Add a shelf number. In Order Tracking, pick the customer and check the shelf and current step, and that **Move to next step** works. Switch between the 3 themes.
15. **Updating an existing installation:** copy the new files over the old folder and keep `config/database.php` and `config/installed.lock`. On the next page load, the new columns and tables are added automatically. No data is deleted. Make a backup first anyway.
16. **Responsive:** use the browser's device mode at phone (375px), tablet (768px) and desktop widths. The menu becomes a hamburger. Tables scroll inside their box, and the page itself does not scroll sideways.

The developer ran these checks automatically on PHP 8.3 and MariaDB 10.11 (the database XAMPP uses): 172 server-side checks (including upgrading a database from the first version and restoring a backup) and 31 browser checks (automatic prices, totals, all 3 themes on phone size). All passed.
