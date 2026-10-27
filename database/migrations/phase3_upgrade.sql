-- Smart Laundry Management System
-- Phase 3 Advanced Management Upgrade Migration
-- Modules: Inventory Management, Suppliers & Stock Movements, Staff Attendance, Low Stock Alerts, Database Backup & Restore History
--
-- Rollback Instructions:
-- In case of emergency rollback, drop tables in reverse foreign-key order:
--   DROP TABLE IF EXISTS `database_backup_history`;
--   DROP TABLE IF EXISTS `staff_attendance`;
--   DROP TABLE IF EXISTS `inventory_alert_state`;
--   DROP TABLE IF EXISTS `inventory_movements`;
--   DROP TABLE IF EXISTS `inventory_items`;
--   DROP TABLE IF EXISTS `inventory_suppliers`;
--   DROP TABLE IF EXISTS `inventory_categories`;
-- Live backup dump is preserved at C:\xampp\backups\smart-laundry\db_backup_pre_phase3_*.sql

USE `smart_laundry_db`;

-- 1. Create Inventory Categories Table
CREATE TABLE IF NOT EXISTS `inventory_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_category_name` (`name`),
  KEY `idx_inv_cat_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Inventory Suppliers Table
CREATE TABLE IF NOT EXISTS `inventory_suppliers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supplier_name` (`name`),
  KEY `idx_supplier_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create Inventory Items Table
CREATE TABLE IF NOT EXISTS `inventory_items` (
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_item_sku` (`sku`),
  KEY `idx_inv_item_cat` (`category_id`),
  KEY `idx_inv_item_supplier` (`supplier_id`),
  KEY `idx_inv_item_active` (`is_active`),
  KEY `idx_inv_item_stock` (`current_stock`, `min_stock`),
  CONSTRAINT `fk_inv_item_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_item_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `inventory_suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create Inventory Movements (Audit Trail & Ledger) Table
CREATE TABLE IF NOT EXISTS `inventory_movements` (
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mov_item_date` (`item_id`, `created_at`),
  KEY `idx_mov_type` (`movement_type`),
  KEY `idx_mov_performed` (`performed_by`),
  KEY `idx_mov_supplier` (`supplier_id`),
  CONSTRAINT `fk_mov_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `inventory_suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create Inventory Alert State Table (Deduplication & State Management)
CREATE TABLE IF NOT EXISTS `inventory_alert_state` (
  `item_id` int(10) unsigned NOT NULL,
  `is_alerted` tinyint(1) NOT NULL DEFAULT 0,
  `last_alerted_at` datetime DEFAULT NULL,
  `last_resolved_at` datetime DEFAULT NULL,
  `alert_level` enum('NONE','LOW','OUT_OF_STOCK') NOT NULL DEFAULT 'NONE',
  PRIMARY KEY (`item_id`),
  KEY `idx_alert_state` (`is_alerted`, `alert_level`),
  CONSTRAINT `fk_alert_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create Staff Attendance Table
CREATE TABLE IF NOT EXISTS `staff_attendance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in` datetime NOT NULL,
  `check_out` datetime DEFAULT NULL,
  `working_hours` decimal(5,2) DEFAULT NULL,
  `status` enum('present','late','half_day') NOT NULL DEFAULT 'present',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_att_user_date` (`user_id`, `attendance_date`),
  KEY `idx_att_checkin` (`check_in`),
  KEY `idx_att_active` (`user_id`, `check_out`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create Database Backup History Table
CREATE TABLE IF NOT EXISTS `database_backup_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(190) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `table_count` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('verified','failed') NOT NULL DEFAULT 'verified',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backup_filename` (`filename`),
  KEY `idx_backup_created` (`created_at`),
  CONSTRAINT `fk_backup_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Seed Standard Inventory Categories if empty
INSERT IGNORE INTO `inventory_categories` (`name`, `description`) VALUES
('Detergents', 'Industrial and gentle laundry washing detergents'),
('Fabric Softeners', 'Fabric conditioners and softening liquids'),
('Cleaning Chemicals', 'Stain removers, bleaches, and specialized washing agents'),
('Packaging Materials', 'Garment covers, hanger wraps, and protective bags'),
('Laundry Bags', 'Heavy-duty collection and delivery laundry bags'),
('Other Supplies', 'General operational, ironing, and maintenance supplies');

