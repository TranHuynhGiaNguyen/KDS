<?php
/**
 * SmartDine - Quản lý Bàn ăn, Khu vực & Trạng thái bàn
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/orders.php';

/**
 * Lấy trạng thái bàn có khách từ các đơn hàng đang mở trong MySQL
 */
function fetchOccupiedTables(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT o.`table_code`, i.`item_name` AS `name`, i.`unit_price` AS `price`, i.`qty`, i.`status`
        FROM `order_items` i
        JOIN `orders` o ON o.`id` = i.`order_id`
        WHERE o.`status` = 'open'
        ORDER BY o.`table_code`, i.`id`
    ");

    $itemsByTable = [];
    foreach ($stmt->fetchAll() as $row) {
        $itemsByTable[$row['table_code']][] = $row;
    }

    $occupied = [];
    foreach ($itemsByTable as $tableCode => $items) {
        try {
            $bill = calculateBill($items);
        } catch (InvalidArgumentException $e) {
            error_log('tables: ' . $tableCode . ' ' . $e->getMessage());
            continue;
        }

        if ($bill['lines'] !== []) {
            $occupied[$tableCode] = [
                'item_count' => count($bill['lines']),
                'total'      => $bill['total']
            ];
        }
    }

    return $occupied;
}

// ==========================================================
// CÁC HÀM QUẢN LÝ KHU VỰC (RESTAURANT_AREAS)
// ==========================================================

/**
 * Lấy danh sách tất cả khu vực kèm số lượng bàn trực thuộc
 */
function getAllAreas(?string $status = null): array {
    $pdo = getDbConnection();
    if (!$pdo) return [];

    $sql = "
        SELECT a.*, 
               COUNT(t.id) AS total_tables,
               SUM(CASE WHEN t.status = 'active' THEN 1 ELSE 0 END) AS active_tables,
               SUM(CASE WHEN t.status != 'hidden' THEN t.capacity ELSE 0 END) AS total_capacity
        FROM `restaurant_areas` a
        LEFT JOIN `dining_tables` t ON t.area_id = a.id
    ";
    $params = [];

    if ($status !== null && $status !== '' && $status !== 'all') {
        $sql .= " WHERE a.status = :status";
        $params[':status'] = $status;
    }

    $sql .= " GROUP BY a.id ORDER BY a.sort_order ASC, a.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Lấy thông tin 1 khu vực theo ID
 */
function getAreaById(int $id): ?array {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT * FROM `restaurant_areas` WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}

/**
 * Lấy thông tin 1 khu vực theo mã code
 */
function getAreaByCode(string $code): ?array {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT * FROM `restaurant_areas` WHERE code = :code LIMIT 1");
    $stmt->execute([':code' => trim($code)]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}

/**
 * Thêm khu vực mới
 */
function createArea(array $data): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu!'];

    $code        = strtolower(trim($data['code'] ?? ''));
    $name        = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $sortOrder   = (int) ($data['sort_order'] ?? 0);
    $status      = in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : 'active';

    if (empty($code) || empty($name)) {
        return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ mã khu vực và tên khu vực!'];
    }

    if (!preg_match('/^[a-z0-9_-]+$/i', $code)) {
        return ['success' => false, 'message' => 'Mã khu vực chỉ được chứa chữ cái, chữ số, dấu gạch nối và gạch dưới (VD: indoor, garden, vip, tang-2)!'];
    }

    if (getAreaByCode($code)) {
        return ['success' => false, 'message' => 'Mã khu vực "' . htmlspecialchars($code) . '" đã tồn tại! Vui lòng chọn mã khác.'];
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `restaurant_areas` (`code`, `name`, `description`, `sort_order`, `status`)
            VALUES (:code, :name, :description, :sort_order, :status)
        ");
        $stmt->execute([
            ':code'        => $code,
            ':name'        => $name,
            ':description' => $description,
            ':sort_order'  => $sortOrder,
            ':status'      => $status,
        ]);

        return [
            'success' => true,
            'message' => 'Thêm khu vực "' . htmlspecialchars($name) . '" thành công!',
            'id'      => (int) $pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        error_log('createArea error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống khi tạo khu vực: ' . $e->getMessage()];
    }
}

/**
 * Cập nhật thông tin khu vực
 */
function updateArea(int $id, array $data): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu!'];

    $area = getAreaById($id);
    if (!$area) {
        return ['success' => false, 'message' => 'Khu vực không tồn tại!'];
    }

    $code        = strtolower(trim($data['code'] ?? ''));
    $name        = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $sortOrder   = (int) ($data['sort_order'] ?? 0);
    $status      = in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : 'active';

    if (empty($code) || empty($name)) {
        return ['success' => false, 'message' => 'Vui lòng nhập đầy đủ mã và tên khu vực!'];
    }

    if (!preg_match('/^[a-z0-9_-]+$/i', $code)) {
        return ['success' => false, 'message' => 'Mã khu vực chỉ chứa chữ cái, số, gạch ngang/dưới!'];
    }

    // Kiểm tra trùng mã code với khu vực khác
    $existing = getAreaByCode($code);
    if ($existing && (int)$existing['id'] !== $id) {
        return ['success' => false, 'message' => 'Mã khu vực "' . htmlspecialchars($code) . '" đã thuộc về khu vực khác!'];
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE `restaurant_areas`
            SET `code` = :code, `name` = :name, `description` = :description,
                `sort_order` = :sort_order, `status` = :status
            WHERE `id` = :id
        ");
        $stmt->execute([
            ':code'        => $code,
            ':name'        => $name,
            ':description' => $description,
            ':sort_order'  => $sortOrder,
            ':status'      => $status,
            ':id'          => $id,
        ]);

        return ['success' => true, 'message' => 'Cập nhật khu vực "' . htmlspecialchars($name) . '" thành công!'];
    } catch (PDOException $e) {
        error_log('updateArea error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi khi cập nhật khu vực: ' . $e->getMessage()];
    }
}

/**
 * Xóa khu vực (Kiểm tra an toàn: không cho xóa nếu đang có bàn thuộc khu vực này)
 */
function deleteArea(int $id): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu!'];

    $area = getAreaById($id);
    if (!$area) {
        return ['success' => false, 'message' => 'Khu vực không tồn tại!'];
    }

    // Kiểm tra số lượng bàn ăn trực thuộc
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `dining_tables` WHERE `area_id` = :area_id");
    $stmtCheck->execute([':area_id' => $id]);
    $tableCount = (int) $stmtCheck->fetchColumn();

    if ($tableCount > 0) {
        return [
            'success' => false,
            'message' => 'Không thể xóa khu vực "' . htmlspecialchars($area['name']) . '" vì đang có ' . $tableCount . ' bàn ăn trực thuộc. Hãy chuyển bàn sang khu vực khác hoặc xóa bàn trước!'
        ];
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM `restaurant_areas` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
        return ['success' => true, 'message' => 'Đã xóa khu vực "' . htmlspecialchars($area['name']) . '" thành công!'];
    } catch (PDOException $e) {
        error_log('deleteArea error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi khi xóa khu vực: ' . $e->getMessage()];
    }
}

