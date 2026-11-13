
<?php
// NGUOI 2 - Quan ly QR Code theo ban
require_once __DIR__ . '/../../config/database.php';

$pdo = getDbConnection();

if (!$pdo) {
    http_response_code(503);
    exit('Khong the ket noi MySQL. Kiem tra cau hinh database.');
}

try {
    $stmt = $pdo->query(
        "SELECT table_code, qr_token
         FROM qr_table_codes
         WHERE is_active = 1
         ORDER BY table_code"
    );
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Khong doc duoc danh sach QR. Kiem tra bang qr_table_codes.');
}

// Dia chi website - co the doi thanh IP LAN khi quet bang dien thoai
$https = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off';

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (!preg_match('/^[a-zA-Z0-9.\-:\[\]]+$/', $host)) {
    http_response_code(400);
    exit('Địa chỉ máy chủ không hợp lệ.');
}

$modulePath = rtrim(
    str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])),
    '/'
);

$baseUrl = ($https ? 'https://' : 'http://')
    . $host
    . $modulePath
    . '/';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QR Code theo bàn - SmartDine</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f8fafc;
    padding: 25px;
    color: #172033;
}
h1 { text-align: center; }
.subtitle { text-align: center; color: #64748b; }
.qr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    max-width: 1100px;
    margin: 30px auto;
}
.qr-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 22px;
    text-align: center;
}
.qr-card h2 { color: #f97316; }
.qr-card img { width: 190px; height: 190px; }
.qr-card a {
    display: block;
    margin-top: 14px;
    color: #ea580c;
    overflow-wrap: anywhere;
}
@media print {
    .subtitle { display: none; }
    .qr-card { break-inside: avoid; }
}
</style>
</head>
<body>

<h1>SmartDine - QR Code theo bàn</h1>
<p class="subtitle">
    Danh sách mã QR lấy trực tiếp từ MySQL
</p>

<div class="qr-grid">
<?php foreach ($tables as $table): ?>
    <?php
        $url = $baseUrl
    . 'qr-entry.php?qr='
    . rawurlencode($table['qr_token']);

        // Dich vu tao anh QR tu URL
        $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?'
                 . http_build_query([
                     'size' => '220x220',
                     'data' => $url
                 ]);
    ?>
    <div class="qr-card">
        <h2>
            Bàn <?= htmlspecialchars($table['table_code'], ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <img src="<?= htmlspecialchars($qrImage, ENT_QUOTES, 'UTF-8') ?>"
             alt="QR Code bàn <?= htmlspecialchars($table['table_code'], ENT_QUOTES, 'UTF-8') ?>">

        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener noreferrer">
            Mở thực đơn
        </a>
    </div>
<?php endforeach; ?>
</div>

<?php if (empty($tables)): ?>
    <p style="text-align:center">Chưa có bàn nào được kích hoạt.</p>
<?php endif; ?>

</body>
</html>
