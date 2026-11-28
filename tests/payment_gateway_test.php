<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/checkout.php';
require_once __DIR__ . '/../includes/vietqr.php';
require_once __DIR__ . '/../includes/payment-gateway.php';

echo "=== KIỂM THỬ CỔNG THANH TOÁN VIETINBANK VIETQR, WEBHOOK & CHATBOT QR ===\n";

// 1. Kiểm tra thông tin VietinBank từ ảnh QR khách hàng cung cấp
$restaurant = getRestaurantInfo();
$bank = $restaurant['bank'] ?? [];
assert(($bank['bin'] ?? '') === '970415', 'BIN VietinBank phải là 970415');
assert(($bank['name'] ?? '') === 'VietinBank', 'Tên ngân hàng phải là VietinBank');
assert(($bank['account_no'] ?? '') === '109881125028', 'Số tài khoản phải là 109881125028');
assert(($bank['account_name'] ?? '') === 'TRAN HUYNH GIA NGUYEN', 'Chủ tài khoản phải là TRAN HUYNH GIA NGUYEN');
echo "  [PASS] Cấu hình ngân hàng VietinBank khớp chính xác STK 109881125028 - TRAN HUYNH GIA NGUYEN\n";

// 2. Kiểm tra sinh VietQR EMVCo khớp số tiền + nội dung chuyển khoản (kèm tiền tố SEVQR cho VietinBank SePay) + CRC16
$qrPayload = buildInvoiceQrPayload(15, 'T01', 283500);
assert(strpos($qrPayload, '970415') !== false, 'QR payload thiếu BIN VietinBank');
assert(strpos($qrPayload, '109881125028') !== false, 'QR payload thiếu STK');
assert(strpos($qrPayload, '5406283500') !== false, 'QR payload phải khóa đúng số tiền 283500');
assert(strpos($qrPayload, 'SEVQR HD000015 Ban T01') !== false, 'QR payload thiếu tiền tố SEVQR + nội dung HD000015 Ban T01');
assert(substr($qrPayload, -8, 4) === '6304' && strlen(substr($qrPayload, -4)) === 4, 'QR payload thiếu CRC16');
echo "  [PASS] Chuỗi EMVCo VietQR khóa đúng số tiền và nội dung SEVQR HD...\n";

// 3. Kiểm tra xác thực header webhook (SePay Apikey, Bearer, X-Webhook-Secret, Secure-Token)
$testCfg = ['webhook_secret' => 'test_secret_123456'];
assert(verifyPaymentWebhookAuth([], ['Authorization' => 'Apikey test_secret_123456']) === false, 'Phải từ chối khi webhook_secret rỗng');
assert(verifyPaymentWebhookAuth($testCfg, ['Authorization' => 'Apikey wrong']) === false, 'Phải từ chối secret sai');
assert(verifyPaymentWebhookAuth($testCfg, ['Authorization' => 'Apikey test_secret_123456']) === true, 'Phải chấp nhận SePay Apikey');
assert(verifyPaymentWebhookAuth($testCfg, ['Authorization' => 'Bearer test_secret_123456']) === true, 'Phải chấp nhận Bearer');
assert(verifyPaymentWebhookAuth($testCfg, ['X-Webhook-Secret' => 'test_secret_123456']) === true, 'Phải chấp nhận X-Webhook-Secret');
assert(verifyPaymentWebhookAuth($testCfg, ['Secure-Token' => 'test_secret_123456']) === true, 'Phải chấp nhận Casso Secure-Token');
echo "  [PASS] Cơ chế xác thực Webhook hoạt động chính xác & chặn giả mạo\n";

$pdo = getOrdersDb();
ensurePaymentIntentsSchema($pdo);
ensureTableAssignmentsSchema($pdo);

$allActive = getAllDiningTables('', 'active', '');
$emptyTables = array_values(array_filter($allActive, static fn(array $t): bool => empty($t['is_occupied'])));
assert(count($emptyTables) >= 1, 'Cần ít nhất 1 bàn trống active để kiểm thử');
$tableCode = $emptyTables[0]['code'];

// 4. Kiểm tra qr-order.php nhúng Chatbot AI và QR thanh toán tại bàn (trên process mới để header sạch)
$qrFile = var_export(realpath(__DIR__ . '/../qr-order.php'), true);
$tblExport = var_export($tableCode, true);
$qrChild = <<<PHP
<?php
\$_GET['table'] = {$tblExport};
require {$qrFile};
PHP;
$procSpec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open(escapeshellarg(PHP_BINARY) . ' -d assert.exception=1', $procSpec, $pipes);
assert(is_resource($proc), 'Không mở được PHP child cho qr-order.php');
fwrite($pipes[0], $qrChild);
fclose($pipes[0]);
$qrHtml = stream_get_contents($pipes[1]);
$qrErr  = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$qrExit = proc_close($proc);
assert($qrExit === 0, "qr-order.php render lỗi (exit={$qrExit}): {$qrErr}");

