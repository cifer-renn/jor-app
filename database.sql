-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 23, 2025 at 05:55 AM
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
-- Database: `job_order_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `inventory_type` enum('raw_materials','finished_materials') DEFAULT 'raw_materials',
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `name`, `inventory_type`, `description`, `quantity`, `updated_at`) VALUES
(1, 'Steel Plates', 'raw_materials', NULL, 150, '2025-06-22 10:44:57'),
(4, 'Plastic Pipes', 'raw_materials', NULL, 120, '2025-06-22 10:44:57'),
(5, 'Rubber Gaskets', 'raw_materials', NULL, 300, '2025-06-22 10:44:57'),
(6, 'Electronic Components', 'raw_materials', NULL, 500, '2025-06-22 10:44:57'),
(7, 'Welding Rods', 'raw_materials', NULL, 80, '2025-06-22 10:44:57'),
(8, 'Paint Cans', 'raw_materials', NULL, 45, '2025-06-22 10:44:57'),
(9, 'Safety Equipment', 'raw_materials', NULL, 55, '2025-06-23 03:38:23'),
(10, 'Machine Parts', 'raw_materials', NULL, 60, '2025-06-22 10:44:57');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `quantity_change` int(11) NOT NULL,
  `movement_type` enum('in','out') NOT NULL,
  `moved_by_id` int(11) NOT NULL,
  `moved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_movements`
--

INSERT INTO `inventory_movements` (`id`, `inventory_id`, `job_id`, `quantity_change`, `movement_type`, `moved_by_id`, `moved_at`, `notes`) VALUES
(5, 4, 5, 30, 'out', 2, '2025-06-22 10:44:57', NULL),
(6, 1, NULL, 50, 'in', 2, '2025-06-22 10:44:57', NULL),
(8, 6, 2, 25, 'out', 2, '2025-06-22 10:44:57', NULL),
(9, 8, 3, 10, 'out', 2, '2025-06-22 10:44:57', NULL),
(10, 9, 4, 5, 'out', 2, '2025-06-22 10:44:57', NULL),
(16, 9, NULL, 30, 'in', 2, '2025-06-23 03:38:23', '0');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','normal','important') NOT NULL DEFAULT 'normal',
  `status` enum('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `verification_status` enum('pending_verification','approved','rejected') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `supervisor_id` int(11) NOT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `title`, `description`, `priority`, `status`, `verification_status`, `notes`, `supervisor_id`, `operator_id`, `created_at`, `updated_at`) VALUES
(2, 'Quality Control Check', 'Conduct quality control inspection on batch #2024-001. Check dimensions, weight, and surface finish.', 'normal', 'pending', NULL, NULL, 1, 4, '2025-06-22 10:44:57', '2025-06-22 10:44:57'),
(3, 'Equipment Calibration', 'Calibrate measuring instruments and sensors. Update calibration certificates.', 'normal', 'completed', NULL, NULL, 1, 3, '2025-06-22 10:44:57', '2025-06-22 10:44:57'),
(4, 'Safety Training Session', 'Conduct monthly safety training for new operators. Cover emergency procedures and PPE usage.', 'low', 'pending', NULL, NULL, 1, NULL, '2025-06-22 10:44:57', '2025-06-22 10:44:57'),
(5, 'Production Line Setup', 'Set up production line for new product model. Install new molds and configure settings.', 'important', 'in_progress', NULL, NULL, 1, 4, '2025-06-22 10:44:57', '2025-06-22 10:44:57'),
(6, 'Inventory Audit', 'Perform physical inventory count and reconcile with system records.', 'normal', 'pending', NULL, NULL, 1, NULL, '2025-06-22 10:44:57', '2025-06-22 10:44:57'),
(7, 'Equipment Repair', 'Repair hydraulic pump on machine #3. Replace seals and test pressure.', 'important', 'pending', NULL, NULL, 1, 3, '2025-06-22 10:44:57', '2025-06-22 10:44:57'),
(8, 'Cleanup Operation', 'Clean production area and organize tools. Dispose of waste materials properly.', 'low', 'completed', NULL, NULL, 1, 4, '2025-06-22 10:44:57', '2025-06-22 10:44:57'),
(9, 'Make am Phone Part', '', 'normal', 'pending', NULL, NULL, 1, 3, '2025-06-22 12:34:44', '2025-06-22 12:35:36'),
(10, 'tessss', '', 'low', 'pending', NULL, NULL, 1, NULL, '2025-06-23 03:39:44', '2025-06-23 03:39:44');

-- --------------------------------------------------------

--
-- Table structure for table `job_requirements`
--

CREATE TABLE `job_requirements` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity_required` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_requirements`
--

INSERT INTO `job_requirements` (`id`, `job_id`, `inventory_id`, `quantity_required`) VALUES
(2, 9, 1, 25);

-- --------------------------------------------------------

--
-- Table structure for table `job_verifications`
--

CREATE TABLE `job_verifications` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `verified_by_id` int(11) NOT NULL,
  `verification_status` enum('approved','rejected') NOT NULL,
  `verification_notes` text DEFAULT NULL,
  `verified_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material_requests`
--

CREATE TABLE `material_requests` (
  `id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `requested_by_id` int(11) NOT NULL,
  `request_notes` text DEFAULT NULL,
  `status` enum('pending','acknowledged','resolved') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `handled_by_id` int(11) DEFAULT NULL,
  `handled_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_requests`
