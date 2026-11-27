<?php
/**
 * SmartDine - Trang xem & in hàng loạt mã QR gán cứng cho từng bàn.
 * Tất cả QR trỏ trực tiếp về `qr-order.php?qr=<fixed_token>`.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/qr-tables.php';

$pdo = getDbConnection();

if (!$pdo) {
    http_response_code(503);
    exit('Không thể kết nối MySQL. Kiểm tra cấu hình database.');
}

try {
    $tables = getAllTableQrCodes($pdo);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Không đọc được danh sách QR theo bàn.');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QR Code cố định theo bàn - SmartDine</title>
<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    background: #f8fafc;
    padding: 25px;
    color: #0f172a;
    margin: 0;
}
.head-bar {
    max-width: 1120px;
    margin: 0 auto 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.head-bar h1 { margin: 0; font-size: 24px; }
.subtitle { margin: 4px 0 0; color: #64748b; font-size: 14px; }
.btn-print-all {
    background: #ea580c;
    color: #fff;
    border: 0;
    border-radius: 10px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}
.qr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 18px;
    max-width: 1120px;
    margin: 0 auto;
}
.qr-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px 16px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
.qr-card h2 {
    margin: 0 0 4px;
    color: #ea580c;
    font-size: 20px;
}
.qr-meta {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 12px;
}
.qr-svg-wrap {
    width: 180px;
    height: 180px;
    margin: 0 auto;
    padding: 8px;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    background: #fff;
}
.qr-svg-wrap svg {
    width: 100%;
    height: 100%;
    display: block;
}
.qr-token-tag {
    margin-top: 8px;
    font-family: monospace;
    font-size: 11px;
    color: #64748b;
    word-break: break-all;
}
.qr-card a {
    display: inline-block;
    margin-top: 12px;
    padding: 7px 14px;
    border-radius: 8px;
    background: #fff7ed;
    color: #c2410c;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid #fed7aa;
}
@media print {
    body { background: #fff; padding: 10px; }
    .no-print { display: none !important; }
    .qr-card { break-inside: avoid; box-shadow: none; }
}
</style>
<script src="../../assets/js/vendor/qrcode.js"></script>
</head>
<body>

<div class="head-bar">
  <div>
    <h1>SmartDine — QR Code Gán Cứng Theo Bàn</h1>
    <p class="subtitle">Mỗi bàn sử dụng duy nhất 1 mã QR cố định, quét để mở trực tiếp <b>qr-order.php</b> gọi món tại bàn.</p>
  </div>
  <button type="button" class="btn-print-all no-print" onclick="window.print()">In toàn bộ mã QR</button>
</div>

<div class="qr-grid">
<?php foreach ($tables as $t): ?>
    <?php
        $token = (string) ($t['qr_token'] ?? '');
        if ($token === '') {
            continue;
        }
        $url = buildTableQrOrderUrl($token);
    ?>
    <div class="qr-card">
        <h2>Bàn <?= htmlspecialchars((string) $t['table_code'], ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="qr-meta">
          <?= htmlspecialchars((string) $t['area_name'], ENT_QUOTES, 'UTF-8') ?> · <?= (int) $t['capacity'] ?> chỗ
        </div>

        <div class="qr-svg-wrap" data-fixed-qr="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"></div>

        <div class="qr-token-tag">Mã bàn cố định: <?= htmlspecialchars(substr($token, 0, 12), ENT_QUOTES, 'UTF-8') ?>…</div>

        <a class="no-print" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
            Mở qr-order.php
        </a>
    </div>
<?php endforeach; ?>
</div>

<?php if (empty($tables)): ?>
    <p style="text-align:center">Chưa có bàn nào được kích hoạt.</p>
<?php endif; ?>

<script>
document.querySelectorAll('[data-fixed-qr]').forEach(function (box) {
    if (typeof qrcode !== 'function') return;
    var qr = qrcode(0, 'M');
    qr.addData(box.dataset.fixedQr);
    qr.make();
    box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 1, scalable: true });
});
</script>

</body>
</html>