// ==========================================================
// CÁC HÀM QUẢN LÝ BÀN ĂN (DINING_TABLES)
// ==========================================================

/**
 * Lấy danh sách bàn ăn (kèm thông tin khu vực và trạng thái có khách)
 */
function getAllDiningTables(string $filterArea = '', string $filterStatus = '', string $keyword = ''): array {
    $pdo = getDbConnection();
    if (!$pdo) return [];

    $sql = "
        SELECT t.*, 
               a.`name` AS `area_name`, 
               a.`code` AS `area_code`,
               a.`status` AS `area_status`
        FROM `dining_tables` t
        JOIN `restaurant_areas` a ON a.`id` = t.`area_id`
        WHERE 1=1
    ";
    $params = [];

    // Lọc theo khu vực (theo code hoặc id)
    if (!empty($filterArea) && $filterArea !== 'all') {
        if (is_numeric($filterArea)) {
            $sql .= " AND t.`area_id` = :area_id";
            $params[':area_id'] = (int) $filterArea;
        } else {
            $sql .= " AND a.`code` = :area_code";
            $params[':area_code'] = $filterArea;
        }
    }

    // Lọc theo trạng thái
    if (!empty($filterStatus) && $filterStatus !== 'all') {
        if ($filterStatus === 'not_hidden') {
            $sql .= " AND t.`status` != 'hidden'";
        } else {
            $sql .= " AND t.`status` = :status";
            $params[':status'] = $filterStatus;
        }
    }

    // Tìm kiếm theo mã bàn hoặc tên bàn
    if (!empty($keyword)) {
        $sql .= " AND (t.`code` LIKE :kw1 OR t.`name` LIKE :kw2)";
        $kwTerm = '%' . trim($keyword) . '%';
        $params[':kw1'] = $kwTerm;
        $params[':kw2'] = $kwTerm;
    }

    $sql .= " ORDER BY a.`sort_order` ASC, t.`sort_order` ASC, t.`code` ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Đồng bộ thông tin đơn hàng hiện tại (có khách hay trống) & gán bàn
    $occupiedTables = [];
    $assignments    = [];
    try {
        $occupiedTables = fetchOccupiedTables($pdo);
        $assignments    = fetchActiveTableAssignments($pdo);
    } catch (Exception $e) {
        error_log('sync occupied tables error: ' . $e->getMessage());
    }

    foreach ($tables as &$t) {
        $occ    = $occupiedTables[$t['code']] ?? null;
        $assign = $assignments[$t['code']] ?? null;
        $t['is_occupied']    = ($occ !== null || $assign !== null);
        $t['occupied_total'] = $occ['total'] ?? 0;
        $t['occupied_items'] = $occ['item_count'] ?? 0;
        $t['guest_count']    = (int) ($assign['guest_count'] ?? 0);
        $t['staff_id']       = (int) ($assign['staff_id'] ?? 0);
        $t['staff_name']     = (string) ($assign['staff_name'] ?? '');
        $t['customer_name']  = (string) ($assign['customer_name'] ?? '');
        $t['assign_note']    = (string) ($assign['note'] ?? '');
        $t['opened_at']      = (string) ($assign['opened_at'] ?? '');
    }
    unset($t);

    return $tables;
}

/**
 * Lấy chi tiết 1 bàn ăn theo ID
 */
