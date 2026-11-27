<?php
/**
 * SmartDine - Cấu hình kết nối Cơ sở dữ liệu MySQL PDO
 */

if (function_exists('date_default_timezone_set') && date_default_timezone_get() !== 'Asia/Ho_Chi_Minh') {
    date_default_timezone_set('Asia/Ho_Chi_Minh');
}
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

$_dbLocalFile = __DIR__ . '/database.local.php';
if (is_file($_dbLocalFile)) {
    require_once $_dbLocalFile;
}
unset($_dbLocalFile);

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'smartdine_kds');

if (!function_exists('assetUrl')) {
    function assetUrl(string $path): string {
        $file = dirname(__DIR__) . '/' . ltrim($path, '/\\');
        return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
}

/**
 * Lấy đối tượng kết nối PDO
 * Hỗ trợ hosting (kết nối thẳng DB có sẵn) và localhost (tự tạo DB nếu chưa có)
 */
function getDbConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsnWithDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        try {
            $pdo = new PDO($dsnWithDb, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $connectErr) {
            // Chỉ tự tạo database khi gặp lỗi Unknown database (1049) trên môi trường dev/localhost
            if (strpos($connectErr->getMessage(), '1049') === false) {
                throw $connectErr;
            }
            $dsnWithoutDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
            $initPdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $initPdo = null;

            $pdo = new PDO($dsnWithDb, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        // Đồng bộ múi giờ MySQL theo Việt Nam (+07:00)
        $pdo->exec("SET time_zone = '+07:00'");

        // Khởi tạo cấu trúc bảng nếu chưa có
        initDatabaseTables($pdo);

        return $pdo;
    } catch (PDOException $e) {
        // Nếu không kết nối được MySQL, ghi log lỗi
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

function smartdineAllowDemoMenuSeed(?bool $set = null): bool {
    static $allow = false;
    if ($set !== null) {
        $allow = $set;
    }
    return $allow;
}

/**
 * Khởi tạo bảng users và nạp tài khoản mẫu ban đầu
 */
function initDatabaseTables(PDO $pdo) {
    $createTableQuery = "
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `fullname` VARCHAR(100) NOT NULL,
        `role` ENUM('admin', 'shift_manager', 'staff', 'kitchen') NOT NULL DEFAULT 'staff',
        `phone` VARCHAR(20) DEFAULT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        `last_login` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_role (`role`),
        INDEX idx_status (`status`),
        INDEX idx_username (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($createTableQuery);

    ensureUsersRoleSchema($pdo);

    // Kiểm tra xem đã có tài khoản nào chưa (chỉ auto-seed thực đơn mẫu khi cài DB mới hoàn toàn)
    $checkStmt = $pdo->query("SELECT COUNT(*) as total FROM `users`");
    $count = (int) $checkStmt->fetchColumn();
    smartdineAllowDemoMenuSeed($count === 0);

    if ($count === 0) {
        // Nạp các tài khoản khởi tạo mẫu theo từng vai trò
        $defaultUsers = [
            [
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'fullname' => 'Quản trị viên Hệ thống',
                'role'     => 'admin',
                'phone'    => '0901234567',
                'email'    => 'admin@smartdine.vn',
                'status'   => 'active'
            ],
            [
                'username' => 'staff',
                'password' => password_hash('staff123', PASSWORD_DEFAULT),
                'fullname' => 'Nguyễn Phục Vụ',
                'role'     => 'staff',
                'phone'    => '0912345678',
                'email'    => 'staff@smartdine.vn',
                'status'   => 'active'
            ],
            [
                'username' => 'kitchen',
                'password' => password_hash('kitchen123', PASSWORD_DEFAULT),
                'fullname' => 'Trần Bếp Trưởng',
                'role'     => 'kitchen',
                'phone'    => '0923456789',
                'email'    => 'chef@smartdine.vn',
                'status'   => 'active'
            ],
            // Tài khoản tương thích với bản cũ (1/1)
            [
                'username' => '1',
                'password' => password_hash('1', PASSWORD_DEFAULT),
                'fullname' => 'Nhân viên Demo #1',
                'role'     => 'staff',
                'phone'    => '0988888888',
                'email'    => 'demo1@smartdine.vn',
                'status'   => 'active'
            ]
        ];

        $insertStmt = $pdo->prepare("
            INSERT INTO `users` (`username`, `password`, `fullname`, `role`, `phone`, `email`, `status`)
            VALUES (:username, :password, :fullname, :role, :phone, :email, :status)
        ");

        foreach ($defaultUsers as $u) {
            $insertStmt->execute($u);
        }
    }

    // Đảm bảo schema & tài khoản mẫu cho role shift_manager
    // (đặt sau nạp user ban đầu để không làm hỏng logic count===0)
    $stmtShiftExists = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'shift_manager'");
    if ((int) $stmtShiftExists->fetchColumn() === 0) {
        $stmtChkShiftUser = $pdo->prepare("SELECT `id` FROM `users` WHERE `username` = 'shift_manager' LIMIT 1");
        $stmtChkShiftUser->execute();
        if (!$stmtChkShiftUser->fetch()) {
            $pdo->prepare("
                INSERT INTO `users` (`username`, `password`, `fullname`, `role`, `phone`, `email`, `status`)
                VALUES ('shift_manager', :pw, 'Lê Quản Lý Ca', 'shift_manager', '0934567890', 'shift@smartdine.vn', 'active')
            ")->execute([':pw' => password_hash('shift123', PASSWORD_DEFAULT)]);
        }
    }

    // ==========================================================
    // KHỞI TẠO BẢNG DANH MỤC (CATEGORIES) VÀ DỮ LIỆU MẪU
    // ==========================================================
    $createCatTableQuery = "
    CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `slug` VARCHAR(50) NOT NULL UNIQUE,
        `icon` VARCHAR(20) DEFAULT '✦',
        `description` VARCHAR(255) DEFAULT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_slug (`slug`),
        INDEX idx_status (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($createCatTableQuery);

    $checkCatStmt = $pdo->query("SELECT COUNT(*) as total FROM `categories`");
    $catCount = (int) $checkCatStmt->fetchColumn();

    if ($catCount === 0 && smartdineAllowDemoMenuSeed()) {
        $defaultCategories = [
            [
                'name'        => 'Món chính',
                'slug'        => 'main',
                'icon'        => '♨',
                'description' => 'Các món ăn chính đậm đà, cung cấp đầy đủ dinh dưỡng',
                'sort_order'  => 1,
                'status'      => 'active'
            ],
            [
                'name'        => 'Món phụ / Khai vị',
                'slug'        => 'side',
                'icon'        => '❋',
                'description' => 'Món khai vị nhẹ nhàng, kích thích vị giác đầu bữa',
                'sort_order'  => 2,
                'status'      => 'active'
            ],
            [
                'name'        => 'Đồ uống',
                'slug'        => 'drink',
                'icon'        => '◉',
                'description' => 'Trà trái cây tươi mát, cà phê và nước giải khát',
                'sort_order'  => 3,
                'status'      => 'active'
            ],
            [
                'name'        => 'Tráng miệng',
                'slug'        => 'dessert',
                'icon'        => '✧',
                'description' => 'Sữa chua, kem tươi và các món bánh tráng miệng thanh ngọt',
                'sort_order'  => 4,
                'status'      => 'active'
            ]
        ];

        $insertCatStmt = $pdo->prepare("
            INSERT INTO `categories` (`name`, `slug`, `icon`, `description`, `sort_order`, `status`)
            VALUES (:name, :slug, :icon, :description, :sort_order, :status)
        ");

        foreach ($defaultCategories as $c) {
            $insertCatStmt->execute($c);
        }
    }

    // ==========================================================
    // KHỞI TẠO BẢNG MÓN ĂN (DISHES) VÀ DỮ LIỆU MẪU
    // ==========================================================
    $createDishesTableQuery = "
    CREATE TABLE IF NOT EXISTS `dishes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category_id` INT NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `price` DECIMAL(12, 0) NOT NULL DEFAULT 0,
        `image` VARCHAR(500) DEFAULT NULL,
        `description` TEXT DEFAULT NULL,
        `tag` VARCHAR(50) DEFAULT NULL,
        `station` ENUM('hot', 'soup', 'cold', 'bar') NOT NULL DEFAULT 'hot',
        `status` ENUM('active', 'out_of_stock', 'hidden') NOT NULL DEFAULT 'active',
        `rating` DECIMAL(2, 1) NOT NULL DEFAULT 5.0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (`category_id`),
        INDEX idx_status (`status`),
        INDEX idx_station (`station`),
        CONSTRAINT `fk_dishes_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($createDishesTableQuery);

    $checkDishStmt = $pdo->query("SELECT COUNT(*) as total FROM `dishes`");
    $dishCount = (int) $checkDishStmt->fetchColumn();

    if ($dishCount === 0 && smartdineAllowDemoMenuSeed()) {
        // Lấy ID của các danh mục theo slug
        $catMap = [];
        $catRows = $pdo->query("SELECT id, slug FROM `categories`")->fetchAll();
        foreach ($catRows as $cr) {
            $catMap[$cr['slug']] = (int) $cr['id'];
        }

        $defaultDishes = [
            [
                'category_id' => $catMap['main'] ?? 1,
                'name'        => 'Cơm gà Hải Nam',
                'price'       => 75000,
                'image'       => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=700&q=80',
                'description' => 'Gà ta mềm ngọt, cơm niêu thơm nấu cùng nước cốt luộc gà và sốt gừng đặc trưng.',
                'tag'         => 'Bán chạy nhất',
                'station'     => 'hot',
                'status'      => 'active',
                'rating'      => 4.9
            ],
            [
                'category_id' => $catMap['main'] ?? 1,
                'name'        => 'Mì xào bò',
                'price'       => 85000,
                'image'       => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?auto=format&fit=crop&w=700&q=80',
                'description' => 'Mì trứng dai xào cùng thăn bò mềm, nấm hương tươi và rau cải giòn thơm.',
                'tag'         => 'Gợi ý bếp trưởng',
                'station'     => 'hot',
                'status'      => 'active',
                'rating'      => 4.8
            ],
            [
                'category_id' => $catMap['side'] ?? 2,
                'name'        => 'Salad trộn đặc biệt',
                'price'       => 55000,
                'image'       => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=700&q=80',
                'description' => 'Rau xanh thủy canh tươi giòn, cà chua bi organic với sốt mè rang béo ngậy thanh mát.',
                'tag'         => 'Healthy',
                'station'     => 'cold',
                'status'      => 'active',
                'rating'      => 4.7
            ],
            [
                'category_id' => $catMap['drink'] ?? 3,
                'name'        => 'Trà đào cam sả',
                'price'       => 35000,
                'image'       => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=700&q=80',
                'description' => 'Hương trà ướp lạnh thanh mát, đào miếng giòn ngọt hòa quyện tinh dầu cam vàng và sả tươi.',
                'tag'         => 'Best Seller',
                'station'     => 'bar',
                'status'      => 'active',
                'rating'      => 4.9
            ],
            [
                'category_id' => $catMap['main'] ?? 1,
                'name'        => 'Bò lúc lắc hạt tiêu',
                'price'       => 120000,
                'image'       => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=700&q=80',
                'description' => 'Thịt bò Úc áp chảo xì xèo xém cạnh, sốt tiêu đen đậm đà ăn kèm khoai tây chiên giòn.',
                'tag'         => NULL,
                'station'     => 'hot',
                'status'      => 'active',
                'rating'      => 4.8
            ],
            [
                'category_id' => $catMap['dessert'] ?? 4,
                'name'        => 'Sữa chua trái cây',
                'price'       => 40000,
                'image'       => 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=700&q=80',
                'description' => 'Sữa chua Hy Lạp sánh mịn lên men tự nhiên, kèm dâu tây tươi và sốt việt quất ngọt dịu.',
                'tag'         => NULL,
                'station'     => 'cold',
                'status'      => 'active',
                'rating'      => 4.7
            ]
        ];

        $insertDishStmt = $pdo->prepare("
            INSERT INTO `dishes` (`category_id`, `name`, `price`, `image`, `description`, `tag`, `station`, `status`, `rating`)
            VALUES (:category_id, :name, :price, :image, :description, :tag, :station, :status, :rating)
        ");

        foreach ($defaultDishes as $d) {
            $insertDishStmt->execute($d);
        }
    }

    // ==========================================================
    // KHỞI TẠO BẢNG KHU VỰC NHÀ HÀNG (RESTAURANT_AREAS)
    // ==========================================================
    $createAreaTableQuery = "
    CREATE TABLE IF NOT EXISTS `restaurant_areas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(30) NOT NULL UNIQUE,
        `name` VARCHAR(100) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        `entrance_pos` VARCHAR(30) NOT NULL DEFAULT 'bottom_center',
        `entrance_x` FLOAT NOT NULL DEFAULT 50.0,
        `entrance_y` FLOAT NOT NULL DEFAULT 96.0,
        `cashier_x` FLOAT NOT NULL DEFAULT 14.0,
        `cashier_y` FLOAT NOT NULL DEFAULT 6.0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_area_status (`status`),
        INDEX idx_area_code (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($createAreaTableQuery);

    $checkAreaStmt = $pdo->query("SELECT COUNT(*) as total FROM `restaurant_areas`");
    $areaCount = (int) $checkAreaStmt->fetchColumn();

    if ($areaCount === 0) {
        $defaultAreas = [
            [
                'code'         => 'indoor',
                'name'         => 'Trong nhà (Máy lạnh)',
                'description'  => 'Không gian sang trọng, máy lạnh trung tâm mát mẻ',
                'sort_order'   => 1,
                'status'       => 'active',
                'entrance_pos' => 'bottom_center',
                'entrance_x'   => 50.0,
                'entrance_y'   => 96.0,
                'cashier_x'    => 14.0,
                'cashier_y'    => 6.0,
            ],
            [
                'code'         => 'garden',
                'name'         => 'Sân vườn (Ngoài trời)',
                'description'  => 'Không gian thoáng mát, cảnh quan thiên nhiên trong lành',
                'sort_order'   => 2,
                'status'       => 'active',
                'entrance_pos' => 'left_center',
                'entrance_x'   => 6.0,
                'entrance_y'   => 50.0,
                'cashier_x'    => 14.0,
                'cashier_y'    => 6.0,
            ],
            [
                'code'         => 'vip',
                'name'         => 'Phòng VIP',
                'description'  => 'Không gian riêng tư, cách âm, phục vụ hội nghị và tiệc gia đình',
                'sort_order'   => 3,
                'status'       => 'active',
                'entrance_pos' => 'bottom_right',
                'entrance_x'   => 85.0,
                'entrance_y'   => 96.0,
                'cashier_x'    => 14.0,
                'cashier_y'    => 6.0,
            ],
        ];

        $insertAreaStmt = $pdo->prepare("
            INSERT INTO `restaurant_areas` (`code`, `name`, `description`, `sort_order`, `status`, `entrance_pos`, `entrance_x`, `entrance_y`, `cashier_x`, `cashier_y`)
            VALUES (:code, :name, :description, :sort_order, :status, :entrance_pos, :entrance_x, :entrance_y, :cashier_x, :cashier_y)
        ");

        foreach ($defaultAreas as $a) {
            $insertAreaStmt->execute($a);
        }
    }

    // ==========================================================
    // KHỞI TẠO BẢNG BÀN ĂN (DINING_TABLES)
    // ==========================================================
    $createDiningTableQuery = "
    CREATE TABLE IF NOT EXISTS `dining_tables` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(20) NOT NULL UNIQUE,
        `name` VARCHAR(100) NOT NULL,
        `area_id` INT NOT NULL,
        `capacity` INT NOT NULL DEFAULT 4,
        `pos_x` FLOAT NOT NULL DEFAULT 15,
        `pos_y` FLOAT NOT NULL DEFAULT 20,
        `shape` ENUM('square', 'round', 'rectangle') NOT NULL DEFAULT 'square',
        `width` INT NOT NULL DEFAULT 85,
        `height` INT NOT NULL DEFAULT 85,
        `status` ENUM('active', 'maintenance', 'hidden') NOT NULL DEFAULT 'active',
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_table_area (`area_id`),
        INDEX idx_table_code (`code`),
        INDEX idx_table_status (`status`),
        CONSTRAINT `fk_dining_tables_area` FOREIGN KEY (`area_id`) REFERENCES `restaurant_areas`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($createDiningTableQuery);

    $checkTableStmt = $pdo->query("SELECT COUNT(*) as total FROM `dining_tables`");
    $tableCount = (int) $checkTableStmt->fetchColumn();

    if ($tableCount === 0) {
        $areaMap = [];
        $areaRows = $pdo->query("SELECT id, code FROM `restaurant_areas`")->fetchAll();
        foreach ($areaRows as $ar) {
            $areaMap[$ar['code']] = (int) $ar['id'];
        }

        $defaultTables = [
            ['code' => 'B01', 'name' => 'Bàn B01', 'area_code' => 'indoor', 'capacity' => 4,  'pos_x' => 18, 'pos_y' => 22, 'shape' => 'square',    'width' => 85,  'height' => 85,  'status' => 'active', 'sort_order' => 1],
            ['code' => 'B02', 'name' => 'Bàn B02', 'area_code' => 'indoor', 'capacity' => 4,  'pos_x' => 48, 'pos_y' => 22, 'shape' => 'square',    'width' => 85,  'height' => 85,  'status' => 'active', 'sort_order' => 2],
            ['code' => 'B03', 'name' => 'Bàn B03', 'area_code' => 'indoor', 'capacity' => 4,  'pos_x' => 78, 'pos_y' => 22, 'shape' => 'square',    'width' => 85,  'height' => 85,  'status' => 'active', 'sort_order' => 3],
            ['code' => 'B04', 'name' => 'Bàn B04', 'area_code' => 'indoor', 'capacity' => 2,  'pos_x' => 18, 'pos_y' => 64, 'shape' => 'round',     'width' => 80,  'height' => 80,  'status' => 'active', 'sort_order' => 4],
            ['code' => 'B05', 'name' => 'Bàn B05', 'area_code' => 'garden', 'capacity' => 6,  'pos_x' => 25, 'pos_y' => 28, 'shape' => 'rectangle', 'width' => 115, 'height' => 85,  'status' => 'active', 'sort_order' => 5],
            ['code' => 'B06', 'name' => 'Bàn B06', 'area_code' => 'garden', 'capacity' => 4,  'pos_x' => 68, 'pos_y' => 28, 'shape' => 'round',     'width' => 90,  'height' => 90,  'status' => 'active', 'sort_order' => 6],
            ['code' => 'B07', 'name' => 'Bàn B07', 'area_code' => 'garden', 'capacity' => 4,  'pos_x' => 25, 'pos_y' => 66, 'shape' => 'square',    'width' => 85,  'height' => 85,  'status' => 'active', 'sort_order' => 7],
            ['code' => 'B08', 'name' => 'Bàn B08', 'area_code' => 'garden', 'capacity' => 8,  'pos_x' => 68, 'pos_y' => 66, 'shape' => 'rectangle', 'width' => 135, 'height' => 85,  'status' => 'active', 'sort_order' => 8],
            ['code' => 'B09', 'name' => 'Bàn B09', 'area_code' => 'vip',    'capacity' => 10, 'pos_x' => 28, 'pos_y' => 46, 'shape' => 'rectangle', 'width' => 150, 'height' => 95,  'status' => 'active', 'sort_order' => 9],
            ['code' => 'B10', 'name' => 'Bàn B10', 'area_code' => 'vip',    'capacity' => 12, 'pos_x' => 70, 'pos_y' => 46, 'shape' => 'rectangle', 'width' => 170, 'height' => 100, 'status' => 'active', 'sort_order' => 10],
            ['code' => 'B11', 'name' => 'Bàn B11', 'area_code' => 'indoor', 'capacity' => 4,  'pos_x' => 48, 'pos_y' => 64, 'shape' => 'square',    'width' => 85,  'height' => 85,  'status' => 'active', 'sort_order' => 11],
            ['code' => 'B12', 'name' => 'Bàn B12', 'area_code' => 'indoor', 'capacity' => 6,  'pos_x' => 78, 'pos_y' => 64, 'shape' => 'rectangle', 'width' => 115, 'height' => 85,  'status' => 'active', 'sort_order' => 12],
        ];

        $insertTableStmt = $pdo->prepare("
            INSERT INTO `dining_tables` (`code`, `name`, `area_id`, `capacity`, `pos_x`, `pos_y`, `shape`, `width`, `height`, `status`, `sort_order`)
            VALUES (:code, :name, :area_id, :capacity, :pos_x, :pos_y, :shape, :width, :height, :status, :sort_order)
        ");

        foreach ($defaultTables as $t) {
            $insertTableStmt->execute([
                ':code'       => $t['code'],
                ':name'       => $t['name'],
                ':area_id'    => $areaMap[$t['area_code']] ?? 1,
                ':capacity'   => $t['capacity'],
                ':pos_x'      => $t['pos_x'],
                ':pos_y'      => $t['pos_y'],
                ':shape'      => $t['shape'],
                ':width'      => $t['width'],
                ':height'     => $t['height'],
                ':status'     => $t['status'],
                ':sort_order' => $t['sort_order'],
            ]);
        }
    }

    // ==========================================================
    // KHỞI TẠO & NÂNG CẤP BẢNG ĐẶT BÀN CÔNG KHAI
    // ==========================================================
    ensurePublicReservationsSchema($pdo);

    // ==========================================================
    // KHỞI TẠO & NÂNG CẤP CẤU HÌNH MENU BUFFET & GỌI MÓN LẺ
    // ==========================================================
    ensureMenuConfigSchema($pdo);

    // ==========================================================
    // KHỞI TẠO BẢNG QUẢN LÝ KHO HÀNG (WAREHOUSE)
    // ==========================================================
    ensureWarehouseSchema($pdo);
}

/**
 * Đảm bảo các bảng quản lý tồn kho (products, stock_alerts, stock_transactions) tồn tại trong smartdine_kds.
 */
function ensureWarehouseSchema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `products` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `sku` VARCHAR(50) NOT NULL,
            `category` VARCHAR(100) NULL DEFAULT NULL,
            `quantity` INT(11) NOT NULL DEFAULT 0,
            `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_products_sku` (`sku`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `stock_alerts` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `product_id` INT(11) NOT NULL,
            `min_quantity` INT(11) NOT NULL DEFAULT 5,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_stock_alerts_product` (`product_id`),
            CONSTRAINT `fk_stock_alerts_product`
                FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `stock_transactions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `product_id` INT(11) NOT NULL,
            `transaction_type` ENUM('in', 'out') NOT NULL,
            `quantity` INT(11) NOT NULL,
            `reason` VARCHAR(255) DEFAULT NULL,
            `reference` VARCHAR(100) DEFAULT NULL,
            `created_by` VARCHAR(100) DEFAULT 'admin',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_stock_tx_product` (`product_id`),
            KEY `idx_stock_tx_created_at` (`created_at`),
            CONSTRAINT `fk_stock_tx_product`
                FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $done = true;
}

/**
 * Đảm bảo bảng `public_reservations` tồn tại và hỗ trợ trạng thái `seated` + `seated_at`.
 */
function ensurePublicReservationsSchema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `public_reservations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `customer_name` VARCHAR(120) NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `email` VARCHAR(120) DEFAULT NULL,
            `guests` INT NOT NULL,
            `reservation_date` DATE NOT NULL,
            `reservation_time` TIME NOT NULL,
            `duration_minutes` INT NOT NULL DEFAULT 120,
            `area_id` INT NOT NULL,
            `table_id` INT NOT NULL,
            `note` TEXT DEFAULT NULL,
            `status` ENUM('pending', 'confirmed', 'seated', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending',
            `seated_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_reservation_date` (`reservation_date`),
            INDEX `idx_reservation_phone` (`phone`),
            INDEX `idx_reservation_table` (`table_id`),
            INDEX `idx_reservation_status` (`status`),
            INDEX `idx_reservation_area` (`area_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    try {
        $colStatus = $pdo->query("SHOW COLUMNS FROM `public_reservations` LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if ($colStatus && stripos((string) ($colStatus['Type'] ?? ''), "'seated'") === false) {
            $pdo->exec("
                ALTER TABLE `public_reservations`
                MODIFY COLUMN `status` ENUM('pending', 'confirmed', 'seated', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending'
            ");
        }

        $colSeatedAt = $pdo->query("SHOW COLUMNS FROM `public_reservations` LIKE 'seated_at'")->fetch(PDO::FETCH_ASSOC);
        if (!$colSeatedAt) {
            $pdo->exec("
                ALTER TABLE `public_reservations`
                ADD COLUMN `seated_at` DATETIME DEFAULT NULL AFTER `status`
            ");
        }
    } catch (PDOException $e) {
        error_log('ensurePublicReservationsSchema alter error: ' . $e->getMessage());
    }

    $done = true;
}

/**
 * Đảm bảo schema cho Gói Buffet (buffet_packages) & phân loại Menu (Buffet / Gọi món lẻ) trên categories, dishes.
 */
function ensureMenuConfigSchema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `buffet_packages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `price_per_person` DECIMAL(12, 0) NOT NULL DEFAULT 0,
            `image` VARCHAR(500) DEFAULT NULL,
            `description` VARCHAR(500) DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_buffet_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    try {
        $colCatType = $pdo->query("SHOW COLUMNS FROM `categories` LIKE 'menu_type'")->fetch(PDO::FETCH_ASSOC);
        if (!$colCatType) {
            $pdo->exec("
                ALTER TABLE `categories`
                ADD COLUMN `menu_type` ENUM('alacarte', 'buffet', 'both') NOT NULL DEFAULT 'alacarte' AFTER `slug`
            ");
        }

        $colDishType = $pdo->query("SHOW COLUMNS FROM `dishes` LIKE 'menu_type'")->fetch(PDO::FETCH_ASSOC);
        if (!$colDishType) {
            $pdo->exec("
                ALTER TABLE `dishes`
                ADD COLUMN `menu_type` ENUM('alacarte', 'buffet') NOT NULL DEFAULT 'alacarte' AFTER `category_id`,
                ADD INDEX `idx_dish_menu_type` (`menu_type`)
            ");
        }

        $colPkgId = $pdo->query("SHOW COLUMNS FROM `dishes` LIKE 'buffet_package_id'")->fetch(PDO::FETCH_ASSOC);
        if (!$colPkgId) {
            $pdo->exec("
                ALTER TABLE `dishes`
                ADD COLUMN `buffet_package_id` INT DEFAULT NULL AFTER `menu_type`,
                ADD INDEX `idx_dish_buffet_pkg` (`buffet_package_id`)
            ");
        }
    } catch (PDOException $e) {
        error_log('ensureMenuConfigSchema alter error: ' . $e->getMessage());
    }

    $pkgCount = (int) $pdo->query("SELECT COUNT(*) FROM `buffet_packages`")->fetchColumn();
    if ($pkgCount === 0 && smartdineAllowDemoMenuSeed()) {
        $stmtPkg = $pdo->prepare("
            INSERT INTO `buffet_packages` (`name`, `price_per_person`, `image`, `description`, `sort_order`, `status`)
            VALUES (:name, :price_per_person, :image, :description, :sort_order, :status)
        ");
        $stmtPkg->execute([
            ':name'             => 'BUFFET XÈO XÈO CLASSIC',
            ':price_per_person' => 529000,
            ':image'            => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=900&q=80',
            ':description'      => 'Thỏa sức thưởng thức các món thịt nướng Hàn Quốc, hải sản và lẩu đặc trưng.',
            ':sort_order'       => 1,
            ':status'           => 'active',
        ]);
        $defaultPkgId = (int) $pdo->lastInsertId();

        $buffetDishCount = (int) $pdo->query("SELECT COUNT(*) FROM `dishes` WHERE `menu_type` = 'buffet'")->fetchColumn();
        if ($buffetDishCount === 0) {
            $seedCats = [
                ['name' => 'SPECIAL OFFER - MÓN TẶNG', 'slug' => 'special-offer-buffet', 'menu_type' => 'buffet', 'icon' => '★', 'sort_order' => 10],
                ['name' => 'THỊT HEO NƯỚNG HÀN', 'slug' => 'thit-heo-nuong-buffet', 'menu_type' => 'buffet', 'icon' => '♨', 'sort_order' => 11],
            ];
            $catIds = [];
            foreach ($seedCats as $sc) {
                $stmtFindCat = $pdo->prepare("SELECT `id` FROM `categories` WHERE `slug` = ? LIMIT 1");
                $stmtFindCat->execute([$sc['slug']]);
                $existingCatId = (int) $stmtFindCat->fetchColumn();
                if ($existingCatId > 0) {
                    $catIds[$sc['slug']] = $existingCatId;
                } else {
                    $stmtInsCat = $pdo->prepare("
                        INSERT INTO `categories` (`name`, `slug`, `menu_type`, `icon`, `description`, `sort_order`, `status`)
                        VALUES (:name, :slug, :menu_type, :icon, '', :sort_order, 'active')
                    ");
                    $stmtInsCat->execute($sc);
                    $catIds[$sc['slug']] = (int) $pdo->lastInsertId();
                }
            }

            $seedBuffetDishes = [
                [
                    'category_id'       => $catIds['special-offer-buffet'],
                    'menu_type'         => 'buffet',
                    'buffet_package_id' => $defaultPkgId,
                    'name'              => 'Đĩa mời size L',
                    'price'             => 0,
                    'image'             => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=700&q=80',
                    'description'       => 'Đĩa thịt tổng hợp đặc biệt tặng kèm theo gói Buffet.',
                    'tag'               => 'Món tặng',
                    'station'           => 'hot',
                    'status'            => 'active',
                    'rating'            => 4.9,
                ],
                [
                    'category_id'       => $catIds['special-offer-buffet'],
                    'menu_type'         => 'buffet',
                    'buffet_package_id' => $defaultPkgId,
                    'name'              => 'Đĩa mời size M',
                    'price'             => 0,
                    'image'             => 'https://images.unsplash.com/photo-1558030006-450675393462?auto=format&fit=crop&w=700&q=80',
                    'description'       => 'Đĩa thịt bò & ba chỉ tuyển chọn dành cho nhóm 2-3 khách.',
                    'tag'               => 'Món tặng',
                    'station'           => 'hot',
                    'status'            => 'active',
                    'rating'            => 4.8,
                ],
                [
                    'category_id'       => $catIds['thit-heo-nuong-buffet'],
                    'menu_type'         => 'buffet',
                    'buffet_package_id' => $defaultPkgId,
                    'name'              => 'Ba chỉ heo cuốn phomai',
                    'price'             => 0,
                    'image'             => 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?auto=format&fit=crop&w=700&q=80',
                    'description'       => 'Ba chỉ heo nướng thơm lừng chấm cùng phô mai béo ngậy.',
                    'tag'               => 'Buffet',
                    'station'           => 'hot',
                    'status'            => 'active',
                    'rating'            => 4.9,
                ],
                [
                    'category_id'       => $catIds['thit-heo-nuong-buffet'],
                    'menu_type'         => 'buffet',
                    'buffet_package_id' => $defaultPkgId,
                    'name'              => 'Ba chỉ heo bào cuộn xốt Gochujang',
                    'price'             => 0,
                    'image'             => 'https://images.unsplash.com/photo-1600891964092-4316c288032e?auto=format&fit=crop&w=700&q=80',
                    'description'       => 'Thịt ba chỉ thái mỏng thấm đẫm sốt ớt Hàn Quốc đậm vị.',
                    'tag'               => 'Buffet',
                    'station'           => 'hot',
                    'status'            => 'active',
                    'rating'            => 4.8,
                ],
            ];
            $stmtInsDish = $pdo->prepare("
                INSERT INTO `dishes` (`category_id`, `menu_type`, `buffet_package_id`, `name`, `price`, `image`, `description`, `tag`, `station`, `status`, `rating`)
                VALUES (:category_id, :menu_type, :buffet_package_id, :name, :price, :image, :description, :tag, :station, :status, :rating)
            ");
            foreach ($seedBuffetDishes as $sd) {
                $stmtInsDish->execute($sd);
            }
        }
    }

    $done = true;
}

/**
 * ==========================================================
 * CÁC HÀM TRUY VẤN VÀ THAO TÁC VỚI BẢNG USERS
 * ==========================================================
 */

function getUserByUsername($username) {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    return $stmt->fetch() ?: null;
}

function getUserById($id) {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `id` = :id LIMIT 1");
    $stmt->execute([':id' => (int) $id]);
    return $stmt->fetch() ?: null;
}

function getAllUsers($filterRole = '', $filterStatus = '', $search = '') {
    $pdo = getDbConnection();
    if (!$pdo) return [];

    $sql = "SELECT id, username, fullname, role, phone, email, status, last_login, created_at, updated_at FROM `users` WHERE 1=1";
    $params = [];

    if (!empty($filterRole)) {
        $sql .= " AND `role` = :role";
        $params[':role'] = $filterRole;
    }

    if (!empty($filterStatus)) {
        $sql .= " AND `status` = :status";
        $params[':status'] = $filterStatus;
    }

    if (!empty($search)) {
        $sql .= " AND (`fullname` LIKE :search1 OR `username` LIKE :search2 OR `phone` LIKE :search3 OR `email` LIKE :search4)";
        $term = "%" . trim($search) . "%";
        $params[':search1'] = $term;
        $params[':search2'] = $term;
        $params[':search3'] = $term;
        $params[':search4'] = $term;
    }

    $sql .= " ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function createUser($data) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    // Kiểm tra username đã tồn tại chưa
    $check = getUserByUsername($data['username']);
    if ($check) {
        return ['success' => false, 'message' => 'Tên đăng nhập "' . htmlspecialchars($data['username']) . '" đã tồn tại!'];
    }

    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO `users` (`username`, `password`, `fullname`, `role`, `phone`, `email`, `status`)
        VALUES (:username, :password, :fullname, :role, :phone, :email, :status)
    ");

    try {
        $stmt->execute([
            ':username' => trim($data['username']),
            ':password' => $hashedPassword,
            ':fullname' => trim($data['fullname']),
            ':role'     => $data['role'] ?? 'staff',
            ':phone'    => trim($data['phone'] ?? ''),
            ':email'    => trim($data['email'] ?? ''),
            ':status'   => $data['status'] ?? 'active',
        ]);
        return ['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Thêm nhân viên thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi thêm tài khoản: ' . $e->getMessage()];
    }
}

function updateUser($id, $data) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $user = getUserById($id);
    if (!$user) {
        return ['success' => false, 'message' => 'Không tìm thấy tài khoản nhân viên!'];
    }

    // Nếu thay đổi username, kiểm tra trùng
    if (!empty($data['username']) && $data['username'] !== $user['username']) {
        $check = getUserByUsername($data['username']);
        if ($check && $check['id'] != $id) {
            return ['success' => false, 'message' => 'Tên đăng nhập đã được sử dụng bởi tài khoản khác!'];
        }
    }

    $sql = "UPDATE `users` SET 
                `fullname` = :fullname, 
                `role` = :role, 
                `phone` = :phone, 
                `email` = :email, 
                `status` = :status";
    $params = [
        ':fullname' => trim($data['fullname']),
        ':role'     => $data['role'] ?? $user['role'],
        ':phone'    => trim($data['phone'] ?? ''),
        ':email'    => trim($data['email'] ?? ''),
        ':status'   => $data['status'] ?? $user['status'],
        ':id'       => (int) $id,
    ];

    if (!empty($data['username'])) {
        $sql .= ", `username` = :username";
        $params[':username'] = trim($data['username']);
    }

    // Nếu có đổi mật khẩu
    if (!empty($data['password'])) {
        $sql .= ", `password` = :password";
        $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    $sql .= " WHERE `id` = :id";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['success' => true, 'message' => 'Cập nhật tài khoản thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi cập nhật: ' . $e->getMessage()];
    }
}

function updateUserPassword($id, $newPassword) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE `users` SET `password` = :password WHERE `id` = :id");
    try {
        $stmt->execute([':password' => $hash, ':id' => (int) $id]);
        return ['success' => true, 'message' => 'Đổi mật khẩu thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi đổi mật khẩu: ' . $e->getMessage()];
    }
}

function toggleUserStatus($id) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $user = getUserById($id);
    if (!$user) return ['success' => false, 'message' => 'Không tìm thấy tài khoản!'];

    $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
    $stmt = $pdo->prepare("UPDATE `users` SET `status` = :status WHERE `id` = :id");
    try {
        $stmt->execute([':status' => $newStatus, ':id' => (int) $id]);
        $statusText = $newStatus === 'active' ? 'Đã kích hoạt hoạt động' : 'Đã khóa tài khoản';
        return ['success' => true, 'new_status' => $newStatus, 'message' => $statusText . ' thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi cập nhật trạng thái: ' . $e->getMessage()];
    }
}

function deleteUser($id) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $stmt = $pdo->prepare("DELETE FROM `users` WHERE `id` = :id");
    try {
        $stmt->execute([':id' => (int) $id]);
        return ['success' => true, 'message' => 'Đã xóa tài khoản nhân viên thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi xóa tài khoản: ' . $e->getMessage()];
    }
}

function updateLastLogin($id) {
    $pdo = getDbConnection();
    if (!$pdo) return;

    $stmt = $pdo->prepare("UPDATE `users` SET `last_login` = NOW() WHERE `id` = :id");
    $stmt->execute([':id' => (int) $id]);
}

function ensureUsersRoleSchema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $colRole = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
        if ($colRole && stripos((string) ($colRole['Type'] ?? ''), "'shift_manager'") === false) {
            $pdo->exec("
                ALTER TABLE `users`
                MODIFY COLUMN `role` ENUM('admin', 'shift_manager', 'staff', 'kitchen') NOT NULL DEFAULT 'staff'
            ");
        }
    } catch (PDOException $e) {
        error_log('ensureUsersRoleSchema error: ' . $e->getMessage());
    }
    $done = true;
}

function getUserStats() {
    $pdo = getDbConnection();
    if (!$pdo) {
        return ['total' => 0, 'admin' => 0, 'shift_manager' => 0, 'staff' => 0, 'kitchen' => 0, 'active' => 0, 'inactive' => 0];
    }

    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN `role` = 'admin' THEN 1 ELSE 0 END) as admin_count,
            SUM(CASE WHEN `role` = 'shift_manager' THEN 1 ELSE 0 END) as shift_manager_count,
            SUM(CASE WHEN `role` = 'staff' THEN 1 ELSE 0 END) as staff_count,
            SUM(CASE WHEN `role` = 'kitchen' THEN 1 ELSE 0 END) as kitchen_count,
            SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN `status` = 'inactive' THEN 1 ELSE 0 END) as inactive_count
        FROM `users`
    ");
    $row = $stmt->fetch();
    return [
        'total'         => (int) ($row['total'] ?? 0),
        'admin'         => (int) ($row['admin_count'] ?? 0),
        'shift_manager' => (int) ($row['shift_manager_count'] ?? 0),
        'staff'         => (int) ($row['staff_count'] ?? 0),
        'kitchen'       => (int) ($row['kitchen_count'] ?? 0),
        'active'        => (int) ($row['active_count'] ?? 0),
        'inactive'      => (int) ($row['inactive_count'] ?? 0),
    ];
}

/**
 * ==========================================================
 * CÁC HÀM TIỆN ÍCH DÀNH CHO MÓN ĂN & DANH MỤC
 * ==========================================================
 */

/**
 * Tạo slug chuẩn từ chuỗi Tiếng Việt (ví dụ: "Món chính" -> "mon-chinh")
 */
function createSlug($str) {
    $str = mb_strtolower(trim($str), 'UTF-8');
    $unicode = [
        'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
        'd' => 'đ',
        'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
        'i' => 'í|ì|ỉ|ĩ|ị',
        'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
        'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
        'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
    ];
    foreach ($unicode as $nonUnicode => $uni) {
        $str = preg_replace("/($uni)/i", $nonUnicode, $str);
    }
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', $str);
    return trim($str, '-');
}

/**
 * Định dạng tiền tệ VNĐ chuẩn (ví dụ: 75.000đ)
 */
function formatPrice($price) {
    return number_format((float) $price, 0, ',', '.') . 'đ';
}

/**
 * Lấy đường dẫn ảnh hợp lệ (hỗ trợ cả ảnh upload và link internet)
 */
function getDishImageUrl($imagePath) {
    if (empty($imagePath)) {
        return 'assets/images/dish-placeholder.svg';
    }
    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return htmlspecialchars($imagePath);
    }
    return htmlspecialchars($imagePath);
}

/**
 * Lấy tên trạm chế biến KDS bằng tiếng Việt
 */
function getStationName($station) {
    switch ($station) {
        case 'hot':
            return 'Bếp nóng (Xào/Nướng)';
        case 'soup':
            return 'Bếp súp / Lẩu';
        case 'cold':
            return 'Bếp lạnh / Salad';
        case 'bar':
            return 'Quầy Bar / Nước';
        default:
            return 'Bếp nóng';
    }
}

/**
 * Trả về HTML badge trạng thái món ăn
 */
function getDishStatusBadgeHtml($status) {
    switch ($status) {
        case 'active':
            return '<span class="status-badge status-active"><span class="status-indicator"></span> Đang mở bán</span>';
        case 'out_of_stock':
            return '<span class="status-badge status-warning"><span class="status-indicator"></span> Tạm hết</span>';
        case 'hidden':
        default:
            return '<span class="status-badge status-inactive"><span class="status-indicator"></span> Đang ẩn</span>';
    }
}

/**
 * Xử lý tải ảnh món ăn lên máy chủ hoặc sử dụng URL ảnh
 */
function handleDishImageUpload($file, $imageUrl = '', $currentImage = '') {
    // 1. Nếu có tệp tải lên từ máy tính
    if (!empty($file) && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $fileInfo = pathinfo($file['name']);
        $ext = strtolower($fileInfo['extension'] ?? '');

        if (!in_array($ext, $allowedExtensions, true)) {
            return ['success' => false, 'message' => 'Định dạng ảnh không hỗ trợ! Chỉ chấp nhận JPG, PNG, WEBP, GIF.'];
        }

        // Kiểm tra dung lượng tối đa 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Dung lượng ảnh quá lớn! Vui lòng chọn ảnh dưới 5MB.'];
        }

        $uploadDir = __DIR__ . '/../uploads/dishes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newFileName = 'dish_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            // Xóa ảnh cũ nếu là ảnh nội bộ và khác ảnh mới
            if (!empty($currentImage) && strpos($currentImage, 'uploads/dishes/') === 0) {
                $oldFilePath = __DIR__ . '/../' . $currentImage;
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }
            }
            return ['success' => true, 'path' => 'uploads/dishes/' . $newFileName];
        } else {
            return ['success' => false, 'message' => 'Lỗi lưu trữ ảnh vào máy chủ!'];
        }
    }

    // 2. Nếu người dùng nhập URL ảnh trực tuyến
    $trimmedUrl = trim($imageUrl);
    if (!empty($trimmedUrl)) {
        return ['success' => true, 'path' => $trimmedUrl];
    }

    // 3. Giữ nguyên ảnh hiện tại
    return ['success' => true, 'path' => $currentImage];
}

/**
 * ==========================================================
 * CÁC HÀM TRUY VẤN VÀ THAO TÁC VỚI BẢNG CATEGORIES (DANH MỤC)
 * ==========================================================
 */

function getMenuTypeLabel($menuType): string {
    switch ($menuType) {
        case 'buffet':
            return 'Buffet';
        case 'both':
            return 'Buffet & Gọi lẻ';
        case 'alacarte':
        default:
            return 'Gọi món lẻ';
    }
}

function getMenuTypeBadgeHtml($menuType): string {
    if ($menuType === 'buffet') {
        return '<span class="status-badge" style="background:#fef2f2;color:#b91c1c;"><span class="status-indicator" style="background:#dc2626;"></span> Buffet</span>';
    }
    if ($menuType === 'both') {
        return '<span class="status-badge" style="background:#eff6ff;color:#1d4ed8;"><span class="status-indicator" style="background:#2563eb;"></span> Cả hai</span>';
    }
    return '<span class="status-badge" style="background:#f8fafc;color:#334155;border:1px solid #e2e8f0;"><span class="status-indicator" style="background:#64748b;"></span> Gọi món lẻ</span>';
}

function getAllBuffetPackages($onlyActive = false): array {
    $pdo = getDbConnection();
    if (!$pdo) return [];

    $sql = "
        SELECT bp.*,
               (SELECT COUNT(*) FROM `dishes` d WHERE d.`menu_type` = 'buffet' AND (d.`buffet_package_id` = bp.`id` OR d.`buffet_package_id` IS NULL)) AS dish_count
        FROM `buffet_packages` bp
        WHERE 1=1
    ";
    if ($onlyActive) {
        $sql .= " AND bp.`status` = 'active'";
    }
    $sql .= " ORDER BY bp.`sort_order` ASC, bp.`id` ASC";
    return $pdo->query($sql)->fetchAll();
}

function getBuffetPackageById($id): ?array {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT * FROM `buffet_packages` WHERE `id` = :id LIMIT 1");
    $stmt->execute([':id' => (int) $id]);
    return $stmt->fetch() ?: null;
}

function createBuffetPackage($data): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $name = trim($data['name'] ?? '');
    if ($name === '') {
        return ['success' => false, 'message' => 'Tên gói Buffet không được để trống!'];
    }
    $price = max(0, (float) ($data['price_per_person'] ?? 0));

    $stmt = $pdo->prepare("
        INSERT INTO `buffet_packages` (`name`, `price_per_person`, `image`, `description`, `sort_order`, `status`)
        VALUES (:name, :price, :image, :description, :sort_order, :status)
    ");
    try {
        $stmt->execute([
            ':name'        => $name,
            ':price'       => $price,
            ':image'       => trim($data['image'] ?? ''),
            ':description' => trim($data['description'] ?? ''),
            ':sort_order'  => (int) ($data['sort_order'] ?? 0),
            ':status'      => in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : 'active',
        ]);
        return ['success' => true, 'id' => (int) $pdo->lastInsertId(), 'message' => 'Đã thêm gói Buffet "' . htmlspecialchars($name) . '" thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi thêm gói Buffet: ' . $e->getMessage()];
    }
}

function updateBuffetPackage($id, $data): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $pkg = getBuffetPackageById($id);
    if (!$pkg) {
        return ['success' => false, 'message' => 'Không tìm thấy gói Buffet!'];
    }

    $name = trim($data['name'] ?? '');
    if ($name === '') {
        return ['success' => false, 'message' => 'Tên gói Buffet không được để trống!'];
    }
    $price = max(0, (float) ($data['price_per_person'] ?? $pkg['price_per_person']));

    $stmt = $pdo->prepare("
        UPDATE `buffet_packages`
        SET `name` = :name,
            `price_per_person` = :price,
            `image` = :image,
            `description` = :description,
            `sort_order` = :sort_order,
            `status` = :status
        WHERE `id` = :id
    ");
    try {
        $stmt->execute([
            ':name'        => $name,
            ':price'       => $price,
            ':image'       => isset($data['image']) ? trim($data['image']) : $pkg['image'],
            ':description' => trim($data['description'] ?? $pkg['description']),
            ':sort_order'  => (int) ($data['sort_order'] ?? $pkg['sort_order']),
            ':status'      => in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : $pkg['status'],
            ':id'          => (int) $id,
        ]);
        return ['success' => true, 'message' => 'Cập nhật gói Buffet "' . htmlspecialchars($name) . '" thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi cập nhật gói Buffet: ' . $e->getMessage()];
    }
}

function deleteBuffetPackage($id): array {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $pkg = getBuffetPackageById($id);
    if (!$pkg) {
        return ['success' => false, 'message' => 'Không tìm thấy gói Buffet!'];
    }

    try {
        $pdo->prepare("UPDATE `dishes` SET `buffet_package_id` = NULL WHERE `buffet_package_id` = :id")
            ->execute([':id' => (int) $id]);
        $pdo->prepare("DELETE FROM `buffet_packages` WHERE `id` = :id")
            ->execute([':id' => (int) $id]);
        return ['success' => true, 'message' => 'Đã xóa gói Buffet "' . htmlspecialchars($pkg['name']) . '"!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi xóa gói Buffet: ' . $e->getMessage()];
    }
}

function getAllCategories($onlyActive = false, $menuType = '') {
    $pdo = getDbConnection();
    if (!$pdo) return [];

    $sql = "SELECT c.*, COUNT(d.id) AS dish_count 
            FROM `categories` c 
            LEFT JOIN `dishes` d ON c.id = d.category_id 
            WHERE 1=1";
    $params = [];
    if ($onlyActive) {
        $sql .= " AND c.`status` = 'active'";
    }
    if ($menuType === 'buffet' || $menuType === 'alacarte') {
        $sql .= " AND c.`menu_type` IN (:menu_type, 'both')";
        $params[':menu_type'] = $menuType;
    }
    $sql .= " GROUP BY c.id ORDER BY c.sort_order ASC, c.id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getCategoryById($id) {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT * FROM `categories` WHERE `id` = :id LIMIT 1");
    $stmt->execute([':id' => (int) $id]);
    return $stmt->fetch() ?: null;
}

function getCategoryBySlug($slug) {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT * FROM `categories` WHERE `slug` = :slug LIMIT 1");
    $stmt->execute([':slug' => trim($slug)]);
    return $stmt->fetch() ?: null;
}

function createCategory($data) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $name = trim($data['name'] ?? '');
    if (empty($name)) {
        return ['success' => false, 'message' => 'Tên danh mục không được để trống!'];
    }

    $slug = trim($data['slug'] ?? '');
    if (empty($slug)) {
        $slug = createSlug($name);
    } else {
        $slug = createSlug($slug);
    }

    // Kiểm tra trùng slug
    $check = getCategoryBySlug($slug);
    if ($check) {
        $slug .= '-' . time();
    }

    $sql = "INSERT INTO `categories` (`name`, `slug`, `menu_type`, `icon`, `description`, `sort_order`, `status`)
            VALUES (:name, :slug, :menu_type, :icon, :description, :sort_order, :status)";
    $stmt = $pdo->prepare($sql);

    try {
        $menuType = in_array($data['menu_type'] ?? '', ['alacarte', 'buffet', 'both'], true) ? $data['menu_type'] : 'alacarte';
        $stmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
            ':menu_type'   => $menuType,
            ':icon'        => trim($data['icon'] ?? '✦'),
            ':description' => trim($data['description'] ?? ''),
            ':sort_order'  => (int) ($data['sort_order'] ?? 0),
            ':status'      => $data['status'] ?? 'active'
        ]);
        return ['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Thêm danh mục "' . htmlspecialchars($name) . '" thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi thêm danh mục: ' . $e->getMessage()];
    }
}

function updateCategory($id, $data) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $category = getCategoryById($id);
    if (!$category) {
        return ['success' => false, 'message' => 'Không tìm thấy danh mục cần sửa!'];
    }

    $name = trim($data['name'] ?? '');
    if (empty($name)) {
        return ['success' => false, 'message' => 'Tên danh mục không được để trống!'];
    }

    $slug = trim($data['slug'] ?? '');
    if (empty($slug)) {
        $slug = createSlug($name);
    } else {
        $slug = createSlug($slug);
    }

    // Kiểm tra trùng slug với danh mục khác
    $check = getCategoryBySlug($slug);
    if ($check && (int) $check['id'] !== (int) $id) {
        $slug .= '-' . time();
    }

    $sql = "UPDATE `categories` SET 
                `name` = :name, 
                `slug` = :slug, 
                `menu_type` = :menu_type,
                `icon` = :icon, 
                `description` = :description, 
                `sort_order` = :sort_order, 
                `status` = :status 
            WHERE `id` = :id";
    $stmt = $pdo->prepare($sql);

    try {
        $menuType = in_array($data['menu_type'] ?? '', ['alacarte', 'buffet', 'both'], true)
            ? $data['menu_type']
            : ($category['menu_type'] ?? 'alacarte');
        $stmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
            ':menu_type'   => $menuType,
            ':icon'        => trim($data['icon'] ?? '✦'),
            ':description' => trim($data['description'] ?? ''),
            ':sort_order'  => (int) ($data['sort_order'] ?? 0),
            ':status'      => $data['status'] ?? 'active',
            ':id'          => (int) $id
        ]);
        return ['success' => true, 'message' => 'Cập nhật danh mục thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi cập nhật danh mục: ' . $e->getMessage()];
    }
}

function deleteCategory($id) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $category = getCategoryById($id);
    if (!$category) {
        return ['success' => false, 'message' => 'Không tìm thấy danh mục cần xóa!'];
    }

    // Kiểm tra xem danh mục có đang chứa món ăn nào không
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `dishes` WHERE `category_id` = :id");
    $stmtCheck->execute([':id' => (int) $id]);
    $dishCount = (int) $stmtCheck->fetchColumn();

    if ($dishCount > 0) {
        return [
            'success' => false, 
            'message' => 'Không thể xóa danh mục "' . htmlspecialchars($category['name']) . '" vì đang chứa ' . $dishCount . ' món ăn! Vui lòng xóa hoặc chuyển các món này sang danh mục khác trước.'
        ];
    }

    $stmt = $pdo->prepare("DELETE FROM `categories` WHERE `id` = :id");
    try {
        $stmt->execute([':id' => (int) $id]);
        return ['success' => true, 'message' => 'Đã xóa danh mục "' . htmlspecialchars($category['name']) . '" thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi xóa danh mục: ' . $e->getMessage()];
    }
}

function toggleCategoryStatus($id) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $cat = getCategoryById($id);
    if (!$cat) return ['success' => false, 'message' => 'Không tìm thấy danh mục!'];

    $newStatus = ($cat['status'] === 'active') ? 'inactive' : 'active';
    $stmt = $pdo->prepare("UPDATE `categories` SET `status` = :status WHERE `id` = :id");
    try {
        $stmt->execute([':status' => $newStatus, ':id' => (int) $id]);
        $msg = ($newStatus === 'active') ? 'Đã kích hoạt danh mục' : 'Đã ẩn danh mục';
        return ['success' => true, 'new_status' => $newStatus, 'message' => $msg . ' thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi đổi trạng thái: ' . $e->getMessage()];
    }
}

