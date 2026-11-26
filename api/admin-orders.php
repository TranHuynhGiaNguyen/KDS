<?php

/**
 * API (GET): danh sách món đang phục vụ theo từng bàn để Trang Admin tự đồng bộ thời gian thực.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-orders-view.php';

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'admin')) {
    respondJson(403, ['error' => 'Bạn không có quyền xem mục này.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}

try {
    $tables = fetchOpenOrdersSummary(getOrdersDb());
} catch (RuntimeException | PDOException $e) {
    error_log('admin-orders API: ' . $e->getMessage());
    respondJson(500, ['error' => 'Không tải được danh sách món đang phục vụ.']);
}

$itemCount = 0;
foreach ($tables as $t) {
    $itemCount += count($t['items']);
}

respondJson(200, [
    'html'        => renderAdminActiveOrders($tables),
    'table_count' => count($tables),
    'item_count'  => $itemCount,
]);
