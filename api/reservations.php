<?php

require_once __DIR__ . '/../includes/http.php';
require_once __DIR__ . '/../includes/reservations.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-store');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$payload = [];

if ($method === 'GET' && $action === 'availability') {
    try {
        $slot = validateReservationSlot($_GET);
        respondJson(200, ['success' => true, 'tables' => getAvailableReservationTables($slot)]);
    } catch (InvalidArgumentException $e) {
        respondJson(422, ['success' => false, 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        error_log('Reservation availability failed: ' . $e->getMessage());
        respondJson(500, ['success' => false, 'message' => 'Không thể kiểm tra bàn trống lúc này. Vui lòng thử lại.']);
    }
}

if ($method !== 'POST') {
    respondJson(405, ['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    respondJson(400, ['success' => false, 'message' => 'Dữ liệu gửi lên không hợp lệ.']);
}

$token = (string) ($payload['csrf_token'] ?? '');
if (empty($_SESSION['reservation_csrf']) || !hash_equals($_SESSION['reservation_csrf'], $token)) {
    respondJson(403, ['success' => false, 'message' => 'Phiên thao tác đã hết hạn. Tải lại trang và thử lại.']);
}

try {
    switch ($payload['action'] ?? '') {
        case 'create':
            $reservation = createReservation($payload);
            respondJson(201, ['success' => true, 'message' => 'Đặt bàn thành công.', 'reservation' => $reservation]);
        case 'history':
            $history = getReservationHistory((string) ($payload['phone'] ?? ''), (string) ($payload['reference'] ?? ''));
            respondJson(200, ['success' => true, 'reservations' => $history]);
        case 'cancel':
            cancelReservation((string) ($payload['phone'] ?? ''), (string) ($payload['reference'] ?? ''));
            respondJson(200, ['success' => true, 'message' => 'Đã hủy lịch đặt bàn.']);
        default:
            respondJson(400, ['success' => false, 'message' => 'Thao tác không hợp lệ.']);
    }
} catch (InvalidArgumentException $e) {
    respondJson(422, ['success' => false, 'message' => $e->getMessage()]);
} catch (DomainException $e) {
    respondJson(409, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Reservation request failed: ' . $e->getMessage());
    respondJson(500, ['success' => false, 'message' => 'Không thể xử lý yêu cầu lúc này. Vui lòng thử lại.']);
}
