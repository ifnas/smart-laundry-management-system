-- Smart Laundry Management System
-- Phase 2 Business Management Upgrade Migration
-- Features: Discount Coupons, Pickup Time Slots, Customer Reviews & Ratings, Expenses & Operating Profit

USE `smart_laundry_db`;

-- 1. Create Pickup Slots Table
CREATE TABLE IF NOT EXISTS `pickup_slots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slot_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `daily_capacity` int(10) unsigned NOT NULL DEFAULT 8,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Coupons Table
CREATE TABLE IF NOT EXISTS `coupons` (
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_coupon_code_active` (`code`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add Phase 2 Columns to Bookings Table
ALTER TABLE `bookings` ADD COLUMN IF NOT EXISTS `subtotal_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `pickup_date`;
ALTER TABLE `bookings` ADD COLUMN IF NOT EXISTS `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `subtotal_amount`;
ALTER TABLE `bookings` ADD COLUMN IF NOT EXISTS `coupon_id` int(10) unsigned DEFAULT NULL AFTER `discount_amount`;
ALTER TABLE `bookings` ADD COLUMN IF NOT EXISTS `coupon_code` varchar(50) DEFAULT NULL AFTER `coupon_id`;
ALTER TABLE `bookings` ADD COLUMN IF NOT EXISTS `pickup_slot_id` int(10) unsigned DEFAULT NULL AFTER `coupon_code`;

-- Update existing bookings subtotal_amount where it was 0 to match total_amount
UPDATE `bookings` SET `subtotal_amount` = `total_amount` WHERE `subtotal_amount` = 0.00 AND `total_amount` > 0.00;

-- 4. Create Coupon Redemptions Table
CREATE TABLE IF NOT EXISTS `coupon_redemptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` int(10) unsigned NOT NULL,
  `booking_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_booking_coupon` (`booking_id`),
  KEY `idx_redemptions_coupon` (`coupon_id`),
  KEY `idx_redemptions_customer` (`customer_id`),
  CONSTRAINT `fk_redemption_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_redemption_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_redemption_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create Customer Reviews Table
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `idx_reviews_rating` (`rating`),
  KEY `idx_reviews_customer` (`customer_id`),
  CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create Expense Categories Table
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create Expenses Table
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expenses_date` (`expense_date`),
  KEY `idx_expenses_cat` (`category_id`),
  KEY `fk_expense_user` (`created_by`),
  CONSTRAINT `fk_expense_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `fk_expense_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Seed Default Expense Categories (if empty)
INSERT IGNORE INTO `expense_categories` (`id`, `name`, `description`) VALUES
(1, 'Cleaning Supplies', 'Detergents, stain removers, softeners, bleach'),
(2, 'Packaging & Bags', 'Poly bags, hangers, garment covers, tags'),
(3, 'Utilities (Water & Power)', 'Electricity, water supply, gas/heating for dryers'),
(4, 'Equipment & Maintenance', 'Washer/dryer repairs, iron maintenance, spare parts'),
(5, 'Delivery & Fuel', 'Driver fuel allowances, delivery vehicle maintenance'),
(6, 'Rent & Facility', 'Shop/hub rent, property rates, facility upkeep'),
(7, 'Staff & Wages', 'Casual labor, overtime, operational staff stipends'),
(8, 'Marketing & Promotion', 'Pamphlets, local flyers, social promotions');

-- 9. Seed Default Pickup Slots (if empty)
INSERT IGNORE INTO `pickup_slots` (`id`, `slot_name`, `start_time`, `end_time`, `daily_capacity`) VALUES
(1, 'Morning Window (08:00 - 11:00)', '08:00:00', '11:00:00', 8),
(2, 'Midday Window (11:00 - 14:00)', '11:00:00', '14:00:00', 8),
(3, 'Afternoon Window (14:00 - 17:00)', '14:00:00', '17:00:00', 8),
(4, 'Evening Window (17:00 - 20:00)', '17:00:00', '20:00:00', 8);

