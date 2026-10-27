# Smart Laundry Management System — Phase 3 Advanced Management Upgrade

## Executive Summary

Phase 3 introduces advanced enterprise management capabilities to the Smart Laundry Booking & Delivery Management System running on XAMPP (PHP 8+, MySQL/MariaDB):
1. **Full-Featured Inventory Management**: Consumables catalogue, SKU tracking, category management, supplier relations, Stock In, Stock Out, and Manual Adjustments with strict database transaction locking, non-negative balance enforcement, and complete audit logging.
2. **Staff Attendance System**: Real-time shift check-in and check-out for delivery drivers and operational staff, single-active-session concurrency locking, Asia/Colombo (+05:30) timezone logging, automatic working hours calculation, administrator monitoring with monthly summaries, and payroll-ready CSV export.
3. **Automatic Low Stock Alerts**: Configurable minimum safety thresholds, distinction between Low Stock and Out of Stock, real-time KPI card on the Admin Dashboard, persistent deduplication tracking in `inventory_alert_state`, and automated resolution notifications when stock is replenished.
4. **Secure Database Backup & Safe Restore Preview**: Automated non-destructive database backup generation stored safely outside the web root (`C:\xampp\backups\smart-laundry`), admin authentication, CSRF protection, path traversal defenses, secure streaming downloads, and a safety-first restore preview displaying file metadata, detected tables, and step-by-step CLI recovery procedures without allowing one-click destructive live overwrites.

---

## 1. Architecture & Module Design

### 1.1 High-Level Architecture

```
+-----------------------------------------------------------------------------------+
|                                 SMART LAUNDRY SYSTEM                             |
+-----------------------------------------------------------------------------------+
|  PRESENTATION LAYER (Bootstrap 5, Vanilla JS, Dark/Light Mode, Bootstrap Icons)   |
|  - Admin Inventory UI (admin/inventory.php, admin/inventory_item.php)             |
|  - Stock Movement & Audit Log (admin/stock_movements.php)                         |
|  - Supplier Directory (admin/suppliers.php)                                       |
|  - Staff Attendance Portals (delivery/attendance.php, admin/attendance.php)       |
|  - Database Backup & Restore Preview (admin/backups.php, admin/restore_preview.php)|
+-----------------------------------------------------------------------------------+
|  APPLICATION & BUSINESS LOGIC LAYER                                              |
|  - Dispatcher & Security Guard (includes/app.php, includes/bootstrap.php)        |
|  - Business Handlers (actions/handle.php)                                         |
|  - Core Functions (includes/functions.php):                                       |
|    * record_inventory_movement() with FOR UPDATE row-level locking                |
|    * update_inventory_alert_state() with deduplication & replenishment resolution |
|    * record_staff_checkin() & record_staff_checkout()                             |
|    * generate_database_backup()                                                   |
+-----------------------------------------------------------------------------------+
|  DATABASE PERSISTENCE LAYER (MySQL / MariaDB via PDO)                             |
|  - inventory_categories, inventory_items, inventory_suppliers, inventory_movements|
|  - inventory_alert_state                                                          |
|  - staff_attendance                                                               |
|  - database_backup_history                                                        |
+-----------------------------------------------------------------------------------+
|  SECURE STORAGE (Outside Web Root)                                                |
|  - C:\xampp\backups\smart-laundry\*.sql                                          |
+-----------------------------------------------------------------------------------+
```

---

## 2. Database Schema (Phase 3 Additive Migration)

The database migration is defined in [`database/migrations/phase3_upgrade.sql`](../database/migrations/phase3_upgrade.sql). All changes are strictly additive, idempotent, and preserve all historical bookings, users, coupons, reviews, and financial records.

### 2.1 Schema Definition

#### `inventory_categories`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `name` VARCHAR(100) NOT NULL UNIQUE
- `description` VARCHAR(255) NULL
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

#### `inventory_suppliers`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `name` VARCHAR(150) NOT NULL
- `contact_person` VARCHAR(100) NULL
- `email` VARCHAR(190) NULL
- `phone` VARCHAR(30) NULL
- `address` TEXT NULL
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

