-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 13, 2026 at 10:34 PM
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
-- Database: `thrive`
--

-- --------------------------------------------------------

--
-- Table structure for table `business`
--

CREATE TABLE `business` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `business_name` varchar(100) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `capital` decimal(12,2) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `business`
--

INSERT INTO `business` (`id`, `user_id`, `business_name`, `industry`, `capital`, `phone`, `address`) VALUES
(1, 2, 'Thrives Clothing Store', 'Clothing', 50000.00, '0111', 'myadress1'),
(3, 1, 'Laptop shop', 'Electronics', 50000.00, '01644711310', '181, Block -C, lalkuthi Bazar, mirpur 1, dhaka'),
(4, 3, 'Watches Shop', 'Electronics', 80000.00, '566512123', 'fsdfsdf');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `id` int(11) NOT NULL,
  `business_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `division` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`id`, `business_id`, `customer_name`, `gender`, `age`, `phone`, `division`) VALUES
(1, 1, 'Ahmed Rahman', 'Male', 28, '01711111111', 'Dhaka'),
(2, 1, 'Sarah Islam', 'Female', 24, '01722222222', 'Dhaka'),
(3, 1, 'Nafis Khan', 'Male', 32, '01733333333', 'Chattogram'),
(4, 1, 'Ayesha Akter', 'Female', 27, '01744444444', 'Sylhet'),
(5, 1, 'Rafi Hasan', 'Male', 21, '01755555555', 'Khulna'),
(6, 1, 'Umme Salma Zimia', 'Female', 18, '01644715810', 'Dhaka'),
(7, 1, 'addas', 'Male', 55, '01600000010', 'Dhaka'),
(8, 1, 'Umme Salma Zimia', 'Female', 23, '01644711310', 'Dhaka'),
(9, 4, 'Sajek rahman', 'Male', 99, '9999999', '9999999'),
(10, 1, 'Madonna', 'Female', 68, '68686868', 'Dhaka');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `id` int(11) NOT NULL,
  `business_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`id`, `business_id`, `product_name`, `category`, `cost_price`, `selling_price`, `stock_quantity`) VALUES
(1, 1, 'Men T-Shirt', 'Mens Wear', 350.00, 550.00, 50),
(2, 1, 'Women Kurti', 'Womens Wear', 700.00, 1100.00, 30),
(3, 1, 'Denim Jeans', 'Pants', 900.00, 1400.00, 25),
(4, 1, 'Hoodie', 'Winter Wear', 1200.00, 1800.00, 20),
(5, 1, 'Polo Shirt', 'Mens Wear', 450.00, 750.00, 40),
(6, 1, 'Suit', 'Mens Wear', 1500.00, 2000.00, 10),
(9, 1, 'Kurti', 'Womens wear', 50.00, 60.00, 5),
(10, 1, 'Women Top', 'Top Wear', 500.00, 600.00, -600);

-- --------------------------------------------------------

--
-- Table structure for table `sale`
--

CREATE TABLE `sale` (
  `id` int(11) NOT NULL,
  `business_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `sale_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale`
--

INSERT INTO `sale` (`id`, `business_id`, `customer_id`, `product_id`, `quantity`, `total_amount`, `sale_date`) VALUES
(1, 1, 1, 1, 2, 1100.00, '2026-08-21'),
(2, 1, 2, 2, 1, 800.00, '2026-08-21'),
(3, 1, 3, 3, 1, 1500.00, '2026-08-21'),
(4, 1, 4, 4, 2, 2400.00, '2026-08-21'),
(5, 1, 5, 5, 1, 900.00, '2026-08-21'),
(6, 1, 2, 9, 2, 120.00, '2026-08-28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(40) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `created_at`) VALUES
(1, 'admin@gmail', '$2y$10$ROjfED5sZtwdhMMr/9s55uPMRTJLmj5OXPXr4QIrZBIMwEMosNMJe', '2026-08-16 12:19:31'),
(2, 'admin@gmail.com', '$2y$10$tDxfUVr.mbbM6bhFH.UGvuuhqGHRf7/CevANN/86G6T1X3LCXjE1i', '2026-08-19 11:11:13'),
(3, 'client1@gmail.com', '$2y$10$5cZhdcv4OHoy6sMGG3W3fe1h36HJo37nyOFCuEGkKv76WgwghOCGS', '2026-08-28 09:24:51'),
(4, 'client2@gmail.com', '$2y$10$fzOOl7YKWYOn2RSxYlqVquM0thmUv0Ce0fYqEs0DDKiBMfxsGleam', '2026-08-28 10:13:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `business`
--
ALTER TABLE `business`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_business_user` (`user_id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_business_name` (`business_id`,`product_name`);

--
-- Indexes for table `sale`
--
ALTER TABLE `sale`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `business`
--
ALTER TABLE `business`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `sale`
--
ALTER TABLE `sale`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
