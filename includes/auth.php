
<?php
/**
 * SmartDine - Hệ thống Xác thực & Phân quyền theo Vai trò (RBAC)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| CẤU HÌNH VAI TRÒ
|--------------------------------------------------------------------------
*/

if (!defined('SMARTDINE_ROLES')) {
    define('SMARTDINE_ROLES', [
        'admin',
        'staff',
        'kitchen'
    ]);
}

/*
|--------------------------------------------------------------------------
| KIỂM TRA ĐĂNG NHẬP
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

/*
|--------------------------------------------------------------------------
| LẤY THÔNG TIN NGƯỜI DÙNG
|--------------------------------------------------------------------------
*/

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'fullname' => $_SESSION['fullname'] ?? '',
        'role'     => $_SESSION['role'] ?? 'staff',
        'email'    => $_SESSION['email'] ?? '',
        'phone'    => $_SESSION['phone'] ?? ''
    ];
}

/*
|--------------------------------------------------------------------------
| LẤY VAI TRÒ
|--------------------------------------------------------------------------
*/

function currentRole(): ?string
{
    return isLoggedIn()
        ? ($_SESSION['role'] ?? null)
        : null;
}

function isAdmin(): bool
{
    return currentRole() === 'admin';
}

function isStaff(): bool
{
    return currentRole() === 'staff';
}

function isKitchen(): bool
{
    return currentRole() === 'kitchen';
}

/*
|--------------------------------------------------------------------------
| TÊN VAI TRÒ
|--------------------------------------------------------------------------
*/

function getRoleName($role): string
{
    switch ($role) {
        case 'admin':
            return 'Quản trị viên';

        case 'kitchen':
            return 'Đầu bếp KDS';

        case 'staff':
            return 'Nhân viên phục vụ';

        default:
            return 'Không xác định';
    }
}

/*
|--------------------------------------------------------------------------
| BADGE VAI TRÒ
|--------------------------------------------------------------------------
*/

function getRoleBadgeHtml($role): string
{
    switch ($role) {
        case 'admin':
            return '<span class="role-badge role-admin"><i class="role-dot"></i> Quản trị viên</span>';

        case 'kitchen':
            return '<span class="role-badge role-kitchen"><i class="role-dot"></i> Bếp KDS</span>';

        case 'staff':
            return '<span class="role-badge role-staff"><i class="role-dot"></i> Phục vụ</span>';

        default:
            return '<span class="role-badge">Không xác định</span>';
    }
}

/*
|--------------------------------------------------------------------------
| BADGE TRẠNG THÁI
|--------------------------------------------------------------------------
*/

function getStatusBadgeHtml($status): string
{
    if ($status === 'active') {
        return '<span class="status-badge status-active"><span class="status-indicator"></span> Đang hoạt động</span>';
    }

    return '<span class="status-badge status-inactive"><span class="status-indicator"></span> Đã khóa</span>';
}

/*
|--------------------------------------------------------------------------
| XỬ LÝ ĐĂNG NHẬP
|--------------------------------------------------------------------------
*/

function loginUser($username, $password): array
{
    $username = trim((string) $username);
    $password = (string) $password;

    if ($username === '' || $password === '') {
        return [
            'success' => false,
            'message' => 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!'
        ];
    }

    // Lấy tài khoản từ database.
    $user = getUserByUsername($username);

    if (!$user) {
        return [
            'success' => false,
            'message' => 'Tài khoản hoặc mật khẩu không chính xác!'
        ];
    }

    // Kiểm tra trạng thái tài khoản.
    if (($user['status'] ?? '') !== 'active') {
        return [
            'success' => false,
            'message' => 'Tài khoản này đã bị khóa. Vui lòng liên hệ Quản trị viên!'
        ];
    }

    // Kiểm tra vai trò hợp lệ.
    $role = $user['role'] ?? '';

    if (!in_array($role, SMARTDINE_ROLES, true)) {
        return [
            'success' => false,
            'message' => 'Tài khoản chưa được phân quyền hợp lệ. Vui lòng liên hệ Quản trị viên!'
        ];
    }

    // Kiểm tra mật khẩu.
    $storedPassword = (string) ($user['password'] ?? '');

    $passwordMatched = false;

    if (
        $storedPassword !== '' &&
        password_verify($password, $storedPassword)
    ) {
        $passwordMatched = true;

        // Tự động nâng cấp thuật toán hash nếu cần.
        if (password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
            updateUserPassword($user['id'], $password);
        }

    } elseif (
        $storedPassword !== '' &&
        hash_equals($storedPassword, $password)
    ) {
        // Tương thích tài khoản cũ lưu mật khẩu dạng văn bản.
        $passwordMatched = true;

        // Nâng cấp sang password_hash.
        updateUserPassword($user['id'], $password);
    }

    if (!$passwordMatched) {
        return [
            'success' => false,
            'message' => 'Tài khoản hoặc mật khẩu không chính xác!'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ĐĂNG NHẬP THÀNH CÔNG
    |--------------------------------------------------------------------------
    */

    // Chỉ tái tạo session ID tại đây.
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['role'] = $role;
    $_SESSION['phone'] = $user['phone'] ?? '';
    $_SESSION['email'] = $user['email'] ?? '';

    // Tương thích với các trang cũ.
    $_SESSION['staff_user'] = $user['username'];
    $_SESSION['staff_name'] = $user['fullname'];

    // Cập nhật thời gian đăng nhập gần nhất.
    updateLastLogin($user['id']);

    return [
        'success' => true,
        'message' => 'Đăng nhập thành công!',
        'user' => $user
    ];
}

/*
|--------------------------------------------------------------------------
| ĐĂNG XUẤT
|--------------------------------------------------------------------------
*/

function logoutUser(): void
{
    // Xóa toàn bộ dữ liệu session.
    $_SESSION = [];

    // Xóa cookie session.
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]
        );
    }

    // Hủy session hiện tại.
    session_destroy();
}

