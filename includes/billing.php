<?php
/**
 * SmartDine - Tính tiền hóa đơn & ràng buộc thuế VAT theo quy định Việt Nam.
 *
 * Thứ tự tính:
 *   1. Tạm tính      = tổng (đơn giá x số lượng) các món chưa hủy
 *   2. Giảm giá      = theo % hoặc số tiền, trừ vào tạm tính (không vượt tạm tính)
 *   3. Phí phục vụ   = tỷ lệ x (tạm tính - giảm giá)
 *   4. VAT           = thuế suất GTGT hợp lệ VN (0%, 5%, 8%, 10%) x (tạm tính - giảm giá + phí phục vụ)
 *   5. Tổng cộng     = (tạm tính - giảm giá + phí phục vụ) + VAT
 *
 * Mọi số tiền là số nguyên (đồng), làm tròn half-up tại từng bước.
 */

const DISCOUNT_NONE    = 'none';
const DISCOUNT_PERCENT = 'percent';
const DISCOUNT_AMOUNT  = 'amount';

const VN_BILLING_TIMEZONE = 'Asia/Ho_Chi_Minh';
const VN_VAT_ALLOWED_BPS = [0, 500, 800, 1000]; // 0%, 5%, 8%, 10%
const VN_VAT_REDUCTION_DEADLINE = '2026-12-31';

/**
 * Kiểm tra thuế suất VAT có hợp lệ theo quy định hiện hành tại Việt Nam (0%, 5%, 8%, 10%).
 *
 * @throws InvalidArgumentException khi thuế suất nằm ngoài khung quy định Việt Nam
 */
function validateVietnamVatRate(float $vatRate): float {
    $bps = (int) round($vatRate * 10000);
    if (!in_array($bps, VN_VAT_ALLOWED_BPS, true)) {
        throw new InvalidArgumentException(
            'Thuế suất VAT không hợp lệ theo quy định Việt Nam (chỉ áp dụng 0%, 5%, 8% hoặc 10%).'
        );
    }
    return $bps / 10000;
}

/**
 * Xác định thuế suất VAT chuẩn cho dịch vụ ăn uống tại Việt Nam theo ngày (ICT):
 * - Đến hết 31/12/2026: 8% (0.08)
 * - Từ 01/01/2027 trở đi: 10% (0.10)
 */
function resolveVietnamVatRate(?string $asOfDate = null, ?array $rawConfig = null): float {
    $date = ($asOfDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate))
        ? $asOfDate
        : (new DateTimeImmutable('now', new DateTimeZone(VN_BILLING_TIMEZONE)))->format('Y-m-d');

    $deadline = (string) ($rawConfig['vat_reduction_until'] ?? VN_VAT_REDUCTION_DEADLINE);
    $reducedRate = validateVietnamVatRate((float) ($rawConfig['reduced_vat_rate'] ?? 0.08));
    $standardRate = validateVietnamVatRate((float) ($rawConfig['standard_vat_rate'] ?? 0.10));

    return ($date <= $deadline) ? $reducedRate : $standardRate;
}

/** Đọc cấu hình tính tiền đã ràng buộc VAT theo ngày hiện tại (ICT). */
function getBillingConfig(): array {
    static $config = null;
    if ($config === null) {
        $raw = require __DIR__ . '/../config/billing.php';
        $vatSetting = $raw['vat_rate'] ?? 'auto';
        $resolvedVat = ($vatSetting === 'auto' || $vatSetting === null)
            ? resolveVietnamVatRate(null, $raw)
            : validateVietnamVatRate((float) $vatSetting);

        $raw['vat_rate'] = $resolvedVat;
        $config = $raw;
    }
    return $config;
}

/**
 * Tính hóa đơn từ danh sách món với ràng buộc thuế VAT theo quy định Việt Nam.
 *
 * @param array      $items    Mỗi phần tử: ['name' => string, 'price' => int, 'qty' => int, 'status' => string (tùy chọn)]
 * @param array      $discount ['type' => none|percent|amount, 'value' => int]
 * @param array|null $config   Ghi đè cấu hình (bắt buộc tuân thủ khung VAT Việt Nam)
 * @throws InvalidArgumentException khi dữ liệu hoặc thuế suất VAT không hợp lệ
 */
function calculateBill(array $items, array $discount = [], ?array $config = null): array {
    $baseConfig = getBillingConfig();
    if ($config !== null) {
        $merged = array_merge($baseConfig, $config);
        $rawVat = $config['vat_rate'] ?? 'auto';
        $effectiveDate = isset($config['effective_date']) ? (string) $config['effective_date'] : null;

        if ($rawVat === 'auto' || $rawVat === null) {
            $merged['vat_rate'] = resolveVietnamVatRate($effectiveDate, $merged);
        } else {
            $validated = validateVietnamVatRate((float) $rawVat);
            if ($effectiveDate !== null && $effectiveDate > (string) ($merged['vat_reduction_until'] ?? VN_VAT_REDUCTION_DEADLINE)) {
                $bps = (int) round($validated * 10000);
                if ($bps === 800) {
                    throw new InvalidArgumentException(
                        'Từ 01/01/2027 hết thời hạn giảm thuế VAT 8%, vui lòng áp dụng mức 10% theo quy định Việt Nam.'
                    );
                }
            }
            $merged['vat_rate'] = $validated;
        }
        $config = $merged;
    } else {
        $config = $baseConfig;
    }

    $serviceRate = (float) ($config['service_fee_rate'] ?? 0);
    if ($serviceRate < 0 || $serviceRate > 0.2) {
        throw new InvalidArgumentException('Phí phục vụ không hợp lệ (tối đa 20%).');
    }

    $vatRate = validateVietnamVatRate((float) ($config['vat_rate'] ?? 0.08));

    $lines    = buildBillLines($items, $config['excluded_statuses'] ?? ['cancelled']);
    $subtotal = array_sum(array_column($lines, 'amount'));

    $discountType   = $discount['type'] ?? DISCOUNT_NONE;
    $discountValue  = (int) ($discount['value'] ?? 0);
    $discountAmount = calculateDiscountAmount($subtotal, $discountType, $discountValue);

    $afterDiscount = $subtotal - $discountAmount;
    $serviceFee    = roundMoney($afterDiscount * $serviceRate);
    $taxable       = $afterDiscount + $serviceFee;
    $vat           = roundMoney($taxable * $vatRate);

    return [
        'lines'           => $lines,
        'subtotal'        => $subtotal,
        'discount_type'   => $discountType,
        'discount_value'  => $discountType === DISCOUNT_NONE ? 0 : $discountValue,
        'discount_amount' => $discountAmount,
        'voucher_code'    => !empty($discount['voucher_code']) ? strtoupper(trim($discount['voucher_code'])) : null,
        'service_fee'     => $serviceFee,
        'vat'             => $vat,
        'total'           => $taxable + $vat,
        'vat_rate'        => $vatRate,
        'service_rate'    => $serviceRate,
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
