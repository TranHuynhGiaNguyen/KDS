<?php
/**
 * SmartDine - Tạo nội dung mã VietQR (chuẩn EMVCo / NAPAS) cho từng hóa đơn.
 * Mã chứa sẵn tài khoản nhận, số tiền và nội dung chuyển khoản; app ngân hàng nào quét cũng tự điền.
 */

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/restaurant.php';

function formatTransferContent(int $orderId, string $tableCode): string {
    return formatInvoiceCode($orderId) . ' Ban ' . $tableCode;
}

function emvField(string $id, string $value): string {
    return $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
}

function crc16Ccitt(string $data): string {
    $crc = 0xFFFF;

    for ($i = 0, $length = strlen($data); $i < $length; $i++) {
        $crc ^= ord($data[$i]) << 8;
        for ($bit = 0; $bit < 8; $bit++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }

    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function buildVietQrPayload(string $bankBin, string $accountNo, int $amount, string $content): string {
    $content  = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $content), 0, 25);
    $merchant = emvField('00', 'A000000727')
        . emvField('01', emvField('00', $bankBin) . emvField('01', $accountNo))
        . emvField('02', 'QRIBFTTA');

    $payload = emvField('00', '01')
        . emvField('01', '12')
        . emvField('38', $merchant)
        . emvField('53', '704')
        . emvField('54', (string) $amount)
        . emvField('58', 'VN')
        . emvField('62', emvField('08', $content));

    $payload .= '6304';

    return $payload . crc16Ccitt($payload);
}

function buildInvoiceQrPayload(?int $orderId, string $tableCode, int $amount): string {
    if ($orderId === null || $amount <= 0) {
        return '';
    }

    $bank = getRestaurantInfo()['bank'];

    return buildVietQrPayload($bank['bin'], $bank['account_no'], $amount, formatTransferContent($orderId, $tableCode));
}