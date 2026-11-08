<?php
/**
 * SmartDine - Trạng thái bàn ăn suy ra từ đơn hàng trong database.
 * Bàn có đơn đang mở và ít nhất một món chưa hủy là "có khách".
 */

require_once __DIR__ . '/orders.php';

function fetchOccupiedTables(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT o.`table_code`, i.`item_name` AS `name`, i.`unit_price` AS `price`, i.`qty`, i.`status`
        FROM `order_items` i
        JOIN `orders` o ON o.`id` = i.`order_id`
        WHERE o.`status` = 'open'
        ORDER BY o.`table_code`, i.`id`
    ");

    $itemsByTable = [];
    foreach ($stmt->fetchAll() as $row) {
        $itemsByTable[$row['table_code']][] = $row;
    }

    $occupied = [];
    foreach ($itemsByTable as $tableCode => $items) {
        try {
            $bill = calculateBill($items);
        } catch (InvalidArgumentException $e) {
            error_log('tables: ' . $tableCode . ' ' . $e->getMessage());
            continue;
        }

        if ($bill['lines'] !== []) {
            $occupied[$tableCode] = ['item_count' => count($bill['lines']), 'total' => $bill['total']];
        }
    }

    return $occupied;
}