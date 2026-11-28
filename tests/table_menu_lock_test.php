<?php
/**
 * Kiểm thử E2E:
 *  1. Khách chọn menu qua QR -> khóa cứng menu bàn đến khi thanh toán xong.
 *  2. Chặn khách (hoặc QR API) đổi menu hoặc gọi món buffet 0đ khi đang khóa Alacarte.
 *  3. Vé Buffet hiển thị trên màn hình POS (Staff, Quản lý ca, Admin) và tính vào bill (không đẩy sang Bếp KDS).
 *  4. Chỉ Quản lý ca (`shift_manager`) hoặc Admin (`admin`) được quyền đổi vé (`api/table-menu.php`); `staff` bị chặn 403.
 *  5. Sau khi `checkoutTable()`, khóa menu được giải phóng để bàn mới chọn lại được.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/kitchen.php';
require_once __DIR__ . '/../includes/checkout.php';
require_once __DIR__ . '/../includes/qr-tables.php';
require_once __DIR__ . '/../includes/table-menu.php';

echo "=== KIỂM THỬ KHÓA MENU BÀN, VÉ BUFFET & PHÂN QUYỀN ĐỔI VÉ ===\n";

$pdo = getOrdersDb();
ensureMenuConfigSchema($pdo);
ensureQrTableCodesSchema($pdo);
ensureTableMenuLocksSchema($pdo);
ensureTableAssignmentsSchema($pdo);

$allActive = getAllDiningTables('', 'active', '');
$emptyTables = array_values(array_filter($allActive, static fn(array $t): bool => empty($t['is_occupied'])));
assert(count($emptyTables) >= 1, 'Cần ít nhất 1 bàn trống active để kiểm thử');
$tableCode = $emptyTables[0]['code'];
$qrRow     = getOrCreateTableQrCode($pdo, $tableCode);
assert($qrRow !== null && !empty($qrRow['qr_token']), 'Bàn test phải có QR token');
$qrToken   = $qrRow['qr_token'];

$pkg = $pdo->query("SELECT * FROM `buffet_packages` WHERE `status` = 'active' ORDER BY `id` ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert($pkg !== false, 'Cần ít nhất 1 gói Buffet active để kiểm thử');
$pkgId = (int) $pkg['id'];

$buffetDish   = $pdo->query("SELECT * FROM `dishes` WHERE `menu_type` = 'buffet' AND `status` = 'active' ORDER BY `id` ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$alacarteDish = $pdo->query("SELECT * FROM `dishes` WHERE `menu_type` = 'alacarte' AND `status` = 'active' ORDER BY `id` ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert($buffetDish !== false && $alacarteDish !== false, 'Cần đủ 1 món Buffet và 1 món Alacarte đang active');

$baseHttp = 'http://localhost/KDS';
$httpPostJson = static function (string $url, array $payload, string $cookie = '') {
    $headers = "Content-Type: application/json\r\n";
    if ($cookie !== '') {
        $headers .= "Cookie: {$cookie}\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method'          => 'POST',
        'header'          => $headers,
        'content'         => json_encode($payload, JSON_UNESCAPED_UNICODE),
        'ignore_errors'   => true,
        'follow_location' => 0,
        'timeout'         => 5,
    ]]);
    $body   = (string) @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $status = (int) $m[1];
        }
    }
    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
};

$httpPostForm = static function (string $url, array $fields, string $cookie = '') {
    $headers = "Content-Type: application/x-www-form-urlencoded\r\n";
    if ($cookie !== '') {
        $headers .= "Cookie: {$cookie}\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method'          => 'POST',
        'header'          => $headers,
        'content'         => http_build_query($fields),
        'ignore_errors'   => true,
        'follow_location' => 0,
        'timeout'         => 5,
    ]]);
    $body   = (string) @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $status = (int) $m[1];
        }
    }
    return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
};

$loginAs = static function (string $username, string $password) use ($baseHttp): string {
    $ctx = stream_context_create(['http' => [
        'method'          => 'POST',
        'header'          => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'         => http_build_query(['login_submit' => '1', 'username' => $username, 'password' => $password]),
        'ignore_errors'   => true,
        'follow_location' => 0,
        'timeout'         => 5,
    ]]);
    @file_get_contents($baseHttp . '/index.php?page=login', false, $ctx);
    $status = 0;
    $cookie = '';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $status = (int) $m[1];
        }
        if (stripos($h, 'Set-Cookie:') === 0 && preg_match('/Set-Cookie:\s*([^;]+)/i', $h, $m)) {
            $cookie = $m[1];
        }
    }
    return $status === 302 ? $cookie : '';
};

$cleanupTable = static function () use ($pdo, $tableCode): void {
    $invStmt = $pdo->prepare("SELECT `id` FROM `invoices` WHERE `table_code` = ? AND `id` > 0");
    // Chỉ dọn các đơn/khóa sinh ra trong test trên $tableCode (an toàn vì chọn bàn trống)
    $ordStmt = $pdo->prepare("SELECT `id` FROM `orders` WHERE `table_code` = ?");
    $ordStmt->execute([$tableCode]);
    $ordIds = array_map('intval', $ordStmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($ordIds as $oid) {
        $invId = (int) $pdo->query("SELECT `id` FROM `invoices` WHERE `order_id` = " . $oid)->fetchColumn();
        if ($invId > 0) {
            $pdo->prepare("DELETE FROM `service_ratings` WHERE `invoice_id` = ?")->execute([$invId]);
            $pdo->prepare("DELETE FROM `payments` WHERE `invoice_id` = ?")->execute([$invId]);
            $pdo->prepare("DELETE FROM `invoice_items` WHERE `invoice_id` = ?")->execute([$invId]);
            $pdo->prepare("DELETE FROM `invoices` WHERE `id` = ?")->execute([$invId]);
        }
        $pdo->prepare("DELETE FROM `payment_intents` WHERE `order_id` = ?")->execute([$oid]);
        $pdo->prepare("DELETE FROM `order_items` WHERE `order_id` = ?")->execute([$oid]);
        $pdo->prepare("DELETE FROM `orders` WHERE `id` = ?")->execute([$oid]);
    }
    $pdo->prepare("DELETE FROM `table_menu_locks` WHERE `table_code` = ?")->execute([$tableCode]);
    $pdo->prepare("DELETE FROM `table_assignments` WHERE `table_code` = ?")->execute([$tableCode]);
};

$cleanupTable();

try {
    // 1. Khách chọn gói Buffet qua API -> khóa cứng menu + tự thêm vé Buffet
    $lockRes = $httpPostJson($baseHttp . '/api/qr-order.php', [
        'action'            => 'select_menu',
        'qr'                => $qrToken,
        'menu_type'         => 'buffet',
        'buffet_package_id' => $pkgId,
        'ticket_qty'        => 2,
    ]);
    assert($lockRes['status'] === 200 && !empty($lockRes['json']['success']), 'Khách chọn gói Buffet phải thành công: ' . $lockRes['body']);
    assert(!empty($lockRes['json']['menu_lock']['locked']), 'Phản hồi phải trả về trạng thái locked=true');
    assert((int) $lockRes['json']['bill']['subtotal'] === ((int) $pkg['price_per_person'] * 2), 'Bill phải cộng đúng 2 vé Buffet');
    echo "  [PASS] Khách chọn Buffet -> khóa cứng menu bàn {$tableCode} và tính tiền 2 vé Buffet\n";

    // Kiểm tra vé Buffet KHÔNG xuất hiện trên Bếp KDS (trạng thái 'done')
    $kdsTickets = fetchKitchenTickets($pdo);
    foreach ($kdsTickets as $kt) {
        assert($kt['table'] !== $tableCode || stripos($kt['name'], 'Vé ') !== 0, 'Vé Buffet không được đẩy sang danh sách nấu KDS Bếp');
    }
    echo "  [PASS] Vé Buffet ghi nhận hóa đơn nhưng không làm phiền Bếp KDS\n";

    // 2. Khách thử chọn lại menu khác -> bị chặn 409
    $reselectRes = $httpPostJson($baseHttp . '/api/qr-order.php', [
        'action'    => 'select_menu',
        'qr'        => $qrToken,
        'menu_type' => 'alacarte',
    ]);
    assert($reselectRes['status'] === 409 && empty($reselectRes['json']['success']), 'Khách không được tự đổi menu sau khi đã khóa');
    echo "  [PASS] Chặn khách tự đổi menu sau khi bàn đã khóa (HTTP 409)\n";

    // 3. Staff thường không được quyền đổi vé (HTTP 403), nhưng thêm vé cùng gói thì được
    $staffCookie = $loginAs('staff', 'staff123');
    assert($staffCookie !== '', 'Đăng nhập tài khoản staff phải thành công');

    $staffChangeRes = $httpPostJson($baseHttp . '/api/table-menu.php', [
        'action'     => 'change_ticket',
        'table_code' => $tableCode,
        'menu_type'  => 'alacarte',
    ], $staffCookie);
    assert($staffChangeRes['status'] === 403, 'Staff thường phải bị chặn đổi vé (403): ' . $staffChangeRes['body']);
    echo "  [PASS] Role staff thường bị chặn quyền đổi vé (HTTP 403)\n";

    $staffAddSameTicket = $httpPostForm($baseHttp . '/api/order-item.php', [
        'action'     => 'add_buffet_ticket',
        'table'      => $tableCode,
        'package_id' => $pkgId,
        'qty'        => 1,
    ], $staffCookie);
    assert($staffAddSameTicket['status'] === 200 && !empty($staffAddSameTicket['json']['ok']), 'Staff được thêm vé cùng gói đang khóa: ' . $staffAddSameTicket['body']);
    echo "  [PASS] Staff thêm thêm vé cùng gói Buffet đang khóa thành công\n";

    // 4. Quản lý ca (shift_manager) & Admin ĐƯỢC phép đổi vé sang Alacarte
    $mgrCookie = '';
    foreach (['shift123', 'manager123', '123456', 'shift_manager'] as $pw) {
        $mgrCookie = $loginAs('shift_manager', $pw);
        if ($mgrCookie !== '') {
            break;
        }
    }
    if ($mgrCookie === '') {
        $mgrCookie = $loginAs('admin', 'admin123');
    }
    assert($mgrCookie !== '', 'Đăng nhập Quản lý ca / Admin phải thành công');

    $mgrChangeAlacarte = $httpPostJson($baseHttp . '/api/table-menu.php', [
        'action'     => 'change_ticket',
        'table_code' => $tableCode,
        'menu_type'  => 'alacarte',
    ], $mgrCookie);
    assert($mgrChangeAlacarte['status'] === 200 && !empty($mgrChangeAlacarte['json']['success']), 'Quản lý ca / Admin phải đổi được sang Alacarte: ' . $mgrChangeAlacarte['body']);
    assert(countActiveBuffetTicketQty($pdo, (int) fetchOpenOrderId($pdo, $tableCode)) === 0, 'Đổi sang Alacarte phải hủy toàn bộ vé Buffet cũ');
    echo "  [PASS] Quản lý ca / Admin đổi vé sang Menu Alacarte & tự hủy vé Buffet cũ\n";

    // 5. Khi bàn đang khóa Alacarte -> gọi món Buffet 0đ qua QR hoặc POS đều bị chặn
    $blockedBuffetQr = $httpPostJson($baseHttp . '/api/qr-order.php', [
        'action' => 'submit',
        'qr'     => $qrToken,
        'items'  => [['dish_id' => (int) $buffetDish['id'], 'qty' => 1]],
    ]);
    assert($blockedBuffetQr['status'] === 422 && empty($blockedBuffetQr['json']['success']), 'Không được gọi món Buffet 0đ khi bàn đang khóa Alacarte');
    echo "  [PASS] Khóa Alacarte chặn gọi món Buffet 0đ thành công\n";

    // 6. Quản lý ca / Admin đổi vé ngược lại sang Buffet -> cho phép gọi món & tính tiền vé mới -> thanh toán xong tự mở khóa
    $mgrChangeBuffet = $httpPostJson($baseHttp . '/api/table-menu.php', [
        'action'            => 'change_ticket',
        'table_code'        => $tableCode,
        'menu_type'         => 'buffet',
        'buffet_package_id' => $pkgId,
        'ticket_qty'        => 1,
    ], $mgrCookie);
    assert($mgrChangeBuffet['status'] === 200 && !empty($mgrChangeBuffet['json']['success']), 'Phải đổi lại sang gói Buffet thành công');

    // Gọi thêm 1 món trả tiền lẻ để có thể thanh toán
    addOrderItem($pdo, $tableCode, (int) $alacarteDish['id'], 1, 'Test lock', 1);
    $bill = calculateBill(fetchTableItems($pdo, $tableCode));
    $invId = checkoutTable($pdo, $tableCode, [], 'cash', (int) $bill['total'], 1);
    assert($invId > 0, 'Thanh toán bàn phải thành công');
    assert(getActiveTableMenuLock($pdo, $tableCode) === null, 'Sau khi thanh toán xong, khóa menu bàn phải được giải phóng');
    echo "  [PASS] Thanh toán bàn xong -> tự động mở khóa menu cho lượt khách mới\n";

    // 7. Kiểm tra UI POS hiển thị Vé Buffet cho Staff, Quản lý ca & Admin; chỉ Admin/Quản lý ca thấy nút Đổi vé
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_GET['table'] = $tableCode;

    $_SESSION['user_id'] = 2; $_SESSION['role'] = 'staff'; $_SESSION['fullname'] = 'Staff';
    ob_start();
    include __DIR__ . '/../pages/table-order.php';
    $staffHtml = ob_get_clean();
    assert(strpos($staffHtml, 'VÉ BUFFET') !== false, 'Màn hình Staff POS phải hiển thị mục Vé Buffet');
    assert(strpos($staffHtml, 'data-open-change-ticket') === false, 'Staff thường không được thấy nút Đổi vé');

    $_SESSION['user_id'] = 1; $_SESSION['role'] = 'shift_manager'; $_SESSION['fullname'] = 'Shift Manager';
    ob_start();
    include __DIR__ . '/../pages/table-order.php';
    $mgrHtml = ob_get_clean();
    assert(strpos($mgrHtml, 'VÉ BUFFET') !== false, 'Màn hình Quản lý ca POS phải hiển thị mục Vé Buffet');
    assert(strpos($mgrHtml, 'data-open-change-ticket') !== false, 'Quản lý ca phải thấy nút Đổi vé');
    echo "  [PASS] Giao diện POS hiển thị Vé Buffet & phân quyền nút Đổi vé chuẩn xác\n";
} finally {
    $cleanupTable();
}

echo "\n>>> TẤT CẢ KIỂM THỬ KHÓA MENU, VÉ BUFFET & PHÂN QUYỀN ĐỔI VÉ ĐẠT 100%! <<<\n";
