<?php
/**
 * SmartDine - Quản lý Khuyến mãi & Giảm giá (Voucher / Coupon).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/billing.php';

function getVouchersDb(): PDO {
    return getDbConnection();
}

/**
 * Lấy danh sách tất cả voucher kèm tìm kiếm và lọc trạng thái.
 */
function getAllVouchers(string $search = '', string $status = ''): array {
    $pdo = getVouchersDb();
    if (!$pdo) return [];

    $sql = "SELECT * FROM `vouchers` WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (`code` LIKE :search OR `name` LIKE :search)";
        $params[':search'] = '%' . trim($search) . '%';
    }

    if ($status !== '' && in_array($status, ['active', 'inactive'], true)) {
        $sql .= " AND `status` = :status";
        $params[':status'] = $status;
    }

    $sql .= " ORDER BY `id` DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Thống kê tổng quan voucher cho dashboard.
 */
function getVoucherStats(): array {
    $pdo = getVouchersDb();
    if (!$pdo) {
        return ['total' => 0, 'active' => 0, 'inactive' => 0, 'total_used' => 0];
    }

    $stmt = $pdo->query("
        SELECT 
            COUNT(*) AS `total`,
            SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) AS `active`,
            SUM(CASE WHEN `status` = 'inactive' THEN 1 ELSE 0 END) AS `inactive`,
            COALESCE(SUM(`used_count`), 0) AS `total_used`
        FROM `vouchers`
    ");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'total'      => (int) ($res['total'] ?? 0),
        'active'     => (int) ($res['active'] ?? 0),
        'inactive'   => (int) ($res['inactive'] ?? 0),
        'total_used' => (int) ($res['total_used'] ?? 0),
    ];
}

/**
 * Lấy chi tiết voucher theo ID.
 */
function getVoucherById(int $id): ?array {
    $pdo = getVouchersDb();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT * FROM `vouchers` WHERE `id` = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}

/**
 * Lấy chi tiết voucher theo mã code.
 */
