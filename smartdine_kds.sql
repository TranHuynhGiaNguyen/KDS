-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: smartdine_kds
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `smartdine_kds`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `smartdine_kds` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `smartdine_kds`;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `icon` varchar(20) DEFAULT '✦',
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Món chính','main','♨','Các món ăn chính đậm đà, cung cấp đầy đủ dinh dưỡng',1,'active','2026-11-05 14:10:13','2026-11-05 14:10:13'),(2,'Món phụ / Khai vị','side','❋','Món khai vị nhẹ nhàng, kích thích vị giác đầu bữa',2,'active','2026-11-05 14:10:13','2026-11-05 14:10:13'),(3,'Đồ uống','drink','◉','Trà trái cây tươi mát, cà phê và nước giải khát',3,'active','2026-11-05 14:10:13','2026-11-05 14:10:13'),(4,'Tráng miệng','dessert','✧','Sữa chua, kem tươi và các món bánh tráng miệng thanh ngọt',4,'active','2026-11-05 14:10:13','2026-11-05 14:10:13');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dishes`
--

DROP TABLE IF EXISTS `dishes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dishes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `price` decimal(12,0) NOT NULL DEFAULT 0,
  `image` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `tag` varchar(50) DEFAULT NULL,
  `station` enum('hot','soup','cold','bar') NOT NULL DEFAULT 'hot',
  `status` enum('active','out_of_stock','hidden') NOT NULL DEFAULT 'active',
  `rating` decimal(2,1) NOT NULL DEFAULT 5.0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_station` (`station`),
  CONSTRAINT `fk_dishes_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dishes`
--

LOCK TABLES `dishes` WRITE;
/*!40000 ALTER TABLE `dishes` DISABLE KEYS */;
INSERT INTO `dishes` VALUES (1,1,'Cơm gà Hải Nam',75000,'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=700&q=80','Gà ta mềm ngọt, cơm niêu thơm nấu cùng nước cốt luộc gà và sốt gừng đặc trưng.','Bán chạy nhất','hot','active',4.9,'2026-11-05 14:10:13','2026-11-05 14:10:13'),(2,1,'Mì xào bò',85000,'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=700&q=80','Mì trứng dai xào cùng thăn bò mềm, nấm hương tươi và rau cải giòn thơm.','Gợi ý bếp trưởng','hot','active',4.8,'2026-11-05 14:10:13','2026-11-05 14:10:13'),(3,2,'Salad trộn đặc biệt',55000,'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=700&q=80','Rau xanh thủy canh tươi giòn, cà chua bi organic với sốt mè rang béo ngậy thanh mát.','Healthy','cold','active',4.7,'2026-11-05 14:10:13','2026-11-05 14:10:13'),(4,3,'Trà đào cam sả',35000,'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=700&q=80','Hương trà ướp lạnh thanh mát, đào miếng giòn ngọt hòa quyện tinh dầu cam vàng và sả tươi.','Best Seller','bar','active',4.9,'2026-11-05 14:10:13','2026-11-05 14:10:13'),(5,1,'Bò lúc lắc hạt tiêu',120000,'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=700&q=80','Thịt bò Úc áp chảo xì xèo xém cạnh, sốt tiêu đen đậm đà ăn kèm khoai tây chiên giòn.',NULL,'hot','active',4.8,'2026-11-05 14:10:13','2026-11-05 14:10:13'),(6,4,'Sữa chua trái cây',40000,'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=700&q=80','Sữa chua Hy Lạp sánh mịn lên men tự nhiên, kèm dâu tây tươi và sốt việt quất ngọt dịu.',NULL,'cold','active',4.7,'2026-11-05 14:10:13','2026-11-05 14:10:13');
/*!40000 ALTER TABLE `dishes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `role` enum('admin','staff','kitchen') NOT NULL DEFAULT 'staff',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$AM/45asFotKqNnz0PXKWXuxXlU6WiLEVoB48A.J3GAHJhZaTpa.te','Quản trị viên Hệ thống','admin','0901234567','admin@smartdine.vn','active','2026-11-05 14:18:40','2026-11-04 11:43:58','2026-11-05 14:18:40'),(2,'staff','$2y$10$ttU56bD8obk37xwsA0h/xurGl0HMsXVMchWGH4Zg1D9MnTSaZtrrC','Nguyễn Phục Vụ','staff','0912345678','staff@smartdine.vn','active','2026-11-04 12:05:01','2026-11-04 11:43:58','2026-11-04 12:05:01'),(3,'kitchen','$2y$10$5484iX1IsNPaFUPXWT.AuO/.zg/IDtoSJON22RL8IogXi65spUL/y','Trần Bếp Trưởng','kitchen','0923456789','chef@smartdine.vn','active','2026-11-04 12:05:16','2026-11-04 11:43:58','2026-11-04 12:05:16'),(4,'1','$2y$10$/W5WawHmXYdBoT18oek3YOZBP/ZkfY5CRUh.eml8x3YWPsv2ZQyYq','Nhân viên Demo #1','staff','0988888888','demo1@smartdine.vn','active','2026-11-04 11:51:17','2026-11-04 11:43:58','2026-11-04 11:51:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'smartdine_kds'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-05 14:18:44
