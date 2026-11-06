<?php

function initOrderTables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `table_code` VARCHAR(10) NOT NULL,
            `status` ENUM('open', 'paid') NOT NULL DEFAULT 'open',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_orders_table (`table_code`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `order_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `item_name` VARCHAR(100) NOT NULL,
            `unit_price` INT NOT NULL,
            `qty` INT NOT NULL DEFAULT 1,
            `note` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('unsent', 'pending', 'cooking', 'done', 'served', 'cancelled') NOT NULL DEFAULT 'unsent',
            `sent_by` INT DEFAULT NULL,
            `sent_at` DATETIME DEFAULT NULL,
            `cancelled_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_items_order (`order_id`),
            CONSTRAINT fk_items_order FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if ((int) $pdo->query('SELECT COUNT(*) FROM `orders`')->fetchColumn() === 0) {
        seedDemoOrders($pdo);
    }
}

function seedDemoOrders(PDO $pdo): void {
    $demoOrders = [
        'B03' => [
            ['Cơm gà Hải Nam', 75000, 1, 'served'],
            ['Mì xào bò', 85000, 1, 'cooking'],
            ['Salad trộn đặc biệt', 55000, 1, 'cooking'],
            ['Trà đào cam sả', 35000, 2, 'served'],
            ['Chè khúc bạch', 45000, 1, 'unsent'],
        ],
        'B05' => [
            ['Lẩu nấm gà tiềm', 320000, 1, 'cooking'],
            ['Khoai tây chiên giòn', 45000, 1, 'served'],
            ['Nước ngọt các loại', 25000, 3, 'served'],
            ['Khăn lạnh', 5000, 3, 'served'],
            ['Bò lúc lắc', 150000, 1, 'unsent'],
        ],
        'B10' => [
            ['Combo Bò Wagyu thượng hạng', 890000, 1, 'served'],
            ['Vang đỏ Cabernet Sauvignon', 350000, 1, 'served'],
            ['Bánh tiramisu tráng miệng', 35000, 6, 'cooking'],
        ],
        'B11' => [
            ['Bún chả Hà Nội đặc biệt', 75000, 2, 'cooking'],
            ['Trà tắc hạt chia', 20000, 2, 'served'],
        ],
    ];

    $staffId  = $pdo->query("SELECT `id` FROM `users` WHERE `role` = 'staff' ORDER BY `id` LIMIT 1")->fetchColumn() ?: null;
    $sentAt   = (new DateTime('-20 minutes', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');
    $orderSql = $pdo->prepare('INSERT INTO `orders` (`table_code`) VALUES (?)');
    $itemSql  = $pdo->prepare(
        'INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `status`, `sent_by`, `sent_at`)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($demoOrders as $tableCode => $items) {
        $orderSql->execute([$tableCode]);
        $orderId = (int) $pdo->lastInsertId();

        foreach ($items as [$name, $price, $qty, $status]) {
            $isSent = $status !== 'unsent';
            $itemSql->execute([
                $orderId, $name, $price, $qty, $status,
                $isSent ? $staffId : null,
                $isSent ? $sentAt : null,
            ]);
        }
    }
}