--

INSERT INTO `material_requests` (`id`, `job_id`, `requested_by_id`, `request_notes`, `status`, `created_at`, `handled_by_id`, `handled_at`) VALUES
(1, 9, 3, 'aaaaa', 'pending', '2025-06-23 03:41:56', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('supervisor','warehouse_manager','machine_operator') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'supervisor', '$2y$10$/NxUZt6ISiWK5kLY97eZoOtwRzFFosXm7.r4zMgeze4eFgRi25S8O', 'supervisor', '2025-06-22 10:23:02'),
(2, 'warehouse', '$2y$10$/NxUZt6ISiWK5kLY97eZoOtwRzFFosXm7.r4zMgeze4eFgRi25S8O', 'warehouse_manager', '2025-06-22 10:23:02'),
(3, 'operator1', '$2y$10$/NxUZt6ISiWK5kLY97eZoOtwRzFFosXm7.r4zMgeze4eFgRi25S8O', 'machine_operator', '2025-06-22 10:23:02'),
(4, 'operator2', '$2y$10$/NxUZt6ISiWK5kLY97eZoOtwRzFFosXm7.r4zMgeze4eFgRi25S8O', 'machine_operator', '2025-06-22 10:23:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_inventory_type` (`inventory_type`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `moved_by_id` (`moved_by_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `operator_id` (`operator_id`),
  ADD KEY `idx_job_verification_status` (`verification_status`);

--
-- Indexes for table `job_requirements`
--
ALTER TABLE `job_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `inventory_id` (`inventory_id`);

--
-- Indexes for table `job_verifications`
--
ALTER TABLE `job_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `verified_by_id` (`verified_by_id`);

--
-- Indexes for table `material_requests`
--
ALTER TABLE `material_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `requested_by_id` (`requested_by_id`),
  ADD KEY `handled_by_id` (`handled_by_id`);

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
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `job_requirements`
--
ALTER TABLE `job_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_verifications`
--
ALTER TABLE `job_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `material_requests`
--
ALTER TABLE `material_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `inventory_movements_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `inventory_movements_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_movements_ibfk_3` FOREIGN KEY (`moved_by_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `jobs_ibfk_2` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `job_requirements`
--
ALTER TABLE `job_requirements`
  ADD CONSTRAINT `job_requirements_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_requirements_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_verifications`
--
ALTER TABLE `job_verifications`
  ADD CONSTRAINT `job_verifications_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_verifications_ibfk_2` FOREIGN KEY (`verified_by_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `material_requests`
--
ALTER TABLE `material_requests`
  ADD CONSTRAINT `material_requests_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `material_requests_ibfk_2` FOREIGN KEY (`requested_by_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `material_requests_ibfk_3` FOREIGN KEY (`handled_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
