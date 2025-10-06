-- =============================================================
-- MINIMAL SCHEMA for current application (compat version)
-- Target: XAMPP typical MySQL/MariaDB (older versions) - avoids VIEW, CHECK, ENUM expansions, extra tables.
-- Import order-safe; you can extend later with full schema.
-- =============================================================
SET FOREIGN_KEY_CHECKS = 0;
CREATE DATABASE IF NOT EXISTS `website` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `website`;

-- ADMIN
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `admin_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `admin_password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone_number` VARCHAR(30) NULL,
  `profilepicture` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CUSTOMER (singular; code maps to customer)
DROP TABLE IF EXISTS `customer`;
CREATE TABLE `customer` (
  `customer_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `cust_password` VARCHAR(255) NOT NULL,
  `facebook_account` VARCHAR(150) NULL,
  `address` VARCHAR(255) NULL,
  `profile_pic` VARCHAR(255) NULL,
  `registration_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `phone_number` VARCHAR(30) NULL,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_customer_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PRODUCTS
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `product_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NULL,
  `service_type` VARCHAR(100) NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `product_details` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `images` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_products_name` (`product_name`),
  KEY `idx_products_service` (`service_type`),
  CONSTRAINT `fk_products_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CART
DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `cart_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `size` VARCHAR(50) NULL,
  `color` VARCHAR(50) NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_cart_line` (`customer_id`,`product_id`,`size`,`color`),
  KEY `idx_cart_customer` (`customer_id`),
  KEY `idx_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ORDERS (keeps product fields for now)
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `order_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED NULL,
  `size` VARCHAR(50) NULL,
  `quantity` INT UNSIGNED NULL,
  `isPartialPayment` TINYINT(1) NOT NULL DEFAULT 0,
  `TotalAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `OrderStatus` VARCHAR(30) NOT NULL DEFAULT 'Pending',
  `DeliveryAddress` VARCHAR(255) NULL,
  `DeliveryStatus` VARCHAR(30) NOT NULL DEFAULT 'Pending',
  `phone_number` VARCHAR(30) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_orders_customer_created` (`customer_id`,`created_at`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ORDER ITEMS (future multi-product orders)
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `order_item_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `size` VARCHAR(50) NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `line_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
  KEY `idx_order_items_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PAYMENTS
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `payment_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `payment_amount` DECIMAL(10,2) NOT NULL,
  `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_status` VARCHAR(30) NOT NULL DEFAULT 'Pending',
  `payment_method` VARCHAR(30) NOT NULL DEFAULT 'Cash',
  CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
  KEY `idx_payments_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- MESSAGES
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `message_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NULL,
  `message_content` TEXT NOT NULL,
  `time_stamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message_status` VARCHAR(20) NOT NULL DEFAULT 'Unread',
  `attachment_url` VARCHAR(255) NULL,
  CONSTRAINT `fk_messages_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin`(`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer`(`customer_id`) ON DELETE CASCADE,
  KEY `idx_messages_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
-- END MINIMAL SCHEMA
