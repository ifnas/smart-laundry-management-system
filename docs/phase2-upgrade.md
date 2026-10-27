# Google Antigravity — Phase 2 Business Management Upgrade Report
## Smart Laundry Booking & Delivery Management System

**Project Directory:** `C:\xampp\htdocs\smart-laundry`  
**Database:** `smart_laundry_db` (MariaDB/MySQL)  
**Base URL:** `http://localhost/smart-laundry/`  
**Date of Completion:** October 27, 2026  

---

## 1. Upgrade Overview

The Smart Laundry Booking & Delivery Management System was enhanced in Phase 2 with four commercial business management modules. These modules enable promotions, time management, customer satisfaction tracking, and financial overhead control while preserving 100% of existing functionality, data integrity, and both Light/Dark theme presentations:

1. **Discount Coupon Engine**
2. **Pickup & Delivery Time Slots**
3. **Customer Reviews & Ratings**
4. **Expense Management & Operating Profit Analysis**

All features were engineered with native PHP 8+, MySQL/MariaDB, and vanilla JavaScript without external API dependencies.

---

## 2. Features Implemented

### Feature One — Discount Coupon Engine
- **Flexible Promotion Types:** Supports both `fixed` currency amount discounts (e.g. 100 LKR off) and `percent` discounts (e.g. 10% off).
- **Validation Rules & Constraints:**
  - `min_booking_amount`: Minimum order subtotal required before the coupon can be applied.
  - `max_discount_amount`: Upper cap on percentage-based discounts to safeguard profit margins.
  - `usage_limit`: Total redemption limit across all customers.
  - `valid_from` & `expiry_date`: Date range validity windows.
  - `is_active`: Administrative toggle to activate or deactivate coupons instantly.
- **Real-Time Client Validation (`assets/js/app.js`):** Customers enter coupon codes on the checkout page; an asynchronous AJAX endpoint verifies code validity, checks eligibility against current subtotal, and computes the discount preview in real time without refreshing.
- **Server-Side Transaction Integrity (`actions/booking_actions.php`):** Re-evaluates coupon eligibility atomically upon order placement. Increments `times_used` and logs the redemption in `coupon_redemptions`.
- **Administrative Control (`admin/coupons.php`, `includes/coupons.php`):** Full CRUD management allowing administrators to create, edit, deactivate, and track promo campaign performance.

### Feature Two — Pickup & Delivery Time Slots
- **Operational Window Scheduling (`admin/slots.php`, `includes/slots.php`):** Configurable daily time windows (e.g., Morning 08:00–11:00, Midday 11:00–14:00, Afternoon 14:00–17:00, Evening 17:00–20:00).
- **Capacity Management:** Enforces a configurable `daily_capacity` limit per slot to prevent driver over-allocation.
- **Dynamic Slot Selection (`customer/new_booking.php`):** Customers choose their preferred pickup window during booking; occupied or maxed-out slots display clear availability badges.
- **Logistics Schedule Overview (`admin/schedules.php`, `includes/schedules.php`):** Central calendar view grouping scheduled pickups and deliveries by time window for efficient staff dispatch.

### Feature Three — Customer Reviews & Ratings
- **Verified Purchase Requirement (`customer/reviews.php`, `includes/reviews.php`):** Only customers with orders marked as `Delivered` can submit ratings and feedback.
- **Single Review Constraint:** Database schema enforces a `UNIQUE KEY (booking_id)` ensuring exactly one review per fulfilled order.
- **5-Star Rating System:** Interactive star rating selector (1 to 5 stars) paired with optional detailed feedback comments.
- **Administrator Feedback Feed (`admin/reviews.php`):** Dedicated administration review management page providing average customer ratings, rating distribution breakdowns, and individual review inspection.

### Feature Four — Expense Management & Operating Profit
- **Categorized Overhead Recording (`admin/expenses.php`, `includes/expenses.php`):** Tracks commercial costs across categorized heads:
  - *Cleaning Supplies* (detergents, softeners, bleaches)
  - *Packaging & Bags* (poly covers, tags, hangers)
  - *Utilities* (electricity, water, dryer gas)
  - *Equipment & Maintenance* (washer repairs, spare parts)
  - *Delivery & Fuel* (driver fuel allowances, vehicle maintenance)
  - *Rent & Facility* (premises rent, rates)
  - *Staff & Wages* (casual labor, operational stipends)
  - *Marketing & Promotion* (print flyers, local campaigns)
- **Net Operating Profit Calculation (`includes/report_summary.php`):**
  $$\text{Operating Profit} = \text{Total Collected COD Revenue} - \text{Total Operating Expenses}$$
- **Date-Filtered Financial Reports (`admin/reports.php`):** Correlates gross revenue, discount deductions, operating expenses, and net profit across customizable date ranges.
- **CSV Audit Export:** Streamed, injection-safe CSV export functionality for offline accounting and tax reporting.

---

## 3. Database Schema Migration

The Phase 2 upgrade was applied via `database/migrations/phase2_upgrade.sql`, introducing 5 new tables and 5 additional columns to `bookings`:

```sql
-- New Tables:
pickup_slots          (id, slot_name, start_time, end_time, daily_capacity, is_active, created_at)
coupons               (id, code, discount_type, discount_value, min_booking_amount, max_discount_amount, usage_limit, times_used, valid_from, expiry_date, is_active, created_at, updated_at)
coupon_redemptions    (id, coupon_id, booking_id, customer_id, discount_amount, redeemed_at)
reviews               (id, booking_id, customer_id, rating, review_text, created_at, updated_at)
expense_categories    (id, name, description, is_active, created_at)
expenses              (id, category_id, title, amount, expense_date, notes, created_by, is_active, created_at, updated_at)

-- Alterations to `bookings`:
ALTER TABLE `bookings` ADD COLUMN `subtotal_amount` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `bookings` ADD COLUMN `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE `bookings` ADD COLUMN `coupon_id` int(10) unsigned DEFAULT NULL;
ALTER TABLE `bookings` ADD COLUMN `coupon_code` varchar(50) DEFAULT NULL;
ALTER TABLE `bookings` ADD COLUMN `pickup_slot_id` int(10) unsigned DEFAULT NULL;
```

---

## 4. Security & Quality Assurance

- **CSRF Token Protection:** Every form submission in Coupons, Reviews, Slots, and Expenses incorporates unique, session-backed CSRF tokens verified in `includes/bootstrap.php`.
- **Role-Based Access Enforcement:** Route guards restrict access to coupon creation, slot capacity edits, and expense recording strictly to administrators.
- **SQL Injection Prevention:** 100% of database queries utilize parameterized prepared statements via PDO.
- **Theme Compatibility:** All Phase 2 tables, cards, modal dialogs, and rating stars seamlessly adapt to both Light and Dark themes.

---

## 5. Verification & Testing

The Phase 2 test suite (`tests/test_phase2.php`) executes 69 automated verification checks:
```powershell
C:\xampp\php\php.exe tests/test_phase2.php http://localhost/smart-laundry
```
**Results:** 69 Passed, 0 Failed, 0 Skipped (100% Pass Rate).

