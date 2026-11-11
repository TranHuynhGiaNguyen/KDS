<?php
/**
 * SmartDine - Nghiệp vụ màn hình bếp (KDS) theo từng món.
 *
 * Màn hình bếp hiển thị mỗi bàn một ô, trong ô là các món đang chờ bếp (pending).
 * Bếp chạm vào một món: hệ thống in bill món và chuyển món sang cooking, món biến mất khỏi màn hình.
 * Nhân viên xác nhận "Đã ra bàn" ở trang chi tiết bàn để món chuyển sang served.
 * Món bị hủy vẫn hiện trong ô bàn một thời gian ngắn để bếp ngừng làm.
 */

require_once __DIR__ . '/orders.php';

const KITCHEN_CANCELLED_VISIBLE_MINUTES = 10;
const KITCHEN_WARNING_MINUTES = 10;
const KITCHEN_URGENT_MINUTES = 20;

const KITCHEN_TRANSITIONS = [
    'start' => ['from' => 'pending', 'to' => 'cooking'],
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
          AND (i.`status` = 'pending'
               OR (i.`status` = 'cancelled' AND i.`cancelled_at` >= ?))
        ORDER BY (i.`status` = 'cancelled') DESC, i.`sent_at`, i.`id`
    ");
    $stmt->execute([$cancelledSince]);

    return array_map(static function (array $row): array {
        $isActive = $row['status'] === 'pending';
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

function groupKitchenTicketsByTable(array $tickets): array {
    $tables = [];

    foreach ($tickets as $ticket) {
        $code = $ticket['table'];

        if (!isset($tables[$code])) {
            $tables[$code] = ['table' => $code, 'time' => $ticket['time'], 'minutes' => 0, 'urgency' => 'normal', 'items' => []];
        }

        $tables[$code]['items'][] = $ticket;

        if ($ticket['status'] === 'pending' && $ticket['minutes'] >= $tables[$code]['minutes']) {
            $tables[$code]['minutes'] = $ticket['minutes'];
            $tables[$code]['urgency'] = $ticket['urgency'];
            $tables[$code]['time']    = $ticket['time'];
        }
    }

    usort($tables, static function (array $a, array $b): int {
        return $b['minutes'] <=> $a['minutes'];
    });

    return $tables;
}

function countKitchenTickets(array $tickets): array {
    $counts = ['pending' => 0, 'cancelled' => 0, 'tables' => count(groupKitchenTicketsByTable($tickets))];

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