-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 07, 2025 at 09:18 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `website`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `admin_password` varchar(255) NOT NULL COMMENT 'Store password_hash() output',
  `email` varchar(150) NOT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `profilepicture` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `size` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1 CHECK (`quantity` > 0),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `customer_id`, `product_id`, `size`, `color`, `quantity`, `created_at`) VALUES
(41, 4, 8, 's', 'asdsd', 1, '2025-11-07 14:42:09');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `cust_password` varchar(255) NOT NULL COMMENT 'password_hash() output',
  `facebook_account` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  `phone_number` varchar(30) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `name`, `email`, `cust_password`, `facebook_account`, `address`, `profile_pic`, `registration_date`, `phone_number`, `updated_at`) VALUES
(2, 'user', 'user@example.com', '$2y$10$C5m/oa/1yOhUUTk6Xa6we.gM16L1TcIQ.d1BsR6xCuZ1BDaZNkTsu', NULL, 'somewhere', 'uploads/avatars/avatar_2_1761751746.png', '2025-10-29 23:05:12', '00000000000', '2025-10-29 23:29:47'),
(3, 'user2', 'user2@example.com', '$2y$10$wV0aih5FWuhRnrC/xNwodOQK9nuM0RR3ggCCvB3uf753Z4t5nNFFa', NULL, 'dewtrewt', NULL, '2025-11-02 22:47:04', '00000000002', '2025-11-02 23:03:19'),
(4, 'user3', 'user3@example.com', '$2y$10$hBuycU4RXJgVQVAYjybIPe202HOtjlGO/KzEMl4vzc.w2F3M8mKb.', NULL, 'somewhere', NULL, '2025-11-03 14:26:59', '00000000003', '2025-11-03 14:56:29'),
(5, 'user4', 'user4@example.com', '$2y$10$2jKOnvU070BEaj4revitCOQjxLikqA5iFXk/u24FxnvpYiwdxQDWq', NULL, 'Agusuihdx, Ozamiz, Misamugidfhf', NULL, '2025-11-03 21:18:02', '00000000004', '2025-11-03 21:35:02');

-- --------------------------------------------------------

--
-- Table structure for table `customization`
--

CREATE TABLE `customization` (
  `customization_id` int(10) UNSIGNED NOT NULL,
  `font_text` varchar(255) DEFAULT NULL,
  `font_size` varchar(50) DEFAULT NULL,
  `font_color` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customization`
--

INSERT INTO `customization` (`customization_id`, `font_text`, `font_size`, `font_color`, `color`, `note`, `created_at`) VALUES
(13, NULL, NULL, NULL, '#2099B7', '{\"camera\":[4.706381316553152e-16,2.353190658276576e-16,-3.8430519884018133],\"rotation\":[0,3.141592653589793,0,\"XYZ\"]}', '2025-11-02 23:02:52'),
(14, NULL, NULL, NULL, '#D62323', '{\"camera\":[4.2475091381892187e-16,2.1237545690946094e-16,-3.468354419532636],\"rotation\":[0,3.141592653589793,0,\"XYZ\"]}', '2025-11-03 14:20:05'),
(15, NULL, NULL, NULL, '#D62323', '{\"camera\":[1.7358777178572244,3.041158736579202e-16,-4.6533575054395415],\"rotation\":[0,3.141592653589793,0,\"XYZ\"]}', '2025-11-03 14:26:12'),
(16, NULL, NULL, NULL, '#C50606', '{\"camera\":[1.7358777178572244,3.041158736579202e-16,-4.6533575054395415],\"rotation\":[0,3.141592653589793,0,\"XYZ\"]}', '2025-11-03 14:26:16');

-- --------------------------------------------------------

--
-- Table structure for table `designoption`
--

CREATE TABLE `designoption` (
  `designoption_id` int(10) UNSIGNED NOT NULL,
  `customization_id` int(10) UNSIGNED DEFAULT NULL,
  `designfilepath` varchar(255) DEFAULT NULL,
  `request_design` text DEFAULT NULL,
  `design_status` enum('Requested','InProgress','ProofSent','Approved','Rejected') DEFAULT 'Requested',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `designoption`
--

INSERT INTO `designoption` (`designoption_id`, `customization_id`, `designfilepath`, `request_design`, `design_status`, `created_at`, `updated_at`) VALUES
(13, 13, 'uploads/designs/thumb_design_13_1762095772.png', '{\"camera\":[4.706381316553152e-16,2.353190658276576e-16,-3.8430519884018133],\"rotation\":[0,3.141592653589793,0,\"XYZ\"]}', 'Requested', '2025-11-02 23:02:52', '2025-11-02 23:02:52'),
(14, 14, 'uploads/designs/thumb_design_14_1762150806.png', '{\"camera\":[4.2475091381892187e-16,2.1237545690946094e-16,-3.468354419532636],\"rotation\":[0,3.141592653589793,0,\"XYZ\"]}', 'Requested', '2025-11-03 14:20:06', '2025-11-03 14:20:06'),
(15, 15, 'uploads/designs/thumb_design_15_1762151172.png', '{\"camera\":[1.7358777178572244,3.041158736579202e-16,-4.6533575054395415],\"rotation\":[0,3.141592653589793,0,\"XYZ\"]}', 'Requested', '2025-11-03 14:26:12', '2025-11-03 14:26:12'),
(16, 16, 'uploads/designs/thumb_design_16_1762151176.png', '{\"camera\":[1.7358777178572244,3.041158736579202e-16,-4.6533575054395415],\"rotation\":[0,3.141592653589793,0,\"XYZ\"]}', 'Requested', '2025-11-03 14:26:16', '2025-11-03 14:26:16');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `message_content` text NOT NULL,
  `time_stamp` datetime NOT NULL DEFAULT current_timestamp(),
  `message_status` enum('Unread','Read','Archived') NOT NULL DEFAULT 'Unread',
  `attachment_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Deprecated if using order_items; keep for now',
  `customer_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `designoption_id` int(10) UNSIGNED DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL COMMENT 'Deprecated if using order_items',
  `quantity` int(10) UNSIGNED DEFAULT NULL COMMENT 'Deprecated if using order_items',
  `partial_payment` tinyint(1) NOT NULL DEFAULT 0,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_status` enum('Pending','Processing','Ready','Shipped','Completed','Cancelled','Ready for Pickup','Ready to Ship') NOT NULL DEFAULT 'Pending',
  `delivery_address` varchar(255) DEFAULT NULL,
  `delivery_status` enum('Pending','Shipped','Delivered','Completed','Picked up','Failed') NOT NULL DEFAULT 'Pending',
  `phone_number` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `product_id`, `customer_id`, `admin_id`, `designoption_id`, `size`, `quantity`, `partial_payment`, `total_amount`, `order_status`, `delivery_address`, `delivery_status`, `phone_number`, `created_at`) VALUES
