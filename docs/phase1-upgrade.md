# Google Antigravity — Phase 1 Professional Upgrade Report
## Smart Laundry Booking & Delivery Management System

**Project Directory:** `C:\xampp\htdocs\smart-laundry`  
**Database:** `smart_laundry_db` (MariaDB/MySQL)  
**Base URL:** `http://localhost/smart-laundry/`  
**Date of Completion:** October 27, 2026  

---

## 1. Upgrade Overview

The Smart Laundry Booking & Delivery Management System has been upgraded from a functional baseline prototype to an enterprise-grade commercial application. Four major professional features were integrated into the architecture while strictly preserving 100% of existing functionality, data integrity, security controls, and responsive UI design (including the dual Light/Dark theme system):

1. **Professional PDF Invoice System**
2. **Cryptographic QR Code Order Tracking & Labeling**
3. **Database-Backed In-App Notification Center**
4. **Advanced Real-Time Analytics Dashboard**

All components were built with native, zero-external-dependency PHP, MySQL, HTML5, CSS3, and JavaScript, ensuring complete independence from paid APIs or external network services.

---

## 2. Features Implemented

### Feature One — Professional PDF Invoice System
- **Pure-PHP PDF Engine (`includes/pdf.php`):** Implemented `SmartLaundryPdf`, a standalone vector PDF 1.4 generator featuring Deflate stream compression, precise coordinate layout, automated page setup, typography, and tabular formatting.
- **Accurate Real Data:** Extracts data directly from `bookings`, `booking_items`, `users`, and `services`. Never generates or displays simulated data.
- **Historical Price Protection:** Uses price snapshots stored in `booking_items` to guarantee that catalogue price adjustments never alter past invoices.
- **Stable Unique Invoice Identifiers:** Generates non-repeating numbers formatted as `INV-YYYYMMDD-XXXX` (e.g., `INV-20261027-A1B2`) assigned upon booking creation.
- **Role-Based Access Control:**
  - Customers may view (`invoice.php?id=...`) and download (`invoice.php?id=...&download=pdf`) invoices only for their own bookings.
  - Administrators have full access to view and download invoices for all bookings.
  - Delivery staff access is restricted (returns HTTP 403 / 404).
- **Payment State Invariance:** Downloading or viewing an invoice leaves the payment status (`Pending`, `Unpaid`, or `Paid`) completely untouched.

### Feature Two — QR Code Order Tracking & Bag Labeling
- **Pure-PHP QR Generator (`includes/qrcode.php`):** Implemented `SmartLaundryQr`, an ISO/IEC 18004 compliant generator producing sharp, scalable inline SVGs without remote API dependencies.
- **Non-Guessable Cryptographic Token:** Every booking is assigned a 64-character cryptographically secure token (`tracking_token`) generated via `bin2hex(random_bytes(32))`.
- **Public Tracking Page (`track.php?token=...`):**
  - Allows customers or recipients to scan garment bag QR codes using any standard smartphone camera.
  - Displays booking reference, current status (with active progress stepper), pickup date, and last status update timestamp.
  - **Zero PII Exposure:** Strictly conceals customer names, phone numbers, delivery addresses, and monetary totals.
- **Print-Friendly Bag Label (`qr_label.php?id=...`):** Provides a specialized printable 100mm label with prominent QR code, booking reference, and garment handling instructions for physical laundry tags.
- **Local Network Support:** Provides configurable `APP_BASE_URL` so QR codes resolve to local Wi-Fi IPs (e.g., `http://192.168.1.X/smart-laundry/track.php?token=...`) during mobile testing.

### Feature Three — In-App Notification Center
- **Database-Backed Schema:** Backed by the `notifications` table linked via foreign keys to `users` and `bookings`.
- **Automated Lifecycle Triggers (`actions/handle.php`):**
  - *Customer:* Receives alerts when orders are placed, accepted, picked up, washing, ironing, ready for delivery, out for delivery, delivered, or when payment is recorded.
  - *Admin:* Receives immediate alerts when new bookings are submitted or cancelled.
  - *Delivery Personnel:* Receives alerts when assigned to an order or when an order is ready for dispatch.
- **Topbar Bell & Dropdown (`includes/header.php`, `assets/css/style.css`, `assets/js/app.js`):**
  - Shows dynamic red badge counter of unread notifications.
  - Dropdown displays latest 6 events, timestamps, and direct deep-links to authorized orders.
  - One-click "Mark all read" action.
- **Dedicated History Center (`notifications.php`):** Full page review of past notifications with individual read/unread toggles and clear chronological status badges.

