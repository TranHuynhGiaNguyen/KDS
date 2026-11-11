<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/orders_schema.php';
require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/http.php';

const ORDER_TIMEZONE = 'Asia/Ho_Chi_Minh';

const ORDER_ITEM_STATUSES = [
    'unsent'    => ['label' => 'Chưa xuất bill',    'class' => 'unsent'],
    'pending'   => ['label' => 'Chờ bếp xử lý',     'class' => 'pending'],
    'cooking'   => ['label' => 'Bếp đang làm', 'class' => 'cooking'],
    'done'      => ['label' => 'Bếp đã xong',       'class' => 'done'],
    'served'    => ['label' => 'Đã phục vụ ra bàn', 'class' => 'ready'],
    'cancelled' => ['label' => 'Đã hủy',            'class' => 'cancelled'],
];

const ORDER_ITEM_ACTIONS = [
    'unsent'    => ['send', 'cancel'],
    'pending'   => ['ticket', 'cancel'],
    'cooking'   => ['ticket', 'serve', 'cancel'],
    'done'      => ['ticket', 'serve', 'cancel'],
    'served'    => ['ticket'],
    'cancelled' => [],
];

const ORDER_ACTION_BUTTONS = [
    'send'   => ['label' => 'Xuất bill', 'class' => 'btn-primary'],
    'serve'  => ['label' => 'Đã ra bàn', 'class' => 'btn-primary'],
    'ticket' => ['label' => 'Xem bill',  'class' => 'btn-outline'],
    'cancel' => ['label' => 'Hủy món',   'class' => 'btn-outline-danger'],
];

function getOrdersDb(): PDO {
    static $initialized = false;

    $pdo = getDbConnection();
    if ($pdo === null) {
        throw new RuntimeException('Không kết nối được cơ sở dữ liệu.');
    }

    if (!$initialized) {
        initOrderTables($pdo);
        $initialized = true;
    }

    return $pdo;
}

function normalizeTableCode(?string $tableCode): string {
    return preg_match('/^[A-Z]\d{2}$/', (string) $tableCode) ? $tableCode : 'B05';
}

function currentTimeForDb(): string {
    return (new DateTime('now', new DateTimeZone(ORDER_TIMEZONE)))->format('Y-m-d H:i:s');
}

function fetchTableItems(PDO $pdo, string $tableCode): array {
    $stmt = $pdo->prepare("
        SELECT i.`id`, i.`item_name` AS `name`, i.`unit_price` AS `price`, i.`qty`, i.`note`, i.`status`, i.`sent_at`
        FROM `order_items` i
        JOIN `orders` o ON o.`id` = i.`order_id`
        WHERE o.`table_code` = ? AND o.`status` = 'open'
        ORDER BY i.`id`
    ");
    $stmt->execute([$tableCode]);

    return $stmt->fetchAll();
}

function formatInvoiceCode(int $orderId): string {
    return 'HD' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
}

function fetchOpenOrderCreatedAt(PDO $pdo, string $tableCode): ?string {
    $stmt = $pdo->prepare("SELECT `created_at` FROM `orders` WHERE `table_code` = ? AND `status` = 'open' ORDER BY `id` DESC LIMIT 1");
    $stmt->execute([$tableCode]);
    $createdAt = $stmt->fetchColumn();

    return $createdAt === false ? null : (string) $createdAt;
}

function findOrderItem(PDO $pdo, int $itemId): ?array {
    $stmt = $pdo->prepare('SELECT `id`, `status` FROM `order_items` WHERE `id` = ?');
    $stmt->execute([$itemId]);

    return $stmt->fetch() ?: null;
}

function sendItemToKitchen(PDO $pdo, int $itemId, int $userId): bool {
    $stmt = $pdo->prepare("
        UPDATE `order_items` SET `status` = 'pending', `sent_by` = ?, `sent_at` = ?
        WHERE `id` = ? AND `status` = 'unsent'
    ");
    $stmt->execute([$userId, currentTimeForDb(), $itemId]);

    return $stmt->rowCount() === 1;
}

function markItemServed(PDO $pdo, int $itemId): bool {
    $stmt = $pdo->prepare("UPDATE `order_items` SET `status` = 'served' WHERE `id` = ? AND `status` IN ('cooking', 'done')");
    $stmt->execute([$itemId]);

    return $stmt->rowCount() === 1;
}

function cancelOrderItem(PDO $pdo, int $itemId): bool {
    $cancellable = array_keys(array_filter(
        ORDER_ITEM_ACTIONS,
        static fn(array $actions): bool => in_array('cancel', $actions, true)
    ));
    $placeholders = implode(', ', array_fill(0, count($cancellable), '?'));

    $stmt = $pdo->prepare("
        UPDATE `order_items` SET `status` = 'cancelled', `cancelled_at` = ?
        WHERE `id` = ? AND `status` IN ($placeholders)
    ");
    $stmt->execute(array_merge([currentTimeForDb(), $itemId], $cancellable));

    return $stmt->rowCount() === 1;
}

function fetchItemTicket(PDO $pdo, int $itemId): ?array {
    $stmt = $pdo->prepare("
        SELECT i.`item_name`, i.`qty`, i.`sent_at`, o.`table_code`, u.`fullname`
        FROM `order_items` i
        JOIN `orders` o ON o.`id` = i.`order_id`
        LEFT JOIN `users` u ON u.`id` = i.`sent_by`
        WHERE i.`id` = ? AND i.`sent_at` IS NOT NULL AND i.`status` <> 'cancelled'
    ");
    $stmt->execute([$itemId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'staff' => $row['fullname'] ?? 'Không rõ',
        'table' => $row['table_code'],
        'time'  => date('H:i', strtotime($row['sent_at'])),
        'name'  => $row['item_name'],
        'qty'   => (int) $row['qty'],
    ];
}