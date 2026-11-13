<?php
/**
 * SmartDine - Kiểm thử tự động Module Báo cáo & Thống kê Doanh thu
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/revenue.php';
require_once __DIR__ . '/../includes/checkout.php';

echo "=== KIỂM THỬ MODULE DOANH THU (REVENUE DASHBOARD) ===\n";

$pdo = getRevenueDb();

function assertTest(bool $condition, string $message): void {
    if ($condition) {
        echo "  [PASS] $message\n";
    } else {
        echo "  [FAIL] $message\n";
        exit(1);
    }
}

// 1. Kiểm tra hàm dịch thứ tiếng Việt
assertTest(getVietnameseDayOfWeek('2026-11-02') === 'Thứ 2', 'Dịch thứ 2 chính xác');
assertTest(getVietnameseDayOfWeek('2026-11-08') === 'Chủ nhật', 'Dịch Chủ nhật chính xác');
assertTest(getVietnameseDayOfWeek('2026-11-07') === 'Thứ 7', 'Dịch Thứ 7 chính xác');

// 2. Kiểm thử khi chưa có hóa đơn nào (Database sạch 0 doanh thu)
$emptyDayReport = getRevenueByDate($pdo, date('Y-m-d'));
assertTest($emptyDayReport['total_invoices'] === 0, 'Số lượng hóa đơn bằng 0 khi CSDL sạch');
assertTest($emptyDayReport['total_revenue'] === 0, 'Tổng doanh thu bằng 0đ khi CSDL sạch');
assertTest($emptyDayReport['avg_order_value'] === 0, 'Không bị lỗi chia cho 0 khi 0 hóa đơn');
assertTest(is_array($emptyDayReport['hourly_data']) && count($emptyDayReport['hourly_data']) === 15, 'Biểu đồ giờ vẫn có đủ 15 khung giờ (08h - 22h)');
assertTest($emptyDayReport['payment_methods']['cash']['percent'] == 0, 'Tỷ lệ % tiền mặt bằng 0');
assertTest($emptyDayReport['payment_methods']['qr']['percent'] == 0, 'Tỷ lệ % QR bằng 0');

$emptyMonthReport = getRevenueByMonth($pdo, date('Y-m'));
assertTest($emptyMonthReport['total_invoices'] === 0, 'Doanh thu tháng bằng 0 hóa đơn khi CSDL sạch');
assertTest($emptyMonthReport['total_revenue'] === 0, 'Doanh thu tháng bằng 0đ khi CSDL sạch');
assertTest(count($emptyMonthReport['daily_data']) >= 28, 'Bảng ngày trong tháng vẫn sinh đủ số ngày');

// 3. Kiểm thử tích hợp: Tạo 1 hóa đơn thực tế cho bàn B03 và kiểm tra Dashboard phản ánh đúng 100%
try {
    $orderSql = $pdo->prepare('INSERT INTO `orders` (`table_code`, `status`, `created_at`) VALUES ("B03", "open", NOW())');
    $orderSql->execute();
    $testOrderId = (int) $pdo->lastInsertId();

    $itemSql = $pdo->prepare('INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `status`) VALUES (?, ?, ?, ?, "served")');
    $itemSql->execute([$testOrderId, 'Cơm gà Hải Nam', 75000, 2]); // 150.000đ
    $itemSql->execute([$testOrderId, 'Trà đào cam sả', 35000, 2]); // 70.000đ
    // Tạm tính: 220.000đ, Phí phục vụ 5%: 11.000đ, VAT 8%: 18.480đ, Tổng: 249.480đ

    $invId = checkoutTable($pdo, 'B03', ['type' => 'none', 'value' => 0], 'qr', 0, 1);
    assertTest($invId > 0, "Tạo hóa đơn thực tế thành công cho bàn B03 (Invoice ID: $invId)");

    $dayAfter = getRevenueByDate($pdo, date('Y-m-d'));
    assertTest($dayAfter['total_invoices'] === 1, 'Dashboard cập nhật ngay lập tức: có đúng 1 hóa đơn');
    assertTest($dayAfter['total_revenue'] === 249480, 'Doanh thu trên Dashboard (249.480đ) khớp chính xác 100% với bill bàn B03');
    assertTest($dayAfter['invoices_list'][0]['table_code'] === 'B03', 'Hóa đơn trên Dashboard ghi nhận chính xác bàn B03');
    assertTest($dayAfter['payment_methods']['qr']['total'] === 249480, 'Ghi nhận đúng phương thức chuyển khoản QR');
    assertTest(count($dayAfter['top_dishes']) === 2, 'Top món ăn phản ánh đúng 2 món trong bill bàn B03');
    assertTest($dayAfter['top_dishes'][0]['item_name'] === 'Cơm gà Hải Nam', 'Món bán chạy nhất là Cơm gà Hải Nam (150.000đ)');

    // Dọn dẹp sau khi kiểm thử để giữ CSDL hoàn toàn sạch
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE `payments`');
    $pdo->exec('TRUNCATE TABLE `invoice_items`');
    $pdo->exec('TRUNCATE TABLE `invoices`');
    $pdo->exec("DELETE FROM `order_items` WHERE `order_id` = $testOrderId");
    $pdo->exec("DELETE FROM `orders` WHERE `id` = $testOrderId");
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "  [PASS] Đã dọn dẹp giao dịch kiểm thử (CSDL hoàn toàn sạch)\n";
} catch (Exception $e) {
    echo "  [FAIL] Lỗi kiểm thử thanh toán bàn: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Kiểm tra lại trạng thái DB sau kiểm thử
$finalInvCount = (int) $pdo->query('SELECT COUNT(*) FROM `invoices`')->fetchColumn();
assertTest($finalInvCount === 0, 'CSDL hoàn toàn sạch (invoices = 0)');

echo "\n>>> TẤT CẢ CÁC BÀI KIỂM THỬ DOANH THU ĐỀU ĐẠT 100%! <<<\n";
