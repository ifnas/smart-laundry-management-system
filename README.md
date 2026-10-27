# Smart Laundry — Booking & Delivery Management System

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B%20%7C%208.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-10.4%2B-4479A1?logo=mysql&logoColor=white)](https://mariadb.org/)
[![UI Framework](https://img.shields.io/badge/Bootstrap-5.3.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Charts](https://img.shields.io/badge/Chart.js-4.4.3-FF6384?logo=chartdotjs&logoColor=white)](https://www.chartjs.org/)
[![Server](https://img.shields.io/badge/Server-XAMPP%20%2F%20Apache-FB7A24?logo=apache&logoColor=white)](https://www.apachefriends.org/)
[![Dependencies](https://img.shields.io/badge/External%20Dependencies-Zero%20(Pure%20PHP)-success)](#zero-dependency-architecture)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

An enterprise-ready, full-stack laundry operations platform built with native PHP 8+, MySQL/MariaDB, and modern vanilla JavaScript. The application provides dedicated, role-tailored workspaces for **Customers**, **Administrators**, and **Delivery Personnel**, handling everything from per-garment customer booking, real-time in-app notifications, and ISO-compliant QR tracking labels to staff attendance, consumable inventory ledgers, and automated financial analytics.

---

## Table of Contents

- [Business Workflow Overview](#business-workflow-overview)
- [Key Features Across Phases](#key-features-across-phases)
  - [Phase 1: Enterprise Core Upgrades](#phase-1-enterprise-core-upgrades)
  - [Phase 2: Business & Commercial Management](#phase-2-business--commercial-management)
  - [Phase 3: Advanced Operations & Inventory](#phase-3-advanced-operations--inventory)
- [Zero-Dependency Architecture](#zero-dependency-architecture)
- [Security & Privacy Features](#security--privacy-features)
- [Screenshots & UI Showcase](#screenshots--ui-showcase)
- [Technology Stack](#technology-stack)
- [Directory Structure](#directory-structure)
- [Installation & Setup](#installation--setup)
- [Demo Accounts](#demo-accounts)
- [Automated Testing Suite](#automated-testing-suite)
- [Phase Documentation](#phase-documentation)
- [License](#license)

---

## Business Workflow Overview

```text
+----------------+      +-------------------+      +--------------------+      +--------------------+
| Customer Books | ---> | Admin Reviews     | ---> | Driver Collects    | ---> | Plant Laundering   |
| Items & Slot   |      | & Assigns Driver  |      | & Bags with QR Tag |      | (Washing/Pressing) |
+----------------+      +-------------------+      +--------------------+      +--------------------+
                                                                                       |
+----------------+      +-------------------+      +--------------------+              |
| Customer Rates | <--- | Driver Collects   | <--- | Driver Dispatched  | <------------+
| & Reviews      |      | COD & Marks Done  |      | (Out for Delivery) |
+----------------+      +-------------------+      +--------------------+
```

1. **Order Intake & Scheduling:** Customers select services, quantities, pickup time windows, and optional discount coupons.
2. **Dispatch & Tracking:** Admins assign deliveries to active drivers. Pure-PHP QR garment tags are generated for physical laundry bags.
3. **Operational Processing:** Status updates trigger real-time in-app notifications as garments progress through Washing, Ironing, and Quality Check.
4. **Fulfillment & Payment:** Delivery staff confirm Cash on Delivery (COD) collections, instantly updating financial records.
5. **Auditing & Reordering:** Admins monitor operational profit, consumable stock thresholds, driver shift attendance, and system backups.

---

## Key Features Across Phases

### Phase 1: Enterprise Core Upgrades

- **Pure-PHP PDF Invoices (`invoice.php`, `includes/pdf.php`):**
  - Standalone vector PDF 1.4 generator (`SmartLaundryPdf`) requiring zero Composer packages or external binaries.
  - Generates branded invoices with unique identifiers (`INV-YYYYMMDD-XXXX`), itemized breakdowns, pricing snapshots, and payment status.
  - Strict role-based access: Customers access only their bookings; admins have global access.
- **Cryptographic QR Code Order Tracking (`track.php`, `qr_label.php`, `includes/qrcode.php`):**
  - High-contrast pure-PHP ISO/IEC 18004 QR engine producing inline SVGs.
  - Unique cryptographically random 64-character token (`tracking_token`) per booking.
  - **Zero Personally Identifiable Information (PII) Leakage:** Public smartphone camera scanning verifies order milestones without revealing customer name, phone number, address, or totals.
  - Printable 100mm laundry bag garment tag (`qr_label.php`).
- **In-App Notification Center (`notifications.php`, `includes/header.php`):**
  - Database-backed event dispatcher notifying customers of milestone transitions, admins of new bookings, and drivers of dispatch assignments.
  - Topbar notification bell with unread badge counter, quick preview dropdown, and full history manager.
- **Advanced Analytics Dashboard (`admin/reports.php`, `includes/dashboard.php`):**
  - 8 real-time KPI cards: Total Bookings, Pending, Active, Completed, Total COD Revenue, Today's Revenue, Monthly Revenue, Registered Customers.
  - 4 interactive Chart.js charts: Daily Revenue Trend, Status Distribution, Popular Services, Driver Performance.
  - Synchronized real-time theme adaptation for Light and Dark modes.

### Phase 2: Business & Commercial Management

- **Discount Coupon Engine (`admin/coupons.php`, `includes/coupons.php`):**
  - Fixed-amount and percentage discounts with minimum subtotal requirements and maximum discount caps.
  - Real-time client-side AJAX validation during checkout and server-side atomic redemption logging (`coupon_redemptions`).
- **Pickup & Delivery Time Slots (`admin/slots.php`, `admin/schedules.php`):**
  - Configurable operational time windows (Morning, Midday, Afternoon, Evening) with daily capacity quotas.
  - Real-time capacity checks preventing driver over-allocation.
- **Customer Reviews & Ratings (`customer/reviews.php`, `admin/reviews.php`):**
  - Verified post-delivery rating system (1 to 5 stars) with written feedback.
  - Database constraint enforcing exactly one review per fulfilled booking (`UNIQUE KEY booking_id`).
- **Expense Management & Operating Profit (`admin/expenses.php`, `includes/expenses.php`):**
  - Categorized operational overhead tracking (cleaning supplies, utilities, packaging, maintenance, wages).
  - Estimated net operating profit calculations: $$\text{Operating Profit} = \text{COD Revenue} - \text{Operating Expenses}$$
  - Injection-safe CSV reporting for financial accounting.

### Phase 3: Advanced Operations & Inventory

- **Consumable Inventory Catalogue & SKU Tracking (`admin/inventory.php`, `admin/inventory_item.php`):**
  - Full inventory management for detergents, fabric conditioners, packaging poly, and chemicals.
  - Unique SKU tracking, purchase cost recording, and safety threshold alerts.
- **Stock Movement Ledger with Concurrency Control (`admin/stock_movements.php`):**
  - Stock In, Stock Out, and Manual Adjustment operations executed inside database transactions with `FOR UPDATE` row-level locks.
  - Guarantees non-negative inventory balances and maintains an immutable audit trail (`inventory_movements`).
- **Supplier Directory (`admin/suppliers.php`):**
  - Vendor relationship management linking suppliers to inventory items and purchase receipts.
- **Staff Shift Attendance Portal (`delivery/attendance.php`, `admin/attendance.php`):**
  - Single-active-session shift check-in and check-out with Asia/Colombo (+05:30) timestamps.
  - Automated working hours calculation, administrator monitoring, and payroll-ready CSV export.
- **Automated Low Stock Alert State Machine (`admin/low_stock.php`):**
  - Real-time detection of low stock (`current_stock <= min_stock`) and out-of-stock items.
  - Persistent deduplication state machine (`inventory_alert_state`) preventing alert spam.
  - Automated replenishment detection and resolution alerts.
- **Secure Database Backup & Safe Restore Preview (`admin/backups.php`, `admin/restore_preview.php`):**
  - Admin-only, CSRF-protected non-destructive database backup generation.
  - Offline storage outside the web root (`C:\xampp\backups\smart-laundry\`).
  - Safety-first restore preview displaying file metadata and detected tables without allowing destructive live web overwrites.

---

## Zero-Dependency Architecture

To guarantee long-term stability and effortless installation in offline or restricted environments:
- **No Composer Required:** Does not depend on third-party PHP packages.
- **No npm / Node Build Step Required:** All CSS and JavaScript assets are bundled locally.
- **Native PDF Engine:** Custom vector PDF 1.4 implementation with zlib Deflate compression.
- **Native QR Engine:** Pure mathematical ISO/IEC 18004 Reed-Solomon SVG generator.

---

## Security & Privacy Features

- **Zero PII Exposure in QR Codes:** Public tracking URLs use 64-character cryptographic tokens. The public tracking screen conceals customer names, phone numbers, addresses, and monetary amounts.
- **Prepared Statements Everywhere:** 100% of database interactions utilize PDO prepared statements with bound parameters to eliminate SQL injection.
- **CSRF Protection:** Synchronizer token pattern implemented across all state-altering forms and AJAX endpoints.
- **Role-Based Access Control (RBAC):** Strict session role validation (`admin`, `customer`, `delivery`) on every request.
- **Safe Database Restorations:** Live restore previews inspect SQL dumps safely without allowing destructive one-click database truncation from the browser.
- **Path Traversal & Command Injection Defenses:** Strict regex allowlists on all file download and backup management handlers.

---

## Screenshots & UI Showcase

| Admin Analytics Dashboard (Dark Mode) | Customer Booking & Coupon Application |
|:---:|:---:|
| ![Admin Dashboard](docs/screenshots/admin-dashboard.png) | ![New Booking](docs/screenshots/customer-booking.png) |

| Pure-PHP Vector PDF Invoice | Garment Bag QR Tracking Label |
|:---:|:---:|
| ![PDF Invoice](docs/screenshots/invoice-preview.png) | ![QR Tracking Label](docs/screenshots/qr-label.png) |

| Inventory Management & Ledger | Staff Shift Attendance Tracker |
|:---:|:---:|
| ![Inventory Ledger](docs/screenshots/inventory-ledger.png) | ![Staff Attendance](docs/screenshots/staff-attendance.png) |

*(Place screenshots in `docs/screenshots/` to display preview images on GitHub).*

---

## Technology Stack

- **Backend:** PHP 8.1+ / 8.2 (Strict typing, PDO MySQL, Session management, zlib)
- **Database:** MySQL 8.0+ / MariaDB 10.4+ (InnoDB engine, foreign keys, UTF8mb4)
- **Frontend:** Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Vanilla JavaScript (ES6+)
- **Data Visualization:** Chart.js 4.4.3 (Theme-adaptive palettes)
- **Supported Platforms:** XAMPP, WampServer, Docker, Linux (LEMP/LAMP)

---

## Directory Structure

```text
smart-laundry/
├── actions/                  # Business logic action handlers (bookings, admin, delivery)
│   ├── admin_actions.php     # Admin state modifications
│   ├── booking_actions.php   # Checkout, coupon application, order creation
│   ├── delivery_actions.php  # Delivery assignments, status updates, COD collection
│   └── handle.php            # Primary action dispatcher
├── admin/                    # Administrator control panel pages
│   ├── attendance.php        # Staff attendance oversight & export
│   ├── backups.php           # Database backup management
│   ├── bookings.php          # Booking management & assignment
│   ├── coupons.php           # Discount coupon administration
│   ├── customers.php         # Customer directory
│   ├── dashboard.php         # Primary management dashboard
│   ├── expenses.php          # Operating expenses & profit tracking
│   ├── inventory.php         # Consumables inventory catalogue
│   ├── inventory_item.php    # Item create & edit forms
│   ├── low_stock.php         # Low stock alert management
│   ├── reports.php           # Financial & operational analytics
│   ├── restore_preview.php   # Safe backup inspection tool
│   ├── reviews.php           # Customer review moderation
│   ├── schedules.php         # Dispatch calendar & pickup slots
│   ├── services.php          # Service pricing management
│   ├── slots.php             # Pickup time slot configuration
│   ├── stock_movements.php   # Stock In/Out movement ledger
│   └── suppliers.php         # Supplier directory
├── assets/                   # Static assets (CSS, JS, bundled vendor libraries)
│   ├── css/style.css         # Custom layout, themes (Dark/Light), scrollbars
│   ├── js/app.js             # Theme toggle, AJAX validation, dynamic interactions
│   └── vendor/               # Bundled Bootstrap, Bootstrap Icons, Chart.js
├── config/                   # Configuration files
│   ├── .htaccess             # Apache access denial for config files
│   ├── database.example.php  # Safe configuration template for developers
│   └── database.php          # Local database connection (excluded from Git)
├── customer/                 # Customer portal pages
│   ├── bookings.php          # Order history & status
│   ├── booking_details.php   # Booking breakdown & invoice link
│   ├── dashboard.php         # Customer account summary
│   ├── new_booking.php       # Item selection, time slots, coupon checkout
│   ├── profile.php           # Profile & contact management
│   └── reviews.php           # Post-delivery feedback form
├── database/                 # Database scripts & schema
│   ├── schema.sql            # Complete turnkey schema with clean seeds (Phases 1-3)
│   ├── database.sql          # Baseline Phase 1 database schema
│   └── migrations/           # Incremental additive migrations
│       ├── phase1_upgrade.sql # Phase 1 schema upgrades
│       ├── phase2_upgrade.sql # Phase 2 schema upgrades
│       └── phase3_upgrade.sql # Phase 3 schema upgrades
├── delivery/                 # Delivery staff portal pages
│   ├── attendance.php        # Shift check-in & check-out
│   ├── completed.php         # Fulfilled deliveries archive
│   ├── dashboard.php         # Active deliveries & today's route
│   ├── orders.php            # Assigned orders & COD collection
│   └── order_details.php     # Customer address, phone & status updates
├── docs/                     # Architectural documentation & upgrade reports
│   ├── phase1-upgrade.md     # Phase 1 technical specification
│   ├── phase2-upgrade.md     # Phase 2 technical specification
│   └── phase3-upgrade.md     # Phase 3 technical specification
├── includes/                 # Shared components, helpers, and domain modules
│   ├── app.php               # Application routing and guard logic
│   ├── bootstrap.php         # Session, CSRF, security headers, helpers
│   ├── functions.php         # Core domain helper functions & queries
│   ├── header.php            # Common navigation & notification bell
│   ├── footer.php            # Common footer & scripts
│   ├── pdf.php               # Zero-dependency Pure-PHP PDF generator
│   ├── qrcode.php            # Zero-dependency Pure-PHP QR SVG generator
│   └── sidebar.php           # Responsive, scrollable navigation sidebar
├── tests/                    # Automated regression & integration test suites
│   ├── smoke.php             # End-to-end HTTP smoke tests
│   ├── demo_accounts.php     # Account integrity & self-repair checks
│   ├── test_phase1.php       # Phase 1 feature verification suite
│   ├── test_phase2.php       # Phase 2 feature verification suite
│   ├── test_phase3.php       # Phase 3 feature verification suite
│   ├── test_sidebar_scroll.php # Viewport scrolling verification
│   └── test_theme.js         # Theme switching verification
├── .gitignore                # Comprehensive Git exclusions for security
├── invoice.php               # Standalone PDF invoice viewer & downloader
├── notifications.php         # Full notification history page
├── qr_label.php              # Printable garment bag QR label
├── setup.php                 # CLI database installer & demo account seed tool
└── track.php                 # Public zero-PII smartphone tracking page
```

---

## Installation & Setup

### Prerequisites

- **PHP 8.1 or later** with extensions: `pdo_mysql`, `session`, `zlib`, `curl`.
- **MySQL 8.0+** or **MariaDB 10.4+**.
- **Apache** (XAMPP recommended on Windows).

### Step 1: Clone the Repository

```bash
git clone https://github.com/your-username/smart-laundry.git
cd smart-laundry
```

### Step 2: Configure the Database Connection

Copy the example configuration to `config/database.php`:

```bash
cp config/database.example.php config/database.php
```

Open `config/database.php` and verify your database credentials (defaults work with standard XAMPP):
```php
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_NAME') ?: 'smart_laundry_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
```

### Step 3: Install the Database

Choose **Option A** (turnkey SQL import) or **Option B** (automated PHP CLI setup):

#### Option A: Direct SQL Import (Recommended)

Import `database/schema.sql` into MySQL/MariaDB:

```bash
# Using MySQL CLI:
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS smart_laundry_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p smart_laundry_db < database/schema.sql
```

#### Option B: Automated CLI Setup Script

```bash
php setup.php
```

*Note: `setup.php` is restricted strictly to CLI execution and cannot be accessed over HTTP.*

### Step 4: Run the Application

Place the project in your web server root (e.g. `C:\xampp\htdocs\smart-laundry`) and open:

```
http://localhost/smart-laundry/
```

---

## Demo Accounts

The installation schema automatically provisions three standard demo accounts with pre-hashed credentials:

| Role | Email | Password | Access Capabilities |
|:---|:---|:---|:---|
| **Administrator** | `admin@laundry.com` | `Admin@123` | Full access: Analytics, Inventory, Staff Attendance, Backups, Invoices |
| **Customer** | `customer@laundry.com` | `Customer@123` | Book services, Apply coupons, Track orders, View PDF invoices, Write reviews |
| **Delivery Staff** | `delivery@laundry.com` | `Delivery@123` | Shift check-in/out, Assigned deliveries, COD collection, Bag labels |

---

## Automated Testing Suite

The repository includes a comprehensive automated test harness covering all functional requirements:

```powershell
# 1. Phase 3 Advanced Management Test Suite (79 checks)
php tests/test_phase3.php http://localhost/smart-laundry

# 2. Phase 2 Business Management Test Suite (69 checks)
php tests/test_phase2.php http://localhost/smart-laundry

# 3. Phase 1 Comprehensive Test Suite (66 checks)
php tests/test_phase1.php http://localhost/smart-laundry

# 4. End-to-End Smoke Tests (68 system checks)
php tests/smoke.php http://localhost/smart-laundry

# 5. Demo Account Integrity & Self-Repair Test (16 checks)
php tests/demo_accounts.php http://localhost/smart-laundry

# 6. Sidebar Scrolling & Viewport Test (14 checks)
php tests/test_sidebar_scroll.php http://localhost/smart-laundry

# 7. Light / Dark Theme Test
node tests/test_theme.js
```

---

## Phase Documentation

Detailed engineering reports and architecture documentation are located in the `docs/` folder:
- [Phase 1 Technical Specification](docs/phase1-upgrade.md) — PDF engine, QR tracking, notifications, analytics.
- [Phase 2 Technical Specification](docs/phase2-upgrade.md) — Coupons, time slots, reviews, expense tracking.
- [Phase 3 Technical Specification](docs/phase3-upgrade.md) — Inventory ledger, staff attendance, stock alerts, backups.

---

## License

This project is licensed under the [MIT License](LICENSE).
