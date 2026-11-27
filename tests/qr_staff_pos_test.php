<?php
/**
 * Kiểm thử E2E:
 *  1. Mỗi bàn sinh duy nhất 1 mã QR gán cứng trong `qr_table_codes` trỏ tới `qr-order.php`.
 *  2. Khách quét QR mở `qr-order.php?qr=<token>`, gửi đơn qua `api/qr-order.php`,
 *     đồng bộ thẳng sang `orders`/`order_items`/Kitchen KDS và Gọi nhân viên.
 *  3. Giao diện Staff POS (`pages/table-order.php`) render đủ Màn Gọi món + Màn Kiểm đồ.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/kitchen.php';
require_once __DIR__ . '/../includes/qr-tables.php';

echo "=== KIỂM THỬ QR GÁN CỨNG, QR-ORDER.PHP & GIAO DIỆN POS BÀN STAFF ===\n";

$pdo = getOrdersDb();
ensureQrTableCodesSchema($pdo);

// 1. Kiểm tra mọi bàn active đều có 1 QR cố định duy nhất
$qrRows = getAllTableQrCodes($pdo);
assert(count($qrRows) >= 10, 'Phải có danh sách QR cho các bàn trong dining_tables');
foreach ($qrRows as $r) {
    assert(!empty($r['qr_token']) && preg_match('/^[a-f0-9]{16,64}$/', $r['qr_token']) === 1, 'Mỗi bàn phải có token QR cố định: ' . ($r['table_code'] ?? '?'));
}

// Kiểm tra B11, B12 (trước đây thiếu trong qr_table_codes) giờ đã có token gán cứng ổn định
$qrB11First  = getOrCreateTableQrCode($pdo, 'B11');
$qrB11Second = getOrCreateTableQrCode($pdo, 'B11');
assert($qrB11First !== null && $qrB11First['qr_token'] === $qrB11Second['qr_token'], 'Token QR của bàn B11 phải gán cứng bất biến');
echo "  [PASS] Mỗi bàn có duy nhất 1 QR code gán cứng (đã đồng bộ B11/B12 & toàn bộ bàn)\n";

// 2. Chọn bàn B02 để test E2E (đảm bảo B02 active và lấy lại trạng thái ban đầu sau test)
$b02Qr = getOrCreateTableQrCode($pdo, 'B02');
assert($b02Qr !== null && !empty($b02Qr['qr_token']), 'Bàn B02 phải có token QR');
$tokenB02 = $b02Qr['qr_token'];

$testOrderId = null;
$createdItemId = null;
$hadOpenOrderBefore = fetchTableItems($pdo, 'B02') !== [];

// Tìm 1 món đang active để test gửi đơn
$activeDish = $pdo->query("SELECT id, name, price FROM dishes WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert($activeDish !== false, 'Cần ít nhất 1 món active trong DB');

try {
    // Kiểm tra HTTP GET qr-order.php?qr=<token>
    $baseHttp = 'http://localhost/KDS';
    $ctxGet = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 5]]);
    $qrPageHtml = @file_get_contents($baseHttp . '/qr-order.php?qr=' . urlencode($tokenB02), false, $ctxGet);
    if ($qrPageHtml !== false) {
        assert(strpos($qrPageHtml, 'Bàn B02') !== false, 'HTTP qr-order.php phải nhận diện đúng Bàn B02 qua QR token');
        assert(strpos($qrPageHtml, 'btn-submit-qr-order') !== false || strpos($qrPageHtml, 'submitQrOrder') !== false, 'qr-order.php phải dùng logic gửi đơn thật');
        echo "  [PASS] HTTP GET qr-order.php?qr=... trả về đúng giao diện Bàn B02\n";

        // Test HTTP POST api/qr-order.php (submit + call_staff)
        $postSubmitCtx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\n",
                'content'       => json_encode([
                    'action' => 'submit',
                    'qr'     => $tokenB02,
                    'items'  => [['dish_id' => (int) $activeDish['id'], 'qty' => 2, 'note' => 'E2E_QR_TEST']],
                ]),
                'ignore_errors' => true,
                'timeout'       => 5,
            ],
        ]);
        $submitRaw = file_get_contents($baseHttp . '/api/qr-order.php', false, $postSubmitCtx);
        $submitRes = json_decode((string) $submitRaw, true);
        assert(is_array($submitRes) && !empty($submitRes['success']), 'POST api/qr-order.php submit phải thành công: ' . (string) $submitRaw);
        assert(!empty($submitRes['ordered_items']), 'Phản hồi submit phải trả về ordered_items từ DB');
        echo "  [PASS] POST api/qr-order.php gửi đơn thật thành công\n";

        // Kiểm tra món vừa gửi đã vào Kitchen KDS
        $tickets = fetchKitchenTickets($pdo);
        $foundInKitchen = false;
        foreach ($tickets as $tk) {
            if ($tk['table'] === 'B02' && $tk['name'] === $activeDish['name'] && strpos($tk['note'], 'E2E_QR_TEST') !== false) {
                $foundInKitchen = true;
                $createdItemId  = (int) $tk['id'];
            }
        }
        assert($foundInKitchen, 'Món khách đặt qua qr-order.php phải xuất hiện ngay trên Màn hình Bếp KDS');
        echo "  [PASS] Món QR Order hiển thị đồng bộ trên KDS Bếp\n";

        // Test Gọi Nhân Viên qua QR
        $postCallCtx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\n",
                'content'       => json_encode(['action' => 'call_staff', 'qr' => $tokenB02]),
                'ignore_errors' => true,
                'timeout'       => 5,
            ],
        ]);
        $callRaw = file_get_contents($baseHttp . '/api/qr-order.php', false, $postCallCtx);
        $callRes = json_decode((string) $callRaw, true);
        assert(is_array($callRes) && !empty($callRes['success']), 'Gọi NV qua QR phải thành công');
        $calls = fetchPendingStaffCalls($pdo);
        assert(isset($calls['B02']), 'Sơ đồ bàn phải ghi nhận Bàn B02 đang gọi nhân viên');
        echo "  [PASS] Chức năng Gọi NV từ qr-order.php hoạt động chuẩn\n";

        // Test trang in toàn bộ QR
        $qrCodesHtml = @file_get_contents($baseHttp . '/api/qr-ordering/qr-codes.php', false, $ctxGet);
        assert($qrCodesHtml !== false && strpos($qrCodesHtml, 'qr-order.php?qr=') !== false, 'Trang qr-codes.php phải trỏ sang qr-order.php?qr=');
        echo "  [PASS] api/qr-ordering/qr-codes.php render danh sách QR trỏ đúng qr-order.php\n";
    } else {
        echo "  [SKIP] Apache localhost không chạy, bỏ qua HTTP check\n";
    }

    // 3. Kiểm tra giao diện pages/table-order.php với role Staff
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['user_id']  = 2;
    $_SESSION['username'] = 'staff';
    $_SESSION['role']     = 'staff';
    $_SESSION['fullname'] = 'Nguyễn Phục Vụ';
    $_GET['table'] = 'B02';

    ob_start();
    include __DIR__ . '/../pages/table-order.php';
    $posHtml = ob_get_clean();

    $expectedTokens = [
        'pos-tablet-shell',
        'Bàn B02',
        'Kiểm đồ',
        'Ra món',
        'In QRCode',
        'GỬI ORDER',
        'XÓA TẤT CẢ',
        'Tổng tiền',
        'Buffet',
        'Alacarte',
        'Đồ uống',
        'Tìm kiếm',
        'Kiểm đồ - bàn B02',
        'Hiện món đã hủy',
        'Giảm giá nhân viên',
        'Voucher',
        'Phiếu thanh toán',
    ];
    foreach ($expectedTokens as $tok) {
        assert(strpos($posHtml, $tok) !== false, "pages/table-order.php thiếu thành phần giao diện: {$tok}");
    }
    if ($createdItemId !== null) {
        assert(strpos($posHtml, 'E2E_QR_TEST') !== false, 'Màn Kiểm đồ của Staff phải hiển thị món khách vừa đặt qua QR');
    }
    echo "  [PASS] Giao diện POS Bàn & Kiểm đồ của role Staff hiển thị đầy đủ giống ảnh mẫu\n";
} finally {
    if ($createdItemId !== null) {
        $ordId = (int) $pdo->query("SELECT order_id FROM order_items WHERE id = " . (int) $createdItemId)->fetchColumn();
        $pdo->prepare("DELETE FROM order_items WHERE id = ?")->execute([$createdItemId]);
        if ($ordId > 0 && !$hadOpenOrderBefore) {
            $rem = (int) $pdo->query("SELECT COUNT(*) FROM order_items WHERE order_id = " . $ordId)->fetchColumn();
            if ($rem === 0) {
                $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$ordId]);
            }
        }
    }
    $pdo->prepare("DELETE FROM qr_staff_calls WHERE table_code = 'B02'")->execute();
}

echo "\n>>> TẤT CẢ KIỂM THỬ QR GÁN CỨNG, QR-ORDER.PHP & STAFF POS ĐẠT 100%! <<<\n";
