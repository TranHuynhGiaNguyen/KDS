<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/tables.php';
require_once __DIR__ . '/../includes/checkout.php';

echo "=== KIỂM THỬ NGHIỆP VỤ GÁN BÀN & CHUYỂN BÀN ===\n";

$pdo = getOrdersDb();
ensureTableAssignmentsSchema($pdo);

// Tìm 2 bàn trống đang hoạt động để test an toàn, và dọn sạch sau khi test
$allActive = getAllDiningTables('', 'active', '');
$emptyTables = array_values(array_filter($allActive, static fn(array $t): bool => empty($t['is_occupied'])));
assert(count($emptyTables) >= 2, 'Cần ít nhất 2 bàn trống active để kiểm thử');

$tableA = $emptyTables[0]['code'];
$tableB = $emptyTables[1]['code'];

$createdOrderIds = [];

try {
    // 1. Kiểm tra validate gán bàn
    $badGuests = assignTable($pdo, ['table_code' => $tableA, 'guest_count' => 0]);
    assert($badGuests['success'] === false, 'Phải chặn số lượng khách = 0');
    echo "  [PASS] Chặn số lượng khách không hợp lệ\n";

    // 2. Gán bàn A cho 4 khách
    $resAssign = assignTable($pdo, [
        'table_code'    => $tableA,
        'guest_count'   => 4,
        'customer_name' => 'Anh Khoa (Test)',
        'staff_id'      => 1,
        'note'          => 'Gần cửa sổ',
    ]);
    assert($resAssign['success'] === true, 'Gán bàn A phải thành công: ' . ($resAssign['message'] ?? ''));

    $assignRow = getActiveTableAssignment($tableA, $pdo);
    assert($assignRow !== null && (int)$assignRow['guest_count'] === 4, 'Phải lưu đúng 4 khách trong DB');

    // Kiểm tra getAllDiningTables() nhận diện bàn đã gán là is_occupied=true
    $mapAfterAssign = [];
    foreach (getAllDiningTables('', 'active', '') as $row) {
        $mapAfterAssign[$row['code']] = $row;
    }
    assert(!empty($mapAfterAssign[$tableA]['is_occupied']), 'Bàn vừa gán phải hiển thị đang phục vụ dù chưa gọi món');
    assert((int)$mapAfterAssign[$tableA]['guest_count'] === 4, 'Số khách trên sơ đồ bàn phải bằng 4');
    echo "  [PASS] Gán bàn mới thành công và đồng bộ lên Sơ đồ bàn\n";

    // 3. Chặn gán đè nếu allow_update = false, cho phép nếu allow_update = true
    $dupAssign = assignTable($pdo, ['table_code' => $tableA, 'guest_count' => 5, 'allow_update' => false]);
    assert($dupAssign['success'] === false, 'Phải chặn gán mới khi bàn đang phục vụ');

    $updAssign = assignTable($pdo, ['table_code' => $tableA, 'guest_count' => 6, 'note' => 'Ghép thêm 2 ghế', 'allow_update' => true]);
    assert($updAssign['success'] === true, 'Cập nhật thông tin bàn phải thành công');
    assert((int)getActiveTableAssignment($tableA, $pdo)['guest_count'] === 6, 'Số khách sau cập nhật phải là 6');
    echo "  [PASS] Cập nhật thông tin gán bàn hoạt động chính xác\n";

    // 4. Tạo đơn mở + món cho bàn A rồi test Chuyển bàn A -> bàn B
    $pdo->prepare("INSERT INTO `orders` (`table_code`, `status`, `created_at`) VALUES (?, 'open', NOW())")->execute([$tableA]);
    $testOrderId = (int) $pdo->lastInsertId();
    $createdOrderIds[] = $testOrderId;

    $pdo->prepare("INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `status`, `sent_at`) VALUES (?, 'Món Test Chuyển Bàn', 50000, 2, 'cooking', NOW())")
        ->execute([$testOrderId]);

    // Chặn chuyển bàn khi chưa có món không thành công nếu trả bàn trống
    $blockedRelease = releaseEmptyTableAssignment($pdo, $tableA);
    assert($blockedRelease['success'] === false, 'Không được trả bàn trống khi đang có món chưa thanh toán');
    echo "  [PASS] Chặn trả bàn trống khi đang có món gọi\n";

    // Chặn chuyển bàn tới chính nó
    $sameTransfer = transferTable($pdo, $tableA, $tableA);
    assert($sameTransfer['success'] === false, 'Không được chuyển bàn sang chính nó');

    // Chuyển bàn A -> bàn B
    $resTransfer = transferTable($pdo, $tableA, $tableB, 'Khách đổi sang khu mát hơn');
    assert($resTransfer['success'] === true, 'Chuyển bàn A -> B phải thành công: ' . ($resTransfer['message'] ?? ''));

    // Kiểm tra bàn A trống, bàn B có đủ đơn, món và thông tin gán bàn
    assert(getActiveTableAssignment($tableA, $pdo) === null, 'Bàn nguồn phải hết lượt gán bàn active');
    assert(fetchTableItems($pdo, $tableA) === [], 'Bàn nguồn phải không còn món mở');

    $assignB = getActiveTableAssignment($tableB, $pdo);
    $itemsB  = fetchTableItems($pdo, $tableB);
    assert($assignB !== null && (int)$assignB['guest_count'] === 6, 'Bàn đích phải nhận đủ 6 khách');
    assert(strpos($assignB['note'], 'Chuyển từ bàn ' . $tableA) !== false, 'Ghi chú chuyển bàn phải được lưu');
    assert(count($itemsB) === 1 && $itemsB[0]['name'] === 'Món Test Chuyển Bàn', 'Bàn đích phải nhận đủ món từ bàn nguồn');
    echo "  [PASS] Chuyển bàn thành công: đơn hàng, món ăn & thông tin gán bàn chuyển sang {$tableB}\n";

    // Chặn chuyển sang bàn đang có khách (test ngược khi chuyển bàn B -> B hoặc khi có bàn occupied)
    // Xóa món test để test trả bàn trống
    $pdo->prepare("DELETE FROM `order_items` WHERE `order_id` = ?")->execute([$testOrderId]);
    $pdo->prepare("DELETE FROM `orders` WHERE `id` = ?")->execute([$testOrderId]);
    $createdOrderIds = [];

    $resRelease = releaseEmptyTableAssignment($pdo, $tableB);
    assert($resRelease['success'] === true, 'Trả bàn trống khi 0 món phải thành công');
    assert(getActiveTableAssignment($tableB, $pdo) === null, 'Bàn B phải hết lượt gán bàn');
    echo "  [PASS] Trả bàn trống thành công khi bàn chưa gọi món\n";

    // 5. Kiểm thử Gộp bàn (Merge Tables): Gán & tạo đơn ở cả tableA (2 khách, Món 1) và tableB (3 khách, Món 2)
    $pdo->prepare("INSERT INTO `orders` (`table_code`, `status`, `created_at`) VALUES (?, 'open', NOW())")->execute([$tableA]);
    $orderA = (int) $pdo->lastInsertId();
    $createdOrderIds[] = $orderA;
    $pdo->prepare("INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `status`, `sent_at`) VALUES (?, 'Món Bàn Nguồn', 40000, 1, 'pending', NOW())")->execute([$orderA]);

    $pdo->prepare("INSERT INTO `orders` (`table_code`, `status`, `created_at`) VALUES (?, 'open', NOW())")->execute([$tableB]);
    $orderB = (int) $pdo->lastInsertId();
    $createdOrderIds[] = $orderB;
    $pdo->prepare("INSERT INTO `order_items` (`order_id`, `item_name`, `unit_price`, `qty`, `status`, `sent_at`) VALUES (?, 'Món Bàn Đích', 60000, 2, 'cooking', NOW())")->execute([$orderB]);

    assignTable($pdo, ['table_code' => $tableA, 'guest_count' => 2, 'note' => 'Nhóm A']);
    assignTable($pdo, ['table_code' => $tableB, 'guest_count' => 3, 'note' => 'Nhóm B']);

    // Chặn gộp chính nó
    $sameMerge = mergeTables($pdo, $tableA, $tableA);
    assert($sameMerge['success'] === false, 'Không được gộp bàn với chính nó');

    // Thực hiện gộp tableA -> tableB
    $resMerge = mergeTables($pdo, $tableA, $tableB, 'Thanh toán chung 1 bill');
    assert($resMerge['success'] === true, 'Gộp bàn A vào B phải thành công: ' . ($resMerge['message'] ?? ''));

    // Xác minh bàn A đã trống hoàn toàn
    assert(getActiveTableAssignment($tableA, $pdo) === null, 'Bàn nguồn sau khi gộp phải hết lượt gán active');
    assert(fetchTableItems($pdo, $tableA) === [], 'Bàn nguồn sau khi gộp phải không còn món mở');

    // Xác minh bàn B nhận đủ 5 khách, cả 2 món và ghi chú gộp bàn
    $mergedAssignB = getActiveTableAssignment($tableB, $pdo);
    $mergedItemsB  = fetchTableItems($pdo, $tableB);
    $mergedNamesB  = array_column($mergedItemsB, 'name');
    assert($mergedAssignB !== null && (int)$mergedAssignB['guest_count'] === 5, 'Tổng số khách sau gộp phải bằng 2 + 3 = 5');
    assert(strpos($mergedAssignB['note'], 'Gộp từ bàn ' . $tableA) !== false, 'Ghi chú gộp bàn phải được lưu');
    assert(in_array('Món Bàn Nguồn', $mergedNamesB, true) && in_array('Món Bàn Đích', $mergedNamesB, true), 'Bàn đích phải có đủ món của cả 2 bàn');
    echo "  [PASS] Gộp bàn thành công: hợp nhất đơn hàng, món ăn & tổng số khách (5 khách) vào {$tableB}\n";

    // Chặn gộp vào bàn vừa bị làm trống (tableA)
    $emptyTargetMerge = mergeTables($pdo, $tableB, $tableA);
    assert($emptyTargetMerge['success'] === false, 'Không được gộp vào bàn đang trống');
    echo "  [PASS] Chặn gộp vào bàn đích đang trống\n";
} finally {
    foreach ($createdOrderIds as $oid) {
        $pdo->prepare("DELETE FROM `order_items` WHERE `order_id` = ?")->execute([$oid]);
        $pdo->prepare("DELETE FROM `orders` WHERE `id` = ?")->execute([$oid]);
    }
    closeTableAssignmentInTx($pdo, $tableA);
    closeTableAssignmentInTx($pdo, $tableB);
}

echo "\n>>> TẤT CẢ KIỂM THỬ GÁN BÀN & CHUYỂN BÀN ĐÃ ĐẠT 100%! <<<\n";
