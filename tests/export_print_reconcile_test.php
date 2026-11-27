<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/revenue.php';
require_once __DIR__ . '/../includes/kitchen-metrics.php';
require_once __DIR__ . '/../includes/shift-reconciliation.php';
require_once __DIR__ . '/../includes/excel-report.php';
require_once __DIR__ . '/../includes/print-bill-report.php';
require_once __DIR__ . '/../warehouse/includes/daily_stock.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;

echo "=== KIỂM THỬ IN BILL THUẦN CHỮ, EXCEL BẢNG KẺ & BÁO CÁO ĐÃ BÁN THEO DANH MỤC ===\n";

// 1. Kiểm tra CSS & cấu hình In báo cáo thuần chữ
$indexPhp = file_get_contents(__DIR__ . '/../index.php');
assert($indexPhp !== false);
preg_match("/if\s*\(\s*\\\$page\s*===\s*'admin'\s*\)\s*:\s*\?>([\s\S]*?)<\?php\s*endif;/m", $indexPhp, $adminHeadMatch);
assert(!empty($adminHeadMatch[1]), 'Không tìm thấy khối CSS trang admin trong index.php');
assert(strpos($adminHeadMatch[1], 'orders.css') === false, 'Trang admin không được nạp orders.css');
assert(strpos($adminHeadMatch[1], 'revenue.css') !== false, 'Trang admin phải nạp revenue.css');

$ordersCss = file_get_contents(__DIR__ . '/../assets/css/orders.css');
assert(strpos($ordersCss, 'body:has(.bill-ticket-dialog[open], .receipt-dialog[open])') !== false);

$revenueCss = file_get_contents(__DIR__ . '/../assets/css/revenue.css');
assert(strpos($revenueCss, '.screen-report-ui') !== false && strpos($revenueCss, '.print-bill-report') !== false && strpos($revenueCss, 'Courier New') !== false);
echo "  [PASS] Cấu hình CSS In báo cáo thuần chữ & ẩn Web UI đạt chuẩn\n";

// 2. Kiểm thử Export Excel (.xlsx) bảng kẻ & CSV sạch HTML cho 5 modes
$runExportCase = static function (array $getParams): string {
    $getExport = var_export($getParams, true);
    $adminPath = var_export(realpath(__DIR__ . '/../pages/admin.php'), true);
    $code = <<<PHP
<?php
ob_start();
echo "<!doctype html><html><body><aside class='sidebar'>Sidebar</aside><main class='dashboard-main'>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
\$_SESSION['user_id'] = 1;
\$_SESSION['username'] = 'admin';
\$_SESSION['fullname'] = 'Quản lý ca A';
\$_SESSION['role'] = 'admin';
\$_GET = {$getExport};
require {$adminPath};
PHP;

    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open(escapeshellarg(PHP_BINARY), $descriptors, $pipes);
    assert(is_resource($proc));
    fwrite($pipes[0], $code);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);
    assert($exitCode === 0, "Export failed (exit={$exitCode}): {$stderr}");
    return $stdout;
};

$modes = [
    ['mode' => 'day', 'date' => date('Y-m-d')],
    ['mode' => 'month', 'month' => date('Y-m')],
    ['mode' => 'range', 'start_date' => date('Y-m-d', strtotime('-2 days')), 'end_date' => date('Y-m-d')],
    ['mode' => 'kitchen', 'start_date' => date('Y-m-d', strtotime('-2 days')), 'end_date' => date('Y-m-d')],
    ['mode' => 'reconcile', 'date' => date('Y-m-d')],
];

