<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/qr-tables.php';

header('Cache-Control: no-store');

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'staff')) {
    respondJson(403, ['error' => 'Bạn không có quyền xem sơ đồ bàn.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondJson(405, ['error' => 'Phương thức không hợp lệ.']);
}

session_write_close();

try {
    $pdo    = getOrdersDb();
    $tables = getAllDiningTables('', 'not_hidden');
    $calls  = fetchPendingStaffCalls($pdo);

    foreach ($tables as &$t) {
        $code = strtoupper(trim((string) ($t['code'] ?? '')));
        $t['pending_call'] = isset($calls[$code]);
    }
    unset($t);

    respondJson(200, ['tables' => $tables]);
} catch (Throwable $error) {
    error_log('table-live: ' . $error->getMessage());
    respondJson(500, ['error' => 'Chưa cập nhật được sơ đồ bàn.']);
}
