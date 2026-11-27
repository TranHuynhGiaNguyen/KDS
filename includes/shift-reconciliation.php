<?php
/**
 * SmartDine - Báo cáo món thực xuất bán được trong ngày (chia theo danh mục Bếp nóng, Rau...)
 * kết hợp tồn theo ngày trong Kho (Warehouse) để đối chiếu kết ca.
 */

require_once __DIR__ . '/kitchen.php';
require_once __DIR__ . '/revenue.php';

/**
 * Chuẩn hóa tên món / sản phẩm để ghép nối không phân biệt hoa thường và khoảng trắng thừa.
 */
function normalizeReconcileKey(string $name): string
{
    $collapsed = preg_replace('/\s+/u', ' ', trim($name));
    return mb_strtolower((string) $collapsed, 'UTF-8');
}

/**
 * Xác định danh mục phân nhóm cho món bán ra: Rau / Bếp nóng / Bếp súp / Bếp lạnh / Quầy Bar / Khác.
 */
function resolveDishCategoryGroup(?string $station, ?string $categoryName): array
{
    $cat = trim((string) ($categoryName ?? ''));
    $catLower = mb_strtolower($cat, 'UTF-8');
    $st = trim((string) ($station ?? ''));

    // Ưu tiên tách nhóm Rau / Salad riêng nếu danh mục thực đơn có chữ "rau"
    if ($catLower !== '' && (mb_strpos($catLower, 'rau', 0, 'UTF-8') !== false)) {
        return ['key' => 'cat_rau', 'label' => 'Rau / Món rau', 'order' => 2];
    }

    switch ($st) {
        case 'hot':
            return ['key' => 'hot', 'label' => 'Bếp nóng', 'order' => 1];
        case 'cold':
            return ['key' => 'cold', 'label' => 'Bếp lạnh / Rau & Salad', 'order' => 2];
        case 'soup':
            return ['key' => 'soup', 'label' => 'Bếp súp / Lẩu', 'order' => 3];
        case 'bar':
            return ['key' => 'bar', 'label' => 'Quầy Bar / Đồ uống', 'order' => 4];
        default:
            if ($cat !== '') {
                return ['key' => 'cat_' . md5($catLower), 'label' => $cat, 'order' => 5];
            }
            return ['key' => 'other', 'label' => 'Món khác', 'order' => 9];
    }
}

/**
 * Đảm bảo bảng daily_stock_snapshots tồn tại (an toàn: kiểm tra SHOW TABLES trước, không chạy DDL trong transaction).
 */
