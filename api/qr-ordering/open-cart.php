<?php
declare(strict_types=1);

require_once __DIR__ . '/backend.php';

// Đọc giỏ theo ngữ cảnh đã được xác thực khi quét QR.
$_GET['api'] = 'cart';

qoApi();