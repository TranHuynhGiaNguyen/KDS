<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Xử lý đăng xuất
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
    header('Location: index.php?page=login&logged_out=1');
    exit;
}

// Xử lý đăng nhập
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $result = loginUser($username, $password);
    if ($result['success']) {
        // Chuyển hướng thông minh theo vai trò
        $defaultPage = getDefaultPageForRole($_SESSION['role']);
        header('Location: index.php?page=' . $defaultPage);
        exit;
    } else {
        $loginError = $result['message'];
    }
}

$page = $_GET['page'] ?? '';

// Kiểm tra quyền hạn và điều hướng phân quyền (RBAC)
enforceAuthorization($page);

$allowed = ['bills', 'staff', 'table-order', 'kitchen', 'admin', 'users', 'dishes', 'tables', 'login', 'menu', 'cart', 'tracking', 'reservation', 'payment'];
if (!in_array($page, $allowed, true)) {
    $page = isLoggedIn() ? getDefaultPageForRole(currentRole()) : 'login';
}

if (!function_exists('assetUrl')) {
    function assetUrl(string $path): string {
        $file = __DIR__ . '/' . $path;

        return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
}

function active($name, $page) {
    return $name === $page ? 'active' : '';
}

$pageTitles = [
    'login'       => 'Đăng nhập hệ thống — SmartDine',
    'admin'       => 'Tổng quan Quản trị — SmartDine',
    'users'       => 'Quản lý tài khoản nhân viên — SmartDine',
    'dishes'      => 'Quản lý Thực đơn & Món ăn — SmartDine',
    'tables'      => 'Quản lý Bàn & Khu vực — SmartDine',
    'staff'       => 'Sơ đồ bàn & Vận hành — SmartDine',
    'table-order' => 'Chi tiết đơn hàng bàn — SmartDine',
    'kitchen'     => 'Màn hình bếp KDS — SmartDine',
    'bills'       => 'Lịch sử bill — SmartDine',
    'menu'        => 'Thực đơn món ăn — SmartDine',
    'cart'        => 'Giỏ hàng & Đặt món — SmartDine',
    'tracking'    => 'Theo dõi món ăn — SmartDine',
    'reservation' => 'Đặt bàn trước — SmartDine',
    'payment'     => 'Thanh toán đơn hàng — SmartDine',
];
$currentTitle = $pageTitles[$page] ?? 'SmartDine Restaurant System';
$isDashboard = in_array($page, ['staff', 'table-order', 'kitchen', 'bills', 'admin', 'users', 'payment', 'dishes', 'tables'], true);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($currentTitle) ?></title>
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
  <?php if (in_array($page, ['staff', 'tables'], true)): ?>
    <link rel="stylesheet" href="<?= assetUrl('assets/css/visual-floor.css') ?>">
  <?php endif; ?>
  <?php if ($page === 'payment'): ?>
    <link rel="stylesheet" href="<?= assetUrl('assets/css/payment.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/receipt.css') ?>">
    <script defer src="<?= assetUrl('assets/js/vendor/qrcode.js') ?>"></script>
    <script defer src="<?= assetUrl('assets/js/payment.js') ?>"></script>
  <?php endif; ?>
  <?php if ($page === 'kitchen'): ?>
    <link rel="stylesheet" href="<?= assetUrl('assets/css/kitchen.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/orders.css') ?>">
    <script defer src="<?= assetUrl('assets/js/kitchen.js') ?>"></script>
  <?php endif; ?>
  <?php if ($page === 'bills'): ?>
    <link rel="stylesheet" href="<?= assetUrl('assets/css/orders.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/bills.css') ?>">
    <script defer src="<?= assetUrl('assets/js/bills.js') ?>"></script>
  <?php endif; ?>
  <?php if ($page === 'table-order'): ?>
    <link rel="stylesheet" href="<?= assetUrl('assets/css/receipt.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/orders.css') ?>">
    <script defer src="<?= assetUrl('assets/js/order-items.js') ?>"></script>
  <?php endif; ?>
  <script defer src="assets/js/app.js"></script>
</head>
<body class="<?= $page === 'login' ? 'login-page-body' : ($isDashboard ? 'dashboard-body' : 'customer-body') ?>">

<?php if ($page === 'login'): ?>
  <?php include __DIR__ . '/pages/login.php'; ?>

<?php elseif ($isDashboard): ?>
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  
  <main class="dashboard-main">
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="flash-alert flash-error">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
      </div>
      <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
      <div class="flash-alert flash-success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
      </div>
      <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if ($page === 'staff') include __DIR__ . '/pages/staff.php'; ?>
    <?php if ($page === 'table-order') include __DIR__ . '/pages/table-order.php'; ?>
    <?php if ($page === 'kitchen') include __DIR__ . '/pages/kitchen.php'; ?>
    <?php if ($page === 'bills') include __DIR__ . '/pages/bills.php'; ?>
    <?php if ($page === 'admin') include __DIR__ . '/pages/admin.php'; ?>
    <?php if ($page === 'users') include __DIR__ . '/pages/users.php'; ?>
    <?php if ($page === 'payment') include __DIR__ . '/pages/payment.php'; ?>
    <?php if ($page === 'dishes') include __DIR__ . '/pages/dishes.php'; ?>
    <?php if ($page === 'tables') include __DIR__ . '/pages/tables.php'; ?>
  </main>

<?php else: ?>
  <?php include __DIR__ . '/includes/customer-nav.php'; ?>
  <main class="customer-main">
    <?php if ($page === 'menu') include __DIR__ . '/pages/menu.php'; ?>
    <?php if ($page === 'cart') include __DIR__ . '/pages/cart.php'; ?>
    <?php if ($page === 'tracking') include __DIR__ . '/pages/tracking.php'; ?>
    <?php if ($page === 'reservation') include __DIR__ . '/pages/reservation.php'; ?>
  </main>
<?php endif; ?>

</body>
</html>