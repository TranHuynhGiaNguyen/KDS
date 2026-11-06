<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bill-summary.php';
require_once __DIR__ . '/../includes/orders.php';

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'payment')) {
    respondJson(403, ['error' => 'Bạn không có quyền truy cập chức năng thanh toán.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}

try {
    $items = fetchTableItems(getOrdersDb(), normalizeTableCode($_POST['table'] ?? null));
} catch (RuntimeException | PDOException $e) {
    error_log('payment-calculate API: ' . $e->getMessage());
    respondJson(500, ['error' => 'Không tải được hóa đơn, vui lòng thử lại.']);
}

$discount = [
    'type'  => $_POST['discount_type'] ?? DISCOUNT_NONE,
    'value' => (int) ($_POST['discount_value'] ?? 0),
];

try {
    $bill = calculateBill($items, $discount);
} catch (InvalidArgumentException $e) {
    respondJson(422, ['error' => $e->getMessage()]);
}

respondJson(200, [
    'summary' => renderBillSummary($bill),
    'total'   => formatMoney($bill['total']),
]);