### Feature Four — Advanced Analytics Dashboard
- **8 Real-Time KPI Cards (`includes/dashboard.php`):**
  1. *Total bookings* (`COUNT(*)`)
  2. *Pending bookings* (`booking_status = 'Pending'`)
  3. *Active orders* (`booking_status NOT IN ('Delivered', 'Cancelled', 'Rejected')`)
  4. *Completed orders* (`booking_status = 'Delivered'`)
  5. *Total COD revenue collected* (Paid orders only)
  6. *Revenue collected today* (`DATE(paid_at) = CURDATE()`)
  7. *Revenue collected this month* (`paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')`)
  8. *Registered customers* (`role = 'customer'`)
- **4 Chart.js Interactive Charts (`includes/report_summary.php`):**
  1. *Collected Revenue Trend:* Daily bar chart of Cash on Delivery payments received.
  2. *Booking Status Distribution:* Doughnut chart showing proportions across operational stages.
  3. *Most Popular Services:* Horizontal bar chart ranking top 5 services by item volume.
  4. *Delivery Performance:* Bar chart of completed deliveries per delivery partner.
- **Time Period Filters:** Preset buttons (`Today`, `Last 7 days`, `Last 30 days`, `This month`) and a validated custom date form.
- **Real-Time Light / Dark Mode Adaptation:** Chart gridlines, labels, tick marks, and tooltips automatically transition when switching themes.

---

## 3. Database Modifications

All schema changes were encapsulated in a non-destructive migration script:  
`database/migrations/phase1_upgrade.sql`

