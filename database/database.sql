CREATE DATABASE IF NOT EXISTS smart_laundry_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_laundry_db;
CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, phone VARCHAR(25) NOT NULL,
 password VARCHAR(255) NOT NULL, role ENUM('admin','customer','delivery') NOT NULL DEFAULT 'customer',
 address TEXT NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(role)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS services (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, service_name VARCHAR(100) NOT NULL,
 description VARCHAR(500) NOT NULL, price DECIMAL(10,2) NOT NULL CHECK(price>0),
 is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bookings (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, booking_number VARCHAR(30) NOT NULL UNIQUE,
 customer_id INT UNSIGNED NOT NULL, pickup_address TEXT NOT NULL, pickup_phone VARCHAR(25) NOT NULL,
 pickup_date DATE NOT NULL, total_amount DECIMAL(12,2) NOT NULL CHECK(total_amount>0),
 payment_method ENUM('COD') NOT NULL DEFAULT 'COD', payment_status ENUM('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid',
 booking_status ENUM('Pending','Accepted','Rejected','Picked Up','Washing','Ironing','Ready for Delivery','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
 special_instructions TEXT NOT NULL, paid_at DATETIME NULL, collected_by INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES users(id), FOREIGN KEY(collected_by) REFERENCES users(id),
 INDEX(customer_id,created_at), INDEX(booking_status), INDEX(payment_status,paid_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, booking_id INT UNSIGNED NOT NULL, service_id INT UNSIGNED NOT NULL,
 service_name VARCHAR(100) NOT NULL, quantity INT NOT NULL CHECK(quantity BETWEEN 1 AND 100),
 unit_price DECIMAL(10,2) NOT NULL CHECK(unit_price>0), subtotal DECIMAL(12,2) NOT NULL CHECK(subtotal>0),
 FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE, FOREIGN KEY(service_id) REFERENCES services(id),
 UNIQUE(booking_id,service_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS delivery_assignments (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, booking_id INT UNSIGNED NOT NULL UNIQUE, delivery_person_id INT UNSIGNED NOT NULL,
 assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE, FOREIGN KEY(delivery_person_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_status_history (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, booking_id INT UNSIGNED NOT NULL,
 status VARCHAR(30) NOT NULL, changed_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE, FOREIGN KEY(changed_by) REFERENCES users(id), INDEX(booking_id,created_at)
) ENGINE=InnoDB;
INSERT INTO services(service_name,description,price)
SELECT 'Wash & Fold','Freshly washed, carefully dried and neatly folded.',250 WHERE NOT EXISTS(SELECT 1 FROM services WHERE service_name='Wash & Fold');
INSERT INTO services(service_name,description,price)
SELECT 'Wash & Iron','Everyday garments washed and pressed, ready to wear.',350 WHERE NOT EXISTS(SELECT 1 FROM services WHERE service_name='Wash & Iron');
INSERT INTO services(service_name,description,price)
SELECT 'Ironing Only','A crisp, professional finish for your clean clothes.',150 WHERE NOT EXISTS(SELECT 1 FROM services WHERE service_name='Ironing Only');
INSERT INTO services(service_name,description,price)
SELECT 'Dry Cleaning','Specialist care for delicate garments and occasion wear.',500 WHERE NOT EXISTS(SELECT 1 FROM services WHERE service_name='Dry Cleaning');
INSERT INTO services(service_name,description,price)
SELECT 'Bedsheet Cleaning','Bring a fresh, comfortable feel back to your bedding.',400 WHERE NOT EXISTS(SELECT 1 FROM services WHERE service_name='Bedsheet Cleaning');
INSERT INTO services(service_name,description,price)
SELECT 'Blanket Cleaning','Deep cleaning for warm, soft and refreshed blankets.',800 WHERE NOT EXISTS(SELECT 1 FROM services WHERE service_name='Blanket Cleaning');
