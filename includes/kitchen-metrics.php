<?php
/**
 * SmartDine - Báo cáo Hiệu suất Bếp (Kitchen Performance Metrics).
 *
 * Thống kê thời gian chế biến trung bình (từ lúc khách đặt đến lúc Bếp bấm Hoàn thành),
 * cảnh báo các món thường xuyên bị trễ giờ phục vụ, và đo lường tải theo từng quầy bếp.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/kitchen.php';

function getKitchenMetricsDb(): PDO {
    return getDbConnection();
}

/**
 * Lấy báo cáo hiệu suất nhà bếp theo khoảng ngày.
 *
 * @param string $startDate Định dạng Y-m-d
 * @param string $endDate   Định dạng Y-m-d
 * @return array
 */
function getKitchenPerformanceReport(string $startDate, string $endDate): array {
    $pdo = getKitchenMetricsDb();
    if (!$pdo) {
        return [
            'total_completed'     => 0,
            'avg_cook_minutes'    => 0,
            'avg_wait_minutes'    => 0,
            'on_time_count'       => 0,
            'on_time_rate'        => 100,
            'delayed_count'       => 0,
            'delayed_rate'        => 0,
            'delayed_alerts'      => [],
            'dish_breakdown'      => [],
            'station_breakdown'   => [],
        ];
    }

    $startSql = $startDate . ' 00:00:00';
    $endSql   = $endDate . ' 23:59:59';

    // 1. Thống kê tổng quan KPI
    $kpiStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_completed,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, COALESCE(cooking_at, sent_at), done_at) / 60), 1) AS avg_cook_minutes,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, sent_at, done_at) / 60), 1) AS avg_wait_minutes,
            SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, sent_at, done_at) < 15 THEN 1 ELSE 0 END) AS on_time_count,
            SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, sent_at, done_at) >= 15 THEN 1 ELSE 0 END) AS delayed_count
        FROM `order_items`
        WHERE `done_at` IS NOT NULL
          AND `sent_at` IS NOT NULL
          AND `done_at` BETWEEN :start_date AND :end_date
    ");
    $kpiStmt->execute([':start_date' => $startSql, ':end_date' => $endSql]);
    $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    $totalCompleted = (int) ($kpi['total_completed'] ?? 0);
    $onTimeCount    = (int) ($kpi['on_time_count'] ?? 0);
    $delayedCount   = (int) ($kpi['delayed_count'] ?? 0);
    $onTimeRate     = $totalCompleted > 0 ? round(($onTimeCount / $totalCompleted) * 100, 1) : 100.0;
    $delayedRate    = $totalCompleted > 0 ? round(($delayedCount / $totalCompleted) * 100, 1) : 0.0;

    // 2. Cảnh báo các món thường xuyên bị trễ giờ phục vụ (>= 15 phút)
    $alertStmt = $pdo->prepare("
        SELECT 
            i.`item_name`,
            COUNT(*) AS total_cooked,
            SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, i.`sent_at`, i.`done_at`) >= 15 THEN 1 ELSE 0 END) AS delayed_count,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, i.`sent_at`, i.`done_at`) / 60), 1) AS avg_wait_minutes,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, COALESCE(i.`cooking_at`, i.`sent_at`), i.`done_at`) / 60), 1) AS avg_cook_minutes,
            MAX(TIMESTAMPDIFF(MINUTE, i.`sent_at`, i.`done_at`)) AS max_wait_minutes,
            COALESCE(
                (SELECT d.`station` FROM `dishes` d WHERE d.`name` = i.`item_name` ORDER BY d.`id` LIMIT 1),
                'hot'
            ) AS `station`
        FROM `order_items` i
        WHERE i.`done_at` IS NOT NULL
          AND i.`sent_at` IS NOT NULL
          AND i.`done_at` BETWEEN :start_date AND :end_date
        GROUP BY i.`item_name`
        HAVING delayed_count > 0
        ORDER BY delayed_count DESC, avg_wait_minutes DESC
    ");
    $alertStmt->execute([':start_date' => $startSql, ':end_date' => $endSql]);
    $rawAlerts = $alertStmt->fetchAll(PDO::FETCH_ASSOC);

    $delayedAlerts = array_map(function ($row) {
        $rate = round(((int) $row['delayed_count'] / (int) $row['total_cooked']) * 100, 1);
        $severity = ($rate >= 30.0 || (float) $row['avg_wait_minutes'] >= 20.0) ? 'urgent' : 'warning';
        return [
            'item_name'        => $row['item_name'],
            'total_cooked'     => (int) $row['total_cooked'],
            'delayed_count'    => (int) $row['delayed_count'],
            'delayed_rate'     => $rate,
            'avg_wait_minutes' => (float) $row['avg_wait_minutes'],
            'avg_cook_minutes' => (float) $row['avg_cook_minutes'],
            'max_wait_minutes' => (int) $row['max_wait_minutes'],
            'station'          => $row['station'],
            'station_name'     => KITCHEN_STATIONS[$row['station']] ?? 'Bếp nóng',
            'severity'         => $severity,
        ];
    }, $rawAlerts);

    // 3. Bảng phân tích chi tiết hiệu suất tất cả món ăn
    $dishStmt = $pdo->prepare("
        SELECT 
            i.`item_name`,
            COUNT(*) AS total_cooked,
            SUM(i.`qty`) AS total_qty,
            ROUND(MIN(TIMESTAMPDIFF(SECOND, COALESCE(i.`cooking_at`, i.`sent_at`), i.`done_at`) / 60), 1) AS min_cook_minutes,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, COALESCE(i.`cooking_at`, i.`sent_at`), i.`done_at`) / 60), 1) AS avg_cook_minutes,
            ROUND(MAX(TIMESTAMPDIFF(SECOND, COALESCE(i.`cooking_at`, i.`sent_at`), i.`done_at`) / 60), 1) AS max_cook_minutes,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, i.`sent_at`, i.`done_at`) / 60), 1) AS avg_wait_minutes,
            SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, i.`sent_at`, i.`done_at`) < 15 THEN 1 ELSE 0 END) AS on_time_count,
            SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, i.`sent_at`, i.`done_at`) >= 15 THEN 1 ELSE 0 END) AS delayed_count,
            COALESCE(
                (SELECT d.`station` FROM `dishes` d WHERE d.`name` = i.`item_name` ORDER BY d.`id` LIMIT 1),
                'hot'
            ) AS `station`
        FROM `order_items` i
        WHERE i.`done_at` IS NOT NULL
          AND i.`sent_at` IS NOT NULL
          AND i.`done_at` BETWEEN :start_date AND :end_date
        GROUP BY i.`item_name`
        ORDER BY total_cooked DESC, avg_wait_minutes DESC
    ");
    $dishStmt->execute([':start_date' => $startSql, ':end_date' => $endSql]);
    $dishBreakdown = array_map(function ($row) {
        $tot = (int) $row['total_cooked'];
        $ontime = (int) $row['on_time_count'];
        $rate = $tot > 0 ? round(($ontime / $tot) * 100, 1) : 100.0;
        $avgCook = (float) $row['avg_cook_minutes'];

        if ($avgCook <= 6.0) {
            $badge = ['text' => 'Siêu tốc', 'class' => 'positive'];
        } elseif ($rate >= 85.0) {
            $badge = ['text' => 'Đạt chuẩn', 'class' => 'neutral'];
        } elseif ($rate >= 60.0) {
            $badge = ['text' => 'Cần cải thiện', 'class' => 'warning'];
        } else {
            $badge = ['text' => 'Quá tải / Trễ', 'class' => 'urgent'];
        }

        return [
            'item_name'        => $row['item_name'],
            'station'          => $row['station'],
            'station_name'     => KITCHEN_STATIONS[$row['station']] ?? 'Bếp nóng',
            'total_cooked'     => $tot,
            'total_qty'        => (int) $row['total_qty'],
            'min_cook_minutes' => (float) $row['min_cook_minutes'],
            'avg_cook_minutes' => $avgCook,
            'max_cook_minutes' => (float) $row['max_cook_minutes'],
            'avg_wait_minutes' => (float) $row['avg_wait_minutes'],
            'on_time_rate'     => $rate,
            'delayed_count'    => (int) $row['delayed_count'],
            'badge'            => $badge,
        ];
    }, $dishStmt->fetchAll(PDO::FETCH_ASSOC));

    // 4. Thống kê theo quầy bếp (Station)
    $stations = [];
    foreach (KITCHEN_STATIONS as $stKey => $stName) {
        $stations[$stKey] = [
            'station'          => $stKey,
            'name'             => $stName,
            'total_cooked'     => 0,
            'avg_cook_minutes' => 0.0,
            'on_time_rate'     => 100.0,
        ];
    }

    $stStmt = $pdo->prepare("
        SELECT 
            COALESCE(
                (SELECT d.`station` FROM `dishes` d WHERE d.`name` = i.`item_name` ORDER BY d.`id` LIMIT 1),
                'hot'
            ) AS `station`,
            COUNT(*) AS total_cooked,
            ROUND(AVG(TIMESTAMPDIFF(SECOND, COALESCE(i.`cooking_at`, i.`sent_at`), i.`done_at`) / 60), 1) AS avg_cook_minutes,
            SUM(CASE WHEN TIMESTAMPDIFF(MINUTE, i.`sent_at`, i.`done_at`) < 15 THEN 1 ELSE 0 END) AS on_time_count
        FROM `order_items` i
        WHERE i.`done_at` IS NOT NULL
          AND i.`sent_at` IS NOT NULL
          AND i.`done_at` BETWEEN :start_date AND :end_date
        GROUP BY `station`
    ");
    $stStmt->execute([':start_date' => $startSql, ':end_date' => $endSql]);
    foreach ($stStmt->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
        $stKey = $sRow['station'];
        $tot   = (int) $sRow['total_cooked'];
        $ont   = (int) $sRow['on_time_count'];
        if (isset($stations[$stKey])) {
            $stations[$stKey]['total_cooked']     = $tot;
            $stations[$stKey]['avg_cook_minutes'] = (float) $sRow['avg_cook_minutes'];
            $stations[$stKey]['on_time_rate']     = $tot > 0 ? round(($ont / $tot) * 100, 1) : 100.0;
        }
    }

    return [
        'total_completed'   => $totalCompleted,
        'avg_cook_minutes'  => (float) ($kpi['avg_cook_minutes'] ?? 0),
        'avg_wait_minutes'  => (float) ($kpi['avg_wait_minutes'] ?? 0),
        'on_time_count'     => $onTimeCount,
        'on_time_rate'      => $onTimeRate,
        'delayed_count'     => $delayedCount,
        'delayed_rate'      => $delayedRate,
        'delayed_alerts'    => $delayedAlerts,
        'dish_breakdown'    => $dishBreakdown,
        'station_breakdown' => array_values($stations),
    ];
}
