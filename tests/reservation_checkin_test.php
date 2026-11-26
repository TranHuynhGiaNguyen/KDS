<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tables.php';

if (!function_exists('active')) {
    function active($target, $current): string {
        return $target === $current ? 'active' : '';
    }
}

echo "=== KIỂM THỬ XEM ĐẶT BÀN CHO STAFF & TÍNH NĂNG ĐÃ VÀO BÀN ===\n";

assert(canRoleAccessPage('staff', 'reservations') === true, 'Staff phải có quyền truy cập trang reservations');
assert(canRoleAccessPage('admin', 'reservations') === true, 'Admin phải có quyền truy cập trang reservations');
assert(canRoleAccessPage('kitchen', 'reservations') === false, 'Kitchen không được truy cập reservations');
echo "  [PASS] Phân quyền RBAC cho Staff & Admin hoạt động chính xác\n";

$pdo = getDbConnection();
assert($pdo instanceof PDO, 'Cần kết nối MySQL');

ensurePublicReservationsSchema($pdo);
ensureTableAssignmentsSchema($pdo);

$colStatus = $pdo->query("SHOW COLUMNS FROM `public_reservations` LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
assert($colStatus && stripos((string) ($colStatus['Type'] ?? ''), "'seated'") !== false, "ENUM status phải có 'seated'");
$colSeatedAt = $pdo->query("SHOW COLUMNS FROM `public_reservations` LIKE 'seated_at'")->fetch(PDO::FETCH_ASSOC);
assert($colSeatedAt !== false, "Cột seated_at phải tồn tại");
echo "  [PASS] Schema public_reservations hỗ trợ trạng thái 'seated' và 'seated_at'\n";

$allActive = getAllDiningTables('', 'active', '');
$emptyTables = array_values(array_filter($allActive, static fn(array $t): bool => empty($t['is_occupied'])));
assert(count($emptyTables) >= 1, 'Cần ít nhất 1 bàn trống active để kiểm thử');
$targetTable = $emptyTables[0];

$createdReservationId = 0;

try {
    $stmtIns = $pdo->prepare("
        INSERT INTO `public_reservations`
            (`customer_name`, `phone`, `email`, `guests`, `reservation_date`, `reservation_time`, `duration_minutes`, `area_id`, `table_id`, `note`, `status`)
        VALUES
            (:name, :phone, :email, :guests, :rdate, :rtime, 120, :area_id, :table_id, :note, 'confirmed')
    ");
    $stmtIns->execute([
        ':name'     => 'Khách Test Checkin',
        ':phone'    => '0909998877',
        ':email'    => 'checkin@test.local',
        ':guests'   => 3,
        ':rdate'    => date('Y-m-d'),
        ':rtime'    => '19:00:00',
        ':area_id'  => (int) $targetTable['area_id'],
        ':table_id' => (int) $targetTable['id'],
        ':note'     => 'Ngồi gần cửa sổ',
    ]);
    $createdReservationId = (int) $pdo->lastInsertId();
    assert($createdReservationId > 0, 'Tạo lượt đặt bàn test thất bại');

    $seatRes = seatPublicReservation($pdo, $createdReservationId, 1);
    assert(!empty($seatRes['success']), 'seatPublicReservation phải thành công: ' . ($seatRes['message'] ?? ''));

    $stmtCheck = $pdo->prepare("SELECT `status`, `seated_at` FROM `public_reservations` WHERE `id` = ?");
    $stmtCheck->execute([$createdReservationId]);
    $resRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    assert(($resRow['status'] ?? '') === 'seated', "Trạng thái sau check-in phải là 'seated'");
    assert(!empty($resRow['seated_at']), 'Thời điểm seated_at phải được ghi nhận');

    $assignRow = getActiveTableAssignment($targetTable['code'], $pdo);
    assert($assignRow !== null, 'Bàn phải được tự động gán trên Sơ đồ bàn khi khách vào bàn');
    assert((int) $assignRow['guest_count'] === 3, 'Số khách trên table_assignments phải khớp');
    assert($assignRow['customer_name'] === 'Khách Test Checkin', 'Tên khách phải đồng bộ sang table_assignments');
    assert(strpos((string) $assignRow['note'], 'SD') !== false, 'Ghi chú gán bàn phải có mã đặt bàn');
    echo "  [PASS] Đã vào bàn thành công và đồng bộ tự động lên Sơ đồ bàn ({$targetTable['code']})\n";

    $reSeat = seatPublicReservation($pdo, $createdReservationId, 1);
    assert(empty($reSeat['success']), 'Không được cho phép vào bàn lại lượt đã seated');
    echo "  [PASS] Chặn xác nhận vào bàn trùng lặp\n";

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['user_id']  = 2;
    $_SESSION['username'] = 'staff_test';
    $_SESSION['role']     = 'staff';
    $_SESSION['fullname'] = 'Nhân viên Phục vụ';

    $page = 'reservations';
    ob_start();
    include __DIR__ . '/../includes/sidebar.php';
    $sidebarHtml = ob_get_clean();
    assert(strpos($sidebarHtml, 'index.php?page=reservations') !== false, 'Sidebar của Staff phải hiển thị mục Quản lý đặt bàn');

    ob_start();
    include __DIR__ . '/../pages/staff.php';
    $staffHtml = ob_get_clean();
    assert(strpos($staffHtml, 'index.php?page=reservations') !== false, 'Trang staff.php phải có nút Khách đặt bàn');

    ob_start();
    include __DIR__ . '/../pages/reservations.php';
    $resHtml = ob_get_clean();
    assert(strpos($resHtml, 'Khách Test Checkin') !== false, 'Staff phải xem được khách đã đặt bàn');
    assert(strpos($resHtml, 'Đã vào bàn') !== false, 'Trang phải hiển thị trạng thái/nút Đã vào bàn');
    assert(strpos($resHtml, 'Mở bàn ' . $targetTable['code']) !== false, 'Lượt đã vào bàn phải có nút Mở bàn');
    echo "  [PASS] Giao diện Sidebar, Sơ đồ bàn & Quản lý đặt bàn hoạt động đúng với role Staff\n";
} finally {
    if ($createdReservationId > 0) {
        $pdo->prepare("DELETE FROM `public_reservations` WHERE `id` = ?")->execute([$createdReservationId]);
    }
    releaseEmptyTableAssignment($pdo, $targetTable['code']);
}

echo "\n>>> TẤT CẢ KIỂM THỬ XEM ĐẶT BÀN & ĐÃ VÀO BÀN ĐÃ ĐẠT 100%! <<<\n";
