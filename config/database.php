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
