<?php
declare(strict_types=1);

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| CẤU HÌNH CHUNG
|--------------------------------------------------------------------------
*/

$allowedPages = [
    'bills',
    'staff',
    'table-order',
    'kitchen',
    'admin',
    'users',
    'dishes',
    'tables',
    'login',
    'menu',
    'cart',
    'tracking',
    'reservation',
    'payment',
    'profile',
    'vouchers',
    'reservations'
];

$pageTitles = [
    'login'        => 'Đăng nhập hệ thống — SmartDine',
    'admin'        => 'Tổng quan Quản trị — SmartDine',
    'users'        => 'Quản lý tài khoản nhân viên — SmartDine',
    'dishes'       => 'Quản lý Thực đơn & Món ăn — SmartDine',
    'tables'       => 'Quản lý Bàn & Khu vực — SmartDine',
    'staff'        => 'Sơ đồ bàn & Vận hành — SmartDine',
    'table-order'  => 'Chi tiết đơn hàng bàn — SmartDine',
    'kitchen'      => 'Màn hình bếp KDS — SmartDine',
    'bills'        => 'Lịch sử bill — SmartDine',
    'menu'         => 'Thực đơn món ăn — SmartDine',
    'cart'         => 'Giỏ hàng & Đặt món — SmartDine',
    'tracking'     => 'Theo dõi món ăn — SmartDine',
    'reservation'  => 'Đặt bàn trước — SmartDine',
    'payment'      => 'Thanh toán đơn hàng — SmartDine',
    'profile'      => 'Hồ sơ cá nhân — SmartDine',
    'vouchers'     => 'Quản lý Mã giảm giá — SmartDine',
    'reservations' => 'Quản lý đặt bàn — SmartDine'
];

/*
|--------------------------------------------------------------------------
| HÀM HỖ TRỢ
|--------------------------------------------------------------------------
*/

if (!function_exists('assetUrl')) {
    function assetUrl(string $path): string
    {
        $file = __DIR__ . '/' . ltrim($path, '/');

        return $path . (
            is_file($file)
                ? '?v=' . filemtime($file)
                : ''
        );
    }
}

if (!function_exists('active')) {
    function active(string $name, string $page): string
    {
        return $name === $page ? 'active' : '';
    }
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| ĐĂNG XUẤT
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['action']) &&
    $_GET['action'] === 'logout'
) {
    logoutUser();

    header('Location: index.php?page=login&logged_out=1');
    exit;
}

/*
|--------------------------------------------------------------------------
| XÁC ĐỊNH TRANG
|--------------------------------------------------------------------------
*/

$page = isset($_GET['page'])
    ? trim((string) $_GET['page'])
    : '';

if (!in_array($page, $allowedPages, true)) {
    $page = isLoggedIn()
        ? getDefaultPageForRole(currentRole())
        : 'login';
}

/*
|--------------------------------------------------------------------------
| XỬ LÝ ĐĂNG NHẬP
|--------------------------------------------------------------------------
*/

