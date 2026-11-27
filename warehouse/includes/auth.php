<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');

warehouse_session_start();

if (empty($_SESSION['user_id']) || empty($_SESSION['loggedin'])) {
    if (!warehouse_try_kds_sso($authConn)) {
        redirect_to('login.php');
    }
}

if (!defined('WAREHOUSE_ALLOWED_ROLES')) {
    define('WAREHOUSE_ALLOWED_ROLES', ['admin', 'shift_manager']);
}

if (!defined('WAREHOUSE_MANAGE_ROLES')) {
    define('WAREHOUSE_MANAGE_ROLES', ['admin', 'shift_manager']);
}

if (!function_exists('current_user_role')) {
    function current_user_role(): string
    {
        return strtolower(trim((string) ($_SESSION['role'] ?? '')));
    }
}

if (!function_exists('current_username')) {
    function current_username(): string
    {
        $name = trim((string) ($_SESSION['username'] ?? ''));
        return $name !== '' ? $name : 'system';
    }
}

if (!function_exists('current_display_name')) {
    function current_display_name(): string
    {
        $full = trim((string) ($_SESSION['fullname'] ?? ''));
        return $full !== '' ? $full : current_username();
    }
}

if (!function_exists('has_role')) {
    function has_role(array $allowedRoles): bool
    {
        $role = current_user_role();
        if ($role === '') {
            return false;
        }
        return in_array($role, $allowedRoles, true);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return current_user_role() === 'admin';
    }
}

if (!function_exists('can_manage_warehouse')) {
    function can_manage_warehouse(): bool
    {
        return has_role(WAREHOUSE_MANAGE_ROLES);
    }
}

if (!function_exists('warehouse_role_meta')) {
    function warehouse_role_meta(?string $role = null): array
    {
        $r = $role !== null ? strtolower(trim($role)) : current_user_role();
        switch ($r) {
            case 'admin':
                return ['label' => 'Quản trị viên', 'badge' => 'role-admin', 'avatar' => 'avatar-role-admin'];
            case 'shift_manager':
                return ['label' => 'Quản lý ca', 'badge' => 'role-shift-manager', 'avatar' => 'avatar-role-shift_manager'];
            case 'kitchen':
                return ['label' => 'Bếp KDS', 'badge' => 'role-kitchen', 'avatar' => 'avatar-role-kitchen'];
            case 'staff':
                return ['label' => 'Phục vụ', 'badge' => 'role-staff', 'avatar' => 'avatar-role-staff'];
            default:
                return ['label' => 'Chưa phân quyền', 'badge' => '', 'avatar' => ''];
        }
    }
}

if (!function_exists('require_role')) {
    function require_role(array $allowedRoles): void
    {
        if (has_role($allowedRoles)) {
            return;
        }
        http_response_code(403);
        die('Bạn không có quyền truy cập chức năng này.');
    }
}
