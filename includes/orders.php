<?php

/**
 * SmartDine - Đơn hàng và trạng thái từng món.
 *
 * Luồng của một món:
 *   pending (Chờ tiếp nhận) -> done (Đã nấu xong)
 *   - Bếp bấm "In phiếu bếp": pending -> done (không qua bước đang nấu, không in vật lý, biến khỏi màn hình bếp).
 *
 * Phân quyền thao tác món trên bàn (xem ORDER_ROLE_ACTIONS):
 *   - Quản trị viên: thêm món và hủy món (dù món chưa nấu hay đã nấu xong).
 *   - Nhân viên phục vụ: chỉ thêm món, không được hủy.
 *   - Món bị hủy đánh dấu 'cancelled' (giữ lịch sử, ẩn khỏi Admin/Staff/Bếp) và không tính tiền.
 *
 * Món mới của bàn được tạo với status = 'pending' và sent_at = giờ gọi món.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/orders_schema.php';
require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/http.php';

const ORDER_TIMEZONE = 'Asia/Ho_Chi_Minh';

const ORDER_ITEM_STATUSES = [
    'unsent'    => ['label' => 'Chờ tiếp nhận', 'class' => 'pending'],
    'pending'   => ['label' => 'Chờ tiếp nhận', 'class' => 'pending'],
    'cooking'   => ['label' => 'Đang nấu',      'class' => 'cooking'],
    'done'      => ['label' => 'Đã nấu xong',   'class' => 'done'],
    'served'    => ['label' => 'Hoàn thành',    'class' => 'ready'],
    'cancelled' => ['label' => 'Đã hủy',        'class' => 'cancelled'],
];

const ORDER_ROLE_ACTIONS = [
    'admin'         => ['add', 'cancel'],
    'shift_manager' => ['add', 'cancel'],
    'staff'         => ['add'],
];

/** Trạng thái món còn hủy được: chưa nấu hoặc đã nấu xong (Admin được hủy mọi lúc khi bàn chưa thanh toán). */
const ORDER_ITEM_CANCELLABLE_STATUSES = ['unsent', 'pending', 'cooking', 'done'];

const ORDER_ITEM_MARKERS = [];

const ORDER_ACTION_BUTTONS = [
    'cancel' => ['label' => 'Hủy món', 'class' => 'btn-outline-danger'],
];

const ORDER_CANCEL_LOCKED_MESSAGE = 'Món này đã được hủy hoặc đã hoàn tất thanh toán.';

const ORDER_ADD_MAX_QTY = 20;

class OrderException extends DomainException {}

function getOrdersDb(): PDO
{
    static $initialized = false;

    $pdo = getDbConnection();
    if ($pdo === null) {
        throw new RuntimeException('Không kết nối được cơ sở dữ liệu.');
    }

    if (!$initialized) {
        initOrderTables($pdo);
        migrateLegacyItemStatuses($pdo);
        $initialized = true;
    }

    return $pdo;
}

