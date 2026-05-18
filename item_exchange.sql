-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 08, 2025 at 10:09 AM
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
-- Database: `item_exchange`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `created_at`, `is_active`) VALUES
(1, 'แจ้งเตือนการปิดระบบเพื่อปรับปรุง', 'ระบบจะปิดให้บริการชั่วคราวในวันที่ 10 สิงหาคม 2025 เวลา 22.00 - 23.59 น. กรุณาเตรียมข้อมูลล่วงหน้า', '2025-08-06 18:15:19', 1),
(2, '	แจ้งเตือนการปิดระบบเพื่อปรับปรุง', 'ระบบจะปิดให้บริการชั่วคราวในวันที่ 18 สิงหาคม 2023 เวลา 22.00 - 23.59 น. กรุณาเตรียมข้อมูลล่วงหน้า', '2025-08-07 23:32:59', 1),
(3, 'แจ้งเตือนการปิดระบบเพื่อปรับปรุง', 'ระบบจะปิดให้บริการชั่วคราวในวันที่ 18 สิงหาคม 2023 เวลา 22.00 - 23.59 น. กรุณาเตรียมข้อมูลล่วงหน้า', '2025-08-08 18:34:45', 1);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(4, 'ของเล่น'),
(6, 'ของใช้ทั่วไป'),
(2, 'หนังสือ'),
(8, 'อุปกรณ์กีฬา'),
(9, 'อุปกรณ์เครื่องครัว'),
(3, 'เครื่องเขียน'),
(1, 'เครื่องใช้ไฟฟ้า');

-- --------------------------------------------------------

--
-- Table structure for table `exchange_requests`
--

