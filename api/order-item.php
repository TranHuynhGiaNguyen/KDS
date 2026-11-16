<?php

/**
 * API (POST): nhân viên thao tác trên một món của bàn.
 * action=cancel : hủy món, chỉ khi bếp chưa in phiếu.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orders.php';

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'table-order')) {
    respondJson(403, ['error' => 'Bạn không có quyền thao tác món ăn.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}

$action = $_POST['action'] ?? '';
$itemId = (int) ($_POST['item_id'] ?? 0);

if (!isset(ORDER_ACTION_BUTTONS[$action])) {
    respondJson(400, ['error' => 'Thao tác không hợp lệ.']);
}

try {
    $pdo  = getOrdersDb();
    $item = findOrderItem($pdo, $itemId);

    if ($item === null) {
        respondJson(404, ['error' => 'Không tìm thấy món.']);
    }

    if (!in_array($action, ORDER_ITEM_ACTIONS[$item['status']], true)) {
        if (in_array($action, ORDER_ITEM_LOCKED_ACTIONS[$item['status']] ?? [], true)) {
            respondJson(409, ['error' => ORDER_CANCEL_LOCKED_MESSAGE]);
        }

        $statusLabel = ORDER_ITEM_STATUSES[$item['status']]['label'];
        respondJson(409, ['error' => "Món đang ở trạng thái \"$statusLabel\" nên không thực hiện được thao tác này."]);
    }

    if (!cancelOrderItem($pdo, $itemId)) {
        respondJson(409, ['error' => 'Bếp vừa in phiếu món này nên không thể hủy nữa.']);
    }

    respondJson(200, ['ok' => true]);
} catch (RuntimeException | PDOException $e) {
    error_log('order-item API: ' . $e->getMessage());
    respondJson(500, ['error' => 'Lỗi hệ thống, vui lòng thử lại.']);
}
