<?php
/**
 * SmartDine - Hệ thống Xác thực & Phân quyền theo Vai trò (RBAC)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Kiểm tra xem người dùng đã đăng nhập chưa
 */
function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

/**
 * Lấy thông tin tài khoản hiện tại
 */
function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'fullname' => $_SESSION['fullname'] ?? '',
        'role'     => $_SESSION['role'] ?? 'staff',
        'email'    => $_SESSION['email'] ?? '',
        'phone'    => $_SESSION['phone'] ?? '',
    ];
}

/**
 * Lấy vai trò của người dùng hiện tại
 */
function currentRole() {
    return $_SESSION['role'] ?? null;
}

function isAdmin() {
    return currentRole() === 'admin';
}

function isStaff() {
    return currentRole() === 'staff';
}

function isKitchen() {
    return currentRole() === 'kitchen';
}

/**
 * Tên hiển thị tiếng Việt của vai trò
 */
function getRoleName($role) {
    switch ($role) {
        case 'admin':
            return 'Quản trị viên';
        case 'kitchen':
            return 'Đầu bếp KDS';
        case 'staff':
        default:
            return 'Nhân viên phục vụ';
    }
}

/**
 * HTML Badge của vai trò với style màu riêng
 */
function getRoleBadgeHtml($role) {
    switch ($role) {
        case 'admin':
            return '<span class="role-badge role-admin"><i class="role-dot"></i> Quản trị viên</span>';
        case 'kitchen':
            return '<span class="role-badge role-kitchen"><i class="role-dot"></i> Bếp KDS</span>';
        case 'staff':
        default:
            return '<span class="role-badge role-staff"><i class="role-dot"></i> Phục vụ</span>';
    }
}

/**
 * HTML Badge trạng thái hoạt động
 */
function getStatusBadgeHtml($status) {
    if ($status === 'active') {
        return '<span class="status-badge status-active"><span class="status-indicator"></span> Đang hoạt động</span>';
    } else {
        return '<span class="status-badge status-inactive"><span class="status-indicator"></span> Đã khóa</span>';
    }
}

/**
 * Xử lý đăng nhập
 */
function loginUser($username, $password) {
    $username = trim($username);
    $password = trim($password);

    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!'];
    }

    $user = getUserByUsername($username);

    // Không tìm thấy tài khoản
    if (!$user) {
        return ['success' => false, 'message' => 'Tài khoản hoặc mật khẩu không chính xác!'];
    }

    // Kiểm tra tài khoản có bị khóa không
    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Tài khoản này đã bị khóa. Vui lòng liên hệ Quản trị viên!'];
    }

    // Kiểm tra mật khẩu (hỗ trợ password_hash hoặc so sánh trực tiếp nếu mật khẩu chưa hash)
    $passwordMatched = false;
    if (password_verify($password, $user['password'])) {
        $passwordMatched = true;
    } elseif ($password === $user['password']) {
        // Nâng cấp tự động lên hash an toàn
        $passwordMatched = true;
        updateUserPassword($user['id'], $password);
    }

    if (!$passwordMatched) {
        return ['success' => false, 'message' => 'Tài khoản hoặc mật khẩu không chính xác!'];
    }

    // Đăng nhập thành công -> Lưu vào Session
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['fullname']  = $user['fullname'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['phone']     = $user['phone'] ?? '';
    $_SESSION['email']     = $user['email'] ?? '';

    // Tương thích ngược với các trang cũ
    $_SESSION['staff_user'] = $user['username'];
    $_SESSION['staff_name'] = $user['fullname'];

    // Cập nhật lần đăng nhập cuối
    updateLastLogin($user['id']);

    return ['success' => true, 'user' => $user];
}

/**
 * Xử lý đăng xuất
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Trang mặc định khi đăng nhập thành công theo từng vai trò
 */
function getDefaultPageForRole($role) {
    switch ($role) {
        case 'admin':
            return 'admin';
        case 'kitchen':
            return 'kitchen';
        case 'staff':
        default:
            return 'staff';
    }
}

/**
 * Kiểm tra xem vai trò có quyền truy cập trang hay không
 */
function canRoleAccessPage($role, $page) {
    $permissions = [
        'admin' => [
            'admin', 'users', 'dishes', 'tables', 'staff', 'table-order', 'kitchen',
            'menu', 'cart', 'tracking', 'reservation', 'payment'
        ],
        'staff' => [
            'staff', 'table-order',
            'menu', 'cart', 'tracking', 'reservation', 'payment'
        ],
        'kitchen' => [
            'kitchen',
            'menu'
        ],
    ];

    if (!isset($permissions[$role])) {
        return false;
    }

    return in_array($page, $permissions[$role], true);
}

const PAGE_LABELS = [
    'staff'       => 'Sơ đồ bàn',
    'table-order' => 'Đơn hàng / Bàn',
    'payment'     => 'Thanh toán',
    'kitchen'     => 'Màn hình bếp',
    'admin'       => 'Tổng quan quản trị',
    'users'       => 'Quản lý nhân viên',
    'dishes'      => 'Quản lý thực đơn',
    'tables'      => 'Quản lý bàn & khu vực',
    'menu'        => 'Thực đơn',
];

/**
 * Kiểm tra và chuyển hướng nếu không có quyền
 */
function enforceAuthorization(&$page) {
    $isAuthed = isLoggedIn();
    $role = currentRole();

    // Các trang thuộc khu vực quản trị/vận hành (yêu cầu đăng nhập)
    $protectedPages = ['staff', 'table-order', 'kitchen', 'admin', 'users', 'payment', 'dishes', 'tables'];

    if (!$isAuthed) {
        // Chưa đăng nhập mà truy cập trang bảo vệ -> Chuyển về login
        if (in_array($page, $protectedPages, true) || empty($page)) {
            $page = 'login';
        }
        return;
    }

    // Đã đăng nhập:
    // Nếu vào trang login hoặc trang chủ trống -> đưa về trang mặc định theo vai trò
    if (empty($page) || $page === 'login') {
        $page = getDefaultPageForRole($role);
        return;
    }

    // Kiểm tra quyền truy cập trang hiện tại
    if (!canRoleAccessPage($role, $page)) {
        // Không có quyền -> set flash error và chuyển về trang mặc định của vai trò
        $_SESSION['flash_error'] = 'Bạn không có quyền truy cập chức năng "' . htmlspecialchars(PAGE_LABELS[$page] ?? $page) . '"!';
        $page = getDefaultPageForRole($role);
    }
}
