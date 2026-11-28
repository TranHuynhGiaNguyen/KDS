<?php

/**
 * SmartDine - API Gọi Món QR Tại Bàn (dùng bởi qr-order.php)
 *
 * GET  ?action=state&qr=<token> : trả về danh sách món đã gọi của bàn, trạng thái bếp & thực đơn cập nhật.
 * POST { action: 'submit', qr: '<token>', items: [{ dish_id, qty, note }] } : gửi đơn xuống bếp thật (orders + order_items).
 * POST { action: 'call_staff', qr: '<token>', note?: '' } : gửi yêu cầu gọi nhân viên phục vụ tới bàn.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/qr-tables.php';
require_once __DIR__ . '/../includes/checkout.php';
require_once __DIR__ . '/../includes/vietqr.php';
require_once __DIR__ . '/../includes/payment-gateway.php';
require_once __DIR__ . '/../includes/table-menu.php';

header('Cache-Control: no-store');

/**
 * Xác thực bàn qua mã QR cố định (hoặc mã bàn hợp lệ nếu không kèm token).
 */
function resolveQrTableContext(PDO $pdo, string $qrToken, string $fallbackTableCode = ''): array
{
    if ($qrToken !== '') {
        $row = findTableByQrToken($pdo, $qrToken);
        if (!$row) {
            respondJson(404, ['success' => false, 'message' => 'Mã QR không tồn tại hoặc không hợp lệ.']);
        }
        if ((int) ($row['qr_active'] ?? 0) !== 1 || ($row['table_status'] ?? '') !== 'active') {
            respondJson(403, ['success' => false, 'message' => 'Bàn ' . $row['table_code'] . ' hiện đang tạm ngưng phục vụ.']);
        }
        return $row;
    }

    $cleanCode = strtoupper(trim($fallbackTableCode));
    if ($cleanCode !== '') {
        $tbl = getDiningTableByCode($cleanCode);
        if (!$tbl || ($tbl['status'] ?? '') !== 'active') {
            respondJson(403, ['success' => false, 'message' => 'Bàn ' . $cleanCode . ' không khả dụng.']);
        }
        $qrRow = getOrCreateTableQrCode($pdo, $cleanCode);
        if ($qrRow) {
            $qrRow['table_status'] = $tbl['status'];
            return $qrRow;
        }
    }

    respondJson(400, ['success' => false, 'message' => 'Thiếu mã QR hợp lệ cho bàn.']);
}

/**
 * Xây dựng dữ liệu trạng thái thực đơn + món đã gọi của bàn.
 */