function getDiningTableById(int $id): ?array {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("
        SELECT t.*, a.`name` AS `area_name`, a.`code` AS `area_code`
        FROM `dining_tables` t
        JOIN `restaurant_areas` a ON a.`id` = t.`area_id`
        WHERE t.`id` = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}

/**
 * Lấy chi tiết 1 bàn ăn theo mã bàn
 */
function getDiningTableByCode(string $code): ?array {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("
        SELECT t.*, a.`name` AS `area_name`, a.`code` AS `area_code`
        FROM `dining_tables` t
        JOIN `restaurant_areas` a ON a.`id` = t.`area_id`
        WHERE t.`code` = :code
        LIMIT 1
    ");
    $stmt->execute([':code' => strtoupper(trim($code))]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}

/**
 * Thêm bàn ăn mới
 */
function createDiningTable(array $data): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu!'];

    $code      = strtoupper(trim($data['code'] ?? ''));
    $name      = trim($data['name'] ?? '');
    $areaId    = (int) ($data['area_id'] ?? 0);
    $capacity  = (int) ($data['capacity'] ?? 4);
    $sortOrder = (int) ($data['sort_order'] ?? 0);
    $status    = in_array($data['status'] ?? '', ['active', 'maintenance', 'hidden'], true) ? $data['status'] : 'active';
    $posX      = isset($data['pos_x']) ? (float)$data['pos_x'] : 20.0;
    $posY      = isset($data['pos_y']) ? (float)$data['pos_y'] : 25.0;
    $shape     = in_array($data['shape'] ?? '', ['square', 'round', 'rectangle'], true) ? $data['shape'] : 'square';
    $width     = (int) ($data['width'] ?? ($shape === 'rectangle' ? 120 : 85));
    $height    = (int) ($data['height'] ?? 85);

    if (empty($code)) {
        return ['success' => false, 'message' => 'Vui lòng nhập mã bàn (VD: B13, V01, S05)!'];
    }

    if (empty($name)) {
        $name = 'Bàn ' . $code;
    }

    if ($areaId <= 0 || !getAreaById($areaId)) {
        return ['success' => false, 'message' => 'Vui lòng chọn khu vực hợp lệ cho bàn ăn!'];
    }

    if ($capacity <= 0 || $capacity > 100) {
        return ['success' => false, 'message' => 'Sức chứa của bàn phải từ 1 đến 100 khách!'];
    }

    if (getDiningTableByCode($code)) {
        return ['success' => false, 'message' => 'Mã bàn "' . htmlspecialchars($code) . '" đã tồn tại! Vui lòng chọn mã bàn khác.'];
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `dining_tables` (`code`, `name`, `area_id`, `capacity`, `pos_x`, `pos_y`, `shape`, `width`, `height`, `status`, `sort_order`)
            VALUES (:code, :name, :area_id, :capacity, :pos_x, :pos_y, :shape, :width, :height, :status, :sort_order)
        ");
        $stmt->execute([
            ':code'       => $code,
            ':name'       => $name,
            ':area_id'    => $areaId,
            ':capacity'   => $capacity,
            ':pos_x'      => $posX,
            ':pos_y'      => $posY,
            ':shape'      => $shape,
            ':width'      => $width,
            ':height'     => $height,
            ':status'     => $status,
            ':sort_order' => $sortOrder,
        ]);

        return [
            'success' => true,
            'message' => 'Thêm bàn "' . htmlspecialchars($code) . '" thành công!',
            'id'      => (int) $pdo->lastInsertId()
        ];
    } catch (PDOException $e) {
        error_log('createDiningTable error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống khi thêm bàn: ' . $e->getMessage()];
    }
}

/**
 * Cập nhật thông tin bàn ăn
 */
function updateDiningTable(int $id, array $data): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu!'];

    $table = getDiningTableById($id);
    if (!$table) {
        return ['success' => false, 'message' => 'Bàn ăn không tồn tại!'];
    }

    $code      = strtoupper(trim($data['code'] ?? ''));
    $name      = trim($data['name'] ?? '');
    $areaId    = (int) ($data['area_id'] ?? 0);
    $capacity  = (int) ($data['capacity'] ?? 4);
    $sortOrder = (int) ($data['sort_order'] ?? 0);
    $status    = in_array($data['status'] ?? '', ['active', 'maintenance', 'hidden'], true) ? $data['status'] : 'active';
    $shape     = in_array($data['shape'] ?? '', ['square', 'round', 'rectangle'], true) ? $data['shape'] : ($table['shape'] ?? 'square');
    $width     = (int) ($data['width'] ?? ($table['width'] ?? 85));
    $height    = (int) ($data['height'] ?? ($table['height'] ?? 85));

    if (empty($code)) {
        return ['success' => false, 'message' => 'Mã bàn không được để trống!'];
    }

    if (empty($name)) {
        $name = 'Bàn ' . $code;
    }

    if ($areaId <= 0 || !getAreaById($areaId)) {
        return ['success' => false, 'message' => 'Vui lòng chọn khu vực hợp lệ!'];
    }

    if ($capacity <= 0 || $capacity > 100) {
        return ['success' => false, 'message' => 'Sức chứa phải từ 1 đến 100 khách!'];
    }

    // Kiểm tra trùng mã code với bàn khác
    $existing = getDiningTableByCode($code);
    if ($existing && (int)$existing['id'] !== $id) {
        return ['success' => false, 'message' => 'Mã bàn "' . htmlspecialchars($code) . '" đã thuộc về một bàn khác!'];
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE `dining_tables`
            SET `code` = :code, `name` = :name, `area_id` = :area_id,
                `capacity` = :capacity, `shape` = :shape, `width` = :width, `height` = :height,
                `status` = :status, `sort_order` = :sort_order
            WHERE `id` = :id
        ");
        $stmt->execute([
            ':code'       => $code,
            ':name'       => $name,
            ':area_id'    => $areaId,
            ':capacity'   => $capacity,
            ':shape'      => $shape,
            ':width'      => $width,
            ':height'     => $height,
            ':status'     => $status,
            ':sort_order' => $sortOrder,
            ':id'         => $id,
        ]);

        return ['success' => true, 'message' => 'Cập nhật bàn "' . htmlspecialchars($code) . '" thành công!'];
    } catch (PDOException $e) {
        error_log('updateDiningTable error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi khi cập nhật bàn: ' . $e->getMessage()];
    }
}

/**
 * Lưu hàng loạt vị trí và hình dạng bàn ăn khi kéo thả trên sơ đồ trực quan
 */
/**
 * Lưu vị trí tọa độ, kích thước và hình dạng bàn ăn kéo thả (hỗ trợ lưu vị trí cửa ra vào khu vực)
 */
function saveTableLayout(array $tablesLayout, array $areaLayout = []): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu!'];

    try {
        $pdo->beginTransaction();

        // 1. Cập nhật vị trí cửa ra vào & quầy thu ngân của khu vực (nếu có)
        if (!empty($areaLayout['area_code'])) {
            $areaCode    = trim($areaLayout['area_code']);
            $entrancePos = trim($areaLayout['entrance_pos'] ?? 'bottom_center');
            $entranceX   = isset($areaLayout['entrance_x']) ? (float)$areaLayout['entrance_x'] : 50.0;
            $entranceY   = isset($areaLayout['entrance_y']) ? (float)$areaLayout['entrance_y'] : 96.0;
            $cashierX    = isset($areaLayout['cashier_x']) ? (float)$areaLayout['cashier_x'] : 14.0;
            $cashierY    = isset($areaLayout['cashier_y']) ? (float)$areaLayout['cashier_y'] : 6.0;

            $stmtArea = $pdo->prepare("
                UPDATE `restaurant_areas`
                SET `entrance_pos` = :pos, `entrance_x` = :ex, `entrance_y` = :ey, `cashier_x` = :cx, `cashier_y` = :cy
                WHERE `code` = :code
            ");
            $stmtArea->execute([
                ':pos'  => $entrancePos,
                ':ex'   => $entranceX,
                ':ey'   => $entranceY,
                ':cx'   => $cashierX,
                ':cy'   => $cashierY,
                ':code' => $areaCode
            ]);
        }

        // 2. Cập nhật tọa độ và kích thước các bàn ăn
        $stmt = $pdo->prepare("
            UPDATE `dining_tables` 
            SET `pos_x` = :x, `pos_y` = :y, `shape` = :shape, `width` = :w, `height` = :h
            WHERE `id` = :id
        ");

        foreach ($tablesLayout as $item) {
            $id    = (int) ($item['id'] ?? 0);
            $posX  = (float) ($item['pos_x'] ?? 15);
            $posY  = (float) ($item['pos_y'] ?? 20);
            $shape = in_array($item['shape'] ?? '', ['square', 'round', 'rectangle'], true) ? $item['shape'] : 'square';
            $w     = (int) ($item['width'] ?? 85);
            $h     = (int) ($item['height'] ?? 85);

            if ($id > 0) {
                $stmt->execute([
                    ':x'     => $posX,
                    ':y'     => $posY,
                    ':shape' => $shape,
                    ':w'     => $w,
                    ':h'     => $h,
                    ':id'    => $id,
                ]);
            }
        }
        $pdo->commit();
        return ['success' => true, 'message' => 'Đã lưu vị trí sơ đồ bàn và cửa ra vào thành công!'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('saveTableLayout error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi khi lưu sơ đồ: ' . $e->getMessage()];
    }
}

/**
 * Xóa bàn ăn (Kiểm tra an toàn: nếu bàn đang có đơn hàng mở thì chặn)
 */
function deleteDiningTable(int $id): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu!'];

    $table = getDiningTableById($id);
    if (!$table) {
        return ['success' => false, 'message' => 'Bàn ăn không tồn tại!'];
    }

    // Kiểm tra xem bàn có đơn hàng đang mở không
    try {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `orders` WHERE `table_code` = :code AND `status` = 'open'");
        $stmtCheck->execute([':code' => $table['code']]);
        $openOrders = (int) $stmtCheck->fetchColumn();

        if ($openOrders > 0) {
            return [
                'success' => false,
                'message' => 'Không thể xóa bàn ' . htmlspecialchars($table['code']) . ' vì bàn đang có đơn gọi món chưa thanh toán. Vui lòng hoàn tất hoặc hủy đơn trước!'
            ];
        }
    } catch (PDOException $e) {
        // Nếu chưa có bảng orders thì bỏ qua kiểm tra này
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM `dining_tables` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
        return ['success' => true, 'message' => 'Đã xóa bàn "' . htmlspecialchars($table['code']) . '" thành công!'];
    } catch (PDOException $e) {
        error_log('deleteDiningTable error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi khi xóa bàn: ' . $e->getMessage()];
    }
}

/**
 * Chuyển nhanh trạng thái bàn ăn (active, maintenance, hidden)
 */
function updateDiningTableStatus(int $id, string $status): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Lỗi kết nối cơ sở dữ liệu!'];

    if (!in_array($status, ['active', 'maintenance', 'hidden'], true)) {
        return ['success' => false, 'message' => 'Trạng thái không hợp lệ!'];
    }

    $table = getDiningTableById($id);
    if (!$table) {
        return ['success' => false, 'message' => 'Bàn ăn không tồn tại!'];
    }

    try {
        $stmt = $pdo->prepare("UPDATE `dining_tables` SET `status` = :status WHERE `id` = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);

        $statusLabels = [
            'active'      => 'Đang sẵn sàng phục vụ',
            'maintenance' => 'Đang tạm bảo trì',
            'hidden'      => 'Đã tạm ẩn'
        ];
        return [
            'success' => true,
            'message' => 'Đã đổi trạng thái bàn ' . htmlspecialchars($table['code']) . ' sang "' . $statusLabels[$status] . '"!'
        ];
    } catch (PDOException $e) {
        error_log('updateDiningTableStatus error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái: ' . $e->getMessage()];
    }
}

/**
 * Thống kê tổng hợp số liệu bàn và khu vực cho Dashboard
 */
function getTableStats(): array {
    $pdo = getDbConnection();
    if (!$pdo) {
        return [
            'total_tables'        => 0,
            'active_tables'       => 0,
            'occupied_tables'     => 0,
            'empty_tables'        => 0,
            'maintenance_tables'  => 0,
            'hidden_tables'       => 0,
            'total_capacity'      => 0,
            'total_areas'         => 0,
            'occupancy_rate'      => 0
        ];
    }

    $allTables = getAllDiningTables('', '', '');
    $allAreas  = getAllAreas();

    $total        = count($allTables);
    $active       = 0;
    $maintenance  = 0;
    $hidden       = 0;
    $occupied     = 0;
    $empty        = 0;
    $capacity     = 0;

    foreach ($allTables as $t) {
        if ($t['status'] === 'active') {
            $active++;
            $capacity += (int) $t['capacity'];
            if ($t['is_occupied']) {
                $occupied++;
            } else {
                $empty++;
            }
        } elseif ($t['status'] === 'maintenance') {
            $maintenance++;
        } elseif ($t['status'] === 'hidden') {
            $hidden++;
        }
    }

    $occupancyRate = ($active > 0) ? round(($occupied / $active) * 100, 1) : 0;

    return [
        'total_tables'        => $total,
        'active_tables'       => $active,
        'occupied_tables'     => $occupied,
        'empty_tables'        => $empty,
        'maintenance_tables'  => $maintenance,
        'hidden_tables'       => $hidden,
        'total_capacity'      => $capacity,
        'total_areas'         => count($allAreas),
        'occupancy_rate'      => $occupancyRate
    ];
}

// ==========================================================
// NGHIỆP VỤ GÁN BÀN & CHUYỂN BÀN (ASSIGN & TRANSFER TABLE)
// ==========================================================

/**
 * Đảm bảo bảng table_assignments tồn tại trong MySQL.
 */
function ensureTableAssignmentsSchema(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `table_assignments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `table_code` VARCHAR(20) NOT NULL,
            `guest_count` INT NOT NULL DEFAULT 2,
            `customer_name` VARCHAR(100) NOT NULL DEFAULT '',
            `customer_phone` VARCHAR(25) NOT NULL DEFAULT '',
            `staff_id` INT DEFAULT NULL,
            `staff_name` VARCHAR(100) NOT NULL DEFAULT '',
            `note` VARCHAR(500) NOT NULL DEFAULT '',
            `status` ENUM('active', 'closed') NOT NULL DEFAULT 'active',
            `opened_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `closed_at` DATETIME DEFAULT NULL,
            INDEX idx_assign_table_status (`table_code`, `status`),
            INDEX idx_assign_staff (`staff_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $initialized = true;
}

/**
 * Lấy tất cả lượt gán bàn đang hoạt động, keyed theo table_code.
 */
function fetchActiveTableAssignments(PDO $pdo): array {
    ensureTableAssignmentsSchema($pdo);

    $stmt = $pdo->query("
        SELECT * FROM `table_assignments`
        WHERE `status` = 'active'
        ORDER BY `id` DESC
    ");
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = strtoupper(trim((string) $row['table_code']));
        if (!isset($map[$code])) {
            $map[$code] = $row;
        }
    }
    return $map;
}

/**
 * Lấy thông tin gán bàn đang hoạt động của 1 bàn cụ thể.
 */
function getActiveTableAssignment(string $tableCode, ?PDO $pdo = null): ?array {
    $pdo = $pdo ?? getDbConnection();
    if (!$pdo) {
        return null;
    }
    ensureTableAssignmentsSchema($pdo);

    $code = strtoupper(trim($tableCode));
    $stmt = $pdo->prepare("
        SELECT * FROM `table_assignments`
        WHERE `table_code` = :code AND `status` = 'active'
        ORDER BY `id` DESC
        LIMIT 1
    ");
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Danh sách nhân viên có thể phân công phụ trách bàn.
 */
function getAssignableStaffList(): array {
    $pdo = getDbConnection();
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->query("
        SELECT `id`, `fullname`, `username`, `role`
        FROM `users`
        WHERE `status` = 'active' AND `role` IN ('staff', 'shift_manager', 'admin')
        ORDER BY FIELD(`role`, 'staff', 'shift_manager', 'admin'), `fullname` ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Đóng lượt gán bàn khi thanh toán xong (dùng bên trong transaction checkout).
 */
function closeTableAssignmentInTx(PDO $pdo, string $tableCode): void {
    ensureTableAssignmentsSchema($pdo);
    $stmt = $pdo->prepare("
        UPDATE `table_assignments`
        SET `status` = 'closed', `closed_at` = NOW()
        WHERE `table_code` = :code AND `status` = 'active'
    ");
    $stmt->execute([':code' => strtoupper(trim($tableCode))]);
}

/**
 * Gán khách / nhân viên vào bàn hoặc cập nhật thông tin gán bàn đang phục vụ.
 */
function assignTable(PDO $pdo, array $data): array {
    ensureTableAssignmentsSchema($pdo);

    $tableCode     = strtoupper(trim((string) ($data['table_code'] ?? '')));
    $guestCount    = (int) ($data['guest_count'] ?? 2);
    $customerName  = trim((string) ($data['customer_name'] ?? ''));
    $customerPhone = trim((string) ($data['customer_phone'] ?? ''));
    $staffId       = (int) ($data['staff_id'] ?? 0);
    $note          = trim((string) ($data['note'] ?? ''));
    $allowUpdate   = !empty($data['allow_update']);

    if ($tableCode === '') {
        return ['success' => false, 'message' => 'Vui lòng chọn bàn cần gán!'];
    }

    $table = getDiningTableByCode($tableCode);
    if (!$table) {
        return ['success' => false, 'message' => 'Không tìm thấy bàn ' . htmlspecialchars($tableCode) . '!'];
    }
    if (($table['status'] ?? '') !== 'active') {
        return ['success' => false, 'message' => 'Bàn ' . htmlspecialchars($tableCode) . ' đang bảo trì hoặc tạm ẩn, không thể gán khách!'];
    }
    if ($guestCount < 1 || $guestCount > 100) {
        return ['success' => false, 'message' => 'Số lượng khách phải từ 1 đến 100 người!'];
    }

    $staffName = '';
    if ($staffId > 0) {
        $stmtStaff = $pdo->prepare("SELECT `id`, `fullname`, `username` FROM `users` WHERE `id` = :id AND `status` = 'active' LIMIT 1");
        $stmtStaff->execute([':id' => $staffId]);
        $staffRow = $stmtStaff->fetch(PDO::FETCH_ASSOC);
        if (!$staffRow) {
            return ['success' => false, 'message' => 'Nhân viên được chọn không hợp lệ hoặc đã ngừng hoạt động!'];
        }
        $staffName = trim((string) ($staffRow['fullname'] ?: $staffRow['username']));
    } else {
        $staffId = null;
    }

    $ownTx = !$pdo->inTransaction();
    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        $existing = getActiveTableAssignment($tableCode, $pdo);

        if ($existing) {
            if (!$allowUpdate) {
                if ($ownTx && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return ['success' => false, 'message' => 'Bàn ' . $tableCode . ' đang phục vụ khách, không thể gán mới!'];
            }

            $stmtUpd = $pdo->prepare("
                UPDATE `table_assignments`
                SET `guest_count` = :guests,
                    `customer_name` = :cname,
                    `customer_phone` = :cphone,
                    `staff_id` = :sid,
                    `staff_name` = :sname,
                    `note` = :note
                WHERE `id` = :id
            ");
            $stmtUpd->execute([
                ':guests' => $guestCount,
                ':cname'  => mb_substr($customerName, 0, 100, 'UTF-8'),
                ':cphone' => mb_substr($customerPhone, 0, 25, 'UTF-8'),
                ':sid'    => $staffId,
                ':sname'  => mb_substr($staffName, 0, 100, 'UTF-8'),
                ':note'   => mb_substr($note, 0, 500, 'UTF-8'),
                ':id'     => (int) $existing['id'],
            ]);
            $msg = 'Đã cập nhật thông tin gán bàn ' . $tableCode . ' (' . $guestCount . ' khách)!';
        } else {
            $stmtIns = $pdo->prepare("
                INSERT INTO `table_assignments`
                    (`table_code`, `guest_count`, `customer_name`, `customer_phone`, `staff_id`, `staff_name`, `note`, `status`, `opened_at`)
                VALUES
                    (:code, :guests, :cname, :cphone, :sid, :sname, :note, 'active', :opened_at)
            ");
            $stmtIns->execute([
                ':code'      => $tableCode,
                ':guests'    => $guestCount,
                ':cname'     => mb_substr($customerName, 0, 100, 'UTF-8'),
                ':cphone'    => mb_substr($customerPhone, 0, 25, 'UTF-8'),
                ':sid'       => $staffId,
                ':sname'     => mb_substr($staffName, 0, 100, 'UTF-8'),
                ':note'      => mb_substr($note, 0, 500, 'UTF-8'),
                ':opened_at' => currentTimeForDb(),
            ]);
            $msg = 'Đã gán bàn ' . $tableCode . ' cho ' . $guestCount . ' khách!';
        }

        if ($ownTx) {
            $pdo->commit();
        }

        return [
            'success'    => true,
            'message'    => $msg,
            'table_code' => $tableCode,
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('assignTable error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống khi gán bàn: ' . $e->getMessage()];
    }
}

/**
 * Hủy gán bàn / trả bàn về trạng thái trống (chỉ khi bàn chưa có món gọi).
 */
function releaseEmptyTableAssignment(PDO $pdo, string $tableCode): array {
    ensureTableAssignmentsSchema($pdo);
    $code = strtoupper(trim($tableCode));
    if ($code === '') {
        return ['success' => false, 'message' => 'Mã bàn không hợp lệ!'];
    }

    $occupiedMap = fetchOccupiedTables($pdo);
    if (isset($occupiedMap[$code])) {
        return ['success' => false, 'message' => 'Bàn ' . $code . ' đang có món chưa thanh toán, không thể trả bàn trống!'];
    }

    closeTableAssignmentInTx($pdo, $code);
    return [
        'success'    => true,
        'message'    => 'Đã trả bàn ' . $code . ' về trạng thái trống!',
        'table_code' => $code,
    ];
}

/**
 * Chuyển toàn bộ đơn mở (orders), món đã gọi và thông tin gán bàn từ bàn nguồn sang bàn trống đích.
 */
function transferTable(PDO $pdo, string $fromCode, string $toCode, string $note = ''): array {
    ensureTableAssignmentsSchema($pdo);

    $fromCode = strtoupper(trim($fromCode));
    $toCode   = strtoupper(trim($toCode));
    $note     = trim($note);

    if ($fromCode === '' || $toCode === '') {
        return ['success' => false, 'message' => 'Vui lòng chọn đầy đủ bàn nguồn và bàn đích!'];
    }
    if ($fromCode === $toCode) {
        return ['success' => false, 'message' => 'Bàn đích phải khác bàn hiện tại!'];
    }

    $fromTable = getDiningTableByCode($fromCode);
    $toTable   = getDiningTableByCode($toCode);
    if (!$fromTable || !$toTable) {
        return ['success' => false, 'message' => 'Bàn nguồn hoặc bàn đích không tồn tại!'];
    }
    if (($toTable['status'] ?? '') !== 'active') {
        return ['success' => false, 'message' => 'Bàn ' . $toCode . ' đang bảo trì hoặc tạm ẩn, không thể chuyển tới!'];
    }

    $ownTx = !$pdo->inTransaction();
    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        // Khóa và kiểm tra trạng thái nguồn & đích trong transaction
        $stmtOpenFrom = $pdo->prepare("SELECT `id` FROM `orders` WHERE `table_code` = :code AND `status` = 'open' FOR UPDATE");
        $stmtOpenFrom->execute([':code' => $fromCode]);
        $fromOrders = $stmtOpenFrom->fetchAll(PDO::FETCH_COLUMN);

        $fromAssignment = getActiveTableAssignment($fromCode, $pdo);

        if ($fromOrders === [] && $fromAssignment === null) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Bàn ' . $fromCode . ' hiện đang trống, không có đơn hoặc khách để chuyển!'];
        }

        $toAssignment = getActiveTableAssignment($toCode, $pdo);
        $occupiedMap  = fetchOccupiedTables($pdo);
        if ($toAssignment !== null || isset($occupiedMap[$toCode])) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Bàn ' . $toCode . ' đang có khách phục vụ, vui lòng chọn bàn trống khác!'];
        }

        // Xóa/đóng đơn mở rỗng (0 món) nếu tồn tại ở bàn đích để tránh trùng đơn mở
        $stmtEmptyTo = $pdo->prepare("
            SELECT o.`id`
            FROM `orders` o
            LEFT JOIN `order_items` i ON i.`order_id` = o.`id` AND i.`status` <> 'cancelled'
            WHERE o.`table_code` = :code AND o.`status` = 'open'
            GROUP BY o.`id`
            HAVING COUNT(i.`id`) = 0
        ");
        $stmtEmptyTo->execute([':code' => $toCode]);
        foreach ($stmtEmptyTo->fetchAll(PDO::FETCH_COLUMN) as $emptyOrderId) {
            $pdo->prepare("DELETE FROM `orders` WHERE `id` = ?")->execute([(int) $emptyOrderId]);
        }

        // Chuyển toàn bộ đơn mở (orders) từ bàn nguồn -> bàn đích (KDS & bill tự động theo order_id)
        if ($fromOrders !== []) {
            $stmtMoveOrders = $pdo->prepare("
                UPDATE `orders`
                SET `table_code` = :to_code
                WHERE `table_code` = :from_code AND `status` = 'open'
            ");
            $stmtMoveOrders->execute([':to_code' => $toCode, ':from_code' => $fromCode]);
        }

        // Chuyển thông tin gán bàn (nếu có), hoặc ghi chú lịch sử chuyển bàn
        $transferTag = 'Chuyển từ bàn ' . $fromCode . ($note !== '' ? ' (' . $note . ')' : '');
        if ($fromAssignment !== null) {
            $oldNote = trim((string) ($fromAssignment['note'] ?? ''));
            $newNote = $oldNote !== '' ? ($oldNote . ' | ' . $transferTag) : $transferTag;
            $stmtMoveAssign = $pdo->prepare("
                UPDATE `table_assignments`
                SET `table_code` = :to_code, `note` = :note
                WHERE `id` = :id AND `status` = 'active'
            ");
            $stmtMoveAssign->execute([
                ':to_code' => $toCode,
                ':note'    => mb_substr($newNote, 0, 500, 'UTF-8'),
                ':id'      => (int) $fromAssignment['id'],
            ]);
        } elseif ($note !== '') {
            $stmtIns = $pdo->prepare("
                INSERT INTO `table_assignments`
                    (`table_code`, `guest_count`, `customer_name`, `customer_phone`, `staff_id`, `staff_name`, `note`, `status`, `opened_at`)
                VALUES
                    (:code, 2, '', '', NULL, '', :note, 'active', :opened_at)
            ");
            $stmtIns->execute([
                ':code'      => $toCode,
                ':note'      => mb_substr($transferTag, 0, 500, 'UTF-8'),
                ':opened_at' => currentTimeForDb(),
            ]);
        }

        // Đồng bộ phiên QR đang mở (nếu bảng qr_sessions đã được khởi tạo)
        try {
            $hasQrTable = $pdo->query("SHOW TABLES LIKE 'qr_sessions'")->fetchColumn();
            if ($hasQrTable) {
                $stmtToQr = $pdo->prepare("SELECT COUNT(*) FROM `qr_sessions` WHERE `table_code` = ? AND `status` = 'open'");
                $stmtToQr->execute([$toCode]);
                if ((int) $stmtToQr->fetchColumn() === 0) {
                    $pdo->prepare("UPDATE `qr_sessions` SET `table_code` = ? WHERE `table_code` = ? AND `status` = 'open'")
                        ->execute([$toCode, $fromCode]);
                }
            }
        } catch (Throwable $qrEx) {
            error_log('transferTable qr_sessions sync: ' . $qrEx->getMessage());
        }

        if ($ownTx) {
            $pdo->commit();
        }

        return [
            'success'    => true,
            'message'    => 'Đã chuyển bàn ' . $fromCode . ' sang bàn ' . $toCode . ' thành công!',
            'from_table' => $fromCode,
            'to_table'   => $toCode,
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('transferTable error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống khi chuyển bàn: ' . $e->getMessage()];
    }
}

/**
 * Gộp bàn: chuyển toàn bộ món ăn (order_items), đơn mở và số khách từ bàn nguồn sang bàn đích đang phục vụ.
 * Bàn nguồn sau khi gộp sẽ trở về trạng thái trống.
 */
function mergeTables(PDO $pdo, string $fromCode, string $toCode, string $note = ''): array {
    ensureTableAssignmentsSchema($pdo);

    $fromCode = strtoupper(trim($fromCode));
    $toCode   = strtoupper(trim($toCode));
    $note     = trim($note);

    if ($fromCode === '' || $toCode === '') {
        return ['success' => false, 'message' => 'Vui lòng chọn đầy đủ bàn cần gộp và bàn nhận gộp!'];
    }
    if ($fromCode === $toCode) {
        return ['success' => false, 'message' => 'Không thể gộp một bàn với chính nó!'];
    }

    $fromTable = getDiningTableByCode($fromCode);
    $toTable   = getDiningTableByCode($toCode);
    if (!$fromTable || !$toTable) {
        return ['success' => false, 'message' => 'Bàn nguồn hoặc bàn đích không tồn tại!'];
    }
    if (($toTable['status'] ?? '') !== 'active') {
        return ['success' => false, 'message' => 'Bàn đích ' . $toCode . ' đang bảo trì hoặc tạm ẩn, không thể gộp vào!'];
    }

    $ownTx = !$pdo->inTransaction();
    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        // Khóa toàn bộ đơn mở ở cả 2 bàn
        $stmtOpenFrom = $pdo->prepare("SELECT `id` FROM `orders` WHERE `table_code` = :code AND `status` = 'open' ORDER BY `id` ASC FOR UPDATE");
        $stmtOpenFrom->execute([':code' => $fromCode]);
        $fromOrderIds = array_map('intval', $stmtOpenFrom->fetchAll(PDO::FETCH_COLUMN));

        $stmtOpenTo = $pdo->prepare("SELECT `id` FROM `orders` WHERE `table_code` = :code AND `status` = 'open' ORDER BY `id` ASC FOR UPDATE");
        $stmtOpenTo->execute([':code' => $toCode]);
        $toOrderIds = array_map('intval', $stmtOpenTo->fetchAll(PDO::FETCH_COLUMN));

        $fromAssignment = getActiveTableAssignment($fromCode, $pdo);
        $toAssignment   = getActiveTableAssignment($toCode, $pdo);

        if ($fromOrderIds === [] && $fromAssignment === null) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Bàn nguồn ' . $fromCode . ' hiện đang trống, không có dữ liệu để gộp!'];
        }

        if ($toOrderIds === [] && $toAssignment === null) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Bàn đích ' . $toCode . ' hiện đang trống. Vui lòng dùng chức năng Chuyển bàn hoặc chọn một bàn khác đang phục vụ!'];
        }

        // Hợp nhất orders & order_items vào 1 đơn mở duy nhất tại bàn đích
        if ($toOrderIds !== []) {
            $targetOrderId = $toOrderIds[0];

            // Gộp các đơn phụ của chính bàn đích (nếu có) về đơn chính
            if (count($toOrderIds) > 1) {
                $extraToIds = array_slice($toOrderIds, 1);
                $phExtra = implode(', ', array_fill(0, count($extraToIds), '?'));
                $pdo->prepare("UPDATE `order_items` SET `order_id` = ? WHERE `order_id` IN ($phExtra)")
                    ->execute(array_merge([$targetOrderId], $extraToIds));
                $pdo->prepare("DELETE FROM `orders` WHERE `id` IN ($phExtra)")
                    ->execute($extraToIds);
            }

            // Chuyển toàn bộ order_items từ bàn nguồn sang đơn chính của bàn đích
            if ($fromOrderIds !== []) {
                $phFrom = implode(', ', array_fill(0, count($fromOrderIds), '?'));
                $pdo->prepare("UPDATE `order_items` SET `order_id` = ? WHERE `order_id` IN ($phFrom)")
                    ->execute(array_merge([$targetOrderId], $fromOrderIds));
                $pdo->prepare("DELETE FROM `orders` WHERE `id` IN ($phFrom)")
                    ->execute($fromOrderIds);
            }
        } elseif ($fromOrderIds !== []) {
            // Bàn đích mới chỉ gán bàn (chưa có order row), bàn nguồn đã có order -> đổi table_code sang bàn đích
            $targetOrderId = $fromOrderIds[0];
            $pdo->prepare("UPDATE `orders` SET `table_code` = ? WHERE `id` = ?")
                ->execute([$toCode, $targetOrderId]);

            if (count($fromOrderIds) > 1) {
                $extraFromIds = array_slice($fromOrderIds, 1);
                $phExtra = implode(', ', array_fill(0, count($extraFromIds), '?'));
                $pdo->prepare("UPDATE `order_items` SET `order_id` = ? WHERE `order_id` IN ($phExtra)")
                    ->execute(array_merge([$targetOrderId], $extraFromIds));
                $pdo->prepare("DELETE FROM `orders` WHERE `id` IN ($phExtra)")
                    ->execute($extraFromIds);
            }
        }

        // Hợp nhất thông tin gán bàn (cộng số khách, gộp ghi chú)
        $mergeTag = 'Gộp từ bàn ' . $fromCode . ($note !== '' ? ' (' . $note . ')' : '');
        if ($toAssignment !== null) {
            $fromGuests = (int) ($fromAssignment['guest_count'] ?? 0);
            $toGuests   = (int) ($toAssignment['guest_count'] ?? 0);
            $totalGuests = max(1, $fromGuests + $toGuests);

            $mergedNoteParts = array_filter([
                trim((string) ($toAssignment['note'] ?? '')),
                trim((string) ($fromAssignment['note'] ?? '')),
                $mergeTag,
            ], static fn(string $s): bool => $s !== '');
            $mergedNote = mb_substr(implode(' | ', array_unique($mergedNoteParts)), 0, 500, 'UTF-8');

            $staffId   = $toAssignment['staff_id'] ?: ($fromAssignment['staff_id'] ?? null);
            $staffName = trim((string) ($toAssignment['staff_name'] ?? '')) ?: trim((string) ($fromAssignment['staff_name'] ?? ''));
            $custName  = trim((string) ($toAssignment['customer_name'] ?? '')) ?: trim((string) ($fromAssignment['customer_name'] ?? ''));
            $custPhone = trim((string) ($toAssignment['customer_phone'] ?? '')) ?: trim((string) ($fromAssignment['customer_phone'] ?? ''));

            $stmtUpdTo = $pdo->prepare("
                UPDATE `table_assignments`
                SET `guest_count` = :guests,
                    `customer_name` = :cname,
                    `customer_phone` = :cphone,
                    `staff_id` = :sid,
                    `staff_name` = :sname,
                    `note` = :note
                WHERE `id` = :id
            ");
            $stmtUpdTo->execute([
                ':guests' => $totalGuests,
                ':cname'  => mb_substr($custName, 0, 100, 'UTF-8'),
                ':cphone' => mb_substr($custPhone, 0, 25, 'UTF-8'),
                ':sid'    => $staffId ? (int) $staffId : null,
                ':sname'  => mb_substr($staffName, 0, 100, 'UTF-8'),
                ':note'   => $mergedNote,
                ':id'     => (int) $toAssignment['id'],
            ]);

            if ($fromAssignment !== null) {
                $pdo->prepare("UPDATE `table_assignments` SET `status` = 'closed', `closed_at` = NOW() WHERE `id` = ?")
                    ->execute([(int) $fromAssignment['id']]);
            }
        } elseif ($fromAssignment !== null) {
            $oldNote = trim((string) ($fromAssignment['note'] ?? ''));
            $newNote = mb_substr($oldNote !== '' ? ($oldNote . ' | ' . $mergeTag) : $mergeTag, 0, 500, 'UTF-8');
            $pdo->prepare("UPDATE `table_assignments` SET `table_code` = :to_code, `note` = :note WHERE `id` = :id")
                ->execute([
                    ':to_code' => $toCode,
                    ':note'    => $newNote,
                    ':id'      => (int) $fromAssignment['id'],
                ]);
        }

        // Đóng phiên QR mở của bàn nguồn (nếu có) để tránh xung đột 2 bàn
        try {
            $hasQrTable = $pdo->query("SHOW TABLES LIKE 'qr_sessions'")->fetchColumn();
            if ($hasQrTable) {
                $pdo->prepare("UPDATE `qr_sessions` SET `status` = 'closed', `active_slot` = NULL WHERE `table_code` = ? AND `status` = 'open'")
                    ->execute([$fromCode]);
            }
        } catch (Throwable $qrEx) {
            error_log('mergeTables qr_sessions sync: ' . $qrEx->getMessage());
        }

        if ($ownTx) {
            $pdo->commit();
        }

        return [
            'success'    => true,
            'message'    => 'Đã gộp bàn ' . $fromCode . ' vào bàn ' . $toCode . ' thành công!',
            'from_table' => $fromCode,
            'to_table'   => $toCode,
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('mergeTables error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống khi gộp bàn: ' . $e->getMessage()];
    }
}

/**
 * Xác nhận khách đặt bàn đã đến và vào bàn:
 * - Chuyển trạng thái public_reservations sang 'seated' và lưu seated_at.
 * - Tự động gán bàn tương ứng trên Sơ đồ bàn (table_assignments) với tên, SĐT, số khách và mã đặt bàn.
 */
function seatPublicReservation(PDO $pdo, int $reservationId, int $staffId = 0): array {
    if ($reservationId <= 0) {
        return ['success' => false, 'message' => 'Mã đặt bàn không hợp lệ!'];
    }

    ensurePublicReservationsSchema($pdo);
    ensureTableAssignmentsSchema($pdo);

    $ownTx = !$pdo->inTransaction();
    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        $stmt = $pdo->prepare("
            SELECT r.*, t.`code` AS `table_code`, t.`name` AS `table_name`
            FROM `public_reservations` r
            LEFT JOIN `dining_tables` t ON t.`id` = r.`table_id`
            WHERE r.`id` = :id
            FOR UPDATE
        ");
        $stmt->execute([':id' => $reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reservation) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Không tìm thấy yêu cầu đặt bàn!'];
        }

        $currentStatus = (string) ($reservation['status'] ?? '');
        if ($currentStatus === 'seated') {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Khách đặt bàn này đã được ghi nhận vào bàn trước đó!'];
        }

        if (!in_array($currentStatus, ['pending', 'confirmed'], true)) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Chỉ có thể cho vào bàn với lượt đặt đang chờ xác nhận hoặc đã xác nhận!'];
        }

        $tableCode = strtoupper(trim((string) ($reservation['table_code'] ?? '')));
        if ($tableCode === '') {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Không tìm thấy bàn gắn với lượt đặt này!'];
        }

        $occupiedMap = fetchOccupiedTables($pdo);
        if (isset($occupiedMap[$tableCode])) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Bàn ' . $tableCode . ' đang có khách gọi món chưa thanh toán, vui lòng xử lý hoặc chuyển bàn trước!'];
        }

        $bookingCode = 'SD' . str_pad((string) $reservationId, 6, '0', STR_PAD_LEFT);
        $rawNote     = trim((string) ($reservation['note'] ?? ''));
        $assignNote  = 'Đặt bàn #' . $bookingCode . ($rawNote !== '' ? ' | ' . $rawNote : '');

        $assignResult = assignTable($pdo, [
            'table_code'     => $tableCode,
            'guest_count'    => max(1, (int) ($reservation['guests'] ?? 2)),
            'customer_name'  => (string) ($reservation['customer_name'] ?? ''),
            'customer_phone' => (string) ($reservation['phone'] ?? ''),
            'staff_id'       => $staffId,
            'note'           => $assignNote,
            'allow_update'   => false,
        ]);

        if (empty($assignResult['success'])) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $assignResult;
        }

        $stmtUpd = $pdo->prepare("
            UPDATE `public_reservations`
            SET `status` = 'seated',
                `seated_at` = :seated_at
            WHERE `id` = :id
        ");
        $stmtUpd->execute([
            ':seated_at' => currentTimeForDb(),
            ':id'        => $reservationId,
        ]);

        if ($ownTx) {
            $pdo->commit();
        }

        return [
            'success'      => true,
            'message'      => 'Khách ' . ($reservation['customer_name'] ?? '') . ' (' . $bookingCode . ') đã vào bàn ' . $tableCode . '!',
            'table_code'   => $tableCode,
            'booking_code' => $bookingCode,
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('seatPublicReservation error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống khi xác nhận vào bàn: ' . $e->getMessage()];
    }
}
