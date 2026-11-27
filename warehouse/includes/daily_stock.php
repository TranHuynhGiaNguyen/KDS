<?php
/**
 * SmartDine Kho - Quản lý tồn kho theo ngày để chốt tồn cuối ca và đối chiếu lượng bán ra KDS.
 */

function warehouse_ensure_daily_stock_schema(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $exists = $conn->query("SHOW TABLES LIKE 'daily_stock_snapshots'");
    if ($exists && $exists->num_rows > 0) {
        $checked = true;
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS `daily_stock_snapshots` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `snapshot_date` DATE NOT NULL,
            `product_id` INT(11) NOT NULL,
            `opening_qty` INT(11) NOT NULL DEFAULT 0,
            `in_qty` INT(11) NOT NULL DEFAULT 0,
            `other_out_qty` INT(11) NOT NULL DEFAULT 0,
            `closing_qty` INT(11) NOT NULL,
            `note` VARCHAR(255) NOT NULL DEFAULT '',
            `updated_by` VARCHAR(100) NOT NULL DEFAULT 'system',
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_daily_stock_date_prod` (`snapshot_date`, `product_id`),
            KEY `idx_daily_stock_date` (`snapshot_date`),
            CONSTRAINT `fk_daily_stock_product`
                FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $checked = true;
}

function warehouse_norm_name(string $name): string
{
    $collapsed = preg_replace('/\s+/u', ' ', trim($name));
    return mb_strtolower((string) $collapsed, 'UTF-8');
}

/**
 * Lấy bảng tồn kho theo ngày kết hợp số lượng món thực xuất bên KDS trong cùng ngày.
 */
function warehouse_get_daily_stock_rows(mysqli $conn, string $date): array
{
    warehouse_ensure_daily_stock_schema($conn);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $startSql = $date . ' 00:00:00';
    $endSql = $date . ' 23:59:59';
    $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));

    // 1. Tồn kho hiện tại
    $products = [];
    $res = $conn->query('SELECT id, name, sku, category, quantity, price FROM products ORDER BY name ASC, id ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $products[(int) $row['id']] = $row;
        }
    }

    // 2. Nhập / xuất trong ngày và biến động sau ngày đang xem
    $dayTx = [];
    $stmt = $conn->prepare("
        SELECT product_id,
               SUM(CASE WHEN created_at BETWEEN ? AND ? AND transaction_type = 'in' THEN quantity ELSE 0 END) AS day_in,
               SUM(CASE WHEN created_at BETWEEN ? AND ? AND transaction_type = 'out' THEN quantity ELSE 0 END) AS day_out,
               SUM(CASE WHEN created_at > ? THEN (CASE WHEN transaction_type = 'in' THEN quantity ELSE -quantity END) ELSE 0 END) AS net_after
        FROM stock_transactions
        GROUP BY product_id
    ");
    $stmt->bind_param('sssss', $startSql, $endSql, $startSql, $endSql, $endSql);
    $stmt->execute();
    $txRes = $stmt->get_result();
    while ($r = $txRes->fetch_assoc()) {
        $dayTx[(int) $r['product_id']] = [
            'day_in'    => (int) ($r['day_in'] ?? 0),
            'day_out'   => (int) ($r['day_out'] ?? 0),
            'net_after' => (int) ($r['net_after'] ?? 0),
        ];
    }
    $stmt->close();

    // 3. Snapshot hôm nay & tồn cuối ngày hôm qua
    $todaySnap = [];
    $prevSnap = [];
    $snapStmt = $conn->prepare('SELECT snapshot_date, product_id, opening_qty, in_qty, other_out_qty, closing_qty, note, updated_by, updated_at FROM daily_stock_snapshots WHERE snapshot_date IN (?, ?)');
    $snapStmt->bind_param('ss', $date, $prevDate);
    $snapStmt->execute();
    $snapRes = $snapStmt->get_result();
    while ($sr = $snapRes->fetch_assoc()) {
        $pid = (int) $sr['product_id'];
        if ($sr['snapshot_date'] === $date) {
            $todaySnap[$pid] = $sr;
        } elseif ($sr['snapshot_date'] === $prevDate) {
            $prevSnap[$pid] = (int) $sr['closing_qty'];
        }
    }
    $snapStmt->close();

    // 4. Lượng món thực xuất bán ra bên KDS trong cùng ngày (nếu các bảng KDS tồn tại)
    $kdsSoldByKey = [];
    try {
        $hasOi = $conn->query("SHOW TABLES LIKE 'order_items'");
        if ($hasOi && $hasOi->num_rows > 0) {
            $kdsStmt = $conn->prepare("
                SELECT MAX(TRIM(oi.item_name)) AS item_name, SUM(oi.qty) AS actual_out_qty
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                LEFT JOIN (
                    SELECT inv.order_id, 1 AS is_paid
                    FROM invoices inv
                    JOIN payments p ON p.invoice_id = inv.id
                    WHERE p.paid_at BETWEEN ? AND ?
                    GROUP BY inv.order_id
                ) p_paid ON p_paid.order_id = o.id
                WHERE oi.status <> 'cancelled'
                  AND (
                      (oi.status IN ('done', 'served') AND COALESCE(oi.done_at, oi.sent_at, oi.created_at) BETWEEN ? AND ?)
                      OR COALESCE(p_paid.is_paid, 0) = 1
                  )
                GROUP BY LOWER(TRIM(oi.item_name))
            ");
            $kdsStmt->bind_param('ssss', $startSql, $endSql, $startSql, $endSql);
            $kdsStmt->execute();
            $kdsRes = $kdsStmt->get_result();
            while ($kr = $kdsRes->fetch_assoc()) {
                $key = warehouse_norm_name((string) ($kr['item_name'] ?? ''));
                if ($key !== '') {
                    $kdsSoldByKey[$key] = ($kdsSoldByKey[$key] ?? 0) + (int) ($kr['actual_out_qty'] ?? 0);
                }
            }
            $kdsStmt->close();
        }
    } catch (Throwable $e) {
        error_log('daily_stock kds query: ' . $e->getMessage());
    }

    $rows = [];
    foreach ($products as $pid => $p) {
        $tx = $dayTx[$pid] ?? ['day_in' => 0, 'day_out' => 0, 'net_after' => 0];
        $currentQty = (int) $p['quantity'];
        $bookClosing = max(0, $currentQty - $tx['net_after']);
        $snap = $todaySnap[$pid] ?? null;

        $inQty = $tx['day_in'];
        $otherOutQty = $tx['day_out'];
        if (isset($prevSnap[$pid])) {
            $openingQty = (int) $prevSnap[$pid];
        } elseif ($snap !== null) {
            $openingQty = (int) $snap['opening_qty'];
        } else {
            $openingQty = max(0, $bookClosing - $inQty + $otherOutQty);
        }

        $availableQty = $openingQty + $inQty - $otherOutQty;
        $kdsSoldQty = (int) ($kdsSoldByKey[warehouse_norm_name((string) $p['name'])] ?? 0);
        $expectedClosing = $availableQty - $kdsSoldQty;
        $savedClosing = $snap !== null ? (int) $snap['closing_qty'] : null;
        $consumedQty = $savedClosing !== null ? ($availableQty - $savedClosing) : null;

        $rows[] = [
            'product_id'       => $pid,
            'name'             => $p['name'],
            'sku'              => $p['sku'],
            'category'         => $p['category'] ?? '',
            'current_qty'      => $currentQty,
            'opening_qty'      => $openingQty,
            'in_qty'           => $inQty,
            'other_out_qty'    => $otherOutQty,
            'available_qty'    => $availableQty,
            'kds_sold_qty'     => $kdsSoldQty,
            'expected_closing' => $expectedClosing,
            'saved_closing'    => $savedClosing,
            'consumed_qty'     => $consumedQty,
            'is_closed'        => ($snap !== null),
            'note'             => $snap['note'] ?? '',
            'updated_by'       => $snap['updated_by'] ?? '',
            'updated_at'       => $snap['updated_at'] ?? '',
        ];
    }

    return $rows;
}

/**
 * Lưu chốt tồn lại cuối ngày cho danh sách sản phẩm và tùy chọn đồng bộ số tồn hiện tại trong Kho.
 */
function warehouse_save_daily_stock_snapshots(
    mysqli $conn,
    string $date,
    array $closingByProductId,
    array $notesByProductId,
    string $actor,
    bool $syncCurrentStock = true
): int {
    warehouse_ensure_daily_stock_schema($conn);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Ngày chốt tồn không hợp lệ.');
    }

    $currentRows = warehouse_get_daily_stock_rows($conn, $date);
    $rowMap = [];
    foreach ($currentRows as $r) {
        $rowMap[(int) $r['product_id']] = $r;
    }

    $conn->begin_transaction();
    $savedCount = 0;
    try {
        $upsert = $conn->prepare("
            INSERT INTO `daily_stock_snapshots` (`snapshot_date`, `product_id`, `opening_qty`, `in_qty`, `other_out_qty`, `closing_qty`, `note`, `updated_by`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `opening_qty` = VALUES(`opening_qty`),
                `in_qty` = VALUES(`in_qty`),
                `other_out_qty` = VALUES(`other_out_qty`),
                `closing_qty` = VALUES(`closing_qty`),
                `note` = VALUES(`note`),
                `updated_by` = VALUES(`updated_by`)
        ");
        $updProduct = $conn->prepare('UPDATE `products` SET `quantity` = ? WHERE `id` = ?');

        foreach ($closingByProductId as $pidRaw => $closingRaw) {
            $pid = (int) $pidRaw;
            if (!isset($rowMap[$pid]) || $closingRaw === '' || $closingRaw === null) {
                continue;
            }
            $closingQty = max(0, (int) $closingRaw);
            $meta = $rowMap[$pid];
            $note = mb_substr(trim((string) ($notesByProductId[$pid] ?? '')), 0, 255);
            $opening = (int) $meta['opening_qty'];
            $inQty = (int) $meta['in_qty'];
            $otherOut = (int) $meta['other_out_qty'];

            $upsert->bind_param('siiiiiss', $date, $pid, $opening, $inQty, $otherOut, $closingQty, $note, $actor);
            $upsert->execute();

            if ($syncCurrentStock && $date === date('Y-m-d')) {
                $updProduct->bind_param('ii', $closingQty, $pid);
                $updProduct->execute();
            }
            $savedCount++;
        }

        $upsert->close();
        $updProduct->close();
        $conn->commit();
        return $savedCount;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
