-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.4.32-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.14.0.7165
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for psu sport equipment
CREATE DATABASE IF NOT EXISTS `psu sport equipment` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `psu sport equipment`;

-- Dumping structure for table psu sport equipment.equipment_fines
CREATE TABLE IF NOT EXISTS `equipment_fines` (
  `fine_id` int(11) NOT NULL AUTO_INCREMENT,
  `trans_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `slip_img` varchar(255) DEFAULT NULL,
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `slipok_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `paid_time` datetime DEFAULT NULL,
  PRIMARY KEY (`fine_id`),
  KEY `trans_id` (`trans_id`),
  CONSTRAINT `trans_id` FOREIGN KEY (`trans_id`) REFERENCES `transactions` (`trans_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu sport equipment.equipment_fines: ~3 rows (approximately)
INSERT INTO `equipment_fines` (`fine_id`, `trans_id`, `price`, `slip_img`, `payment_status`, `slipok_status`, `paid_time`) VALUES
	(1, 15, 20.00, NULL, 'paid', 'verified', NULL),
	(2, 18, 60.00, NULL, 'paid', 'verified', NULL),
	(3, 19, 60.00, NULL, 'paid', 'verified', NULL);

-- Dumping structure for table psu sport equipment.image_equipment
CREATE TABLE IF NOT EXISTS `image_equipment` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) DEFAULT NULL,
  `eq_id` int(11) NOT NULL,
  PRIMARY KEY (`image_id`),
  KEY `eq_id2` (`eq_id`),
  CONSTRAINT `eq_id2` FOREIGN KEY (`eq_id`) REFERENCES `sport_equipment` (`eq_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu sport equipment.image_equipment: ~24 rows (approximately)
INSERT INTO `image_equipment` (`image_id`, `image_path`, `eq_id`) VALUES
	(1, 'uploads/equipments/1787579511_0_d15f82d7-5dd9-444c-a56b-5da80d5f2eab.jfif', 1),
	(2, 'uploads/equipments/1787579511_1_646224b6-8a14-45b6-b6de-365ad7b40528.jfif', 1),
	(3, 'uploads/equipments/1787579511_2_84a6bd29-4869-4385-a8a8-860c7869590e.jfif', 1),
	(4, 'uploads/equipments/1787579671_0_c8fa1e9c-a872-4b28-b4d6-1753aef07acd.jfif', 2),
	(5, 'uploads/equipments/1787579671_1_08f8becc-c806-4ebc-af36-78b77efa49cc.jfif', 2),
	(6, 'uploads/equipments/1787579671_2_97191c55-33b5-4e0d-9d19-9a9ee98a00d6.jfif', 2),
	(7, 'uploads/equipments/1787580005_0_14db75ad-1529-402d-b12b-14913bf05366.jfif', 3),
	(8, 'uploads/equipments/1787580005_1_11ce1d8d-37d4-4eb9-8351-18380392a571.jfif', 3),
	(9, 'uploads/equipments/1787580005_2_264da504-c414-4905-91da-39b38ffb3b8e.jfif', 3),
	(10, 'uploads/equipments/1787591689_0_231f7989-a832-4690-81a3-8e56208cd183.jfif', 4),
	(11, 'uploads/equipments/1787591689_1_768c0d3b-52d7-4abc-bd29-9b10b6e638a7.jfif', 4),
	(12, 'uploads/equipments/1787591689_2_e4d1f690-5fce-4b43-b0cf-6226d56d6f6e.jfif', 4),
	(13, 'uploads/equipments/1787591753_0_0ef38203-9a46-447e-9e4b-b7d58a46dfe1.jfif', 5),
	(14, 'uploads/equipments/1787591753_1_6b263d4b-5055-4c26-ab9a-66c73d8d9c08.jfif', 5),
	(15, 'uploads/equipments/1787591753_2_b8e72150-e03d-472d-b8e2-c5a91e274455.jfif', 5),
	(16, 'uploads/equipments/1787591788_0_05764bd7-0dfc-4e97-9a14-d458ec2333c9.jfif', 6),
	(17, 'uploads/equipments/1787591788_1_865d0d19-a327-4720-b495-2b5cf30339c0.jfif', 6),
	(18, 'uploads/equipments/1787591788_2_24ed2dbb-fdbc-46f4-862c-85344cda7a5d.jfif', 6),
	(19, 'uploads/equipments/1787592150_0_c46c5d30-5a4a-4c30-8413-80c5d86b6fe9.jfif', 7),
	(20, 'uploads/equipments/1787592150_1_9faf498b-31c6-4049-869e-f129e4219a74.jfif', 7),
	(21, 'uploads/equipments/1787592150_2_72fdb12d-1a4e-46c5-bba9-c82d60800ac3.jfif', 7),
	(25, 'uploads/equipments/1787600409_0_0d0ee89a-837b-42c4-97a3-3b1862a03fad.jfif', 8),
	(26, 'uploads/equipments/1787600409_1_e295f741-077a-49eb-b692-4c3fbea70304.jfif', 8),
	(27, 'uploads/equipments/1787600409_2_af1946bc-8934-46c4-88e4-1f16d8ec9c67.jfif', 8);

-- Dumping structure for table psu sport equipment.sport_categories
CREATE TABLE IF NOT EXISTS `sport_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `create_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu sport equipment.sport_categories: ~3 rows (approximately)
INSERT INTO `sport_categories` (`category_id`, `category_name`, `create_at`) VALUES
	(1, 'ลูกวอลเลย์ Molten V5M5000', '2026-08-24 13:43:03'),
	(2, 'ลูกฟุตบอลหนังเย็บ Molten F5A4800', '2026-08-24 13:58:59'),
	(3, 'ลูกฟุตซอลหนังเย็บ Molten F9A3555', '2026-08-24 17:16:59');

-- Dumping structure for table psu sport equipment.sport_equipment
CREATE TABLE IF NOT EXISTS `sport_equipment` (
  `eq_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `eq_code` varchar(255) DEFAULT '',
  `eq_name` varchar(255) DEFAULT '',
  `status` enum('avaliable','borrowed','damaged','maintenance') NOT NULL DEFAULT 'avaliable',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`eq_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `category_id` FOREIGN KEY (`category_id`) REFERENCES `sport_categories` (`category_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu sport equipment.sport_equipment: ~8 rows (approximately)
INSERT INTO `sport_equipment` (`eq_id`, `category_id`, `eq_code`, `eq_name`, `status`, `created_at`) VALUES
	(1, 1, 'PSU-01-001', 'ลูกวอลเลย์ Molten V5M5000', 'avaliable', '2026-08-24 13:51:51'),
	(2, 1, 'PSU-01-002', 'ลูกวอลเลย์ Molten V5M5000', 'borrowed', '2026-08-24 13:54:31'),
	(3, 2, 'PSU-02-001', 'ลูกฟุตบอลหนังเย็บ Molten F5A4800', 'avaliable', '2026-08-24 14:00:05'),
	(4, 1, 'PSU-01-003', 'ลูกวอลเลย์ Molten V5M5000', 'damaged', '2026-08-24 17:14:49'),
	(5, 2, 'PSU-02-002', 'ลูกฟุตบอลหนังเย็บ Molten F5A4800', 'borrowed', '2026-08-24 17:15:53'),
	(6, 2, 'PSU-02-003', 'ลูกฟุตบอลหนังเย็บ Molten F5A4800', 'borrowed', '2026-08-24 17:16:28'),
	(7, 3, 'PSU-03-001', 'ลูกฟุตซอลหนังเย็บ Molten F9A3555', 'avaliable', '2026-08-24 17:22:30'),
	(8, 3, 'PSU-03-002', 'ลูกฟุตซอลหนังเย็บ Molten F9A3555', 'borrowed', '2026-08-24 17:23:57');

-- Dumping structure for table psu sport equipment.transactions
CREATE TABLE IF NOT EXISTS `transactions` (
  `trans_id` int(11) NOT NULL AUTO_INCREMENT,
  `eq_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `borrow_time` datetime NOT NULL,
  `due_time` datetime NOT NULL,
  `return_time` datetime DEFAULT NULL,
  `borrow_image` varchar(255) NOT NULL,
  `return_image` varchar(255) DEFAULT NULL,
  `trans_status` enum('borrowed','returned','overdue') NOT NULL DEFAULT 'borrowed',
  `analyze_damaged_status` enum('normal','damaged','pending') NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`trans_id`),
  KEY `eq_id` (`eq_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `eq_id` FOREIGN KEY (`eq_id`) REFERENCES `sport_equipment` (`eq_id`) ON UPDATE CASCADE,
  CONSTRAINT `user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu sport equipment.transactions: ~21 rows (approximately)
INSERT INTO `transactions` (`trans_id`, `eq_id`, `user_id`, `borrow_time`, `due_time`, `return_time`, `borrow_image`, `return_image`, `trans_status`, `analyze_damaged_status`) VALUES
	(1, 1, 1, '2026-08-25 00:03:49', '2026-08-26 23:59:59', '2026-08-25 00:04:01', 'borrow_1787591029_772.jfif', 'return_1787591041_465.jfif', 'returned', 'normal'),
	(2, 3, 1, '2026-08-25 00:40:40', '2026-08-28 23:59:59', '2026-08-25 00:40:52', 'borrow_1787593240_733.jfif', 'return_1787593252_870.jfif', 'returned', 'normal'),
	(3, 8, 1, '2026-08-25 00:41:06', '2026-08-28 23:59:59', '2026-08-25 00:41:12', 'borrow_1787593266_478.jfif', 'return_1787593272_767.jfif', 'returned', 'normal'),
	(4, 2, 1, '2026-08-25 00:42:01', '2026-08-28 23:59:59', '2026-08-25 00:42:07', 'borrow_1787593321_273.jfif', 'return_1787593327_350.jfif', 'returned', 'normal'),
	(5, 1, 3, '2026-08-25 00:42:48', '2026-08-28 23:59:59', '2026-08-25 00:42:57', 'borrow_1787593368_616.jfif', 'return_1787593377_166.jfif', 'returned', 'normal'),
	(6, 4, 3, '2026-08-25 00:43:07', '2026-08-28 23:59:59', '2026-08-25 00:43:16', 'borrow_1787593387_113.jfif', 'return_1787593396_363.jfif', 'returned', 'normal'),
	(7, 1, 3, '2026-08-25 00:45:23', '2026-08-28 23:59:59', '2026-08-25 00:45:31', 'borrow_1787593523_705.jfif', 'return_1787593531_326.jfif', 'returned', 'normal'),
	(8, 4, 4, '2026-08-25 01:00:30', '2026-08-28 23:59:59', '2026-08-25 01:00:37', 'borrow_1787594430_256.jfif', 'return_1787594437_399.jfif', 'returned', 'normal'),
	(9, 7, 4, '2026-08-25 01:00:56', '2026-08-28 23:59:59', '2026-08-25 01:01:03', 'borrow_1787594456_273.jfif', 'return_1787594463_927.jfif', 'returned', 'normal'),
	(10, 6, 5, '2026-08-25 01:02:36', '2026-08-28 23:59:59', '2026-08-25 01:02:42', 'borrow_1787594556_280.jfif', 'return_1787594562_831.jfif', 'returned', 'normal'),
	(11, 3, 5, '2026-08-25 01:02:56', '2026-08-28 23:59:59', '2026-08-25 01:03:01', 'borrow_1787594576_132.jfif', 'return_1787594581_191.jfif', 'returned', 'normal'),
	(12, 8, 4, '2026-08-25 01:03:21', '2026-08-28 23:59:59', '2026-08-25 01:03:39', 'borrow_1787594601_453.jfif', 'return_1787594619_242.jfif', 'returned', 'normal'),
	(13, 7, 4, '2026-08-25 01:03:29', '2026-08-28 23:59:59', '2026-08-25 01:03:34', 'borrow_1787594609_710.jfif', 'return_1787594614_936.jfif', 'returned', 'normal'),
	(14, 2, 4, '2026-08-25 01:12:52', '2026-08-28 23:59:59', NULL, 'borrow_1787595172_600.jfif', NULL, 'borrowed', 'normal'),
	(15, 8, 1, '2026-08-23 01:14:02', '2026-08-24 23:59:59', '2026-08-25 01:16:34', 'borrow_1787595242_580.jfif', 'return_1787595394_300.jfif', 'returned', 'normal'),
	(16, 6, 3, '2026-08-21 01:15:33', '2026-08-23 23:59:59', NULL, 'borrow_1787595333_838.jfif', NULL, 'overdue', 'normal'),
	(17, 5, 1, '2026-08-25 01:16:11', '2026-08-28 23:59:59', '2026-08-25 01:42:44', 'borrow_1787595371_970.jfif', 'return_1787596964_568.jfif', 'returned', 'normal'),
	(18, 5, 1, '2026-08-21 01:20:58', '2026-08-22 23:59:59', '2026-08-25 01:21:06', 'borrow_1787595658_248.jfif', 'return_1787595666_861.jfif', 'returned', 'normal'),
	(19, 8, 1, '2026-08-21 01:26:16', '2026-08-22 23:59:59', '2026-08-25 01:34:31', 'borrow_1787595976_325.jfif', 'return_1787596471_680.jfif', 'returned', 'normal'),
	(20, 8, 1, '2026-08-21 01:44:07', '2026-08-23 23:59:59', NULL, 'borrow_1787597047_119.jfif', NULL, 'overdue', 'normal'),
	(21, 5, 1, '2026-08-25 01:44:29', '2026-08-28 23:59:59', NULL, 'borrow_1787597069_225.jfif', NULL, 'borrowed', 'normal');

-- Dumping structure for table psu sport equipment.user
CREATE TABLE IF NOT EXISTS `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `line_user_id` varchar(255) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `status` enum('active','inactive','graduated') DEFAULT 'active',
  `enrollment_year` int(4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `line_user_id` (`line_user_id`),
  UNIQUE KEY `psu_passport_id` (`student_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table psu sport equipment.user: ~6 rows (approximately)
INSERT INTO `user` (`user_id`, `line_user_id`, `student_id`, `full_name`, `email`, `role`, `status`, `enrollment_year`, `created_at`) VALUES
	(1, NULL, '6650110025', 'นาย ศุภณัฐ วรฤทธิ์', '6650110025@email.psu.ac.th', 'user', 'active', 2569, '2026-08-21 07:44:18'),
	(2, NULL, '6650110099', 'นาย สมชาย ใจดี', '6650110099@email.psu.ac.th', 'admin', 'active', 2565, '2026-08-24 14:37:45'),
	(3, NULL, '6650110001', 'นาย กิตติพัฒน์ บุญรอด', '6650110001@email.psu.ac.th', 'user', 'active', 2568, '2026-08-24 16:08:22'),
	(4, NULL, '6650110008', 'นาย ธนดล ยิ้นห้อง', '6650110008@email.psu.ac.th', 'user', 'active', 2567, '2026-08-24 16:09:38'),
	(5, NULL, '6650110098', 'นาย สมส่วน สมมาตร', '6650110098@email.psu.ac.th', 'user', 'active', 2566, '2026-08-24 16:10:36'),
	(6, NULL, '6650110097', 'นาง สมศรี ใจสุข', '6650110097@email.psu.ac.th', 'user', 'active', 2566, '2026-08-24 16:11:28');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
