-- Warehouse Management System Tables (Merged into smartdine_kds)
-- Sử dụng chung database `smartdine_kds` và bảng `users` của SmartDine KDS.
CREATE DATABASE IF NOT EXISTS `smartdine_kds`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `smartdine_kds`;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `category` VARCHAR(100) NULL DEFAULT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 0,
  `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_products_sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_alerts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `min_quantity` INT(11) NOT NULL DEFAULT 5,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stock_alerts_product` (`product_id`),
  CONSTRAINT `fk_stock_alerts_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `transaction_type` ENUM('in', 'out') NOT NULL,
  `quantity` INT(11) NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `reference` VARCHAR(100) DEFAULT NULL,
  `created_by` VARCHAR(100) DEFAULT 'admin',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stock_tx_product` (`product_id`),
  KEY `idx_stock_tx_created_at` (`created_at`),
  CONSTRAINT `fk_stock_tx_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