```sql
-- 1. Create notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `booking_id` INT UNSIGNED NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'danger') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user_read` (`user_id`, `is_read`),
  INDEX `idx_notif_booking` (`booking_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add stable invoice number and secure tracking token to bookings
ALTER TABLE `bookings`
  ADD COLUMN IF NOT EXISTS `invoice_number` VARCHAR(32) NULL UNIQUE AFTER `booking_number`,
  ADD COLUMN IF NOT EXISTS `tracking_token` VARCHAR(64) NULL UNIQUE AFTER `invoice_number`;
```

### Backfill & Data Preservation
- Existing historical bookings were safely backfilled with stable invoice numbers and unique cryptographic tracking tokens.
- No existing records, user profiles, or past payment entries were altered or deleted.

---

## 4. Summary of Files Changed & Created

### Files Created:
1. `database/migrations/phase1_upgrade.sql` — Schema migration for notifications, invoice numbers, and tracking tokens.
2. `includes/pdf.php` — Pure-PHP PDF 1.4 generation engine (`SmartLaundryPdf`).
3. `includes/qrcode.php` — Pure-PHP ISO/IEC 18004 QR code SVG generator (`SmartLaundryQr`).
4. `invoice.php` — Web and PDF invoice view/download controller.
5. `track.php` — Public privacy-compliant order tracking page by token.
6. `qr_label.php` — Print-friendly laundry bag label with scannable QR code.
7. `notifications.php` — Comprehensive In-App Notification Center page.
8. `tests/test_phase1.php` — Automated test suite verifying the 4 Phase 1 features.
9. `docs/phase1-upgrade.md` — This technical upgrade report and manual checklist.

### Files Modified:
1. `includes/functions.php` — Added `app_base_url()`, `notify()`, `unread_notifications_count()`, `recent_notifications()`, `time_ago()`, and invoice link generation in `orders_table()`.
2. `includes/header.php` — Added notification bell with badge counter, interactive quick-view dropdown, and theme toggle controls.
3. `includes/booking_details.php` — Integrated invoice view/download buttons and QR code tracking card with print label button.
4. `includes/dashboard.php` — Upgraded admin overview to display the 8 real-time KPI cards.
5. `includes/reports.php` — Integrated preset filters (`today`, `7d`, `30d`, `month`, `custom`) and linked analytics suite.
6. `includes/report_summary.php` — Completely upgraded to provide the filter bar, 4 interactive Chart.js charts, accessibility fallbacks, and real-time theme observer.
7. `actions/handle.php` — Added notification event triggers, stable invoice/token generation on new bookings, and notification read/all-read CSRF handlers.
8. `assets/css/style.css` — Added styling for notification dropdown, badge counter, analytics filter bar, and chart cards in both light and dark modes.
9. `assets/js/app.js` — Added theme switcher persistence (`localStorage`), live Chart.js theme dispatching, and notification dropdown click-outside handlers.
10. `README.md` — Updated with Phase 1 feature documentation, setup steps, and testing guides.

---

## 5. Security Architecture & Controls

| Security Principle | Implementation Detail |
| --- | --- |
| **Zero PII Exposure** | `track.php` accepts only unguessable 64-char hex tokens and never renders names, phone numbers, addresses, or billing data. |
| **Strict Role-Based Authorization** | Invoices check `$u['role'] === 'admin'` or `$u['id'] === $booking['customer_id']`. Delivery staff and other customers receive HTTP 403/404. |
| **Financial State Immutability** | Invoice downloads are strictly read-only GET requests that never modify `payment_status` or `paid_at`. |
| **Historical Price Snapshotting** | Invoices calculate from immutable integer snapshots in `booking_items`, protecting historical invoices from future service price edits. |
| **CSRF Defense** | All state changes (marking notifications as read, updating order status) enforce strict CSRF tokens and POST requests. |
| **SQL Injection Prevention** | All database interactions utilize PDO prepared statements with strict parameter bindings. |
| **XSS Protection** | All dynamic values are sanitized using `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`. |
| **Session Security** | Sessions configured with `use_strict_mode = 1`, `HttpOnly`, `SameSite = Lax`, and strict regeneration upon authentication. |

---

## 6. Verification and Testing

### Automated Test Execution

1. **Phase 1 Comprehensive Test Suite (`tests/test_phase1.php`):**
   - Customer HTML and PDF invoice view/download verified.
   - Valid `%PDF-1.4` binary stream verified.
   - Unauthorized invoice access blocked (other customers & delivery staff receive HTTP 403/404).
   - Invariance of payment status verified after download.
   - QR code token generation and public tracking resolution verified.
   - Verification that no customer name, phone, address, or payment totals leak on `track.php`.
   - Invalid tracking tokens handled cleanly without errors.
   - Print-friendly QR label access verified.
   - Notifications generated on booking creation, status acceptance, and driver assignment.
   - Notification mark-as-read and mark-all-read verified.
   - 8 Admin KPI cards verified against database records.
   - All 4 Chart.js chart canvases verified.
   - Time filters (`today`, `7d`, `30d`, `month`, `custom`, invalid fallback) verified.
   - **Result:** **35 / 35 checks PASSED.**

2. **Full System Smoke Test Suite (`tests/smoke.php`):**
   - Verified authentication, customer booking, service management, role protection, state transitions, COD collection, and sales reports.
   - **Result:** **68 / 68 checks PASSED.**

3. **Demo Account Integration & Self-Repair Test (`tests/demo_accounts.php`):**
   - Verified account preservation, password hashing, and login functionality.
   - **Result:** **16 / 16 checks PASSED.**

---

## 7. Manual Walkthrough Checklist

Follow these steps in your browser to verify the new features:

1. **Dark Mode & Notification Bell:**
   - Open `http://localhost/smart-laundry/login.php`.
   - Sign in as `admin@laundry.com` (`Admin@123`).
   - Notice the **Notification Bell** in the top bar with the unread count badge.
   - Click the bell: The quick-view dropdown opens showing recent operational events.
   - Click **Dark / Light** button: Verify the theme instantly transitions and charts automatically adapt their axes and grid lines.

2. **Advanced Analytics Dashboard:**
   - In the Admin workspace, view the **Overview** dashboard (`admin/dashboard.php`).
   - Review the **8 KPI cards** showing live totals.
   - Scroll down to review the **4 Charts**:
     1. Collected Revenue Trend
     2. Booking Status Distribution
     3. Most Popular Services
     4. Delivery Performance
   - Click **Last 7 days**, **Last 30 days**, or **Today** in the filter bar to see charts update accordingly.

3. **Customer Booking & QR Tracking:**
   - Open an incognito browser window and sign in as `customer@laundry.com` (`Customer@123`).
   - Notice the bell icon alerting the customer of past orders.
   - Click **My bookings** and open any booking.
   - Review the **Order Tracking & QR Code** card showing a scannable QR code.
   - Click **Print Laundry Label** to view the printable garment bag label.
   - Click **Public Tracking Link**: A clean, privacy-protected tracking page opens showing milestones without exposing private customer contact information.

4. **PDF Invoice Generation:**
   - On the customer's booking details page, click **View Invoice**: A formatted HTML invoice displays.
   - Click **Download PDF**: The browser prompts to save an authentic `.pdf` document with complete order details.
   - Check the invoice status: Unpaid orders remain accurately marked as Unpaid; paid orders show Paid.

---

## 8. Dark / Light Mode Toggle Resolution

### Root Cause
During the Phase 1 script consolidation, the theme initialization and click handler block in `assets/js/app.js` was accidentally duplicated at lines 48-120 and lines 122-195. Consequently, two event listeners were attached to every `#theme-toggle` button. On any user click, Listener 1 immediately toggled the theme from Light to Dark, and Listener 2 immediately fired within the same microtask, read the new Dark state, and toggled it back to Light. To the user, clicking the button appeared completely non-functional.

### Resolution
1. Completely removed the duplicated JavaScript code block in `assets/js/app.js`.
2. Implemented a single document-level delegated click listener targeting `e.target.closest('#theme-toggle, .btn-theme-toggle')`, properly bypassing `.notif-btn` so clicking the button or its child `<i>` / `<span>` elements triggers exactly one state transition.
3. Added the theme toggle button and `app.js` script to `invoice.php` toolbar for consistent switching across all views.
4. Preserved early theme execution in `<head>` across all pages to guarantee zero flash of wrong theme on initial load or refresh.


