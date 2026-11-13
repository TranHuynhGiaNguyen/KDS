<?php
declare(strict_types=1);

/**
 * SmartDine - Người 2: QR Code & Ordering
 *
 * - Xác thực QR và nhận diện bàn.
 * - Tách giỏ theo lượt dùng bàn.
 * - Đọc menu, mô tả và topping từ MySQL.
 * - Quản lý giỏ hàng, đơn hàng và chi tiết món.
 * - Chống gửi đơn trùng.
 * - Phân quyền admin, phục vụ và bếp.
 */

require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================================
 * PHẢN HỒI VÀ KIỂM TRA DỮ LIỆU
 * ========================================================= */

function qoReply(int $status, array $data): void
{
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    exit;
}

function qoFail(int $status, string $message): void
{
    throw new RuntimeException($message, $status);
}

function qoDb(): PDO
{
    $pdo = getDbConnection();

    if (!$pdo) {
        qoFail(503, 'Không kết nối được MySQL.');
    }

    return $pdo;
}

function qoText($value, int $max): string
{
    if (
        !is_string($value) ||
        preg_match('//u', $value) !== 1
    ) {
        qoFail(422, 'Nội dung văn bản không hợp lệ.');
    }

    $value = trim($value);

    if (preg_match_all('/./us', $value) > $max) {
        qoFail(422, "Nội dung vượt quá $max ký tự.");
    }

    return $value;
}

function qoInt(
    $value,
    int $min = 1,
    int $max = PHP_INT_MAX
): int {
    if (
        !is_int($value) ||
        $value < $min ||
        $value > $max
    ) {
        qoFail(422, 'Mã dữ liệu hoặc số lượng không hợp lệ.');
    }

    return $value;
}

function qoMoney(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

/* =========================================================
 * MENU VÀ TOPPING
 * ========================================================= */

function qoToppings(PDO $pdo, ?int $menuId = null): array
{
    if ($menuId === null) {
        $stmt = $pdo->query(
            'SELECT name, price
             FROM qr_toppings
             WHERE is_available = 1
             ORDER BY name'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT t.name, t.price
             FROM qr_toppings t
             JOIN qr_menu_toppings mt
                ON mt.topping_id = t.id
             WHERE mt.menu_item_id = ?
               AND t.is_available = 1
             ORDER BY t.name
             LOCK IN SHARE MODE'
        );

        $stmt->execute([$menuId]);
    }

    $prices = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((float) $row['price'] < 0) {
            qoFail(422, 'Giá topping không hợp lệ.');
        }

        $prices[$row['name']] = (float) $row['price'];
    }

    return $prices;
}

function qoMenu(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT
            m.id,
            m.name,
            m.price,
            m.is_available,
            COALESCE(d.category, 'main') AS category,
            COALESCE(d.description, '') AS description,
            COALESCE(d.image_url, '') AS image_url
         FROM qr_menu_items m
         LEFT JOIN qr_menu_details d
            ON d.menu_item_id = m.id
         ORDER BY m.id"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['price'] = (float) $row['price'];
        $row['is_available'] = (bool) $row['is_available'];
        $row['toppings'] = [];

        foreach (
            qoToppings($pdo, $row['id']) as $name => $price
        ) {
            $row['toppings'][] = [
                'name' => $name,
                'price' => $price,
            ];
        }
    }

    unset($row);

    return $rows;
}

/* =========================================================
 * KIỂM TRA TÀI KHOẢN NHÂN VIÊN
 * ========================================================= */

function qoRole(PDO $pdo): string
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT role
         FROM users
         WHERE id = ?
           AND status = 'active'"
    );

    $stmt->execute([$userId]);

    $role = $stmt->fetchColumn();

    if (!in_array($role, ['admin', 'staff', 'kitchen'], true)) {
        qoFail(
            403,
            'Bạn cần đăng nhập bằng tài khoản nhân viên đang hoạt động.'
        );
    }

    return $role;
}

/* =========================================================
 * QUÉT QR VÀ MỞ LƯỢT DÙNG BÀN
 * ========================================================= */

