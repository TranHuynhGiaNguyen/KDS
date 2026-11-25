<?php

/**
 * SmartDine - Nghiệp vụ màn hình bếp (KDS) theo từng món.
 *
 * Màn hình bếp hiển thị mỗi bàn một ô, trong ô là các món đang chờ bếp làm (pending).
 *   - Bếp bấm "In phiếu bếp": không in vật lý và không qua bước "đang nấu",
 *     chuyển thẳng từ "pending" sang "done" (Đã nấu xong) và biến khỏi màn hình bếp.
 * Món bị Admin hủy sẽ biến khỏi màn hình bếp ngay ở lần làm mới kế tiếp.
 */

require_once __DIR__ . '/orders.php';

const KITCHEN_WARNING_MINUTES = 10;
const KITCHEN_URGENT_MINUTES = 20;

const KITCHEN_TRANSITIONS = [
    'start' => ['from' => 'pending', 'to' => 'done'],
    'print' => ['from' => 'pending', 'to' => 'done'],
];

const KITCHEN_STATIONS = [
    'hot'  => 'Bếp nóng',
    'soup' => 'Bếp súp / Lẩu',
    'cold' => 'Bếp lạnh / Salad',
    'bar'  => 'Quầy Bar / Nước',
];

function minutesSince(string $dateTime): int
{
    $timezone = new DateTimeZone(ORDER_TIMEZONE);
    $start    = new DateTime($dateTime, $timezone);
    $now      = new DateTime('now', $timezone);

    return max(0, intdiv($now->getTimestamp() - $start->getTimestamp(), 60));
}

function kitchenUrgency(int $minutes): string
{
    if ($minutes >= KITCHEN_URGENT_MINUTES) {
        return 'urgent';
    }

    return $minutes >= KITCHEN_WARNING_MINUTES ? 'warning' : 'normal';
}

function fetchKitchenTickets(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT i.`id`, i.`item_name`, i.`qty`, i.`note`, i.`status`, i.`sent_at`,
               o.`table_code`,
               COALESCE(
                   (SELECT d.`station` FROM `dishes` d WHERE d.`name` = i.`item_name` ORDER BY d.`id` LIMIT 1),
                   'hot'
               ) AS `station`
        FROM `order_items` i
        JOIN `orders` o ON o.`id` = i.`order_id`
        WHERE o.`status` = 'open'
          AND i.`sent_at` IS NOT NULL
          AND i.`status` = 'pending'
        ORDER BY i.`sent_at`, i.`id`
    ");

    return array_map(static function (array $row): array {
        $isPending = $row['status'] === 'pending';
        $minutes   = minutesSince($row['sent_at']);

        return [
            'id'      => (int) $row['id'],
            'table'   => $row['table_code'],
            'name'    => $row['item_name'],
            'qty'     => (int) $row['qty'],
            'note'    => (string) $row['note'],
            'status'  => $row['status'],
            'station' => $row['station'],
            'time'    => date('H:i', strtotime($row['sent_at'])),
            'minutes' => $minutes,
            'urgency' => $isPending ? kitchenUrgency($minutes) : 'normal',
        ];
    }, $stmt->fetchAll());
}

function groupKitchenTicketsByTable(array $tickets): array
{
    $tables = [];

    foreach ($tickets as $ticket) {
        $code = $ticket['table'];

        if (!isset($tables[$code])) {
            $tables[$code] = [
                'table'       => $code,
                'time'        => $ticket['time'],
                'minutes'     => $ticket['minutes'],
                'urgency'     => 'normal',
                'has_pending' => false,
                'items'       => [],
            ];
        }

        $tables[$code]['items'][] = $ticket;

        $isOldestPending = $ticket['status'] === 'pending'
            && (!$tables[$code]['has_pending'] || $ticket['minutes'] >= $tables[$code]['minutes']);

        if ($isOldestPending) {
            $tables[$code]['has_pending'] = true;
            $tables[$code]['minutes']     = $ticket['minutes'];
            $tables[$code]['urgency']     = $ticket['urgency'];
            $tables[$code]['time']        = $ticket['time'];
        }
    }

    usort($tables, static function (array $a, array $b): int {
        return [$b['has_pending'], $b['minutes']] <=> [$a['has_pending'], $a['minutes']];
    });

    return $tables;
}

function countKitchenTickets(array $tickets): array
{
    $counts = ['pending' => 0, 'cooking' => 0, 'tables' => count(groupKitchenTicketsByTable($tickets))];

    foreach ($tickets as $ticket) {
        if (isset($counts[$ticket['status']])) {
            $counts[$ticket['status']]++;
        }
    }

    return $counts;
}

/**
 * Chuyển trạng thái một món khi bếp bấm "In phiếu bếp": pending -> done.
 * Lưu đồng thời cooking_at (nếu chưa có) và done_at để giữ thống kê hiệu suất bếp.
 */
function advanceKitchenItem(PDO $pdo, int $itemId, string $action, int $userId): bool
{
    $transition = KITCHEN_TRANSITIONS[$action];

    $stmt = $pdo->prepare('
        UPDATE `order_items`
        SET `status` = ?, `sent_by` = COALESCE(`sent_by`, ?), `cooking_at` = COALESCE(`cooking_at`, NOW()), `done_at` = COALESCE(`done_at`, NOW())
        WHERE `id` = ? AND `status` = ?
    ');
    $stmt->execute([$transition['to'], $userId, $itemId, $transition['from']]);

    return $stmt->rowCount() === 1;
}