function buildQrOrderStatePayload(PDO $pdo, array $tableCtx): array
{
    $tableCode = (string) $tableCtx['table_code'];
    $rawItems  = fetchTableItems($pdo, $tableCode);

    // Map ảnh & loại menu của món theo tên để hiển thị đẹp ở tab Món đã gọi
    $dishMetaStmt = $pdo->query("SELECT `id`, `name`, `image`, `menu_type`, `status` FROM `dishes`");
    $dishByName   = [];
    $dishStatuses = [];
    foreach ($dishMetaStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $key = mb_strtolower(trim((string) $d['name']), 'UTF-8');
        if (!isset($dishByName[$key])) {
            $dishByName[$key] = $d;
        }
        $dishStatuses[(int) $d['id']] = (string) $d['status'];
    }

    $orderedItems = [];
    $activeItems  = [];
    foreach ($rawItems as $item) {
        if (($item['status'] ?? '') === 'cancelled') {
            continue;
        }
        $activeItems[] = $item;
        $key  = mb_strtolower(trim((string) $item['name']), 'UTF-8');
        $meta = $dishByName[$key] ?? null;

        $orderedItems[] = [
            'id'        => (int) $item['id'],
            'name'      => (string) $item['name'],
            'price'     => (int) $item['price'],
            'qty'       => (int) $item['qty'],
            'note'      => (string) ($item['note'] ?? ''),
            'status'    => (string) ($item['status'] ?? 'pending'),
            'sent_at'   => $item['sent_at'] ? date('H:i', strtotime((string) $item['sent_at'])) : '',
            'image'     => $meta['image'] ?? '',
            'menu_type' => $meta['menu_type'] ?? ((int) $item['price'] <= 0 ? 'buffet' : 'alacarte'),
        ];
    }

    $openOrderId = fetchOpenOrderId($pdo, $tableCode);
    $discount    = ['type' => DISCOUNT_NONE, 'value' => 0, 'voucher_code' => null];
    $hasCashierDiscount = false;
    if ($openOrderId !== null) {
        ensurePaymentIntentsSchema($pdo);
        $iStmt = $pdo->prepare("SELECT `discount_type`, `discount_value`, `voucher_code`, `status` FROM `payment_intents` WHERE `order_id` = ? LIMIT 1");
        $iStmt->execute([$openOrderId]);
        $intentRow = $iStmt->fetch(PDO::FETCH_ASSOC);
        if ($intentRow && ($intentRow['status'] ?? '') === 'pending' && (($intentRow['discount_type'] ?? 'none') !== 'none' || !empty($intentRow['voucher_code']))) {
            $discount = [
                'type'         => (string) $intentRow['discount_type'],
                'value'        => (int) ($intentRow['discount_value'] ?? 0),
                'voucher_code' => !empty($intentRow['voucher_code']) ? (string) $intentRow['voucher_code'] : null,
            ];
            $hasCashierDiscount = true;
        }
    }

    $bill = [
        'subtotal'        => 0,
        'discount_amount' => 0,
        'service_fee'     => 0,
        'vat'             => 0,
        'total'           => 0,
    ];
    if ($activeItems !== []) {
        try {
            $bill = calculateBill($activeItems, $discount);
        } catch (Throwable $e) {
            error_log('qr-order state bill: ' . $e->getMessage());
            $bill = calculateBill($activeItems);
        }
    }

    if ($openOrderId !== null && (int) $bill['total'] > 0) {
        upsertPaymentIntent($pdo, $openOrderId, $tableCode, (int) $bill['total'], $hasCashierDiscount ? $discount : ['type' => DISCOUNT_NONE, 'value' => 0, 'voucher_code' => null]);
    }

    $assignment = getActiveTableAssignment($tableCode, $pdo);
    $gatewayCfg = getPaymentGatewayConfig();
    $bank       = getRestaurantInfo()['bank'];
    $menuLock   = getActiveTableMenuLock($pdo, $tableCode);

    return [
        'success'                 => true,
        'table_code'              => $tableCode,
        'qr_token'                => (string) ($tableCtx['qr_token'] ?? ''),
        'guests'                  => (int) ($assignment['guest_count'] ?? 0),
        'order_id'                => (int) ($openOrderId ?? 0),
        'menu_lock'               => serializeTableMenuLock($menuLock),
        'ordered_items'           => $orderedItems,
        'dish_statuses'           => $dishStatuses,
        'transfer_content'        => $openOrderId !== null ? formatTransferContent($openOrderId, $tableCode) : '',
        'qr_payload'              => ($openOrderId !== null && (int) $bill['total'] > 0) ? buildInvoiceQrPayload($openOrderId, $tableCode, (int) $bill['total']) : '',
        'bank'                    => $bank,
        'auto_confirm_configured' => ($gatewayCfg['webhook_secret'] ?? '') !== '',
        'bill'                    => [
            'subtotal'        => (int) $bill['subtotal'],
            'discount_amount' => (int) ($bill['discount_amount'] ?? 0),
            'service_fee'     => (int) $bill['service_fee'],
            'vat'             => (int) $bill['vat'],
            'total'           => (int) $bill['total'],
        ],
    ];
}