/**
 * ==========================================================
 * CÁC HÀM TRUY VẤN VÀ THAO TÁC VỚI BẢNG DISHES (MÓN ĂN & GIÁ)
 * ==========================================================
 */

function getAllDishes($filterCategory = '', $filterStatus = '', $search = '', $filterStation = '', $filterMenuType = '') {
    $pdo = getDbConnection();
    if (!$pdo) return [];

    $sql = "SELECT d.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon, c.menu_type AS category_menu_type, bp.name AS buffet_package_name
            FROM `dishes` d
            JOIN `categories` c ON d.category_id = c.id
            LEFT JOIN `buffet_packages` bp ON d.buffet_package_id = bp.id
            WHERE 1=1";
    $params = [];

    if ($filterMenuType === 'buffet' || $filterMenuType === 'alacarte') {
        $sql .= " AND d.menu_type = :menu_type";
        $params[':menu_type'] = $filterMenuType;
    }

    if (!empty($filterCategory)) {
        if (is_numeric($filterCategory)) {
            $sql .= " AND d.category_id = :cat_id";
            $params[':cat_id'] = (int) $filterCategory;
        } else {
            $sql .= " AND c.slug = :cat_slug";
            $params[':cat_slug'] = trim($filterCategory);
        }
    }

    if (!empty($filterStatus)) {
        if ($filterStatus === 'visible') {
            // Cho phép hiển thị cả món active và out_of_stock trên menu khách
            $sql .= " AND d.status IN ('active', 'out_of_stock')";
        } else {
            $sql .= " AND d.status = :status";
            $params[':status'] = trim($filterStatus);
        }
    }

    if (!empty($filterStation)) {
        $sql .= " AND d.station = :station";
        $params[':station'] = trim($filterStation);
    }

    if (!empty($search)) {
        $sql .= " AND (d.name LIKE :search1 OR d.description LIKE :search2 OR d.tag LIKE :search3 OR c.name LIKE :search4)";
        $term = "%" . trim($search) . "%";
        $params[':search1'] = $term;
        $params[':search2'] = $term;
        $params[':search3'] = $term;
        $params[':search4'] = $term;
    }

    $sql .= " ORDER BY c.sort_order ASC, d.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getDishById($id) {
    $pdo = getDbConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("
        SELECT d.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon, bp.name AS buffet_package_name
        FROM `dishes` d
        JOIN `categories` c ON d.category_id = c.id
        LEFT JOIN `buffet_packages` bp ON d.buffet_package_id = bp.id
        WHERE d.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => (int) $id]);
    return $stmt->fetch() ?: null;
}

