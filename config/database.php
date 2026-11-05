<?php
/**
 * SmartDine - Cấu hình kết nối Cơ sở dữ liệu MySQL PDO
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smartdine_kds');

/**
 * Lấy đối tượng kết nối PDO
 * Tự động tạo Database và Bảng nếu chưa tồn tại
 */
function getDbConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // 1. Kết nối ban đầu đến MySQL server để đảm bảo Database tồn tại
        $dsnWithoutDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
        $initPdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $initPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $initPdo = null;

        // 2. Kết nối trực tiếp vào Database của hệ thống
        $dsnWithDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsnWithDb, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // 3. Khởi tạo cấu trúc bảng users nếu chưa có
        initDatabaseTables($pdo);

        return $pdo;
    } catch (PDOException $e) {
        // Nếu không kết nối được MySQL, ghi log lỗi
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
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
        `role` ENUM('admin', 'staff', 'kitchen') NOT NULL DEFAULT 'staff',
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

    // Kiểm tra xem đã có tài khoản nào chưa
    $checkStmt = $pdo->query("SELECT COUNT(*) as total FROM `users`");
    $count = (int) $checkStmt->fetchColumn();

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

    if ($catCount === 0) {
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

    if ($dishCount === 0) {
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
        ':role'     => $data['role'],
        ':phone'    => trim($data['phone'] ?? ''),
        ':email'    => trim($data['email'] ?? ''),
        ':status'   => $data['status'],
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

function getUserStats() {
    $pdo = getDbConnection();
    if (!$pdo) {
        return ['total' => 0, 'admin' => 0, 'staff' => 0, 'kitchen' => 0, 'active' => 0, 'inactive' => 0];
    }

    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN `role` = 'admin' THEN 1 ELSE 0 END) as admin_count,
            SUM(CASE WHEN `role` = 'staff' THEN 1 ELSE 0 END) as staff_count,
            SUM(CASE WHEN `role` = 'kitchen' THEN 1 ELSE 0 END) as kitchen_count,
            SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN `status` = 'inactive' THEN 1 ELSE 0 END) as inactive_count
        FROM `users`
    ");
    $row = $stmt->fetch();
    return [
        'total'    => (int) ($row['total'] ?? 0),
        'admin'    => (int) ($row['admin_count'] ?? 0),
        'staff'    => (int) ($row['staff_count'] ?? 0),
        'kitchen'  => (int) ($row['kitchen_count'] ?? 0),
        'active'   => (int) ($row['active_count'] ?? 0),
        'inactive' => (int) ($row['inactive_count'] ?? 0),
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

function getAllCategories($onlyActive = false) {
    $pdo = getDbConnection();
    if (!$pdo) return [];

    $sql = "SELECT c.*, COUNT(d.id) AS dish_count 
            FROM `categories` c 
            LEFT JOIN `dishes` d ON c.id = d.category_id 
            WHERE 1=1";
    if ($onlyActive) {
        $sql .= " AND c.`status` = 'active'";
    }
    $sql .= " GROUP BY c.id ORDER BY c.sort_order ASC, c.id ASC";

    $stmt = $pdo->query($sql);
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

    $sql = "INSERT INTO `categories` (`name`, `slug`, `icon`, `description`, `sort_order`, `status`)
            VALUES (:name, :slug, :icon, :description, :sort_order, :status)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
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
                `icon` = :icon, 
                `description` = :description, 
                `sort_order` = :sort_order, 
                `status` = :status 
            WHERE `id` = :id";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
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

function getAllDishes($filterCategory = '', $filterStatus = '', $search = '', $filterStation = '') {
    $pdo = getDbConnection();
    if (!$pdo) return [];

    $sql = "SELECT d.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
            FROM `dishes` d
            JOIN `categories` c ON d.category_id = c.id
            WHERE 1=1";
    $params = [];

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
        SELECT d.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
        FROM `dishes` d
        JOIN `categories` c ON d.category_id = c.id
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

    $sql = "INSERT INTO `dishes` (`category_id`, `name`, `price`, `image`, `description`, `tag`, `station`, `status`, `rating`)
            VALUES (:category_id, :name, :price, :image, :description, :tag, :station, :status, :rating)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':category_id' => $categoryId,
            ':name'        => $name,
            ':price'       => $price,
            ':image'       => trim($data['image'] ?? ''),
            ':description' => trim($data['description'] ?? ''),
            ':tag'         => !empty($data['tag']) ? trim($data['tag']) : null,
            ':station'     => $data['station'] ?? 'hot',
            ':status'      => $data['status'] ?? 'active',
            ':rating'      => !empty($data['rating']) ? (float) $data['rating'] : 5.0,
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

    $sql = "UPDATE `dishes` SET 
                `category_id` = :category_id,
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
            ':category_id' => $categoryId,
            ':name'        => $name,
            ':price'       => $price,
            ':image'       => isset($data['image']) ? trim($data['image']) : $dish['image'],
            ':description' => trim($data['description'] ?? $dish['description']),
            ':tag'         => !empty($data['tag']) ? trim($data['tag']) : null,
            ':station'     => $data['station'] ?? $dish['station'],
            ':status'      => $data['status'] ?? $dish['status'],
            ':rating'      => isset($data['rating']) ? (float) $data['rating'] : $dish['rating'],
            ':id'          => (int) $id
        ]);
        return ['success' => true, 'message' => 'Cập nhật món ăn "' . htmlspecialchars($name) . '" thành công!'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Lỗi cập nhật món ăn: ' . $e->getMessage()];
    }
}

function deleteDish($id) {
    $pdo = getDbConnection();
    if (!$pdo) return ['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu!'];

    $dish = getDishById($id);
    if (!$dish) {
        return ['success' => false, 'message' => 'Không tìm thấy món ăn cần xóa!'];
    }

    // Nếu ảnh là file lưu cục bộ, xóa file để giải phóng bộ nhớ
    if (!empty($dish['image']) && strpos($dish['image'], 'uploads/dishes/') === 0) {
        $filePath = __DIR__ . '/../' . $dish['image'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM `dishes` WHERE `id` = :id");
    try {
        $stmt->execute([':id' => (int) $id]);
        return ['success' => true, 'message' => 'Đã xóa món ăn "' . htmlspecialchars($dish['name']) . '" thành công!'];
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
        return ['total' => 0, 'active' => 0, 'out_of_stock' => 0, 'hidden' => 0, 'categories' => 0, 'avg_price' => 0];
    }

    $dishQuery = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN `status` = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN `status` = 'out_of_stock' THEN 1 ELSE 0 END) as out_of_stock_count,
            SUM(CASE WHEN `status` = 'hidden' THEN 1 ELSE 0 END) as hidden_count,
            AVG(`price`) as avg_price
        FROM `dishes`
    ");
    $dishRow = $dishQuery->fetch();

    $catQuery = $pdo->query("SELECT COUNT(*) as total_categories FROM `categories`");
    $catTotal = (int) $catQuery->fetchColumn();

    return [
        'total'        => (int) ($dishRow['total'] ?? 0),
        'active'       => (int) ($dishRow['active_count'] ?? 0),
        'out_of_stock' => (int) ($dishRow['out_of_stock_count'] ?? 0),
        'hidden'       => (int) ($dishRow['hidden_count'] ?? 0),
        'categories'   => $catTotal,
        'avg_price'    => (float) ($dishRow['avg_price'] ?? 0),
    ];
}