/** Món ở trạng thái cũ "unsent" hoặc "cooking" được chuẩn hóa thành "pending" để bếp xử lý 1 bước. */
function migrateLegacyItemStatuses(PDO $pdo): void
{
    $pdo->exec("
        UPDATE `order_items`
        SET `status` = 'pending', `sent_at` = COALESCE(`sent_at`, `created_at`, NOW())
        WHERE `status` IN ('unsent', 'cooking')
    ");
}

function normalizeTableCode(?string $tableCode): string
{
    $clean = strtoupper(trim((string) $tableCode));
    return preg_match('/^[A-Z0-9_-]{1,20}$/', $clean) ? $clean : 'B05';
}

function currentTimeForDb(): string
{
    return (new DateTime('now', new DateTimeZone(ORDER_TIMEZONE)))->format('Y-m-d H:i:s');
}

function fetchTableItems(PDO $pdo, string $tableCode): array
{
    $stmt = $pdo->prepare("
        SELECT i.`id`, i.`item_name` AS `name`, i.`unit_price` AS `price`, i.`qty`, i.`note`, i.`status`, i.`sent_at`
        FROM `order_items` i
        JOIN `orders` o ON o.`id` = i.`order_id`
        WHERE o.`table_code` = ? AND o.`status` = 'open'
        ORDER BY i.`id`
    ");
    $stmt->execute([$tableCode]);

    return $stmt->fetchAll();
}

function formatInvoiceCode(int $orderId): string
{
    return 'HD' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
}

function fetchOpenOrderCreatedAt(PDO $pdo, string $tableCode): ?string
{
    $stmt = $pdo->prepare("SELECT `created_at` FROM `orders` WHERE `table_code` = ? AND `status` = 'open' ORDER BY `id` DESC LIMIT 1");
    $stmt->execute([$tableCode]);
    $createdAt = $stmt->fetchColumn();

    return $createdAt === false ? null : (string) $createdAt;
}

function findOrderItem(PDO $pdo, int $itemId): ?array
{
    $stmt = $pdo->prepare('SELECT `id`, `status` FROM `order_items` WHERE `id` = ?');
    $stmt->execute([$itemId]);

    return $stmt->fetch() ?: null;
}

function canRoleDoOrderAction(?string $role, string $action): bool
{
    return in_array($action, ORDER_ROLE_ACTIONS[$role] ?? [], true);
}

/** Thao tác bấm được trên một món: chỉ "cancel" cho quản trị viên, khi bếp chưa in phiếu. */
function availableItemActions(array $item, ?string $role): array
{
    $canCancel = canRoleDoOrderAction($role, 'cancel')
        && in_array($item['status'], ORDER_ITEM_CANCELLABLE_STATUSES, true);

    return $canCancel ? ['cancel'] : [];
}

/** Thao tác hiện nhưng bị khóa (nút mờ): không khóa nút hủy của Admin nữa. */
function lockedItemActions(array $item, ?string $role): array
{
    return [];
}

/** Món có thể gọi thêm vào bàn, gom theo danh mục (món "out_of_stock" vẫn liệt kê nhưng không chọn được). */
function fetchOrderableDishes(PDO $pdo): array
{
    return $pdo->query("
        SELECT d.`id`, d.`name`, d.`price`, d.`status`, d.`station`, d.`menu_type` AS `dish_menu_type`,
               d.`buffet_package_id`, c.`id` AS `category_id`, c.`name` AS `category`,
               c.`slug` AS `category_slug`, c.`menu_type` AS `category_menu_type`
        FROM `dishes` d
        JOIN `categories` c ON c.`id` = d.`category_id`
        WHERE d.`status` IN ('active', 'out_of_stock')
        ORDER BY c.`sort_order`, d.`id`
    ")->fetchAll();
}

/**
 * Hủy món hoặc giảm bớt số lượng món:
 * - Nếu $cancelQty === null hoặc >= qty hiện có: đánh dấu cancelled toàn bộ dòng.
 * - Nếu 1 <= $cancelQty < qty hiện có: giảm qty dòng hiện tại và tách phần đã hủy vào bản ghi cancelled.
 */
function cancelOrderItem(PDO $pdo, int $itemId, ?int $cancelQty = null): bool
{
    $placeholders = implode(', ', array_fill(0, count(ORDER_ITEM_CANCELLABLE_STATUSES), '?'));
    $ownTx = !$pdo->inTransaction();

    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        $stmt = $pdo->prepare("
            SELECT i.`id`, i.`order_id`, i.`item_name`, i.`unit_price`, i.`qty`, i.`note`, i.`status`, i.`sent_by`, i.`sent_at`
            FROM `order_items` i
            JOIN `orders` o ON o.`id` = i.`order_id`
            WHERE i.`id` = ? AND i.`status` IN ($placeholders) AND o.`status` = 'open'
            FOR UPDATE
        ");
        $stmt->execute(array_merge([$itemId], ORDER_ITEM_CANCELLABLE_STATUSES));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }

        $currentQty = max(1, (int) $row['qty']);
        $nowDb      = currentTimeForDb();

        if ($cancelQty === null || $cancelQty >= $currentQty) {
            $upd = $pdo->prepare("UPDATE `order_items` SET `status` = 'cancelled', `cancelled_at` = ? WHERE `id` = ?");
            $upd->execute([$nowDb, $itemId]);
        } else {
            if ($cancelQty < 1) {
                if ($ownTx && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return false;
            }
            $remainQty = $currentQty - $cancelQty;
            $upd = $pdo->prepare("UPDATE `order_items` SET `qty` = ? WHERE `id` = ?");
            $upd->execute([$remainQty, $itemId]);

            $ins = $pdo->prepare("
                INSERT INTO `order_items`
                    (`order_id`, `item_name`, `unit_price`, `qty`, `note`, `status`, `sent_by`, `sent_at`, `cancelled_at`)
                VALUES (?, ?, ?, ?, ?, 'cancelled', ?, ?, ?)
            ");
            $ins->execute([
                (int) $row['order_id'],
                $row['item_name'],
                (int) $row['unit_price'],
                $cancelQty,
                $row['note'],
                $row['sent_by'],
                $row['sent_at'],
                $nowDb,
            ]);
        }

        if ($ownTx) {
            $pdo->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Thêm món vào bàn: tự mở đơn mới nếu bàn chưa có đơn.
 * Nếu đang có dòng món ở trạng thái 'pending' trùng tên, đơn giá & ghi chú trong đơn mở, cộng dồn số lượng để dễ tap nhiều lần.
 *
 * @throws OrderException khi dữ liệu không hợp lệ hoặc món không còn phục vụ
 */
function addOrderItem(PDO $pdo, string $tableCode, int $dishId, int $qty, string $note, ?int $userId): int
{
    $tableCode = strtoupper(trim($tableCode));
    if (!preg_match('/^[A-Z0-9_-]{1,20}$/', $tableCode)) {
        throw new OrderException('Mã bàn không hợp lệ.');
    }

    if ($qty < 1 || $qty > ORDER_ADD_MAX_QTY) {
        throw new OrderException('Số lượng phải từ 1 đến ' . ORDER_ADD_MAX_QTY . '.');
    }

    $tableStmt = $pdo->prepare("SELECT 1 FROM `dining_tables` WHERE `code` = ? AND `status` = 'active'");
    $tableStmt->execute([$tableCode]);
    if ($tableStmt->fetchColumn() === false) {
        throw new OrderException('Bàn ' . $tableCode . ' không tồn tại hoặc đang không hoạt động.');
    }

    $dishStmt = $pdo->prepare('SELECT `name`, `price`, `status`, `menu_type`, `buffet_package_id` FROM `dishes` WHERE `id` = ?');
    $dishStmt->execute([$dishId]);
    $dish = $dishStmt->fetch();

    if (!$dish) {
        throw new OrderException('Không tìm thấy món ăn.');
    }

    if ($dish['status'] !== 'active') {
        throw new OrderException('Món "' . $dish['name'] . '" hiện tạm hết hoặc đã ngừng phục vụ.');
    }

    require_once __DIR__ . '/table-menu.php';
    $menuLock = getActiveTableMenuLock($pdo, $tableCode);
    $lockErr  = validateDishAgainstTableMenuLock($menuLock, $dish);
    if ($lockErr !== null) {
        throw new OrderException($lockErr);
    }

    $note    = mb_substr(trim($note), 0, 255);
    $noteVal = $note === '' ? null : $note;
    $sentBy  = ($userId !== null && $userId > 0) ? $userId : null;
    $ownTx   = !$pdo->inTransaction();

    if ($ownTx) {
        $pdo->beginTransaction();
    }

    try {
        $orderId = findOrCreateOpenOrderId($pdo, $tableCode);

        // Cộng dồn vào dòng đang pending nếu trùng tên, giá và ghi chú
        $findPending = $pdo->prepare("
            SELECT `id`, `qty`
            FROM `order_items`
            WHERE `order_id` = ?
              AND `item_name` = ?
              AND `unit_price` = ?
              AND COALESCE(`note`, '') = COALESCE(?, '')
              AND `status` = 'pending'
            ORDER BY `id` DESC
            LIMIT 1
            FOR UPDATE
        ");
        $findPending->execute([$orderId, $dish['name'], (int) $dish['price'], $noteVal]);
        $existingPending = $findPending->fetch(PDO::FETCH_ASSOC);

        if ($existingPending) {
            $itemId = (int) $existingPending['id'];
            $newQty = (int) $existingPending['qty'] + $qty;
            $pdo->prepare("UPDATE `order_items` SET `qty` = ?, `sent_by` = ?, `sent_at` = ? WHERE `id` = ?")
                ->execute([$newQty, $sentBy, currentTimeForDb(), $itemId]);
        } else {
            $insert = $pdo->prepare("
                INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `note`, `status`, `sent_by`, `sent_at`)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            $insert->execute([$orderId, $dish['name'], (int) $dish['price'], $qty, $noteVal, $sentBy, currentTimeForDb()]);
            $itemId = (int) $pdo->lastInsertId();
        }

        if ($ownTx) {
            $pdo->commit();
        }

        return $itemId;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function findOrCreateOpenOrderId(PDO $pdo, string $tableCode): int
{
    $stmt = $pdo->prepare("SELECT `id` FROM `orders` WHERE `table_code` = ? AND `status` = 'open' ORDER BY `id` DESC LIMIT 1");
    $stmt->execute([$tableCode]);
    $orderId = $stmt->fetchColumn();

    if ($orderId !== false) {
        return (int) $orderId;
    }

    $pdo->prepare('INSERT INTO `orders` (`table_code`) VALUES (?)')->execute([$tableCode]);

    return (int) $pdo->lastInsertId();
}

function fetchItemTicket(PDO $pdo, int $itemId): ?array
{
    $stmt = $pdo->prepare("
        SELECT i.`item_name`, i.`qty`, i.`sent_at`, o.`table_code`, u.`fullname`
        FROM `order_items` i
        JOIN `orders` o ON o.`id` = i.`order_id`
        LEFT JOIN `users` u ON u.`id` = i.`sent_by`
        WHERE i.`id` = ? AND i.`sent_at` IS NOT NULL AND i.`status` <> 'cancelled'
    ");
    $stmt->execute([$itemId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return [
        'staff' => $row['fullname'] ?? 'Không rõ',
        'table' => $row['table_code'],
        'time'  => date('H:i', strtotime($row['sent_at'])),
        'name'  => $row['item_name'],
        'qty'   => (int) $row['qty'],
    ];
}

/** Danh sách món đang phục vụ theo từng bàn mở (không gồm món đã hủy), dùng cho Admin theo dõi tập trung. */
function fetchOpenOrdersSummary(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT o.`table_code`, o.`created_at` AS `order_created_at`,
               i.`id`, i.`item_name` AS `name`, i.`qty`, i.`note`, i.`status`,
               COALESCE(i.`sent_at`, i.`created_at`, o.`created_at`) AS `item_time`
        FROM `orders` o
        JOIN `order_items` i ON i.`order_id` = o.`id`
        WHERE o.`status` = 'open' AND i.`status` <> 'cancelled'
        ORDER BY o.`table_code` ASC, i.`id` ASC
    ");

    $tables = [];
    foreach ($stmt->fetchAll() as $row) {
        $code = $row['table_code'];
        if (!isset($tables[$code])) {
            $tables[$code] = [
                'table'      => $code,
                'created_at' => $row['order_created_at'],
                'items'      => [],
            ];
        }
        $tables[$code]['items'][] = [
            'id'     => (int) $row['id'],
            'name'   => $row['name'],
            'qty'    => (int) $row['qty'],
            'note'   => (string) $row['note'],
            'status' => $row['status'],
            'time'   => date('H:i', strtotime($row['item_time'])),
        ];
    }

    return array_values($tables);
}
