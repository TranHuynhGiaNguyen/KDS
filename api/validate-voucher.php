<?php
/**
 * SmartDine - API kiểm tra tính hợp lệ của mã Voucher / Coupon.
 * Dùng cho cả Khách hàng (menu QR / giỏ hàng) và Thu ngân.
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/vouchers.php';

$code = trim($_REQUEST['code'] ?? '');
$subtotal = max(0, (int) ($_REQUEST['subtotal'] ?? 0));

if ($code === '') {
    echo json_encode([
        'success' => false,
        'valid'   => false,
        'message' => 'Vui lòng nhập mã ưu đãi!'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = validateVoucher($code, $subtotal);

echo json_encode([
    'success'         => $result['valid'],
    'valid'           => $result['valid'],
    'message'         => $result['message'],
    'code'            => $result['code'] ?? $code,
    'name'            => $result['name'] ?? '',
    'discount_type'   => $result['discount_type'] ?? 'percent',
    'discount_value'  => $result['discount_value'] ?? 0,
    'discount_amount' => $result['discount_amount'] ?? 0,
    'final_subtotal'  => $result['final_subtotal'] ?? $subtotal,
], JSON_UNESCAPED_UNICODE);
