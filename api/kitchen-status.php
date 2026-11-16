<?php

/**
 * API (POST): bếp thao tác trên một món.
 * action=start  : bếp bấm "In phiếu bếp": món chuyển từ chờ tiếp nhận sang đang nấu, trả về nội dung phiếu để in.
 * action=finish : bếp bấm "Hoàn thành món": món chuyển từ đang nấu sang đã nấu xong.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/kitchen.php';

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'kitchen')) {
    respondJson(403, ['error' => 'Bạn không có quyền thao tác trên màn hình bếp.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}

$action = $_POST['action'] ?? '';
$itemId = (int) ($_POST['item_id'] ?? 0);

if (!isset(KITCHEN_TRANSITIONS[$action])) {
    respondJson(400, ['error' => 'Thao tác không hợp lệ.']);
}

try {
    $pdo  = getOrdersDb();
    $item = findOrderItem($pdo, $itemId);

    if ($item === null) {
        respondJson(404, ['error' => 'Không tìm thấy món.']);
    }

    if ($item['status'] === 'cancelled') {
        respondJson(409, ['error' => 'Món này đã bị hủy, không cần làm nữa.']);
    }

    if (!advanceKitchenItem($pdo, $itemId, $action, (int) $_SESSION['user_id'])) {
        respondJson(409, ['error' => 'Món đã được xử lý ở thao tác khác, danh sách sẽ được làm mới.']);
    }

    if ($action === 'start') {
        respondJson(200, ['ticket' => fetchItemTicket($pdo, $itemId)]);
    }

    respondJson(200, ['ok' => true]);
} catch (RuntimeException | PDOException $e) {
    error_log('kitchen-status API: ' . $e->getMessage());
    respondJson(500, ['error' => 'Lỗi hệ thống, vui lòng thử lại.']);
}
