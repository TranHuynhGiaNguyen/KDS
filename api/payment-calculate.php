<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bill-summary.php';
require_once __DIR__ . '/../includes/checkout.php';
require_once __DIR__ . '/../includes/vietqr.php';
require_once __DIR__ . '/../includes/vouchers.php';

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'payment')) {
    respondJson(403, ['error' => 'Bạn không có quyền truy cập chức năng thanh toán.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}

$tableCode = normalizeTableCode($_POST['table'] ?? null);

try {
    $pdo     = getOrdersDb();
    $items   = fetchTableItems($pdo, $tableCode);
    $orderId = fetchOpenOrderId($pdo, $tableCode);
} catch (RuntimeException | PDOException $e) {
    error_log('payment-calculate API: ' . $e->getMessage());
    respondJson(500, ['error' => 'Không tải được hóa đơn, vui lòng thử lại.']);
}

$voucherCode = trim($_POST['voucher_code'] ?? '');
if ($voucherCode !== '') {
    // Tính tạm tính sơ bộ để kiểm tra voucher
    $lines = buildBillLines($items, getBillingConfig()['excluded_statuses']);
    $subtotal = array_sum(array_column($lines, 'amount'));
    $vRes = validateVoucher($voucherCode, $subtotal);
    if (!$vRes['valid']) {
        respondJson(422, ['error' => $vRes['message']]);
    }

    $discount = [
        'type'         => $vRes['discount_type'],
        'value'        => $vRes['discount_value'],
        'voucher_code' => $vRes['code'],
    ];
} else {
    $discount = [
        'type'         => $_POST['discount_type'] ?? DISCOUNT_NONE,
        'value'        => (int) ($_POST['discount_value'] ?? 0),
        'voucher_code' => null,
    ];
}

try {
    $bill = calculateBill($items, $discount);
} catch (InvalidArgumentException $e) {
    respondJson(422, ['error' => $e->getMessage()]);
}

respondJson(200, [
    'summary'   => renderBillSummary($bill),
    'total'     => formatMoney($bill['total']),
    'total_raw' => $bill['total'],
    'qr_payload' => buildInvoiceQrPayload($orderId, $tableCode, $bill['total']),
]);