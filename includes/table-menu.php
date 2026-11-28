<?php
/**
 * SmartDine - Khóa cứng thực đơn theo bàn & Quản lý Vé Buffet / Đổi vé.
 *
 * Quy tắc nghiệp vụ:
 *  - Khi khách chọn menu (Gói Buffet hoặc Menu Gọi món lẻ) trên QR (hoặc nhân viên thêm vé buffet đầu tiên),
 *    bàn đó bị khóa cứng thực đơn (`status = 'locked'`) cho đến khi thanh toán xong bàn (`checkoutTable`).
 *  - Chỉ `admin` hoặc `shift_manager` (Staff/Shift Manager) mới có quyền đổi vé / đổi loại menu của bàn.
 *  - Vé Buffet được ghi vào `order_items` với trạng thái `done` (tính tiền vào hóa đơn nhưng không đẩy lên KDS Bếp).
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/tables.php';

/**
 * Đảm bảo bảng `table_menu_locks` tồn tại (KHÔNG gọi DDL bên trong transaction).
 */
function ensureTableMenuLocksSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready || $pdo->inTransaction()) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `table_menu_locks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `table_code` VARCHAR(20) NOT NULL,
            `order_id` INT DEFAULT NULL,
            `menu_type` ENUM('buffet', 'alacarte') NOT NULL,
            `buffet_package_id` INT DEFAULT NULL,
            `package_name` VARCHAR(150) NOT NULL DEFAULT '',
            `ticket_price` INT NOT NULL DEFAULT 0,
            `ticket_qty` INT NOT NULL DEFAULT 0,
            `locked_by` VARCHAR(40) NOT NULL DEFAULT 'customer_qr',
            `updated_by_user_id` INT DEFAULT NULL,
            `status` ENUM('locked', 'closed') NOT NULL DEFAULT 'locked',
            `locked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `closed_at` DATETIME DEFAULT NULL,
            INDEX `idx_tml_table_status` (`table_code`, `status`),
            INDEX `idx_tml_order_status` (`order_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ready = true;
}

/**
 * Tên dòng hóa đơn cho vé Buffet.
 */
function formatBuffetTicketName(string $packageName): string
{
    $clean = trim($packageName);
    if ($clean === '') {
        return 'Vé Buffet';
    }
    return mb_stripos($clean, 'vé ', 0, 'UTF-8') === 0 ? $clean : ('Vé ' . $clean);
}

/**
 * Chuỗi mô tả ngắn trạng thái khóa menu của bàn.
 */
function formatTableMenuLockLabel(?array $lock): string
{
    if (!$lock) {
        return 'Chưa khóa menu';
    }
    if (($lock['menu_type'] ?? '') === 'alacarte') {
        return 'MENU GỌI MÓN (Alacarte)';
    }
    $name = trim((string) ($lock['package_name'] ?? 'Buffet'));
    $price = (int) ($lock['ticket_price'] ?? 0);
    return $price > 0 ? ($name . ' (' . formatMoney($price) . '/vé)') : $name;
}

/**
 * Lấy thông tin menu đang khóa cứng của 1 bàn (trả về null nếu bàn chưa khóa hoặc đã thanh toán).
 */
function getActiveTableMenuLock(PDO $pdo, string $tableCode): ?array
{
    $code = strtoupper(trim($tableCode));
    if ($code === '') {
        return null;
    }

    ensureTableMenuLocksSchema($pdo);

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM `table_menu_locks`
            WHERE `table_code` = :code AND `status` = 'locked'
            ORDER BY `id` DESC
            LIMIT 1
        ");
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Định dạng mảng khóa menu trả về cho API JSON (QR Order & POS).
 */
function serializeTableMenuLock(?array $lock): ?array
{
    if (!$lock) {
        return null;
    }

    return [
        'id'                => (int) ($lock['id'] ?? 0),
        'table_code'        => (string) ($lock['table_code'] ?? ''),
        'order_id'          => !empty($lock['order_id']) ? (int) $lock['order_id'] : 0,
        'menu_type'         => (string) ($lock['menu_type'] ?? 'alacarte'),
        'buffet_package_id' => !empty($lock['buffet_package_id']) ? (int) $lock['buffet_package_id'] : 0,
        'package_name'      => (string) ($lock['package_name'] ?? ''),
        'ticket_price'      => (int) ($lock['ticket_price'] ?? 0),
        'ticket_qty'        => (int) ($lock['ticket_qty'] ?? 0),
        'locked_by'         => (string) ($lock['locked_by'] ?? 'customer_qr'),
        'locked_at'         => (string) ($lock['locked_at'] ?? ''),
        'label'             => formatTableMenuLockLabel($lock),
        'locked'            => true,
    ];
}

/**
 * Giải phóng khóa menu của bàn khi thanh toán hoặc trả bàn trống (dùng bên trong transaction).
 */
function closeTableMenuLockInTx(PDO $pdo, string $tableCode): void
{
    $code = strtoupper(trim($tableCode));
    if ($code === '') {
        return;
    }

    try {
        if ($pdo->inTransaction()) {
            if ($pdo->query("SHOW TABLES LIKE 'table_menu_locks'")->fetchColumn() === false) {
                return;
            }
        } else {
            ensureTableMenuLocksSchema($pdo);
        }

        $pdo->prepare("
            UPDATE `table_menu_locks`
            SET `status` = 'closed', `closed_at` = NOW()
            WHERE `table_code` = :code AND `status` = 'locked'
        ")->execute([':code' => $code]);
    } catch (PDOException $e) {
        // Bỏ qua nếu bảng chưa được tạo trên DB
    }
}

/**
 * Chuyển khóa menu sang bàn đích khi nghiệp vụ Chuyển bàn chạy trong transaction.
 */
function transferTableMenuLockInTx(PDO $pdo, string $fromCode, string $toCode): void
{
    $fromCode = strtoupper(trim($fromCode));
    $toCode   = strtoupper(trim($toCode));
    if ($fromCode === '' || $toCode === '' || $fromCode === $toCode) {
        return;
    }

    try {
        if ($pdo->inTransaction()) {
            if ($pdo->query("SHOW TABLES LIKE 'table_menu_locks'")->fetchColumn() === false) {
                return;
            }
        } else {
            ensureTableMenuLocksSchema($pdo);
        }

        $pdo->prepare("
            UPDATE `table_menu_locks`
            SET `status` = 'closed', `closed_at` = NOW()
            WHERE `table_code` = :to_code AND `status` = 'locked'
        ")->execute([':to_code' => $toCode]);

        $pdo->prepare("
            UPDATE `table_menu_locks`
            SET `table_code` = :to_code
            WHERE `table_code` = :from_code AND `status` = 'locked'
        ")->execute([':to_code' => $toCode, ':from_code' => $fromCode]);
    } catch (PDOException $e) {
        // Bỏ qua nếu bảng chưa được tạo trên DB
    }
}

/**
 * Kiểm tra món ăn định gọi có hợp lệ với thực đơn đang khóa cứng của bàn hay không.
 * Trả về thông báo lỗi nếu bị chặn, hoặc null nếu hợp lệ.
 */
function validateDishAgainstTableMenuLock(?array $lock, array $dish): ?string
{
    if (!$lock) {
        return null;
    }

    $lockType     = (string) ($lock['menu_type'] ?? '');
    $dishMenuType = (($dish['menu_type'] ?? 'alacarte') === 'buffet') ? 'buffet' : 'alacarte';

    if ($lockType === 'alacarte' && $dishMenuType === 'buffet') {
        return 'Bàn đang khóa Menu gọi món lẻ (Alacarte), không thể gọi món Buffet 0đ. Chỉ Quản lý ca hoặc Quản trị viên mới được quyền đổi vé.';
    }

    if ($lockType === 'buffet' && $dishMenuType === 'buffet') {
        $lockedPkgId = (int) ($lock['buffet_package_id'] ?? 0);
        $dishPkgId   = !empty($dish['buffet_package_id']) ? (int) $dish['buffet_package_id'] : 0;
        if ($lockedPkgId > 0 && $dishPkgId > 0 && $dishPkgId !== $lockedPkgId) {
            return 'Món "' . ($dish['name'] ?? '') . '" không thuộc gói "' . ($lock['package_name'] ?? 'Buffet') . '" đang khóa cho bàn. Chỉ Quản lý ca hoặc Quản trị viên mới được quyền đổi vé.';
        }
    }

    return null;
}

/**
 * Tìm gói Buffet đang active theo ID.
 */
function findActiveBuffetPackage(PDO $pdo, int $packageId): ?array
{
    if ($packageId <= 0) {
        return null;
    }
    ensureMenuConfigSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM `buffet_packages` WHERE `id` = ? AND `status` = 'active' LIMIT 1");
    $stmt->execute([$packageId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Ghi/cộng dồn dòng Vé Buffet vào `order_items` (trạng thái `done` để tính bill ngay mà không đưa sang bếp KDS).
 */
function upsertBuffetTicketOrderItemInTx(
    PDO $pdo,
    int $orderId,
    array $package,
    int $qty,
    string $note,
    ?int $userId
): int {
    $ticketName  = formatBuffetTicketName((string) ($package['name'] ?? 'Buffet'));
    $ticketPrice = max(0, (int) round((float) ($package['price_per_person'] ?? 0)));
    $nowDb       = currentTimeForDb();
    $cleanNote   = mb_substr(trim($note), 0, 255, 'UTF-8');
    $finalNote   = $cleanNote !== '' ? ('Vé Buffet · ' . $cleanNote) : 'Vé Buffet';
    $sentBy      = ($userId !== null && $userId > 0) ? $userId : null;

    $findExisting = $pdo->prepare("
        SELECT `id`, `qty`
        FROM `order_items`
        WHERE `order_id` = ?
          AND `item_name` = ?
          AND `unit_price` = ?
          AND `status` = 'done'
          AND `note` LIKE 'Vé Buffet%'
        ORDER BY `id` DESC
        LIMIT 1
        FOR UPDATE
    ");
    $findExisting->execute([$orderId, $ticketName, $ticketPrice]);
    $existing = $findExisting->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $itemId = (int) $existing['id'];
        $newQty = (int) $existing['qty'] + $qty;
        $pdo->prepare("UPDATE `order_items` SET `qty` = ?, `sent_by` = COALESCE(?, `sent_by`), `sent_at` = ? WHERE `id` = ?")
            ->execute([$newQty, $sentBy, $nowDb, $itemId]);
        return $itemId;
    }

    $ins = $pdo->prepare("
        INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `note`, `status`, `sent_by`, `sent_at`, `done_at`)
        VALUES (?, ?, ?, ?, ?, 'done', ?, ?, ?)
    ");
    $ins->execute([$orderId, $ticketName, $ticketPrice, $qty, $finalNote, $sentBy, $nowDb, $nowDb]);
    return (int) $pdo->lastInsertId();
}

/**
 * Hủy toàn bộ dòng Vé Buffet đang hiệu lực của đơn mở (khi Quản lý / Admin đổi vé hoặc đổi sang Alacarte).
 */
function cancelActiveBuffetTicketItemsInTx(PDO $pdo, int $orderId): int
{
    if ($orderId <= 0) {
        return 0;
    }

    $nowDb = currentTimeForDb();
    $stmt = $pdo->prepare("
        UPDATE `order_items`
        SET `status` = 'cancelled', `cancelled_at` = ?
        WHERE `order_id` = ?
          AND `status` <> 'cancelled'
          AND (
              `note` LIKE 'Vé Buffet%'
              OR EXISTS (
                  SELECT 1 FROM `buffet_packages` bp
                  WHERE `order_items`.`item_name` = CONCAT('Vé ', bp.`name`)
                     OR `order_items`.`item_name` = bp.`name`
              )
          )
    ");
    $stmt->execute([$nowDb, $orderId]);
    return $stmt->rowCount();
}

/**
 * Đếm tổng số vé Buffet đang hiệu lực trên đơn mở của bàn.
 */
function countActiveBuffetTicketQty(PDO $pdo, int $orderId): int
{
    if ($orderId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(`qty`), 0)
        FROM `order_items`
        WHERE `order_id` = ?
          AND `status` <> 'cancelled'
          AND (
              `note` LIKE 'Vé Buffet%'
              OR EXISTS (
                  SELECT 1 FROM `buffet_packages` bp
                  WHERE `order_items`.`item_name` = CONCAT('Vé ', bp.`name`)
                     OR `order_items`.`item_name` = bp.`name`
              )
          )
    ");
    $stmt->execute([$orderId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Khách chọn & khóa cứng menu từ QR Order (chỉ cho phép khi bàn chưa khóa menu).
 */
function lockTableMenuByCustomer(
    PDO $pdo,
    string $tableCode,
    string $menuType,
    int $packageId = 0,
    int $ticketQty = 1
): array {
    $code = strtoupper(trim($tableCode));
    if (!preg_match('/^[A-Z0-9_-]{1,20}$/', $code)) {
        return ['success' => false, 'code' => 400, 'message' => 'Mã bàn không hợp lệ.'];
    }

    $menuType = $menuType === 'buffet' ? 'buffet' : ($menuType === 'alacarte' ? 'alacarte' : '');
    if ($menuType === '') {
        return ['success' => false, 'code' => 400, 'message' => 'Loại menu không hợp lệ.'];
    }

    ensureMenuConfigSchema($pdo);
    ensureTableMenuLocksSchema($pdo);
    ensureTableAssignmentsSchema($pdo);

    $package = null;
    if ($menuType === 'buffet') {
        $package = findActiveBuffetPackage($pdo, $packageId);
        if (!$package) {
            return ['success' => false, 'code' => 404, 'message' => 'Gói Buffet được chọn không tồn tại hoặc đã ngừng phục vụ.'];
        }
        $ticketQty = max(1, min(100, $ticketQty));
    } else {
        $ticketQty = 0;
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $lockStmt = $pdo->prepare("
            SELECT * FROM `table_menu_locks`
            WHERE `table_code` = :code AND `status` = 'locked'
            ORDER BY `id` DESC
            LIMIT 1
            FOR UPDATE
        ");
        $lockStmt->execute([':code' => $code]);
        $existingLock = $lockStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingLock) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'success' => false,
                'code'    => 409,
                'locked'  => true,
                'lock'    => serializeTableMenuLock($existingLock),
                'message' => 'Bàn ' . $code . ' đã khóa menu (' . formatTableMenuLockLabel($existingLock) . '). Chỉ Quản lý ca hoặc Quản trị viên mới có quyền đổi vé.',
            ];
        }

        $orderId = null;
        $pkgName = 'MENU GỌI MÓN';
        $ticketPrice = 0;

        if ($menuType === 'buffet' && $package !== null) {
            $orderId     = findOrCreateOpenOrderId($pdo, $code);
            $pkgName     = (string) $package['name'];
            $ticketPrice = max(0, (int) round((float) $package['price_per_person']));

            // Chỉ tự động thêm vé nếu đơn chưa có sẵn vé buffet (tránh trùng nếu nhân viên đã bấm vé trước đó)
            if (countActiveBuffetTicketQty($pdo, $orderId) === 0) {
                upsertBuffetTicketOrderItemInTx($pdo, $orderId, $package, $ticketQty, 'Khách chọn qua QR', null);
            } else {
                $ticketQty = countActiveBuffetTicketQty($pdo, $orderId);
            }
        } else {
            $openOrderStmt = $pdo->prepare("SELECT `id` FROM `orders` WHERE `table_code` = ? AND `status` = 'open' ORDER BY `id` DESC LIMIT 1");
            $openOrderStmt->execute([$code]);
            $foundOrderId = $openOrderStmt->fetchColumn();
            $orderId = $foundOrderId !== false ? (int) $foundOrderId : null;
        }

        $ins = $pdo->prepare("
            INSERT INTO `table_menu_locks`
                (`table_code`, `order_id`, `menu_type`, `buffet_package_id`, `package_name`, `ticket_price`, `ticket_qty`, `locked_by`, `updated_by_user_id`, `status`)
            VALUES
                (:table_code, :order_id, :menu_type, :pkg_id, :pkg_name, :ticket_price, :ticket_qty, 'customer_qr', NULL, 'locked')
        ");
        $ins->execute([
            ':table_code'   => $code,
            ':order_id'     => $orderId,
            ':menu_type'    => $menuType,
            ':pkg_id'       => $menuType === 'buffet' ? (int) $package['id'] : null,
            ':pkg_name'     => $pkgName,
            ':ticket_price' => $ticketPrice,
            ':ticket_qty'   => $ticketQty,
        ]);

        if ($ownTx) {
            $pdo->commit();
        }

        $freshLock = getActiveTableMenuLock($pdo, $code);
        return [
            'success' => true,
            'code'    => 200,
            'lock'    => serializeTableMenuLock($freshLock),
            'message' => 'Đã chọn và khóa menu ' . formatTableMenuLockLabel($freshLock) . ' cho Bàn ' . $code . '.',
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Thêm Vé Buffet vào màn hình/đơn bàn (Staff / Shift Manager / Admin).
 * Nếu bàn đã khóa sang gói/menu khác -> chặn và yêu cầu Quản lý ca / Admin dùng chức năng Đổi vé.
 */
function addBuffetTicketToTable(
    PDO $pdo,
    string $tableCode,
    int $packageId,
    int $qty,
    string $note = '',
    ?int $userId = null
): array {
    $code = strtoupper(trim($tableCode));
    if (!preg_match('/^[A-Z0-9_-]{1,20}$/', $code)) {
        throw new OrderException('Mã bàn không hợp lệ.');
    }

    if ($qty < 1 || $qty > ORDER_ADD_MAX_QTY) {
        throw new OrderException('Số lượng vé phải từ 1 đến ' . ORDER_ADD_MAX_QTY . '.');
    }

    $tableStmt = $pdo->prepare("SELECT 1 FROM `dining_tables` WHERE `code` = ? AND `status` = 'active'");
    $tableStmt->execute([$code]);
    if ($tableStmt->fetchColumn() === false) {
        throw new OrderException('Bàn ' . $code . ' không tồn tại hoặc đang không hoạt động.');
    }

    ensureMenuConfigSchema($pdo);
    ensureTableMenuLocksSchema($pdo);

    $package = findActiveBuffetPackage($pdo, $packageId);
    if (!$package) {
        throw new OrderException('Gói Buffet không tồn tại hoặc đã ngừng phục vụ.');
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $lockStmt = $pdo->prepare("
            SELECT * FROM `table_menu_locks`
            WHERE `table_code` = :code AND `status` = 'locked'
            ORDER BY `id` DESC
            LIMIT 1
            FOR UPDATE
        ");
        $lockStmt->execute([':code' => $code]);
        $existingLock = $lockStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingLock) {
            $lockedType  = (string) ($existingLock['menu_type'] ?? '');
            $lockedPkgId = (int) ($existingLock['buffet_package_id'] ?? 0);
            if ($lockedType !== 'buffet' || $lockedPkgId !== $packageId) {
                throw new OrderException('Bàn ' . $code . ' đang khóa menu "' . formatTableMenuLockLabel($existingLock) . '". Chỉ Quản lý ca hoặc Quản trị viên mới được quyền đổi vé.');
            }
        }

        $orderId = findOrCreateOpenOrderId($pdo, $code);
        $itemId  = upsertBuffetTicketOrderItemInTx($pdo, $orderId, $package, $qty, $note, $userId);
        $totalTicketQty = countActiveBuffetTicketQty($pdo, $orderId);

        if ($existingLock) {
            $pdo->prepare("UPDATE `table_menu_locks` SET `order_id` = ?, `ticket_qty` = ? WHERE `id` = ?")
                ->execute([$orderId, $totalTicketQty, (int) $existingLock['id']]);
        } else {
            $pdo->prepare("
                INSERT INTO `table_menu_locks`
                    (`table_code`, `order_id`, `menu_type`, `buffet_package_id`, `package_name`, `ticket_price`, `ticket_qty`, `locked_by`, `updated_by_user_id`, `status`)
                VALUES
                    (:table_code, :order_id, 'buffet', :pkg_id, :pkg_name, :ticket_price, :ticket_qty, 'staff_pos', :uid, 'locked')
            ")->execute([
                ':table_code'   => $code,
                ':order_id'     => $orderId,
                ':pkg_id'       => $packageId,
                ':pkg_name'     => (string) $package['name'],
                ':ticket_price' => max(0, (int) round((float) $package['price_per_person'])),
                ':ticket_qty'   => $totalTicketQty,
                ':uid'          => ($userId !== null && $userId > 0) ? $userId : null,
            ]);
        }

        if ($ownTx) {
            $pdo->commit();
        }

        return [
            'ok'         => true,
            'item_id'    => $itemId,
            'order_id'   => $orderId,
            'ticket_qty' => $totalTicketQty,
            'lock'       => serializeTableMenuLock(getActiveTableMenuLock($pdo, $code)),
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Đổi vé / Đổi loại menu bàn (CHỈ Quản lý ca `shift_manager` hoặc Quản trị viên `admin`).
 * Hỗ trợ: 'buffet' (chọn gói & số vé), 'alacarte' (menu gọi món lẻ), 'unlock' (mở khóa để khách chọn lại).
 */
function changeTableMenuByManager(
    PDO $pdo,
    string $tableCode,
    string $targetMenuType,
    int $packageId,
    int $ticketQty,
    int $managerUserId,
    string $managerName = ''
): array {
    $code = strtoupper(trim($tableCode));
    if (!preg_match('/^[A-Z0-9_-]{1,20}$/', $code)) {
        return ['success' => false, 'code' => 400, 'message' => 'Mã bàn không hợp lệ.'];
    }

    if (!in_array($targetMenuType, ['buffet', 'alacarte', 'unlock'], true)) {
        return ['success' => false, 'code' => 400, 'message' => 'Loại thao tác đổi vé không hợp lệ.'];
    }

    ensureMenuConfigSchema($pdo);
    ensureTableMenuLocksSchema($pdo);

    $package = null;
    if ($targetMenuType === 'buffet') {
        $package = findActiveBuffetPackage($pdo, $packageId);
        if (!$package) {
            return ['success' => false, 'code' => 404, 'message' => 'Gói Buffet mới không tồn tại hoặc đã ngừng phục vụ.'];
        }
        $ticketQty = max(1, min(100, $ticketQty));
    }

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $openOrderStmt = $pdo->prepare("SELECT `id` FROM `orders` WHERE `table_code` = ? AND `status` = 'open' ORDER BY `id` DESC LIMIT 1 FOR UPDATE");
        $openOrderStmt->execute([$code]);
        $foundOrderId = $openOrderStmt->fetchColumn();
        $orderId = $foundOrderId !== false ? (int) $foundOrderId : null;

        if ($orderId !== null) {
            cancelActiveBuffetTicketItemsInTx($pdo, $orderId);
        }

        $pdo->prepare("
            UPDATE `table_menu_locks`
            SET `status` = 'closed', `closed_at` = NOW()
            WHERE `table_code` = :code AND `status` = 'locked'
        ")->execute([':code' => $code]);

        if ($targetMenuType === 'unlock') {
            if ($ownTx) {
                $pdo->commit();
            }
            return [
                'success' => true,
                'code'    => 200,
                'lock'    => null,
                'message' => 'Đã mở khóa menu Bàn ' . $code . '. Khách hàng có thể chọn lại thực đơn.',
            ];
        }

        $pkgName     = 'MENU GỌI MÓN';
        $ticketPrice = 0;
        $storedQty   = 0;

        if ($targetMenuType === 'buffet' && $package !== null) {
            if ($orderId === null) {
                $orderId = findOrCreateOpenOrderId($pdo, $code);
            }
            $pkgName     = (string) $package['name'];
            $ticketPrice = max(0, (int) round((float) $package['price_per_person']));
            $storedQty   = $ticketQty;
            $noteTag     = $managerName !== '' ? ('Đổi vé bởi ' . $managerName) : 'Đổi vé bởi Quản lý';
            upsertBuffetTicketOrderItemInTx($pdo, $orderId, $package, $ticketQty, $noteTag, $managerUserId);
        }

        $pdo->prepare("
            INSERT INTO `table_menu_locks`
                (`table_code`, `order_id`, `menu_type`, `buffet_package_id`, `package_name`, `ticket_price`, `ticket_qty`, `locked_by`, `updated_by_user_id`, `status`)
            VALUES
                (:table_code, :order_id, :menu_type, :pkg_id, :pkg_name, :ticket_price, :ticket_qty, 'manager_change', :uid, 'locked')
        ")->execute([
            ':table_code'   => $code,
            ':order_id'     => $orderId,
            ':menu_type'    => $targetMenuType,
            ':pkg_id'       => $targetMenuType === 'buffet' ? (int) $package['id'] : null,
            ':pkg_name'     => $pkgName,
            ':ticket_price' => $ticketPrice,
            ':ticket_qty'   => $storedQty,
            ':uid'          => $managerUserId > 0 ? $managerUserId : null,
        ]);

        if ($ownTx) {
            $pdo->commit();
        }

        $freshLock = getActiveTableMenuLock($pdo, $code);
        return [
            'success' => true,
            'code'    => 200,
            'lock'    => serializeTableMenuLock($freshLock),
            'message' => 'Đã đổi vé/menu Bàn ' . $code . ' sang ' . formatTableMenuLockLabel($freshLock) . '!',
        ];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
