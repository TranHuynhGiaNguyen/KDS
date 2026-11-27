<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Regression test: requiring checkout.php alone (like pages/payment.php does)
// must expose closeTableAssignmentInTx() and complete checkoutTable() cleanly in a fresh request.
require_once __DIR__ . '/../includes/checkout.php';

echo "=== KIỂM THỬ THANH TOÁN & ĐÓNG GÁN BÀN ===\n";

assert(function_exists('closeTableAssignmentInTx'), 'checkout.php phải nạp sẵn closeTableAssignmentInTx() từ tables.php');
echo "  [PASS] closeTableAssignmentInTx() sẵn sàng khi chỉ require checkout.php\n";

$pdo = getOrdersDb();
ensureTableAssignmentsSchema($pdo);

$allActive = getAllDiningTables('', 'active', '');
$emptyTables = array_values(array_filter($allActive, static fn(array $t): bool => empty($t['is_occupied'])));
assert(count($emptyTables) >= 1, 'Cần ít nhất 1 bàn trống active để kiểm thử thanh toán');

$tableCode = $emptyTables[0]['code'];
$cashierId = (int) $pdo->query("SELECT `id` FROM `users` WHERE `status` = 'active' ORDER BY `id` LIMIT 1")->fetchColumn();
assert($cashierId > 0, 'Cần ít nhất 1 tài khoản active để làm thu ngân test');

$waiterStmt = $pdo->query("SELECT `id` FROM `users` WHERE `role` = 'staff' AND `status` = 'active' ORDER BY `id` LIMIT 1");
$waiterVal = $waiterStmt->fetchColumn();
$waiterId = $waiterVal === false ? null : (int) $waiterVal;

$createdOrderId = null;
$createdInvoiceId = null;
$createdAssignId = null;

try {
    $resAssign = assignTable($pdo, [
        'table_code'  => $tableCode,
        'guest_count' => 2,
        'staff_id'    => $waiterId,
        'note'        => 'Test checkout fresh process',
    ]);
    assert($resAssign['success'] === true, 'Gán bàn test thất bại');
    $assignRow = getActiveTableAssignment($tableCode, $pdo);
    assert($assignRow !== null, 'Không đọc được table_assignment vừa tạo');
    $createdAssignId = (int) $assignRow['id'];

    $pdo->prepare("INSERT INTO `orders` (`table_code`, `status`, `created_at`) VALUES (?, 'open', NOW())")->execute([$tableCode]);
    $createdOrderId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `status`, `sent_at`) VALUES (?, 'Món Test Thanh Toán', 100000, 1, 'served', NOW())")
        ->execute([$createdOrderId]);

    // Execute checkoutTable() in a clean child PHP process where static schema caches are cold,
    // matching POST index.php?page=payment behavior.
    $checkoutInclude = var_export(realpath(__DIR__ . '/../includes/checkout.php'), true);
    $tableExport = var_export($tableCode, true);
    $waiterExport = var_export($waiterId, true);
    $childCode = <<<PHP
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once {$checkoutInclude};
\$pdo = getOrdersDb();
\$invoiceId = checkoutTable(\$pdo, {$tableExport}, [], 'cash', 200000, {$cashierId}, {$waiterExport}, 5, '');
if (\$pdo->inTransaction()) {
    throw new RuntimeException('Transaction should be committed.');
}
echo \$invoiceId;
PHP;

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open(escapeshellarg(PHP_BINARY) . ' -d assert.exception=1', $descriptors, $pipes);
    assert(is_resource($proc), 'Không khởi chạy được tiến trình PHP con');
    fwrite($pipes[0], $childCode);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);

    assert($exitCode === 0, "checkoutTable() trong process mới thất bại (exit={$exitCode}): {$stderr} {$stdout}");
    $createdInvoiceId = (int) trim($stdout);
    assert($createdInvoiceId > 0, "checkoutTable() phải trả về invoice_id hợp lệ, nhận: {$stdout}");

    $orderStatus = $pdo->query("SELECT `status` FROM `orders` WHERE `id` = " . (int) $createdOrderId)->fetchColumn();
    assert($orderStatus === 'paid', 'Đơn hàng phải chuyển sang trạng thái paid sau khi thanh toán');
    assert(getActiveTableAssignment($tableCode, $pdo) === null, 'Lượt gán bàn active phải được đóng sau khi thanh toán');

    $invoice = fetchInvoice($pdo, $createdInvoiceId);
    assert($invoice !== null && $invoice['table'] === $tableCode, 'fetchInvoice() phải đọc được hóa đơn vừa tạo');
    echo "  [PASS] checkoutTable() hoàn tất transaction sạch trên process mới (invoice #{$createdInvoiceId}, bàn {$tableCode})\n";
} finally {
    if ($createdInvoiceId !== null && $createdInvoiceId > 0) {
        $pdo->prepare("DELETE FROM `service_ratings` WHERE `invoice_id` = ?")->execute([$createdInvoiceId]);
        $pdo->prepare("DELETE FROM `payments` WHERE `invoice_id` = ?")->execute([$createdInvoiceId]);
        $pdo->prepare("DELETE FROM `invoice_items` WHERE `invoice_id` = ?")->execute([$createdInvoiceId]);
        $pdo->prepare("DELETE FROM `invoices` WHERE `id` = ?")->execute([$createdInvoiceId]);
    }
    if ($createdOrderId !== null) {
        $pdo->prepare("DELETE FROM `order_items` WHERE `order_id` = ?")->execute([$createdOrderId]);
        $pdo->prepare("DELETE FROM `orders` WHERE `id` = ?")->execute([$createdOrderId]);
    }
    if ($createdAssignId !== null) {
        $pdo->prepare("DELETE FROM `table_assignments` WHERE `id` = ?")->execute([$createdAssignId]);
    }
}

echo "\n>>> KIỂM THỬ THANH TOÁN ĐẠT 100%! <<<\n";
