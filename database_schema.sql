-- =============================================================
-- Website Application Database Schema
-- Generated: 2025-10-07
-- Purpose: Full schema (improved) for import via phpMyAdmin / CLI.
-- Notes:
--  * Keeps existing column names used by current PHP code (mixed case in orders columns).
--  * Adds missing columns (created_at, phone_number in orders) and indexes.
--  * Leaves product_id/size/quantity in orders for backward compatibility.
--  * Provides improved/normalized alternatives for reports & reviews.
--  * MySQL (InnoDB, utf8mb4) compatible; run:  mysql -u root -p < database_schema.sql
--  * If you already have data, REMOVE the DROP statements or back up first.
-- =============================================================

-- Safety: Disable FK checks during (re)creation
SET FOREIGN_KEY_CHECKS = 0;

-- (Optional) Create database
CREATE DATABASE IF NOT EXISTS `website` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `website`;

-- =============================================================
-- Table: admin
-- =============================================================
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `admin_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `admin_password` VARCHAR(255) NOT NULL COMMENT 'Store password_hash() output',
  `email` VARCHAR(150) NOT NULL,
  `phone_number` VARCHAR(30) NULL,
  `profilepicture` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: customer (singular per existing code preference)
-- =============================================================
DROP TABLE IF EXISTS `customer`;
CREATE TABLE `customer` (
  `customer_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `cust_password` VARCHAR(255) NOT NULL COMMENT 'password_hash() output',
  `facebook_account` VARCHAR(150) NULL,
  `address` VARCHAR(255) NULL,
  `profile_pic` VARCHAR(255) NULL,
  `registration_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `phone_number` VARCHAR(30) NULL,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_customer_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: products
-- =============================================================
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `product_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NULL,
  `service_type` VARCHAR(100) NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `product_details` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `images` TEXT NULL COMMENT 'Could store JSON array; consider separate product_images table',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_products_name` (`product_name`),
  KEY `idx_products_service` (`service_type`),
  CONSTRAINT `fk_products_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: cart
-- =============================================================
DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `cart_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `size` VARCHAR(50) NULL,
  `color` VARCHAR(50) NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1 CHECK (`quantity` > 0),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cart_line` (`customer_id`,`product_id`,`size`,`color`),
  KEY `idx_cart_customer` (`customer_id`),
  KEY `idx_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: customization (legacy – retained)
-- =============================================================
DROP TABLE IF EXISTS `customization`;
CREATE TABLE `customization` (
  `customization_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `font_text` VARCHAR(255) NULL,
  `font_size` VARCHAR(50) NULL,
  `font_color` VARCHAR(50) NULL,
  `color` VARCHAR(50) NULL,
  `note` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: designoption (legacy – references customization)
-- =============================================================
DROP TABLE IF EXISTS `designoption`;
CREATE TABLE `designoption` (
  `designoption_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customization_id` INT UNSIGNED NULL,
  `designfilepath` VARCHAR(255) NULL,
  `request_design` TEXT NULL,
  `design_status` ENUM('Requested','InProgress','ProofSent','Approved','Rejected') DEFAULT 'Requested',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_designoption_status` (`design_status`),
  CONSTRAINT `fk_designoption_customization` FOREIGN KEY (`customization_id`) REFERENCES `customization`(`customization_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: orders (keeps product_id/size/quantity for backward compatibility; plan to move to order_items exclusively later)
-- =============================================================
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `order_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NULL COMMENT 'Deprecated if using order_items; keep for now',
  `customer_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED NULL,
  `designoption_id` INT UNSIGNED NULL,
  `size` VARCHAR(50) NULL COMMENT 'Deprecated if using order_items',
  `quantity` INT UNSIGNED NULL COMMENT 'Deprecated if using order_items',
  `isPartialPayment` TINYINT(1) NOT NULL DEFAULT 0,
  `TotalAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `OrderStatus` ENUM('Pending','Processing','Ready','Shipped','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `DeliveryAddress` VARCHAR(255) NULL,
  `DeliveryStatus` ENUM('Pending','Dispatched','Delivered','Failed') NOT NULL DEFAULT 'Pending',
  `phone_number` VARCHAR(30) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_orders_customer_created` (`customer_id`,`created_at`),
  KEY `idx_orders_status` (`OrderStatus`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_designoption` FOREIGN KEY (`designoption_id`) REFERENCES `designoption`(`designoption_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: order_items (normalized line items)
-- =============================================================
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `order_item_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `size` VARCHAR(50) NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1 CHECK (`quantity` > 0),
  `line_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
  KEY `idx_order_items_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: payments
-- =============================================================
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `payment_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `payment_amount` DECIMAL(10,2) NOT NULL,
  `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_status` ENUM('Pending','Paid','Partial','Refunded') NOT NULL DEFAULT 'Pending',
  `payment_method` ENUM('Cash','GCash','Card','Bank','Other') NOT NULL DEFAULT 'Cash',
  CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
  KEY `idx_payments_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: messages
-- =============================================================
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `message_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NULL,
  `message_content` TEXT NOT NULL,
  `time_stamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message_status` ENUM('Unread','Read','Archived') NOT NULL DEFAULT 'Unread',
  `attachment_url` VARCHAR(255) NULL,
  CONSTRAINT `fk_messages_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE CASCADE,
  KEY `idx_messages_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: product_images (recommended, optional)
-- =============================================================
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `image_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
  KEY `idx_product_images_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: reviews (normalized replacement for duplicated structure)
-- =============================================================
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `review_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `review_text` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE CASCADE,
  KEY `idx_reviews_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- Table: reports (normalized)
-- =============================================================
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `report_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NULL,
  `admin_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NULL,
  `report_type` VARCHAR(100) NULL,
  `report_details` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_reports_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE SET NULL,
  KEY `idx_reports_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- (Optional) View: order_summary (aggregated totals from order_items)
-- =============================================================
DROP VIEW IF EXISTS `order_summary`;
CREATE VIEW `order_summary` AS
SELECT o.order_id,
       o.customer_id,
       o.TotalAmount AS stored_total,
       COALESCE(SUM(oi.line_price),0) AS computed_total,
       o.OrderStatus,
       o.created_at
FROM orders o
LEFT JOIN order_items oi ON oi.order_id = o.order_id
GROUP BY o.order_id;

-- Re-enable FK checks
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- Post-import reminders:
-- 1. Migrate existing data before dropping legacy duplicate columns.
-- 2. Update PHP code to insert line items into order_items (future refactor).
-- 3. Ensure password hashing on insert/update (NEVER store plaintext).
-- 4. Consider adding: password_reset_tokens, audit_log, inventory, search indices.
-- =============================================================