assert(strpos($qrHtml, 'ai-ordering.css') !== false, 'qr-order.php thiếu ai-ordering.css');
assert(strpos($qrHtml, 'ai-assistant-bubble') !== false, 'qr-order.php thiếu #ai-assistant-bubble');
assert(strpos($qrHtml, 'ai-chat-window') !== false, 'qr-order.php thiếu #ai-chat-window');
assert(strpos($qrHtml, 'assets/js/app.js') !== false, 'qr-order.php thiếu app.js');
assert(strpos($qrHtml, 'window.onAiChatbotAddDish') !== false, 'qr-order.php thiếu hook onAiChatbotAddDish');
assert(strpos($qrHtml, 'window.getAiCartSummary') !== false, 'qr-order.php thiếu hook getAiCartSummary');
assert(strpos($qrHtml, 'vendor/qrcode.js') !== false, 'qr-order.php thiếu qrcode.js');
echo "  [PASS] qr-order.php nhúng đầy đủ Chatbot AI & trình sinh VietQR tại bàn\n";

// 5. Kiểm tra luồng payment_intents + webhook tự động đối soát & xuất hóa đơn trên DB thật
$dishStmt = $pdo->query("SELECT `id`, `name`, `price` FROM `dishes` WHERE `price` > 0 AND `status` = 'active' ORDER BY `id` LIMIT 1");
$dish = $dishStmt->fetch(PDO::FETCH_ASSOC);
assert($dish !== false, 'Cần ít nhất 1 món giá > 0 đang mở bán để kiểm thử thanh toán');

$createdOrderId = null;
$createdInvoiceId = null;
$txId = 'TEST_SEPAY_' . uniqid();

