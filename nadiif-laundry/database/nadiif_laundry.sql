-- =====================================================================
-- NADIIF LAUNDRY - Database structure
-- =====================================================================
-- This file is run automatically by the installer (install/).
-- You do NOT need to import it by hand.
--
-- Money rule used by the whole system:
--   * ALL income  lives in the `income`   table.
--   * ALL expenses live in the `expenses` table.
--   Order payments, salary payments, delivery costs and running costs
--   are copied into income/expenses automatically, with `source` and
--   `source_id` pointing back to the original record. The UNIQUE key
--   on (source, source_id) makes it impossible to count the same
--   payment twice.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Users who can log in (the administrator)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL DEFAULT 'Administrator',
  `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
  `permissions` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Business settings (name, phone, currency, ...)
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` TEXT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service types (Wash, Wash & Iron, ...)
CREATE TABLE IF NOT EXISTS `service_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price list: the price of each item + service for each package
-- (price = Normal, price_silver = Silver, price_gold = Gold; empty = Normal price).
-- The order form fills in these prices automatically.
CREATE TABLE IF NOT EXISTS `price_list` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_name` VARCHAR(100) NOT NULL,
  `service_type` VARCHAR(50) NOT NULL,
  `price` DECIMAL(12,2) NOT NULL,
  `price_silver` DECIMAL(12,2) NULL,
  `price_gold` DECIMAL(12,2) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_item_service` (`item_name`, `service_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers (tier: Standard, Premium, VIP)
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code` VARCHAR(20) NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `tier` VARCHAR(10) NOT NULL DEFAULT 'Standard',
  `alt_phone` VARCHAR(30) NULL,
  `address` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `registration_date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_code` (`customer_code`),
  KEY `idx_customers_name` (`full_name`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Laundry orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(20) NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `order_date` DATE NOT NULL,
  `expected_date` DATE NULL,
  `ready_at` DATETIME NULL,
  `service_speed` VARCHAR(10) NOT NULL DEFAULT 'Normal',
  `shelf_number` VARCHAR(20) NULL,
  `pickup_type` VARCHAR(20) NOT NULL DEFAULT 'Pickup',
  `delivery_address` VARCHAR(255) NULL,
  `delivery_phone` VARCHAR(30) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Received',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `speed_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `speed_charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` VARCHAR(10) NOT NULL DEFAULT 'Unpaid',
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_by_name` VARCHAR(50) NULL,
  `received_by` VARCHAR(100) NULL,
  `handed_over_at` DATETIME NULL,
  `notified_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_number` (`order_number`),
  KEY `idx_orders_customer` (`customer_id`),
  KEY `idx_orders_date` (`order_date`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_ready` (`ready_at`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items inside each order (Shirt x 3, Suit x 1, ...)
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `item_name` VARCHAR(100) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `service_type` VARCHAR(50) NOT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_items_order` (`order_id`),
  CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer payments for orders (each one is also copied into `income`)
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'Cash',
  `reference` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payments_order` (`order_id`),
  KEY `idx_payments_date` (`payment_date`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ALL business income
-- source = 'manual'        -> typed in the Income page
-- source = 'order_payment' -> copied from payments.id
-- source = 'delivery'      -> copied from deliveries.id (delivery income)
CREATE TABLE IF NOT EXISTS `income` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `income_date` DATE NOT NULL,
  `income_type` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'Cash',
  `reference` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `source` VARCHAR(20) NOT NULL DEFAULT 'manual',
  `source_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_income_source` (`source`, `source_id`),
  KEY `idx_income_date` (`income_date`),
  KEY `idx_income_type` (`income_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ALL business expenses
-- source = 'manual'          -> typed in the Expenses page
-- source = 'salary'          -> copied from salary_payments.id
-- source = 'delivery'        -> copied from deliveries.id (delivery cost)
-- source = 'daily_running'   -> copied from daily_running_costs.id
-- source = 'monthly_running' -> copied from monthly_running_costs.id
-- source = 'stock'           -> copied from stock_batches.id (stock purchase)
-- source = 'asset'           -> copied from assets.id (when the purchase is an expense)
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_date` DATE NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'Cash',
  `reference` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `source` VARCHAR(20) NOT NULL DEFAULT 'manual',
  `source_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expenses_source` (`source`, `source_id`),
  KEY `idx_expenses_date` (`expense_date`),
  KEY `idx_expenses_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employees
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `position` VARCHAR(50) NULL,
  `salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `salary_type` VARCHAR(10) NOT NULL DEFAULT 'Monthly',
  `start_date` DATE NULL,
  `status` VARCHAR(10) NOT NULL DEFAULT 'Active',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employees_name` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Salary payments (each one is also copied into `expenses`)
CREATE TABLE IF NOT EXISTS `salary_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `salary_period` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'Cash',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_salary_employee` (`employee_id`),
  KEY `idx_salary_date` (`payment_date`),
  CONSTRAINT `fk_salary_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deliveries (cost -> expenses, income -> income, when applicable)
CREATE TABLE IF NOT EXISTS `deliveries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NULL,
  `delivery_person` VARCHAR(100) NULL,
  `delivery_date` DATE NOT NULL,
  `delivery_address` VARCHAR(255) NULL,
  `delivery_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cost_is_expense` TINYINT(1) NOT NULL DEFAULT 1,
  `delivery_income` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` VARCHAR(10) NOT NULL DEFAULT 'Unpaid',
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'Cash',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_deliveries_date` (`delivery_date`),
  KEY `idx_deliveries_order` (`order_id`),
  KEY `idx_deliveries_customer` (`customer_id`),
  CONSTRAINT `fk_deliveries_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_deliveries_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Everyday running costs (each one is also copied into `expenses`)
CREATE TABLE IF NOT EXISTS `daily_running_costs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cost_date` DATE NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'Cash',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_daily_date` (`cost_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monthly / recurring running costs (each one is also copied into `expenses`)
CREATE TABLE IF NOT EXISTS `monthly_running_costs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cost_month` TINYINT UNSIGNED NOT NULL,
  `cost_year` SMALLINT UNSIGNED NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'Cash',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_monthly_period` (`cost_year`, `cost_month`),
  KEY `idx_monthly_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Footprints: every save / edit / delete / login done in the system
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` DATETIME NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `username` VARCHAR(50) NULL,
  `ip` VARCHAR(45) NULL,
  `page` VARCHAR(100) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `record_id` INT UNSIGNED NULL,
  `outcome` VARCHAR(10) NOT NULL DEFAULT 'success',
  `details` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_date` (`created_at`),
  KEY `idx_activity_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security events: failed logins, blocked logins, forbidden pages, suspicious input...
CREATE TABLE IF NOT EXISTS `security_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` DATETIME NOT NULL,
  `event` VARCHAR(50) NOT NULL,
  `severity` VARCHAR(10) NOT NULL DEFAULT 'info',
  `user_id` INT UNSIGNED NULL,
  `username` VARCHAR(50) NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `page` VARCHAR(100) NULL,
  `details` TEXT NULL,
  `tried_password` VARCHAR(100) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_security_date` (`created_at`),
  KEY `idx_security_event` (`event`),
  KEY `idx_security_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visits: how many times each page was opened, per user per day
CREATE TABLE IF NOT EXISTS `page_visits` (
  `visit_date` DATE NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `page` VARCHAR(100) NOT NULL,
  `visits` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`visit_date`, `user_id`, `page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order trace: every step of every order (who and when)
CREATE TABLE IF NOT EXISTS `order_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `changed_at` DATETIME NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `username` VARCHAR(50) NULL,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_history_order` (`order_id`),
  CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company assets: shelves, computers, machines, vehicles...
CREATE TABLE IF NOT EXISTS `assets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asset_code` VARCHAR(30) NULL,
  `name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(30) NOT NULL,
  `location` VARCHAR(100) NULL,
  `serial_number` VARCHAR(100) NULL,
  `purchase_date` DATE NULL,
  `purchase_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cost_is_expense` TINYINT(1) NOT NULL DEFAULT 0,
  `condition_status` VARCHAR(20) NOT NULL DEFAULT 'Good',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assets_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendors (where stock is bought)
CREATE TABLE IF NOT EXISTS `vendors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `address` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vendors_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock items (detergent, soap, starch...)
CREATE TABLE IF NOT EXISTS `stock_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(30) NOT NULL,
  `unit` VARCHAR(10) NOT NULL DEFAULT 'kg',
  `min_quantity` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_items_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock purchases (each one is a batch with its own expiry date).
-- The cost is copied to `expenses` (source = 'stock').
CREATE TABLE IF NOT EXISTS `stock_batches` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` INT UNSIGNED NOT NULL,
  `vendor_id` INT UNSIGNED NULL,
  `purchase_date` DATE NOT NULL,
  `expiry_date` DATE NULL,
  `qty_in` DECIMAL(12,3) NOT NULL,
  `qty_left` DECIMAL(12,3) NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(20) NOT NULL DEFAULT 'Cash',
  `reference` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_by_name` VARCHAR(50) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batches_item` (`item_id`),
  KEY `idx_batches_expiry` (`expiry_date`),
  CONSTRAINT `fk_batches_item` FOREIGN KEY (`item_id`) REFERENCES `stock_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_batches_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock used / thrown away (taken from the batch that expires first)
CREATE TABLE IF NOT EXISTS `stock_moves` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` INT UNSIGNED NOT NULL,
  `batch_id` INT UNSIGNED NOT NULL,
  `move_date` DATE NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL,
  `reason` VARCHAR(30) NOT NULL DEFAULT 'Used',
  `notes` VARCHAR(255) NULL,
  `created_by_name` VARCHAR(50) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_moves_item` (`item_id`),
  KEY `idx_moves_date` (`move_date`),
  CONSTRAINT `fk_moves_item` FOREIGN KEY (`item_id`) REFERENCES `stock_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_moves_batch` FOREIGN KEY (`batch_id`) REFERENCES `stock_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