#### `inventory_items`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `category_id` INT UNSIGNED NOT NULL (FK -> `inventory_categories.id`)
- `supplier_id` INT UNSIGNED NULL (FK -> `inventory_suppliers.id` ON DELETE SET NULL)
- `sku` VARCHAR(50) NOT NULL UNIQUE
- `name` VARCHAR(150) NOT NULL
- `unit` VARCHAR(30) NOT NULL DEFAULT 'Pieces'
- `min_stock` DECIMAL(12,2) NOT NULL DEFAULT 0.00
- `purchase_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00
- `current_stock` DECIMAL(12,2) NOT NULL DEFAULT 0.00
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

#### `inventory_movements` (Immutable Ledger)
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `item_id` INT UNSIGNED NOT NULL (FK -> `inventory_items.id` ON DELETE CASCADE)
- `movement_type` ENUM('IN','OUT','ADJUST') NOT NULL
- `quantity` DECIMAL(12,2) NOT NULL
- `balance_after` DECIMAL(12,2) NOT NULL
- `unit` VARCHAR(30) NOT NULL
- `reference` VARCHAR(100) NULL
- `notes` TEXT NULL
- `supplier_id` INT UNSIGNED NULL (FK -> `inventory_suppliers.id` ON DELETE SET NULL)
- `performed_by` INT UNSIGNED NOT NULL (FK -> `users.id`)
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

#### `inventory_alert_state` (Deduplication Tracker)
- `item_id` INT UNSIGNED NOT NULL PRIMARY KEY (FK -> `inventory_items.id` ON DELETE CASCADE)
- `is_alerted` TINYINT(1) NOT NULL DEFAULT 0
- `last_alerted_at` DATETIME NULL
- `last_resolved_at` DATETIME NULL
- `alert_level` ENUM('NONE','LOW','OUT_OF_STOCK') NOT NULL DEFAULT 'NONE'

#### `staff_attendance`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `user_id` INT UNSIGNED NOT NULL (FK -> `users.id` ON DELETE CASCADE)
- `attendance_date` DATE NOT NULL
- `check_in` DATETIME NOT NULL
- `check_out` DATETIME NULL
- `working_hours` DECIMAL(5,2) NULL
- `status` ENUM('present','late','half_day') NOT NULL DEFAULT 'present'
- `notes` VARCHAR(255) NULL
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

#### `database_backup_history`
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `filename` VARCHAR(190) NOT NULL UNIQUE
- `file_path` VARCHAR(255) NOT NULL
- `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0
- `table_count` INT UNSIGNED NOT NULL DEFAULT 0
- `status` ENUM('verified','failed') NOT NULL DEFAULT 'verified'
- `created_by` INT UNSIGNED NOT NULL (FK -> `users.id`)
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

---

## 3. Module Specifications & Business Rules

### 3.1 Inventory Management
- **Transactions & Concurrency**: All balance modifications invoke `db()->beginTransaction()` and acquire row locks via `SELECT ... FOR UPDATE` on `inventory_items`.
- **Negative Stock Rejection**: Any Stock Out or adjustment that would cause `balance_after < 0.00` immediately throws a `DomainException`, rolls back the transaction, and leaves stock unmodified.
- **Audit Ledger**: Every balance transition writes an immutable movement record containing `movement_type`, `quantity`, `balance_after`, `unit`, `reference`, `notes`, `performed_by` user ID, and server timestamp.
- **Non-Destructive Archiving**: Items can be deactivated without deleting rows or orphaning historical movement ledger entries.
- **No Automatic Washing Consumption**: Deductions are recorded explicitly via stock movements to prevent distorted inventory or billing mismatches.

### 3.2 Staff Attendance
- **Single Active Session Constraint**: A staff member may have only one active session (`check_out IS NULL`). Submitting a check-in while already checked in is rejected with a user-friendly error.
- **Timezone Consistency**: Timestamps use `Asia/Colombo` (+05:30) consistently across PHP and MySQL.
- **Working Hours Computation**: Duration is calculated from actual stored timestamps upon check-out: `round((check_out_time - check_in_time) / 3600, 2)`.
- **Admin Oversight**: Administrators can filter attendance by staff member, view custom date ranges, review missing/unclosed check-outs, and export records as standard CSV (`export_attendance_csv`).

