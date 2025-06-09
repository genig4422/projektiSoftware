-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 09, 2025 at 03:41 PM
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
-- Database: `phpdatabase`
--

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `business_id` int(11) NOT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`business_id`, `business_name`, `address`, `contact_info`) VALUES
(1, 'Eagle Rentals', 'Tirana, Albania', '+355 694445555'),
(2, 'AutoRent Plus', 'Durrës, Albania', '+355 692221111'),
(4, 'Omaris Rental', 'Rruga Zyrihu', '13241325'),
(5, 'Ali Expres', '1234', '231122414');

-- --------------------------------------------------------

--
-- Table structure for table `cars`
--

CREATE TABLE `cars` (
  `car_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `license_plate` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cars`
--

INSERT INTO `cars` (`car_id`, `business_id`, `brand`, `model`, `license_plate`) VALUES
(1, 1, 'Toyota', 'Yaris', 'AA123ZZ'),
(2, 1, 'Volkswagen', 'Golf', 'AB456YY'),
(3, 2, 'Hyundai', 'Tucson', 'AC789XX'),
(4, 1, 'Audi', 'a4', 'AA345GR'),
(5, 1, 'Audi', 'q3', '1323'),
(6, 1, 'Bmw', 'seria 5', '134'),
(7, 1, 'Bmw', 'seria 4', 'qe2'),
(8, 4, 'Toyota', 'sc2', '23424'),
(9, 4, 'Toyota', 'sc2', '2342432'),
(10, 4, 'Toyota', 'sc2', '234243223'),
(11, 4, 'dsffa', 'sc2', '1323rqwdesad'),
(12, 4, 'dsffa', 'sc2', '123ewq');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `business_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `name`, `phone`, `license_number`, `business_id`) VALUES
(1, 'Arben Hoxha', '+355681112233', 'L1234567', 1),
(2, 'Elira Beqiri', '+355682223344', 'L2345678', 1),
(3, 'Costumee New 1', '143241', '13123132123', 1),
(6, 'Fasida', '0684848485', '13123132123', 1),
(7, 'jordan sewes', '133213131313', '13123 sedad131', 1);

-- --------------------------------------------------------

--
-- Table structure for table `damage_reports`
--

CREATE TABLE `damage_reports` (
  `damage_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `reservation_id` int(10) UNSIGNED DEFAULT NULL,
  `maintenance_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `repair_cost` decimal(10,2) DEFAULT NULL,
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `damage_reports`
--

INSERT INTO `damage_reports` (`damage_id`, `car_id`, `reservation_id`, `maintenance_id`, `description`, `repair_cost`, `reported_at`) VALUES
(1, 2, NULL, 2, 'Rear bumper scratch', 50.00, '2025-06-06 22:00:00'),
(2, 7, NULL, NULL, 'qewqeqwe', 120.00, '2025-06-07 22:00:00'),
(3, 5, NULL, NULL, 'ka prishur parakolb, dere, motorr', 1200.00, '2025-06-07 22:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`log_id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'login', 'User logged in successfully', '2025-06-03 23:16:30'),
(2, 2, 'add_reservation', 'Reservation created for customer Elira', '2025-06-03 23:16:30');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `maintenance_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `maintenance_type` varchar(100) DEFAULT NULL,
  `maintenance_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cost` decimal(10,2) DEFAULT NULL,
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance`
--

