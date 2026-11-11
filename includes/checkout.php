<?php
/**
 * SmartDine - Hoàn tất thanh toán một bàn.
 *
 * checkoutTable() chạy trong một transaction: đóng đơn (open -> paid), lưu hóa đơn,
 * các dòng món và giao dịch. Số tiền được tính lại ở server, không tin dữ liệu từ trình duyệt.
 */

require_once __DIR__ . '/orders.php';

class CheckoutException extends DomainException {
}

const PAYMENT_METHODS = [
    'cash' => 'Tiền mặt',
    'qr'   => 'Chuyển khoản QR',
];

const CHECKOUT_BLOCKING_STATUSES = [
    'unsent'  => 'chưa xuất bill',
    'pending' => 'đang chờ bếp nhận',
    'cooking' => 'bếp đang chế biến',
];

function findCheckoutBlockers(array $items): array {
    $blockers = [];

    foreach ($items as $item) {
        if (isset(CHECKOUT_BLOCKING_STATUSES[$item['status']])) {
            $blockers[] = $item['name'] . ' (' . CHECKOUT_BLOCKING_STATUSES[$item['status']] . ')';
        }
    }

    return $blockers;
}

function checkoutBlockersMessage(array $blockers): string {
    return 'Chưa thể thanh toán, cần xử lý các món sau: ' . implode(', ', $blockers) . '.';
}

function fetchOpenOrderId(PDO $pdo, string $tableCode): ?int {
    $stmt = $pdo->prepare("SELECT `id` FROM `orders` WHERE `table_code` = ? AND `status` = 'open' ORDER BY `id` DESC LIMIT 1");
    $stmt->execute([$tableCode]);
    $orderId = $stmt->fetchColumn();

    return $orderId === false ? null : (int) $orderId;
}

