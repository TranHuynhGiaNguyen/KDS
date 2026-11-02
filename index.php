<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Xử lý đăng xuất
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['staff_user']);
    unset($_SESSION['staff_name']);
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

// Xử lý đăng nhập (Tài khoản: 1, Mật khẩu: 1)
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username === '1' && $password === '1') {
        $_SESSION['staff_user'] = '1';
        $_SESSION['staff_name'] = 'Nhân viên #1';
        header('Location: index.php?page=staff');
        exit;
    } else {
        $loginError = 'Tài khoản hoặc mật khẩu không chính xác! (Tài khoản: 1, Mật khẩu: 1)';
    }
}

$isLoggedIn = !empty($_SESSION['staff_user']);
$page = $_GET['page'] ?? '';

// Nếu chưa đăng nhập: Bắt buộc vào trang đăng nhập để vào hệ thống / trang staff
if (!$isLoggedIn) {
    $page = 'login';
} else {
    // Khi đã đăng nhập, nếu vào trang chủ hoặc đăng nhập thì chuyển thẳng vào trang staff
    if (empty($page) || $page === 'login' || $page === 'menu') {
        $page = 'staff';
    }
}

$allowed = ['staff', 'table-order', 'kitchen', 'admin', 'login', 'menu', 'cart', 'tracking', 'reservation', 'payment'];
if (!in_array($page, $allowed, true)) {
    $page = $isLoggedIn ? 'staff' : 'login';
}

function active($name, $page) {
    return $name === $page ? 'active' : '';
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SmartDine — <?= $page === 'login' ? 'Đăng nhập hệ thống' : ($page === 'staff' ? 'Sơ đồ bàn & Vận hành' : 'Quản lý nhà hàng') ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script defer src="assets/js/app.js"></script>
</head>
<body class="<?= $page === 'login' ? 'login-page-body' : (in_array($page, ['staff', 'table-order', 'kitchen', 'admin']) ? 'dashboard-body' : 'customer-body') ?>">
<?php if ($page === 'login'): ?>
  <?php include __DIR__.'/pages/login.php'; ?>
<?php elseif (in_array($page, ['staff', 'table-order', 'kitchen', 'admin'])): ?>
  <?php include __DIR__.'/includes/sidebar.php'; ?>
  <main class="dashboard-main">
    <?php if ($page === 'staff') include __DIR__.'/pages/staff.php'; ?>
    <?php if ($page === 'table-order') include __DIR__.'/pages/table-order.php'; ?>
    <?php if ($page === 'kitchen') include __DIR__.'/pages/kitchen.php'; ?>
    <?php if ($page === 'admin') include __DIR__.'/pages/admin.php'; ?>
  </main>
<?php else: ?>
  <?php include __DIR__.'/includes/customer-nav.php'; ?>
  <main class="customer-main">
    <?php if ($page === 'menu') include __DIR__.'/pages/menu.php'; ?>
    <?php if ($page === 'cart') include __DIR__.'/pages/cart.php'; ?>
    <?php if ($page === 'tracking') include __DIR__.'/pages/tracking.php'; ?>
    <?php if ($page === 'reservation') include __DIR__.'/pages/reservation.php'; ?>
    <?php if ($page === 'payment') include __DIR__.'/pages/payment.php'; ?>
  </main>
<?php endif; ?>
</body>
</html>
