<?php
/**
 * SmartDine - Webhook nhận thông báo chuyển khoản ngân hàng tự động (SePay / Casso / PayOS).
 *
 * Bảo mật:
 *  - Yêu cầu header `Authorization: Apikey <secret>` hoặc `Authorization: Bearer <secret>`
 *    hoặc `X-Webhook-Secret: <secret>` hoặc `Secure-Token: <secret>` khớp với
 *    `webhook_secret` trong `config/payment.local.php` / env `SMARTDINE_WEBHOOK_SECRET`.
 *  - Đối chiếu mã đơn `HDxxxxxx` trong nội dung chuyển khoản và kiểm tra số tiền >= tổng hóa đơn.
 *  - Khi hợp lệ, tự động gọi `checkoutTable(..., 'qr', 0, null)` để xuất hóa đơn & giải phóng bàn.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/checkout.php';
require_once __DIR__ . '/../includes/payment-gateway.php';

header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respondJson(405, ['success' => false, 'error' => 'Method not allowed']);
}

$config = getPaymentGatewayConfig();
if (($config['webhook_secret'] ?? '') === '') {
    respondJson(503, [
        'success' => false,
        'error'   => 'webhook_secret_not_configured',
        'message' => 'Chưa cấu hình webhook_secret trong config/payment.local.php hoặc biến môi trường SMARTDINE_WEBHOOK_SECRET.',
    ]);
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
if (!is_array($headers)) {
    $headers = [];
}
foreach ($_SERVER as $k => $v) {
    if ($k === 'HTTP_AUTHORIZATION' || $k === 'REDIRECT_HTTP_AUTHORIZATION') {
        $headers['Authorization'] = (string) $v;
    } elseif ($k === 'HTTP_X_WEBHOOK_SECRET') {
        $headers['X-Webhook-Secret'] = (string) $v;
    } elseif ($k === 'HTTP_SECURE_TOKEN') {
        $headers['Secure-Token'] = (string) $v;
    }
}

if (!verifyPaymentWebhookAuth($config, $headers)) {
    respondJson(401, ['success' => false, 'error' => 'Unauthorized webhook request']);
}

$rawBody = (string) file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    respondJson(400, ['success' => false, 'error' => 'Invalid JSON payload']);
}

$events = normalizeWebhookTransactions($payload, $config['provider'] ?? 'sepay');
if ($events === []) {
    respondJson(200, [
        'success'   => true,
        'processed' => 0,
        'results'   => [],
        'message'   => 'Không có giao dịch tiền vào cần xử lý.',
    ]);
}

try {
    $pdo     = getOrdersDb();
    $results = [];
    $paidCnt = 0;

    foreach ($events as $event) {
        $res = processPaymentWebhookEvent($pdo, $event);
        if (!empty($res['ok']) && ($res['status'] ?? '') === 'paid') {
            $paidCnt++;
        }
        $results[] = $res;
    }

    respondJson(200, [
        'success'   => true,
        'processed' => count($results),
        'paid'      => $paidCnt,
        'results'   => $results,
    ]);
} catch (Throwable $e) {
    error_log('payment-webhook API: ' . $e->getMessage());
    respondJson(500, ['success' => false, 'error' => 'Internal server error processing webhook']);
}
