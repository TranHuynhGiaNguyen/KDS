
<?php
/*
|--------------------------------------------------------------------------
| PUBLIC RESERVATION - ĐẶT BÀN DÀNH CHO KHÁCH
|--------------------------------------------------------------------------
| - Không yêu cầu đăng nhập.
| - Khách tự chọn bàn.
| - Kiểm tra bàn trống theo ngày, giờ, số người và khu vực.
| - Chống đặt trùng lịch.
| - Lưu yêu cầu với trạng thái pending.
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/restaurant.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDbConnection();

if (!$pdo) {
    die('Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra MySQL/XAMPP.');
}

$restaurant = getRestaurantInfo();

/*
|--------------------------------------------------------------------------
| HÀM HỖ TRỢ
|--------------------------------------------------------------------------
*/

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function reservationJson($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getAvailableTables(
    PDO $pdo,
    int $areaId,
    int $guests,
    string $date,
    string $time
): array {
    $start = new DateTime($date . ' ' . $time);
    $end = clone $start;
    $end->modify('+120 minutes');

    $sql = "
        SELECT
            t.id,
            t.code,
            t.name,
            t.capacity,
            a.name AS area_name,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM public_reservations r
                    WHERE r.table_id = t.id
                      AND r.status IN ('pending', 'confirmed')
                      AND TIMESTAMP(r.reservation_date, r.reservation_time)
                          < :requested_end
                      AND DATE_ADD(
                          TIMESTAMP(r.reservation_date, r.reservation_time),
                          INTERVAL r.duration_minutes MINUTE
                      ) > :requested_start
                )
                THEN 0
                ELSE 1
            END AS is_available
        FROM dining_tables t
        INNER JOIN restaurant_areas a ON a.id = t.area_id
        WHERE t.area_id = :area_id
          AND t.status = 'active'
          AND a.status = 'active'
          AND t.capacity >= :guests
        ORDER BY t.capacity ASC, t.sort_order ASC, t.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':requested_end' => $end->format('Y-m-d H:i:s'),
        ':requested_start' => $start->format('Y-m-d H:i:s'),
        ':area_id' => $areaId,
        ':guests' => $guests
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| TẠO BẢNG ĐẶT BÀN NẾU CHƯA CÓ
|--------------------------------------------------------------------------
*/

$pdo->exec("
    CREATE TABLE IF NOT EXISTS public_reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(120) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(120) DEFAULT NULL,
        guests INT NOT NULL,
        reservation_date DATE NOT NULL,
        reservation_time TIME NOT NULL,
        duration_minutes INT NOT NULL DEFAULT 120,
        area_id INT NOT NULL,
        table_id INT NOT NULL,
        note TEXT DEFAULT NULL,
        status ENUM('pending','confirmed','cancelled','rejected')
            NOT NULL DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reservation_date (reservation_date),
        INDEX idx_reservation_phone (phone),
        INDEX idx_reservation_table (table_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/*
|--------------------------------------------------------------------------
| TOKEN BẢO VỆ FORM
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['public_reservation_token'])) {
    $_SESSION['public_reservation_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| LẤY DANH SÁCH KHU VỰC
|--------------------------------------------------------------------------
*/

$areas = $pdo->query("
    SELECT id, name, description
    FROM restaurant_areas
    WHERE status = 'active'
    ORDER BY sort_order ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| AJAX: LẤY DANH SÁCH BÀN THEO NGÀY, GIỜ, SỐ NGƯỜI, KHU VỰC
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && ($_GET['action'] ?? '') === 'available_tables'
) {
    $date = trim($_GET['date'] ?? '');
    $time = trim($_GET['time'] ?? '');
    $areaId = filter_var($_GET['area_id'] ?? '', FILTER_VALIDATE_INT);
    $guests = filter_var($_GET['guests'] ?? '', FILTER_VALIDATE_INT);

    if (
        !$date ||
        !$time ||
        !$areaId ||
        !$guests ||
        $guests < 1 ||
        $guests > 30
    ) {
        reservationJson([
            'success' => false,
            'message' => 'Vui lòng nhập đầy đủ ngày, giờ, số người và khu vực.'
        ], 400);
    }

    $dateTime = DateTime::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);

    if (
        !$dateTime ||
        $dateTime->format('Y-m-d H:i') !== $date . ' ' . $time
    ) {
        reservationJson([
            'success' => false,
            'message' => 'Ngày hoặc giờ không hợp lệ.'
        ], 400);
    }

    if ($dateTime < new DateTime()) {
        reservationJson([
            'success' => false,
            'message' => 'Không thể chọn thời gian đã qua.'
        ], 400);
    }

    $areaCheck = $pdo->prepare("
        SELECT id
        FROM restaurant_areas
        WHERE id = ? AND status = 'active'
    ");
    $areaCheck->execute([$areaId]);

    if (!$areaCheck->fetch()) {
        reservationJson([
            'success' => false,
            'message' => 'Khu vực không hợp lệ hoặc đã ngừng hoạt động.'
        ], 400);
    }

    $tables = getAvailableTables(
        $pdo,
        (int)$areaId,
        (int)$guests,
        $date,
        $time
    );

    reservationJson([
        'success' => true,
        'tables' => $tables
    ]);
}

/*
|--------------------------------------------------------------------------
| KHỞI TẠO FORM
|--------------------------------------------------------------------------
*/

$errors = [];
$success = '';
$bookingCode = '';

$form = [
    'customer_name' => '',
    'phone' => '',
    'email' => '',
    'guests' => '2',
    'reservation_date' => '',
    'reservation_time' => '18:00',
    'area_id' => '',
    'table_id' => '',
    'note' => ''
];

/*
|--------------------------------------------------------------------------
| XỬ LÝ GỬI FORM ĐẶT BÀN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($form as $key => $value) {
        $form[$key] = trim($_POST[$key] ?? $value);
    }

    $token = $_POST['reservation_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['public_reservation_token'], $token)
    ) {
        $errors[] = 'Phiên gửi biểu mẫu không hợp lệ. Vui lòng tải lại trang.';
    }

    if ($form['customer_name'] === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    }

    if (!preg_match('/^[0-9+\s().-]{9,20}$/', $form['phone'])) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }

    if (
        $form['email'] !== '' &&
        !filter_var($form['email'], FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'Email không hợp lệ.';
    }

    $guests = filter_var($form['guests'], FILTER_VALIDATE_INT);

    if (!$guests || $guests < 1 || $guests > 30) {
        $errors[] = 'Số lượng khách phải từ 1 đến 30.';
    }

    $dateTime = DateTime::createFromFormat(
        '!Y-m-d H:i',
        $form['reservation_date'] . ' ' . $form['reservation_time']
    );

    if (
        !$dateTime ||
        $dateTime->format('Y-m-d H:i') !==
            $form['reservation_date'] . ' ' . $form['reservation_time']
    ) {
        $errors[] = 'Ngày hoặc giờ đặt bàn không hợp lệ.';
    } elseif ($dateTime < new DateTime()) {
        $errors[] = 'Không thể đặt bàn vào thời gian đã qua.';
    }

    $areaId = filter_var($form['area_id'], FILTER_VALIDATE_INT);
    $tableId = filter_var($form['table_id'], FILTER_VALIDATE_INT);

    if (!$areaId) {
        $errors[] = 'Vui lòng chọn khu vực.';
    }

    if (!$tableId) {
        $errors[] = 'Vui lòng chọn bàn.';
    }

    if ($areaId) {
        $areaCheck = $pdo->prepare("
            SELECT id
            FROM restaurant_areas
            WHERE id = ? AND status = 'active'
        ");
        $areaCheck->execute([$areaId]);

        if (!$areaCheck->fetch()) {
            $errors[] = 'Khu vực đã chọn không còn hoạt động.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            /*
             * Kiểm tra yêu cầu trùng số điện thoại,
             * cùng ngày và cùng giờ.
             */
            $duplicate = $pdo->prepare("
                SELECT id
                FROM public_reservations
                WHERE phone = ?
                  AND reservation_date = ?
                  AND reservation_time = ?
                  AND status IN ('pending', 'confirmed')
                LIMIT 1
            ");

            $duplicate->execute([
                $form['phone'],
                $form['reservation_date'],
                $form['reservation_time']
            ]);

            if ($duplicate->fetch()) {
                throw new Exception(
                    'Bạn đã có yêu cầu đặt bàn bằng số điện thoại này vào cùng thời gian.'
                );
            }

            /*
             * Khóa dòng bàn được chọn trong lúc xử lý
             * để hạn chế hai yêu cầu cùng chọn một bàn.
             */
            $lockTable = $pdo->prepare("
                SELECT id, code, name, capacity
                FROM dining_tables
                WHERE id = ?
                  AND area_id = ?
                  AND status = 'active'
                  AND capacity >= ?
                FOR UPDATE
            ");

            $lockTable->execute([
                $tableId,
                $areaId,
                $guests
            ]);

            $table = $lockTable->fetch(PDO::FETCH_ASSOC);

            if (!$table) {
                throw new Exception(
                    'Bàn bạn chọn không còn hợp lệ hoặc không đủ sức chứa. Vui lòng chọn bàn khác.'
                );
            }

            /*
             * Kiểm tra lại lịch trùng ngay trước khi lưu.
             * Khoảng sử dụng bàn mặc định là 120 phút.
             */
            $startDateTime = new DateTime(
                $form['reservation_date'] . ' ' . $form['reservation_time']
            );

            $endDateTime = clone $startDateTime;
            $endDateTime->modify('+120 minutes');

            $checkConflict = $pdo->prepare("
                SELECT id
                FROM public_reservations
                WHERE table_id = :table_id
                  AND status IN ('pending', 'confirmed')
                  AND TIMESTAMP(reservation_date, reservation_time)
                      < :requested_end
                  AND DATE_ADD(
                      TIMESTAMP(reservation_date, reservation_time),
                      INTERVAL duration_minutes MINUTE
                  ) > :requested_start
                LIMIT 1
            ");

            $checkConflict->execute([
                ':table_id' => $tableId,
                ':requested_end' => $endDateTime->format('Y-m-d H:i:s'),
                ':requested_start' => $startDateTime->format('Y-m-d H:i:s')
            ]);

            if ($checkConflict->fetch()) {
                throw new Exception(
                    'Rất tiếc, bàn này vừa được đặt vào khung giờ bạn chọn. Vui lòng chọn bàn khác.'
                );
            }

            /*
             * Lưu đặt bàn.
             */
            $insert = $pdo->prepare("
                INSERT INTO public_reservations (
                    customer_name,
                    phone,
                    email,
                    guests,
                    reservation_date,
                    reservation_time,
                    duration_minutes,
                    area_id,
                    table_id,
                    note,
                    status
                ) VALUES (
                    :customer_name,
                    :phone,
                    :email,
                    :guests,
                    :reservation_date,
                    :reservation_time,
                    120,
                    :area_id,
                    :table_id,
                    :note,
                    'pending'
                )
            ");

            $insert->execute([
                ':customer_name' => $form['customer_name'],
                ':phone' => $form['phone'],
                ':email' => $form['email'] ?: null,
                ':guests' => $guests,
                ':reservation_date' => $form['reservation_date'],
                ':reservation_time' => $form['reservation_time'],
                ':area_id' => $areaId,
                ':table_id' => $tableId,
                ':note' => $form['note'] ?: null
            ]);

            $newId = $pdo->lastInsertId();

            $bookingCode = 'SD' . str_pad(
                (string)$newId,
                6,
                '0',
                STR_PAD_LEFT
            );

            $pdo->commit();

            $success = 'Đặt bàn thành công! Yêu cầu của bạn đang chờ nhà hàng xác nhận.';

            $_SESSION['public_reservation_token'] = bin2hex(random_bytes(32));

            $form = [
                'customer_name' => '',
                'phone' => '',
                'email' => '',
                'guests' => '2',
                'reservation_date' => '',
                'reservation_time' => '18:00',
                'area_id' => '',
                'table_id' => '',
                'note' => ''
            ];

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $e instanceof PDOException
                ? 'Có lỗi khi lưu đặt bàn. Vui lòng thử lại.'
                : $e->getMessage();
        }
    }
}

$minDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Đặt bàn | <?= h($restaurant['name'] ?? 'SmartDine') ?></title>

    <link rel="stylesheet" href="../assets/css/home.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fff7ef;
            color: #181c26;
        }

        a {
            text-decoration: none;
        }

        /* HEADER VÀ LOGO */
        .standalone-header {
            width: 100%;
            min-height: 76px;
            padding: 14px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            background: #fff;
            border-bottom: 1px solid #eee;
            position: relative;
            z-index: 10;
        }

        .standalone-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #181c26;
            font-size: 21px;
            font-weight: 800;
            white-space: nowrap;
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f47b08;
            color: #fff;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 900;
        }

        .standalone-header nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .standalone-header nav a {
            color: #333;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
        }

        .standalone-header nav a:hover,
        .standalone-header nav a.active {
            color: #f47b08;
        }

        /* TRA CỨU ĐẶT BÀN */
        .standalone-header nav .booking-lookup-link {
            color: #f47b08;
            font-weight: 700;
        }

        .standalone-header nav .booking-lookup-link:hover {
            color: #e06d04;
            text-decoration: underline;
        }

        .standalone-header-actions {
            display: flex;
            align-items: center;
        }

        .standalone-reserve {
            display: inline-block;
            background: #f47b08;
            color: #fff;
            padding: 12px 20px;
            border-radius: 9px;
            font-weight: 800;
            font-size: 14px;
            white-space: nowrap;
        }

        /* NỘI DUNG */
        .reservation-container {
            width: min(850px, calc(100% - 30px));
            margin: 55px auto 70px;
        }

        .reservation-intro {
            text-align: center;
            margin-bottom: 30px;
        }

        .reservation-intro .eyebrow {
            color: #f47b08;
            font-weight: 800;
            letter-spacing: 2px;
            font-size: 13px;
        }

        .reservation-intro h1 {
            font-size: clamp(30px, 5vw, 44px);
            margin: 12px 0;
        }

        .reservation-intro p {
            color: #667085;
            line-height: 1.7;
            max-width: 620px;
            margin: 0 auto;
        }

        .reservation-card {
            background: #fff;
            padding: 34px;
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(24, 28, 38, .08);
        }

        .form-section-title {
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 19px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .field label {
            font-size: 14px;
            font-weight: 700;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #dfe3e8;
            border-radius: 9px;
            font: inherit;
            background: #fff;
            color: #181c26;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: 2px solid rgba(244, 123, 8, .18);
            border-color: #f47b08;
        }

        .field textarea {
            min-height: 95px;
            resize: vertical;
        }

        .section-divider {
            border: 0;
            border-top: 1px solid #eee;
            margin: 27px 0;
        }

        /* CHỌN BÀN */
        .table-help {
            color: #667085;
            font-size: 13px;
            line-height: 1.6;
            margin: -8px 0 17px;
        }

        .table-status {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 17px;
            font-size: 13px;
            color: #555;
        }

        .status-item {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .status-dot {
            width: 11px;
            height: 11px;
            display: inline-block;
            border-radius: 50%;
        }

        .dot-available {
            background: #20a464;
        }

        .dot-unavailable {
            background: #dc5555;
        }

        .dot-selected {
            background: #f47b08;
        }

        .table-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 13px;
        }

        .table-option {
            position: relative;
        }

        .table-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .table-option label {
            min-height: 112px;
            border: 1px solid #dfe3e8;
            border-radius: 12px;
            padding: 15px 12px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 7px;
            text-align: center;
            cursor: pointer;
            transition: .2s;
            background: #fff;
        }

        .table-option label strong {
            font-size: 17px;
        }

        .table-option label span {
            font-size: 13px;
            color: #667085;
        }

        .table-option label .table-state {
            font-weight: 700;
            color: #16834d;
        }

        .table-option input:checked + label {
            border: 2px solid #f47b08;
            background: #fff6ec;
            box-shadow: 0 4px 15px rgba(244, 123, 8, .12);
        }

        .table-option.unavailable label {
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
            border-color: #e4e4e4;
        }

        .table-option.unavailable label .table-state {
            color: #c33;
        }

        .table-message {
            border-radius: 9px;
            padding: 13px 15px;
            background: #f5f7fa;
            color: #667085;
            font-size: 14px;
            line-height: 1.6;
        }

        .table-message.error-message {
            background: #fff0f0;
            color: #a32121;
        }

        .table-message.success-message {
            background: #edf9f0;
            color: #176b35;
        }

        .selected-table-info {
            display: none;
            margin-top: 15px;
            padding: 13px 15px;
            border-radius: 9px;
            background: #fff6ec;
            color: #9a4c00;
            font-size: 14px;
        }

        /* NÚT VÀ THÔNG BÁO */
        .submit-button {
            width: 100%;
            border: 0;
            border-radius: 9px;
            background: #f47b08;
            color: white;
            padding: 16px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            margin-top: 25px;
            transition: .2s;
        }

        .submit-button:hover {
            background: #e06d04;
        }

        .submit-button:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 18px;
            line-height: 1.7;
            font-size: 14px;
        }

        .alert.error {
            background: #fff0f0;
            color: #a32121;
        }

        .alert.success {
            background: #edf9f0;
            color: #176b35;
        }

        .booking-code {
            display: inline-block;
            margin: 8px 0;
            padding: 8px 13px;
            background: #fff;
            border: 1px dashed #20a464;
            border-radius: 7px;
            font-size: 20px;
            letter-spacing: 1px;
        }

        /* FOOTER */
        .reservation-footer {
            text-align: center;
            background: #181c26;
            color: #fff;
            padding: 30px 15px;
            line-height: 1.8;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .standalone-header {
                flex-wrap: wrap;
                gap: 15px;
            }

            .standalone-header nav {
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
            }
        }

        @media (max-width: 700px) {
            .standalone-header {
                padding: 13px 15px;
                gap: 12px;
            }

            .standalone-header nav {
                order: 3;
                width: 100%;
                justify-content: center;
                gap: 12px;
            }

            .standalone-header nav a {
                font-size: 12px;
            }

            .standalone-header-actions {
                margin-left: auto;
            }

            .standalone-reserve {
                padding: 10px 13px;
                font-size: 12px;
            }

            .reservation-container {
                margin: 35px auto 45px;
            }

            .reservation-card {
                padding: 23px 17px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .table-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 380px) {
            .standalone-brand {
                font-size: 18px;
            }

            .brand-mark {
                width: 36px;
                height: 36px;
            }

            .standalone-header nav {
                gap: 10px;
            }
        }
    </style>
</head>

<body>

<!-- HEADER: LOGO VÀ MENU HOMEPAGE -->
<header class="standalone-header">

    <a class="standalone-brand" href="index.php">
        <span class="brand-mark">SD</span>
        <strong>SmartDine</strong>
    </a>

    <nav>
        <a href="index.php">Trang chủ</a>
        <a href="index.php#gioi-thieu">Giới thiệu</a>
        <a href="menu.php">Thực đơn</a>
        <a class="active" href="reservation.php">Đặt bàn</a>
        <a href="booking-history.php" class="booking-lookup-link">
            Tra cứu đặt bàn
        </a>
    </nav>

    <div class="standalone-header-actions">
        <a class="standalone-reserve" href="reservation.php">
            Đặt bàn ngay
        </a>
    </div>

</header>

<main class="reservation-container">

    <section class="reservation-intro">
        <span class="eyebrow">SMARTDINE RESTAURANT</span>
        <h1>Đặt bàn ngay</h1>
        <p>
            Chọn thời gian, khu vực và bàn phù hợp.
            Nhà hàng sẽ tiếp nhận và xác nhận yêu cầu đặt bàn của bạn.
        </p>
    </section>

    <section class="reservation-card">

        <?php if ($success): ?>
            <div class="alert success">
                <strong><?= h($success) ?></strong>
                <br>
                Mã đặt bàn của bạn:
                <br>
                <span class="booking-code">
                    <?= h($bookingCode) ?>
                </span>
                <br>
                Vui lòng lưu lại mã đặt bàn để tiện tra cứu.
                Nhà hàng sẽ liên hệ với bạn để xác nhận.
                <br>
                <a href="booking-history.php" class="booking-lookup-link">
                    Tra cứu đặt bàn
                </a>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert error">
                <?= h($error) ?>
            </div>
        <?php endforeach; ?>

        <form method="POST" action="" id="reservationForm">

            <input
                type="hidden"
                name="reservation_token"
                value="<?= h($_SESSION['public_reservation_token']) ?>"
            >

            <h2 class="form-section-title">Thông tin khách hàng</h2>

            <div class="form-grid">

                <div class="field full">
                    <label for="customer_name">Họ và tên *</label>
                    <input
                        type="text"
                        id="customer_name"
                        name="customer_name"
                        required
                        maxlength="120"
                        value="<?= h($form['customer_name']) ?>"
                        placeholder="Nhập họ và tên"
                    >
                </div>

                <div class="field">
                    <label for="phone">Số điện thoại *</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        required
                        maxlength="20"
                        value="<?= h($form['phone']) ?>"
                        placeholder="Nhập số điện thoại"
                    >
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="120"
                        value="<?= h($form['email']) ?>"
                        placeholder="example@gmail.com"
                    >
                </div>

            </div>

            <hr class="section-divider">

            <h2 class="form-section-title">Thông tin đặt bàn</h2>

            <div class="form-grid">

                <div class="field">
                    <label for="guests">Số lượng khách *</label>
                    <input
                        type="number"
                        id="guests"
                        name="guests"
                        min="1"
                        max="30"
                        required
                        value="<?= h($form['guests']) ?>"
                    >
                </div>

                <div class="field">
                    <label for="area_id">Khu vực *</label>
                    <select id="area_id" name="area_id" required>
                        <option value="">-- Chọn khu vực --</option>

                        <?php foreach ($areas as $area): ?>
                            <option
                                value="<?= (int)$area['id'] ?>"
                                <?= (string)$form['area_id'] === (string)$area['id'] ? 'selected' : '' ?>
                            >
                                <?= h($area['name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="field">
                    <label for="reservation_date">Ngày đặt *</label>
                    <input
                        type="date"
                        id="reservation_date"
                        name="reservation_date"
                        min="<?= h($minDate) ?>"
                        required
                        value="<?= h($form['reservation_date']) ?>"
                    >
                </div>

                <div class="field">
                    <label for="reservation_time">Giờ đặt *</label>
                    <input
                        type="time"
                        id="reservation_time"
                        name="reservation_time"
                        required
                        value="<?= h($form['reservation_time']) ?>"
                    >
                </div>

            </div>

            <hr class="section-divider">

            <h2 class="form-section-title">Chọn bàn</h2>

            <p class="table-help">
                Sau khi chọn ngày, giờ, số lượng khách và khu vực,
                hệ thống sẽ hiển thị những bàn phù hợp để bạn lựa chọn.
            </p>

            <div class="table-status">
                <span class="status-item">
                    <i class="status-dot dot-available"></i>
                    Còn trống
                </span>

                <span class="status-item">
                    <i class="status-dot dot-unavailable"></i>
                    Đã được đặt
                </span>

                <span class="status-item">
                    <i class="status-dot dot-selected"></i>
                    Đang chọn
                </span>
            </div>

            <div id="tableMessage" class="table-message">
                Vui lòng chọn ngày, giờ, số người và khu vực để xem bàn.
            </div>

            <div id="tableList" class="table-list"></div>

            <div id="selectedTableInfo" class="selected-table-info"></div>

            <input
                type="hidden"
                name="table_id"
                id="table_id"
                value="<?= h($form['table_id']) ?>"
            >

            <hr class="section-divider">

            <div class="field">
                <label for="note">Ghi chú</label>
                <textarea
                    id="note"
                    name="note"
                    maxlength="1000"
                    placeholder="Ví dụ: Có trẻ em, cần ghế trẻ em, yêu cầu vị trí ngồi..."
                ><?= h($form['note']) ?></textarea>
            </div>

            <button class="submit-button" type="submit" id="submitButton">
                Gửi yêu cầu đặt bàn
            </button>

        </form>

    </section>

</main>

<footer class="reservation-footer">
    <strong><?= h($restaurant['name'] ?? 'SmartDine Restaurant') ?></strong>
    <br>
    <?= h($restaurant['address'] ?? '') ?>
    <br>
    <?= h($restaurant['phone'] ?? '') ?>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const dateInput = document.getElementById('reservation_date');
    const timeInput = document.getElementById('reservation_time');
    const guestsInput = document.getElementById('guests');
    const areaInput = document.getElementById('area_id');

    const tableList = document.getElementById('tableList');
    const tableMessage = document.getElementById('tableMessage');
    const tableIdInput = document.getElementById('table_id');
    const selectedTableInfo = document.getElementById('selectedTableInfo');

    const form = document.getElementById('reservationForm');
    const submitButton = document.getElementById('submitButton');

    let requestNumber = 0;

    function clearSelectedTable() {
        tableIdInput.value = '';
        selectedTableInfo.style.display = 'none';
        selectedTableInfo.textContent = '';
    }

    function showMessage(message, type = '') {
        tableMessage.textContent = message;
        tableMessage.className = 'table-message';

        if (type === 'error') {
            tableMessage.classList.add('error-message');
        }

        if (type === 'success') {
            tableMessage.classList.add('success-message');
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    async function loadAvailableTables() {

        const date = dateInput.value;
        const time = timeInput.value;
        const guests = guestsInput.value;
        const areaId = areaInput.value;

        const currentRequest = ++requestNumber;

        tableList.innerHTML = '';
        clearSelectedTable();

        if (!date || !time || !guests || !areaId) {
            showMessage(
                'Vui lòng chọn ngày, giờ, số người và khu vực để xem bàn.'
            );
            return;
        }

        if (Number(guests) < 1 || Number(guests) > 30) {
            showMessage('Số lượng khách phải từ 1 đến 30.', 'error');
            return;
        }

        showMessage('Đang kiểm tra bàn phù hợp...');

        const params = new URLSearchParams({
            action: 'available_tables',
            date: date,
            time: time,
            guests: guests,
            area_id: areaId
        });

        try {
            const response = await fetch(
                'reservation.php?' + params.toString(),
                {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            const result = await response.json();

            if (currentRequest !== requestNumber) {
                return;
            }

            if (!response.ok || !result.success) {
                showMessage(
                    result.message || 'Không thể tải danh sách bàn.',
                    'error'
                );
                return;
            }

            renderTables(result.tables || []);

        } catch (error) {
            if (currentRequest !== requestNumber) {
                return;
            }

            showMessage(
                'Không thể kết nối đến máy chủ. Vui lòng thử lại.',
                'error'
            );
        }
    }

    function renderTables(tables) {

        tableList.innerHTML = '';
        clearSelectedTable();

        if (tables.length === 0) {
            showMessage(
                'Không có bàn phù hợp với số lượng khách trong khu vực này.',
                'error'
            );
            return;
        }

        let availableCount = 0;

        tables.forEach(function (table) {

            const isAvailable = Number(table.is_available) === 1;

            if (isAvailable) {
                availableCount++;
            }

            const item = document.createElement('div');
            item.className = 'table-option' +
                (isAvailable ? '' : ' unavailable');

            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'table_choice';
            input.id = 'table_' + table.id;
            input.value = table.id;
            input.disabled = !isAvailable;

            const label = document.createElement('label');
            label.htmlFor = input.id;

            const tableName = table.name || table.code || ('Bàn ' + table.id);
            const tableCode = table.code || ('B' + table.id);

            label.innerHTML = `
                <strong>${escapeHtml(tableName)}</strong>
                <span>Mã: ${escapeHtml(tableCode)}</span>
                <span>Sức chứa: ${escapeHtml(table.capacity)} người</span>
                <span class="table-state">
                    ${isAvailable ? 'Còn trống' : 'Đã được đặt'}
                </span>
            `;

            input.addEventListener('change', function () {
                if (this.checked) {
                    tableIdInput.value = this.value;

                    selectedTableInfo.style.display = 'block';
                    selectedTableInfo.textContent =
                        'Bạn đang chọn: ' + tableName +
                        ' (' + tableCode + ') - Sức chứa ' +
                        table.capacity + ' người.';
                }
            });

            item.appendChild(input);
            item.appendChild(label);
            tableList.appendChild(item);
        });

        if (availableCount > 0) {
            showMessage(
                'Tìm thấy ' + tables.length +
                ' bàn phù hợp, trong đó có ' +
                availableCount + ' bàn còn trống.',
                'success'
            );
        } else {
            showMessage(
                'Các bàn phù hợp trong khu vực này đã được đặt hết. Vui lòng đổi giờ hoặc khu vực.',
                'error'
            );
        }
    }

    dateInput.addEventListener('change', loadAvailableTables);
    timeInput.addEventListener('change', loadAvailableTables);
    guestsInput.addEventListener('change', loadAvailableTables);
    areaInput.addEventListener('change', loadAvailableTables);

    form.addEventListener('submit', function (event) {

        if (!tableIdInput.value) {
            event.preventDefault();

            showMessage(
                'Bạn cần chọn một bàn còn trống trước khi gửi yêu cầu.',
                'error'
            );

            tableList.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Đang gửi yêu cầu...';
    });

    // Nếu trang được tải lại sau khi có lỗi, tải lại danh sách bàn.
    if (
        dateInput.value &&
        timeInput.value &&
        guestsInput.value &&
        areaInput.value
    ) {
        loadAvailableTables();
    }

});
</script>

</body>
</html>
