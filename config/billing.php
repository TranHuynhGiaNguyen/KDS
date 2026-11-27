<?php
/**
 * SmartDine - Cấu hình tính tiền hóa đơn theo quy định thuế GTGT (VAT) Việt Nam.
 *
 * Theo Nghị quyết giảm thuế GTGT hiện hành:
 *   - Thuế suất VAT dịch vụ ăn uống là 8% đến hết ngày 31/12/2026.
 *   - Từ ngày 01/01/2027 tự động áp dụng mức chuẩn 10%.
 *   - Các mức thuế suất GTGT hợp lệ tại Việt Nam: 0%, 5%, 8%, 10%.
 */
return [
    'vat_rate'            => 'auto',
    'reduced_vat_rate'    => 0.08,
    'standard_vat_rate'   => 0.10,
    'vat_reduction_until' => '2026-12-31',
    'service_fee_rate'    => 0.05,
    'excluded_statuses'   => ['cancelled'],
];