function ensureDailyStockSchema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    if ($pdo->query("SHOW TABLES LIKE 'daily_stock_snapshots'")->fetchColumn() !== false) {
        $initialized = true;
        return;
    }

    if ($pdo->inTransaction()) {
        throw new RuntimeException('Cannot create daily_stock_snapshots inside an active transaction.');
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `daily_stock_snapshots` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `snapshot_date` DATE NOT NULL,
            `product_id` INT NOT NULL,
            `opening_qty` INT NOT NULL DEFAULT 0,
            `in_qty` INT NOT NULL DEFAULT 0,
            `other_out_qty` INT NOT NULL DEFAULT 0,
            `closing_qty` INT NOT NULL,
            `note` VARCHAR(255) NOT NULL DEFAULT '',
            `updated_by` VARCHAR(100) NOT NULL DEFAULT 'system',
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_daily_stock_date_prod` (`snapshot_date`, `product_id`),
            INDEX `idx_daily_stock_date` (`snapshot_date`),
            CONSTRAINT `fk_daily_stock_product`
                FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $initialized = true;
}

/**
 * Truy vấn báo cáo CHỈ GỒM các món thực tế đã bán/thực xuất được trong ngày,
 * phân nhóm theo danh mục (Bếp nóng, Rau/Salad, Bếp súp, Quầy Bar...) và kèm tồn theo ngày của Warehouse.
 */
function getShiftReconciliationReport(PDO $pdo, string $date): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $startSql = $date . ' 00:00:00';
    $endSql = $date . ' 23:59:59';
    $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));

    // 1. Danh mục món ăn KDS kèm quầy bếp & tên danh mục thực đơn
    $dishCatalog = [];
    $dishSql = 'SELECT d.`name`, d.`station`, d.`status`, COALESCE(c.`name`, \'\') AS `category_name`
                FROM `dishes` d
                LEFT JOIN `categories` c ON c.`id` = d.`category_id`
                ORDER BY d.`id` ASC';
    foreach ($pdo->query($dishSql)->fetchAll(PDO::FETCH_ASSOC) as $dRow) {
        $key = normalizeReconcileKey((string) ($dRow['name'] ?? ''));
        if ($key !== '' && !isset($dishCatalog[$key])) {
            $dishCatalog[$key] = [
                'name'          => trim((string) $dRow['name']),
                'station'       => (string) ($dRow['station'] ?? ''),
                'category_name' => trim((string) ($dRow['category_name'] ?? '')),
            ];
        }
    }

    // 2. CHỈ lấy các món thực sự đã bán / thực xuất trong ngày (kds_actual_qty > 0, loại trừ cancelled)
    $kdsStmt = $pdo->prepare("
        SELECT
            MAX(TRIM(oi.`item_name`)) AS `item_name`,
            SUM(oi.`qty`) AS `actual_out_qty`
        FROM `order_items` oi
        JOIN `orders` o ON o.`id` = oi.`order_id`
        LEFT JOIN (
            SELECT inv.`order_id`, 1 AS `is_paid`
            FROM `invoices` inv
            JOIN `payments` p ON p.`invoice_id` = inv.`id`
            WHERE p.`paid_at` BETWEEN :paid_start AND :paid_end
            GROUP BY inv.`order_id`
        ) p_paid ON p_paid.`order_id` = o.`id`
        WHERE oi.`status` <> 'cancelled'
          AND (
              (oi.`status` IN ('done', 'served') AND COALESCE(oi.`done_at`, oi.`sent_at`, oi.`created_at`) BETWEEN :item_start AND :item_end)
              OR COALESCE(p_paid.`is_paid`, 0) = 1
          )
        GROUP BY LOWER(TRIM(oi.`item_name`))
        HAVING SUM(oi.`qty`) > 0
    ");
    $kdsStmt->execute([
        ':paid_start' => $startSql,
        ':paid_end'   => $endSql,
        ':item_start' => $startSql,
        ':item_end'   => $endSql,
    ]);

    $kdsMap = [];
    foreach ($kdsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = normalizeReconcileKey((string) ($row['item_name'] ?? ''));
        $qty = (int) ($row['actual_out_qty'] ?? 0);
        if ($key === '' || $qty <= 0) {
            continue;
        }
        if (!isset($kdsMap[$key])) {
            $kdsMap[$key] = [
                'item_name'      => (string) $row['item_name'],
                'actual_out_qty' => 0,
            ];
        }
        $kdsMap[$key]['actual_out_qty'] += $qty;
    }

    // 3. Món còn đang chờ trên bàn mở trong ngày (cảnh báo trước khi chốt ca)
    $pendingStmt = $pdo->prepare("
        SELECT MAX(TRIM(oi.`item_name`)) AS `item_name`, SUM(oi.`qty`) AS `pending_qty`
        FROM `order_items` oi
        JOIN `orders` o ON o.`id` = oi.`order_id`
        WHERE o.`status` = 'open'
          AND oi.`status` IN ('unsent', 'pending', 'cooking')
          AND oi.`created_at` BETWEEN :p_start AND :p_end
        GROUP BY LOWER(TRIM(oi.`item_name`))
    ");
    $pendingStmt->execute([':p_start' => $startSql, ':p_end' => $endSql]);
    $totalPendingOpenQty = 0;
    $pendingMap = [];
    foreach ($pendingStmt->fetchAll(PDO::FETCH_ASSOC) as $pRow) {
        $key = normalizeReconcileKey((string) ($pRow['item_name'] ?? ''));
        $qty = (int) ($pRow['pending_qty'] ?? 0);
        if ($key !== '' && $qty > 0) {
            $pendingMap[$key] = ($pendingMap[$key] ?? 0) + $qty;
            $totalPendingOpenQty += $qty;
        }
    }

    // 4. Dữ liệu Tồn theo ngày trong Kho (chỉ ghép nối cho các món đã bán trong ngày)
    $warehouseReady = false;
    $productCatalog = [];
    $warehouseProductCount = 0;
    $closedSnapshotCount = 0;

    try {
        $hasProducts = $pdo->query("SHOW TABLES LIKE 'products'")->fetchColumn() !== false;
        $hasTx = $pdo->query("SHOW TABLES LIKE 'stock_transactions'")->fetchColumn() !== false;
        $warehouseReady = $hasProducts && $hasTx;

        if ($warehouseReady) {
            ensureDailyStockSchema($pdo);

            $prodRows = $pdo->query('SELECT `id`, `name`, `sku`, `category`, `quantity` FROM `products` ORDER BY `id` ASC')->fetchAll(PDO::FETCH_ASSOC);
            $warehouseProductCount = count($prodRows);

            $txByProd = [];
            $txStmt = $pdo->prepare("
                SELECT `product_id`,
                       SUM(CASE WHEN `created_at` BETWEEN :s1 AND :e1 AND `transaction_type` = 'in' THEN `quantity` ELSE 0 END) AS `day_in`,
                       SUM(CASE WHEN `created_at` BETWEEN :s2 AND :e2 AND `transaction_type` = 'out' THEN `quantity` ELSE 0 END) AS `day_out`,
                       SUM(CASE WHEN `created_at` > :e3 THEN (CASE WHEN `transaction_type` = 'in' THEN `quantity` ELSE -`quantity` END) ELSE 0 END) AS `net_after`
                FROM `stock_transactions`
                GROUP BY `product_id`
            ");
            $txStmt->execute([':s1' => $startSql, ':e1' => $endSql, ':s2' => $startSql, ':e2' => $endSql, ':e3' => $endSql]);
            foreach ($txStmt->fetchAll(PDO::FETCH_ASSOC) as $tx) {
                $txByProd[(int) $tx['product_id']] = [
                    'day_in'    => (int) ($tx['day_in'] ?? 0),
                    'day_out'   => (int) ($tx['day_out'] ?? 0),
                    'net_after' => (int) ($tx['net_after'] ?? 0),
                ];
            }

            $todaySnap = [];
            $prevSnap = [];
            $snapStmt = $pdo->prepare('SELECT `snapshot_date`, `product_id`, `opening_qty`, `in_qty`, `other_out_qty`, `closing_qty`, `note` FROM `daily_stock_snapshots` WHERE `snapshot_date` IN (?, ?)');
            $snapStmt->execute([$date, $prevDate]);
            foreach ($snapStmt->fetchAll(PDO::FETCH_ASSOC) as $sr) {
                $pid = (int) $sr['product_id'];
                if ($sr['snapshot_date'] === $date) {
                    $todaySnap[$pid] = $sr;
                } elseif ($sr['snapshot_date'] === $prevDate) {
                    $prevSnap[$pid] = (int) $sr['closing_qty'];
                }
            }

            foreach ($prodRows as $pr) {
                $pid = (int) $pr['id'];
                $key = normalizeReconcileKey((string) ($pr['name'] ?? ''));
                if ($key === '') {
                    continue;
                }

                $tx = $txByProd[$pid] ?? ['day_in' => 0, 'day_out' => 0, 'net_after' => 0];
                $currentQty = (int) ($pr['quantity'] ?? 0);
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
                $closingQty = $snap !== null ? (int) $snap['closing_qty'] : $bookClosing;
                $isClosed = ($snap !== null);

                if (!isset($productCatalog[$key])) {
                    $productCatalog[$key] = [
                        'id'          => $pid,
                        'name'        => trim((string) $pr['name']),
                        'sku'         => (string) ($pr['sku'] ?? ''),
                        'opening_qty' => $openingQty,
                        'in_qty'      => $inQty,
                        'other_out'   => $otherOutQty,
                        'available'   => $availableQty,
                        'closing_qty' => $closingQty,
                        'is_closed'   => $isClosed,
                        'note'        => (string) ($snap['note'] ?? ''),
                    ];
                } else {
                    $productCatalog[$key]['opening_qty'] += $openingQty;
                    $productCatalog[$key]['in_qty'] += $inQty;
                    $productCatalog[$key]['other_out'] += $otherOutQty;
                    $productCatalog[$key]['available'] += $availableQty;
                    $productCatalog[$key]['closing_qty'] += $closingQty;
                    $productCatalog[$key]['is_closed'] = $productCatalog[$key]['is_closed'] && $isClosed;
                }
            }
        }
    } catch (PDOException $e) {
        error_log('shift reconciliation warehouse query: ' . $e->getMessage());
        $warehouseReady = false;
    }

    // 5. CHỈ tổng hợp các món thực sự đã bán trong ngày và chia nhóm theo danh mục (Bếp nóng, Rau...)
    $rows = [];
    $groupsMap = [];
    $totalKdsActual = 0;
    $totalWarehouseAvailable = 0;
    $totalWarehouseClosing = 0;
    $trackedInWarehouseCount = 0;
    $matchedCount = 0;
    $mismatchCount = 0;
    $unmappedCount = 0;

    foreach ($kdsMap as $key => $kds) {
        $prod = $productCatalog[$key] ?? null;
        $dish = $dishCatalog[$key] ?? null;

        $displayName = $kds['item_name'];
        $kdsActualQty = (int) $kds['actual_out_qty'];
        $pendingOpenQty = (int) ($pendingMap[$key] ?? 0);
        $hasProduct = ($prod !== null);

        $groupInfo = resolveDishCategoryGroup($dish['station'] ?? '', $dish['category_name'] ?? '');
        $groupKey = $groupInfo['key'];

        $openingQty = $hasProduct ? (int) $prod['opening_qty'] : null;
        $inQty = $hasProduct ? (int) $prod['in_qty'] : null;
        $otherOutQty = $hasProduct ? (int) $prod['other_out'] : null;
        $availableQty = $hasProduct ? (int) $prod['available'] : null;
        $closingQty = $hasProduct ? (int) $prod['closing_qty'] : null;
        $isClosed = $hasProduct ? (bool) $prod['is_closed'] : false;
        $consumedQty = ($hasProduct && $isClosed) ? ($availableQty - $closingQty) : null;
        $expectedClosing = $hasProduct ? ($availableQty - $kdsActualQty) : null;

        if ($hasProduct) {
            $trackedInWarehouseCount++;
            $totalWarehouseAvailable += $availableQty;
            $totalWarehouseClosing += $closingQty;
        }

        if (!$hasProduct) {
            $status = 'no_warehouse_product';
            $statusLabel = 'Chưa có trong Kho';
            $badgeClass = 'neutral';
            $unmappedCount++;
        } elseif ($isClosed) {
            $closedSnapshotCount++;
            if ($consumedQty === $kdsActualQty) {
                $status = 'matched';
                $statusLabel = 'Đã chốt tồn · Khớp bán KDS';
                $badgeClass = 'positive';
                $matchedCount++;
            } else {
                $status = 'snapshot_mismatch';
                $statusLabel = 'Đã chốt tồn (Tiêu hao: ' . $consumedQty . ')';
                $badgeClass = 'warning';
                $mismatchCount++;
            }
        } else {
            $status = 'pending_close';
            $statusLabel = 'Chờ chốt tồn cuối ngày';
            $badgeClass = 'neutral';
        }

        $totalKdsActual += $kdsActualQty;

        $itemRow = [
            'item_name'                 => $displayName,
            'group_key'                 => $groupKey,
            'group_label'               => $groupInfo['label'],
            'group_order'               => $groupInfo['order'],
            'category_name'             => $dish['category_name'] ?? '',
            'station_name'              => $groupInfo['label'],
            'sku'                       => $prod['sku'] ?? '',
            'product_id'                => $prod['id'] ?? null,
            'has_product'               => $hasProduct,
            'kds_actual_qty'            => $kdsActualQty,
            'pending_open_qty'          => $pendingOpenQty,
            'warehouse_opening_qty'     => $openingQty,
            'warehouse_in_qty'          => $inQty,
            'warehouse_other_out_qty'   => $otherOutQty,
            'warehouse_available_qty'   => $availableQty,
            'warehouse_closing_qty'     => $closingQty,
            'warehouse_expected_qty'    => $expectedClosing,
            'warehouse_consumed_qty'    => $consumedQty,
            'is_snapshot_closed'        => $isClosed,
            'snapshot_note'             => $prod['note'] ?? '',
            'status'                    => $status,
            'status_label'              => $statusLabel,
            'badge_class'               => $badgeClass,
        ];

        $rows[] = $itemRow;

        if (!isset($groupsMap[$groupKey])) {
            $groupsMap[$groupKey] = [
                'group_key'           => $groupKey,
                'group_label'         => $groupInfo['label'],
                'group_order'         => $groupInfo['order'],
                'total_kds_qty'       => 0,
                'total_available_qty' => 0,
                'total_closing_qty'   => 0,
                'items'               => [],
            ];
        }
        $groupsMap[$groupKey]['total_kds_qty'] += $kdsActualQty;
        if ($hasProduct) {
            $groupsMap[$groupKey]['total_available_qty'] += $availableQty;
            $groupsMap[$groupKey]['total_closing_qty'] += $closingQty;
        }
        $groupsMap[$groupKey]['items'][] = $itemRow;
    }

    // Sắp xếp các nhóm theo thứ tự Bếp nóng -> Rau/Bếp lạnh -> Bếp súp -> Quầy Bar -> Khác; trong mỗi nhóm sắp xếp SL bán giảm dần
    usort($groupsMap, static function (array $a, array $b): int {
        $ord = $a['group_order'] <=> $b['group_order'];
        return $ord !== 0 ? $ord : strcmp($a['group_label'], $b['group_label']);
    });

    $sortedRows = [];
    foreach ($groupsMap as &$grp) {
        usort($grp['items'], static function (array $a, array $b): int {
            $q = $b['kds_actual_qty'] <=> $a['kds_actual_qty'];
            return $q !== 0 ? $q : strcmp($a['item_name'], $b['item_name']);
        });
        foreach ($grp['items'] as $gItem) {
            $sortedRows[] = $gItem;
        }
    }
    unset($grp);

    return [
        'mode'                          => 'reconcile',
        'date'                          => $date,
        'date_display'                  => date('d/m/Y', strtotime($date)),
        'day_name'                      => getVietnameseDayOfWeek($date),
        'warehouse_ready'               => $warehouseReady,
        'warehouse_product_count'       => $warehouseProductCount,
        'tracked_in_warehouse_count'    => $trackedInWarehouseCount,
        'closed_snapshot_count'         => $closedSnapshotCount,
        'total_kds_actual_qty'          => $totalKdsActual,
        'total_kds_pending_open_qty'    => $totalPendingOpenQty,
        'total_warehouse_available_qty' => $totalWarehouseAvailable,
        'total_warehouse_closing_qty'   => $totalWarehouseClosing,
        'matched_count'                 => $matchedCount,
        'mismatch_count'                => $mismatchCount,
        'unmapped_count'                => $unmappedCount,
        'is_fully_matched'              => ($totalKdsActual > 0 && $trackedInWarehouseCount > 0 && $closedSnapshotCount === $trackedInWarehouseCount && $mismatchCount === 0 && $unmappedCount === 0),
        'groups'                        => array_values($groupsMap),
        'items'                         => $sortedRows,
    ];
}

/**
 * Xuất báo cáo hiệu suất bếp (mode=kitchen) ra tệp CSV.
 */
function exportKitchenCsv(array $kitchenReport, string $startDate, string $endDate): void
{
    $filename = 'Bao_cao_hieu_suat_bep_' . str_replace('-', '', $startDate) . '_den_' . str_replace('-', '', $endDate) . '.csv';
    $output = prepareCsvDownload($filename);

    writeCsvRow($output, ['SMARTDINE - BÁO CÁO HIỆU SUẤT BẾP KDS (' . date('d/m/Y', strtotime($startDate)) . ' – ' . date('d/m/Y', strtotime($endDate)) . ')']);
    writeCsvRow($output, ['Thời gian xuất:', date('d/m/Y H:i:s')]);
    writeCsvRow($output, ['Thời gian chế biến TB (phút):', $kitchenReport['avg_cook_minutes'] ?? 0]);
    writeCsvRow($output, ['Thời gian chờ phục vụ TB (phút):', $kitchenReport['avg_wait_minutes'] ?? 0]);
    writeCsvRow($output, ['Tổng phần món hoàn thành:', $kitchenReport['total_completed'] ?? 0]);
    writeCsvRow($output, ['Tỷ lệ đúng giờ (%):', $kitchenReport['on_time_rate'] ?? 0]);
    writeCsvRow($output, ['Số món trễ (>= 15p):', $kitchenReport['delayed_count'] ?? 0]);
    writeCsvRow($output, []);

    writeCsvRow($output, ['CHI TIẾT THỜI GIAN CHẾ BIẾN TỪNG MÓN']);
    writeCsvRow($output, ['Tên món ăn', 'Quầy bếp', 'Số lượt làm', 'Tổng SL (đĩa/bát)', 'Nhanh nhất (p)', 'Chế biến TB (p)', 'Lâu nhất (p)', 'Khách chờ TB (p)', 'Đúng giờ (%)', 'Đánh giá']);
    foreach ($kitchenReport['dish_breakdown'] ?? [] as $dish) {
        writeCsvRow($output, [
            $dish['item_name'],
            $dish['station_name'],
            $dish['total_cooked'],
            $dish['total_qty'],
            $dish['min_cook_minutes'],
            $dish['avg_cook_minutes'],
            $dish['max_cook_minutes'],
            $dish['avg_wait_minutes'],
            $dish['on_time_rate'],
            $dish['badge']['text'] ?? '',
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Xuất báo cáo món thực xuất đã bán trong ngày (chia theo danh mục Bếp nóng, Rau...) ra tệp CSV.
 */
function exportShiftReconciliationCsv(array $reconcileReport): void
{
    $filename = 'Bao_cao_thuc_xuat_ket_ca_' . str_replace('-', '_', $reconcileReport['date']) . '.csv';
    $output = prepareCsvDownload($filename);

    writeCsvRow($output, ['SMARTDINE - BÁO CÁO MÓN THỰC XUẤT BÁN ĐƯỢC TRONG NGÀY (' . $reconcileReport['date_display'] . ')']);
    writeCsvRow($output, ['Ngày đối chiếu:', $reconcileReport['date_display'] . ' (' . $reconcileReport['day_name'] . ')']);
    writeCsvRow($output, ['Thời gian xuất:', date('d/m/Y H:i:s')]);
    writeCsvRow($output, ['Tổng SL món thực xuất KDS:', $reconcileReport['total_kds_actual_qty']]);
    writeCsvRow($output, ['Tổng tồn lại cuối ngày trong Kho:', $reconcileReport['total_warehouse_closing_qty']]);
    writeCsvRow($output, []);

    writeCsvRow($output, ['BẢNG KÊ MÓN ĐÃ BÁN TRONG NGÀY THEO DANH MỤC & ĐỐI CHIẾU TỒN KHO']);
    writeCsvRow($output, [
        'Danh mục / Quầy',
        'Tên món / Sản phẩm',
        'Mã SKU',
        'Thực xuất KDS',
        'Tồn đầu + Nhập (Kho)',
        'Tồn lại cuối ngày (Kho)',
        'Trạng thái đối chiếu',
    ]);

    foreach ($reconcileReport['groups'] ?? [] as $group) {
        foreach ($group['items'] ?? [] as $row) {
            writeCsvRow($output, [
                $group['group_label'],
                $row['item_name'],
                $row['sku'] !== '' ? $row['sku'] : '-',
                $row['kds_actual_qty'],
                $row['has_product'] ? $row['warehouse_available_qty'] : '-',
                $row['warehouse_closing_qty'] !== null ? $row['warehouse_closing_qty'] : '-',
                $row['status_label'],
            ]);
        }
        writeCsvRow($output, [
            'Cộng nhóm ' . $group['group_label'],
            '',
            '',
            $group['total_kds_qty'],
            $group['total_available_qty'],
            $group['total_closing_qty'],
            '',
        ]);
    }

    writeCsvRow($output, [
        'TỔNG CỘNG CUỐI CA',
        '',
        '',
        $reconcileReport['total_kds_actual_qty'],
        $reconcileReport['total_warehouse_available_qty'],
        $reconcileReport['total_warehouse_closing_qty'],
        '',
    ]);

    fclose($output);
    exit;
}