foreach ($modes as $m) {
    $xlsxBytes = $runExportCase(array_merge(['page' => 'admin', 'export' => 'excel'], $m));
    assert(strncmp($xlsxBytes, "PK\x03\x04", 4) === 0, "Excel mode={$m['mode']} phải là .xlsx hợp lệ");

    $tmpFile = tempnam(sys_get_temp_dir(), 'kds_xlsx_');
    file_put_contents($tmpFile, $xlsxBytes);
    try {
        $loaded = IOFactory::load($tmpFile);
        $sheet = $loaded->getActiveSheet();
        assert($sheet->getShowGridlines() === true);
        $borderTop = $sheet->getStyle('A5')->getBorders()->getTop()->getBorderStyle();
        $borderRight = $sheet->getStyle('A5')->getBorders()->getRight()->getBorderStyle();
        assert(in_array($borderTop, [Border::BORDER_THIN, Border::BORDER_MEDIUM], true));
        assert(in_array($borderRight, [Border::BORDER_THIN, Border::BORDER_MEDIUM], true));
    } finally {
        @unlink($tmpFile);
    }

    $csvBytes = $runExportCase(array_merge(['page' => 'admin', 'export' => 'csv'], $m));
    assert(strncmp($csvBytes, "\xEF\xBB\xBF", 3) === 0);
    assert(stripos($csvBytes, '<!doctype') === false && stripos($csvBytes, '<aside') === false);
}
echo "  [PASS] Xuất Excel (.xlsx) có bảng kẻ & CSV sạch HTML cho cả 5 chế độ\n";

// 3. Kiểm thử Báo cáo thực xuất: CHỈ lấy món ĐÃ BÁN ĐƯỢC hôm nay, CHIA THEO DANH MỤC (Bếp nóng, Rau...)
$pdo = getRevenueDb();
require __DIR__ . '/../warehouse/config.php';
assert(isset($conn) && $conn instanceof mysqli);

$testDate = '2035-12-31';
$testTime = $testDate . ' 20:00:00';
$suffix = substr(bin2hex(random_bytes(4)), 0, 6);

$dishHotSold = "Bò Nướng Test {$suffix}";
$dishVegSold = "Rau Muống Xào Test {$suffix}";
$dishColdSold = "Salad Trộn Test {$suffix}";
$dishUnsoldWh = "Hàng Tồn Không Bán Test {$suffix}";
$dishPendingOpen = "Món Chờ Chưa Ra {$suffix}";

$createdCatId = null;
$createdDishIds = [];
$createdProdIds = [];
$createdOrderIds = [];
$createdInvoiceIds = [];

