-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: smartdine_kds
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buffet_packages`
--

LOCK TABLES `buffet_packages` WRITE;
/*!40000 ALTER TABLE `buffet_packages` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_stock_snapshots`
--

DROP TABLE IF EXISTS `daily_stock_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_stock_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `snapshot_date` date NOT NULL,
  `product_id` int(11) NOT NULL,
  `opening_qty` int(11) NOT NULL DEFAULT 0,
  `in_qty` int(11) NOT NULL DEFAULT 0,
  `other_out_qty` int(11) NOT NULL DEFAULT 0,
  `closing_qty` int(11) NOT NULL,
  `note` varchar(255) NOT NULL DEFAULT '',
  `updated_by` varchar(100) NOT NULL DEFAULT 'system',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_daily_stock_date_prod` (`snapshot_date`,`product_id`),
  KEY `idx_daily_stock_date` (`snapshot_date`),
  KEY `fk_daily_stock_product` (`product_id`),
  CONSTRAINT `fk_daily_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_stock_snapshots`
--

LOCK TABLES `daily_stock_snapshots` WRITE;
/*!40000 ALTER TABLE `daily_stock_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `daily_stock_snapshots` ENABLE KEYS */;
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
INSERT INTO `dining_tables` VALUES (1,'B01','Bàn B01',1,4,7.73,22.76,'square',115,115,'active',1,'2026-11-08 23:09:01'),(2,'B02','Bàn B02',1,4,7.5,49.4,'square',145,145,'active',2,'2026-11-08 23:09:01'),(4,'B04','Bàn B04',1,2,20.93,23.58,'round',120,120,'active',4,'2026-11-08 23:09:01'),(5,'B05','Bàn B05',2,6,7.71,79.92,'square',135,135,'active',5,'2026-11-08 23:09:01'),(6,'B06','Bàn B06',2,4,68,28,'round',90,90,'active',6,'2026-11-08 23:09:01'),(7,'B07','Bàn B07',2,4,20.06,82.28,'square',85,85,'active',7,'2026-11-08 23:09:01'),(8,'B08','Bàn B08',2,8,68,66,'rectangle',135,85,'active',8,'2026-11-08 23:09:01'),(9,'B09','Bàn B09',3,10,20.52,48.68,'rectangle',150,95,'active',9,'2026-11-08 23:09:01'),(10,'B10','Bàn B10',3,12,70,46,'rectangle',170,100,'active',10,'2026-11-08 23:09:01'),(11,'B11','Bàn B11',1,4,30.38,78.37,'square',85,85,'active',11,'2026-11-08 23:09:01'),(12,'B12','Bàn B12',1,6,78,64,'rectangle',115,85,'active',12,'2026-11-08 23:09:01');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dishes`
--

LOCK TABLES `dishes` WRITE;
/*!40000 ALTER TABLE `dishes` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_intents`
--

