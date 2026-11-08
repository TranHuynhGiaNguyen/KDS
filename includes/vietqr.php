<?php
/**
 * SmartDine - Sinh mã QR chuyển khoản bằng VietQR Quick Link (https://vietqr.io).
 * Mã QR chứa sẵn số tài khoản của quán, số tiền và nội dung chuyển khoản.
 * Ảnh được tải từ img.vietqr.io nên máy thu ngân cần có Internet.
 */

require_once __DIR__ . '/restaurant.php';
require_once __DIR__ . '/checkout.php';

const VIETQR_TEMPLATE = 'compact2';

function buildTransferContent(int $orderId, string $tableCode): string {
    return formatInvoiceCode($orderId) . ' Ban ' . $tableCode;
}

function buildVietQrUrl(int $amount, string $content): string {
    $bank  = getRestaurantInfo()['bank'];
    $query = http_build_query([
        'amount'      => $amount,
        'addInfo'     => $content,
        'accountName' => $bank['account_name'],
    ], '', '&', PHP_QUERY_RFC3986);

    return sprintf(
        'https://img.vietqr.io/image/%s-%s-%s.png?%s',
        rawurlencode($bank['id']),
        rawurlencode($bank['account_no']),
        VIETQR_TEMPLATE,
        $query
    );
}