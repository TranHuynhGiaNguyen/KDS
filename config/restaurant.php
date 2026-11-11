<?php
/**
 * SmartDine - Thông tin quán dùng cho hóa đơn và thanh toán chuyển khoản QR.
 * Sửa các giá trị dưới đây cho đúng thông tin thật của quán.
 *
 * payment_qr : ảnh QR tĩnh in ở cuối hóa đơn (thay bằng ảnh thật của quán)
 * bank       : tài khoản nhận tiền, dùng để tạo mã VietQR đúng số tiền cho từng hóa đơn
 *   bin          : mã ngân hàng 6 số (MB 970422, Vietcombank 970436, Techcombank 970407,
 *                  BIDV 970418, VietinBank 970415, ACB 970416, VPBank 970432, TPBank 970423,
 *                  Agribank 970405, Sacombank 970403)
 *   name         : tên ngân hàng hiển thị cho nhân viên
 *   account_no   : số tài khoản nhận tiền
 *   account_name : tên chủ tài khoản hiển thị cho nhân viên
 */

return [
    'name'       => 'SMARTDINE RESTAURANT',
    'address'    => '123 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh',
    'phone'      => '0900 000 000',
    'payment_qr' => 'assets/images/payment-qr.svg',
    'bank'       => [
        'bin'          => '970422',
        'name'         => 'MB Bank',
        'account_no'   => '0000000000',
        'account_name' => 'NGUYEN VAN A',
    ],
];