### 3.3 Automatic Low Stock Alerts
- **State Machine**:
  - `current_stock > min_stock` -> Level = `NONE`
  - `current_stock <= min_stock` and `current_stock > 0` -> Level = `LOW`
  - `current_stock <= 0` -> Level = `OUT_OF_STOCK`
- **Deduplication**: When an item first falls below `min_stock`, `inventory_alert_state.is_alerted` is set to `1` and an admin notification is created. Repeated page refreshes or minor stock changes within the same low-stock state do not create spam notifications.
- **Replenishment Resolution**: When stock is restocked above `min_stock`, `is_alerted` resets to `0`, `last_resolved_at` is stamped, and a "Stock Replenished" success notification is sent to admins. If the item later falls below the threshold again, a new alert is generated.
- **Admin Dashboard Integration**: Real-time "Low stock items" KPI card on `admin/dashboard.php` and warning banner linking directly to filtered inventory items.

### 3.4 Secure Database Backup & Safe Restore Preview
- **Offline Storage**: Backups reside strictly in `C:\xampp\backups\smart-laundry`, outside the Apache `htdocs` document root, preventing direct HTTP exposure.
- **Path Traversal Protection**: Filenames must match `^backup_smart_laundry_db_\d{8}_\d{6}\.sql$` and resolve strictly within the realpath of the backup directory.
- **Authorization**: Only administrators can trigger backup creation or authorized downloads (`backup_download`).
- **Safety-First Restore Preview**: Restore preview page (`admin/restore_preview.php`) provides file size, timestamp, line inspection, detected tables, and safety warnings. **One-click destructive restore over the live database is strictly prohibited in code**; step-by-step CLI restoration instructions and staging verification guidelines are provided instead.

---

## 4. Security Controls

| Threat / Risk | Mitigating Control Implemented |
| --- | --- |
| **Direct Web Access to SQL Dumps** | Backup folder `C:\xampp\backups\smart-laundry` is located outside `htdocs`. |
| **Path Traversal Attacks** | Strict regex validation (`preg_match('/^backup_smart_laundry_db_\d{8}_\d{6}\.sql$/')`) and `realpath()` root containment verification. |
| **Cross-Site Request Forgery (CSRF)** | Synchronizer token pattern with `verify_post()` on every mutation and download action. |
| **Role Elevation / Unauthorized Access** | `protect('admin')` for inventory, backup, and report views; `protect('delivery')` for staff shifts. |
| **Negative Stock & Race Conditions** | Row-level locking (`SELECT ... FOR UPDATE`) inside atomic database transactions (`beginTransaction` / `commit` / `rollBack`). |
| **SQL Injection** | Strict PDO prepared statements with parameter binding across all queries. |
| **XSS / Content Injection** | Strict HTML escaping via `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`). |
| **Destructive Database Overwrite** | One-click restore disabled in code; documented CLI restoration procedure enforced. |

---

## 5. Verification & Test Results

The entire verification suite was executed against the local running environment (`http://localhost/smart-laundry`):

| Test Suite | File | Checks Passed | Status |
| --- | --- | --- | --- |
| **Phase 3 Verification Suite** | `tests/test_phase3.php` | **79 / 79** | **PASSED** |
| **Phase 2 Verification Suite** | `tests/test_phase2.php` | **69 / 69** | **PASSED** |
| **Phase 1 Verification Suite** | `tests/test_phase1.php` | **66 / 66** | **PASSED** |
| **End-to-End Smoke Tests** | `tests/smoke.php` | **68 / 68** | **PASSED** |
| **Demo Accounts Integrity** | `tests/demo_accounts.php` | **16 / 16** | **PASSED** |
| **Theme System Tests** | `tests/test_theme.js` | **5 / 5** | **PASSED** |
| **Project-Wide PHP Linter** | `tools/lint.php` | **88 / 88 files** | **PASSED** |

### Detailed 20-Point Test Coverage in `test_phase3.php`

