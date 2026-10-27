-- ==============================================================================
-- Smart Laundry Booking & Delivery Management System
-- Complete Installation Schema (Phases 1, 2, and 3)
--
-- Pure PHP & MySQL / MariaDB (XAMPP Compatible)
-- Zero real customer data, passwords, payment info, or QR tracking tokens.
-- Contains safe default seeds and demo accounts for local development.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:30";

-- ------------------------------------------------------------------------------
-- 1. Users Table (Core: Administrators, Customers, Delivery Staff)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','customer','delivery') NOT NULL DEFAULT 'customer',
  `address` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Services Catalogue (Core: Wash & Fold, Dry Cleaning, Ironing, etc.)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service_name` varchar(100) NOT NULL,
  `description` varchar(500) NOT NULL,
  `price` decimal(10,2) NOT NULL CHECK (`price` > 0),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Pickup Time Slots (Phase 2: Operational Pickup Windows)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `pickup_slots`;
CREATE TABLE `pickup_slots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slot_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `daily_capacity` int(10) unsigned NOT NULL DEFAULT 8,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slot_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Discount Coupons (Phase 2: Promo Engine)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('fixed','percent') NOT NULL DEFAULT 'fixed',
  `discount_value` decimal(10,2) NOT NULL,
  `min_booking_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(10) unsigned DEFAULT NULL,
  `times_used` int(10) unsigned NOT NULL DEFAULT 0,
  `valid_from` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_coupon_code_active` (`code`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Expense Categories (Phase 2: Operational Overhead Classification)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_exp_cat_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. Inventory Categories (Phase 3: Consumables & Stock Categories)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `inventory_categories`;
CREATE TABLE `inventory_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_category_name` (`name`),
  KEY `idx_inv_cat_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. Inventory Suppliers (Phase 3: Vendor Directory)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `inventory_suppliers`;
CREATE TABLE `inventory_suppliers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_name` (`name`),
  KEY `idx_supplier_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. Bookings Table (Core + Phase 1 Invoices/Tokens + Phase 2 Coupons/Slots)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_number` varchar(30) NOT NULL,
  `invoice_number` varchar(40) DEFAULT NULL,
  `tracking_token` varchar(64) DEFAULT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `pickup_address` text NOT NULL,
  `pickup_phone` varchar(25) NOT NULL,
  `pickup_date` date NOT NULL,
  `subtotal_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coupon_id` int(10) unsigned DEFAULT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `pickup_slot_id` int(10) unsigned DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL CHECK (`total_amount` > 0),
  `payment_method` enum('COD') NOT NULL DEFAULT 'COD',
  `payment_status` enum('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid',
  `booking_status` enum('Pending','Accepted','Rejected','Picked Up','Washing','Ironing','Ready for Delivery','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `special_instructions` text NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `collected_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_number` (`booking_number`),
  UNIQUE KEY `uq_booking_invoice_number` (`invoice_number`),
  UNIQUE KEY `uq_booking_tracking_token` (`tracking_token`),
  KEY `collected_by` (`collected_by`),
  KEY `customer_id` (`customer_id`,`created_at`),
  KEY `booking_status` (`booking_status`),
  KEY `payment_status` (`payment_status`,`paid_at`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 9. Booking Items Table (Itemized Line Items with Historical Price Snapshots)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `booking_items`;
CREATE TABLE `booking_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` between 1 and 100),
  `unit_price` decimal(10,2) NOT NULL CHECK (`unit_price` > 0),
  `subtotal` decimal(12,2) NOT NULL CHECK (`subtotal` > 0),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`,`service_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `booking_items_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_items_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 10. Delivery Assignments Table
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `delivery_assignments`;
CREATE TABLE `delivery_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `delivery_person_id` int(10) unsigned NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `delivery_person_id` (`delivery_person_id`),
  CONSTRAINT `delivery_assignments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delivery_assignments_ibfk_2` FOREIGN KEY (`delivery_person_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 11. Booking Status History (State Transitions & Immutable Audit Trail)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `booking_status_history`;
CREATE TABLE `booking_status_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `status` varchar(30) NOT NULL,
  `changed_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `changed_by` (`changed_by`),
  KEY `booking_id` (`booking_id`,`created_at`),
  CONSTRAINT `booking_status_history_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 12. Notifications (Phase 1: Real-Time In-App Event Alerts)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `booking_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id_read_created` (`user_id`,`is_read`,`created_at`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 13. Coupon Redemptions (Phase 2: Customer Promo Audit Log)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `coupon_redemptions`;
CREATE TABLE `coupon_redemptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` int(10) unsigned NOT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_booking_coupon` (`booking_id`),
  KEY `idx_redemptions_coupon` (`coupon_id`),
  KEY `idx_redemptions_customer` (`customer_id`),
  CONSTRAINT `fk_redemption_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_redemption_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_redemption_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 14. Customer Reviews & Ratings (Phase 2: Post-Delivery Feedback)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `idx_reviews_rating` (`rating`),
  KEY `idx_reviews_customer` (`customer_id`),
  CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 15. Operational Expenses (Phase 2: Overhead Tracking & Net Profit)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expenses_date` (`expense_date`),
  KEY `idx_expenses_cat` (`category_id`),
  KEY `fk_expense_user` (`created_by`),
  CONSTRAINT `fk_expense_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `fk_expense_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 16. Inventory Items (Phase 3: Consumables Catalogue)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'Pieces',
  `min_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `purchase_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `current_stock` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_item_sku` (`sku`),
  KEY `idx_inv_item_cat` (`category_id`),
  KEY `idx_inv_item_supplier` (`supplier_id`),
  KEY `idx_inv_item_active` (`is_active`),
  KEY `idx_inv_item_stock` (`current_stock`,`min_stock`),
  CONSTRAINT `fk_inv_item_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_item_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `inventory_suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 17. Inventory Movements (Phase 3: Immutable Stock Ledger & Row Locking)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `inventory_movements`;
CREATE TABLE `inventory_movements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `movement_type` enum('IN','OUT','ADJUST') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `performed_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mov_item_date` (`item_id`,`created_at`),
  KEY `idx_mov_type` (`movement_type`),
  KEY `idx_mov_performed` (`performed_by`),
  KEY `idx_mov_supplier` (`supplier_id`),
  CONSTRAINT `fk_mov_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `inventory_suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 18. Inventory Alert State (Phase 3: Deduplication & State Machine)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `inventory_alert_state`;
CREATE TABLE `inventory_alert_state` (
  `item_id` int(10) unsigned NOT NULL,
  `is_alerted` tinyint(1) NOT NULL DEFAULT 0,
  `last_alerted_at` datetime DEFAULT NULL,
  `last_resolved_at` datetime DEFAULT NULL,
  `alert_level` enum('NONE','LOW','OUT_OF_STOCK') NOT NULL DEFAULT 'NONE',
  PRIMARY KEY (`item_id`),
  KEY `idx_alert_state` (`is_alerted`,`alert_level`),
  CONSTRAINT `fk_alert_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 19. Staff Attendance (Phase 3: Shift Logs & Concurrency Control)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `staff_attendance`;
CREATE TABLE `staff_attendance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in` datetime NOT NULL,
  `check_out` datetime DEFAULT NULL,
  `working_hours` decimal(5,2) DEFAULT NULL,
  `status` enum('present','late','half_day') NOT NULL DEFAULT 'present',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_att_user_date` (`user_id`,`attendance_date`),
  KEY `idx_att_checkin` (`check_in`),
  KEY `idx_att_active` (`user_id`,`check_out`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 20. Database Backup History (Phase 3: Verified Backup Audit Registry)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `database_backup_history`;
CREATE TABLE `database_backup_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(190) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `table_count` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('verified','failed') NOT NULL DEFAULT 'verified',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backup_filename` (`filename`),
  KEY `idx_backup_created` (`created_at`),
  CONSTRAINT `fk_backup_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- CLEAN SEED DATA (Standard Services, Slots, Categories & Development Accounts)
-- ==============================================================================

-- 1. Standard Laundry Services
INSERT INTO `services` (`id`, `service_name`, `description`, `price`, `is_active`) VALUES
(1, 'Wash & Fold', 'Freshly washed, carefully dried and neatly folded everyday laundry.', 250.00, 1),
(2, 'Wash & Iron', 'Everyday garments washed and pressed with crisp finish, ready to wear.', 350.00, 1),
(3, 'Ironing Only', 'Professional steam pressing for washed garments.', 150.00, 1),
(4, 'Dry Cleaning', 'Specialist solvent cleaning for suits, dresses and delicate fabrics.', 500.00, 1),
(5, 'Bedsheet Cleaning', 'Deep thermal laundering and sanitization for bed sheets and linens.', 400.00, 1),
(6, 'Blanket Cleaning', 'Heavy-duty washing and gentle drying for blankets and duvets.', 800.00, 1)
ON DUPLICATE KEY UPDATE `service_name` = VALUES(`service_name`);

-- 2. Standard Pickup Time Slots
INSERT INTO `pickup_slots` (`id`, `slot_name`, `start_time`, `end_time`, `daily_capacity`, `is_active`) VALUES
(1, 'Morning Window (08:00 - 11:00)', '08:00:00', '11:00:00', 8, 1),
(2, 'Midday Window (11:00 - 14:00)', '11:00:00', '14:00:00', 8, 1),
(3, 'Afternoon Window (14:00 - 17:00)', '14:00:00', '17:00:00', 8, 1),
(4, 'Evening Window (17:00 - 20:00)', '17:00:00', '20:00:00', 8, 1)
ON DUPLICATE KEY UPDATE `slot_name` = VALUES(`slot_name`);

-- 3. Standard Expense Overhead Categories
INSERT INTO `expense_categories` (`id`, `name`, `description`, `is_active`) VALUES
(1, 'Cleaning Supplies', 'Detergents, stain removers, softeners, bleach', 1),
(2, 'Packaging & Bags', 'Poly bags, hangers, garment covers, tags', 1),
(3, 'Utilities (Water & Power)', 'Electricity, water supply, gas/heating for dryers', 1),
(4, 'Equipment & Maintenance', 'Washer/dryer repairs, iron maintenance, spare parts', 1),
(5, 'Delivery & Fuel', 'Driver fuel allowances, delivery vehicle maintenance', 1),
(6, 'Rent & Facility', 'Shop/hub rent, property rates, facility upkeep', 1),
(7, 'Staff & Wages', 'Casual labor, overtime, operational staff stipends', 1),
(8, 'Marketing & Promotion', 'Pamphlets, local flyers, social promotions', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 4. Standard Inventory Categories
INSERT INTO `inventory_categories` (`id`, `name`, `description`, `is_active`) VALUES
(1, 'Detergents', 'Industrial and gentle laundry washing detergents', 1),
(2, 'Fabric Softeners', 'Fabric conditioners and softening liquids', 1),
(3, 'Cleaning Chemicals', 'Stain removers, bleaches, and specialized washing agents', 1),
(4, 'Packaging Materials', 'Garment covers, hanger wraps, and protective bags', 1),
(5, 'Laundry Bags', 'Heavy-duty collection and delivery laundry bags', 1),
(6, 'Other Supplies', 'General operational, ironing, and maintenance supplies', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 5. Standard Development Demo Accounts (Default Passwords: Role@123)
-- Admin:    admin@laundry.com    / Admin@123
-- Customer: customer@laundry.com / Customer@123
-- Delivery: delivery@laundry.com / Delivery@123
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `address`, `is_active`) VALUES
(1, 'Admin', 'admin@laundry.com', '0771234567', '$2y$10$ivKlpi.TpTtdDeUNLTHXi.7dwtEJpQlBehJO5Q1KG6ykrKIths59y', 'admin', 'Smart Laundry Headquarters, Colombo', 1),
(2, 'Demo Customer', 'customer@laundry.com', '0771234568', '$2y$10$5NmM0EJu1m8nWkuTah7Gv.YljOSJBJcTQHT4ND9H62DDoryeLIgP.', 'customer', '25 Flower Road, Colombo 07', 1),
(3, 'Demo Driver', 'delivery@laundry.com', '0771234569', '$2y$10$C/G1bTSGGaL3EDrv.RAu4.jWzrRU7WbYyaWcvAXwbFR4JPhIzf.N6', 'delivery', 'Central Logistics Hub, Colombo 03', 1)
ON DUPLICATE KEY UPDATE `role` = VALUES(`role`), `is_active` = 1;

-- 6. Sample Promotional Discount Coupons
INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_booking_amount`, `max_discount_amount`, `usage_limit`, `times_used`, `is_active`) VALUES
(1, 'WELCOME10', 'percent', 10.00, 500.00, 500.00, 100, 0, 1),
(2, 'FLAT100', 'fixed', 100.00, 600.00, NULL, 50, 0, 1)
ON DUPLICATE KEY UPDATE `code` = VALUES(`code`);

-- 7. Sample Inventory Suppliers
INSERT INTO `inventory_suppliers` (`id`, `name`, `contact_person`, `email`, `phone`, `address`, `is_active`) VALUES
(1, 'Lanka Hygiene Supplies', 'Sunil Perera', 'sales@lankahygiene.lk', '0112345678', '12 Industrial Zone, Peliyagoda', 1),
(2, 'CleanTech Chemicals', 'Kamal Silva', 'orders@cleantech.lk', '0118765432', '45 Kelaniya Road, Colombo', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 8. Sample Consumable Inventory Items
INSERT INTO `inventory_items` (`id`, `category_id`, `supplier_id`, `sku`, `name`, `unit`, `min_stock`, `purchase_cost`, `current_stock`, `is_active`) VALUES
(1, 1, 1, 'DET-IND-20L', 'Industrial Detergent 20L', 'Liters', 5.00, 4500.00, 25.00, 1),
(2, 2, 1, 'SOF-PREM-10L', 'Premium Fabric Softener 10L', 'Liters', 4.00, 3200.00, 18.00, 1),
(3, 4, 2, 'PKG-POLY-500', 'Garment Poly Covers (Roll 500)', 'Rolls', 2.00, 2800.00, 10.00, 1)
ON DUPLICATE KEY UPDATE `sku` = VALUES(`sku`);

-- 9. Initialize Alert State for Inventory Items
INSERT INTO `inventory_alert_state` (`item_id`, `is_alerted`, `alert_level`) VALUES
(1, 0, 'NONE'),
(2, 0, 'NONE'),
(3, 0, 'NONE')
ON DUPLICATE KEY UPDATE `alert_level` = VALUES(`alert_level`);

