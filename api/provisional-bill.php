<?php
/**
 * API (GET): phiếu tạm tính của một bàn để khách kiểm tra món.
 * Chỉ liệt kê món, số lượng, tạm tính, phí phục vụ và VAT; không có giảm giá hay tiền khách đưa.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/receipt.php';

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'table-order')) {
    respondJson(403, ['error' => 'Bạn không có quyền in tạm tính.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}

$tableCode = normalizeTableCode($_GET['table'] ?? null);

try {
    $pdo    = getOrdersDb();
    $bill   = calculateBill(fetchTableItems($pdo, $tableCode));
    $timeIn = fetchOpenOrderCreatedAt($pdo, $tableCode);
} catch (RuntimeException | PDOException $e) {
    error_log('provisional-bill API: ' . $e->getMessage());
    respondJson(500, ['error' => 'Không tải được phiếu tạm tính, vui lòng thử lại.']);
}

if ($bill['lines'] === []) {
    respondJson(409, ['error' => 'Bàn ' . $tableCode . ' chưa có món nào để in tạm tính.']);
}

respondJson(200, [
    'html' => renderReceipt(buildProvisionalReceipt($tableCode, $bill, $timeIn, currentUser()['fullname'])),
]);