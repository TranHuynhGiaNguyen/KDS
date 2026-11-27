<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tables.php';
header('Cache-Control: no-store');
if (!isLoggedIn() || !in_array(currentRole(), ['admin', 'staff'], true)) {
    respondJson(403, ['error' => 'Bạn không có quyền xem sơ đồ bàn.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}
session_write_close();
try {
    respondJson(200, ['tables' => getAllDiningTables('', 'not_hidden')]);
} catch (Throwable $error) {
    error_log('table-live: ' . $error->getMessage());
    respondJson(500, ['error' => 'Chưa cập nhật được sơ đồ bàn.']);
}