function createDish($data) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $name = trim($data['name'] ?? '');
    if (empty($name)) {
        return ['success' => false, 'message' => 'Tên món ăn không được để trống!'];
    }

    $categoryId = (int) ($data['category_id'] ?? 0);
    if ($categoryId <= 0) {
        return ['success' => false, 'message' => 'Vui lòng chọn danh mục cho món ăn!'];
    }

    $price = (float) ($data['price'] ?? 0);
    if ($price < 0) {
        return ['success' => false, 'message' => 'Giá món ăn không hợp lệ!'];
    }

    $menuType        = ($data['menu_type'] ?? '') === 'buffet' ? 'buffet' : 'alacarte';
    $buffetPackageId = ($menuType === 'buffet' && !empty($data['buffet_package_id']))
        ? (int) $data['buffet_package_id']
        : null;

    $sql = "INSERT INTO `dishes` (`category_id`, `menu_type`, `buffet_package_id`, `name`, `price`, `image`, `description`, `tag`, `station`, `status`, `rating`)
            VALUES (:category_id, :menu_type, :buffet_package_id, :name, :price, :image, :description, :tag, :station, :status, :rating)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':category_id'       => $categoryId,
            ':menu_type'         => $menuType,
            ':buffet_package_id' => $buffetPackageId,
            ':name'              => $name,
            ':price'             => $price,
            ':image'             => trim($data['image'] ?? ''),
            ':description'       => trim($data['description'] ?? ''),
            ':tag'               => !empty($data['tag']) ? trim($data['tag']) : null,
            ':station'           => $data['station'] ?? 'hot',
            ':status'            => $data['status'] ?? 'active',
            ':rating'            => !empty($data['rating']) ? (float) $data['rating'] : 5.0,
        ]);
        return ['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Thêm món ăn "' . htmlspecialchars($name) . '" thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi thêm món ăn: ' . $e->getMessage()];
    }
}