function getVoucherByCode(string $code): ?array {
    $pdo = getVouchersDb();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT * FROM `vouchers` WHERE UPPER(`code`) = UPPER(?)");
    $stmt->execute([trim($code)]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}

/**
 * Tạo mới voucher.
 */
function createVoucher(array $data): array {
    $pdo = getVouchersDb();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối CSDL'];

    $code = strtoupper(trim($data['code'] ?? ''));
    $name = trim($data['name'] ?? '');
    $discountType = $data['discount_type'] ?? 'percent';
    $discountValue = (int) ($data['discount_value'] ?? 0);
    $minOrderValue = max(0, (int) ($data['min_order_value'] ?? 0));
    $maxDiscountAmount = max(0, (int) ($data['max_discount_amount'] ?? 0));
    $usageLimit = max(0, (int) ($data['usage_limit'] ?? 0));
    $startDate = !empty($data['start_date']) ? $data['start_date'] : null;
    $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
    $status = in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : 'active';

    if ($code === '' || $name === '') {
        return ['success' => false, 'message' => 'Mã voucher và tên chương trình không được để trống!'];
    }

    if (!preg_match('/^[A-Z0-9_\-]{3,30}$/', $code)) {
        return ['success' => false, 'message' => 'Mã voucher chỉ chứa ký tự chữ không dấu, số, dấu gạch (3-30 ký tự)!'];
    }

    if ($discountValue <= 0) {
        return ['success' => false, 'message' => 'Giá trị giảm giá phải lớn hơn 0!'];
    }

    if ($discountType === 'percent' && $discountValue > 100) {
        return ['success' => false, 'message' => 'Giảm giá theo phần trăm tối đa là 100%!'];
    }

    // Kiểm tra trùng mã code
    $exist = getVoucherByCode($code);
    if ($exist) {
        return ['success' => false, 'message' => 'Mã voucher "' . $code . '" đã tồn tại! Vui lòng chọn mã khác.'];
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `vouchers` (`code`, `name`, `discount_type`, `discount_value`, `min_order_value`, `max_discount_amount`, `usage_limit`, `used_count`, `start_date`, `end_date`, `status`)
            VALUES (:code, :name, :discount_type, :discount_value, :min_order_value, :max_discount_amount, :usage_limit, 0, :start_date, :end_date, :status)
        ");
        $stmt->execute([
            ':code'                => $code,
            ':name'                => $name,
            ':discount_type'       => $discountType,
            ':discount_value'      => $discountValue,
            ':min_order_value'     => $minOrderValue,
            ':max_discount_amount' => $maxDiscountAmount,
            ':usage_limit'         => $usageLimit,
            ':start_date'          => $startDate,
            ':end_date'            => $endDate,
            ':status'              => $status,
        ]);

        return ['success' => true, 'id' => (int) $pdo->lastInsertId(), 'message' => 'Thêm mã voucher thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi tạo voucher: ' . $e->getMessage()];
    }
}

/**
 * Cập nhật voucher.
 */
function updateVoucher(int $id, array $data): array {
    $pdo = getVouchersDb();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối CSDL'];

    $voucher = getVoucherById($id);
    if (!$voucher) {
        return ['success' => false, 'message' => 'Không tìm thấy voucher cần cập nhật!'];
    }

    $code = strtoupper(trim($data['code'] ?? $voucher['code']));
    $name = trim($data['name'] ?? $voucher['name']);
    $discountType = $data['discount_type'] ?? $voucher['discount_type'];
    $discountValue = isset($data['discount_value']) ? (int) $data['discount_value'] : (int) $voucher['discount_value'];
    $minOrderValue = isset($data['min_order_value']) ? max(0, (int) $data['min_order_value']) : (int) $voucher['min_order_value'];
    $maxDiscountAmount = isset($data['max_discount_amount']) ? max(0, (int) $data['max_discount_amount']) : (int) $voucher['max_discount_amount'];
    $usageLimit = isset($data['usage_limit']) ? max(0, (int) $data['usage_limit']) : (int) $voucher['usage_limit'];
    $startDate = array_key_exists('start_date', $data) ? ($data['start_date'] ?: null) : $voucher['start_date'];
    $endDate = array_key_exists('end_date', $data) ? ($data['end_date'] ?: null) : $voucher['end_date'];
    $status = in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : $voucher['status'];

    if ($code === '' || $name === '') {
        return ['success' => false, 'message' => 'Mã voucher và tên chương trình không được để trống!'];
    }

    if ($discountValue <= 0) {
        return ['success' => false, 'message' => 'Giá trị giảm giá phải lớn hơn 0!'];
    }

    if ($discountType === 'percent' && $discountValue > 100) {
        return ['success' => false, 'message' => 'Giảm giá theo phần trăm tối đa là 100%!'];
    }

    // Kiểm tra trùng mã code với ID khác
    $exist = getVoucherByCode($code);
    if ($exist && (int) $exist['id'] !== $id) {
        return ['success' => false, 'message' => 'Mã voucher "' . $code . '" đã được sử dụng bởi chương trình khác!'];
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE `vouchers` SET
                `code` = :code,
                `name` = :name,
                `discount_type` = :discount_type,
                `discount_value` = :discount_value,
                `min_order_value` = :min_order_value,
                `max_discount_amount` = :max_discount_amount,
                `usage_limit` = :usage_limit,
                `start_date` = :start_date,
                `end_date` = :end_date,
                `status` = :status
            WHERE `id` = :id
        ");
        $stmt->execute([
            ':code'                => $code,
            ':name'                => $name,
            ':discount_type'       => $discountType,
            ':discount_value'      => $discountValue,
            ':min_order_value'     => $minOrderValue,
            ':max_discount_amount' => $maxDiscountAmount,
            ':usage_limit'         => $usageLimit,
            ':start_date'          => $startDate,
            ':end_date'            => $endDate,
            ':status'              => $status,
            ':id'                  => $id,
        ]);

        return ['success' => true, 'message' => 'Cập nhật mã voucher thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi cập nhật voucher: ' . $e->getMessage()];
    }
}

/**
 * Xóa voucher.
 */
function deleteVoucher(int $id): array {
    $pdo = getVouchersDb();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối CSDL'];

    try {
        $stmt = $pdo->prepare("DELETE FROM `vouchers` WHERE `id` = ?");
        $stmt->execute([$id]);
        return ['success' => true, 'message' => 'Đã xóa mã voucher thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi xóa voucher: ' . $e->getMessage()];
    }
}

/**
 * Kiểm tra tính hợp lệ của voucher và tính số tiền giảm giá chính xác.
 */
function validateVoucher(string $code, int $subtotal): array {
    $code = strtoupper(trim($code));
    if ($code === '') {
        return ['valid' => false, 'message' => 'Vui lòng nhập mã giảm giá!'];
    }

    $voucher = getVoucherByCode($code);
    if (!$voucher) {
        return ['valid' => false, 'message' => 'Mã giảm giá "' . $code . '" không tồn tại!'];
    }

    if ($voucher['status'] !== 'active') {
        return ['valid' => false, 'message' => 'Mã giảm giá "' . $code . '" hiện đang tạm ngưng sử dụng!'];
    }

    $today = date('Y-m-d');
    if (!empty($voucher['start_date']) && $today < $voucher['start_date']) {
        return ['valid' => false, 'message' => 'Mã giảm giá chưa đến ngày bắt đầu áp dụng (' . date('d/m/Y', strtotime($voucher['start_date'])) . ')!'];
    }

    if (!empty($voucher['end_date']) && $today > $voucher['end_date']) {
        return ['valid' => false, 'message' => 'Mã giảm giá đã hết hạn sử dụng từ ngày ' . date('d/m/Y', strtotime($voucher['end_date'])) . '!'];
    }

    if ($voucher['usage_limit'] > 0 && $voucher['used_count'] >= $voucher['usage_limit']) {
        return ['valid' => false, 'message' => 'Mã giảm giá đã đạt số lượt sử dụng tối đa!'];
    }

    if ($voucher['min_order_value'] > 0 && $subtotal < $voucher['min_order_value']) {
        return [
            'valid' => false,
            'message' => 'Đơn hàng tối thiểu phải từ ' . number_format($voucher['min_order_value']) . 'đ để áp dụng mã này (Tạm tính hiện tại: ' . number_format($subtotal) . 'đ)!'
        ];
    }

    // Tính tiền giảm
    $discountAmount = 0;
    if ($voucher['discount_type'] === 'percent') {
        $discountAmount = (int) round($subtotal * ($voucher['discount_value'] / 100));
        if ($voucher['max_discount_amount'] > 0 && $discountAmount > $voucher['max_discount_amount']) {
            $discountAmount = (int) $voucher['max_discount_amount'];
        }
    } else { // amount
        $discountAmount = min($subtotal, (int) $voucher['discount_value']);
    }

    return [
        'valid'           => true,
        'message'         => 'Áp dụng mã giảm giá thành công!',
        'voucher'         => $voucher,
        'code'            => $voucher['code'],
        'name'            => $voucher['name'],
        'discount_type'   => $voucher['discount_type'],
        'discount_value'  => (int) $voucher['discount_value'],
        'discount_amount' => $discountAmount,
        'final_subtotal'  => max(0, $subtotal - $discountAmount),
    ];
}

/**
 * Ghi nhận thêm 1 lượt sử dụng voucher khi hoàn tất hóa đơn.
 */
function recordVoucherUsage(PDO $pdo, string $code): bool {
    $code = strtoupper(trim($code));
    if ($code === '') return false;

    $stmt = $pdo->prepare("UPDATE `vouchers` SET `used_count` = `used_count` + 1 WHERE UPPER(`code`) = UPPER(?)");
    $stmt->execute([$code]);
    return $stmt->rowCount() > 0;
}
