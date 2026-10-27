-- Smart Laundry Management System
-- Phase 1 Professional Upgrade Migration
-- Features: PDF Invoices, QR Tracking, In-App Notifications

USE `smart_laundry_db`;

-- 1. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `booking_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id_read_created` (`user_id`, `is_read`, `created_at`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Add Invoice Number and Tracking Token to Bookings
ALTER TABLE `bookings` ADD COLUMN IF NOT EXISTS `invoice_number` varchar(40) DEFAULT NULL AFTER `booking_number`;
ALTER TABLE `bookings` ADD COLUMN IF NOT EXISTS `tracking_token` varchar(64) DEFAULT NULL AFTER `invoice_number`;

-- 3. Populate existing rows safely with unique values
UPDATE `bookings` 
SET `invoice_number` = CONCAT('INV-', DATE_FORMAT(`created_at`, '%y%m%d'), '-', LPAD(`id`, 4, '0'), '-', UPPER(SUBSTRING(MD5(CONCAT(`id`, `booking_number`)), 1, 4))) 
WHERE `invoice_number` IS NULL OR `invoice_number` = '';

UPDATE `bookings` 
SET `tracking_token` = SHA2(CONCAT(`id`, `booking_number`, 'smart_laundry_salt_2026'), 256) 
WHERE `tracking_token` IS NULL OR `tracking_token` = '';

-- 4. Add unique constraints
ALTER TABLE `bookings` ADD UNIQUE KEY IF NOT EXISTS `uq_booking_invoice_number` (`invoice_number`);
ALTER TABLE `bookings` ADD UNIQUE KEY IF NOT EXISTS `uq_booking_tracking_token` (`tracking_token`);

