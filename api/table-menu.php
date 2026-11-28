<?php
declare(strict_types=1);

/**
 * SmartDine - API (POST): Đổi vé Buffet / Đổi loại thực đơn bàn.
 * Phân quyền: CHỈ `admin` (Quản trị viên) hoặc `shift_manager` (Quản lý ca / Staff Manager).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/checkout.php';
require_once __DIR__ . '/../includes/table-menu.php';
require_once __DIR__ . '/../includes/payment-gateway.php';

header('Cache-Control: no-store');

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'table-order')) {
    respondJson(403, ['success' => false, 'message' => 'Bạn không có quyền thao tác bàn.']);
}

if (!isManagerOrAdmin()) {
    respondJson(403, ['success' => false, 'message' => 'Chỉ Quản lý ca (Staff Manager) hoặc Quản trị viên (Admin) mới được quyền đổi vé.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respondJson(405, ['success' => false, 'message' => 'Chỉ chấp nhận phương thức POST.']);
}

$raw     = file_get_contents('php://input');
$decoded = json_decode((string) $raw, true);
$payload = is_array($decoded) ? $decoded : $_POST;

$action      = trim((string) ($payload['action'] ?? 'change_ticket'));
$tableCode   = strtoupper(trim((string) ($payload['table_code'] ?? $payload['table'] ?? '')));
$menuType    = trim((string) ($payload['menu_type'] ?? ''));
$packageId   = (int) ($payload['buffet_package_id'] ?? $payload['package_id'] ?? 0);
$ticketQty   = max(1, (int) ($payload['ticket_qty'] ?? 1));

if ($action === 'unlock') {
    $menuType = 'unlock';
}

if ($action !== 'change_ticket' && $action !== 'unlock') {
    respondJson(400, ['success' => false, 'message' => 'Thao tác không hợp lệ.']);
}

try {
    $pdo         = getOrdersDb();
    $user        = currentUser();
    $managerId   = (int) ($user['id'] ?? 0);
    $managerName = trim((string) ($user['fullname'] ?: ($user['username'] ?? 'Quản lý')));

    $res = changeTableMenuByManager($pdo, $tableCode, $menuType, $packageId, $ticketQty, $managerId, $managerName);

    // Cập nhật lại payment_intents nếu bàn đang có đơn mở để mã VietQR đồng bộ số tiền mới
    if (!empty($res['success'])) {
        $openOrderId = fetchOpenOrderId($pdo, $tableCode);
        if ($openOrderId !== null) {
            $items = fetchOrderItemsById($pdo, $openOrderId);
            if ($items !== []) {
                $bill = calculateBill($items);
                if ((int) $bill['total'] > 0) {
                    upsertPaymentIntent($pdo, $openOrderId, $tableCode, (int) $bill['total']);
                }
            }
        }
    }

    respondJson((int) ($res['code'] ?? 200), $res);
} catch (Throwable $e) {
    error_log('api/table-menu error: ' . $e->getMessage());
    respondJson(500, ['success' => false, 'message' => 'Lỗi hệ thống khi đổi vé: ' . $e->getMessage()]);
}