INSERT INTO `maintenance` (`maintenance_id`, `car_id`, `maintenance_type`, `maintenance_date`, `cost`, `comments`) VALUES
(1, 1, 'Oil Change', '2025-04-30 22:00:00', 30.00, 'Regular oil change'),
(2, 2, 'Brake Check', '2025-04-14 22:00:00', 45.00, 'Replaced front pads'),
(3, 5, 'Vaj dhe Filtra', '2025-06-04 22:00:00', 35.00, 'vaj dhe filtra'),
(4, 4, 'Vaj dhe Filtra', '2025-06-09 22:00:00', 100.00, ''),
(5, 4, 'Vaj dhe Filtra', '2025-06-08 22:00:00', 220.00, ''),
(6, 4, 'Vaj dhe Filtra', '2025-06-08 22:00:00', 220.00, ''),
(7, 4, 'Vaj dhe Filtra', '2025-06-08 22:00:00', 220.00, ''),
(8, 4, 'Vaj dhe Filtra', '2025-06-08 22:00:00', 220.00, ''),
(9, 5, 'Vaj dhe Filtra', '2025-06-09 22:00:00', 240.00, 'adfsasd'),
(10, 11, 'Vaj dhe Filtra', '2025-06-08 22:00:00', 250.00, ''),
(11, 11, 'Vaj dhe Filtra', '2025-06-08 22:00:00', 250.00, ''),
(12, 11, 'Vaj dhe Filtra', '2025-06-08 22:00:00', 250.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` enum('service_due','booking_cancellation','maintenance_reminder','subscriptions_reminder') DEFAULT NULL,
  `status` enum('sent','pending') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `service_id`, `message`, `type`, `status`) VALUES
(2, 2, 2, 'Car Papers updated for VW Golf', 'service_due', 'pending'),
(3, 3, NULL, 'Subscription ending soon', '', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('payed','pending') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `reservation_id`, `amount`, `payment_date`, `status`) VALUES
(1, 1, 150.00, '2025-06-07 18:32:39', ''),
(2, 2, 200.00, '2025-06-01 22:00:00', NULL),
(3, 6, 134.00, '2025-06-07 19:01:18', ''),
(4, 7, 122.00, '2025-06-08 06:52:28', ''),
(5, 8, 450.00, '2025-06-08 07:09:10', ''),
(6, 9, 110.00, '2025-06-08 07:16:07', '');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `start_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `end_date` timestamp NULL DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`reservation_id`, `car_id`, `customer_id`, `start_date`, `end_date`, `total_cost`, `start_time`, `end_time`, `comments`) VALUES
(1, 1, 1, '2025-06-05 10:02:45', '2025-06-09 22:00:00', 150.00, '12:02:00', '22:00:00', NULL),
(2, 2, 2, '2025-06-05 10:03:06', '2025-06-06 22:00:00', 200.00, '12:02:00', '22:00:00', 'e do me sexholino makinen'),
(3, 1, 6, '2025-06-10 22:00:00', '2025-06-22 22:00:00', 360.00, '00:00:00', '14:00:00', ''),
(5, 6, 2, '2025-06-07 22:00:00', '2025-06-10 22:00:00', 250.00, '00:00:00', '09:00:00', ''),
(6, 5, 6, '2025-06-11 22:00:00', '2025-06-23 22:00:00', 134.00, '00:01:00', '12:33:00', '0'),
(7, 6, 6, '2025-06-25 22:00:00', '2025-06-27 22:00:00', 122.00, '22:51:00', '12:51:00', ''),
(8, 7, 2, '2025-07-06 22:00:00', '2025-07-17 22:00:00', 450.00, '22:59:00', '12:10:00', ''),
(9, 7, 6, '2025-06-21 22:00:00', '2025-06-22 22:00:00', 110.00, '10:00:00', '23:00:00', '');

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `return_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `return_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `condition_notes` text DEFAULT NULL,
  `damage_id` int(11) DEFAULT NULL,
  `mileage` int(11) DEFAULT NULL,
  `additional_fees` decimal(10,2) DEFAULT NULL,
  `final_invoice_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `returns`
--

INSERT INTO `returns` (`return_id`, `reservation_id`, `return_date`, `condition_notes`, `damage_id`, `mileage`, `additional_fees`, `final_invoice_amount`) VALUES
(1, 1, '2025-06-09 22:00:00', 'Clean, no damages', NULL, 55000, 0.00, 150.00),
(2, 2, '2025-06-06 22:00:00', 'Scratch on rear bumper', NULL, 62000, 50.00, 250.00);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `due_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL,
  `service_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `car_id`, `cost`, `due_date`, `created_by`, `service_type`) VALUES
(2, 2, 75.00, '2025-09-30 22:00:00', 2, NULL),
(3, 3, 120.00, '2025-08-14 22:00:00', 3, NULL),
(6, 4, 110.00, '2025-06-10 22:00:00', 7, NULL),
(7, 4, 110.00, '2025-06-09 22:00:00', 7, NULL),
(11, 6, 140.00, '2025-06-12 22:00:00', 7, NULL),
(12, 1, 0.00, '2025-06-10 22:00:00', 7, NULL),
(13, 4, 0.00, '2025-06-09 22:00:00', 7, 'Marledi do ndrim filtash'),
(14, 4, 0.00, '2025-06-09 22:00:00', 7, 'Marledi do ndrim filtash'),
(16, 12, 0.00, '2025-06-09 22:00:00', 8, 'Marledi do ndrim filtash');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `subscription_id` int(11) NOT NULL,
  `business_id` int(11) NOT NULL,
  `allowed_cars` int(11) NOT NULL,
  `start_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `end_date` timestamp NULL DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`subscription_id`, `business_id`, `allowed_cars`, `start_date`, `end_date`, `amount`, `status`) VALUES