function qoEnter(PDO $pdo, string $token): string
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        qoFail(400, 'Mã QR không hợp lệ.');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT id, table_code, qr_token
             FROM qr_table_codes
             WHERE qr_token = ?
               AND is_active = 1
             FOR UPDATE'
        );

        $stmt->execute([$token]);

        $table = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$table) {
            qoFail(404, 'QR không tồn tại hoặc đã bị khóa.');
        }

        /*
         * Mỗi bàn chỉ có một lượt đang mở.
         * Cần UNIQUE(table_code, active_slot).
         */
        $stmt = $pdo->prepare(
            "INSERT INTO qr_table_sessions
                (table_code, status, active_slot)
             VALUES (?, 'open', 1)
             ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id)"
        );

        $stmt->execute([$table['table_code']]);

        $sessionId = (int) $pdo->lastInsertId();

        /*
         * Mỗi lượt dùng bàn có một giỏ.
         * Cần UNIQUE(session_id) trong qr_carts.
         */
        $stmt = $pdo->prepare(
            'INSERT INTO qr_carts (session_id)
             VALUES (?)
             ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id)'
        );

        $stmt->execute([$sessionId]);

        $pdo->commit();

        /*
         * Mỗi lần mở QR có ngữ cảnh riêng.
         * Mở B04 không ghi đè ngữ cảnh B02.
         */
        $context = bin2hex(random_bytes(24));

        $_SESSION['qo_contexts'][$context] = [
            'table_id' => (int) $table['id'],
            'session_id' => $sessionId,
            'token' => $token,
        ];

        return $context;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/* =========================================================
 * XÁC THỰC NGỮ CẢNH BÀN
 * ========================================================= */

function qoContext(
    PDO $pdo,
    string $context,
    bool $write = false
): array {
    $grant = $_SESSION['qo_contexts'][$context] ?? null;

    if (!is_array($grant)) {
        qoFail(
            403,
            'Phiên QR không hợp lệ. Vui lòng mở lại QR của bàn.'
        );
    }

    $lock = $pdo->inTransaction() ? ' FOR UPDATE' : '';

    $stmt = $pdo->prepare(
        'SELECT id, table_code, qr_token, is_active
         FROM qr_table_codes
         WHERE id = ?' . $lock
    );

    $stmt->execute([$grant['table_id']]);

    $table = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$table ||
        !(int) $table['is_active'] ||
        !hash_equals($table['qr_token'], $grant['token'])
    ) {
        qoFail(403, 'QR của bàn không còn hoạt động.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, status, opened_at
         FROM qr_table_sessions
         WHERE id = ?
           AND table_code = ?' . $lock
    );

    $stmt->execute([
        $grant['session_id'],
        $table['table_code'],
    ]);

    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        qoFail(403, 'Không tìm thấy lượt dùng bàn.');
    }

    if ($write && $session['status'] !== 'open') {
        qoFail(
            409,
            'Lượt dùng bàn đã đóng. Vui lòng mở lại QR.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM qr_carts
         WHERE session_id = ?' . $lock
    );

    $stmt->execute([$session['id']]);

    $cartId = (int) $stmt->fetchColumn();

    if (!$cartId) {
        qoFail(
            409,
            'Không tìm thấy giỏ hàng của lượt dùng bàn.'
        );
    }

    return [
        'table_code' => $table['table_code'],
        'session_id' => (int) $session['id'],
        'session_status' => $session['status'],
        'opened_at' => $session['opened_at'],
        'cart_id' => $cartId,
    ];
}

/* =========================================================
 * ĐỌC GIỎ HÀNG
 * ========================================================= */

function qoCart(PDO $pdo, int $cartId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            ci.id,
            ci.menu_item_id,
            ci.quantity,
            ci.unit_price,
            ci.toppings_json,
            ci.note,
            m.name
         FROM qr_cart_items ci
         JOIN qr_menu_items m
            ON m.id = ci.menu_item_id
         WHERE ci.cart_id = ?
         ORDER BY ci.id'
    );

    $stmt->execute([$cartId]);

    $items = [];
    $subtotal = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cents = (int) round(
            (float) $row['unit_price'] * 100
        );

        $item = [
            'id' => (int) $row['id'],
            'menu_item_id' => (int) $row['menu_item_id'],
            'name' => $row['name'],
            'qty' => (int) $row['quantity'],
            'price' => $cents / 100,
            'note' => $row['note'] ?? '',
            'toppings' => json_decode(
                $row['toppings_json'] ?? '[]',
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
        ];

        $items[] = $item;

        $subtotal += $cents * $item['qty'];
    }

    return [
        'items' => $items,
        'subtotal' => $subtotal / 100,
        'revision' => hash(
            'sha256',
            json_encode($items, JSON_THROW_ON_ERROR)
        ),
    ];
}