try {
    // Tạo danh mục "Rau test" và gắn các món test với đúng station/category để kiểm tra phân nhóm
    $pdo->prepare("INSERT INTO `categories` (`name`, `slug`, `menu_type`, `icon`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (?, ?, 'alacarte', '🥬', 99, 'active', NOW(), NOW())")
        ->execute(["Rau tươi {$suffix}", "rau-test-{$suffix}"]);
    $createdCatId = (int) $pdo->lastInsertId();

    $insDish = $pdo->prepare("INSERT INTO `dishes` (`category_id`, `menu_type`, `name`, `price`, `station`, `status`, `created_at`, `updated_at`) VALUES (?, 'alacarte', ?, 50000, ?, 'active', NOW(), NOW())");
    $insDish->execute([1, $dishHotSold, 'hot']);
    $createdDishIds[] = (int) $pdo->lastInsertId();
    $insDish->execute([$createdCatId, $dishVegSold, 'hot']);
    $createdDishIds[] = (int) $pdo->lastInsertId();
    $insDish->execute([2, $dishColdSold, 'cold']);
    $createdDishIds[] = (int) $pdo->lastInsertId();

    // Tạo sản phẩm trong Kho (bao gồm cả 1 sản phẩm KHÔNG BÁN ĐƯỢC trong ngày)
    $insProd = $pdo->prepare('INSERT INTO `products` (`name`, `sku`, `category`, `quantity`, `price`, `created_at`) VALUES (?, ?, ?, ?, 0, ?)');
    $insProd->execute([$dishHotSold, "SKU-H-{$suffix}", '', 10, $testTime]);
    $createdProdIds['hot'] = (int) $pdo->lastInsertId();
    $insProd->execute([$dishVegSold, "SKU-V-{$suffix}", '', 15, $testTime]);
    $createdProdIds['veg'] = (int) $pdo->lastInsertId();
    $insProd->execute([$dishUnsoldWh, "SKU-U-{$suffix}", '', 50, $testTime]);
    $createdProdIds['unsold'] = (int) $pdo->lastInsertId();

    // Đơn đã thanh toán trong ngày: Bò Nướng (4, done) + Rau Muống (6, done) + Hủy (9, cancelled -> không tính)
    $pdo->prepare("INSERT INTO `orders` (`table_code`, `status`, `created_at`) VALUES ('B01', 'paid', ?)")->execute([$testTime]);
    $paidOrderId = (int) $pdo->lastInsertId();
    $createdOrderIds[] = $paidOrderId;

    $insItem = $pdo->prepare("INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `status`, `sent_at`, `done_at`, `created_at`) VALUES (?, ?, 50000, ?, ?, ?, ?, ?)");
    $insItem->execute([$paidOrderId, $dishHotSold, 4, 'done', $testTime, $testTime, $testTime]);
    $insItem->execute([$paidOrderId, $dishVegSold, 6, 'done', $testTime, $testTime, $testTime]);
    $insItem->execute([$paidOrderId, $dishHotSold, 9, 'cancelled', $testTime, null, $testTime]);

    $pdo->prepare("INSERT INTO `invoices` (`order_id`, `table_code`, `subtotal`, `discount_type`, `discount_value`, `discount_amount`, `service_fee`, `vat`, `total`, `service_rate`, `vat_rate`, `created_at`) VALUES (?, 'B01', 500000, 'none', 0, 0, 25000, 42000, 567000, 0.05, 0.08, ?)")->execute([$paidOrderId, $testTime]);
    $invId = (int) $pdo->lastInsertId();
    $createdInvoiceIds[] = $invId;
    $pdo->prepare("INSERT INTO `payments` (`invoice_id`, `method`, `amount`, `cash_received`, `change_amount`, `cashier_id`, `paid_at`) VALUES (?, 'cash', 567000, 600000, 33000, 1, ?)")->execute([$invId, $testTime]);

    // Đơn mở: Salad (2, served) + Món Chờ (4, pending -> chưa tính thực xuất)
    $pdo->prepare("INSERT INTO `orders` (`table_code`, `status`, `created_at`) VALUES ('B02', 'open', ?)")->execute([$testTime]);
    $openOrderId = (int) $pdo->lastInsertId();
    $createdOrderIds[] = $openOrderId;
    $insItem->execute([$openOrderId, $dishColdSold, 2, 'served', $testTime, $testTime, $testTime]);
    $insItem->execute([$openOrderId, $dishPendingOpen, 4, 'pending', $testTime, null, $testTime]);

    // Chốt tồn cuối ngày bên Warehouse cho Bò Nướng (=6 -> tiêu hao 4 khớp KDS 4)
    $saved = warehouse_save_daily_stock_snapshots(
        $conn,
        $testDate,
        [$createdProdIds['hot'] => 6],
        [$createdProdIds['hot'] => 'Khớp cuối ca'],
        'shift_manager',
        false
    );
    assert($saved === 1);

    $rep = getShiftReconciliationReport($pdo, $testDate);
    assert($rep['total_kds_actual_qty'] === 12, "Tổng bán = 4 + 6 + 2 = 12, got {$rep['total_kds_actual_qty']}");
    assert($rep['total_kds_pending_open_qty'] === 4);

    $byName = [];
    foreach ($rep['items'] as $row) {
        $byName[$row['item_name']] = $row;
    }
    // Món KHÔNG bán được & món đang chờ KHÔNG được xuất hiện
    assert(!isset($byName[$dishUnsoldWh]), 'Sản phẩm Kho không bán được trong ngày KHÔNG được nằm trong báo cáo thực xuất');
    assert(!isset($byName[$dishPendingOpen]), 'Món đang chờ chưa ra KHÔNG được nằm trong báo cáo thực xuất');

    // Kiểm tra chia đúng nhóm danh mục: Bếp nóng (4) và Rau / Món rau (6) và Bếp lạnh / Rau & Salad (2)
    $groupByLabel = [];
    foreach ($rep['groups'] as $grp) {
        $groupByLabel[$grp['group_label']] = $grp;
    }
    assert(isset($groupByLabel['Bếp nóng']) && $groupByLabel['Bếp nóng']['total_kds_qty'] === 4);
    assert(isset($groupByLabel['Rau / Món rau']) && $groupByLabel['Rau / Món rau']['total_kds_qty'] === 6);
    assert(isset($groupByLabel['Bếp lạnh / Rau & Salad']) && $groupByLabel['Bếp lạnh / Rau & Salad']['total_kds_qty'] === 2);

    // Kiểm tra bản in bill thuần chữ chia theo nhóm & không chứa món không bán / cột thừa
    $billHtml = renderPureTextPrintBillReport('reconcile', [
        'reconcile'     => $rep,
        'selected_date' => $testDate,
        'printer_name'  => 'Quản lý ca',
    ]);
    assert(strpos($billHtml, '[ BẾP NÓNG ]') !== false);
    assert(strpos($billHtml, '[ RAU / MÓN RAU ]') !== false);
    assert(strpos($billHtml, $dishUnsoldWh) === false);
    assert(strpos($billHtml, 'Bếp ra / HĐ thu') === false);
    assert(strpos($billHtml, 'Bán ra trong Kho') === false);
    assert(strpos($billHtml, 'Chênh lệch') === false);

    // Kiểm tra bản Excel đối chiếu có đúng cấu trúc nhóm danh mục & không chứa cột cũ
    $ss = buildAdminReportSpreadsheet('reconcile', ['reconcile' => $rep]);
    $sheetArr = $ss->getActiveSheet()->toArray();
    $flatExcel = implode("\n", array_map(static fn($r) => implode('|', array_map('strval', $r)), $sheetArr));
    assert(strpos($flatExcel, 'Bếp nóng') !== false && strpos($flatExcel, 'Rau / Món rau') !== false);
    assert(strpos($flatExcel, $dishUnsoldWh) === false);
    assert(strpos($flatExcel, 'Bếp ra / HĐ thu') === false && strpos($flatExcel, 'Bán ra trong Kho') === false);

    echo "  [PASS] Báo cáo thực xuất chỉ giữ món đã bán, chia nhóm Bếp nóng/Rau/Bếp lạnh chuẩn xác\n";
} finally {
    foreach ($createdInvoiceIds as $invId) {
        $pdo->prepare('DELETE FROM `payments` WHERE `invoice_id` = ?')->execute([$invId]);
        $pdo->prepare('DELETE FROM `invoice_items` WHERE `invoice_id` = ?')->execute([$invId]);
        $pdo->prepare('DELETE FROM `invoices` WHERE `id` = ?')->execute([$invId]);
    }
    foreach ($createdOrderIds as $oid) {
        $pdo->prepare('DELETE FROM `order_items` WHERE `order_id` = ?')->execute([$oid]);
        $pdo->prepare('DELETE FROM `orders` WHERE `id` = ?')->execute([$oid]);
    }
    foreach ($createdProdIds as $pid) {
        $pdo->prepare('DELETE FROM `daily_stock_snapshots` WHERE `product_id` = ?')->execute([$pid]);
        $pdo->prepare('DELETE FROM `stock_transactions` WHERE `product_id` = ?')->execute([$pid]);
        $pdo->prepare('DELETE FROM `products` WHERE `id` = ?')->execute([$pid]);
    }
    foreach ($createdDishIds as $did) {
        $pdo->prepare('DELETE FROM `dishes` WHERE `id` = ?')->execute([$did]);
    }
    if ($createdCatId !== null) {
        $pdo->prepare('DELETE FROM `categories` WHERE `id` = ?')->execute([$createdCatId]);
    }
}

echo "\n>>> TẤT CẢ KIỂM THỬ IN BILL THUẦN CHỮ, EXCEL BẢNG KẺ & BÁO CÁO THEO DANH MỤC ĐẠT 100%! <<<\n";
