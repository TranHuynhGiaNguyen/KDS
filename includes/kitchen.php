<?php
/**
 * SmartDine - Nghiệp vụ màn hình bếp (KDS) theo từng món.
 *
 * Bếp làm việc trên từng món (ticket), không phải cả đơn:
 * pending (chờ bếp) -> cooking (đang nấu) -> done (bếp xong, chờ bưng).
 * Nhân viên xác nhận "Đã ra bàn" ở trang chi tiết bàn để món chuyển sang served.
 */

require_once __DIR__ . '/orders.php';

const KITCHEN_CANCELLED_VISIBLE_MINUTES = 10;
const KITCHEN_WARNING_MINUTES = 10;
const KITCHEN_URGENT_MINUTES = 20;

const KITCHEN_TRANSITIONS = [
    'start'  => ['from' => 'pending', 'to' => 'cooking'],
    'finish' => ['from' => 'cooking', 'to' => 'done'],
];

const KITCHEN_STATIONS = [
    'hot'  => 'Bếp nóng',
    'soup' => 'Bếp súp / Lẩu',
    'cold' => 'Bếp lạnh / Salad',
    'bar'  => 'Quầy Bar / Nước',
];

function minutesSince(string $dateTime): int {
    $timezone = new DateTimeZone(ORDER_TIMEZONE);
    $start    = new DateTime($dateTime, $timezone);
    $now      = new DateTime('now', $timezone);

    return max(0, intdiv($now->getTimestamp() - $start->getTimestamp(), 60));
}

function kitchenUrgency(int $minutes): string {
    if ($minutes >= KITCHEN_URGENT_MINUTES) {
        return 'urgent';
    }

    return $minutes >= KITCHEN_WARNING_MINUTES ? 'warning' : 'normal';
}

function fetchKitchenTickets(PDO $pdo): array {
    $cancelledSince = (new DateTime(
        '-' . KITCHEN_CANCELLED_VISIBLE_MINUTES . ' minutes',
        new DateTimeZone(ORDER_TIMEZONE)
    ))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        SELECT i.`id`, i.`item_name`, i.`qty`, i.`note`, i.`status`, i.`sent_at`,
               o.`table_code`, COALESCE(d.`station`, 'hot') AS `station`
        FROM `order_items` i
        JOIN `orders` o ON o.`id` = i.`order_id`
        LEFT JOIN `dishes` d ON d.`name` = i.`item_name`
        WHERE o.`status` = 'open'
          AND i.`sent_at` IS NOT NULL
          AND (i.`status` IN ('pending', 'cooking', 'done')
               OR (i.`status` = 'cancelled' AND i.`cancelled_at` >= ?))
        ORDER BY (i.`status` = 'cancelled') DESC, i.`sent_at`, i.`id`
    ");
    $stmt->execute([$cancelledSince]);

    return array_map(static function (array $row): array {
        $isActive = in_array($row['status'], ['pending', 'cooking'], true);
        $minutes  = minutesSince($row['sent_at']);

        return [
            'id'        => (int) $row['id'],
            'table'     => $row['table_code'],
            'name'      => $row['item_name'],
            'qty'       => (int) $row['qty'],
            'note'      => (string) $row['note'],
            'status'    => $row['status'],
            'station'   => $row['station'],
            'time'      => date('H:i', strtotime($row['sent_at'])),
            'minutes'   => $minutes,
            'urgency'   => $isActive ? kitchenUrgency($minutes) : 'normal',
        ];
    }, $stmt->fetchAll());
}

function countKitchenTickets(array $tickets): array {
    $counts = ['all' => count($tickets), 'pending' => 0, 'cooking' => 0, 'done' => 0];

    foreach ($tickets as $ticket) {
        if (isset($counts[$ticket['status']])) {
            $counts[$ticket['status']]++;
        }
    }

    return $counts;
}

function advanceKitchenItem(PDO $pdo, int $itemId, string $action): bool {
    $transition = KITCHEN_TRANSITIONS[$action];

    $stmt = $pdo->prepare('UPDATE `order_items` SET `status` = ? WHERE `id` = ? AND `status` = ?');
    $stmt->execute([$transition['to'], $itemId, $transition['from']]);

    return $stmt->rowCount() === 1;
}