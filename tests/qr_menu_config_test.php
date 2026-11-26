<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

echo "=== KIỂM THỬ CONFIG MENU (BUFFET / GỌI MÓN LẺ) & TRANG QR ORDER ===\n";

$pdo = getDbConnection();
assert($pdo instanceof PDO, 'Cần kết nối MySQL');
ensureMenuConfigSchema($pdo);

$colCat = $pdo->query("SHOW COLUMNS FROM `categories` LIKE 'menu_type'")->fetch(PDO::FETCH_ASSOC);
assert($colCat !== false, 'Cột categories.menu_type phải tồn tại');
$colDish = $pdo->query("SHOW COLUMNS FROM `dishes` LIKE 'menu_type'")->fetch(PDO::FETCH_ASSOC);
assert($colDish !== false, 'Cột dishes.menu_type phải tồn tại');
$colPkg = $pdo->query("SHOW COLUMNS FROM `dishes` LIKE 'buffet_package_id'")->fetch(PDO::FETCH_ASSOC);
assert($colPkg !== false, 'Cột dishes.buffet_package_id phải tồn tại');
echo "  [PASS] Schema Buffet packages & menu_type đã sẵn sàng\n";

$createdPkgId = 0;
$createdCatId = 0;
$createdDishId = 0;

try {
    $pkgRes = createBuffetPackage([
        'name'             => 'BUFFET TEST CAO CẤP',
        'price_per_person' => 699000,
        'description'      => 'Gói test',
        'status'           => 'active',
    ]);
    assert(!empty($pkgRes['success']), 'Tạo gói Buffet phải thành công');
    $createdPkgId = (int) $pkgRes['id'];

    $catRes = createCategory([
        'name'      => 'Danh mục Buffet Test',
        'menu_type' => 'buffet',
        'status'    => 'active',
    ]);
    assert(!empty($catRes['success']), 'Tạo danh mục buffet phải thành công');
    $createdCatId = (int) $catRes['id'];

    $dishRes = createDish([
        'category_id'       => $createdCatId,
        'menu_type'         => 'buffet',
        'buffet_package_id' => $createdPkgId,
        'name'              => 'Sườn bò Mỹ Buffet Test',
        'price'             => 0,
        'station'           => 'hot',
        'status'            => 'active',
    ]);
    assert(!empty($dishRes['success']), 'Tạo món buffet phải thành công');
    $createdDishId = (int) $dishRes['id'];

    $buffetList = getAllDishes('', '', 'Sườn bò Mỹ Buffet Test', '', 'buffet');
    assert(count($buffetList) === 1, 'Lọc món buffet phải trả về đúng món vừa tạo');
    assert(($buffetList[0]['buffet_package_name'] ?? '') === 'BUFFET TEST CAO CẤP', 'Món phải liên kết đúng tên gói Buffet');
    echo "  [PASS] CRUD Gói Buffet, Danh mục & Món ăn theo menu_type hoạt động chính xác\n";

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['user_id']  = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role']     = 'admin';
    $_SESSION['fullname'] = 'Quản trị viên';

    foreach (['dishes', 'categories', 'buffet'] as $tab) {
        $_GET['tab'] = $tab;
        ob_start();
        include __DIR__ . '/../pages/dishes.php';
        $html = ob_get_clean();
        assert(strlen($html) > 500, "Tab $tab của pages/dishes.php render quá ngắn");
    }
    echo "  [PASS] pages/dishes.php render đầy đủ cả 3 tab (dishes, categories, buffet)\n";

    $_GET['table'] = 'B05';
    ob_start();
    include __DIR__ . '/../qr-order.php';
    $qrHtml = ob_get_clean();
    assert(strpos($qrHtml, 'Bàn B05') !== false, 'Trang QR Order phải hiển thị đúng Bàn B05');
    assert(strpos($qrHtml, 'BUFFET TEST CAO CẤP') !== false, 'Trang QR Order phải hiển thị gói Buffet từ Admin config');
    assert(strpos($qrHtml, 'MENU GỌI MÓN') !== false, 'Trang QR Order phải có mục MENU GỌI MÓN');
    assert(strpos($qrHtml, 'Món buffet') !== false && strpos($qrHtml, 'Món tính tiền') !== false, 'Trang QR Order phải có 2 tab Món buffet & Món tính tiền');
    assert(strpos($qrHtml, 'Gọi món của bạn') !== false && strpos($qrHtml, 'Giỏ đồ ăn') !== false && strpos($qrHtml, 'Món đã gọi') !== false, 'Trang QR Order phải có màn hình Giỏ đồ ăn / Món đã gọi');
    echo "  [PASS] Giao diện qr-order.php đồng bộ 100% với cấu hình Menu Admin\n";
} finally {
    if ($createdDishId > 0) {
        deleteDish($createdDishId, false);
    }
    if ($createdCatId > 0) {
        deleteCategory($createdCatId);
    }
    if ($createdPkgId > 0) {
        deleteBuffetPackage($createdPkgId);
    }
}

echo "\n>>> TẤT CẢ KIỂM THỬ CONFIG MENU & GIAO DIỆN QR ORDER ĐÃ ĐẠT 100%! <<<\n";
