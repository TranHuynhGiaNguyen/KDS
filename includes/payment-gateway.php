<?php
/**
 * SmartDine - Tiện ích cổng thanh toán VietQR & đối soát Webhook tự động.
 */

require_once __DIR__ . '/../config/payment_gateway.php';
require_once __DIR__ . '/vietqr.php';
require_once __DIR__ . '/checkout.php';

/**
 * Đảm bảo bảng payment_intents tồn tại (PHẢI gọi ngoài transaction vì DDL gây implicit commit).
 */
function ensurePaymentIntentsSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `payment_intents` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `table_code` VARCHAR(20) NOT NULL,
            `transfer_code` VARCHAR(64) NOT NULL,
            `expected_amount` INT NOT NULL,
            `discount_type` VARCHAR(20) NOT NULL DEFAULT 'none',
            `discount_value` INT NOT NULL DEFAULT 0,
            `voucher_code` VARCHAR(64) DEFAULT NULL,
            `status` ENUM('pending', 'paid', 'expired') NOT NULL DEFAULT 'pending',
            `provider` VARCHAR(40) DEFAULT NULL,
            `gateway_tx_id` VARCHAR(120) DEFAULT NULL,
            `paid_amount` INT NOT NULL DEFAULT 0,
            `invoice_id` INT DEFAULT NULL,
            `raw_payload` TEXT DEFAULT NULL,
            `paid_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_order_id` (`order_id`),
            KEY `idx_table_status` (`table_code`, `status`),
            KEY `idx_transfer_code` (`transfer_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ready = true;
}

/**
 * Lưu hoặc cập nhật số tiền kỳ vọng + ưu đãi cho đơn đang chờ chuyển khoản QR.
 */
function upsertPaymentIntent(PDO $pdo, int $orderId, string $tableCode, int $expectedAmount, array $discount = []): ?array
{
    if ($orderId <= 0 || $expectedAmount <= 0) {
        return null;
    }

    ensurePaymentIntentsSchema($pdo);

    $transferCode  = formatTransferContent($orderId, $tableCode);
    $rawType       = (string) ($discount['type'] ?? 'none');
    $discountType  = in_array($rawType, ['none', 'percent', 'amount'], true)
        ? $rawType
        : 'none';
    $discountValue = max(0, (int) ($discount['value'] ?? 0));
    $voucherCode   = trim((string) ($discount['voucher_code'] ?? ''));
    $voucherParam  = $voucherCode !== '' ? strtoupper($voucherCode) : null;

    $stmt = $pdo->prepare("
        INSERT INTO `payment_intents` (
            `order_id`, `table_code`, `transfer_code`, `expected_amount`,
            `discount_type`, `discount_value`, `voucher_code`, `status`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ON DUPLICATE KEY UPDATE
            `table_code`      = IF(`status` = 'paid', `table_code`, VALUES(`table_code`)),
            `transfer_code`   = IF(`status` = 'paid', `transfer_code`, VALUES(`transfer_code`)),
            `expected_amount` = IF(`status` = 'paid', `expected_amount`, VALUES(`expected_amount`)),
            `discount_type`   = IF(`status` = 'paid', `discount_type`, VALUES(`discount_type`)),
            `discount_value`  = IF(`status` = 'paid', `discount_value`, VALUES(`discount_value`)),
            `voucher_code`    = IF(`status` = 'paid', `voucher_code`, VALUES(`voucher_code`))
    ");
    $stmt->execute([
        $orderId,
        $tableCode,
        $transferCode,
        $expectedAmount,
        $discountType,
        $discountValue,
        $voucherParam,
    ]);

    $fetch = $pdo->prepare("SELECT * FROM `payment_intents` WHERE `order_id` = ? LIMIT 1");
    $fetch->execute([$orderId]);
    return $fetch->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Cập nhật payment_intent thành đã thanh toán (chạy trong transaction checkoutTable).
 */
function syncPaymentIntentPaidInTx(
    PDO $pdo,
    int $orderId,
    string $tableCode,
    int $totalAmount,
    int $invoiceId,
    array $gatewayMeta = []
): void {
    if ($orderId <= 0) {
        return;
    }

    $transferCode = formatTransferContent($orderId, $tableCode);
    $provider     = trim((string) ($gatewayMeta['provider'] ?? 'manual'));
    $txId         = trim((string) ($gatewayMeta['tx_id'] ?? ''));
    $paidAmount   = (int) ($gatewayMeta['paid_amount'] ?? $totalAmount);
    $rawPayload   = isset($gatewayMeta['raw_payload']) ? (string) $gatewayMeta['raw_payload'] : null;
    if ($rawPayload !== null && strlen($rawPayload) > 4000) {
        $rawPayload = substr($rawPayload, 0, 4000);
    }

    $stmt = $pdo->prepare("
        INSERT INTO `payment_intents` (
            `order_id`, `table_code`, `transfer_code`, `expected_amount`,
            `status`, `provider`, `gateway_tx_id`, `paid_amount`, `invoice_id`, `raw_payload`, `paid_at`
        ) VALUES (?, ?, ?, ?, 'paid', ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            `status`        = 'paid',
            `provider`      = VALUES(`provider`),
            `gateway_tx_id` = COALESCE(NULLIF(VALUES(`gateway_tx_id`), ''), `gateway_tx_id`),
            `paid_amount`   = VALUES(`paid_amount`),
            `invoice_id`    = VALUES(`invoice_id`),
            `raw_payload`   = COALESCE(VALUES(`raw_payload`), `raw_payload`),
            `paid_at`       = VALUES(`paid_at`)
    ");
    $stmt->execute([
        $orderId,
        $tableCode,
        $transferCode,
        $totalAmount,
        $provider !== '' ? $provider : 'manual',
        $txId !== '' ? $txId : null,
        $paidAmount,
        $invoiceId,
        $rawPayload,
    ]);
}

/**
 * Kiểm tra secret xác thực webhook (hỗ trợ SePay Apikey, Bearer token, Casso secure-token).
 */
function verifyPaymentWebhookAuth(array $config, array $headers): bool
{
    $secret = trim((string) ($config['webhook_secret'] ?? ''));
    if ($secret === '') {
        return false;
    }

    $normalized = [];
    foreach ($headers as $key => $value) {
        $normalized[strtolower((string) $key)] = trim((string) $value);
    }

    $candidates = [];
    if (!empty($normalized['x-webhook-secret'])) {
        $candidates[] = $normalized['x-webhook-secret'];
    }
    if (!empty($normalized['secure-token'])) {
        $candidates[] = $normalized['secure-token'];
    }
    if (!empty($normalized['authorization'])) {
        $auth = $normalized['authorization'];
        if (preg_match('/^(?:Apikey|Bearer)\s+(.+)$/i', $auth, $m)) {
            $candidates[] = trim($m[1]);
        } else {
            $candidates[] = $auth;
        }
    }

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && hash_equals($secret, $candidate)) {
            return true;
        }
    }

    return false;
}

/**
 * Rút trích mã đơn (HD000012 -> 12) từ nội dung chuyển khoản ngân hàng.
 */
function parseOrderIdFromTransferContent(string $content): int
{
    $upper = strtoupper(trim($content));
    if (preg_match('/\bHD0*([1-9]\d*)\b/', $upper, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/HD0*([1-9]\d*)/', $upper, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/**
 * Chuẩn hóa payload webhook từ SePay / Casso / PayOS hoặc JSON chuẩn về danh sách giao dịch tiền vào.
 */
function normalizeWebhookTransactions(array $payload, string $defaultProvider = 'sepay'): array
{
    // 1. Casso: { error: 0, data: [ { id, amount, description, ... } ] }
    if (isset($payload['data']) && is_array($payload['data']) && isset($payload['data'][0]) && is_array($payload['data'][0])) {
        $events = [];
        foreach ($payload['data'] as $tx) {
            if (!is_array($tx)) {
                continue;
            }
            $amount  = (int) round((float) ($tx['amount'] ?? 0));
            $content = (string) ($tx['description'] ?? $tx['content'] ?? '');
            $txId    = (string) ($tx['tid'] ?? $tx['id'] ?? '');
            if ($amount > 0) {
                $events[] = [
                    'provider'    => 'casso',
                    'amount'      => $amount,
                    'content'     => $content,
                    'order_id'    => parseOrderIdFromTransferContent($content),
                    'tx_id'       => $txId,
                    'raw_payload' => json_encode($tx, JSON_UNESCAPED_UNICODE),
                ];
            }
        }
        return $events;
    }

    // 2. PayOS: { code: "00", data: { orderCode, amount, description, reference } }
    if (isset($payload['data']) && is_array($payload['data']) && isset($payload['data']['amount'])) {
        $tx      = $payload['data'];
        $amount  = (int) round((float) ($tx['amount'] ?? 0));
        $content = (string) ($tx['description'] ?? $tx['content'] ?? '');
        $orderId = parseOrderIdFromTransferContent($content);
        if ($orderId <= 0 && !empty($tx['orderCode'])) {
            $orderId = (int) $tx['orderCode'];
        }
        $txId = (string) ($tx['reference'] ?? $tx['paymentLinkId'] ?? $tx['orderCode'] ?? '');
        return $amount > 0 ? [[
            'provider'    => 'payos',
            'amount'      => $amount,
            'content'     => $content,
            'order_id'    => $orderId,
            'tx_id'       => $txId,
            'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]] : [];
    }

    // 3. SePay hoặc JSON webhook chuẩn
    if (isset($payload['transferType']) && strtolower((string) $payload['transferType']) !== 'in') {
        return [];
    }

    $amount = (int) round((float) ($payload['transferAmount'] ?? $payload['amount'] ?? 0));
    if ($amount <= 0) {
        return [];
    }

    $content = (string) ($payload['content'] ?? $payload['description'] ?? $payload['transfer_code'] ?? '');
    $orderId = (int) ($payload['order_id'] ?? 0);
    if ($orderId <= 0) {
        $orderId = parseOrderIdFromTransferContent($content);
    }

    $txId = (string) ($payload['id'] ?? $payload['referenceCode'] ?? $payload['tx_id'] ?? '');
    return [[
        'provider'    => isset($payload['gateway']) || isset($payload['transferAmount']) ? 'sepay' : $defaultProvider,
        'amount'      => $amount,
        'content'     => $content,
        'order_id'    => $orderId,
        'tx_id'       => $txId,
        'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]];
}

/**
 * Đối soát 1 giao dịch webhook và tự động đóng đơn (checkoutTable) nếu khớp đúng đơn và đủ tiền.
 */
function processPaymentWebhookEvent(PDO $pdo, array $event): array
{
    ensurePaymentIntentsSchema($pdo);

    $amount   = (int) ($event['amount'] ?? 0);
    $orderId  = (int) ($event['order_id'] ?? 0);
    $provider = trim((string) ($event['provider'] ?? 'webhook'));
    $txId     = trim((string) ($event['tx_id'] ?? ''));

    if ($amount <= 0 || $orderId <= 0) {
        return [
            'ok'      => false,
            'status'  => 'ignored',
            'message' => 'Không tìm thấy mã đơn HD hợp lệ hoặc số tiền <= 0.',
        ];
    }

    // Chống xử lý lặp theo gateway_tx_id
    if ($txId !== '') {
        $dupStmt = $pdo->prepare("
            SELECT `order_id`, `invoice_id`, `table_code`
            FROM `payment_intents`
            WHERE `provider` = ? AND `gateway_tx_id` = ? AND `status` = 'paid'
            LIMIT 1
        ");
        $dupStmt->execute([$provider, $txId]);
        $dup = $dupStmt->fetch(PDO::FETCH_ASSOC);
        if ($dup) {
            return [
                'ok'         => true,
                'status'     => 'already_paid',
                'idempotent' => true,
                'order_id'   => (int) $dup['order_id'],
                'invoice_id' => (int) ($dup['invoice_id'] ?? 0),
                'table_code' => (string) $dup['table_code'],
            ];
        }
    }

    $orderStmt = $pdo->prepare("SELECT `id`, `table_code`, `status` FROM `orders` WHERE `id` = ? LIMIT 1");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return [
            'ok'      => false,
            'status'  => 'order_not_found',
            'message' => 'Không tìm thấy đơn hàng #' . $orderId . '.',
        ];
    }

    $tableCode = (string) $order['table_code'];

    if (($order['status'] ?? '') === 'paid') {
        $invStmt = $pdo->prepare("SELECT `id` FROM `invoices` WHERE `order_id` = ? LIMIT 1");
        $invStmt->execute([$orderId]);
        $existingInvoiceId = (int) ($invStmt->fetchColumn() ?: 0);
        return [
            'ok'         => true,
            'status'     => 'already_paid',
            'idempotent' => true,
            'order_id'   => $orderId,
            'invoice_id' => $existingInvoiceId,
            'table_code' => $tableCode,
        ];
    }

    if (($order['status'] ?? '') !== 'open') {
        return [
            'ok'      => false,
            'status'  => 'order_not_open',
            'message' => 'Đơn hàng #' . $orderId . ' không ở trạng thái đang mở.',
        ];
    }

    // Lấy ưu đãi/voucher thu ngân đã áp (nếu có) từ payment_intents
    $intentStmt = $pdo->prepare("SELECT * FROM `payment_intents` WHERE `order_id` = ? LIMIT 1");
    $intentStmt->execute([$orderId]);
    $intent = $intentStmt->fetch(PDO::FETCH_ASSOC);

    $discount = [
        'type'         => $intent['discount_type'] ?? DISCOUNT_NONE,
        'value'        => (int) ($intent['discount_value'] ?? 0),
        'voucher_code' => !empty($intent['voucher_code']) ? (string) $intent['voucher_code'] : null,
    ];

    $items = fetchOrderItemsById($pdo, $orderId);
    try {
        $bill = calculateBill($items, $discount);
    } catch (Throwable $e) {
        $discount = ['type' => DISCOUNT_NONE, 'value' => 0, 'voucher_code' => null];
        $bill     = calculateBill($items, $discount);
    }

    $requiredAmount = (int) $bill['total'];
    if ($requiredAmount <= 0) {
        return [
            'ok'      => false,
            'status'  => 'zero_total',
            'message' => 'Hóa đơn có tổng tiền bằng 0đ.',
        ];
    }

    if ($amount < $requiredAmount) {
        return [
            'ok'              => false,
            'status'          => 'insufficient_amount',
            'order_id'        => $orderId,
            'table_code'      => $tableCode,
            'expected_amount' => $requiredAmount,
            'received_amount' => $amount,
            'message'         => 'Số tiền chuyển (' . formatMoney($amount) . ') nhỏ hơn hóa đơn (' . formatMoney($requiredAmount) . ').',
        ];
    }

    $assignment = getActiveTableAssignment($tableCode, $pdo);
    $waiterId   = !empty($assignment['waiter_id']) ? (int) $assignment['waiter_id'] : null;

    $invoiceId = checkoutTable(
        $pdo,
        $tableCode,
        $discount,
        'qr',
        0,
        null,
        $waiterId,
        null,
        '',
        [
            'webhook_verified' => true,
            'provider'         => $provider,
            'tx_id'            => $txId,
            'paid_amount'      => $amount,
            'raw_payload'      => $event['raw_payload'] ?? null,
        ]
    );

    return [
        'ok'              => true,
        'status'          => 'paid',
        'order_id'        => $orderId,
        'invoice_id'      => $invoiceId,
        'table_code'      => $tableCode,
        'expected_amount' => $requiredAmount,
        'received_amount' => $amount,
    ];
}

/**
 * Truy vấn trạng thái thanh toán real-time cho màn hình thu ngân và màn hình QR của khách.
 */
function getTablePaymentStatus(PDO $pdo, string $tableCode, int $orderId = 0): array
{
    ensurePaymentIntentsSchema($pdo);
    $cfg    = getPaymentGatewayConfig();
    $autoOn = ($cfg['webhook_secret'] ?? '') !== '';

    $resolvedOrderId = $orderId > 0 ? $orderId : (fetchOpenOrderId($pdo, $tableCode) ?? 0);

    if ($resolvedOrderId > 0) {
        $stmt = $pdo->prepare("
            SELECT o.`id`, o.`status`, o.`table_code`,
                   i.`id` AS `invoice_id`, i.`total` AS `invoice_total`,
                   pi.`expected_amount`, pi.`transfer_code`, pi.`provider`, pi.`paid_at`
            FROM `orders` o
            LEFT JOIN `invoices` i ON i.`order_id` = o.`id`
            LEFT JOIN `payment_intents` pi ON pi.`order_id` = o.`id`
            WHERE o.`id` = ?
            LIMIT 1
        ");
        $stmt->execute([$resolvedOrderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && ($row['status'] ?? '') === 'paid') {
            $invId = (int) ($row['invoice_id'] ?? 0);
            $ratingUrl = '';
            if ($invId > 0 && function_exists('fetchInvoiceRating')) {
                $rInfo = fetchInvoiceRating($pdo, $invId);
                if ($rInfo && ($rInfo['rating'] ?? null) === null) {
                    $ratingUrl = (string) ($rInfo['url'] ?? '');
                }
            }

            return [
                'success'               => true,
                'paid'                  => true,
                'status'                => 'paid',
                'order_id'              => $resolvedOrderId,
                'table_code'            => (string) $row['table_code'],
                'invoice_id'            => $invId,
                'total'                 => (int) ($row['invoice_total'] ?? $row['expected_amount'] ?? 0),
                'provider'              => (string) ($row['provider'] ?? ''),
                'paid_at'               => (string) ($row['paid_at'] ?? ''),
                'rating_url'            => $ratingUrl,
                'redirect_url'          => 'index.php?page=payment&table=' . urlencode((string) $row['table_code']) . '&invoice=' . $invId,
                'auto_confirm_configured' => $autoOn,
            ];
        }

        if ($row && ($row['status'] ?? '') === 'open') {
            return [
                'success'               => true,
                'paid'                  => false,
                'status'                => 'pending',
                'order_id'              => $resolvedOrderId,
                'table_code'            => (string) $row['table_code'],
                'expected_amount'       => (int) ($row['expected_amount'] ?? 0),
                'transfer_code'         => (string) ($row['transfer_code'] ?? formatTransferContent($resolvedOrderId, (string) $row['table_code'])),
                'auto_confirm_configured' => $autoOn,
            ];
        }
    }

    return [
        'success'                 => true,
        'paid'                    => false,
        'status'                  => 'no_open_order',
        'order_id'                => $resolvedOrderId,
        'table_code'              => $tableCode,
        'auto_confirm_configured' => $autoOn,
    ];
}
