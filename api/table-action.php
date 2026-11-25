<?php
declare(strict_types=1);

/**
 * API (POST): Nghiệp vụ Gán bàn, Chuyển bàn và Trả bàn trống (dành cho Staff / Admin).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tables.php';

if (!isLoggedIn() || !canRoleAccessPage(currentRole(), 'staff')) {
    respondJson(403, ['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác bàn.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respondJson(405, ['success' => false, 'message' => 'Chỉ chấp nhận phương thức POST.']);
}

$raw = file_get_contents('php://input');
$jsonBody = json_decode((string) $raw, true);
$payload = is_array($jsonBody) ? $jsonBody : $_POST;

$action = trim((string) ($payload['action'] ?? ''));

try {
    $pdo = getOrdersDb();

    if ($action === 'assign') {
        $result = assignTable($pdo, [
            'table_code'     => $payload['table_code'] ?? '',
            'guest_count'    => $payload['guest_count'] ?? 2,
            'customer_name'  => $payload['customer_name'] ?? '',
            'customer_phone' => $payload['customer_phone'] ?? '',
            'staff_id'       => $payload['staff_id'] ?? ($_SESSION['user_id'] ?? 0),
            'note'           => $payload['note'] ?? '',
            'allow_update'   => !empty($payload['allow_update']),
        ]);
        respondJson($result['success'] ? 200 : 409, $result);
    }

    if ($action === 'transfer') {
        $result = transferTable(
            $pdo,
            (string) ($payload['from_table'] ?? ''),
            (string) ($payload['to_table'] ?? ''),
            (string) ($payload['note'] ?? '')
        );
        if ($result['success']) {
            $result['redirect_url'] = 'index.php?page=table-order&table=' . urlencode($result['to_table']);
        }
        respondJson($result['success'] ? 200 : 409, $result);
    }

    if ($action === 'merge') {
        $result = mergeTables(
            $pdo,
            (string) ($payload['from_table'] ?? ''),
            (string) ($payload['to_table'] ?? ''),
            (string) ($payload['note'] ?? '')
        );
        if ($result['success']) {
            $result['redirect_url'] = 'index.php?page=table-order&table=' . urlencode($result['to_table']);
        }
        respondJson($result['success'] ? 200 : 409, $result);
    }

    if ($action === 'release') {
        $result = releaseEmptyTableAssignment($pdo, (string) ($payload['table_code'] ?? ''));
        respondJson($result['success'] ? 200 : 409, $result);
    }

    respondJson(400, ['success' => false, 'message' => 'Thao tác không hợp lệ (chỉ hỗ trợ assign, transfer, merge, release).']);
} catch (RuntimeException | PDOException $e) {
    error_log('api/table-action error: ' . $e->getMessage());
    respondJson(500, ['success' => false, 'message' => 'Lỗi hệ thống, vui lòng thử lại.']);
}