function updateDish($id, $data) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $dish = getDishById($id);
    if (!$dish) {
        return ['success' => false, 'message' => 'Không tìm thấy món ăn cần cập nhật!'];
    }

    $name = trim($data['name'] ?? '');
    if (empty($name)) {
        return ['success' => false, 'message' => 'Tên món ăn không được để trống!'];
    }

    $categoryId = (int) ($data['category_id'] ?? $dish['category_id']);
    $price = isset($data['price']) ? (float) $data['price'] : (float) $dish['price'];
    if ($price < 0) {
        return ['success' => false, 'message' => 'Giá món ăn không hợp lệ!'];
    }

    $menuType = in_array($data['menu_type'] ?? '', ['alacarte', 'buffet'], true)
        ? $data['menu_type']
        : ($dish['menu_type'] ?? 'alacarte');
    $buffetPackageId = ($menuType === 'buffet')
        ? (!empty($data['buffet_package_id']) ? (int) $data['buffet_package_id'] : null)
        : null;

    $sql = "UPDATE `dishes` SET 
                `category_id` = :category_id,
                `menu_type` = :menu_type,
                `buffet_package_id` = :buffet_package_id,
                `name` = :name,
                `price` = :price,
                `image` = :image,
                `description` = :description,
                `tag` = :tag,
                `station` = :station,
                `status` = :status,
                `rating` = :rating
            WHERE `id` = :id";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':category_id'       => $categoryId,
            ':menu_type'         => $menuType,
            ':buffet_package_id' => $buffetPackageId,
            ':name'              => $name,
            ':price'             => $price,
            ':image'             => isset($data['image']) ? trim($data['image']) : $dish['image'],
            ':description'       => trim($data['description'] ?? $dish['description']),
            ':tag'               => !empty($data['tag']) ? trim($data['tag']) : null,
            ':station'           => $data['station'] ?? $dish['station'],
            ':status'            => $data['status'] ?? $dish['status'],
            ':rating'            => isset($data['rating']) ? (float) $data['rating'] : $dish['rating'],
            ':id'                => (int) $id
        ]);
        return ['success' => true, 'message' => 'Cập nhật món ăn "' . htmlspecialchars($name) . '" thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi cập nhật món ăn: ' . $e->getMessage()];
    }
}

