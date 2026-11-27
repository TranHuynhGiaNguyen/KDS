<?php
/**
 * SmartDine - API kiểm tra trạng thái thanh toán real-time cho thu ngân & khách tại bàn.
 *
 * GET ?table=T01&order_id=123          (khi đã đăng nhập nhân viên/thu ngân)
 * GET ?qr=<token>&order_id=123         (khi khách quét QR tại bàn trên qr-order.php)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/qr-tables.php';
require_once __DIR__ . '/../includes/ratings.php';
require_once __DIR__ . '/../includes/payment-gateway.php';

header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respondJson(405, ['success' => false, 'error' => 'Phương thức không hợp lệ.']);
}

$qrToken   = trim((string) ($_GET['qr'] ?? ''));
$rawTable  = trim((string) ($_GET['table'] ?? ''));
$orderId   = max(0, (int) ($_GET['order_id'] ?? 0));

try {
    $pdo = getOrdersDb();

    if ($qrToken !== '') {
        $qrRow = findTableByQrToken($pdo, $qrToken);
        if (!$qrRow) {
            respondJson(404, ['success' => false, 'error' => 'Mã QR không hợp lệ.']);
        }
        $tableCode = (string) $qrRow['table_code'];
    } elseif ($rawTable !== '' && (isLoggedIn() || getDiningTableByCode(strtoupper($rawTable)) !== null)) {
        $tableCode = normalizeTableCode($rawTable);
    } else {
        respondJson(403, ['success' => false, 'error' => 'Thiếu thông tin bàn hoặc phiên đăng nhập hợp lệ.']);
    }

    $status = getTablePaymentStatus($pdo, $tableCode, $orderId);
    if (!isLoggedIn()) {
        unset($status['redirect_url']);
    }

    respondJson(200, $status);
} catch (InvalidArgumentException $e) {
    respondJson(400, ['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('payment-status API: ' . $e->getMessage());
    respondJson(500, ['success' => false, 'error' => 'Không kiểm tra được trạng thái thanh toán.']);
}
