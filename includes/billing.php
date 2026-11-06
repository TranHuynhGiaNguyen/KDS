<?php
/**
 * SmartDine - Tính tiền hóa đơn.
 *
 * Thứ tự tính:
 *   1. Tạm tính      = tổng (đơn giá x số lượng) các món chưa hủy
 *   2. Giảm giá      = theo % hoặc số tiền, trừ vào tạm tính (không vượt tạm tính)
 *   3. Phí phục vụ   = tỷ lệ x (tạm tính - giảm giá)
 *   4. VAT           = tỷ lệ x (tạm tính - giảm giá + phí phục vụ)
 *   5. Tổng cộng     = (tạm tính - giảm giá + phí phục vụ) + VAT
 *
 * Mọi số tiền là số nguyên (đồng), làm tròn half-up tại từng bước.
 */

const DISCOUNT_NONE    = 'none';
const DISCOUNT_PERCENT = 'percent';
const DISCOUNT_AMOUNT  = 'amount';

/** Đọc cấu hình tính tiền (có cache trong request). */
function getBillingConfig(): array {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/billing.php';
    }
    return $config;
}

/**
 * Tính hóa đơn từ danh sách món.
 *
 * @param array $items    Mỗi phần tử: ['name' => string, 'price' => int, 'qty' => int, 'status' => string (tùy chọn)]
 * @param array $discount ['type' => none|percent|amount, 'value' => int]
 * @param array|null $config Ghi đè cấu hình (dùng khi kiểm thử)
 * @throws InvalidArgumentException khi dữ liệu không hợp lệ
 */
function calculateBill(array $items, array $discount = [], ?array $config = null): array {
    $config = $config ?? getBillingConfig();

    $lines    = buildBillLines($items, $config['excluded_statuses']);
    $subtotal = array_sum(array_column($lines, 'amount'));

    $discountType   = $discount['type'] ?? DISCOUNT_NONE;
    $discountValue  = (int) ($discount['value'] ?? 0);
    $discountAmount = calculateDiscountAmount($subtotal, $discountType, $discountValue);

    $afterDiscount = $subtotal - $discountAmount;
    $serviceFee    = roundMoney($afterDiscount * $config['service_fee_rate']);
    $taxable       = $afterDiscount + $serviceFee;
    $vat           = roundMoney($taxable * $config['vat_rate']);

    return [
        'lines'           => $lines,
        'subtotal'        => $subtotal,
        'discount_type'   => $discountType,
        'discount_value'  => $discountType === DISCOUNT_NONE ? 0 : $discountValue,
        'discount_amount' => $discountAmount,
        'service_fee'     => $serviceFee,
        'vat'             => $vat,
        'total'           => $taxable + $vat,
        'vat_rate'        => $config['vat_rate'],
        'service_rate'    => $config['service_fee_rate'],
    ];
}

/** Chuyển danh sách món thành các dòng hóa đơn, bỏ món bị hủy. */
function buildBillLines(array $items, array $excludedStatuses): array {
    $lines = [];
    foreach ($items as $item) {
        if (in_array($item['status'] ?? '', $excludedStatuses, true)) {
            continue;
        }

        $price = (int) ($item['price'] ?? 0);
        $qty   = (int) ($item['qty'] ?? 0);
        if ($price < 0 || $qty < 1) {
            throw new InvalidArgumentException('Món "' . ($item['name'] ?? '?') . '" có đơn giá hoặc số lượng không hợp lệ.');
        }

        $lines[] = [
            'name'   => (string) ($item['name'] ?? ''),
            'price'  => $price,
            'qty'    => $qty,
            'amount' => $price * $qty,
        ];
    }
    return $lines;
}

/** Số tiền giảm thực tế, không vượt tạm tính. */
function calculateDiscountAmount(int $subtotal, string $type, int $value): int {
    switch ($type) {
        case DISCOUNT_NONE:
            return 0;
        case DISCOUNT_PERCENT:
            if ($value < 0 || $value > 100) {
                throw new InvalidArgumentException('Phần trăm giảm giá phải từ 0 đến 100.');
            }
            return roundMoney($subtotal * $value / 100);
        case DISCOUNT_AMOUNT:
            if ($value < 0) {
                throw new InvalidArgumentException('Số tiền giảm giá không được âm.');
            }
            return min($value, $subtotal);
        default:
            throw new InvalidArgumentException('Loại giảm giá không hợp lệ: ' . $type);
    }
}

/** Làm tròn half-up về số nguyên đồng. */
function roundMoney(float $amount): int {
    return (int) round($amount, 0, PHP_ROUND_HALF_UP);
}

/** Định dạng tiền Việt: 204120 -> "204.120đ". */
function formatMoney(int $amount): string {
    return number_format($amount, 0, ',', '.') . 'đ';
}