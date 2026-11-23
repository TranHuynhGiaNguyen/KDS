<?php

/**
 * SmartDine - Đơn hàng và trạng thái từng món.
 *
 * Luồng của một món:
 *   pending (Chờ tiếp nhận) -> cooking (Đang nấu) -> done (Đã nấu xong)
 *   - Bếp bấm "In phiếu bếp": pending -> cooking, từ lúc này nhân viên không hủy món được nữa.
 *   - Bếp bấm "Hoàn thành món": cooking -> done, bên nhân viên nút "Hủy món" được thay bằng nhãn "Hoàn thành".
 *   - Nhân viên chỉ được hủy món khi bếp chưa in phiếu (pending); món hủy không tính tiền.
 *
 * Món mới của bàn phải được tạo với status = 'pending' và sent_at = giờ gọi món.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/orders_schema.php';
require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/http.php';

const ORDER_TIMEZONE = 'Asia/Ho_Chi_Minh';

const ORDER_ITEM_STATUSES = [
    'unsent'    => ['label' => 'Chờ tiếp nhận', 'class' => 'pending'],
    'pending'   => ['label' => 'Chờ tiếp nhận', 'class' => 'pending'],
    'cooking'   => ['label' => 'Đang nấu',      'class' => 'cooking'],
    'done'      => ['label' => 'Đã nấu xong',   'class' => 'done'],
    'served'    => ['label' => 'Hoàn thành',    'class' => 'ready'],
    'cancelled' => ['label' => 'Đã hủy',        'class' => 'cancelled'],
];

const ORDER_ITEM_ACTIONS = [
    'unsent'    => ['cancel'],
    'pending'   => ['cancel'],
    'cooking'   => [],
    'done'      => [],
    'served'    => [],
    'cancelled' => [],
];

const ORDER_ITEM_LOCKED_ACTIONS = [
    'cooking' => ['cancel'],
];

const ORDER_ITEM_MARKERS = [
    'done' => ['label' => 'Hoàn thành', 'class' => 'btn-complete'],
];

const ORDER_ACTION_BUTTONS = [
    'cancel' => ['label' => 'Hủy món', 'class' => 'btn-outline-danger'],
];

const ORDER_CANCEL_LOCKED_MESSAGE = 'Bếp đã in phiếu món này nên không thể hủy.';

function getOrdersDb(): PDO
{
    static $initialized = false;

    $pdo = getDbConnection();
    if ($pdo === null) {
        throw new RuntimeException('Không kết nối được cơ sở dữ liệu.');
    }

    if (!$initialized) {
        initOrderTables($pdo);
        migrateLegacyItemStatuses($pdo);
        $initialized = true;
    }

    return $pdo;
}

/** Món ở trạng thái cũ "unsent" (chưa xuất bill) được chuyển thành "pending" để bếp nhìn thấy. */
function migrateLegacyItemStatuses(PDO $pdo): void
{
    $pdo->exec("
        UPDATE `order_items`
        SET `status` = 'pending', `sent_at` = COALESCE(`sent_at`, `created_at`, NOW())
        WHERE `status` = 'unsent'
    ");
}

function normalizeTableCode(?string $tableCode): string
{
    if (!preg_match('/^[A-Z]\d{2}$/', (string) $tableCode)) {
        throw new InvalidArgumentException('Mã bàn không hợp lệ.');
    }

    return $tableCode;
}

function currentTimeForDb(): string
{
    return (new DateTime('now', new DateTimeZone(ORDER_TIMEZONE)))->format('Y-m-d H:i:s');
}

function fetchTableItems(PDO $pdo, string $tableCode): array
{
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

function formatInvoiceCode(int $orderId): string
{
    return 'HD' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
}

function fetchOpenOrderCreatedAt(PDO $pdo, string $tableCode): ?string
{
    $stmt = $pdo->prepare("SELECT `created_at` FROM `orders` WHERE `table_code` = ? AND `status` = 'open' ORDER BY `id` DESC LIMIT 1");
    $stmt->execute([$tableCode]);
    $createdAt = $stmt->fetchColumn();

    return $createdAt === false ? null : (string) $createdAt;
}

function findOrderItem(PDO $pdo, int $itemId): ?array
{
    $stmt = $pdo->prepare('SELECT `id`, `status` FROM `order_items` WHERE `id` = ?');
    $stmt->execute([$itemId]);

    return $stmt->fetch() ?: null;
}

/** Hủy món: chỉ thực hiện được khi bếp chưa in phiếu (các trạng thái có thao tác "cancel" trong ORDER_ITEM_ACTIONS). */
function cancelOrderItem(PDO $pdo, int $itemId): bool
{
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

function fetchItemTicket(PDO $pdo, int $itemId): ?array
{
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