(1, 1, 10, '2024-12-31 23:00:00', '0000-00-00 00:00:00', 500.00, 'inactive'),
(2, 2, 7, '2025-06-03 22:00:00', '2026-05-11 22:00:00', 350.00, 'active'),
(4, 4, 25, '2025-06-04 22:00:00', '2025-06-29 22:00:00', 50.00, 'active'),
(5, 5, 1, '2025-06-08 22:00:00', '2026-06-08 22:00:00', 200.00, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `business_id` int(11) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','owner','manager') DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `business_id`, `password`, `role`, `email`, `phone`, `name`) VALUES
(1, 1, '$2y$10$wXzQrcWiYkXxE7dMGhPK..Ht5t2sZyoEBg3U17k8j1xEfRtpQ/K6G', 'owner', 'john@eagle.com', '+355694445555', 'John Eagle'),
(2, 1, '$2y$10$kV48Pt3Zas/rgKlP9OySPe9iAbFXJKL1sMPMCy/WK6D3x/MZtJPyC', 'manager', 'sara@eagle.com', '+355692221111', 'Sara Manager'),
(3, 2, '$2y$10$4AKYURkTXBRjN2aCjeZf5OcTYaMf1MTZkb4YoTpzEWr0D2ocMx/Ia', 'owner', 'elda@autorent.com', '+355696667777', 'Elda Auto'),
(4, NULL, '$2y$10$FQXYxkTYrK8geTUCQSTNSeg7KvdWjqWZu/UgZCQxQi0nUjAypdc7a', 'admin', 'admin@system.com', '+355600000000', 'System Admin'),
(5, NULL, '$2y$10$QLD6cUM0PB3ZT75mmmij4e4Je2hiTVszUeJpep.H1Rpu4hksBaiUu', 'admin', 'genig4422@gmail.com', '5551003', 'Genmand Dulaj'),
(7, 1, '$2y$10$.uk4oGKCcPd/o62Nwp5Wcu2gD77uO8j1g4.80.gHcmN4IJIHErkiq', 'owner', 'test123@gmail.com', NULL, 'Rexhep Rexhepi'),
(8, 4, '$2y$10$Isloiqty9qmXjQHCJtX2Rug.gt1QwmGa74ZdAiJXeQ.ReYce83pMG', 'manager', 'test34@gmail.com', '143241', 'test34'),
(9, 5, '$2y$10$v1ZcmjucXBn5ojWJOHtIx.mYwvK20KiVYTRvSbljsYcsyuinWcH/2', 'owner', 'testonly1car@gmail.com', NULL, 'Aqif Kapertoni');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`business_id`);

--
-- Indexes for table `cars`
--
ALTER TABLE `cars`
  ADD PRIMARY KEY (`car_id`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD KEY `business_id` (`business_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `business_id` (`business_id`);

--
-- Indexes for table `damage_reports`
--
ALTER TABLE `damage_reports`
  ADD PRIMARY KEY (`damage_id`),
  ADD KEY `car_id` (`car_id`),
  ADD KEY `maintenance_id` (`maintenance_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`maintenance_id`),
  ADD KEY `car_id` (`car_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `reservation_id` (`reservation_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `car_id` (`car_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`return_id`),
  ADD UNIQUE KEY `reservation_id` (`reservation_id`),
  ADD KEY `damage_id` (`damage_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `car_id` (`car_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `business_id` (`business_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `business_id` (`business_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `business_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `cars`
--
ALTER TABLE `cars`
  MODIFY `car_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `damage_reports`
--
ALTER TABLE `damage_reports`
  MODIFY `damage_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `maintenance`
--
ALTER TABLE `maintenance`
  MODIFY `maintenance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `subscription_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cars`
--
ALTER TABLE `cars`
  ADD CONSTRAINT `cars_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`);

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`);

--
-- Constraints for table `damage_reports`
--
ALTER TABLE `damage_reports`
  ADD CONSTRAINT `damage_reports_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`car_id`),
  ADD CONSTRAINT `damage_reports_ibfk_2` FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance` (`maintenance_id`);

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD CONSTRAINT `maintenance_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`car_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`);

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`car_id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`);

--
-- Constraints for table `returns`
--
ALTER TABLE `returns`
  ADD CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`),
  ADD CONSTRAINT `returns_ibfk_2` FOREIGN KEY (`damage_id`) REFERENCES `damage_reports` (`damage_id`);

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`car_id`),
  ADD CONSTRAINT `services_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`business_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