function deleteDish($id, $soft = true) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $dish = getDishById($id);
    if (!$dish) {
        return ['success' => false, 'message' => 'Không tìm thấy món ăn cần xóa!'];
    }

    // 1. Soft delete (Mặc định): Chuyển trạng thái sang hidden để bảo toàn lịch sử hóa đơn & doanh thu
    if ($soft) {
        $stmt = $pdo->prepare("UPDATE `dishes` SET `status` = 'hidden' WHERE `id` = :id");
        try {
            $stmt->execute([':id' => (int) $id]);
            return [
                'success' => true,
                'soft' => true,
                'message' => 'Đã ẩn món ăn "' . htmlspecialchars($dish['name']) . '" khỏi thực đơn (Lưu trữ an toàn, bảo vệ dữ liệu doanh thu cũ)!'
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Lỗi khi ẩn món ăn: ' . $e->getMessage()];
        }
    }

    // 2. Hard delete (Xóa vĩnh viễn): Xóa file ảnh và bản ghi khỏi cơ sở dữ liệu
    if (!empty($dish['image']) && strpos($dish['image'], 'uploads/dishes/') === 0) {
        $filePath = __DIR__ . '/../' . $dish['image'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM `dishes` WHERE `id` = :id");
    try {
        $stmt->execute([':id' => (int) $id]);
        return ['success' => true, 'soft' => false, 'message' => 'Đã xóa vĩnh viễn món ăn "' . htmlspecialchars($dish['name']) . '" khỏi cơ sở dữ liệu!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi xóa món ăn: ' . $e->getMessage()];
    }
}

function toggleDishStatus($id) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $dish = getDishById($id);
    if (!$dish) return ['success' => false, 'message' => 'Không tìm thấy món ăn!'];

    $newStatus = ($dish['status'] === 'active') ? 'out_of_stock' : 'active';
    $stmt = $pdo->prepare("UPDATE `dishes` SET `status` = :status WHERE `id` = :id");
    try {
        $stmt->execute([':status' => $newStatus, ':id' => (int) $id]);
        $statusText = ($newStatus === 'active') ? 'Đã mở bán lại món' : 'Đã chuyển sang tạm hết';
        return ['success' => true, 'new_status' => $newStatus, 'message' => $statusText . ' thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi cập nhật trạng thái món: ' . $e->getMessage()];
    }
}

function getDishStats() {
    $pdo = getDbConnection();
    if (!$pdo) {
        return ['total' => 0, 'active' => 0, 'out_of_stock' => 0, 'hidden' => 0, 'buffet' => 0, 'alacarte' => 0, 'buffet_packages' => 0, 'categories' => 0, 'avg_price' => 0];
    }

    $dishQuery = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN `status` = 'out_of_stock' THEN 1 ELSE 0 END) as out_of_stock_count,
            SUM(CASE WHEN `status` = 'hidden' THEN 1 ELSE 0 END) as hidden_count,
            SUM(CASE WHEN `menu_type` = 'buffet' THEN 1 ELSE 0 END) as buffet_count,
            SUM(CASE WHEN `menu_type` = 'alacarte' THEN 1 ELSE 0 END) as alacarte_count,
            AVG(CASE WHEN `menu_type` = 'alacarte' AND `price` > 0 THEN `price` ELSE NULL END) as avg_price
        FROM `dishes`
    ");
    $dishRow = $dishQuery->fetch();

    $catTotal = (int) $pdo->query("SELECT COUNT(*) as total_categories FROM `categories`")->fetchColumn();
    $pkgTotal = (int) $pdo->query("SELECT COUNT(*) as total_packages FROM `buffet_packages` WHERE `status` = 'active'")->fetchColumn();

    return [
        'total'           => (int) ($dishRow['total'] ?? 0),
        'active'          => (int) ($dishRow['active_count'] ?? 0),
        'out_of_stock'    => (int) ($dishRow['out_of_stock_count'] ?? 0),
        'hidden'          => (int) ($dishRow['hidden_count'] ?? 0),
        'buffet'          => (int) ($dishRow['buffet_count'] ?? 0),
        'alacarte'        => (int) ($dishRow['alacarte_count'] ?? 0),
        'buffet_packages' => $pkgTotal,
        'categories'      => $catTotal,
        'avg_price'       => (float) ($dishRow['avg_price'] ?? 0),
    ];
}
