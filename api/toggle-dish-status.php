<?php
/**
 * SmartDine - API Bật / Tắt trạng thái món ăn (Đang bán <-> Tạm hết).
 * Dành riêng cho Quản trị viên (Admin).
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!canRoleAccessPage(currentRole(), 'dishes')) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này!'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID món ăn không hợp lệ!'], JSON_UNESCAPED_UNICODE);
    exit;
}

$res = toggleDishStatus($id);
echo json_encode($res, JSON_UNESCAPED_UNICODE);
