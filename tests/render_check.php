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
assert(strpos($adminDayHtml, 'Top 5 món bán chạy nhất') === false, "Top 5 dishes should be removed");
assert(strpos($adminDayHtml, 'Doanh thu theo Khu vực Nhà hàng') === false, "Area breakdown should be removed");
assert(strpos($adminDayHtml, 'Danh sách Hóa đơn thanh toán') === false, "Invoice list table should be removed");
assert(strpos($adminDayHtml, 'menu-stock-management') === false, "Stock management panel should be removed");
echo "  [PASS] pages/admin.php (Dashboard mode=day) rendered cleanly with SVG chart & KPI cards\n";

// 6. Render pages/admin.php (mode=month)
$_GET['page'] = 'admin';
$_GET['mode'] = 'month';
$_GET['month'] = '2026-11';
ob_start();
include __DIR__ . '/../pages/admin.php';
$adminMonthHtml = ob_get_clean();
assert(strpos($adminMonthHtml, 'revenue-svg-chart') !== false, "SVG chart not found in month mode");
echo "  [PASS] pages/admin.php (Dashboard mode=month) rendered cleanly with SVG chart\n";

// 7. Render pages/admin.php (mode=range)
$_GET['page'] = 'admin';
$_GET['mode'] = 'range';
$_GET['start_date'] = '2026-11-01';
$_GET['end_date'] = '2026-11-13';
ob_start();
include __DIR__ . '/../pages/admin.php';
$adminRangeHtml = ob_get_clean();
assert(strpos($adminRangeHtml, 'revenue-svg-chart') !== false, "SVG chart not found in range mode");
assert(strpos($adminRangeHtml, 'Theo khoảng ngày') !== false, "Range tab not found");
echo "  [PASS] pages/admin.php (Dashboard mode=range) rendered cleanly with range chart\n";

// 8. Render pages/dishes.php
$_GET['page'] = 'dishes';
$_GET['tab'] = 'dishes';
ob_start();
include __DIR__ . '/../pages/dishes.php';
$dishesHtml = ob_get_clean();
assert(strpos($dishesHtml, 'Quản lý Thực đơn, Món ăn & Giá bán') !== false, "Dishes page title not found");
assert(strpos($dishesHtml, 'modal-delete-dish') !== false, "Delete dish modal not found");
assert(strpos($dishesHtml, 'delete_type') !== false, "Delete type soft/hard not found");
assert(strpos($dishesHtml, 'pagination-bar') !== false, "Dishes pagination bar not found");
echo "  [PASS] pages/dishes.php rendered successfully with Soft Delete modal & Pagination\n";

// 9. Render pages/users.php
$_GET['page'] = 'users';
ob_start();
include __DIR__ . '/../pages/users.php';
$usersHtml = ob_get_clean();
assert(strpos($usersHtml, 'Quản lý Tài khoản & Phân quyền') !== false, "Users page title not found");
assert(strpos($usersHtml, 'pagination-bar') !== false, "Users pagination bar not found");
echo "  [PASS] pages/users.php rendered successfully with Pagination & RBAC controls\n";

// 10. Render pages/profile.php
$_GET['page'] = 'profile';
ob_start();
include __DIR__ . '/../pages/profile.php';
$profileHtml = ob_get_clean();
assert(strpos($profileHtml, 'Hồ sơ Cá nhân & Bảo mật') !== false, "Profile page title not found");
assert(strpos($profileHtml, 'update_profile') !== false, "Update profile form not found");
assert(strpos($profileHtml, 'change_password') !== false, "Change password form not found");
echo "  [PASS] pages/profile.php rendered successfully with Profile update & Password change forms\n";

// 11. Render pages/kitchen.php (Không còn phần unavailable mock)
$_GET['page'] = 'kitchen';
ob_start();
include __DIR__ . '/../pages/kitchen.php';
$kitchenHtml = ob_get_clean();
assert(strpos($kitchenHtml, 'kds-grid') !== false, "KDS grid not found in kitchen.php");
assert(strpos($kitchenHtml, 'unavailable-panel') === false, "Mock unavailable panel must be removed from kitchen.php");
echo "  [PASS] pages/kitchen.php rendered successfully (Out-of-stock panel cleanly removed)\n";

// 12. Render pages/admin.php (Đã dọn các mục thừa khỏi tổng quan)
$_GET['page'] = 'admin';
$_GET['mode'] = 'day';
ob_start();
include __DIR__ . '/../pages/admin.php';
$adminCleanHtml = ob_get_clean();
assert(strpos($adminCleanHtml, 'menu-stock-management') === false, "menu-stock-management should be removed from admin.php");
echo "  [PASS] pages/admin.php overview sections cleaned successfully\n";

echo "\n>>> TẤT CẢ CÁC TRANG RENDER HOÀN HẢO! <<<\n";
