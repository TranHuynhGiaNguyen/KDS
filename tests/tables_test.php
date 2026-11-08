<?php
/**
 * Test Suite: Quản lý Bàn ăn và Khu vực Nhà hàng (SmartDine)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tables.php';

function assertTest(bool $condition, string $testName): void {
    if ($condition) {
        echo "  [PASS] $testName\n";
    } else {
        echo "  [FAIL] $testName\n";
        exit(1);
    }
}

echo "=== KIỂM THỬ KHU VỰC VÀ BÀN ĂN ===\n";

// 1. Kiểm tra danh sách mặc định
$areas = getAllAreas();
assertTest(count($areas) >= 3, 'Khởi tạo sẵn ít nhất 3 khu vực (indoor, garden, vip)');

$tables = getAllDiningTables();
assertTest(count($tables) >= 12, 'Khởi tạo sẵn ít nhất 12 bàn ăn (B01-B12)');

// 2. Thêm khu vực mới
$resArea = createArea([
    'code'        => 'test-rooftop',
    'name'        => 'Sân Thượng Test',
    'description' => 'Không gian thử nghiệm',
    'sort_order'  => 99,
    'status'      => 'active'
]);
assertTest($resArea['success'] === true, 'Tạo khu vực test-rooftop thành công');
$testAreaId = (int) $resArea['id'];

// Kiểm tra trùng mã khu vực
$resDup = createArea([
    'code' => 'test-rooftop',
    'name' => 'Sân Thượng Trùng'
]);
assertTest($resDup['success'] === false, 'Chặn tạo khu vực trùng mã code');

// 3. Cập nhật khu vực
$resUpdateArea = updateArea($testAreaId, [
    'code'        => 'test-rooftop',
    'name'        => 'Sân Thượng VIP Cập Nhật',
    'description' => 'Mô tả mới',
    'sort_order'  => 50,
    'status'      => 'active'
]);
assertTest($resUpdateArea['success'] === true, 'Cập nhật thông tin khu vực thành công');

$areaInfo = getAreaById($testAreaId);
assertTest($areaInfo['name'] === 'Sân Thượng VIP Cập Nhật', 'Thông tin cập nhật được lưu chính xác');

// 4. Thêm bàn ăn mới vào khu vực vừa tạo
$resTable = createDiningTable([
    'code'       => 'TEST-99',
    'name'       => 'Bàn Test 99',
    'area_id'    => $testAreaId,
    'capacity'   => 8,
    'status'     => 'active',
    'sort_order' => 1
]);
assertTest($resTable['success'] === true, 'Thêm bàn mới TEST-99 vào khu vực test-rooftop thành công');
$testTableId = (int) $resTable['id'];

// Kiểm tra trùng mã bàn
$resDupTable = createDiningTable([
    'code'    => 'TEST-99',
    'area_id' => $testAreaId
]);
assertTest($resDupTable['success'] === false, 'Chặn tạo bàn trùng mã bàn');

// 5. Cập nhật bàn ăn & hình dạng 2D
$resUpdateTable = updateDiningTable($testTableId, [
    'code'       => 'TEST-99',
    'name'       => 'Bàn Test VIP 99 Sửa',
    'area_id'    => $testAreaId,
    'capacity'   => 10,
    'shape'      => 'round',
    'status'     => 'active',
    'sort_order' => 5
]);
assertTest($resUpdateTable['success'] === true, 'Cập nhật thông tin bàn ăn thành công');

$tableInfo = getDiningTableById($testTableId);
assertTest((int)$tableInfo['capacity'] === 10, 'Sức chứa cập nhật thành 10 khách chính xác');
assertTest($tableInfo['shape'] === 'round', 'Hình dạng bàn tròn (round) được lưu chính xác');

// 5b. Thử nghiệm lưu bố cục sơ đồ 2D, cửa ra vào & quầy thu ngân (saveTableLayout)
$resLayout = saveTableLayout(
    [
        [
            'id'     => $testTableId,
            'pos_x'  => 55.5,
            'pos_y'  => 62.0,
            'shape'  => 'rectangle',
            'width'  => 140,
            'height' => 95
        ]
    ],
    [
        'area_code'    => 'test-rooftop',
        'entrance_pos' => 'top_right',
        'entrance_x'   => 86.0,
        'entrance_y'   => 4.0,
        'cashier_x'    => 20.0,
        'cashier_y'    => 10.0
    ]
);
assertTest($resLayout['success'] === true, 'Lưu bố cục sơ đồ bàn 2D, quầy thu ngân & cửa ra vào (saveTableLayout) thành công');
$tableInfoLayout = getDiningTableById($testTableId);
assertTest((float)$tableInfoLayout['pos_x'] === 55.5, 'Tọa độ X lưu chính xác (55.5%)');
assertTest((float)$tableInfoLayout['pos_y'] === 62.0, 'Tọa độ Y lưu chính xác (62.0%)');
assertTest($tableInfoLayout['shape'] === 'rectangle', 'Hình dạng chuyển sang rectangle chính xác');
assertTest((int)$tableInfoLayout['width'] === 140, 'Kích thước width (to nhỏ) cập nhật thành 140px');
assertTest((int)$tableInfoLayout['height'] === 95, 'Kích thước height (to nhỏ) cập nhật thành 95px');

$areaInfoLayout = getAreaById($testAreaId);
assertTest($areaInfoLayout['entrance_pos'] === 'top_right', 'Vị trí cửa ra vào lưu chính xác (top_right)');
assertTest((float)$areaInfoLayout['entrance_x'] === 86.0, 'Tọa độ X cửa ra vào lưu chính xác (86.0%)');
assertTest((float)$areaInfoLayout['entrance_y'] === 4.0, 'Tọa độ Y cửa ra vào lưu chính xác (4.0%)');
assertTest((float)$areaInfoLayout['cashier_x'] === 20.0, 'Tọa độ X quầy thu ngân lưu chính xác (20.0%)');
assertTest((float)$areaInfoLayout['cashier_y'] === 10.0, 'Tọa độ Y quầy thu ngân lưu chính xác (10.0%)');

// 6. Đổi nhanh trạng thái bàn ăn
$resToggle = updateDiningTableStatus($testTableId, 'maintenance');
assertTest($resToggle['success'] === true, 'Đổi trạng thái bàn sang bảo trì thành công');
$tableInfoMaint = getDiningTableById($testTableId);
assertTest($tableInfoMaint['status'] === 'maintenance', 'Trạng thái bảo trì đã được cập nhật');

// 7. Kiểm tra an toàn: Chặn xóa khu vực khi còn bàn trực thuộc
$resDelAreaFail = deleteArea($testAreaId);
assertTest($resDelAreaFail['success'] === false, 'Chặn xóa khu vực khi còn bàn ăn TEST-99 trực thuộc');

// 8. Kiểm tra an toàn: Chặn xóa bàn đang có đơn hàng mở
$resDelOccupied = deleteDiningTable(3); // Bàn B03 đang có đơn mở demo
assertTest($resDelOccupied['success'] === false, 'Chặn xóa bàn B03 vì đang có đơn hàng mở');

// 9. Xóa bàn test
$resDelTable = deleteDiningTable($testTableId);
assertTest($resDelTable['success'] === true, 'Xóa bàn test thành công sau khi không có đơn mở');

// 10. Xóa khu vực test sau khi bàn đã được dọn
$resDelArea = deleteArea($testAreaId);
assertTest($resDelArea['success'] === true, 'Xóa khu vực test thành công sau khi đã dọn hết bàn');

// 11. Kiểm tra thống kê tổng hợp
$stats = getTableStats();
assertTest($stats['total_tables'] >= 12, 'Thống kê tổng số bàn chính xác');
assertTest($stats['total_areas'] >= 3, 'Thống kê tổng số khu vực chính xác');
assertTest($stats['total_capacity'] > 0, 'Thống kê tổng sức chứa > 0');

echo "\n>>> TẤT CẢ CÁC BÀI KIỂM THỬ ĐỀU ĐẠT 100%! <<<\n";

