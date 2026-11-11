<?php
/**
 * SmartDine API - Lưu vị trí tọa độ, hình dạng bàn ăn và vị trí cửa ra vào
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tables.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Chỉ Quản trị viên (Admin) mới có quyền lưu vị trí sơ đồ!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ!']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dữ liệu sơ đồ gửi lên không hợp lệ!']);
    exit;
}

$tables = is_array($data['tables'] ?? null) ? $data['tables'] : [];
$areaLayout = [];

if (!empty($data['area_code'])) {
    $areaLayout = [
        'area_code'    => (string) $data['area_code'],
        'entrance_pos' => (string) ($data['entrance_pos'] ?? 'bottom_center'),
        'entrance_x'   => isset($data['entrance_x']) ? (float) $data['entrance_x'] : 50.0,
        'entrance_y'   => isset($data['entrance_y']) ? (float) $data['entrance_y'] : 96.0,
        'cashier_x'    => isset($data['cashier_x']) ? (float) $data['cashier_x'] : 14.0,
        'cashier_y'    => isset($data['cashier_y']) ? (float) $data['cashier_y'] : 6.0,
    ];
}

$res = saveTableLayout($tables, $areaLayout);
echo json_encode($res);
exit;

