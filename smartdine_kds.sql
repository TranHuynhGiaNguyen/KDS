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
-- Table structure for table `buffet_packages`
--

DROP TABLE IF EXISTS `buffet_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buffet_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `price_per_person` decimal(12,0) NOT NULL DEFAULT 0,
  `image` varchar(500) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_buffet_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buffet_packages`
--

LOCK TABLES `buffet_packages` WRITE;
/*!40000 ALTER TABLE `buffet_packages` DISABLE KEYS */;
INSERT INTO `buffet_packages` VALUES (1,'BUFFET XÈO XÈO CLASSIC',529000,'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=900&q=80','Thỏa sức thưởng thức các món thịt nướng Hàn Quốc, hải sản và lẩu đặc trưng.',1,'active','2026-11-27 01:21:48','2026-11-27 01:21:48');
/*!40000 ALTER TABLE `buffet_packages` ENABLE KEYS */;
UNLOCK TABLES;

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
  `menu_type` enum('alacarte','buffet','both') NOT NULL DEFAULT 'alacarte',
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Món chính','main','alacarte','♨','Các món ăn chính đậm đà, cung cấp đầy đủ dinh dưỡng',1,'active','2026-11-05 14:10:13','2026-11-05 14:10:13'),(2,'Món phụ / Khai vị','side','alacarte','❋','Món khai vị nhẹ nhàng, kích thích vị giác đầu bữa',2,'active','2026-11-05 14:10:13','2026-11-05 14:10:13'),(3,'Đồ uống','drink','alacarte','◉','Trà trái cây tươi mát, cà phê và nước giải khát',3,'active','2026-11-05 14:10:13','2026-11-05 14:10:13'),(4,'Tráng miệng','dessert','alacarte','✧','Sữa chua, kem tươi và các món bánh tráng miệng thanh ngọt',4,'active','2026-11-05 14:10:13','2026-11-05 14:10:13'),(6,'SPECIAL OFFER - MÓN TẶNG','special-offer-buffet','buffet','★','',10,'active','2026-11-27 01:21:48','2026-11-27 01:21:48'),(7,'THỊT HEO NƯỚNG HÀN','thit-heo-nuong-buffet','buffet','♨','',11,'active','2026-11-27 01:21:48','2026-11-27 01:21:48');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dining_tables`
--