1. **Inventory item creation**: Verified category, supplier, SKU, unit, min_stock, purchase_cost, and initial stock persistence.
2. **Stock In**: Verified stock increases atomically and movement record is created.
3. **Stock Out**: Verified stock decreases atomically with reason reference.
4. **Rejection of negative stock**: Verified transaction aborts and balance is protected when deduction exceeds balance.
5. **Concurrent stock updates**: Verified row-level locking maintains transactional consistency.
6. **Inventory movement audit**: Verified user ID, timestamp, and balance_after integrity.
7. **Low stock threshold detection**: Verified alert trigger when balance reaches or falls below `min_stock`.
8. **Duplicate alert prevention**: Verified deduplication logic prevents repeated notification generation.
9. **Alert resolution after replenishment**: Verified threshold recovery clears `is_alerted` and emits replenishment notification.
10. **Staff check-in**: Verified delivery driver check-in creates active attendance session.
11. **Staff check-out**: Verified check-out timestamps and working duration.
12. **Duplicate check-in prevention**: Verified multiple active sessions are rejected.
13. **Working hours calculation**: Verified duration calculation from stored server timestamps.
14. **Admin attendance reporting**: Verified attendance logs UI and CSV export stream.
15. **Unauthorized staff access**: Verified customer accounts cannot access attendance mutations or admin pages.
16. **Backup creation**: Verified non-empty SQL dump generation outside web root with schema and data.
17. **Backup file access security**: Verified download protection against anonymous/customer access and path traversal.
18. **Restore preview safety**: Verified metadata inspection and absence of destructive live overwrites.
19. **Dark/light mode compatibility**: Verified theme switcher and persistent styling on all 7 new pages.
20. **Existing notification integration**: Verified alert notifications coexist seamlessly with booking events.

---

## 6. Local Application URLs

| Module | Local URL | Role Required |
| --- | --- | --- |
| **Inventory Management** | `http://localhost/smart-laundry/admin/inventory.php` | Admin |
| **Add / Edit Inventory Item** | `http://localhost/smart-laundry/admin/inventory_item.php` | Admin |
| **Stock Movements Ledger** | `http://localhost/smart-laundry/admin/stock_movements.php` | Admin |
| **Suppliers Directory** | `http://localhost/smart-laundry/admin/suppliers.php` | Admin |
| **Low Stock Alerts Filter** | `http://localhost/smart-laundry/admin/low_stock.php` | Admin |
| **Staff Attendance (Admin)** | `http://localhost/smart-laundry/admin/attendance.php` | Admin |
| **My Attendance (Driver)** | `http://localhost/smart-laundry/delivery/attendance.php` | Delivery Driver |
| **Database Backup Management** | `http://localhost/smart-laundry/admin/backups.php` | Admin |
| **Safe Restore Preview** | `http://localhost/smart-laundry/admin/restore_preview.php` | Admin |

---

## 7. Manual Demo & Verification Steps

1. **Sign in as Administrator**:
   - URL: `http://localhost/smart-laundry/login.php`
   - Email: `admin@laundry.com` / Password: `Admin@123`
2. **Review Inventory & Stock**:
   - Navigate to **Inventory** in sidebar.
   - Click **Add Inventory Item**; enter SKU (e.g. `DET-01`), Name, Category, Unit (`Litres`), Min Stock (`10`), Purchase Cost (`500`), Initial Stock (`20`).
   - Navigate to **Stock movements**; perform Stock In (+10) and Stock Out (-25).
   - Confirm balance is 5 Litres and notice the **Low Stock Warning** badge.
3. **Check Low Stock Alert**:
   - Check the topbar notification bell: see the "Low Stock Alert" message.
   - Return to **Overview** (Admin Dashboard): see the "Low stock items" KPI card and yellow alert banner.
   - In **Stock movements**, perform Stock In (+20); observe that the alert state resolves and a "Stock Replenished" notification is issued.
4. **Staff Attendance Workflow**:
   - Sign in as Delivery Driver (`delivery@laundry.com` / `Delivery@123`).
   - Open **My attendance** in the delivery sidebar.
   - Click **Record Shift Check In**; observe the status changes to "On Duty" with live timestamp.
   - Attempting to check in again is blocked. Click **Check Out Now** to complete shift and view recorded working hours.
   - Switch back to Admin -> **Staff attendance** to view the logged shift and click **Export Attendance CSV**.
5. **Database Backup & Restore Preview**:
   - In Admin sidebar, click **Backup management**.
   - Click **Create Backup Snapshot**; confirm the file is saved outside web root in `C:\xampp\backups\smart-laundry`.
   - Click **Preview**; inspect table count, metadata, and the documented CLI recovery steps.
   - Click **Download**; verify the SQL file downloads securely.