function qoState(PDO $pdo, array $context): array
{
    return [
        'context' => $context,
        'menu' => qoMenu($pdo),
        'toppings' => qoToppings($pdo),
        'cart' => qoCart($pdo, $context['cart_id']),
    ];
}

/* =========================================================
 * ĐỌC ĐƠN HÀNG VÀ CHI TIẾT MÓN
 * ========================================================= */

function qoOrders(
    PDO $pdo,
    string $where,
    array $params
): array {
    $stmt = $pdo->prepare(
        'SELECT
            o.*,
            s.table_code,
            s.status AS session_status
         FROM qr_orders o
         JOIN qr_table_sessions s
            ON s.id = o.session_id
         WHERE ' . $where . '
         ORDER BY o.id DESC'
    );

    $stmt->execute($params);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itemQuery = $pdo->prepare(
        'SELECT *
         FROM qr_order_items
         WHERE order_id = ?
         ORDER BY id'
    );

    $toppingQuery = $pdo->prepare(
        'SELECT
            topping_name AS name,
            topping_price AS price
         FROM qr_order_item_toppings
         WHERE order_item_id = ?
         ORDER BY id'
    );

    foreach ($orders as &$order) {
        $order['id'] = (int) $order['id'];
        $order['subtotal'] = (float) $order['subtotal'];

        $itemQuery->execute([$order['id']]);

        $order['items'] = $itemQuery->fetchAll(
            PDO::FETCH_ASSOC
        );

        foreach ($order['items'] as &$item) {
            $item['id'] = (int) $item['id'];
            $item['quantity'] = (int) $item['quantity'];
            $item['unit_price'] = (float) $item['unit_price'];
            $item['line_total'] = (float) $item['line_total'];

            $toppingQuery->execute([$item['id']]);

            $item['toppings'] = $toppingQuery->fetchAll(
                PDO::FETCH_ASSOC
            );
        }

        unset($item);
    }

    unset($order);

    return $orders;
}

/* =========================================================
 * XÁC NHẬN VÀ LƯU ĐƠN HÀNG
 * ========================================================= */

