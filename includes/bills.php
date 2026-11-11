<?php
/**
 * SmartDine - Dữ liệu cho trang lịch sử bill.
 * Gồm bill món (phiếu gửi bếp) và hóa đơn đã thanh toán, có lọc theo bàn và ngày.
 */

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/checkout.php';

const BILLS_LIST_LIMIT = 100;

function normalizeBillDate(?string $date): string {
    return $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
}

function fetchItemBills(PDO $pdo, string $tableCode, string $date): array {
    $where  = ['i.`sent_at` IS NOT NULL'];
    $params = [];

    if ($tableCode !== '') {
        $where[]  = 'o.`table_code` = ?';
        $params[] = $tableCode;
    }

    if ($date !== '') {
        $where[]  = 'i.`sent_at` >= ? AND i.`sent_at` < ?';
        $params[] = $date . ' 00:00:00';
        $params[] = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
    }

    $stmt = $pdo->prepare('
        SELECT i.`id`, i.`item_name`, i.`qty`, i.`note`, i.`status`, i.`sent_at`, o.`table_code`, u.`fullname`
        FROM `order_items` i
        JOIN `orders` o ON o.`id` = i.`order_id`
        LEFT JOIN `users` u ON u.`id` = i.`sent_by`
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY i.`sent_at` DESC, i.`id` DESC
        LIMIT ' . BILLS_LIST_LIMIT);
    $stmt->execute($params);

    return array_map(static fn(array $row): array => [
        'id'        => (int) $row['id'],
        'table'     => $row['table_code'],
        'name'      => $row['item_name'],
        'qty'       => (int) $row['qty'],
        'note'      => (string) $row['note'],
        'status'    => $row['status'],
        'staff'     => $row['fullname'] ?? 'Không rõ',
        'sent_at'   => date('d/m/Y H:i', strtotime($row['sent_at'])),
    ], $stmt->fetchAll());
}

function fetchInvoiceList(PDO $pdo, string $tableCode, string $date): array {
    $where  = ['1 = 1'];
    $params = [];

    if ($tableCode !== '') {
        $where[]  = 'v.`table_code` = ?';
        $params[] = $tableCode;
    }

    if ($date !== '') {
        $where[]  = 'p.`paid_at` >= ? AND p.`paid_at` < ?';
        $params[] = $date . ' 00:00:00';
        $params[] = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
    }

    $stmt = $pdo->prepare('
        SELECT v.`id`, v.`order_id`, v.`table_code`, v.`total`, p.`method`, p.`paid_at`, u.`fullname`
        FROM `invoices` v
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        LEFT JOIN `users` u ON u.`id` = p.`cashier_id`
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.`paid_at` DESC, v.`id` DESC
        LIMIT ' . BILLS_LIST_LIMIT);
    $stmt->execute($params);

    return array_map(static fn(array $row): array => [
        'id'      => (int) $row['id'],
        'code'    => formatInvoiceCode((int) $row['order_id']),
        'table'   => $row['table_code'],
        'total'   => (int) $row['total'],
        'method'  => PAYMENT_METHODS[$row['method']] ?? $row['method'],
        'paid_at' => date('d/m/Y H:i', strtotime($row['paid_at'])),
        'cashier' => $row['fullname'] ?? 'Không rõ',
    ], $stmt->fetchAll());
}