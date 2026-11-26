<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/tables.php';

echo "=== KIỂM THỬ ROLE QUẢN LÝ CA (SHIFT_MANAGER) ===\n";

$pdo = getDbConnection();
assert($pdo instanceof PDO, 'Cần kết nối MySQL');

// 1. Kiểm tra schema ENUM & tài khoản mẫu shift_manager
$colRole = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
assert($colRole && stripos((string) ($colRole['Type'] ?? ''), "'shift_manager'") !== false, "users.role ENUM phải chứa 'shift_manager'");

$smUser = getUserByUsername('shift_manager');
assert($smUser !== null && ($smUser['role'] ?? '') === 'shift_manager', 'Tài khoản mẫu shift_manager phải tồn tại');
echo "  [PASS] Schema users.role & tài khoản shift_manager đã sẵn sàng\n";

// 2. Kiểm tra RBAC: tất cả chức năng TRỪ users và vouchers
$allowedForShift = [
    'admin', 'dishes', 'tables', 'staff', 'table-order',
    'kitchen', 'bills', 'reservations', 'payment', 'profile',
    'menu', 'cart', 'tracking', 'reservation',
];
foreach ($allowedForShift as $p) {
    assert(canRoleAccessPage('shift_manager', $p) === true, "Quản lý ca phải truy cập được trang $p");
}
assert(canRoleAccessPage('shift_manager', 'users') === false, 'Quản lý ca KHÔNG được truy cập quản lý nhân viên (users)');
assert(canRoleAccessPage('shift_manager', 'vouchers') === false, 'Quản lý ca KHÔNG được truy cập mã khuyến mãi (vouchers)');
echo "  [PASS] Ma trận phân quyền Quản lý ca chính xác (được tất cả trừ users & vouchers)\n";

// 3. Kiểm tra quyền gọi/hủy món
assert(canRoleDoOrderAction('shift_manager', 'add') === true, 'Quản lý ca được thêm món');
assert(canRoleDoOrderAction('shift_manager', 'cancel') === true, 'Quản lý ca được hủy món');
assert(getRoleName('shift_manager') === 'Quản lý ca', 'Tên vai trò phải là Quản lý ca');
assert(getDefaultPageForRole('shift_manager') === 'admin', 'Trang mặc định Quản lý ca phải là admin');
echo "  [PASS] Quyền thao tác món & thông tin vai trò chính xác\n";

// 4. Giả lập đăng nhập shift_manager để kiểm tra giao diện & chặn trang bị cấm
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['user_id']  = (int) $smUser['id'];
$_SESSION['username'] = $smUser['username'];
$_SESSION['role']     = 'shift_manager';
$_SESSION['fullname'] = $smUser['fullname'];

assert(isShiftManager() === true && isAdmin() === false && isManagerOrAdmin() === true, 'Helper role shift_manager không khớp');

if (!function_exists('active')) {
    function active($a, $b) { return $a === $b ? 'active' : ''; }
}

$page = 'admin';
ob_start();
include __DIR__ . '/../includes/sidebar.php';
$sidebarHtml = ob_get_clean();
assert(strpos($sidebarHtml, 'index.php?page=admin') !== false, 'Sidebar Quản lý ca phải có Tổng quan');
assert(strpos($sidebarHtml, 'index.php?page=dishes') !== false, 'Sidebar Quản lý ca phải có Thực đơn');
assert(strpos($sidebarHtml, 'index.php?page=tables') !== false, 'Sidebar Quản lý ca phải có Bàn & khu vực');
assert(strpos($sidebarHtml, 'index.php?page=kitchen') !== false, 'Sidebar Quản lý ca phải có Màn hình bếp');
assert(strpos($sidebarHtml, 'index.php?page=users') === false, 'Sidebar Quản lý ca KHÔNG được hiện Quản lý nhân viên');
assert(strpos($sidebarHtml, 'index.php?page=vouchers') === false, 'Sidebar Quản lý ca KHÔNG được hiện Mã khuyến mãi');

ob_start();
include __DIR__ . '/../pages/admin.php';
$adminHtml = ob_get_clean();
assert(strpos($adminHtml, 'index.php?page=users') === false, 'Header Tổng quan của Quản lý ca KHÔNG được hiện nút users');
assert(strpos($adminHtml, 'index.php?page=vouchers') === false, 'Header Tổng quan của Quản lý ca KHÔNG được hiện nút vouchers');
assert(strpos($adminHtml, 'index.php?page=dishes') !== false, 'Header Tổng quan của Quản lý ca phải có nút thực đơn');

ob_start();
include __DIR__ . '/../pages/users.php';
$usersDenied = ob_get_clean();
assert(strpos($usersDenied, 'không có quyền truy cập') !== false, 'pages/users.php phải chặn Quản lý ca');

ob_start();
include __DIR__ . '/../pages/vouchers.php';
$vouchersDenied = ob_get_clean();
assert(strpos($vouchersDenied, 'không có quyền truy cập') !== false, 'pages/vouchers.php phải chặn Quản lý ca');

$_GET['page'] = 'dishes';
$_GET['tab'] = 'dishes';
ob_start();
include __DIR__ . '/../pages/dishes.php';
$dishesOk = ob_get_clean();
assert(strpos($dishesOk, 'Quản lý Thực đơn') !== false, 'Quản lý ca phải vào được trang Quản lý thực đơn');

$_GET['page'] = 'tables';
$_GET['tab'] = 'tables';
ob_start();
include __DIR__ . '/../pages/tables.php';
$tablesOk = ob_get_clean();
assert(strpos($tablesOk, 'Danh sách Bàn ăn Nhà hàng') !== false, 'Quản lý ca phải vào được trang Quản lý bàn');

$_GET['page'] = 'reservations';
ob_start();
include __DIR__ . '/../pages/reservations.php';
$resOk = ob_get_clean();
assert(strpos($resOk, 'Quản lý đặt bàn') !== false, 'Quản lý ca phải vào được Quản lý đặt bàn');

echo "  [PASS] Toàn bộ giao diện & chốt chặn Quản lý ca hoạt động chính xác 100%\n";
echo "\n>>> TẤT CẢ KIỂM THỬ ROLE QUẢN LÝ CA ĐÃ ĐẠT 100%! <<<\n";
