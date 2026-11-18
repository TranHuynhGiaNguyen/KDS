<?php
require_once __DIR__ . '/database.php';

function runMigration(): void {
    $pdo = getDbConnection();
    if (!$pdo) {
        echo "Database connection failed!\n";
        return;
    }

    echo "=== 1. Tạo bảng vouchers nếu chưa có ===\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `vouchers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(50) NOT NULL UNIQUE,
            `name` VARCHAR(150) NOT NULL,
            `discount_type` ENUM('percent', 'amount') NOT NULL DEFAULT 'percent',
            `discount_value` INT NOT NULL,
            `min_order_value` INT NOT NULL DEFAULT 0,
            `max_discount_amount` INT NOT NULL DEFAULT 0,
            `usage_limit` INT NOT NULL DEFAULT 0,
            `used_count` INT NOT NULL DEFAULT 0,
            `start_date` DATE DEFAULT NULL,
            `end_date` DATE DEFAULT NULL,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_voucher_code (`code`),
            INDEX idx_voucher_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  [OK] Bảng vouchers đã sẵn sàng.\n";

    // Kiểm tra xem bảng vouchers đã có dữ liệu mẫu chưa, nếu chưa thì thêm vào
    $count = (int) $pdo->query("SELECT COUNT(*) FROM `vouchers`")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO `vouchers` (`code`, `name`, `discount_type`, `discount_value`, `min_order_value`, `max_discount_amount`, `usage_limit`, `used_count`, `start_date`, `end_date`, `status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $today = date('Y-m-d');
        $endYear = date('Y-12-31');

        $samples = [
            ['WELCOME10', 'Khuyến mãi thành viên mới 10%', 'percent', 10, 100000, 50000, 500, 12, $today, $endYear, 'active'],
            ['GIAM50K', 'Giảm trực tiếp 50K cho đơn từ 300K', 'amount', 50000, 300000, 0, 200, 45, $today, $endYear, 'active'],
            ['VIP20', 'Tri ân khách hàng VIP giảm 20%', 'percent', 20, 500000, 150000, 100, 8, $today, $endYear, 'active'],
            ['LUNCH30K', 'Ưu đãi giờ trưa giảm 30K', 'amount', 30000, 150000, 0, 150, 26, $today, $endYear, 'active'],
            ['EXPIRED15', 'Chương trình khai trương (Đã kết thúc)', 'percent', 15, 200000, 60000, 50, 50, '2026-01-01', '2026-02-01', 'inactive'],
        ];

        foreach ($samples as $s) {
            $stmt->execute($s);
        }
        echo "  [OK] Đã thêm 5 mã voucher mẫu ban đầu.\n";
    }

    echo "=== 2. Kiểm tra và thêm cột voucher_code vào bảng invoices ===\n";
    $invCols = $pdo->query("SHOW COLUMNS FROM `invoices` LIKE 'voucher_code'")->fetchAll();
    if (empty($invCols)) {
        $pdo->exec("ALTER TABLE `invoices` ADD COLUMN `voucher_code` VARCHAR(50) DEFAULT NULL AFTER `discount_amount`");
        echo "  [OK] Đã thêm cột voucher_code vào invoices.\n";
    } else {
        echo "  [INFO] Cột voucher_code đã tồn tại trong invoices.\n";
    }

    echo "=== 3. Kiểm tra và thêm cột cooking_at, done_at vào order_items ===\n";
    $itemColsCooking = $pdo->query("SHOW COLUMNS FROM `order_items` LIKE 'cooking_at'")->fetchAll();
    if (empty($itemColsCooking)) {
        $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `cooking_at` DATETIME DEFAULT NULL AFTER `sent_at`");
        echo "  [OK] Đã thêm cột cooking_at vào order_items.\n";
    } else {
        echo "  [INFO] Cột cooking_at đã tồn tại trong order_items.\n";
    }

    $itemColsDone = $pdo->query("SHOW COLUMNS FROM `order_items` LIKE 'done_at'")->fetchAll();
    if (empty($itemColsDone)) {
        $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `done_at` DATETIME DEFAULT NULL AFTER `cooking_at`");
        echo "  [OK] Đã thêm cột done_at vào order_items.\n";
    } else {
        echo "  [INFO] Cột done_at đã tồn tại trong order_items.\n";
    }

    echo "=== 4. Cập nhật dữ liệu mẫu cho order_items (để có báo cáo hiệu suất bếp ngay) ===\n";
    // Kiểm tra xem có order_items nào có done_at chưa
    $doneCount = (int) $pdo->query("SELECT COUNT(*) FROM `order_items` WHERE `done_at` IS NOT NULL")->fetchColumn();
    if ($doneCount < 10) {
        // Cập nhật cho các món đã served hoặc done có thời gian cooking_at và done_at hợp lý
        $pdo->exec("
            UPDATE `order_items`
            SET `cooking_at` = DATE_ADD(COALESCE(`sent_at`, `created_at`), INTERVAL 2 MINUTE),
                `done_at`    = DATE_ADD(COALESCE(`sent_at`, `created_at`), INTERVAL 12 MINUTE)
            WHERE `status` IN ('done', 'served') AND `done_at` IS NULL
        ");

        // Tạo thêm vài dòng order_items lịch sử hoàn thành để kiểm tra biểu đồ và cảnh báo món trễ
        $sampleOrder = $pdo->query("SELECT id FROM orders LIMIT 1")->fetchColumn();
        if ($sampleOrder) {
            $seedItems = [
                ['Lẩu nấm gà tiềm', 320000, 1, 'served', -28, 22], // Món lâu (trễ 22 phút)
                ['Bò nướng tảng sốt tiêu', 280000, 2, 'served', -45, 26], // Món trễ 26 phút
                ['Cơm gà Hải Nam', 65000, 1, 'served', -60, 9], // Món nhanh 9 phút
                ['Salad trộn đặc biệt', 55000, 1, 'served', -75, 6], // Món nhanh 6 phút
                ['Mì xào bò', 85000, 2, 'served', -90, 11], // Món chuẩn 11 phút
                ['Lẩu nấm gà tiềm', 320000, 1, 'served', -120, 25], // Món lâu (trễ 25 phút)
                ['Trà đào cam sả', 35000, 3, 'served', -130, 4], // Nước 4 phút
                ['Bún chả Hà Nội đặc biệt', 75000, 1, 'served', -180, 14], // Chuẩn 14 phút
                ['Bò lúc lắc khoai tây', 165000, 1, 'served', -240, 18], // Trễ 18 phút
                ['Cơm rang dưa bò', 70000, 2, 'served', -300, 10], // Chuẩn 10 phút
            ];

            $insertStmt = $pdo->prepare("
                INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `status`, `sent_at`, `cooking_at`, `done_at`, `created_at`)
                VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), DATE_ADD(NOW(), INTERVAL ? MINUTE), DATE_ADD(NOW(), INTERVAL ? MINUTE), DATE_ADD(NOW(), INTERVAL ? MINUTE))
            ");

            foreach ($seedItems as $s) {
                $sentOffset = $s[4];
                $cookOffset = $sentOffset + 2;
                $doneOffset = $sentOffset + $s[5];
                $insertStmt->execute([$sampleOrder, $s[0], $s[1], $s[2], $s[3], $sentOffset, $cookOffset, $doneOffset, $sentOffset]);
            }
            echo "  [OK] Đã bổ sung dữ liệu lịch sử nấu món cho Kitchen Metrics.\n";
        }
    }

    echo "\n>>> HOÀN TẤT MIGRATION DATABASE THÀNH CÔNG! <<<\n";
}

runMigration();
