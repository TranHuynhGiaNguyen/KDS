
<?php
/**
 * SmartDine - Nguoi 2
 * Buoc 1: Kiem tra ket noi va doc danh sach ban.
 * Chi doc du lieu, khong sua database.
 */

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = getDbConnection();

if (!$pdo) {
    http_response_code(500);
    exit('Lỗi: Không thể kết nối MySQL.');
}

try {
    $stmt = $pdo->query("
        SELECT id, table_code, is_active
        FROM qr_table_codes
        ORDER BY table_code ASC
    ");

    $tables = $stmt->fetchAll();

} catch (PDOException $e) {
    http_response_code(500);
    error_log($e->getMessage());
    exit('Lỗi: Không thể đọc danh sách bàn.');
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>SmartDine - Kiểm tra bàn</title>
</head>
<body>
    <h2>Danh sách bàn từ MySQL</h2>

    <p>Tổng số bàn: <?= count($tables) ?></p>

    <table border="1" cellpadding="10">
        <tr>
            <th>ID</th>
            <th>Mã bàn</th>
            <th>Trạng thái QR</th>
        </tr>

        <?php foreach ($tables as $table): ?>
            <tr>
                <td><?= (int)$table['id'] ?></td>
                <td>
                    <?= htmlspecialchars(
                        $table['table_code'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>
                <td>
                    <?= $table['is_active']
                        ? 'Hoạt động'
                        : 'Đã khóa' ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
