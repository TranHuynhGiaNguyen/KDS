<?php
/**
 * SmartDine - Module Báo cáo & Thống kê Doanh thu
 * Xử lý truy vấn dữ liệu doanh thu theo ngày, tháng, khu vực, phương thức thanh toán và top món ăn.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/orders_schema.php';
require_once __DIR__ . '/tables.php';

const REVENUE_TIMEZONE = 'Asia/Ho_Chi_Minh';

if (!function_exists('formatVnd')) {
    function formatVnd(int|float $amount): string {
        return number_format($amount, 0, ',', '.') . 'đ';
    }
}

/**
 * Lấy kết nối DB và tự động khởi tạo bảng nếu cần
 */
function getRevenueDb(): PDO {
    static $initialized = false;
    $pdo = getDbConnection();
    if ($pdo === null) {
        throw new RuntimeException('Không thể kết nối cơ sở dữ liệu cho báo cáo doanh thu.');
    }

    if (!$initialized) {
        initOrderTables($pdo);
        $initialized = true;
    }

    return $pdo;
}

/**
 * Tự động tạo dữ liệu mẫu hóa đơn lịch sử nếu bảng invoices còn trống
 */
function seedDemoRevenueIfEmpty(PDO $pdo): void {
    try {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM `invoices`')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $menuItems = [
            ['Cơm gà Hải Nam', 75000],
            ['Mì xào bò', 85000],
            ['Bò lúc lắc', 150000],
            ['Lẩu nấm gà tiềm', 320000],
            ['Bún chả Hà Nội đặc biệt', 75000],
            ['Combo Bò Wagyu thượng hạng', 890000],
            ['Salad trộn đặc biệt', 55000],
            ['Khoai tây chiên giòn', 45000],
            ['Trà đào cam sả', 35000],
            ['Trà tắc hạt chia', 20000],
            ['Nước ngọt các loại', 25000],
            ['Chè khúc bạch', 45000],
            ['Bánh tiramisu tráng miệng', 35000],
        ];

        $tables = ['B01', 'B02', 'B03', 'B04', 'B05', 'B06', 'B07', 'B08', 'B09', 'B10', 'B11', 'B12'];
        $cashiers = [1, 2, 4];
        $methods = ['cash', 'qr', 'qr', 'cash', 'qr'];

        $startDate = new DateTime('2026-10-25', new DateTimeZone(REVENUE_TIMEZONE));
        $endDate = new DateTime('2026-11-13', new DateTimeZone(REVENUE_TIMEZONE));

        $orderStmt = $pdo->prepare('INSERT INTO `orders` (`table_code`, `status`, `created_at`) VALUES (?, "paid", ?)');
        $orderItemStmt = $pdo->prepare('INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `status`, `sent_by`, `sent_at`, `created_at`) VALUES (?, ?, ?, ?, "served", ?, ?, ?)');
        $invoiceStmt = $pdo->prepare('INSERT INTO `invoices` (`order_id`, `table_code`, `subtotal`, `discount_type`, `discount_value`, `discount_amount`, `service_fee`, `vat`, `total`, `service_rate`, `vat_rate`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $invoiceItemStmt = $pdo->prepare('INSERT INTO `invoice_items` (`invoice_id`, `item_name`, `unit_price`, `qty`, `amount`) VALUES (?, ?, ?, ?, ?)');
        $paymentStmt = $pdo->prepare('INSERT INTO `payments` (`invoice_id`, `method`, `amount`, `cash_received`, `change_amount`, `cashier_id`, `paid_at`) VALUES (?, ?, ?, ?, ?, ?, ?)');

        $interval = new DateInterval('P1D');
        $period = new DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day'));

        mt_srand(12345);
        $pdo->beginTransaction();

        foreach ($period as $dt) {
            $dateStr = $dt->format('Y-m-d');
            $isToday = ($dateStr === '2026-11-13');
            $dayOfWeek = (int) $dt->format('N');
            $isWeekend = ($dayOfWeek >= 6);

            $numOrders = $isToday ? 14 : ($isWeekend ? mt_rand(26, 38) : mt_rand(14, 24));

            for ($o = 0; $o < $numOrders; $o++) {
                $hour = mt_rand(10, 21);
                if ($isToday && $hour > 11) {
                    $hour = mt_rand(8, 11);
                }
                $min = mt_rand(0, 59);
                $sec = mt_rand(0, 59);
                $timeStr = sprintf('%s %02d:%02d:%02d', $dateStr, $hour, $min, $sec);

                $tableCode = $tables[array_rand($tables)];
                $cashierId = $cashiers[array_rand($cashiers)];
                $method = $methods[array_rand($methods)];

                $numItems = mt_rand(2, 5);
                $selectedIndexes = (array) array_rand($menuItems, min($numItems, count($menuItems)));
                shuffle($selectedIndexes);

                $subtotal = 0;
                $lines = [];
                foreach ($selectedIndexes as $idx) {
                    $m = $menuItems[$idx];
                    $qty = mt_rand(1, 3);
                    $lineAmount = $m[1] * $qty;
                    $subtotal += $lineAmount;
                    $lines[] = [
                        'name' => $m[0],
                        'price' => $m[1],
                        'qty' => $qty,
                        'amount' => $lineAmount
                    ];
                }

                $hasDiscount = (mt_rand(1, 100) <= 15);
                $discountType = 'none';
                $discountVal = 0;
                $discountAmt = 0;
                if ($hasDiscount) {
                    if (mt_rand(0, 1) === 0) {
                        $discountType = 'percent';
                        $discountVal = 10;
                        $discountAmt = (int) round($subtotal * 0.10);
                    } else {
                        $discountType = 'amount';
                        $discountVal = 50000;
                        $discountAmt = min(50000, $subtotal);
                    }
                }

                $afterDiscount = max(0, $subtotal - $discountAmt);
                $serviceRate = 0.05;
                $vatRate = 0.08;
                $serviceFee = (int) round($afterDiscount * $serviceRate);
                $vat = (int) round(($afterDiscount + $serviceFee) * $vatRate);
                $total = $afterDiscount + $serviceFee + $vat;

                $orderStmt->execute([$tableCode, $timeStr]);
                $orderId = (int) $pdo->lastInsertId();

                foreach ($lines as $line) {
                    $orderItemStmt->execute([$orderId, $line['name'], $line['price'], $line['qty'], $cashierId, $timeStr, $timeStr]);
                }

                $invoiceStmt->execute([
                    $orderId, $tableCode, $subtotal, $discountType, $discountVal, $discountAmt,
                    $serviceFee, $vat, $total, $serviceRate, $vatRate, $timeStr
                ]);
                $invoiceId = (int) $pdo->lastInsertId();

                foreach ($lines as $line) {
                    $invoiceItemStmt->execute([$invoiceId, $line['name'], $line['price'], $line['qty'], $line['amount']]);
                }

                $cashReceived = ($method === 'cash') ? (int) (ceil($total / 50000) * 50000) : 0;
                if ($cashReceived < $total && $method === 'cash') {
                    $cashReceived = $total;
                }
                $changeAmt = ($method === 'cash') ? ($cashReceived - $total) : 0;
                $paymentStmt->execute([$invoiceId, $method, $total, $cashReceived, $changeAmt, $cashierId, $timeStr]);
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('seedDemoRevenue error: ' . $e->getMessage());
    }
}

/**
 * Lấy báo cáo doanh thu chi tiết cho một ngày cụ thể (YYYY-MM-DD)
 */
function getRevenueByDate(PDO $pdo, string $date): array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $start = $date . ' 00:00:00';
    $end = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';

    // 1. Thống kê tổng quan
    $summaryStmt = $pdo->prepare('
        SELECT 
            COUNT(v.id) AS total_invoices,
            COALESCE(SUM(v.subtotal), 0) AS total_subtotal,
            COALESCE(SUM(v.discount_amount), 0) AS total_discount,
            COALESCE(SUM(v.service_fee), 0) AS total_service_fee,
            COALESCE(SUM(v.vat), 0) AS total_vat,
            COALESCE(SUM(v.total), 0) AS total_revenue
        FROM `invoices` v
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
    ');
    $summaryStmt->execute([$start, $end]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_invoices' => 0,
        'total_subtotal' => 0,
        'total_discount' => 0,
        'total_service_fee' => 0,
        'total_vat' => 0,
        'total_revenue' => 0,
    ];

    $totalInvoices = (int) $summary['total_invoices'];
    $totalRevenue = (int) $summary['total_revenue'];
    $avgOrderValue = $totalInvoices > 0 ? (int) round($totalRevenue / $totalInvoices) : 0;

    // 2. Tổng số món đã bán
    $itemsSoldStmt = $pdo->prepare('
        SELECT COALESCE(SUM(ii.qty), 0) AS total_items
        FROM `invoice_items` ii
        JOIN `invoices` v ON v.`id` = ii.`invoice_id`
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
    ');
    $itemsSoldStmt->execute([$start, $end]);
    $totalItemsSold = (int) $itemsSoldStmt->fetchColumn();

    // 3. Phân bổ phương thức thanh toán
    $methodStmt = $pdo->prepare('
        SELECT p.`method`, COUNT(p.`id`) AS `count`, COALESCE(SUM(p.`amount`), 0) AS `total`
        FROM `payments` p
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
        GROUP BY p.`method`
    ');
    $methodStmt->execute([$start, $end]);
    $methodsRaw = $methodStmt->fetchAll(PDO::FETCH_ASSOC);

    $paymentMethods = [
        'cash' => ['name' => 'Tiền mặt', 'count' => 0, 'total' => 0, 'percent' => 0],
        'qr' => ['name' => 'Chuyển khoản QR', 'count' => 0, 'total' => 0, 'percent' => 0],
    ];
    foreach ($methodsRaw as $row) {
        $m = $row['method'];
        if (isset($paymentMethods[$m])) {
            $paymentMethods[$m]['count'] = (int) $row['count'];
            $paymentMethods[$m]['total'] = (int) $row['total'];
        }
    }
    if ($totalRevenue > 0) {
        $paymentMethods['cash']['percent'] = round(($paymentMethods['cash']['total'] / $totalRevenue) * 100, 1);
        $paymentMethods['qr']['percent'] = round(($paymentMethods['qr']['total'] / $totalRevenue) * 100, 1);
    }

    // 4. Biểu đồ theo khung giờ trong ngày (08:00 -> 22:00)
    $hourlyStmt = $pdo->prepare('
        SELECT 
            HOUR(p.`paid_at`) AS `hour`,
            COUNT(v.`id`) AS `invoices`,
            COALESCE(SUM(v.`total`), 0) AS `revenue`
        FROM `invoices` v
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
        GROUP BY HOUR(p.`paid_at`)
        ORDER BY `hour` ASC
    ');
    $hourlyStmt->execute([$start, $end]);
    $hourlyRaw = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);
    $hourlyMap = [];
    foreach ($hourlyRaw as $h) {
        $hourlyMap[(int) $h['hour']] = [
            'invoices' => (int) $h['invoices'],
            'revenue' => (int) $h['revenue'],
        ];
    }

    $hourlyData = [];
    for ($h = 8; $h <= 22; $h++) {
        $hourlyData[] = [
            'hour' => $h,
            'label' => sprintf('%02dh', $h),
            'invoices' => $hourlyMap[$h]['invoices'] ?? 0,
            'revenue' => $hourlyMap[$h]['revenue'] ?? 0,
        ];
    }

    // 5. Danh sách toàn bộ hóa đơn trong ngày
    $invoicesStmt = $pdo->prepare('
        SELECT 
            v.`id`,
            v.`order_id`,
            v.`table_code`,
            v.`subtotal`,
            v.`discount_amount`,
            v.`service_fee`,
            v.`vat`,
            v.`total`,
            p.`method`,
            p.`paid_at`,
            u.`fullname` AS `cashier_name`
        FROM `invoices` v
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        LEFT JOIN `users` u ON u.`id` = p.`cashier_id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
        ORDER BY p.`paid_at` DESC, v.`id` DESC
    ');
    $invoicesStmt->execute([$start, $end]);
    $invoicesList = array_map(static fn(array $r): array => [
        'id' => (int) $r['id'],
        'order_id' => (int) $r['order_id'],
        'code' => sprintf('HD%05d', $r['order_id']),
        'table_code' => $r['table_code'],
        'subtotal' => (int) $r['subtotal'],
        'discount_amount' => (int) $r['discount_amount'],
        'service_fee' => (int) $r['service_fee'],
        'vat' => (int) $r['vat'],
        'total' => (int) $r['total'],
        'method' => $r['method'],
        'method_label' => $r['method'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản QR',
        'cashier_name' => $r['cashier_name'] ?: 'Quản trị viên',
        'paid_at' => date('H:i', strtotime($r['paid_at'])),
        'paid_at_full' => date('d/m/Y H:i', strtotime($r['paid_at'])),
    ], $invoicesStmt->fetchAll(PDO::FETCH_ASSOC));

    // 6. Top 5 món ăn bán chạy trong ngày
    $topStmt = $pdo->prepare('
        SELECT 
            ii.`item_name`,
            SUM(ii.`qty`) AS `total_qty`,
            SUM(ii.`amount`) AS `total_amount`
        FROM `invoice_items` ii
        JOIN `invoices` v ON v.`id` = ii.`invoice_id`
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
        GROUP BY ii.`item_name`
        ORDER BY `total_amount` DESC, `total_qty` DESC
        LIMIT 5
    ');
    $topStmt->execute([$start, $end]);
    $topDishes = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    // Tính phần trăm so với món dẫn đầu
    $maxAmount = !empty($topDishes) ? (int) $topDishes[0]['total_amount'] : 0;
    foreach ($topDishes as &$d) {
        $d['total_qty'] = (int) $d['total_qty'];
        $d['total_amount'] = (int) $d['total_amount'];
        $d['percentage'] = $maxAmount > 0 ? (int) round(($d['total_amount'] / $maxAmount) * 100) : 0;
    }
    unset($d);

    // 7. Phân bổ doanh thu theo khu vực
    $areaStmt = $pdo->prepare('
        SELECT 
            COALESCE(a.`name`, "Khu vực chung") AS `area_name`,
            COALESCE(a.`code`, "other") AS `area_code`,
            COUNT(v.`id`) AS `invoice_count`,
            COALESCE(SUM(v.`total`), 0) AS `total_revenue`
        FROM `invoices` v
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        LEFT JOIN `dining_tables` t ON t.`code` = v.`table_code`
        LEFT JOIN `restaurant_areas` a ON a.`id` = t.`area_id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
        GROUP BY a.`code`, a.`name`
        ORDER BY `total_revenue` DESC
    ');
    $areaStmt->execute([$start, $end]);
    $areaBreakdown = $areaStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($areaBreakdown as &$ar) {
        $ar['total_revenue'] = (int) $ar['total_revenue'];
        $ar['invoice_count'] = (int) $ar['invoice_count'];
        $ar['percentage'] = $totalRevenue > 0 ? round(($ar['total_revenue'] / $totalRevenue) * 100, 1) : 0;
    }
    unset($ar);

    // 8. So sánh với ngày hôm trước
    $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
    $prevStmt = $pdo->prepare('
        SELECT COALESCE(SUM(v.`total`), 0) AS `prev_revenue`, COUNT(v.`id`) AS `prev_invoices`
        FROM `invoices` v
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
    ');
    $prevStmt->execute([$prevDate . ' 00:00:00', $prevDate . ' 23:59:59']);
    $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC);
    $prevRevenue = (int) ($prevRow['prev_revenue'] ?? 0);
    $prevInvoices = (int) ($prevRow['prev_invoices'] ?? 0);

    $diffRevenue = $totalRevenue - $prevRevenue;
    $diffPercent = $prevRevenue > 0 ? round(($diffRevenue / $prevRevenue) * 100, 1) : ($totalRevenue > 0 ? 100.0 : 0.0);

    return [
        'mode' => 'day',
        'date' => $date,
        'date_display' => date('d/m/Y', strtotime($date)),
        'day_name' => getVietnameseDayOfWeek($date),
        'total_revenue' => $totalRevenue,
        'total_subtotal' => (int) $summary['total_subtotal'],
        'total_discount' => (int) $summary['total_discount'],
        'total_service_fee' => (int) $summary['total_service_fee'],
        'total_vat' => (int) $summary['total_vat'],
        'total_invoices' => $totalInvoices,
        'total_items_sold' => $totalItemsSold,
        'avg_order_value' => $avgOrderValue,
        'payment_methods' => $paymentMethods,
        'hourly_data' => $hourlyData,
        'invoices_list' => $invoicesList,
        'top_dishes' => $topDishes,
        'area_breakdown' => $areaBreakdown,
        'comparison' => [
            'prev_date' => $prevDate,
            'prev_revenue' => $prevRevenue,
            'prev_invoices' => $prevInvoices,
            'diff_amount' => $diffRevenue,
            'diff_percent' => $diffPercent,
            'direction' => $diffRevenue >= 0 ? 'positive' : 'negative',
        ],
    ];
}

/**
 * Lấy báo cáo doanh thu chi tiết cho một tháng cụ thể (YYYY-MM)
 */
function getRevenueByMonth(PDO $pdo, string $yearMonth): array {
    if (!preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
        $yearMonth = date('Y-m');
    }

    $monthStart = $yearMonth . '-01 00:00:00';
    $nextMonthDate = (new DateTime($yearMonth . '-01', new DateTimeZone(REVENUE_TIMEZONE)))->modify('+1 month');
    $monthEnd = $nextMonthDate->format('Y-m-d') . ' 00:00:00';

    $daysInMonth = (int) (new DateTime($yearMonth . '-01', new DateTimeZone(REVENUE_TIMEZONE)))->format('t');

    // 1. Thống kê tổng quan tháng
    $summaryStmt = $pdo->prepare('
        SELECT 
            COUNT(v.`id`) AS `total_invoices`,
            COALESCE(SUM(v.`subtotal`), 0) AS `total_subtotal`,
            COALESCE(SUM(v.`discount_amount`), 0) AS `total_discount`,
            COALESCE(SUM(v.`service_fee`), 0) AS `total_service_fee`,
            COALESCE(SUM(v.`vat`), 0) AS `total_vat`,
            COALESCE(SUM(v.`total`), 0) AS `total_revenue`
        FROM `invoices` v
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
    ');
    $summaryStmt->execute([$monthStart, $monthEnd]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_invoices' => 0,
        'total_subtotal' => 0,
        'total_discount' => 0,
        'total_service_fee' => 0,
        'total_vat' => 0,
        'total_revenue' => 0,
    ];

    $totalInvoices = (int) $summary['total_invoices'];
    $totalRevenue = (int) $summary['total_revenue'];
    $avgOrderValue = $totalInvoices > 0 ? (int) round($totalRevenue / $totalInvoices) : 0;

    // 2. Tổng số món đã bán trong tháng
    $itemsSoldStmt = $pdo->prepare('
        SELECT COALESCE(SUM(ii.`qty`), 0) AS `total_items`
        FROM `invoice_items` ii
        JOIN `invoices` v ON v.`id` = ii.`invoice_id`
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
    ');
    $itemsSoldStmt->execute([$monthStart, $monthEnd]);
    $totalItemsSold = (int) $itemsSoldStmt->fetchColumn();

    // 3. Phân bổ phương thức thanh toán trong tháng
    $methodStmt = $pdo->prepare('
        SELECT p.`method`, COUNT(p.`id`) AS `count`, COALESCE(SUM(p.`amount`), 0) AS `total`
        FROM `payments` p
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
        GROUP BY p.`method`
    ');
    $methodStmt->execute([$monthStart, $monthEnd]);
    $methodsRaw = $methodStmt->fetchAll(PDO::FETCH_ASSOC);

    $paymentMethods = [
        'cash' => ['name' => 'Tiền mặt', 'count' => 0, 'total' => 0, 'percent' => 0],
        'qr' => ['name' => 'Chuyển khoản QR', 'count' => 0, 'total' => 0, 'percent' => 0],
    ];
    foreach ($methodsRaw as $row) {
        $m = $row['method'];
        if (isset($paymentMethods[$m])) {
            $paymentMethods[$m]['count'] = (int) $row['count'];
            $paymentMethods[$m]['total'] = (int) $row['total'];
        }
    }
    if ($totalRevenue > 0) {
        $paymentMethods['cash']['percent'] = round(($paymentMethods['cash']['total'] / $totalRevenue) * 100, 1);
        $paymentMethods['qr']['percent'] = round(($paymentMethods['qr']['total'] / $totalRevenue) * 100, 1);
    }

    // 4. Bảng doanh thu chi tiết từng ngày trong tháng (1 đến t)
    $dailyStmt = $pdo->prepare('
        SELECT 
            DATE(p.`paid_at`) AS `pay_date`,
            COUNT(v.`id`) AS `invoices`,
            COALESCE(SUM(v.`subtotal`), 0) AS `subtotal`,
            COALESCE(SUM(v.`discount_amount`), 0) AS `discount`,
            COALESCE(SUM(v.`service_fee` + v.`vat`), 0) AS `tax_fee`,
            COALESCE(SUM(v.`total`), 0) AS `revenue`,
            COALESCE(SUM(CASE WHEN p.`method` = "qr" THEN p.`amount` ELSE 0 END), 0) AS `qr_total`,
            COALESCE(SUM(CASE WHEN p.`method` = "cash" THEN p.`amount` ELSE 0 END), 0) AS `cash_total`
        FROM `invoices` v
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
        GROUP BY DATE(p.`paid_at`)
        ORDER BY `pay_date` ASC
    ');
    $dailyStmt->execute([$monthStart, $monthEnd]);
    $dailyMap = [];
    foreach ($dailyStmt->fetchAll(PDO::FETCH_ASSOC) as $dRow) {
        $dailyMap[$dRow['pay_date']] = $dRow;
    }

    $dailyData = [];
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $curDateStr = sprintf('%s-%02d', $yearMonth, $day);
        $row = $dailyMap[$curDateStr] ?? null;

        $rev = $row ? (int) $row['revenue'] : 0;
        $inv = $row ? (int) $row['invoices'] : 0;
        $sub = $row ? (int) $row['subtotal'] : 0;
        $disc = $row ? (int) $row['discount'] : 0;
        $tax = $row ? (int) $row['tax_fee'] : 0;
        $qr = $row ? (int) $row['qr_total'] : 0;
        $cash = $row ? (int) $row['cash_total'] : 0;

        $dailyData[] = [
            'date' => $curDateStr,
            'day_num' => $day,
            'day_name' => getVietnameseDayOfWeek($curDateStr),
            'label' => sprintf('%02d/%s', $day, substr($yearMonth, 5, 2)),
            'invoices' => $inv,
            'subtotal' => $sub,
            'discount' => $disc,
            'tax_fee' => $tax,
            'revenue' => $rev,
            'qr_total' => $qr,
            'cash_total' => $cash,
            'avg_order' => $inv > 0 ? (int) round($rev / $inv) : 0,
        ];
    }

    // 5. Top 5 món bán chạy nhất trong tháng
    $topStmt = $pdo->prepare('
        SELECT 
            ii.`item_name`,
            SUM(ii.`qty`) AS `total_qty`,
            SUM(ii.`amount`) AS `total_amount`
        FROM `invoice_items` ii
        JOIN `invoices` v ON v.`id` = ii.`invoice_id`
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
        GROUP BY ii.`item_name`
        ORDER BY `total_amount` DESC, `total_qty` DESC
        LIMIT 5
    ');
    $topStmt->execute([$monthStart, $monthEnd]);
    $topDishes = $topStmt->fetchAll(PDO::FETCH_ASSOC);

    $maxAmount = !empty($topDishes) ? (int) $topDishes[0]['total_amount'] : 0;
    foreach ($topDishes as &$d) {
        $d['total_qty'] = (int) $d['total_qty'];
        $d['total_amount'] = (int) $d['total_amount'];
        $d['percentage'] = $maxAmount > 0 ? (int) round(($d['total_amount'] / $maxAmount) * 100) : 0;
    }
    unset($d);

    // 6. Phân bổ doanh thu theo khu vực trong tháng
    $areaStmt = $pdo->prepare('
        SELECT 
            COALESCE(a.`name`, "Khu vực chung") AS `area_name`,
            COALESCE(a.`code`, "other") AS `area_code`,
            COUNT(v.`id`) AS `invoice_count`,
            COALESCE(SUM(v.`total`), 0) AS `total_revenue`
        FROM `invoices` v
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        LEFT JOIN `dining_tables` t ON t.`code` = v.`table_code`
        LEFT JOIN `restaurant_areas` a ON a.`id` = t.`area_id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
        GROUP BY a.`code`, a.`name`
        ORDER BY `total_revenue` DESC
    ');
    $areaStmt->execute([$monthStart, $monthEnd]);
    $areaBreakdown = $areaStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($areaBreakdown as &$ar) {
        $ar['total_revenue'] = (int) $ar['total_revenue'];
        $ar['invoice_count'] = (int) $ar['invoice_count'];
        $ar['percentage'] = $totalRevenue > 0 ? round(($ar['total_revenue'] / $totalRevenue) * 100, 1) : 0;
    }
    unset($ar);

    // 7. So sánh với tháng trước
    $prevMonthDate = (new DateTime($yearMonth . '-01', new DateTimeZone(REVENUE_TIMEZONE)))->modify('-1 month');
    $prevMonthStr = $prevMonthDate->format('Y-m');
    $prevMonthStart = $prevMonthStr . '-01 00:00:00';
    $prevMonthEnd = $yearMonth . '-01 00:00:00';

    $prevStmt = $pdo->prepare('
        SELECT COALESCE(SUM(v.`total`), 0) AS `prev_revenue`, COUNT(v.`id`) AS `prev_invoices`
        FROM `invoices` v
        JOIN `payments` p ON p.`invoice_id` = v.`id`
        WHERE p.`paid_at` >= ? AND p.`paid_at` < ?
    ');
    $prevStmt->execute([$prevMonthStart, $prevMonthEnd]);
    $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC);
    $prevRevenue = (int) ($prevRow['prev_revenue'] ?? 0);
    $prevInvoices = (int) ($prevRow['prev_invoices'] ?? 0);

    $diffRevenue = $totalRevenue - $prevRevenue;
    $diffPercent = $prevRevenue > 0 ? round(($diffRevenue / $prevRevenue) * 100, 1) : ($totalRevenue > 0 ? 100.0 : 0.0);

    return [
        'mode' => 'month',
        'year_month' => $yearMonth,
        'month_display' => date('m/Y', strtotime($yearMonth . '-01')),
        'days_in_month' => $daysInMonth,
        'total_revenue' => $totalRevenue,
        'total_subtotal' => (int) $summary['total_subtotal'],
        'total_discount' => (int) $summary['total_discount'],
        'total_service_fee' => (int) $summary['total_service_fee'],
        'total_vat' => (int) $summary['total_vat'],
        'total_invoices' => $totalInvoices,
        'total_items_sold' => $totalItemsSold,
        'avg_order_value' => $avgOrderValue,
        'payment_methods' => $paymentMethods,
        'daily_data' => $dailyData,
        'top_dishes' => $topDishes,
        'area_breakdown' => $areaBreakdown,
        'comparison' => [
            'prev_month' => $prevMonthStr,
            'prev_revenue' => $prevRevenue,
            'prev_invoices' => $prevInvoices,
            'diff_amount' => $diffRevenue,
            'diff_percent' => $diffPercent,
            'direction' => $diffRevenue >= 0 ? 'positive' : 'negative',
        ],
    ];
}

/**
 * Trả về thứ tiếng Việt từ chuỗi ngày (YYYY-MM-DD)
 */
function getVietnameseDayOfWeek(string $date): string {
    $days = [
        'Monday' => 'Thứ 2',
        'Tuesday' => 'Thứ 3',
        'Wednesday' => 'Thứ 4',
        'Thursday' => 'Thứ 5',
        'Friday' => 'Thứ 6',
        'Saturday' => 'Thứ 7',
        'Sunday' => 'Chủ nhật',
    ];
    $english = date('l', strtotime($date));
    return $days[$english] ?? $english;
}

/**
 * Xuất dữ liệu báo cáo doanh thu ra tệp CSV (kèm BOM để Excel hiển thị đúng tiếng Việt)
 */
function exportRevenueCsv(array $report): void {
    $mode = $report['mode'] ?? 'day';
    $filename = $mode === 'month' 
        ? 'Bao_cao_doanh_thu_thang_' . str_replace('-', '_', $report['year_month']) . '.csv'
        : 'Bao_cao_doanh_thu_ngay_' . str_replace('-', '_', $report['date']) . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Ghi UTF-8 BOM cho Excel
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Tiêu đề báo cáo
    fputcsv($output, ['SMARTDINE - BÁO CÁO DOANH THU ' . ($mode === 'month' ? 'THÁNG ' . $report['month_display'] : 'NGÀY ' . $report['date_display'])]);
    fputcsv($output, ['Thời gian xuất:', date('d/m/Y H:i:s')]);
    fputcsv($output, ['Tổng doanh thu thực nhận:', number_format($report['total_revenue'], 0, ',', '.') . ' đ']);
    fputcsv($output, ['Tổng số hóa đơn:', $report['total_invoices']]);
    fputcsv($output, ['Giá trị trung bình/đơn:', number_format($report['avg_order_value'], 0, ',', '.') . ' đ']);
    fputcsv($output, ['Tổng số phần món đã bán:', $report['total_items_sold']]);
    fputcsv($output, ['Doanh thu QR:', number_format($report['payment_methods']['qr']['total'], 0, ',', '.') . ' đ (' . $report['payment_methods']['qr']['count'] . ' đơn)']);
    fputcsv($output, ['Doanh thu Tiền mặt:', number_format($report['payment_methods']['cash']['total'], 0, ',', '.') . ' đ (' . $report['payment_methods']['cash']['count'] . ' đơn)']);
    fputcsv($output, []); // Dòng trống

    if ($mode === 'month') {
        // Bảng chi tiết từng ngày trong tháng
        fputcsv($output, ['BẢNG CHI TIẾT DOANH THU TỪNG NGÀY TRONG THÁNG']);
        fputcsv($output, ['Ngày', 'Thứ', 'Số hóa đơn', 'Doanh thu món (Tạm tính)', 'Giảm giá', 'Thuế & Phí', 'Doanh thu thực nhận', 'Tiền mặt', 'Chuyển khoản QR', 'TB/Đơn']);

        foreach ($report['daily_data'] as $d) {
            fputcsv($output, [
                $d['date'],
                $d['day_name'],
                $d['invoices'],
                $d['subtotal'],
                $d['discount'],
                $d['tax_fee'],
                $d['revenue'],
                $d['cash_total'],
                $d['qr_total'],
                $d['avg_order'],
            ]);
        }

        fputcsv($output, [
            'TỔNG CỘNG',
            '-',
            $report['total_invoices'],
            $report['total_subtotal'],
            $report['total_discount'],
            $report['total_service_fee'] + $report['total_vat'],
            $report['total_revenue'],
            $report['payment_methods']['cash']['total'],
            $report['payment_methods']['qr']['total'],
            $report['avg_order_value'],
        ]);
    } else {
        // Bảng danh sách hóa đơn trong ngày
        fputcsv($output, ['DANH SÁCH HÓA ĐƠN ĐÃ THANH TOÁN TRONG NGÀY']);
        fputcsv($output, ['Mã HĐ', 'Bàn', 'Giờ thanh toán', 'Thu ngân', 'Phương thức', 'Tạm tính', 'Giảm giá', 'Phí DV', 'VAT', 'Tổng thanh toán']);

        foreach ($report['invoices_list'] as $inv) {
            fputcsv($output, [
                $inv['code'],
                $inv['table_code'],
                $inv['paid_at'],
                $inv['cashier_name'],
                $inv['method_label'],
                $inv['subtotal'],
                $inv['discount_amount'],
                $inv['service_fee'],
                $inv['vat'],
                $inv['total'],
            ]);
        }

        fputcsv($output, [
            'TỔNG CỘNG',
            '-',
            '-',
            '-',
            '-',
            $report['total_subtotal'],
            $report['total_discount'],
            $report['total_service_fee'],
            $report['total_vat'],
            $report['total_revenue'],
        ]);
    }

    fputcsv($output, []); // Dòng trống

    // Bảng Top 5 món bán chạy
    fputcsv($output, ['TOP 5 MÓN ĂN BÁN CHẠY NHẤT']);
    fputcsv($output, ['Thứ hạng', 'Tên món ăn', 'Số lượng bán (phần)', 'Doanh thu']);
    foreach ($report['top_dishes'] as $rank => $item) {
        fputcsv($output, [
            $rank + 1,
            $item['item_name'],
            $item['total_qty'],
            $item['total_amount'],
        ]);
    }

    fclose($output);
    exit;
}