DROP TABLE IF EXISTS `dining_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dining_tables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `area_id` int(11) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 4,
  `pos_x` float NOT NULL DEFAULT 15,
  `pos_y` float NOT NULL DEFAULT 20,
  `shape` enum('square','round','rectangle') NOT NULL DEFAULT 'square',
  `width` int(11) NOT NULL DEFAULT 85,
  `height` int(11) NOT NULL DEFAULT 85,
  `status` enum('active','maintenance','hidden') NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_table_area` (`area_id`),
  KEY `idx_table_code` (`code`),
  KEY `idx_table_status` (`status`),
  CONSTRAINT `fk_dining_tables_area` FOREIGN KEY (`area_id`) REFERENCES `restaurant_areas` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dining_tables`
--

LOCK TABLES `dining_tables` WRITE;
/*!40000 ALTER TABLE `dining_tables` DISABLE KEYS */;
INSERT INTO `dining_tables` VALUES (1,'B01','Bàn B01',1,4,18,22,'square',85,85,'active',1,'2026-11-08 23:09:01'),(2,'B02','Bàn B02',1,4,48,22,'square',85,85,'active',2,'2026-11-08 23:09:01'),(4,'B04','Bàn B04',1,2,18,64,'round',80,80,'active',4,'2026-11-08 23:09:01'),(5,'B05','Bàn B05',2,6,25,28,'rectangle',115,85,'active',5,'2026-11-08 23:09:01'),(6,'B06','Bàn B06',2,4,68,28,'round',90,90,'active',6,'2026-11-08 23:09:01'),(7,'B07','Bàn B07',2,4,25,66,'square',85,85,'active',7,'2026-11-08 23:09:01'),(8,'B08','Bàn B08',2,8,68,66,'rectangle',135,85,'active',8,'2026-11-08 23:09:01'),(9,'B09','Bàn B09',3,10,28,46,'rectangle',150,95,'active',9,'2026-11-08 23:09:01'),(10,'B10','Bàn B10',3,12,70,46,'rectangle',170,100,'active',10,'2026-11-08 23:09:01'),(11,'B11','Bàn B11',1,4,48,64,'square',85,85,'active',11,'2026-11-08 23:09:01'),(12,'B12','Bàn B12',1,6,78,64,'rectangle',115,85,'active',12,'2026-11-08 23:09:01'),(34,'TEST-99','Bàn Test VIP 99 Sửa',25,10,55.5,62,'rectangle',140,95,'maintenance',5,'2026-11-25 10:24:18');
/*!40000 ALTER TABLE `dining_tables` ENABLE KEYS */;
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
  `menu_type` enum('alacarte','buffet') NOT NULL DEFAULT 'alacarte',
  `buffet_package_id` int(11) DEFAULT NULL,
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
  KEY `idx_dish_menu_type` (`menu_type`),
  KEY `idx_dish_buffet_pkg` (`buffet_package_id`),
  CONSTRAINT `fk_dishes_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dishes`
--

LOCK TABLES `dishes` WRITE;
/*!40000 ALTER TABLE `dishes` DISABLE KEYS */;
INSERT INTO `dishes` VALUES (15,4,'alacarte',NULL,'Sữa chua trái cây',40000,'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=700&q=80','Sữa chua Hy Lạp sánh mịn lên men tự nhiên, kèm dâu tây tươi và sốt việt quất ngọt dịu.',NULL,'cold','active',4.7,'2026-11-25 09:44:39','2026-11-25 09:44:39'),(16,1,'alacarte',NULL,'Bò áp chảo',100000,'','Áp với thảo mộc và tiêu cho ra hương liệu cay nồng',NULL,'hot','active',5.0,'2026-11-25 10:06:03','2026-11-27 01:48:30'),(17,2,'alacarte',NULL,'Salad hoa quả',50000,'','Trộn với sốt colelaw cho ra vị chua nhẹ và ngọt',NULL,'cold','active',5.0,'2026-11-25 10:06:52','2026-11-25 10:06:52'),(18,6,'buffet',1,'Đĩa mời size L',0,'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=700&q=80','Đĩa thịt tổng hợp đặc biệt tặng kèm theo gói Buffet.','Món tặng','hot','active',4.9,'2026-11-27 01:21:48','2026-11-27 01:21:48'),(19,6,'buffet',1,'Đĩa mời size M',0,'https://images.unsplash.com/photo-1558030006-450675393462?auto=format&fit=crop&w=700&q=80','Đĩa thịt bò & ba chỉ tuyển chọn dành cho nhóm 2-3 khách.','Món tặng','hot','active',4.8,'2026-11-27 01:21:48','2026-11-27 01:21:48'),(20,7,'buffet',1,'Ba chỉ heo cuốn phomai',0,'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?auto=format&fit=crop&w=700&q=80','Ba chỉ heo nướng thơm lừng chấm cùng phô mai béo ngậy.','Buffet','hot','active',4.9,'2026-11-27 01:21:48','2026-11-27 01:21:48'),(21,7,'buffet',1,'Ba chỉ heo bào cuộn xốt Gochujang',0,'https://images.unsplash.com/photo-1600891964092-4316c288032e?auto=format&fit=crop&w=700&q=80','Thịt ba chỉ thái mỏng thấm đẫm sốt ớt Hàn Quốc đậm vị.','Buffet','hot','active',4.8,'2026-11-27 01:21:48','2026-11-27 01:21:48');
/*!40000 ALTER TABLE `dishes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `unit_price` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_items_invoice` (`invoice_id`),
  CONSTRAINT `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT INTO `invoice_items` VALUES (1,1,'Cơm gà Hải Nam',75000,1,75000),(2,1,'Mì xào bò',85000,1,85000),(3,1,'Salad trộn đặc biệt',55000,1,55000),(4,1,'Trà đào cam sả',35000,2,70000),(5,1,'Chè khúc bạch',45000,1,45000),(6,1,'Lẩu nấm gà tiềm',320000,1,320000),(7,1,'Bò nướng tảng sốt tiêu',280000,2,560000),(8,1,'Cơm gà Hải Nam',65000,1,65000),(9,1,'Salad trộn đặc biệt',55000,1,55000),(10,1,'Mì xào bò',85000,2,170000),(11,1,'Lẩu nấm gà tiềm',320000,1,320000),(12,1,'Trà đào cam sả',35000,3,105000),(13,1,'Bún chả Hà Nội đặc biệt',75000,1,75000),(14,1,'Bò lúc lắc khoai tây',165000,1,165000),(15,1,'Cơm rang dưa bò',70000,2,140000),(16,2,'Lẩu nấm gà tiềm',320000,1,320000),(17,2,'Khoai tây chiên giòn',45000,1,45000),(18,2,'Nước ngọt các loại',25000,3,75000),(19,2,'Khăn lạnh',5000,3,15000),(20,2,'Bò lúc lắc',150000,1,150000),(21,3,'Bún chả Hà Nội đặc biệt',75000,2,150000),(22,3,'Trà tắc hạt chia',20000,2,40000),(23,4,'Combo Bò Wagyu thượng hạng',890000,1,890000),(24,4,'Vang đỏ Cabernet Sauvignon',350000,1,350000),(25,4,'Bánh tiramisu tráng miệng',35000,6,210000);
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `table_code` varchar(10) NOT NULL,
  `subtotal` int(11) NOT NULL,
  `discount_type` varchar(10) NOT NULL DEFAULT 'none',
  `discount_value` int(11) NOT NULL DEFAULT 0,
  `discount_amount` int(11) NOT NULL DEFAULT 0,
  `voucher_code` varchar(50) DEFAULT NULL,
  `service_fee` int(11) NOT NULL,
  `vat` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `service_rate` decimal(5,4) NOT NULL,
  `vat_rate` decimal(5,4) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  CONSTRAINT `fk_invoices_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,1,'B03',2305000,'none',0,0,NULL,115250,193620,2613870,0.0500,0.0800,'2026-11-23 19:01:51'),(2,2,'B05',605000,'none',0,0,NULL,30250,50820,686070,0.0500,0.0800,'2026-11-23 19:01:59'),(3,4,'B11',190000,'none',0,0,NULL,9500,15960,215460,0.0500,0.0800,'2026-11-23 19:02:07'),(4,3,'B10',1450000,'none',0,0,NULL,72500,121800,1644300,0.0500,0.0800,'2026-11-23 19:02:16');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `unit_price` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `note` varchar(255) DEFAULT NULL,
  `status` enum('unsent','pending','cooking','done','served','cancelled') NOT NULL DEFAULT 'unsent',
  `sent_by` int(11) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `cooking_at` datetime DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_items_order` (`order_id`),
  CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1640 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,'Cơm gà Hải Nam',75000,1,NULL,'served',2,'2026-11-08 22:37:49','2026-11-08 22:39:49','2026-11-08 22:49:49',NULL,'2026-11-08 22:57:49'),(2,1,'Mì xào bò',85000,1,NULL,'served',2,'2026-11-08 22:37:49','2026-11-08 22:39:49','2026-11-08 22:49:49',NULL,'2026-11-08 22:57:49'),(3,1,'Salad trộn đặc biệt',55000,1,NULL,'served',2,'2026-11-08 22:37:49','2026-11-08 22:39:49','2026-11-08 22:49:49',NULL,'2026-11-08 22:57:49'),(4,1,'Trà đào cam sả',35000,2,NULL,'served',2,'2026-11-08 22:37:49','2026-11-08 22:39:49','2026-11-08 22:49:49',NULL,'2026-11-08 22:57:49'),(5,1,'Chè khúc bạch',45000,1,NULL,'served',NULL,NULL,'2026-11-08 22:59:49','2026-11-08 23:09:49',NULL,'2026-11-08 22:57:49'),(6,2,'Lẩu nấm gà tiềm',320000,1,NULL,'done',2,'2026-11-08 22:37:49',NULL,'2026-11-18 10:44:46',NULL,'2026-11-08 22:57:49'),(7,2,'Khoai tây chiên giòn',45000,1,NULL,'served',2,'2026-11-08 22:37:49','2026-11-08 22:39:49','2026-11-08 22:49:49',NULL,'2026-11-08 22:57:49'),(8,2,'Nước ngọt các loại',25000,3,NULL,'served',2,'2026-11-08 22:37:49','2026-11-08 22:39:49','2026-11-08 22:49:49',NULL,'2026-11-08 22:57:49'),(9,2,'Khăn lạnh',5000,3,NULL,'served',2,'2026-11-08 22:37:49','2026-11-08 22:39:49','2026-11-08 22:49:49',NULL,'2026-11-08 22:57:49'),(10,2,'Bò lúc lắc',150000,1,NULL,'done',1,'2026-11-08 22:57:49','2026-11-18 10:44:33','2026-11-18 10:44:50',NULL,'2026-11-08 22:57:49'),(11,3,'Combo Bò Wagyu thượng hạng',890000,1,NULL,'served',2,'2026-11-08 22:37:49','2026-11-08 22:39:49','2026-11-08 22:49:49',NULL,'2026-11-08 22:57:49'),(12,3,'Vang đỏ Cabernet Sauvignon',350000,1,NULL,'served',2,'2026-11-08 22:37:49','2026-11-08 22:39:49','2026-11-08 22:49:49',NULL,'2026-11-08 22:57:49'),(13,3,'Bánh tiramisu tráng miệng',35000,6,NULL,'done',2,'2026-11-08 22:37:49',NULL,'2026-11-18 10:44:48',NULL,'2026-11-08 22:57:49'),(14,4,'Bún chả Hà Nội đặc biệt',75000,2,NULL,'done',2,'2026-11-08 22:37:49',NULL,'2026-11-18 10:44:49',NULL,'2026-11-08 22:57:49'),(15,4,'Trà tắc hạt chia',20000,2,NULL,'served',2,'2026-11-08 22:37:49','2026-11-08 22:39:49','2026-11-08 22:49:49',NULL,'2026-11-08 22:57:49'),(1579,1,'Lẩu nấm gà tiềm',320000,1,NULL,'served',NULL,'2026-11-18 09:11:47','2026-11-18 09:13:47','2026-11-18 09:33:47',NULL,'2026-11-18 09:11:47'),(1580,1,'Bò nướng tảng sốt tiêu',280000,2,NULL,'served',NULL,'2026-11-18 08:54:47','2026-11-18 08:56:47','2026-11-18 09:20:47',NULL,'2026-11-18 08:54:47'),(1581,1,'Cơm gà Hải Nam',65000,1,NULL,'served',NULL,'2026-11-18 08:39:47','2026-11-18 08:41:47','2026-11-18 08:48:47',NULL,'2026-11-18 08:39:47'),(1582,1,'Salad trộn đặc biệt',55000,1,NULL,'served',NULL,'2026-11-18 08:24:47','2026-11-18 08:26:47','2026-11-18 08:30:47',NULL,'2026-11-18 08:24:47'),(1583,1,'Mì xào bò',85000,2,NULL,'served',NULL,'2026-11-18 08:09:47','2026-11-18 08:11:47','2026-11-18 08:20:47',NULL,'2026-11-18 08:09:47'),(1584,1,'Lẩu nấm gà tiềm',320000,1,NULL,'served',NULL,'2026-11-18 07:39:47','2026-11-18 07:41:47','2026-11-18 08:04:47',NULL,'2026-11-18 07:39:47'),(1585,1,'Trà đào cam sả',35000,3,NULL,'served',NULL,'2026-11-18 07:29:47','2026-11-18 07:31:47','2026-11-18 07:33:47',NULL,'2026-11-18 07:29:47'),(1586,1,'Bún chả Hà Nội đặc biệt',75000,1,NULL,'served',NULL,'2026-11-18 06:39:47','2026-11-18 06:41:47','2026-11-18 06:53:47',NULL,'2026-11-18 06:39:47'),(1587,1,'Bò lúc lắc khoai tây',165000,1,NULL,'served',NULL,'2026-11-18 05:39:47','2026-11-18 05:41:47','2026-11-18 05:57:47',NULL,'2026-11-18 05:39:47'),(1588,1,'Cơm rang dưa bò',70000,2,NULL,'served',NULL,'2026-11-18 04:39:47','2026-11-18 04:41:47','2026-11-18 04:49:47',NULL,'2026-11-18 04:39:47'),(1602,461,'Salad hoa quả',50000,1,NULL,'cancelled',1,'2026-11-27 00:56:45',NULL,NULL,'2026-11-27 00:56:55','2026-11-27 00:56:45'),(1603,461,'Bò áp chảo',100000,1,NULL,'cancelled',1,'2026-11-27 00:56:52',NULL,NULL,'2026-11-27 00:56:56','2026-11-27 00:56:52'),(1604,462,'Sữa chua trái cây',40000,1,NULL,'cancelled',1,'2026-11-27 00:57:09','2026-11-27 00:57:27','2026-11-27 00:57:27','2026-11-27 00:57:33','2026-11-27 00:57:09'),(1620,478,'Ba chỉ heo cuốn phomai',0,1,NULL,'done',1,'2026-11-27 01:26:43','2026-11-27 01:27:02','2026-11-27 01:27:02',NULL,'2026-11-27 01:26:43'),(1624,482,'Đĩa mời size L',0,1,NULL,'cancelled',1,'2026-11-27 01:35:03',NULL,NULL,'2026-11-27 01:36:55','2026-11-27 01:35:03'),(1625,482,'Salad hoa quả',50000,1,NULL,'cancelled',1,'2026-11-27 01:35:05',NULL,NULL,'2026-11-27 01:36:56','2026-11-27 01:35:05'),(1626,482,'Sữa chua trái cây',40000,1,NULL,'cancelled',1,'2026-11-27 01:35:07',NULL,NULL,'2026-11-27 01:36:58','2026-11-27 01:35:07'),(1627,482,'Sữa chua trái cây',40000,1,NULL,'cancelled',1,'2026-11-27 01:37:07',NULL,NULL,'2026-11-27 01:37:13','2026-11-27 01:37:07'),(1628,482,'Sữa chua trái cây',40000,1,NULL,'cancelled',1,'2026-11-27 01:37:09',NULL,NULL,'2026-11-27 01:37:11','2026-11-27 01:37:09'),(1629,482,'Bò áp chảo',100000,1,NULL,'cancelled',1,'2026-11-27 01:37:22',NULL,NULL,'2026-11-27 01:44:32','2026-11-27 01:37:22'),(1630,482,'Salad hoa quả',50000,6,NULL,'cancelled',1,'2026-11-27 01:46:26',NULL,NULL,'2026-11-27 01:46:31','2026-11-27 01:41:08'),(1636,482,'Salad hoa quả',50000,2,NULL,'done',1,'2026-11-27 01:54:13','2026-11-27 08:45:00','2026-11-27 08:45:00',NULL,'2026-11-27 01:54:13');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_code` varchar(10) NOT NULL,
  `status` enum('open','paid') NOT NULL DEFAULT 'open',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_orders_table` (`table_code`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=490 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'B03','paid','2026-11-08 22:57:49'),(2,'B05','paid','2026-11-08 22:57:49'),(3,'B10','paid','2026-11-08 22:57:49'),(4,'B11','paid','2026-11-08 22:57:49'),(461,'B09','open','2026-11-27 00:56:45'),(462,'B06','open','2026-11-27 00:57:09'),(478,'B05','open','2026-11-27 01:26:43'),(482,'B01','open','2026-11-27 01:35:03');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `method` varchar(10) NOT NULL,
  `amount` int(11) NOT NULL,
  `cash_received` int(11) NOT NULL DEFAULT 0,
  `change_amount` int(11) NOT NULL DEFAULT 0,
  `cashier_id` int(11) DEFAULT NULL,
  `paid_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,'qr',2613870,0,0,2,'2026-11-23 19:01:51'),(2,2,'qr',686070,0,0,2,'2026-11-23 19:01:59'),(3,3,'qr',215460,0,0,2,'2026-11-23 19:02:07'),(4,4,'qr',1644300,0,0,2,'2026-11-23 19:02:16');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_products_sku` (`sku`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (23,'Áo thun nam','SP001',NULL,100,150000.00,'2025-08-25 17:43:49'),(24,'Quần jean nữ','SP002',NULL,50,299000.00,'2025-08-25 17:43:49'),(25,'Giày thể thao','SP003',NULL,25,599000.00,'2025-08-25 17:43:49'),(26,'Túi xách','SP004',NULL,15,459000.00,'2025-08-25 17:43:49'),(27,'Đồng hồ','SP005',NULL,8,899000.00,'2025-08-25 17:43:49');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `public_reservations`
--

DROP TABLE IF EXISTS `public_reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `public_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(120) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(120) DEFAULT NULL,
  `guests` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 120,
  `area_id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `status` enum('pending','confirmed','seated','cancelled','rejected') NOT NULL DEFAULT 'pending',
  `seated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reservation_date` (`reservation_date`),
  KEY `idx_reservation_phone` (`phone`),
  KEY `idx_reservation_table` (`table_id`),
  KEY `idx_reservation_status` (`status`),
  KEY `idx_reservation_area` (`area_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `public_reservations`
--

LOCK TABLES `public_reservations` WRITE;
/*!40000 ALTER TABLE `public_reservations` DISABLE KEYS */;
INSERT INTO `public_reservations` VALUES (1,'Trần Huỳnh Gia Nguyễn','0777815075','trangianguyen123@gmail.com',2,'2026-11-28','18:00:00',120,1,4,NULL,'seated','2026-11-27 01:23:29','2026-11-27 01:05:11');
/*!40000 ALTER TABLE `public_reservations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `guests` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `table_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `status` enum('pending','confirmed','seated','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reservation_code` varchar(24) DEFAULT NULL,
  `owner_token_hash` char(64) DEFAULT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `guest_phone` varchar(24) DEFAULT NULL,
  `guest_email` varchar(150) DEFAULT NULL,
  `duration_minutes` smallint(5) unsigned NOT NULL DEFAULT 120,
  `party_size` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `area_name` varchar(100) NOT NULL DEFAULT '',
  `table_code` varchar(20) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `reservation_code` (`reservation_code`),
  KEY `idx_reservation_date_status` (`reservation_date`,`status`),
  KEY `idx_reservation_table_slot` (`table_id`,`reservation_date`,`status`,`reservation_time`),
  KEY `idx_reservation_phone` (`guest_phone`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservations`
--

LOCK TABLES `reservations` WRITE;
/*!40000 ALTER TABLE `reservations` DISABLE KEYS */;
/*!40000 ALTER TABLE `reservations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `restaurant_areas`
--

DROP TABLE IF EXISTS `restaurant_areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `restaurant_areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `entrance_pos` varchar(30) NOT NULL DEFAULT 'bottom_center',
  `entrance_x` float NOT NULL DEFAULT 50,
  `entrance_y` float NOT NULL DEFAULT 96,
  `cashier_x` float NOT NULL DEFAULT 14,
  `cashier_y` float NOT NULL DEFAULT 6,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_area_status` (`status`),
  KEY `idx_area_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restaurant_areas`
--

LOCK TABLES `restaurant_areas` WRITE;
/*!40000 ALTER TABLE `restaurant_areas` DISABLE KEYS */;
INSERT INTO `restaurant_areas` VALUES (1,'indoor','Trong nhà (Máy lạnh)','Không gian sang trọng, máy lạnh trung tâm mát mẻ',1,'active','bottom_center',50,96,14,6,'2026-11-08 23:09:01'),(2,'garden','Sân vườn (Ngoài trời)','Không gian thoáng mát, cảnh quan thiên nhiên trong lành',2,'active','left_center',6,50,14,6,'2026-11-08 23:09:01'),(3,'vip','Phòng VIP','Không gian riêng tư, cách âm, phục vụ hội nghị và tiệc gia đình',3,'active','bottom_right',85,96,14,6,'2026-11-08 23:09:01'),(25,'test-rooftop','Sân Thượng VIP Cập Nhật','Mô tả mới',50,'active','top_right',86,4,20,10,'2026-11-25 10:24:18');
/*!40000 ALTER TABLE `restaurant_areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_ratings`
--

DROP TABLE IF EXISTS `service_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `token` char(32) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `rated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_id` (`invoice_id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_service_ratings_staff` (`staff_id`),
  CONSTRAINT `fk_service_ratings_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_ratings`
--

LOCK TABLES `service_ratings` WRITE;
/*!40000 ALTER TABLE `service_ratings` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_ratings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_alerts`
--

DROP TABLE IF EXISTS `stock_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `min_quantity` int(11) NOT NULL DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stock_alerts_product` (`product_id`),
  CONSTRAINT `fk_stock_alerts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_alerts`
--

LOCK TABLES `stock_alerts` WRITE;
/*!40000 ALTER TABLE `stock_alerts` DISABLE KEYS */;
INSERT INTO `stock_alerts` VALUES (1,23,10,1,'2025-08-25 17:43:49'),(2,24,5,1,'2025-08-25 17:43:49'),(3,25,3,1,'2025-08-25 17:43:49'),(4,26,2,1,'2025-08-25 17:43:49'),(5,27,1,1,'2025-08-25 17:43:49');
/*!40000 ALTER TABLE `stock_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_transactions`
--

DROP TABLE IF EXISTS `stock_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `transaction_type` enum('in','out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stock_tx_product` (`product_id`),
  KEY `idx_stock_tx_created_at` (`created_at`),
  CONSTRAINT `fk_stock_tx_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_transactions`
--

LOCK TABLES `stock_transactions` WRITE;
/*!40000 ALTER TABLE `stock_transactions` DISABLE KEYS */;
INSERT INTO `stock_transactions` VALUES (15,23,'in',100,'Import từ Excel',NULL,'admin','2025-08-25 17:43:49'),(16,24,'in',50,'Import từ Excel',NULL,'admin','2025-08-25 17:43:49'),(17,25,'in',25,'Import từ Excel',NULL,'admin','2025-08-25 17:43:49'),(18,26,'in',15,'Import từ Excel',NULL,'admin','2025-08-25 17:43:49'),(19,27,'in',15,'Import từ Excel',NULL,'admin','2025-08-25 17:43:49'),(20,26,'out',10,'Sử dụng nội bộ','','admin','2025-08-28 11:26:39'),(28,26,'in',10,'Import từ Excel',NULL,'admin','2025-10-02 05:58:45');
/*!40000 ALTER TABLE `stock_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `table_assignments`
--

DROP TABLE IF EXISTS `table_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `table_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_code` varchar(20) NOT NULL,
  `guest_count` int(11) NOT NULL DEFAULT 2,
  `customer_name` varchar(100) NOT NULL DEFAULT '',
  `customer_phone` varchar(25) NOT NULL DEFAULT '',
  `staff_id` int(11) DEFAULT NULL,
  `staff_name` varchar(100) NOT NULL DEFAULT '',
  `note` varchar(500) NOT NULL DEFAULT '',
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `opened_at` datetime DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assign_table_status` (`table_code`,`status`),
  KEY `idx_assign_staff` (`staff_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `table_assignments`
--

LOCK TABLES `table_assignments` WRITE;
/*!40000 ALTER TABLE `table_assignments` DISABLE KEYS */;
INSERT INTO `table_assignments` VALUES (1,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-25 10:24:17','2026-11-25 10:24:18'),(2,'B10',4,'','',2,'Nguyễn Phục Vụ','Chuyển từ bàn B05 | Gộp từ bàn B09','active','2026-11-25 10:41:03',NULL),(3,'B09',2,'','',1,'Quản trị viên Hệ thống','Chuyển từ bàn B05','closed','2026-11-25 10:41:11','2026-11-25 10:44:48'),(4,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-25 10:43:37','2026-11-25 10:43:37'),(5,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-25 10:44:06','2026-11-25 10:44:06'),(6,'B01',2,'','',NULL,'','Nhóm A','closed','2026-11-25 10:44:06','2026-11-25 10:44:06'),(7,'B02',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B01 (Thanh toán chung 1 bill)','closed','2026-11-25 10:44:06','2026-11-25 10:44:06'),(8,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-27 00:54:01','2026-11-27 00:54:01'),(9,'B01',2,'','',NULL,'','Nhóm A','closed','2026-11-27 00:54:01','2026-11-27 00:54:01'),(10,'B02',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B01 (Thanh toán chung 1 bill)','closed','2026-11-27 00:54:01','2026-11-27 00:54:01'),(11,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-27 00:55:01','2026-11-27 00:55:01'),(12,'B01',2,'','',NULL,'','Nhóm A','closed','2026-11-27 00:55:01','2026-11-27 00:55:01'),(13,'B02',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B01 (Thanh toán chung 1 bill)','closed','2026-11-27 00:55:01','2026-11-27 00:55:01'),(14,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-27 01:03:27','2026-11-27 01:03:27'),(15,'B01',2,'','',NULL,'','Nhóm A','closed','2026-11-27 01:03:27','2026-11-27 01:03:27'),(16,'B02',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B01 (Thanh toán chung 1 bill)','closed','2026-11-27 01:03:27','2026-11-27 01:03:27'),(17,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-27 01:10:45','2026-11-27 01:10:45'),(18,'B01',2,'','',NULL,'','Nhóm A','closed','2026-11-27 01:10:45','2026-11-27 01:10:45'),(19,'B02',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B01 (Thanh toán chung 1 bill)','closed','2026-11-27 01:10:45','2026-11-27 01:10:45'),(20,'B01',3,'Khách Test Checkin','0909998877',1,'Quản trị viên Hệ thống','Đặt bàn #SD000002 | Ngồi gần cửa sổ','closed','2026-11-27 01:10:46','2026-11-27 01:10:46'),(21,'B01',3,'Khách Test Checkin','0909998877',1,'Quản trị viên Hệ thống','Đặt bàn #SD000003 | Ngồi gần cửa sổ','closed','2026-11-27 01:10:58','2026-11-27 01:10:58'),(22,'B01',3,'Khách Test Checkin','0909998877',1,'Quản trị viên Hệ thống','Đặt bàn #SD000004 | Ngồi gần cửa sổ','closed','2026-11-27 01:11:10','2026-11-27 01:11:10'),(23,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-27 01:11:10','2026-11-27 01:11:10'),(24,'B01',2,'','',NULL,'','Nhóm A','closed','2026-11-27 01:11:10','2026-11-27 01:11:10'),(25,'B02',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B01 (Thanh toán chung 1 bill)','closed','2026-11-27 01:11:10','2026-11-27 01:11:10'),(26,'B01',3,'Khách Test Checkin','0909998877',1,'Quản trị viên Hệ thống','Đặt bàn #SD000005 | Ngồi gần cửa sổ','closed','2026-11-27 01:12:06','2026-11-27 01:12:06'),(27,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-27 01:12:06','2026-11-27 01:12:06'),(28,'B01',2,'','',NULL,'','Nhóm A','closed','2026-11-27 01:12:06','2026-11-27 01:12:06'),(29,'B02',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B01 (Thanh toán chung 1 bill)','closed','2026-11-27 01:12:06','2026-11-27 01:12:06'),(30,'B01',3,'Khách Test Checkin','0909998877',1,'Quản trị viên Hệ thống','Đặt bàn #SD000006 | Ngồi gần cửa sổ','closed','2026-11-27 01:21:49','2026-11-27 01:21:49'),(31,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-27 01:21:49','2026-11-27 01:21:49'),(32,'B01',2,'','',NULL,'','Nhóm A','closed','2026-11-27 01:21:49','2026-11-27 01:21:49'),(33,'B02',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B01 (Thanh toán chung 1 bill)','closed','2026-11-27 01:21:49','2026-11-27 01:21:49'),(34,'B04',2,'Trần Huỳnh Gia Nguyễn','0777815075',2,'Nguyễn Phục Vụ','Đặt bàn #SD000001','closed','2026-11-27 01:23:29','2026-11-27 01:29:17'),(35,'B02',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B01 (Khách đổi sang khu mát hơn)','closed','2026-11-27 01:34:19','2026-11-27 01:34:19'),(36,'B01',2,'','',NULL,'','Nhóm A','closed','2026-11-27 01:34:19','2026-11-27 01:34:19'),(37,'B02',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B01 (Thanh toán chung 1 bill)','closed','2026-11-27 01:34:19','2026-11-27 01:34:20'),(38,'B01',3,'Khách Test Checkin','0909998877',1,'Quản trị viên Hệ thống','Đặt bàn #SD000007 | Ngồi gần cửa sổ','closed','2026-11-27 01:34:20','2026-11-27 01:34:20'),(39,'B04',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B02 (Khách đổi sang khu mát hơn)','closed','2026-11-27 01:44:08','2026-11-27 01:44:08'),(40,'B02',2,'','',NULL,'','Nhóm A','closed','2026-11-27 01:44:08','2026-11-27 01:44:08'),(41,'B04',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B02 (Thanh toán chung 1 bill)','closed','2026-11-27 01:44:08','2026-11-27 01:44:08'),(42,'B02',3,'Khách Test Checkin','0909998877',1,'Quản trị viên Hệ thống','Đặt bàn #SD000008 | Ngồi gần cửa sổ','closed','2026-11-27 01:44:08','2026-11-27 01:44:08'),(43,'B02',3,'Khách Test Checkin','0909998877',1,'Quản trị viên Hệ thống','Đặt bàn #SD000009 | Ngồi gần cửa sổ','closed','2026-11-27 01:54:18','2026-11-27 01:54:18'),(44,'B04',6,'','',NULL,'','Ghép thêm 2 ghế | Chuyển từ bàn B02 (Khách đổi sang khu mát hơn)','closed','2026-11-27 01:54:18','2026-11-27 01:54:18'),(45,'B02',2,'','',NULL,'','Nhóm A','closed','2026-11-27 01:54:18','2026-11-27 01:54:18'),(46,'B04',5,'','',NULL,'','Nhóm B | Nhóm A | Gộp từ bàn B02 (Thanh toán chung 1 bill)','closed','2026-11-27 01:54:18','2026-11-27 01:54:18'),(47,'B02',3,'Khách Test Checkin','0909998877',1,'Quản trị viên Hệ thống','Đặt bàn #SD000010 | Ngồi gần cửa sổ','closed','2026-11-27 02:04:09','2026-11-27 02:04:09');
/*!40000 ALTER TABLE `table_assignments` ENABLE KEYS */;
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
  `role` enum('admin','shift_manager','staff','kitchen') NOT NULL DEFAULT 'staff',
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$AM/45asFotKqNnz0PXKWXuxXlU6WiLEVoB48A.J3GAHJhZaTpa.te','Quản trị viên Hệ thống','admin','','','active','2026-11-27 08:25:23','2026-11-04 11:43:58','2026-11-27 08:25:23'),(2,'staff','$2y$10$ttU56bD8obk37xwsA0h/xurGl0HMsXVMchWGH4Zg1D9MnTSaZtrrC','Nguyễn Phục Vụ','staff','0912345678','staff@smartdine.vn','active','2026-11-27 01:23:24','2026-11-04 11:43:58','2026-11-27 01:23:24'),(3,'kitchen','$2y$10$5484iX1IsNPaFUPXWT.AuO/.zg/IDtoSJON22RL8IogXi65spUL/y','Trần Bếp Trưởng','kitchen','0923456789','chef@smartdine.vn','active','2026-11-23 18:51:39','2026-11-04 11:43:58','2026-11-23 18:51:39'),(4,'1','$2y$10$/W5WawHmXYdBoT18oek3YOZBP/ZkfY5CRUh.eml8x3YWPsv2ZQyYq','Nhân viên Demo #1','staff','0988888888','demo1@smartdine.vn','active','2026-11-18 10:40:29','2026-11-04 11:43:58','2026-11-18 10:40:29'),(7,'shift_manager','$2y$10$vC3tkl5NxEmNro0DNCVlveoep5FaJuX/BzB5eBEDRjQ2YCuD2pjiS','Lê Quản Lý Ca','shift_manager','0934567890','shift@smartdine.vn','active',NULL,'2026-11-27 01:53:58','2026-11-27 01:53:58');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vouchers`
--

DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `discount_type` enum('percent','amount') NOT NULL DEFAULT 'percent',
  `discount_value` int(11) NOT NULL,
  `min_order_value` int(11) NOT NULL DEFAULT 0,
  `max_discount_amount` int(11) NOT NULL DEFAULT 0,
  `usage_limit` int(11) NOT NULL DEFAULT 0,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_voucher_code` (`code`),
  KEY `idx_voucher_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vouchers`
--

LOCK TABLES `vouchers` WRITE;
/*!40000 ALTER TABLE `vouchers` DISABLE KEYS */;
INSERT INTO `vouchers` VALUES (1,'WELCOME10','Khuyến mãi thành viên mới 10%','percent',10,100000,50000,500,12,'2026-11-18','2026-12-31','active','2026-11-18 09:39:47'),(2,'GIAM50K','Giảm trực tiếp 50K cho đơn từ 300K','amount',50000,300000,0,200,45,'2026-11-18','2026-12-31','active','2026-11-18 09:39:47'),(3,'VIP20','Tri ân khách hàng VIP giảm 20%','percent',20,500000,150000,100,8,'2026-11-18','2026-12-31','active','2026-11-18 09:39:47'),(4,'LUNCH30K','Ưu đãi giờ trưa giảm 30K','amount',30000,150000,0,150,26,'2026-11-18','2026-12-31','active','2026-11-18 09:39:47'),(5,'EXPIRED15','Chương trình khai trương (Đã kết thúc)','percent',15,200000,60000,50,50,'2026-01-01','2026-02-01','inactive','2026-11-18 09:39:47');
/*!40000 ALTER TABLE `vouchers` ENABLE KEYS */;
UNLOCK TABLES;
-- Table structure for table `qr_carts`
--

DROP TABLE IF EXISTS `qr_carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_carts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_session` (`session_id`),
  CONSTRAINT `fk_qr_cart_session` FOREIGN KEY (`session_id`) REFERENCES `qr_table_sessions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_carts`
--

LOCK TABLES `qr_carts` WRITE;
/*!40000 ALTER TABLE `qr_carts` DISABLE KEYS */;
INSERT INTO `qr_carts` VALUES (21,1,'2026-11-18 05:25:38','2026-11-18 05:25:38');
/*!40000 ALTER TABLE `qr_carts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_cart_items`
--

DROP TABLE IF EXISTS `qr_cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_cart_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint(20) unsigned NOT NULL,
  `menu_item_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `toppings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cart_items_cart` (`cart_id`),
  KEY `idx_cart_items_menu` (`menu_item_id`),
  CONSTRAINT `fk_qr_cart_item_cart` FOREIGN KEY (`cart_id`) REFERENCES `qr_carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_cart_item_menu` FOREIGN KEY (`menu_item_id`) REFERENCES `qr_menu_items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_cart_items`
--

LOCK TABLES `qr_cart_items` WRITE;
/*!40000 ALTER TABLE `qr_cart_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_kitchen_links`
--

DROP TABLE IF EXISTS `qr_kitchen_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_kitchen_links` (
  `qr_item_id` bigint(20) unsigned NOT NULL,
  `kitchen_item_id` int(11) NOT NULL,
  PRIMARY KEY (`qr_item_id`),
  UNIQUE KEY `kitchen_item_id` (`kitchen_item_id`),
  CONSTRAINT `fk_qr_kitchen_item` FOREIGN KEY (`qr_item_id`) REFERENCES `qr_order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_kitchen_links`
--

LOCK TABLES `qr_kitchen_links` WRITE;
/*!40000 ALTER TABLE `qr_kitchen_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_kitchen_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_menu_details`
--

DROP TABLE IF EXISTS `qr_menu_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_menu_details` (
  `menu_item_id` bigint(20) unsigned NOT NULL,
  `category` varchar(30) NOT NULL DEFAULT 'main',
  `description` varchar(1000) NOT NULL DEFAULT '',
  `image_url` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`menu_item_id`),
  CONSTRAINT `qr_menu_details_ibfk_1` FOREIGN KEY (`menu_item_id`) REFERENCES `qr_menu_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_menu_details`
--

LOCK TABLES `qr_menu_details` WRITE;
/*!40000 ALTER TABLE `qr_menu_details` DISABLE KEYS */;
INSERT INTO `qr_menu_details` VALUES (1,'main','Gà mềm ngọt, cơm thơm nấu cùng nước luộc gà; dùng kèm dưa leo và sốt gừng.','https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=600&q=80'),(2,'main','Mì trứng xào cùng thịt bò, rau cải và nấm. Có thể ghi chú ít dầu hoặc không hành.','https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=600&q=80'),(3,'side','Rau xanh, cà chua và rau củ trộn sốt mè rang. Dùng làm món khai vị.','https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=600&q=80'),(4,'drink','Trà đào dùng cùng cam, sả và đá. Có thể ghi chú mức đường hoặc lượng đá mong muốn.','https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=600&q=80'),(5,'main','Thịt bò áp chảo cùng rau củ và sốt tiêu đen, dùng nóng.','https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=600&q=80'),(6,'dessert','Sữa chua mát lạnh dùng cùng trái cây theo mùa.','https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=600&q=80');
/*!40000 ALTER TABLE `qr_menu_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_menu_items`
--

DROP TABLE IF EXISTS `qr_menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_menu_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `source_menu_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_source_menu` (`source_menu_id`),
  KEY `idx_qr_menu_available` (`is_available`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_menu_items`
--

LOCK TABLES `qr_menu_items` WRITE;
/*!40000 ALTER TABLE `qr_menu_items` DISABLE KEYS */;
INSERT INTO `qr_menu_items` VALUES (1,'Cơm gà Hải Nam',75000.00,1,'qr-ui-com-ga','2026-11-08 01:06:31','2026-11-08 01:06:31'),(2,'Mì xào bò',85000.00,1,'qr-ui-mi-bo','2026-11-08 01:06:31','2026-11-08 01:06:31'),(3,'Salad trộn',55000.00,1,'qr-ui-salad','2026-11-08 01:06:31','2026-11-08 01:06:31'),(4,'Trà đào cam sả',35000.00,1,'qr-ui-tra-dao','2026-11-08 01:06:31','2026-11-08 01:06:31'),(5,'Bò lúc lắc',120000.00,1,'qr-ui-bo-luc-lac','2026-11-08 01:06:31','2026-11-08 01:06:31'),(6,'Sữa chua trái cây',40000.00,1,'qr-ui-sua-chua','2026-11-08 01:06:31','2026-11-08 01:06:31');
/*!40000 ALTER TABLE `qr_menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_menu_toppings`
--

DROP TABLE IF EXISTS `qr_menu_toppings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_menu_toppings` (
  `menu_item_id` bigint(20) unsigned NOT NULL,
  `topping_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`menu_item_id`,`topping_id`),
  KEY `topping_id` (`topping_id`),
  CONSTRAINT `qr_menu_toppings_ibfk_1` FOREIGN KEY (`menu_item_id`) REFERENCES `qr_menu_items` (`id`),
  CONSTRAINT `qr_menu_toppings_ibfk_2` FOREIGN KEY (`topping_id`) REFERENCES `qr_toppings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_menu_toppings`
--

LOCK TABLES `qr_menu_toppings` WRITE;
/*!40000 ALTER TABLE `qr_menu_toppings` DISABLE KEYS */;
INSERT INTO `qr_menu_toppings` VALUES (1,1),(1,2),(2,1),(2,2),(3,1),(3,2),(5,1),(5,2);
/*!40000 ALTER TABLE `qr_menu_toppings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_order_item_toppings`
--

DROP TABLE IF EXISTS `qr_order_item_toppings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_order_item_toppings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` bigint(20) unsigned NOT NULL,
  `topping_name` varchar(150) NOT NULL,
  `topping_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_qr_topping_order_item` (`order_item_id`),
  CONSTRAINT `fk_qr_topping_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `qr_order_items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_order_item_toppings`
--

LOCK TABLES `qr_order_item_toppings` WRITE;
/*!40000 ALTER TABLE `qr_order_item_toppings` DISABLE KEYS */;
INSERT INTO `qr_order_item_toppings` VALUES (1,2,'Phô mai',15000.00),(2,5,'Phô mai',15000.00),(3,6,'Trứng thêm',10000.00);
/*!40000 ALTER TABLE `qr_order_item_toppings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_order_items`
--

DROP TABLE IF EXISTS `qr_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `menu_item_id` bigint(20) unsigned NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `base_price` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `status` enum('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_qr_order_items_order` (`order_id`),
  KEY `idx_qr_order_items_status` (`status`),
  KEY `idx_qr_order_items_menu` (`menu_item_id`),
  CONSTRAINT `fk_qr_order_item_menu` FOREIGN KEY (`menu_item_id`) REFERENCES `qr_menu_items` (`id`),
  CONSTRAINT `fk_qr_order_item_order` FOREIGN KEY (`order_id`) REFERENCES `qr_orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_order_items`
--

LOCK TABLES `qr_order_items` WRITE;
/*!40000 ALTER TABLE `qr_order_items` DISABLE KEYS */;
INSERT INTO `qr_order_items` VALUES (1,1,1,'Cơm gà Hải Nam',1,75000.00,75000.00,75000.00,'','pending','2026-11-18 05:31:34','2026-11-18 05:31:34'),(2,2,1,'Cơm gà Hải Nam',1,75000.00,90000.00,90000.00,'','pending','2026-11-24 20:23:04','2026-11-24 20:23:04'),(3,2,1,'Cơm gà Hải Nam',1,75000.00,75000.00,75000.00,'','pending','2026-11-24 20:23:04','2026-11-24 20:23:04'),(4,2,2,'Mì xào bò',1,85000.00,85000.00,85000.00,'','pending','2026-11-24 20:23:04','2026-11-24 20:23:04'),(5,3,1,'Cơm gà Hải Nam',1,75000.00,90000.00,90000.00,'','pending','2026-11-24 20:26:48','2026-11-24 20:26:48'),(6,3,2,'Mì xào bò',2,85000.00,95000.00,190000.00,'','pending','2026-11-24 20:26:48','2026-11-24 20:26:48'),(7,4,3,'Salad trộn',1,55000.00,55000.00,55000.00,'','pending','2026-11-24 20:27:23','2026-11-24 20:27:23');
/*!40000 ALTER TABLE `qr_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_order_requests`
--

DROP TABLE IF EXISTS `qr_order_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_order_requests` (
  `request_key` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`request_key`),
  KEY `idx_qr_request_order` (`order_id`),
  CONSTRAINT `fk_qr_request_order` FOREIGN KEY (`order_id`) REFERENCES `qr_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_order_requests`
--

LOCK TABLES `qr_order_requests` WRITE;
/*!40000 ALTER TABLE `qr_order_requests` DISABLE KEYS */;
INSERT INTO `qr_order_requests` VALUES ('616fd6d477ba97dcbab10be5b2980a6cb905669ff8db380ab8c3ac23b9f15f55','b9840ca312c073f631036ee8db298720357519e8085a1c4fb55d0c608ba162f3',2,'2026-11-24 20:23:04'),('78e35754202e8c69f9dda9ad9466dd5bd7e06ad62fb737767342de64e3be1188','f5d673b3a106abc6e3f21d3a759f173164244c072198c2711d7cc75622667992',1,'2026-11-18 05:31:34'),('80a8a9cc30e1e246228f50f2297f27e390132cb2bd26bc3e32ab1931b98b500b','2e45985be33d1bdca6a27f1ed43b4d8436e913ee6ab0906adf2c3df743de1008',3,'2026-11-24 20:26:48'),('ba863b75484f9cb449216b55dfde47de6861e115f1343586ae3e822301b42d90','b3f506a1bd151827f66f45b8f93eaad9cdd279488ff88089923c569ff5977cf8',4,'2026-11-24 20:27:23');
/*!40000 ALTER TABLE `qr_order_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_orders`
--

DROP TABLE IF EXISTS `qr_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) unsigned NOT NULL,
  `order_code` varchar(40) DEFAULT NULL,
  `status` enum('submitted','preparing','ready','served','cancelled') NOT NULL DEFAULT 'submitted',
  `order_note` varchar(1000) DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qr_order_code` (`order_code`),
  KEY `idx_qr_order_session` (`session_id`),
  KEY `idx_qr_order_status` (`status`),
  KEY `idx_qr_order_created` (`created_at`),
  CONSTRAINT `fk_qr_order_session` FOREIGN KEY (`session_id`) REFERENCES `qr_table_sessions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_orders`
--

LOCK TABLES `qr_orders` WRITE;
/*!40000 ALTER TABLE `qr_orders` DISABLE KEYS */;
INSERT INTO `qr_orders` VALUES (1,1,'QR-BE153564DC27CCE84EB04D3C','submitted','',75000.00,'2026-11-18 05:31:34','2026-11-18 05:31:34'),(2,1,'QR-658BA06EFFCED8A0EC9212FF','submitted','',250000.00,'2026-11-24 20:23:04','2026-11-24 20:23:04'),(3,1,'QR-334C2E283BBF80FBBDAF0416','submitted','ít cay',280000.00,'2026-11-24 20:26:48','2026-11-24 20:26:48'),(4,1,'QR-CBFB0C3D77240FAB0E4B9580','submitted','',55000.00,'2026-11-24 20:27:23','2026-11-24 20:27:23');
/*!40000 ALTER TABLE `qr_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_table_codes`
--

DROP TABLE IF EXISTS `qr_table_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_table_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_code` varchar(20) NOT NULL,
  `qr_token` varchar(64) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qr_table_code` (`table_code`),
  UNIQUE KEY `uq_qr_token` (`qr_token`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_table_codes`
--

LOCK TABLES `qr_table_codes` WRITE;
/*!40000 ALTER TABLE `qr_table_codes` DISABLE KEYS */;
INSERT INTO `qr_table_codes` VALUES (1,'B01','39895a98d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(2,'B02','39895ffed9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(3,'B03','398960b1d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(4,'B04','39896106d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(5,'B05','39896153d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(6,'B06','398961bbd9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(7,'B07','3989620bd9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(8,'B08','39896253d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(9,'B09','398962a1d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(10,'B10','398962edd9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31');
/*!40000 ALTER TABLE `qr_table_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_table_sessions`
--

DROP TABLE IF EXISTS `qr_table_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_table_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `table_code` varchar(20) NOT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `active_slot` tinyint(3) unsigned DEFAULT 1,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_active_table` (`table_code`,`active_slot`),
  CONSTRAINT `fk_qr_session_table` FOREIGN KEY (`table_code`) REFERENCES `qr_table_codes` (`table_code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_table_sessions`
--

LOCK TABLES `qr_table_sessions` WRITE;
/*!40000 ALTER TABLE `qr_table_sessions` DISABLE KEYS */;
INSERT INTO `qr_table_sessions` VALUES (1,'B01','open',1,'2026-11-18 05:25:38',NULL);
/*!40000 ALTER TABLE `qr_table_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_toppings`
--

DROP TABLE IF EXISTS `qr_toppings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_toppings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_toppings`
--

LOCK TABLES `qr_toppings` WRITE;
/*!40000 ALTER TABLE `qr_toppings` DISABLE KEYS */;
INSERT INTO `qr_toppings` VALUES (1,'Trứng thêm',10000.00,1),(2,'Phô mai',15000.00,1);
/*!40000 ALTER TABLE `qr_toppings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-27 20:01:10
