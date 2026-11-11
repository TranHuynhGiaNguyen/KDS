<?php
/**
 * API (GET): nội dung bill món để in lại từ trang lịch sử bill.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orders.php';

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'bills')) {
    respondJson(403, ['error' => 'Bạn không có quyền xem lịch sử bill.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}

try {
    $ticket = fetchItemTicket(getOrdersDb(), (int) ($_GET['item_id'] ?? 0));
} catch (RuntimeException | PDOException $e) {
    error_log('bill-ticket API: ' . $e->getMessage());
    respondJson(500, ['error' => 'Lỗi hệ thống, vui lòng thử lại.']);
}

if ($ticket === null) {
    respondJson(404, ['error' => 'Không tìm thấy bill món này.']);
}

respondJson(200, ['ticket' => $ticket]);