<?php
/**
 * Kiểm thử hàm tính tiền. Chạy: php tests/billing_test.php
 */
require __DIR__ . '/../includes/billing.php';

$config = ['vat_rate' => 0.08, 'service_fee_rate' => 0.05, 'excluded_statuses' => ['cancelled']];
$failed = 0;

function check(string $name, $actual, $expected): void {
    global $failed;
    if ($actual === $expected) {
        echo "  OK   $name\n";
    } else {
        $failed++;
        echo "  FAIL $name: nhận " . var_export($actual, true) . ", mong đợi " . var_export($expected, true) . "\n";
    }
}

$items = [
    ['name' => 'Cơm gà Hải Nam', 'price' => 75000, 'qty' => 1],
    ['name' => 'Mì xào bò',      'price' => 85000, 'qty' => 1],
    ['name' => 'Trà đào cam sả', 'price' => 35000, 'qty' => 2],
];

echo "Không giảm giá (tạm tính 230.000)\n";
$b = calculateBill($items, [], $config);
check('tạm tính', $b['subtotal'], 230000);
check('phí phục vụ 5%', $b['service_fee'], 11500);
check('VAT 8% trên 241.500', $b['vat'], 19320);
check('tổng', $b['total'], 260820);

echo "Giảm 10% (ví dụ trong tư vấn: 200.000 -> 204.120)\n";
$b = calculateBill([['name' => 'A', 'price' => 100000, 'qty' => 2]], ['type' => 'percent', 'value' => 10], $config);
check('giảm', $b['discount_amount'], 20000);
check('phí phục vụ', $b['service_fee'], 9000);
check('VAT', $b['vat'], 15120);
check('tổng', $b['total'], 204120);

echo "Giảm theo số tiền\n";
$b = calculateBill($items, ['type' => 'amount', 'value' => 30000], $config);
check('giảm', $b['discount_amount'], 30000);
check('tổng (200.000 + 10.000 + 16.800)', $b['total'], 226800);

echo "Giảm vượt tạm tính -> bị chặn ở tạm tính\n";
$b = calculateBill($items, ['type' => 'amount', 'value' => 999999], $config);
check('giảm tối đa bằng tạm tính', $b['discount_amount'], 230000);
check('tổng = 0', $b['total'], 0);

echo "Món đã hủy không tính tiền\n";
$b = calculateBill(array_merge($items, [['name' => 'Hủy', 'price' => 50000, 'qty' => 1, 'status' => 'cancelled']]), [], $config);
check('tạm tính vẫn 230.000', $b['subtotal'], 230000);

echo "Đơn rỗng\n";
check('tổng = 0', calculateBill([], [], $config)['total'], 0);

echo "Dữ liệu sai phải báo lỗi\n";
foreach ([
    'phần trăm > 100' => fn() => calculateBill($items, ['type' => 'percent', 'value' => 150], $config),
    'số tiền âm'      => fn() => calculateBill($items, ['type' => 'amount', 'value' => -1], $config),
    'loại lạ'         => fn() => calculateBill($items, ['type' => 'xyz', 'value' => 1], $config),
    'số lượng 0'      => fn() => calculateBill([['name' => 'A', 'price' => 1, 'qty' => 0]], [], $config),
] as $name => $call) {
    try { $call(); check($name, 'không lỗi', 'lỗi'); }
    catch (InvalidArgumentException $e) { check($name, 'lỗi', 'lỗi'); }
}

echo "Định dạng tiền\n";
check('formatMoney', formatMoney(204120), '204.120đ');

echo $failed === 0 ? "\nTẤT CẢ ĐỀU ĐẠT\n" : "\nCÓ $failed LỖI\n";
exit($failed === 0 ? 0 : 1);