/*
|--------------------------------------------------------------------------
| TRANG MẶC ĐỊNH THEO VAI TRÒ
|--------------------------------------------------------------------------
*/

function getDefaultPageForRole($role): string
{
    switch ($role) {
        case 'admin':
            return 'admin';

        case 'kitchen':
            return 'kitchen';

        case 'staff':
            return 'staff';

        default:
            return 'login';
    }
}

/*
|--------------------------------------------------------------------------
| DANH SÁCH QUYỀN TRUY CẬP
|--------------------------------------------------------------------------
*/

function getRolePermissions(): array
{
    return [

        /*
        |--------------------------------------------------------------------------
        | QUẢN TRỊ VIÊN
        |--------------------------------------------------------------------------
        */

        'admin' => [
            'admin',
            'users',
            'dishes',
            'tables',
            'staff',
            'table-order',
            'kitchen',
            'bills',
            'menu',
            'cart',
            'tracking',
            'reservation',
            'payment',
            'profile',
            'vouchers',
            'reservations'
        ],

        /*
        |--------------------------------------------------------------------------
        | NHÂN VIÊN PHỤC VỤ
        |--------------------------------------------------------------------------
        */

        'staff' => [
            'staff',
            'table-order',
            'bills',
            'menu',
            'cart',
            'tracking',
            'reservation',
            'payment',
            'profile'
        ],

        /*
        |--------------------------------------------------------------------------
        | NHÂN VIÊN BẾP
        |--------------------------------------------------------------------------
        */

        'kitchen' => [
            'kitchen',
            'bills',
            'menu',
            'profile'
        ]
    ];
}

/*
|--------------------------------------------------------------------------
| KIỂM TRA QUYỀN TRUY CẬP TRANG
|--------------------------------------------------------------------------
*/

function canRoleAccessPage($role, $page): bool
{
    $permissions = getRolePermissions();

    if (!isset($permissions[$role])) {
        return false;
    }

    return in_array(
        $page,
        $permissions[$role],
        true
    );
}

/*
|--------------------------------------------------------------------------
| TÊN HIỂN THỊ CÁC TRANG
|--------------------------------------------------------------------------
*/

if (!defined('PAGE_LABELS')) {
    define('PAGE_LABELS', [

        'login'        => 'Đăng nhập',

        'staff'        => 'Sơ đồ bàn',

        'table-order'  => 'Đơn hàng / Bàn',

        'payment'      => 'Thanh toán',

        'kitchen'      => 'Màn hình bếp',

        'bills'        => 'Lịch sử bill',

        'admin'        => 'Tổng quan quản trị',

        'users'        => 'Quản lý nhân viên',

        'dishes'       => 'Quản lý thực đơn',

        'tables'       => 'Quản lý bàn & khu vực',

        'menu'         => 'Thực đơn',

        'cart'         => 'Giỏ hàng',

        'tracking'     => 'Theo dõi đơn hàng',

        'reservation'  => 'Đặt bàn',

        'profile'      => 'Hồ sơ cá nhân',

        'vouchers'     => 'Quản lý mã giảm giá',

        'reservations' => 'Quản lý đặt bàn'
    ]);
}

/*
|--------------------------------------------------------------------------
| DANH SÁCH TRANG BẮT BUỘC ĐĂNG NHẬP
|--------------------------------------------------------------------------
*/

function getProtectedPages(): array
{
    return [
        'staff',
        'table-order',
        'kitchen',
        'admin',
        'users',
        'payment',
        'dishes',
        'tables',
        'bills',
        'profile',
        'vouchers',
        'reservations'
    ];
}

/*
|--------------------------------------------------------------------------
| KIỂM TRA VÀ ĐIỀU HƯỚNG PHÂN QUYỀN
|--------------------------------------------------------------------------
*/

function enforceAuthorization(&$page): void
{
    $isAuthed = isLoggedIn();

    $role = currentRole();

    /*
    |--------------------------------------------------------------------------
    | NGƯỜI CHƯA ĐĂNG NHẬP
    |--------------------------------------------------------------------------
    */

    if (!$isAuthed) {

        $protectedPages = getProtectedPages();

        if (
            in_array($page, $protectedPages, true) ||
            empty($page)
        ) {
            $page = 'login';
        }

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA VAI TRÒ SESSION
    |--------------------------------------------------------------------------
    */

    if (!in_array($role, SMARTDINE_ROLES, true)) {

        logoutUser();

        $page = 'login';

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | ĐIỀU HƯỚNG KHI ĐÃ ĐĂNG NHẬP
    |--------------------------------------------------------------------------
    */

    if (empty($page) || $page === 'login') {

        $page = getDefaultPageForRole($role);

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | KIỂM TRA QUYỀN TRUY CẬP
    |--------------------------------------------------------------------------
    */

    if (!canRoleAccessPage($role, $page)) {

        $pageLabel = PAGE_LABELS[$page] ?? $page;

        $_SESSION['flash_error'] =
            'Bạn không có quyền truy cập chức năng "' .
            $pageLabel .
            '"!';

        $page = getDefaultPageForRole($role);

        return;
    }
}
