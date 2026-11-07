<?php
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
        $statusLabel = ORDER_ITEM_STATUSES[$item['status']]['label'];
        respondJson(409, ['error' => "Món đang ở trạng thái \"$statusLabel\" nên không thực hiện được thao tác này."]);
    }

    if ($action === 'send' && !sendItemToKitchen($pdo, $itemId, (int) $_SESSION['user_id'])) {
        respondJson(409, ['error' => 'Món này vừa được xuất bill trước đó.']);
    }

    if ($action === 'serve') {
        if (!markItemServed($pdo, $itemId)) {
            respondJson(409, ['error' => 'Món này đã được xác nhận ra bàn trước đó.']);
        }
        respondJson(200, ['ok' => true]);
    }

    if ($action === 'cancel') {
        if (!cancelOrderItem($pdo, $itemId)) {
            respondJson(409, ['error' => 'Không thể hủy món ở trạng thái hiện tại.']);
        }
        respondJson(200, ['ok' => true]);
    }

    respondJson(200, ['ticket' => fetchItemTicket($pdo, $itemId)]);
} catch (RuntimeException | PDOException $e) {
    error_log('order-item API: ' . $e->getMessage());
    respondJson(500, ['error' => 'Lỗi hệ thống, vui lòng thử lại.']);
}