function qoSubmit(
    PDO $pdo,
    array $context,
    array $data
): array {
    $requestId = $data['request_id'] ?? '';

    if (
        !is_string($requestId) ||
        !preg_match('/^[a-f0-9]{32}$/', $requestId)
    ) {
        qoFail(422, 'Mã gửi đơn không hợp lệ.');
    }

    $revision = qoText($data['revision'] ?? '', 64);
    $note = qoText($data['order_note'] ?? '', 1000);

    $key = hash(
        'sha256',
        $context['session_id'] . ':' . $requestId
    );

    $payloadHash = hash(
        'sha256',
        json_encode(
            [$revision, $note],
            JSON_THROW_ON_ERROR
        )
    );

    /*
     * Kiểm tra lần gửi trước trước khi kiểm tra giỏ.
     * Nếu đơn đã được lưu, trả lại đơn cũ.
     */
    $stmt = $pdo->prepare(
        'SELECT r.payload_hash, o.id, o.order_code
         FROM qr_order_requests r
         JOIN qr_orders o
            ON o.id = r.order_id
         WHERE r.request_key = ?'
    );

    $stmt->execute([$key]);

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if (!hash_equals(
            $existing['payload_hash'],
            $payloadHash
        )) {
            qoFail(
                409,
                'Mã gửi đơn đã được dùng với nội dung khác.'
            );
        }

        return [
            'order_id' => (int) $existing['id'],
            'order_code' => $existing['order_code'],
            'replayed' => true,
        ];
    }

    if ($context['session_status'] !== 'open') {
        qoFail(409, 'Lượt dùng bàn đã đóng.');
    }

    $cart = qoCart($pdo, $context['cart_id']);

    if (!hash_equals($cart['revision'], $revision)) {
        qoFail(
            409,
            'Giỏ đã thay đổi. Đọc lại giỏ trước khi gửi.'
        );
    }

    if (!$cart['items']) {
        qoFail(422, 'Giỏ hàng đang trống.');
    }

    $subtotal = 0;
    $prepared = [];

    $menuQuery = $pdo->prepare(
        'SELECT name, price, is_available
         FROM qr_menu_items
         WHERE id = ?
         LOCK IN SHARE MODE'
    );

    foreach ($cart['items'] as $item) {
        $prices = qoToppings(
            $pdo,
            $item['menu_item_id']
        );

        $menuQuery->execute([$item['menu_item_id']]);

        $menu = $menuQuery->fetch(PDO::FETCH_ASSOC);

        if (!$menu || !(int) $menu['is_available']) {
            qoFail(
                422,
                'Món "' . $item['name'] . '" đã ngừng bán.'
            );
        }

        $base = (int) round(
            (float) $menu['price'] * 100
        );

        if ($base < 0) {
            qoFail(422, 'Giá món không hợp lệ.');
        }

        $unit = $base;

        foreach ($item['toppings'] as $topping) {
            $name = $topping['name'] ?? '';

            if (!array_key_exists($name, $prices)) {
                qoFail(
                    422,
                    'Topping không còn được cung cấp.'
                );
            }

            $unit += (int) round($prices[$name] * 100);
        }

        if (
            $unit !==
            (int) round($item['price'] * 100)
        ) {
            qoFail(
                422,
                'Giá món đã thay đổi. Hãy xóa và chọn lại món "' .
                $item['name'] . '".'
            );
        }

        $line = $unit * $item['qty'];
        $subtotal += $line;

        if (
            $line > 999999999999 ||
            $subtotal > 999999999999
        ) {
            qoFail(
                422,
                'Giá trị đơn vượt giới hạn database.'
            );
        }

        $prepared[] = [
            $item,
            $menu['name'],
            $base,
            $unit,
            $line,
            $prices,
        ];
    }

    $orderCode = 'QR-' .
        strtoupper(bin2hex(random_bytes(12)));

    $stmt = $pdo->prepare(
        "INSERT INTO qr_orders
            (
                session_id,
                order_code,
                status,
                order_note,
                subtotal
            )
         VALUES (?, ?, 'submitted', ?, ?)"
    );

    $stmt->execute([
        $context['session_id'],
        $orderCode,
        $note,
        qoMoney($subtotal),
    ]);

    $orderId = (int) $pdo->lastInsertId();

    $insertItem = $pdo->prepare(
        "INSERT INTO qr_order_items
            (
                order_id,
                menu_item_id,
                item_name,
                quantity,
                base_price,
                unit_price,
                line_total,
                note,
                status
            )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );

    $insertTopping = $pdo->prepare(
        'INSERT INTO qr_order_item_toppings
            (
                order_item_id,
                topping_name,
                topping_price
            )
         VALUES (?, ?, ?)'
    );

    foreach (
        $prepared as [
            $item,
            $name,
            $base,
            $unit,
            $line,
            $prices
        ]
    ) {
        $insertItem->execute([
            $orderId,
            $item['menu_item_id'],
            $name,
            $item['qty'],
            qoMoney($base),
            qoMoney($unit),
            qoMoney($line),
            $item['note'],
        ]);

        $orderItemId = (int) $pdo->lastInsertId();

        foreach ($item['toppings'] as $topping) {
            $toppingName = $topping['name'];

            $insertTopping->execute([
                $orderItemId,
                $toppingName,
                $prices[$toppingName],
            ]);
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO qr_order_requests
            (request_key, payload_hash, order_id)
         VALUES (?, ?, ?)'
    );

    $stmt->execute([
        $key,
        $payloadHash,
        $orderId,
    ]);

    /*
     * Chỉ xóa giỏ sau khi đã lưu đủ đơn và chi tiết.
     * qoApi() sẽ commit toàn bộ transaction.
     */
    $stmt = $pdo->prepare(
        'DELETE FROM qr_cart_items
         WHERE cart_id = ?'
    );

    $stmt->execute([$context['cart_id']]);

    return [
        'order_id' => $orderId,
        'order_code' => $orderCode,
        'replayed' => false,
    ];
}

/* =========================================================
 * CẬP NHẬT TRẠNG THÁI MÓN THEO VAI TRÒ
 * ========================================================= */