function checkoutTable(PDO $pdo, string $tableCode, array $discount, string $method, int $cashReceived, int $cashierId, bool $transferConfirmed = false): int {
    if (!isset(PAYMENT_METHODS[$method])) {
        throw new CheckoutException('Phương thức thanh toán không hợp lệ.');
    }

    $orderId = fetchOpenOrderId($pdo, $tableCode);
    if ($orderId === null) {
        throw new CheckoutException('Bàn ' . $tableCode . ' không có đơn đang mở, có thể đã được thanh toán.');
    }

    $pdo->beginTransaction();

    try {
        $claim = $pdo->prepare("UPDATE `orders` SET `status` = 'paid' WHERE `id` = ? AND `status` = 'open'");
        $claim->execute([$orderId]);
        if ($claim->rowCount() !== 1) {
            throw new CheckoutException('Bàn này vừa được thanh toán ở thao tác khác.');
        }

        $items    = fetchOrderItemsById($pdo, $orderId);
        $blockers = findCheckoutBlockers($items);
        if ($blockers !== []) {
            throw new CheckoutException(checkoutBlockersMessage($blockers));
        }

        try {
            $bill = calculateBill($items, $discount);
        } catch (InvalidArgumentException $e) {
            throw new CheckoutException($e->getMessage(), 0, $e);
        }

        if ($bill['total'] <= 0) {
            throw new CheckoutException('Hóa đơn không có món nào để thanh toán.');
        }

        if ($method === 'cash' && $cashReceived < $bill['total']) {
            throw new CheckoutException('Tiền khách đưa chưa đủ. Cần tối thiểu ' . formatMoney($bill['total']) . '.');
        }

        if ($method === 'qr' && !$transferConfirmed) {
            throw new CheckoutException('Vui lòng xác nhận đã kiểm tra tiền chuyển khoản vào tài khoản trước khi thanh toán.');
        }

        $invoiceId = insertInvoice($pdo, $orderId, $tableCode, $bill);
        insertPayment($pdo, $invoiceId, $bill['total'], $method, $cashReceived, $cashierId);

        $pdo->commit();

        return $invoiceId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function fetchOrderItemsById(PDO $pdo, int $orderId): array {
    $stmt = $pdo->prepare("
        SELECT `id`, `item_name` AS `name`, `unit_price` AS `price`, `qty`, `note`, `status`
        FROM `order_items` WHERE `order_id` = ? ORDER BY `id`
    ");
    $stmt->execute([$orderId]);

    return $stmt->fetchAll();
}

function insertInvoice(PDO $pdo, int $orderId, string $tableCode, array $bill): int {
    $stmt = $pdo->prepare("
        INSERT INTO `invoices`
            (`order_id`, `table_code`, `subtotal`, `discount_type`, `discount_value`, `discount_amount`,
             `service_fee`, `vat`, `total`, `service_rate`, `vat_rate`, `created_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderId, $tableCode, $bill['subtotal'], $bill['discount_type'], $bill['discount_value'],
        $bill['discount_amount'], $bill['service_fee'], $bill['vat'], $bill['total'],
        $bill['service_rate'], $bill['vat_rate'], currentTimeForDb(),
    ]);
    $invoiceId = (int) $pdo->lastInsertId();

    $lineStmt = $pdo->prepare('INSERT INTO `invoice_items` (`invoice_id`, `item_name`, `unit_price`, `qty`, `amount`) VALUES (?, ?, ?, ?, ?)');
    foreach ($bill['lines'] as $line) {
        $lineStmt->execute([$invoiceId, $line['name'], $line['price'], $line['qty'], $line['amount']]);
    }

    return $invoiceId;
}

function insertPayment(PDO $pdo, int $invoiceId, int $total, string $method, int $cashReceived, int $cashierId): void {
    $isCash   = $method === 'cash';
    $received = $isCash ? $cashReceived : 0;

    $stmt = $pdo->prepare("
        INSERT INTO `payments` (`invoice_id`, `method`, `amount`, `cash_received`, `change_amount`, `cashier_id`, `paid_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$invoiceId, $method, $total, $received, $isCash ? $received - $total : 0, $cashierId, currentTimeForDb()]);
}

function fetchInvoice(PDO $pdo, int $invoiceId): ?array {
    $stmt = $pdo->prepare("
        SELECT v.*, p.`method`, p.`cash_received`, p.`change_amount`, p.`paid_at`, u.`fullname` AS `cashier`,
               o.`created_at` AS `time_in`
        FROM `invoices` v
        JOIN `orders` o ON o.`id` = v.`order_id`
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        LEFT JOIN `users` u ON u.`id` = p.`cashier_id`
        WHERE v.`id` = ?
    ");
    $stmt->execute([$invoiceId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $lineStmt = $pdo->prepare('SELECT `item_name` AS `name`, `unit_price` AS `price`, `qty`, `amount` FROM `invoice_items` WHERE `invoice_id` = ? ORDER BY `id`');
    $lineStmt->execute([$invoiceId]);

    return [
        'id'            => (int) $row['id'],
        'code'          => formatInvoiceCode((int) $row['order_id']),
        'table'         => $row['table_code'],
        'paid_at'       => date('d/m/Y H:i', strtotime($row['paid_at'])),
        'paid_at_raw'   => $row['paid_at'],
        'time_in_raw'   => $row['time_in'],
        'cashier'       => $row['cashier'] ?? 'Không rõ',
        'method'        => $row['method'],
        'cash_received' => (int) $row['cash_received'],
        'change'        => (int) $row['change_amount'],
        'bill'          => [
            'lines'           => array_map(static fn(array $line): array => [
                'name'   => $line['name'],
                'price'  => (int) $line['price'],
                'qty'    => (int) $line['qty'],
                'amount' => (int) $line['amount'],
            ], $lineStmt->fetchAll()),
            'subtotal'        => (int) $row['subtotal'],
            'discount_type'   => $row['discount_type'],
            'discount_value'  => (int) $row['discount_value'],
            'discount_amount' => (int) $row['discount_amount'],
            'service_fee'     => (int) $row['service_fee'],
            'vat'             => (int) $row['vat'],
            'total'           => (int) $row['total'],
            'vat_rate'        => (float) $row['vat_rate'],
            'service_rate'    => (float) $row['service_rate'],
        ],
    ];
}