DROP TABLE IF EXISTS `payment_intents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_intents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `table_code` varchar(20) NOT NULL,
  `transfer_code` varchar(64) NOT NULL,
  `expected_amount` int(11) NOT NULL,
  `discount_type` varchar(20) NOT NULL DEFAULT 'none',
  `discount_value` int(11) NOT NULL DEFAULT 0,
  `voucher_code` varchar(64) DEFAULT NULL,
  `status` enum('pending','paid','expired') NOT NULL DEFAULT 'pending',
  `provider` varchar(40) DEFAULT NULL,
  `gateway_tx_id` varchar(120) DEFAULT NULL,
  `paid_amount` int(11) NOT NULL DEFAULT 0,
  `invoice_id` int(11) DEFAULT NULL,
  `raw_payload` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_id` (`order_id`),
  KEY `idx_table_status` (`table_code`,`status`),
  KEY `idx_transfer_code` (`transfer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_intents`
--

LOCK TABLES `payment_intents` WRITE;
/*!40000 ALTER TABLE `payment_intents` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_intents` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `public_reservations`
--

LOCK TABLES `public_reservations` WRITE;
/*!40000 ALTER TABLE `public_reservations` DISABLE KEYS */;
/*!40000 ALTER TABLE `public_reservations` ENABLE KEYS */;
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
  `toppings_json` longtext DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cart_items_cart` (`cart_id`),
  KEY `idx_cart_items_menu` (`menu_item_id`),
  CONSTRAINT `fk_qr_cart_item_cart` FOREIGN KEY (`cart_id`) REFERENCES `qr_carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_cart_item_menu` FOREIGN KEY (`menu_item_id`) REFERENCES `qr_menu_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_cart_items`
--

LOCK TABLES `qr_cart_items` WRITE;
/*!40000 ALTER TABLE `qr_cart_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_carts`
--

LOCK TABLES `qr_carts` WRITE;
/*!40000 ALTER TABLE `qr_carts` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_carts` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_menu_details`
--

LOCK TABLES `qr_menu_details` WRITE;
/*!40000 ALTER TABLE `qr_menu_details` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_menu_items`
--

LOCK TABLES `qr_menu_items` WRITE;
/*!40000 ALTER TABLE `qr_menu_items` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_menu_toppings`
--

LOCK TABLES `qr_menu_toppings` WRITE;
/*!40000 ALTER TABLE `qr_menu_toppings` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_order_item_toppings`
--

LOCK TABLES `qr_order_item_toppings` WRITE;
/*!40000 ALTER TABLE `qr_order_item_toppings` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_order_items`
--

LOCK TABLES `qr_order_items` WRITE;
/*!40000 ALTER TABLE `qr_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_order_requests`
--

DROP TABLE IF EXISTS `qr_order_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_order_requests` (
  `request_key` char(64) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`request_key`),
  KEY `idx_qr_request_order` (`order_id`),
  CONSTRAINT `fk_qr_request_order` FOREIGN KEY (`order_id`) REFERENCES `qr_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_order_requests`
--

LOCK TABLES `qr_order_requests` WRITE;
/*!40000 ALTER TABLE `qr_order_requests` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_orders`
--

LOCK TABLES `qr_orders` WRITE;
/*!40000 ALTER TABLE `qr_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_staff_calls`
--

DROP TABLE IF EXISTS `qr_staff_calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qr_staff_calls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_code` varchar(20) NOT NULL,
  `note` varchar(255) NOT NULL DEFAULT '',
  `status` enum('pending','handled') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `handled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qr_call_table_status` (`table_code`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_staff_calls`
--

LOCK TABLES `qr_staff_calls` WRITE;
/*!40000 ALTER TABLE `qr_staff_calls` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_staff_calls` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_table_codes`
--

LOCK TABLES `qr_table_codes` WRITE;
/*!40000 ALTER TABLE `qr_table_codes` DISABLE KEYS */;
INSERT INTO `qr_table_codes` VALUES (1,'B01','39895a98d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(2,'B02','39895ffed9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(4,'B04','39896106d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(5,'B05','39896153d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(6,'B06','398961bbd9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(7,'B07','3989620bd9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(8,'B08','39896253d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(9,'B09','398962a1d9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(10,'B10','398962edd9ce11f1a98914857fe0442a',1,'2026-11-08 01:06:31'),(11,'B11','28dabfc244fecdb8737b8cc82ac5cae7',1,'2026-11-27 17:47:10'),(12,'B12','e92f5aa0c69ede4904e727c9125c6e6d',1,'2026-11-27 17:47:10');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_table_sessions`
--

LOCK TABLES `qr_table_sessions` WRITE;
/*!40000 ALTER TABLE `qr_table_sessions` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_toppings`
--

LOCK TABLES `qr_toppings` WRITE;
/*!40000 ALTER TABLE `qr_toppings` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_toppings` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
INSERT INTO `restaurant_areas` VALUES (1,'indoor','Trong nhà (Máy lạnh)','Không gian sang trọng, máy lạnh trung tâm mát mẻ',1,'active','bottom_center',50,96,14,6,'2026-11-08 23:09:01'),(2,'garden','Sân vườn (Ngoài trời)','Không gian thoáng mát, cảnh quan thiên nhiên trong lành',2,'active','left_center',6,50,14,6,'2026-11-08 23:09:01'),(3,'vip','Phòng VIP','Không gian riêng tư, cách âm, phục vụ hội nghị và tiệc gia đình',3,'active','bottom_right',85,96,14,6,'2026-11-08 23:09:01');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_alerts`
--

LOCK TABLES `stock_alerts` WRITE;
/*!40000 ALTER TABLE `stock_alerts` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_transactions`
--

LOCK TABLES `stock_transactions` WRITE;
/*!40000 ALTER TABLE `stock_transactions` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `table_assignments`
--

LOCK TABLES `table_assignments` WRITE;
/*!40000 ALTER TABLE `table_assignments` DISABLE KEYS */;
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
INSERT INTO `users` VALUES (1,'admin','$2y$10$AM/45asFotKqNnz0PXKWXuxXlU6WiLEVoB48A.J3GAHJhZaTpa.te','Quản trị viên Hệ thống','admin','','','active','2026-11-28 00:53:33','2026-11-04 11:43:58','2026-11-28 00:53:33'),(2,'staff','$2y$10$ttU56bD8obk37xwsA0h/xurGl0HMsXVMchWGH4Zg1D9MnTSaZtrrC','Nguyễn Phục Vụ','staff','0912345678','staff@smartdine.vn','active','2026-11-28 00:48:40','2026-11-04 11:43:58','2026-11-28 00:48:40'),(3,'kitchen','$2y$10$5484iX1IsNPaFUPXWT.AuO/.zg/IDtoSJON22RL8IogXi65spUL/y','Trần Bếp Trưởng','kitchen','0923456789','chef@smartdine.vn','active','2026-11-28 00:52:12','2026-11-04 11:43:58','2026-11-28 00:52:12'),(7,'shift_manager','$2y$10$vC3tkl5NxEmNro0DNCVlveoep5FaJuX/BzB5eBEDRjQ2YCuD2pjiS','Lê Quản Lý Ca','shift_manager','0934567890','shift@smartdine.vn','active',NULL,'2026-11-27 01:53:58','2026-11-27 01:53:58');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vouchers`
--

LOCK TABLES `vouchers` WRITE;
/*!40000 ALTER TABLE `vouchers` DISABLE KEYS */;
/*!40000 ALTER TABLE `vouchers` ENABLE KEYS */;
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

-- Dump completed on 2026-11-28  1:25:37
