<?php
/**
 * API (GET): danh sách món đang ở bếp để màn hình KDS tự làm mới.
 * Trả về html các ô bàn, số lượng món chờ / số bàn và id các món đang chờ.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/kitchen-view.php';

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'kitchen')) {
    respondJson(403, ['error' => 'Bạn không có quyền xem màn hình bếp.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}

try {
    $tickets = fetchKitchenTickets(getOrdersDb());
} catch (RuntimeException | PDOException $e) {
    error_log('kitchen-items API: ' . $e->getMessage());
    respondJson(500, ['error' => 'Không tải được danh sách món của bếp.']);
}

$pendingIds = array_values(array_map(
    static fn(array $ticket): int => $ticket['id'],
    array_filter($tickets, static fn(array $ticket): bool => $ticket['status'] === 'pending')
));

respondJson(200, [
    'html'        => renderKitchenGrid($tickets),
    'counts'      => countKitchenTickets($tickets),
    'pending_ids' => $pendingIds,
]);