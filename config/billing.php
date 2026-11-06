<?php
/**
 * SmartDine - Cấu hình tính tiền hóa đơn.
 *
 * VAT dịch vụ ăn uống là 8% đến hết 31/12/2026, từ 01/01/2027 trở lại 10%
 * nếu không có văn bản gia hạn.
 */
return [
    'vat_rate'          => 0.08,
    'service_fee_rate'  => 0.05,
    'excluded_statuses' => ['cancelled'],
];