(1, NULL, 2, NULL, NULL, '12oz', 1, 0, 0.00, 'Completed', NULL, 'Completed', '00000000000', '2025-10-29 16:30:27'),
(2, NULL, 3, NULL, NULL, '12oz', 3, 0, 0.00, 'Completed', NULL, 'Picked up', '00000000002', '2025-11-02 16:04:11'),
(3, 8, 4, NULL, NULL, 's', 2, 0, 0.00, 'Completed', NULL, 'Picked up', '00000000003', '2025-11-07 06:58:17'),
(4, 8, 4, NULL, NULL, 's', 1, 0, 0.00, 'Completed', NULL, 'Completed', '00000000003', '2025-11-07 07:00:05'),
(5, 8, 4, NULL, NULL, 's', 1, 0, 0.00, 'Completed', NULL, 'Completed', '00000000003', '2025-11-07 07:26:43'),
(6, 8, 4, NULL, NULL, 's', 1, 0, 0.00, 'Completed', NULL, 'Picked up', '00000000003', '2025-11-07 07:41:42');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `size` varchar(50) DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1 CHECK (`quantity` > 0),
  `line_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `size`, `quantity`, `line_price`) VALUES
(3, 3, 8, 's', 2, 600.00),
(4, 4, 8, 's', 1, 300.00),
(5, 5, 8, 's', 1, 300.00),
(6, 6, 8, 's', 1, 300.00);

-- --------------------------------------------------------

--
-- Stand-in structure for view `order_summary`
-- (See below for the actual view)
--
CREATE TABLE `order_summary` (
);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('Pending','Paid','Partial','Refunded') NOT NULL DEFAULT 'Pending',
  `payment_method` enum('Cash','GCash','Card','Bank','Other') NOT NULL DEFAULT 'Cash',
  `amount_paid` decimal(30,0) NOT NULL,
  `img_proof` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `service_type` varchar(100) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `product_details` text DEFAULT NULL,
  `images` text DEFAULT NULL COMMENT 'Could store JSON array; consider separate product_images table',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `admin_id`, `service_type`, `product_name`, `product_details`, `images`, `created_at`, `updated_at`) VALUES
(8, NULL, 'Aparrel Printing', 'T-Shirt Printing', 'fghh', '[]', '2025-11-06 12:34:22', '2025-11-06 12:50:19');

-- --------------------------------------------------------

--
-- Table structure for table `products_sub`
--

CREATE TABLE `products_sub` (
  `sub_id` int(10) NOT NULL,
  `types` varchar(30) NOT NULL,
  `sizes` varchar(30) NOT NULL,
  `attributes` varchar(30) NOT NULL,
  `price` decimal(30,2) NOT NULL,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `kind` varchar(20) NOT NULL DEFAULT '',
  `value` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products_sub`