function qoChangeStatus(
    PDO $pdo,
    string $role,
    array $data
): void {
    $itemId = qoInt($data['item_id'] ?? null);

    $stmt = $pdo->prepare(
        'SELECT order_id
         FROM qr_order_items
         WHERE id = ?'
    );

    $stmt->execute([$itemId]);

    $orderId = (int) $stmt->fetchColumn();

    if (!$orderId) {
        qoFail(404, 'Không tìm thấy món trong đơn.');
    }

    /*
     * Khóa đơn và lượt dùng bàn trước khi đổi trạng thái.
     */
    $stmt = $pdo->prepare(
        'SELECT
            o.id,
            s.status AS session_status
         FROM qr_orders o
         JOIN qr_table_sessions s
            ON s.id = o.session_id
         WHERE o.id = ?
         FOR UPDATE'
    );

    $stmt->execute([$orderId]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$order ||
        $order['session_status'] !== 'open'
    ) {
        qoFail(409, 'Lượt dùng bàn đã đóng.');
    }

    $stmt = $pdo->prepare(
        'SELECT status
         FROM qr_order_items
         WHERE id = ?
         FOR UPDATE'
    );

    $stmt->execute([$itemId]);

    $old = $stmt->fetchColumn();

    if (($data['expected_status'] ?? null) !== $old) {
        qoFail(
            409,
            'Món đã được cập nhật ở thiết bị khác.'
        );
    }

    $next = $data['status'] ?? '';

    $allowed =
        (
            in_array($role, ['admin', 'kitchen'], true) &&
            (
                (
                    $old === 'pending' &&
                    $next === 'preparing'
                ) ||
                (
                    $old === 'preparing' &&
                    $next === 'ready'
                )
            )
        )
        ||
        (
            in_array($role, ['admin', 'staff'], true) &&
            $old === 'ready' &&
            $next === 'served'
        )
        ||
        (
            in_array($role, ['admin', 'staff'], true) &&
            $old === 'pending' &&
            $next === 'cancelled'
        );

    if (!$allowed) {
        qoFail(
            403,
            'Bạn không có quyền chuyển trạng thái này.'
        );
    }

    $stmt = $pdo->prepare(
        'UPDATE qr_order_items
         SET status = ?
         WHERE id = ?'
    );

    $stmt->execute([$next, $itemId]);

    $stmt = $pdo->prepare(
        'SELECT status, line_total
         FROM qr_order_items
         WHERE order_id = ?'
    );

    $stmt->execute([$orderId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $active = [];
    $subtotal = 0;

    foreach ($rows as $row) {
        if ($row['status'] !== 'cancelled') {
            $active[] = $row['status'];

            $subtotal += (int) round(
                (float) $row['line_total'] * 100
            );
        }
    }

    if (!$active) {
        $orderStatus = 'cancelled';

    } elseif (
        count(array_unique($active)) === 1 &&
        $active[0] === 'pending'
    ) {
        $orderStatus = 'submitted';

    } elseif (
        in_array('pending', $active, true) ||
        in_array('preparing', $active, true)
    ) {
        $orderStatus = 'preparing';

    } elseif (in_array('ready', $active, true)) {
        $orderStatus = 'ready';

    } else {
        $orderStatus = 'served';
    }

    $stmt = $pdo->prepare(
        'UPDATE qr_orders
         SET status = ?, subtotal = ?
         WHERE id = ?'
    );

    $stmt->execute([
        $orderStatus,
        qoMoney($subtotal),
        $orderId,
    ]);
}

/* =========================================================
 * API
 * ========================================================= */

function qoApi(): void
{
    $pdo = null;

    try {
        $method = $_SERVER['REQUEST_METHOD'];

        if (!in_array($method, ['GET', 'POST'], true)) {
            qoFail(
                405,
                'Phương thức không được hỗ trợ.'
            );
        }

        $data = [];

        if ($method === 'POST') {
            if (
                stripos(
                    $_SERVER['CONTENT_TYPE'] ?? '',
                    'application/json'
                ) !== 0
            ) {
                qoFail(415, 'Yêu cầu dữ liệu JSON.');
            }

            $raw = file_get_contents(
                'php://input',
                false,
                null,
                0,
                16385
            );

            if (
                $raw === false ||
                strlen($raw) > 16384
            ) {
                qoFail(413, 'Dữ liệu quá lớn.');
            }

            try {
                $data = json_decode(
                    $raw,
                    true,
                    16,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException $e) {
                qoFail(400, 'JSON không hợp lệ.');
            }

            if (
                !is_array($data) ||
                array_is_list($data)
            ) {
                qoFail(400, 'Dữ liệu không hợp lệ.');
            }

            if (
                !is_string($data['csrf'] ?? null) ||
                !hash_equals(
                    $_SESSION['qo_csrf'] ?? '',
                    $data['csrf']
                ) ||
                empty($_SESSION['qo_csrf'])
            ) {
                qoFail(
                    403,
                    'Phiên xác thực hết hạn. Hãy tải lại trang.'
                );
            }
        }

        $action = $_GET['api'] ?? '';

        $reads = [
            'cart',
            'orders',
            'queue',
        ];

        $writes = [
            'add',
            'quantity',
            'remove',
            'clear',
            'submit',
            'status',
        ];

        if (
            !is_string($action) ||
            (
                $method === 'GET' &&
                !in_array($action, $reads, true)
            ) ||
            (
                $method === 'POST' &&
                !in_array($action, $writes, true)
            )
        ) {
            qoFail(
                405,
                'Thao tác hoặc phương thức không hợp lệ.'
            );
        }

        $pdo = qoDb();

        /*
         * API dành cho admin, phục vụ và bếp.
         */
        if (in_array($action, ['queue', 'status'], true)) {
            $role = qoRole($pdo);

            $pdo->beginTransaction();

            if ($action === 'status') {
                qoChangeStatus($pdo, $role, $data);
            }

            $where = $role === 'admin'
                ? '1 = 1'
                : "s.status = 'open'";

            $orders = qoOrders($pdo, $where, []);

            $pdo->commit();

            qoReply(200, [
                'success' => true,
                'role' => $role,
                'orders' => $orders,
            ]);
        }

        /*
         * API dành cho khách tại bàn.
         */
        $contextKey = $_GET['ctx'] ?? '';

        if (!is_string($contextKey)) {
            qoFail(
                403,
                'Ngữ cảnh bàn không hợp lệ.'
            );
        }

        $pdo->beginTransaction();

        /*
         * Với submit, qoSubmit() tự kiểm tra phiên đóng
         * sau khi đã xác minh yêu cầu cũ.
         */
        $context = qoContext(
            $pdo,
            $contextKey,
            $method === 'POST' && $action !== 'submit'
        );

        if ($action === 'orders') {
            $orders = qoOrders(
                $pdo,
                'o.session_id = ?',
                [$context['session_id']]
            );

            $pdo->commit();

            qoReply(200, [
                'success' => true,
                'orders' => $orders,
                'context' => $context,
            ]);
        }

        $result = null;

        if ($action === 'submit') {
            $result = qoSubmit(
                $pdo,
                $context,
                $data
            );

        } elseif (
            in_array(
                $action,
                ['add', 'quantity', 'remove', 'clear'],
                true
            )
        ) {
            $cart = qoCart(
                $pdo,
                $context['cart_id']
            );

            if (
                !is_string($data['revision'] ?? null) ||
                !hash_equals(
                    $cart['revision'],
                    $data['revision']
                )
            ) {
                qoFail(
                    409,
                    'Giỏ vừa thay đổi ở tab hoặc thiết bị khác.'
                );
            }

            if ($action === 'clear') {
                $stmt = $pdo->prepare(
                    'DELETE FROM qr_cart_items
                     WHERE cart_id = ?'
                );

                $stmt->execute([
                    $context['cart_id'],
                ]);

            } elseif ($action === 'add') {
                $menuId = qoInt(
                    $data['menu_item_id'] ?? null
                );

                $quantity = qoInt(
                    $data['quantity'] ?? null,
                    1,
                    99
                );

                $note = qoText(
                    $data['note'] ?? '',
                    500
                );

                $names = $data['toppings'] ?? [];

                if (
                    !is_array($names) ||
                    !array_is_list($names) ||
                    count($names) > 20
                ) {
                    qoFail(
                        422,
                        'Topping không hợp lệ.'
                    );
                }

                $prices = qoToppings(
                    $pdo,
                    $menuId
                );

                foreach ($names as $name) {
                    if (
                        !is_string($name) ||
                        !array_key_exists($name, $prices)
                    ) {
                        qoFail(
                            422,
                            'Topping không được cung cấp cho món này.'
                        );
                    }
                }

                if (
                    count(array_unique($names)) !==
                    count($names)
                ) {
                    qoFail(422, 'Topping bị lặp.');
                }

                sort($names, SORT_STRING);

                $stmt = $pdo->prepare(
                    'SELECT price
                     FROM qr_menu_items
                     WHERE id = ?
                       AND is_available = 1
                     LOCK IN SHARE MODE'
                );

                $stmt->execute([$menuId]);

                $price = $stmt->fetchColumn();

                if ($price === false) {
                    qoFail(
                        422,
                        'Món không tồn tại hoặc đã ngừng bán.'
                    );
                }

                $cents = (int) round(
                    (float) $price * 100
                );

                if ($cents < 0) {
                    qoFail(
                        422,
                        'Giá món không hợp lệ.'
                    );
                }

                $toppings = [];

                foreach ($names as $name) {
                    $cents += (int) round(
                        $prices[$name] * 100
                    );

                    $toppings[] = [
                        'name' => $name,
                        'price' => $prices[$name],
                    ];
                }

                if ($cents > 999999999999) {
                    qoFail(
                        422,
                        'Giá món vượt giới hạn database.'
                    );
                }

                $found = null;

                foreach ($cart['items'] as $item) {
                    if (
                        $item['menu_item_id'] === $menuId &&
                        $item['note'] === $note &&
                        $item['toppings'] == $toppings &&
                        (int) round($item['price'] * 100)
                            === $cents
                    ) {
                        $found = $item;
                        break;
                    }
                }

                if ($found) {
                    $quantity += $found['qty'];

                    if ($quantity > 99) {
                        qoFail(
                            422,
                            'Một dòng món tối đa 99 phần.'
                        );
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE qr_cart_items
                         SET quantity = ?
                         WHERE id = ?
                           AND cart_id = ?'
                    );

                    $stmt->execute([
                        $quantity,
                        $found['id'],
                        $context['cart_id'],
                    ]);

                } else {
                    if (count($cart['items']) >= 100) {
                        qoFail(
                            422,
                            'Giỏ tối đa 100 dòng món.'
                        );
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO qr_cart_items
                            (
                                cart_id,
                                menu_item_id,
                                quantity,
                                unit_price,
                                toppings_json,
                                note
                            )
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );

                    $stmt->execute([
                        $context['cart_id'],
                        $menuId,
                        $quantity,
                        qoMoney($cents),
                        json_encode(
                            $toppings,
                            JSON_UNESCAPED_UNICODE |
                            JSON_THROW_ON_ERROR
                        ),
                        $note,
                    ]);
                }

            } else {
                $itemId = qoInt(
                    $data['item_id'] ?? null
                );

                $found = array_filter(
                    $cart['items'],
                    static fn($item) =>
                        $item['id'] === $itemId
                );

                if (!$found) {
                    qoFail(
                        404,
                        'Món không thuộc giỏ của bàn này.'
                    );
                }

                if ($action === 'remove') {
                    $stmt = $pdo->prepare(
                        'DELETE FROM qr_cart_items
                         WHERE id = ?
                           AND cart_id = ?'
                    );

                    $stmt->execute([
                        $itemId,
                        $context['cart_id'],
                    ]);

                } else {
                    $quantity = qoInt(
                        $data['quantity'] ?? null,
                        1,
                        99
                    );

                    $stmt = $pdo->prepare(
                        'UPDATE qr_cart_items
                         SET quantity = ?
                         WHERE id = ?
                           AND cart_id = ?'
                    );

                    $stmt->execute([
                        $quantity,
                        $itemId,
                        $context['cart_id'],
                    ]);
                }
            }
        }

        $state = qoState($pdo, $context);

        $pdo->commit();

        qoReply(200, [
            'success' => true,
            'state' => $state,
            'result' => $result,
        ]);

    } catch (Throwable $e) {
        if (
            $pdo instanceof PDO &&
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        $status = $e instanceof RuntimeException
            ? $e->getCode()
            : 0;

        $expectedStatuses = [
            400,
            403,
            404,
            405,
            409,
            413,
            415,
            422,
            503,
        ];

        if (!in_array($status, $expectedStatuses, true)) {
            error_log(
                'QR Ordering: ' . $e->getMessage()
            );

            qoReply(500, [
                'success' => false,
                'message' =>
                    'Không xử lý được dữ liệu. Kiểm tra Apache error.log.',
            ]);
        }

        qoReply($status, [
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
}