<?php

/**
 * API (POST): nhân viên thao tác trên món của bàn.
 * action=add    : thêm món vào bàn (Quản trị viên và Nhân viên phục vụ).
 * action=cancel : hủy món (chỉ Quản trị viên, áp dụng cả khi chưa nấu lẫn đã nấu xong).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orders.php';

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'table-order')) {
    respondJson(403, ['error' => 'Bạn không có quyền thao tác món ăn.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}

const ORDER_API_ACTIONS = ['add', 'cancel'];
const ORDER_API_DENIED_MESSAGES = [
    'add'    => 'Bạn không có quyền thêm món vào bàn.',
    'cancel' => 'Chỉ Quản trị viên mới được hủy món.',
];

$action = $_POST['action'] ?? '';

if (!in_array($action, ORDER_API_ACTIONS, true)) {
    respondJson(400, ['error' => 'Thao tác không hợp lệ.']);
}

if (!canRoleDoOrderAction(currentRole(), $action)) {
    respondJson(403, ['error' => ORDER_API_DENIED_MESSAGES[$action]]);
}

try {
    $pdo = getOrdersDb();

    if ($action === 'add') {
        $itemId = addOrderItem(
            $pdo,
            strtoupper(trim((string) ($_POST['table'] ?? ''))),
            (int) ($_POST['dish_id'] ?? 0),
            (int) ($_POST['qty'] ?? 1),
            (string) ($_POST['note'] ?? ''),
            (int) $_SESSION['user_id']
        );

        respondJson(200, ['ok' => true, 'item_id' => $itemId]);
    }

    $itemId    = (int) ($_POST['item_id'] ?? 0);
    $cancelQty = isset($_POST['qty']) && $_POST['qty'] !== '' ? (int) $_POST['qty'] : null;

    if ($cancelQty !== null && $cancelQty < 1) {
        respondJson(422, ['error' => 'Số lượng cần hủy phải từ 1 trở lên.']);
    }

    $item = findOrderItem($pdo, $itemId);

    if ($item === null) {
        respondJson(404, ['error' => 'Không tìm thấy món.']);
    }

    if ($item['status'] === 'cancelled') {
        respondJson(409, ['error' => 'Món này đã được hủy trước đó.']);
    }

    if (!in_array($item['status'], ORDER_ITEM_CANCELLABLE_STATUSES, true)) {
        respondJson(409, ['error' => ORDER_CANCEL_LOCKED_MESSAGE]);
    }

    if (!cancelOrderItem($pdo, $itemId, $cancelQty)) {
        respondJson(409, ['error' => 'Không hủy được món: món đã được hủy hoặc bàn đã thanh toán.']);
    }

    respondJson(200, ['ok' => true]);
} catch (OrderException $e) {
    respondJson(422, ['error' => $e->getMessage()]);
} catch (RuntimeException | PDOException $e) {
    error_log('order-item API: ' . $e->getMessage());
    respondJson(500, ['error' => 'Lỗi hệ thống, vui lòng thử lại.']);
}