--

INSERT INTO `products_sub` (`sub_id`, `types`, `sizes`, `attributes`, `price`, `product_id`, `kind`, `value`) VALUES
(27, 'fgg', '', '', 0.00, 8, 'type', 'fgg'),
(28, 'grge', '', '', 0.00, 8, 'type', 'grge'),
(29, '', 's', '', 0.00, 8, 'size', 's'),
(30, '', 'd', '', 0.00, 8, 'size', 'd'),
(31, '', '', 'asdsd', 200.00, 8, 'attribute', 'asdsd'),
(32, '', '', 'wad', 300.00, 8, 'attribute', 'wad'),
(33, 'vfvffv', '', '', 0.00, 8, 'type', 'vfvffv'),
(34, '', 'g', '', 0.00, 8, 'size', 'g'),
(35, '', '', 'derff', 400.00, 8, 'attribute', 'derff');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `product_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `report_type` varchar(100) DEFAULT NULL,
  `report_details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL CHECK (`rating` between 1 and 5),
  `review_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `name`, `description`, `image`, `created_at`) VALUES
(4, 'Aparrel Printing', '', 'uploads/services/1-1761750223-65f99268.png', '2025-10-29 23:03:43'),
(6, 'Signages', '', NULL, '2025-11-04 09:34:24');

-- --------------------------------------------------------

--
-- Structure for view `order_summary`
--
DROP TABLE IF EXISTS `order_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `order_summary`  AS SELECT `o`.`order_id` AS `order_id`, `o`.`customer_id` AS `customer_id`, `o`.`TotalAmount` AS `stored_total`, coalesce(sum(`oi`.`line_price`),0) AS `computed_total`, `o`.`OrderStatus` AS `OrderStatus`, `o`.`created_at` AS `created_at` FROM (`orders` `o` left join `order_items` `oi` on(`oi`.`order_id` = `o`.`order_id`)) GROUP BY `o`.`order_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `uq_admin_email` (`email`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `uq_cart_line` (`customer_id`,`product_id`,`size`,`color`),
  ADD KEY `idx_cart_customer` (`customer_id`),
  ADD KEY `idx_cart_product` (`product_id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `uq_customer_email` (`email`);

--
-- Indexes for table `customization`
--
ALTER TABLE `customization`
  ADD PRIMARY KEY (`customization_id`);

--
-- Indexes for table `designoption`
--
ALTER TABLE `designoption`
  ADD PRIMARY KEY (`designoption_id`),
  ADD KEY `idx_designoption_status` (`design_status`),
  ADD KEY `fk_designoption_customization` (`customization_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `fk_messages_admin` (`admin_id`),
  ADD KEY `idx_messages_customer` (`customer_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_orders_customer_created` (`customer_id`,`created_at`),
  ADD KEY `idx_orders_status` (`order_status`),
  ADD KEY `fk_orders_admin` (`admin_id`),
  ADD KEY `fk_orders_designoption` (`designoption_id`),
  ADD KEY `fk_orders_product` (`product_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `fk_order_items_product` (`product_id`),
  ADD KEY `idx_order_items_order` (`order_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payments_customer` (`customer_id`),
  ADD KEY `fk_payments_admin` (`admin_id`),
  ADD KEY `fk_payments_order` (`order_id`),
  ADD KEY `idx_payments_status` (`payment_status`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_products_name` (`product_name`),
  ADD KEY `idx_products_service` (`service_type`),
  ADD KEY `fk_products_admin` (`admin_id`);

--
-- Indexes for table `products_sub`
--
ALTER TABLE `products_sub`
  ADD PRIMARY KEY (`sub_id`),
  ADD UNIQUE KEY `uniq_prod_kind_val` (`product_id`,`kind`,`value`),
  ADD KEY `idx_prod` (`product_id`),
  ADD KEY `idx_kind` (`kind`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD KEY `idx_product_images_product` (`product_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `fk_reports_product` (`product_id`),
  ADD KEY `fk_reports_admin` (`admin_id`),
  ADD KEY `fk_reports_customer` (`customer_id`),
  ADD KEY `idx_reports_type` (`report_type`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `fk_reviews_customer` (`customer_id`),
  ADD KEY `idx_reviews_product` (`product_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customization`
--
ALTER TABLE `customization`
  MODIFY `customization_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `designoption`
--
ALTER TABLE `designoption`
  MODIFY `designoption_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `products_sub`
--
ALTER TABLE `products_sub`
  MODIFY `sub_id` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `designoption`
--
ALTER TABLE `designoption`
  ADD CONSTRAINT `fk_designoption_customization` FOREIGN KEY (`customization_id`) REFERENCES `customization` (`customization_id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_messages_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_orders_designoption` FOREIGN KEY (`designoption_id`) REFERENCES `designoption` (`designoption_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reports_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reports_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