CREATE TABLE `exchange_requests` (
  `id` int(11) NOT NULL,
  `item_owner_id` int(11) NOT NULL,
  `item_request_id` int(11) DEFAULT NULL,
  `requester_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `delivery_method` enum('grab','pickup') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `receiver_id` int(11) DEFAULT NULL,
  `receiver_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `requester_confirmed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exchange_requests`
--

INSERT INTO `exchange_requests` (`id`, `item_owner_id`, `item_request_id`, `requester_id`, `status`, `delivery_method`, `created_at`, `receiver_id`, `receiver_confirmed`, `requester_confirmed`) VALUES
(65, 88, 87, 11, 'accepted', 'pickup', '2025-10-04 16:43:03', 14, 1, 1),
(66, 94, NULL, 18, 'rejected', NULL, '2025-10-05 05:45:34', 14, 0, 0),
(67, 90, 85, 11, 'pending', NULL, '2025-10-08 07:52:52', 14, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `estimated_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `owner_id` int(11) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `condition_notes` text DEFAULT NULL,
  `purchase_year` int(4) DEFAULT NULL,
  `status` enum('แจกฟรี','แลกเปลี่ยน','ขายราคาถูก') NOT NULL DEFAULT 'แจกฟรี',
  `exchange_for` varchar(255) DEFAULT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `is_exchanged` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `name`, `category`, `price`, `estimated_price`, `created_at`, `owner_id`, `is_available`, `description`, `condition_notes`, `purchase_year`, `status`, `exchange_for`, `contact_name`, `contact_info`, `location`, `latitude`, `longitude`, `is_approved`, `is_exchanged`) VALUES
(85, 'กระบวยตักอาหาร', 'อุปกรณ์เครื่องครัว', 100.00, 90.00, '2025-10-04 16:33:52', 11, 1, 'กระบวยตักอาหารใช้น้อย', '90%', 2024, 'แลกเปลี่ยน', 'อุปกรณ์ในครัวต่างๆ', 'test34', '0987654321', 'ขามเรียง', 16.25056372, 103.24049950, 1, 0),
(86, 'ที่วางของในครัว', 'อุปกรณ์เครื่องครัว', 180.00, 100.00, '2025-10-04 16:35:04', 11, 1, 'ที่วางของในครัวใช้น้อย', '90%', 2024, 'แลกเปลี่ยน', 'อุปกรณ์ในครัวต่างๆ', 'test34', '0987654321', 'ตึก it', 16.24662901, 103.25200081, 1, 0),
(87, 'ที่ตีฟอง', 'อุปกรณ์เครื่องครัว', 190.00, 100.00, '2025-10-04 16:36:08', 11, 0, 'ที่ตีฟองไม่ได้ใช้', '90%', 2024, 'แลกเปลี่ยน', 'อุปกรณ์ในครัวต่างๆ', 'test34', '0987654321', 'หน้ามอ', 16.25064612, 103.25968266, 1, 0),
(88, 'กระบวยตักอาหารใหม่', 'อุปกรณ์เครื่องครัว', 100.00, 90.00, '2025-10-04 16:37:21', 14, 0, 'กระบวยตักอาหารใหม่ไม่ได้ใช้แล้ว', '90%', 2025, 'แลกเปลี่ยน', 'อุปกรณ์ในครัวต่างๆ', 'game34', '0980899876', 'หน้ามอ', 16.24965730, 103.25590611, 1, 0),
(89, 'ช้อนซ้อมทำอาหาร', 'อุปกรณ์เครื่องครัว', 250.00, 120.00, '2025-10-04 16:38:18', 14, 1, 'ช้อนซ้อมทำอาหารไม่ได้ใช้', '90%', 2025, 'แลกเปลี่ยน', 'อุปกรณ์ในครัวต่างๆ', 'game34', '0980899876', 'ตึก it', 16.24656720, 103.25197935, 1, 0),
(90, 'กระทะ', 'อุปกรณ์เครื่องครัว', 250.00, 200.00, '2025-10-04 16:39:11', 14, 1, 'กระทะใช้น้อย', '90%', 2024, 'แลกเปลี่ยน', 'อุปกรณ์ในครัวต่างๆ', 'game34', '0980899876', 'ขามเรียง', 16.25060492, 103.24062824, 1, 0),
(91, 'เคส Ipad gen9', 'ของใช้ทั่วไป', 590.00, 480.00, '2025-10-05 02:21:07', 11, 1, 'ใช้งานดีปกติ', '95%', 2024, 'แลกเปลี่ยน', 'เคส Iphone 13', 'test', 'facebook test1', 'MSU Library', 16.24428000, 103.24901000, 1, 0),
(92, 'แว่นกรองแสง เลนส์ออโต้', 'ของใช้ทั่วไป', 890.00, 800.00, '2025-10-05 02:23:50', 11, 1, 'ตำหนิน้อยมาก', '98%', 2025, 'แลกเปลี่ยน', 'ไดร์เป่าผม', 'test', 'facebook test1', 'Msu 1', 16.24428000, 103.24901000, 1, 0),
(93, 'หูฟังบลูทูธไร้สาย iSuper Evo Buds 2', 'ของใช้ทั่วไป', 599.00, 380.00, '2025-10-05 02:27:20', 11, 1, 'เสียงดี เบสนุ่ม ดีเลย์น้อย', '92%', 2024, 'แลกเปลี่ยน', 'สายชาร์จไอเเพด Gen9', 'test', 'facebook test1', 'Msu 3', 16.24428000, 103.24901000, 1, 0),
(94, 'กระเป๋ารีไซเคิลรักษ์โลก', 'ของใช้ทั่วไป', 89.00, 89.00, '2025-10-05 02:31:32', 14, 1, 'ของฟรีแบ่งปันกันครับ', '100%', 2025, 'แจกฟรี', NULL, 'game', 'facebook game1', 'Msu 1', 16.24428000, 103.24901000, 1, 0),
(95, 'หม้อหุงข้าวไฟฟ้าสีขาวพร้อมฝาแก้ว', 'อุปกรณ์เครื่องครัว', 799.00, 599.00, '2025-10-05 02:39:05', 14, 1, 'ข้างสุกดี เเข็งเเรง', '89%', 2023, 'แลกเปลี่ยน', 'ของใช้ในครัวต่างๆ', 'game', 'facebook game1', 'Msu 3', 16.24428000, 103.24901000, 1, 0),
(96, 'GRAND SPORT Twin Badminton Racket ไม้แบดมินตัน GS แพ็คคู่ พร้อมกระเป๋า', 'อุปกรณ์กีฬา', 250.00, 219.00, '2025-10-05 02:43:49', 14, 1, 'ไม้แบดมินตัน แพ็คคู่', 'มีรอยปลายไม้นิดหน่อย', 2024, 'แลกเปลี่ยน', 'ลูกบอล เบอร์5', 'game', 'facebook game1', 'Msu 2', 16.24428000, 103.24901000, 1, 0),
(97, 'กระเป๋าสะพายข้าง', 'ของใช้ทั่วไป', 290.00, 250.00, '2025-10-05 02:46:59', 14, 1, 'กระเป๋าสะพายข้าง', 'ใช้งานดีปกติ', 2025, 'แลกเปลี่ยน', 'เมาส์เกมมิ่ง', 'game', 'facebook game1', 'Msu 3', 16.24428000, 103.24901000, 1, 0),
(98, 'เตารีดไอน้ำ', 'เครื่องใช้ไฟฟ้า', 850.00, 590.00, '2025-10-05 02:50:42', 14, 1, 'ใช้งานได้ดีตามปกติ', 'ใช้งานได้ดีตามปกติ', 2024, 'แลกเปลี่ยน', 'ไดร์เป่าผม', 'game', 'facebook game1', 'Msu 3', 16.24428000, 103.24901000, 1, 0),
(100, 'เมาส์ Gaming gear', 'ของใช้ทั่วไป', 1300.00, 980.00, '2025-10-05 02:53:21', 11, 1, 'เมาส์ Gaming', 'สภาพดี', 2025, 'แลกเปลี่ยน', 'หูฟังครอบหู Gaming', 'test', 'facebook test1', 'Msu', 16.24428000, 103.24901000, 1, 0),
(101, 'นาฬิกา คาสิโอ Casio STANDARD DIGITAL Vintage', 'ของใช้ทั่วไป', 3300.00, 2290.00, '2025-10-05 02:55:32', 11, 1, 'รุ่น ABL-100WE-1A', 'สภาพเหมือนใหม่', 2024, 'ขายราคาถูก', NULL, 'test', 'facebook test1', 'Msu 3', 16.24428000, 103.24901000, 1, 0),
(103, 'เสื้อนักศึกษาชายแขนยาว เสื้อเชิ้ตชายแขนยาวสีขาว', 'ของใช้ทั่วไป', 230.00, 230.00, '2025-10-05 03:03:28', 14, 1, 'อก42', 'สภาพดี', 2025, 'แลกเปลี่ยน', 'เสื้อยืดสีดำ', 'game', 'facebook game1', 'Msu 2', 16.24428000, 103.24901000, 1, 0),
(104, 'สายเข็มขัด', 'ของใช้ทั่วไป', 60.00, 50.00, '2025-10-05 03:06:34', 14, 1, 'ความยาว 40-48 นิ้ว', 'สภาพดี', 2024, 'ขายราคาถูก', NULL, 'game', 'facebook game1', 'Msu 2', 16.24428000, 103.24901000, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `item_images`
--

CREATE TABLE `item_images` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_images`
--

INSERT INTO `item_images` (`id`, `item_id`, `image`) VALUES
(136, 85, '1759595632_68e14c7054632_b1f6cc.jpg'),
(137, 86, '1759595704_68e14cb838a55_Custom-Bamboo-Drawer-Organizer-Storage-Box-Ziplock-Bag-Container-Sundries-Bins-Plastic-Bag-Dispenser-Holder-for-Wholesale.jpg'),
(138, 87, '1759595768_68e14cf8bab54_h6hxaamplcoy.jpg'),
(139, 88, '1759595841_68e14d41c519d_Had5c6a4b7eb847aaa391eafca1052452h.png'),
(140, 89, '1759595898_68e14d7a22d3f_p-11.jpg'),
(141, 90, '1759595951_68e14daff2761_pngtree-stainless-steel-pan-for-cooking-and-frying-png-image_16006292.png'),
(142, 91, '1759630867_68e1d6136978f_869cd6e771a8399ca1c244bdbd5748f2.jpg_720x720q80.jpg'),
(143, 92, '1759631030_68e1d6b6b876c_91d081e6a76df3bd2b58d1ae50ffec9f.jpg'),
(144, 93, '1759631240_68e1d7883f2ae_images (2).jpeg'),
(145, 94, '1759631492_68e1d8841ea83_3-15.jpg'),
(146, 95, '1759631945_68e1da49b6b40_pngtree-a-white-electric-rice-cooker-with-glass-lid-png-image_19219035.png'),
(147, 96, '1759632229_68e1db6546e0b_post1-768x768.jpg'),
(148, 96, '1759632229_68e1db6547533_post2-768x768.jpg'),
(149, 97, '1759632419_68e1dc238bec0_011.jpg.jpg'),
(150, 98, '1759632642_68e1dd02e954a_14513bafd343a249e86c1ed961578eed.png'),
(152, 100, '1759632801_68e1dda13d3ba_71uNZAdQOoL._AC_SL1500_.jpg'),
(153, 101, '1759632932_68e1de24a2a78_akgdy9.jpg'),
(154, 103, '1759633408_68e1e000cb27e_ajcc0t.png'),
(155, 104, '1759633594_68e1e0ba6886c_200277563_1204580726631483_6699455781723318271_n.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `request_id`, `sender_id`, `message`, `sent_at`) VALUES
(48, 65, 11, 'สวัดดี', '2025-10-04 23:43:18'),
(49, 65, 11, 'uploads/chat/chat_68e14ed27e86c0.85675926.png', '2025-10-04 23:44:02'),
(50, 65, 11, '[MAP]16.252017969090108,103.25326681137086', '2025-10-04 23:44:13'),
(51, 65, 14, 'ตกลง', '2025-10-04 23:44:40'),
(52, 66, 18, 'ขอของหน่อยครับ', '2025-10-05 12:46:02'),
(53, 66, 14, 'ไม่ให้ครับ', '2025-10-05 12:47:17'),
(54, 66, 18, 'เอ้าเห้ยๆ', '2025-10-05 12:47:32'),
(55, 66, 18, 'uploads/chat/chat_68e206801a2bb1.71368524.jpg', '2025-10-05 12:47:44'),
(56, 66, 18, '[MAP]28.205701804421768,-248.9391097423684', '2025-10-05 12:48:05');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `report_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `user_id`, `description`, `report_date`) VALUES
(2, 11, 'test', '2025-09-02 19:41:12');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `exchange_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `reviewee_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `comment` text DEFAULT NULL,
  `date_rated` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `exchange_id`, `reviewer_id`, `item_id`, `reviewee_id`, `rating`, `comment`, `date_rated`, `created_at`) VALUES
(58, 65, 14, 88, 11, 5, 'ดีมากๆ', '2025-10-04 16:46:02', '2025-10-04 16:45:10'),
(59, 65, 11, 87, 14, 5, 'ดีมาก', '2025-10-04 16:45:36', '2025-10-04 16:45:10');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `verification_code` varchar(255) DEFAULT NULL,
  `verification_expires` int(11) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `score` int(11) DEFAULT 0,
  `reset_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `verification_code`, `verification_expires`, `fullname`, `email`, `phone`, `address`, `profile_image`, `created_at`, `role`, `score`, `reset_token`) VALUES
(11, 'test', '$2y$10$4w9OXjRbY/IZOTy7nDZ4d.PVh31oSOokpcRdNQEzz/6cDKoXLOmLi', NULL, NULL, 'time thai', 'test@gmail.com', '0987654321', 'msu2', '687dd14ee9f2d_Screenshot 2025-07-04 120240.png', '2025-07-10 14:12:31', 'user', 5, NULL),
(12, 'admin', '$2y$10$isQFtQLvjgZ7SPMzvu7FwOU0N8TgWd.DqO1vz1a71SYQzgGEx.B.m', NULL, NULL, NULL, 'admin@mail.co', NULL, NULL, NULL, '2025-07-10 14:19:45', 'admin', 0, NULL),
(14, 'game', '$2y$10$uQn919ixB8HPOKhqsyegvOyc7uMkKnmeBDbvwuqUi1/ExC47Gpx.y', NULL, NULL, 'Timcook', 'gamer@gmail.com', '0987654321', 'มหาสารคาม msu', NULL, '2025-07-22 10:38:40', 'user', 5, NULL),
(18, 'beam1412za', '$2y$10$nM7VYKHTZn5Yj5JcA2RgOuZyARULzYk8f3J70EDiLYoDqq0bCbSu6', NULL, NULL, NULL, 'topoasis45@gmail.com', NULL, NULL, NULL, '2025-10-05 05:42:50', 'user', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `exchange_requests`
--
ALTER TABLE `exchange_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_owner_id` (`item_owner_id`),
  ADD KEY `item_request_id` (`item_request_id`),
  ADD KEY `requester_id` (`requester_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_items_owner` (`owner_id`),
  ADD KEY `fk_items_category` (`category`);

--
-- Indexes for table `item_images`
--
ALTER TABLE `item_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review_per_exchange_reviewer` (`exchange_id`,`reviewer_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `reviewee_id` (`reviewee_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `exchange_requests`
--
ALTER TABLE `exchange_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT for table `item_images`
--
ALTER TABLE `item_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `exchange_requests`
--
ALTER TABLE `exchange_requests`
  ADD CONSTRAINT `exchange_requests_ibfk_1` FOREIGN KEY (`item_owner_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `exchange_requests_ibfk_2` FOREIGN KEY (`item_request_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `exchange_requests_ibfk_3` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_category` FOREIGN KEY (`category`) REFERENCES `categories` (`name`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_items_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `item_images`
--
ALTER TABLE `item_images`
  ADD CONSTRAINT `item_images_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `exchange_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`exchange_id`) REFERENCES `exchange_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`reviewee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
