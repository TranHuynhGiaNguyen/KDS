<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tables.php';

session_start();
$_SESSION['user_id']  = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role']     = 'admin';
$_SESSION['fullname'] = 'Quản trị viên';

echo "=== KIỂM TRA RENDER CÁC TRANG ===\n";

// 1. Render pages/staff.php
ob_start();
include __DIR__ . '/../pages/staff.php';
$staffHtml = ob_get_clean();
assert(strlen($staffHtml) > 500, "staff.php HTML output too short");
assert(strpos($staffHtml, 'restaurant-floor-grid') !== false, "Grid view not found in staff.php");
assert(strpos($staffHtml, 'restaurant-floor-canvas') !== false, "Visual 2D view not found in staff.php");
assert(strpos($staffHtml, 'btn-toggle-grid') !== false, "Toggle button grid not found");
assert(strpos($staffHtml, 'btn-toggle-visual') !== false, "Toggle button visual not found");
echo "  [PASS] pages/staff.php rendered successfully with both Grid & Visual Canvas\n";

// 2. Render pages/tables.php (tab=tables)
$_GET['tab'] = 'tables';
ob_start();
include __DIR__ . '/../pages/tables.php';
$tablesHtml = ob_get_clean();
assert(strpos($tablesHtml, 'Danh sách Bàn ăn Nhà hàng') !== false, "Tab tables not found");
assert(strpos($tablesHtml, 'modal-add-table') !== false, "Modal add table not found");
assert(strpos($tablesHtml, 'modal-edit-table') !== false, "Modal edit table not found");
assert(strpos($tablesHtml, 'edit-table-shape') !== false, "Shape select not found in edit modal");
echo "  [PASS] pages/tables.php (Tab 1: tables) rendered successfully with shape controls\n";

// 3. Render pages/tables.php (tab=areas)
$_GET['tab'] = 'areas';
ob_start();
include __DIR__ . '/../pages/tables.php';
$areasHtml = ob_get_clean();
assert(strpos($areasHtml, 'Danh mục Khu vực Không gian Nhà hàng') !== false, "Tab areas not found");
echo "  [PASS] pages/tables.php (Tab 2: areas) rendered successfully\n";

// 4. Render pages/tables.php (tab=layout)
$_GET['tab'] = 'layout';
ob_start();
include __DIR__ . '/../pages/tables.php';
$layoutHtml = ob_get_clean();
assert(strpos($layoutHtml, 'Thiết kế Sơ đồ Bàn 2D') !== false, "Tab layout not found");
assert(strpos($layoutHtml, 'admin-layout-canvas') !== false, "admin-layout-canvas not found");
assert(strpos($layoutHtml, 'draggable-table-item') !== false, "draggable-table-item not found");
assert(strpos($layoutHtml, 'btn-save-layout') !== false, "btn-save-layout not found");
assert(strpos($layoutHtml, 'btn-toggle-shape') !== false, "btn-toggle-shape not found");
echo "  [PASS] pages/tables.php (Tab 3: layout) rendered successfully with 2D Visual Editor\n";

// 5. Render pages/admin.php (mode=day)
$_GET['page'] = 'admin';
$_GET['mode'] = 'day';
$_GET['date'] = '2026-11-13';
ob_start();
include __DIR__ . '/../pages/admin.php';
$adminDayHtml = ob_get_clean();
assert(strpos($adminDayHtml, 'Tổng quan & Báo cáo Doanh thu') !== false, "Dashboard title not found");
assert(strpos($adminDayHtml, 'revenue-svg-chart') !== false, "SVG chart not found in day mode");
assert(strpos($adminDayHtml, 'Danh sách Hóa đơn thanh toán') !== false, "Invoice list table not found in day mode");
echo "  [PASS] pages/admin.php (Dashboard mode=day) rendered successfully with SVG chart & invoice table\n";

// 6. Render pages/admin.php (mode=month)
$_GET['page'] = 'admin';
$_GET['mode'] = 'month';
$_GET['month'] = '2026-11';
ob_start();
include __DIR__ . '/../pages/admin.php';
$adminMonthHtml = ob_get_clean();
assert(strpos($adminMonthHtml, 'Bảng kê Doanh thu từng ngày') !== false, "Monthly breakdown table not found in month mode");
assert(strpos($adminMonthHtml, 'revenue-svg-chart') !== false, "SVG chart not found in month mode");
assert(strpos($adminMonthHtml, 'Top 5 món bán chạy nhất') !== false, "Top dishes not found");
assert(strpos($adminMonthHtml, 'Doanh thu theo Khu vực Nhà hàng') !== false, "Area breakdown not found");
echo "  [PASS] pages/admin.php (Dashboard mode=month) rendered successfully with 30-day breakdown\n";

echo "\n>>> TẤT CẢ CÁC TRANG RENDER HOÀN HẢO! <<<\n";
