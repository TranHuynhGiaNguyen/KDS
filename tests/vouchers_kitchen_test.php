<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/revenue.php';
require_once __DIR__ . '/../includes/vouchers.php';
require_once __DIR__ . '/../includes/kitchen-metrics.php';

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';
$_SESSION['fullname'] = 'Quản trị viên';

echo "=== TEST 1: VOUCHER FUNCTIONS ===\n";
$allV = getAllVouchers();
assert(is_array($allV), 'getAllVouchers not array');
assert(count($allV) >= 4, 'Vouchers count < 4');
echo "  [PASS] getAllVouchers returned " . count($allV) . " vouchers\n";

$stats = getVoucherStats();
assert(isset($stats['total'], $stats['active'], $stats['inactive'], $stats['total_used']), 'Stats keys missing');
echo "  [PASS] getVoucherStats works: Total={$stats['total']}, Active={$stats['active']}\n";

// Test validateVoucher
$valRes = validateVoucher('WELCOME10', 200000);
assert($valRes['valid'] === true, 'WELCOME10 should be valid for 200k');
assert($valRes['discount_amount'] === 20000, 'WELCOME10 10% of 200k should be 20k');
echo "  [PASS] validateVoucher percent works (10% of 200k = 20k)\n";

$valRes50k = validateVoucher('GIAM50K', 350000);
assert($valRes50k['valid'] === true, 'GIAM50K should be valid for 350k');
assert($valRes50k['discount_amount'] === 50000, 'GIAM50K discount should be 50k');
echo "  [PASS] validateVoucher amount works (50k for 350k)\n";

// Test min_order_value failure
$valMinFail = validateVoucher('GIAM50K', 100000);
assert($valMinFail['valid'] === false, 'GIAM50K should fail for 100k (min 300k)');
echo "  [PASS] validateVoucher rejects order under min_order_value\n";

// Test expired voucher
$valExpFail = validateVoucher('EXPIRED15', 300000);
assert($valExpFail['valid'] === false, 'EXPIRED15 should fail');
echo "  [PASS] validateVoucher rejects expired or inactive voucher\n";

// Test create, update, delete temporary voucher
$testCode = 'TESTCODE' . rand(100, 999);
$cRes = createVoucher([
    'code' => $testCode,
    'name' => 'Voucher kiểm thử tự động',
    'discount_type' => 'percent',
    'discount_value' => 15,
    'min_order_value' => 50000,
    'max_discount_amount' => 30000,
    'usage_limit' => 10,
    'status' => 'active'
]);
assert($cRes['success'] === true, 'createVoucher failed');
$tempId = $cRes['id'];
echo "  [PASS] createVoucher succeeded (id: $tempId)\n";

$uRes = updateVoucher($tempId, ['name' => 'Voucher kiểm thử đã đổi tên']);
assert($uRes['success'] === true, 'updateVoucher failed');
echo "  [PASS] updateVoucher succeeded\n";

$dRes = deleteVoucher($tempId);
assert($dRes['success'] === true, 'deleteVoucher failed');
echo "  [PASS] deleteVoucher succeeded\n";

echo "\n=== TEST 2: KITCHEN PERFORMANCE METRICS ===\n";
$today = date('Y-m-d');
$past7 = date('Y-m-d', strtotime('-6 days'));
$kReport = getKitchenPerformanceReport($past7, $today);

assert(isset($kReport['total_completed']), 'Missing total_completed');
assert(isset($kReport['avg_cook_minutes']), 'Missing avg_cook_minutes');
assert(isset($kReport['avg_wait_minutes']), 'Missing avg_wait_minutes');
assert(isset($kReport['on_time_rate']), 'Missing on_time_rate');
assert(isset($kReport['delayed_count']), 'Missing delayed_count');
assert(is_array($kReport['delayed_alerts']), 'Missing delayed_alerts array');
assert(is_array($kReport['dish_breakdown']), 'Missing dish_breakdown array');
assert(is_array($kReport['station_breakdown']), 'Missing station_breakdown array');

echo "  [PASS] Kitchen Metrics calculated successfully:\n";
echo "         - Total dishes cooked: {$kReport['total_completed']}\n";
echo "         - Avg cooking time: {$kReport['avg_cook_minutes']} mins\n";
echo "         - Avg wait time: {$kReport['avg_wait_minutes']} mins\n";
echo "         - On-time rate: {$kReport['on_time_rate']}%\n";
echo "         - Delayed dishes count: {$kReport['delayed_count']}\n";
echo "         - Delayed alerts items: " . count($kReport['delayed_alerts']) . "\n";
echo "         - Stations breakdown: " . count($kReport['station_breakdown']) . "\n";

echo "\n=== TEST 3: PAGE RENDERING CHECKS ===\n";

function checkPageRender($file, $query = []) {
    $_GET = $query;
    ob_start();
    try {
        include $file;
        $html = ob_get_clean();
        $len = strlen($html);
        return [true, $len];
    } catch (Throwable $e) {
        ob_end_clean();
        return [false, $e->getMessage()];
    }
}

// 1. Render pages/vouchers.php
[$ok, $res] = checkPageRender(__DIR__ . '/../pages/vouchers.php');
assert($ok === true, "vouchers.php error: $res");
echo "  [PASS] pages/vouchers.php rendered successfully ($res bytes)\n";

// 2. Render pages/admin.php mode=kitchen
[$ok, $res] = checkPageRender(__DIR__ . '/../pages/admin.php', [
    'page' => 'admin',
    'mode' => 'kitchen',
    'start_date' => $past7,
    'end_date' => $today
]);
assert($ok === true, "admin.php mode=kitchen error: $res");
echo "  [PASS] pages/admin.php (mode=kitchen) rendered successfully ($res bytes)\n";

// 3. Render pages/admin.php mode=day
[$ok, $res] = checkPageRender(__DIR__ . '/../pages/admin.php', [
    'page' => 'admin',
    'mode' => 'day',
    'date' => $today
]);
assert($ok === true, "admin.php mode=day error: $res");
echo "  [PASS] pages/admin.php (mode=day with kitchen widget) rendered successfully ($res bytes)\n";

// 4. Render pages/payment.php
[$ok, $res] = checkPageRender(__DIR__ . '/../pages/payment.php', [
    'page' => 'payment',
    'table' => 'B03'
]);
assert($ok === true, "payment.php error: $res");
echo "  [PASS] pages/payment.php rendered successfully ($res bytes)\n";

// 5. Render pages/cart.php
[$ok, $res] = checkPageRender(__DIR__ . '/../pages/cart.php');
assert($ok === true, "cart.php error: $res");
echo "  [PASS] pages/cart.php rendered successfully ($res bytes)\n";

echo "\n>>> TẤT CẢ TÍNH NĂNG MỞ RỘNG ĐÃ VƯỢT QUA KIỂM THỬ THÀNH CÔNG! <<<\n";
