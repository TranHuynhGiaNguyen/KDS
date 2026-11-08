<?php
/**
 * SmartDine - Thông tin quán dùng cho hóa đơn và mã QR chuyển khoản.
 * Sửa các giá trị dưới đây cho đúng thông tin thật của quán.
 *
 * bank.id           : mã ngân hàng theo VietQR (ví dụ MB, VCB, ACB, TCB hoặc mã BIN như 970422)
 * bank.account_no   : số tài khoản nhận tiền của quán
 * bank.account_name : tên chủ tài khoản (không dấu, viết hoa)
 * payment_qr        : ảnh QR tĩnh in ở cuối hóa đơn (thay bằng ảnh thật của quán)
 */

return [
    'name'       => 'SMARTDINE RESTAURANT',
    'address'    => '123 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh',
    'phone'      => '0900 000 000',
    'payment_qr' => 'assets/images/payment-qr.svg',
    'bank'       => [
        'id'           => 'MB',
        'name'         => 'MB Bank',
        'account_no'   => '0000000000',
        'account_name' => 'NHA HANG SMARTDINE',
    ],
];