$loginError = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['login_submit'])
) {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $loginError = 'Phiên đăng nhập không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $loginError = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
        } else {
            $result = loginUser($username, $password);

            if (!empty($result['success'])) {
                // Chống session fixation.
                session_regenerate_id(true);

                // Tạo CSRF token mới.
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Điều hướng theo vai trò.
                $role = currentRole();
                $defaultPage = getDefaultPageForRole($role);

                if (!in_array($defaultPage, $allowedPages, true)) {
                    $defaultPage = 'login';
                }

                header('Location: index.php?page=' . urlencode($defaultPage));
                exit;
            }

            $loginError = $result['message'] ?? 'Đăng nhập thất bại. Vui lòng thử lại.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| PHÂN QUYỀN RBAC
|--------------------------------------------------------------------------
*/

// Chỉ gọi sau khi đã xác định trang hợp lệ.
enforceAuthorization($page);

// Kiểm tra lại trang sau khi xử lý phân quyền.
if (!in_array($page, $allowedPages, true)) {
    $page = isLoggedIn()
        ? getDefaultPageForRole(currentRole())
        : 'login';
}

/*
|--------------------------------------------------------------------------
| XÁC ĐỊNH GIAO DIỆN
|--------------------------------------------------------------------------
*/

$currentTitle = $pageTitles[$page] ?? 'SmartDine Restaurant System';

$isDashboard = in_array(
    $page,
    [
        'staff',
        'table-order',
        'kitchen',
        'bills',
        'admin',
        'users',
        'payment',
        'dishes',
        'tables',
        'profile',
        'vouchers',
        'reservations'
    ],
    true
);

$bodyClass = $page === 'login'
    ? 'login-page-body'
    : ($isDashboard ? 'dashboard-body' : 'customer-body');

/*
|--------------------------------------------------------------------------
| DANH SÁCH FILE TRANG
|--------------------------------------------------------------------------
*/

$dashboardPages = [
    'staff'        => 'staff.php',
    'table-order'  => 'table-order.php',
    'kitchen'      => 'kitchen.php',
    'bills'        => 'bills.php',
    'admin'        => 'admin.php',
    'users'        => 'users.php',
    'payment'      => 'payment.php',
    'dishes'       => 'dishes.php',
    'tables'       => 'tables.php',
    'vouchers'     => 'vouchers.php',
    'reservations' => 'reservations.php',
    'profile'      => 'profile.php'
];

$customerPages = [
    'menu'        => 'menu.php',
    'cart'        => 'cart.php',
    'tracking'    => 'tracking.php',
    'reservation' => 'reservation.php'
];

/*
|--------------------------------------------------------------------------
| KIỂM TRA FILE TRANG
|--------------------------------------------------------------------------
*/

if ($page === 'login') {
    $pageFile = __DIR__ . '/pages/login.php';
} elseif ($isDashboard && isset($dashboardPages[$page])) {
    $pageFile = __DIR__ . '/pages/' . $dashboardPages[$page];
} elseif (isset($customerPages[$page])) {
    $pageFile = __DIR__ . '/pages/' . $customerPages[$page];
} else {
    $pageFile = '';
}

if ($pageFile !== '' && !is_file($pageFile)) {
    error_log('SmartDine: Không tìm thấy file trang: ' . $pageFile);

    http_response_code(500);

    exit('Lỗi hệ thống: Không tìm thấy nội dung trang.');
}

?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= htmlspecialchars($currentTitle, ENT_QUOTES, 'UTF-8') ?>
    </title>

    <!-- CSS CHÍNH -->
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(assetUrl('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>"
    >

    <!-- ADMIN -->
    <?php if ($page === 'admin'): ?>
        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/revenue.css'), ENT_QUOTES, 'UTF-8') ?>"
        >
    <?php endif; ?>

    <!-- SƠ ĐỒ BÀN -->
    <?php if (in_array($page, ['staff', 'tables'], true)): ?>
        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/visual-floor.css'), ENT_QUOTES, 'UTF-8') ?>"
        >
    <?php endif; ?>

    <!-- THANH TOÁN -->
    <?php if ($page === 'payment'): ?>

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/payment.css'), ENT_QUOTES, 'UTF-8') ?>"
        >

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/receipt.css'), ENT_QUOTES, 'UTF-8') ?>"
        >

        <script
            defer
            src="<?= htmlspecialchars(assetUrl('assets/js/vendor/qrcode.js'), ENT_QUOTES, 'UTF-8') ?>"
        ></script>

        <script
            defer
            src="<?= htmlspecialchars(assetUrl('assets/js/payment.js'), ENT_QUOTES, 'UTF-8') ?>"
        ></script>

    <?php endif; ?>

    <!-- BẾP KDS -->
    <?php if ($page === 'kitchen'): ?>

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/kitchen.css'), ENT_QUOTES, 'UTF-8') ?>"
        >

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/orders.css'), ENT_QUOTES, 'UTF-8') ?>"
        >

        <script
            defer
            src="<?= htmlspecialchars(assetUrl('assets/js/kitchen.js'), ENT_QUOTES, 'UTF-8') ?>"
        ></script>

    <?php endif; ?>

    <!-- LỊCH SỬ BILL -->
    <?php if ($page === 'bills'): ?>

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/orders.css'), ENT_QUOTES, 'UTF-8') ?>"
        >

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/bills.css'), ENT_QUOTES, 'UTF-8') ?>"
        >

        <script
            defer
            src="<?= htmlspecialchars(assetUrl('assets/js/bills.js'), ENT_QUOTES, 'UTF-8') ?>"
        ></script>

    <?php endif; ?>

    <!-- CHI TIẾT ĐƠN HÀNG -->
    <?php if ($page === 'table-order'): ?>

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/receipt.css'), ENT_QUOTES, 'UTF-8') ?>"
        >

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/orders.css'), ENT_QUOTES, 'UTF-8') ?>"
        >

        <script
            defer
            src="<?= htmlspecialchars(assetUrl('assets/js/order-items.js'), ENT_QUOTES, 'UTF-8') ?>"
        ></script>

    <?php endif; ?>

    <!-- THAO TÁC BÀN -->
    <?php if (in_array($page, ['staff', 'table-order'], true)): ?>

        <script
            defer
            src="<?= htmlspecialchars(assetUrl('assets/js/table-actions.js'), ENT_QUOTES, 'UTF-8') ?>"
        ></script>

    <?php endif; ?>

    <!-- GIAO DIỆN KHÁCH HÀNG -->
    <?php if (!$isDashboard && $page !== 'login'): ?>

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars(assetUrl('assets/css/ai-ordering.css'), ENT_QUOTES, 'UTF-8') ?>"
        >

    <?php endif; ?>

    <!-- JAVASCRIPT CHUNG -->
    <script
        defer
        src="<?= htmlspecialchars(assetUrl('assets/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"
    ></script>

</head>

<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">

<?php if ($page === 'login'): ?>

    <?php include $pageFile; ?>

<?php elseif ($isDashboard): ?>

    <!-- SIDEBAR -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="dashboard-main">

        <!-- THÔNG BÁO LỖI -->
        <?php if (!empty($_SESSION['flash_error'])): ?>

            <div class="flash-alert flash-error">

                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>

                <span>
                    <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
                </span>

            </div>

            <?php unset($_SESSION['flash_error']); ?>

        <?php endif; ?>

        <!-- THÔNG BÁO THÀNH CÔNG -->
        <?php if (!empty($_SESSION['flash_success'])): ?>

            <div class="flash-alert flash-success">

                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>

                <span>
                    <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
                </span>

            </div>

            <?php unset($_SESSION['flash_success']); ?>

        <?php endif; ?>

        <!-- NỘI DUNG TRANG -->
        <?php include $pageFile; ?>

    </main>

<?php else: ?>

    <!-- THANH ĐIỀU HƯỚNG KHÁCH HÀNG -->
    <?php include __DIR__ . '/includes/customer-nav.php'; ?>

    <main class="customer-main">

        <?php include $pageFile; ?>

    </main>

    <!-- AI ORDERING WIDGET -->
    <?php include __DIR__ . '/includes/customer-ai-widget.php'; ?>

<?php endif; ?>

</body>
</html>