try {
    $pdo    = getOrdersDb();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $action   = trim((string) ($_GET['action'] ?? 'state'));
        $qrToken  = trim((string) ($_GET['qr'] ?? ''));
        $tblCode  = trim((string) ($_GET['table'] ?? ''));
        $tableCtx = resolveQrTableContext($pdo, $qrToken, $tblCode);

        if ($action !== 'state' && $action !== 'orders') {
            respondJson(400, ['success' => false, 'message' => 'Hành động không hợp lệ.']);
        }

        respondJson(200, buildQrOrderStatePayload($pdo, $tableCtx));
    }

    if ($method !== 'POST') {
        respondJson(405, ['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
    }

    $raw     = file_get_contents('php://input');
    $decoded = json_decode((string) $raw, true);
    $payload = is_array($decoded) ? $decoded : $_POST;

    $action   = trim((string) ($payload['action'] ?? ''));
    $qrToken  = trim((string) ($payload['qr'] ?? ''));
    $tblCode  = trim((string) ($payload['table'] ?? ''));
    $tableCtx = resolveQrTableContext($pdo, $qrToken, $tblCode);
    $tableCode = (string) $tableCtx['table_code'];

    if ($action === 'call_staff') {
        $note = trim((string) ($payload['note'] ?? ''));
        $res  = createTableStaffCall($pdo, $tableCode, $note);
        respondJson($res['success'] ? 200 : 422, $res);
    }

    if ($action === 'select_menu') {
        $menuType  = trim((string) ($payload['menu_type'] ?? ''));
        $packageId = (int) ($payload['buffet_package_id'] ?? $payload['package_id'] ?? 0);
        $rawQty    = (int) ($payload['ticket_qty'] ?? 0);
        if ($rawQty <= 0) {
            $assign = getActiveTableAssignment($tableCode, $pdo);
            $rawQty = max(1, (int) ($assign['guest_count'] ?? 1));
        }

        $lockRes = lockTableMenuByCustomer($pdo, $tableCode, $menuType, $packageId, $rawQty);
        $state   = buildQrOrderStatePayload($pdo, $tableCtx);
        $state['success'] = !empty($lockRes['success']);
        $state['locked']  = !empty($lockRes['locked']) || !empty($lockRes['success']);
        $state['message'] = (string) ($lockRes['message'] ?? '');
        respondJson((int) ($lockRes['code'] ?? 200), $state);
    }

    if ($action === 'submit') {
        $items = $payload['items'] ?? [];
        if (!is_array($items) || $items === []) {
            respondJson(422, ['success' => false, 'message' => 'Giỏ đồ ăn đang trống, vui lòng chọn ít nhất 1 món.']);
        }
        if (count($items) > 50) {
            respondJson(422, ['success' => false, 'message' => 'Tối đa 50 dòng món mỗi lần gửi.']);
        }

        $orderNote = mb_substr(trim((string) ($payload['order_note'] ?? '')), 0, 120, 'UTF-8');
        ensureTableMenuLocksSchema($pdo);

        $pdo->beginTransaction();
        try {
            // Đánh dấu phiên QR đang mở (tương thích checkoutTable)
            try {
                $hasQrSessions = $pdo->query("SHOW TABLES LIKE 'qr_table_sessions'")->fetchColumn();
                if ($hasQrSessions) {
                    $pdo->prepare("
                        INSERT INTO `qr_table_sessions` (`table_code`, `status`, `active_slot`)
                        VALUES (?, 'open', 1)
                        ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)
                    ")->execute([$tableCode]);
                }
            } catch (PDOException $e) {
                // Bỏ qua nếu bảng qr_table_sessions không khả dụng
            }

            $addedIds = [];
            foreach ($items as $line) {
                if (!is_array($line)) {
                    throw new OrderException('Dữ liệu món không hợp lệ.');
                }
                $dishId   = (int) ($line['dish_id'] ?? 0);
                $qty      = (int) ($line['qty'] ?? 0);
                $itemNote = trim((string) ($line['note'] ?? ''));

                if ($dishId <= 0) {
                    throw new OrderException('Mã món ăn không hợp lệ.');
                }

                $combinedParts = array_filter([
                    $itemNote,
                    $orderNote,
                ], static fn(string $v): bool => $v !== '');
                $finalNote = $combinedParts ? implode(' · ', $combinedParts) : 'Khách gọi QR';

                $addedIds[] = addOrderItem($pdo, $tableCode, $dishId, $qty, $finalNote, null);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $state = buildQrOrderStatePayload($pdo, $tableCtx);
        $state['added_count'] = count($addedIds);
        $state['message']     = 'Đã gửi đơn xuống bếp thành công cho Bàn ' . $tableCode . '!';
        respondJson(200, $state);
    }

    respondJson(400, ['success' => false, 'message' => 'Thao tác không hợp lệ (chỉ hỗ trợ select_menu, submit, call_staff).']);
} catch (OrderException $e) {
    respondJson(422, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('api/qr-order error: ' . $e->getMessage());
    respondJson(500, ['success' => false, 'message' => 'Lỗi hệ thống khi xử lý gọi món QR, vui lòng thử lại.']);
}
