<?php
declare(strict_types=1);

require_once __DIR__ . '/backend.php';

header('Cache-Control: no-store');

try {
    $token = $_GET['qr'] ?? '';

    if (!is_string($token)) {
        qoFail(400, 'Mã QR không hợp lệ.');
    }

    // Backend xác thực QR, lấy đúng bàn,
    // tạo hoặc lấy lượt dùng bàn và giỏ MySQL.
    $contextKey = qoEnter(qoDb(), $token);

    header(
        'Location: ordering.php?view=menu&ctx=' .
        rawurlencode($contextKey)
    );

    exit;
} catch (Throwable $e) {
    $status = $e instanceof RuntimeException
        ? $e->getCode()
        : 0;

    if (!in_array($status, [400, 403, 404, 409, 503], true)) {
        error_log('QR entry: ' . $e->getMessage());

        $status = 500;
        $message = 'Không mở được QR. Kiểm tra MySQL và database.';
    } else {
        $message = $e->getMessage();
    }

    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');

    echo $message;
}