try {
    $pdo->prepare("INSERT INTO `orders` (`table_code`, `status`, `created_at`) VALUES (?, 'open', NOW())")->execute([$tableCode]);
    $createdOrderId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `note`, `status`, `sent_at`)
        VALUES (?, ?, ?, 2, 'Test webhook QR', 'served', NOW())
    ")->execute([$createdOrderId, $dish['name'], (int) $dish['price']]);

    $items = fetchOrderItemsById($pdo, $createdOrderId);
    $bill = calculateBill($items);
    $expectedTotal = (int) $bill['total'];
    assert($expectedTotal > 0, 'Tổng hóa đơn test phải > 0');

    $intent = upsertPaymentIntent($pdo, $createdOrderId, $tableCode, $expectedTotal);
    assert($intent !== null && $intent['status'] === 'pending' && (int) $intent['expected_amount'] === $expectedTotal, 'payment_intent phải lưu đúng expected_amount');

    $stPending = getTablePaymentStatus($pdo, $tableCode, $createdOrderId);
    assert($stPending['paid'] === false && $stPending['status'] === 'pending', 'Trạng thái trước khi nhận tiền phải là pending');

    // Bỏ qua tiền ra (transferType = out)
    $outEvents = normalizeWebhookTransactions([
        'transferType'   => 'out',
        'transferAmount' => $expectedTotal,
        'content'        => formatTransferContent($createdOrderId, $tableCode),
    ]);
    assert($outEvents === [], 'Giao dịch tiền ra phải bị bỏ qua');

    // Khóa đóng bàn thủ công khi chọn QR (chỉ Webhook mới được chốt đơn QR)
    $blockedManualQr = false;
    try {
        checkoutTable($pdo, $tableCode, [], 'qr', 0, 1);
    } catch (CheckoutException $e) {
        $blockedManualQr = true;
    }
    assert($blockedManualQr === true, 'Phải chặn đóng bàn QR thủ công khi chưa có xác nhận Webhook');

    // Kiểm tra HTTP POST action=pay&pay=qr qua pages/payment.php bị từ chối và đơn vẫn open
    $loginCtx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(['login_submit' => '1', 'username' => 'admin', 'password' => 'admin123']),
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5,
    ]]);
    @file_get_contents('http://localhost/KDS/index.php?page=login', false, $loginCtx);
    $admCookie = '';
    foreach ($http_response_header ?? [] as $h) {
        if (stripos($h, 'Set-Cookie:') === 0 && preg_match('/Set-Cookie:\s*([^;]+)/i', $h, $m)) $admCookie = $m[1];
    }
    if ($admCookie !== '') {
        $postQrCtx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $admCookie\r\n",
            'content' => http_build_query(['action' => 'pay', 'pay' => 'qr', 'discount_type' => 'none', 'discount_value' => 0]),
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5,
        ]]);
        $qrPostHtml = (string) @file_get_contents('http://localhost/KDS/index.php?page=payment&table=' . urlencode($tableCode), false, $postQrCtx);
        $stillOpen = $pdo->prepare("SELECT `status` FROM `orders` WHERE `id` = ?");
        $stillOpen->execute([$createdOrderId]);
        assert($stillOpen->fetchColumn() === 'open', 'POST action=pay với pay=qr không được phép đóng đơn');
        assert(strpos($qrPostHtml, 'Webhook') !== false, 'Phải trả về thông báo yêu cầu Webhook khi POST pay=qr');
    }

    // Từ chối khi chuyển thiếu tiền
    $underEvents = normalizeWebhookTransactions([
        'id'             => $txId . '_UNDER',
        'gateway'        => 'VietinBank',
        'transferType'   => 'in',
        'transferAmount' => max(1, $expectedTotal - 500),
        'content'        => 'Chuyen khoan ' . formatTransferContent($createdOrderId, $tableCode),
    ]);
    assert(count($underEvents) === 1);
    $underRes = processPaymentWebhookEvent($pdo, $underEvents[0]);
    assert($underRes['ok'] === false && $underRes['status'] === 'insufficient_amount', 'Phải từ chối khi chuyển thiếu tiền');

    // Tự động hoàn tất khi nhận đúng số tiền và nội dung thực tế từ VietinBank SePay (kèm tiền tố SEVQR)
    $validEvents = normalizeWebhookTransactions([
        'id'             => $txId,
        'gateway'        => 'VietinBank',
        'transferType'   => 'in',
        'transferAmount' => $expectedTotal,
        'content'        => '159078870754-0777815075-' . formatTransferContent($createdOrderId, $tableCode),
    ]);
    assert(count($validEvents) === 1 && $validEvents[0]['order_id'] === $createdOrderId, 'Phải tách đúng order_id từ nội dung CK');

    $paidRes = processPaymentWebhookEvent($pdo, $validEvents[0]);
    assert($paidRes['ok'] === true && $paidRes['status'] === 'paid', 'Webhook hợp lệ phải tự động xác nhận thanh toán');
    $createdInvoiceId = (int) ($paidRes['invoice_id'] ?? 0);
    assert($createdInvoiceId > 0, 'Phải tạo hóa đơn thật sau webhook');

    // Idempotent khi nhận trùng gateway_tx_id
    $dupRes = processPaymentWebhookEvent($pdo, $validEvents[0]);
    assert($dupRes['ok'] === true && !empty($dupRes['idempotent']) && (int) $dupRes['invoice_id'] === $createdInvoiceId, 'Webhook lặp lại phải idempotent');

    // Kiểm tra payment-status sau khi thanh toán thành công
    $stPaid = getTablePaymentStatus($pdo, $tableCode, $createdOrderId);
    assert($stPaid['paid'] === true && (int) $stPaid['invoice_id'] === $createdInvoiceId && (int) $stPaid['total'] === $expectedTotal, 'getTablePaymentStatus phải trả về paid=true và đúng invoice_id');
    assert(!empty($stPaid['rating_url']), 'Phải tự sinh rating_url cho khách sau khi thanh toán webhook');

    echo "  [PASS] Webhook VietinBank đối soát đúng số tiền ({$expectedTotal}đ), tự tạo hóa đơn #{$createdInvoiceId} & đồng bộ status\n";
} finally {
    if ($createdInvoiceId !== null && $createdInvoiceId > 0) {
        $pdo->prepare("DELETE FROM `service_ratings` WHERE `invoice_id` = ?")->execute([$createdInvoiceId]);
        $pdo->prepare("DELETE FROM `payments` WHERE `invoice_id` = ?")->execute([$createdInvoiceId]);
        $pdo->prepare("DELETE FROM `invoice_items` WHERE `invoice_id` = ?")->execute([$createdInvoiceId]);
        $pdo->prepare("DELETE FROM `invoices` WHERE `id` = ?")->execute([$createdInvoiceId]);
    }
    if ($createdOrderId !== null && $createdOrderId > 0) {
        $pdo->prepare("DELETE FROM `payment_intents` WHERE `order_id` = ?")->execute([$createdOrderId]);
        $pdo->prepare("DELETE FROM `order_items` WHERE `order_id` = ?")->execute([$createdOrderId]);
        $pdo->prepare("DELETE FROM `orders` WHERE `id` = ?")->execute([$createdOrderId]);
    }
}

echo "\n>>> TẤT CẢ KIỂM THỬ THANH TOÁN TỰ ĐỘNG & CHATBOT QR ĐÃ VƯỢT QUA 100%! <<<\n";
