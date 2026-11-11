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

    // Đồng bộ thông tin đơn hàng hiện tại (có khách hay trống)
    $occupiedTables = [];
    try {
        $occupiedTables = fetchOccupiedTables($pdo);
    } catch (Exception $e) {
        error_log('sync occupied tables error: ' . $e->getMessage());
    }

    foreach ($tables as &$t) {
        $occ = $occupiedTables[$t['code']] ?? null;
        $t['is_occupied']    = ($occ !== null);
        $t['occupied_total'] = $occ['total'] ?? 0;
        $t['occupied_items'] = $occ['item_count'] ?? 0;
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
