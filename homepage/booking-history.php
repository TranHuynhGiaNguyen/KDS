
<?php
/*
|--------------------------------------------------------------------------
| SMARTDINE - LỊCH SỬ ĐẶT BÀN CỦA KHÁCH
|--------------------------------------------------------------------------
| - Không yêu cầu đăng nhập.
| - Tra cứu tất cả đặt bàn bằng số điện thoại.
| - Hiển thị lịch sử mới nhất trước.
| - Cho phép hủy từng lượt đặt bàn hợp lệ.
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/restaurant.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDbConnection();

if (!$pdo instanceof PDO) {
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

function formatBookingCode($id)
{
    return 'SD' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

function getBookingStatusLabel($status)
{
    $labels = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'seated' => 'Đã vào bàn',
        'cancelled' => 'Đã hủy',
        'rejected' => 'Từ chối'
    ];

    return $labels[$status] ?? 'Không xác định';
}

function getBookingStatusClass($status)
{
    $classes = [
        'pending' => 'pending',
        'confirmed' => 'confirmed',
        'seated' => 'seated',
        'cancelled' => 'cancelled',
        'rejected' => 'rejected'
    ];

    return $classes[$status] ?? 'unknown';
}

/*
|--------------------------------------------------------------------------
| TOKEN BẢO VỆ FORM
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['booking_history_token'])) {
    $_SESSION['booking_history_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['booking_history_token'];

/*
|--------------------------------------------------------------------------
| DỮ LIỆU
|--------------------------------------------------------------------------
*/

$errors = [];
$success = '';

$phone = trim($_POST['phone'] ?? $_GET['phone'] ?? '');
$searched = false;
$bookings = [];

/*
|--------------------------------------------------------------------------
| KIỂM TRA SỐ ĐIỆN THOẠI
|--------------------------------------------------------------------------
*/

function isValidBookingPhone($phone)
{
    return preg_match('/^[0-9+\s().-]{9,20}$/', $phone);
}

/*
|--------------------------------------------------------------------------
| XỬ LÝ HỦY MỘT LƯỢT ĐẶT BÀN
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'cancel'
) {
    $token = $_POST['csrf_token'] ?? '';

    $phone = trim($_POST['phone'] ?? '');
    $bookingId = filter_var(
        $_POST['booking_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    if (
        !is_string($token)
        || !hash_equals($csrfToken, $token)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    if (!isValidBookingPhone($phone)) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }

    if (!$bookingId || $bookingId <= 0) {
        $errors[] = 'Mã đặt bàn không hợp lệ.';
    }

    if (empty($errors)) {
        try {
            /*
             * Chỉ hủy lượt đặt thuộc số điện thoại này.
             * Chỉ cho phép hủy khi trạng thái pending/confirmed
             * và thời gian đặt vẫn còn ở tương lai.
             */
            $update = $pdo->prepare("
                UPDATE public_reservations
                SET status = 'cancelled'
                WHERE id = :id
                  AND phone = :phone
                  AND status IN ('pending', 'confirmed')
                  AND TIMESTAMP(reservation_date, reservation_time) > NOW()
            ");

            $update->execute([
                ':id' => $bookingId,
                ':phone' => $phone
            ]);

            if ($update->rowCount() === 1) {
                $success = 'Đã hủy đặt bàn thành công.';
            } else {
                $errors[] = 'Không thể hủy lượt đặt bàn này. Có thể lượt đặt đã được xử lý, đã hủy hoặc đã đến thời gian sử dụng.';
            }

        } catch (PDOException $e) {
            $errors[] = 'Có lỗi xảy ra khi hủy đặt bàn. Vui lòng thử lại.';
        }
    }

    $searched = true;
}

/*
|--------------------------------------------------------------------------
| XỬ LÝ TRA CỨU LỊCH SỬ
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'search'
) {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token)
        || !hash_equals($csrfToken, $token)
    ) {
        $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    }

    if (!isValidBookingPhone($phone)) {
        $errors[] = 'Vui lòng nhập số điện thoại hợp lệ.';
    }

    $searched = true;
}

/*
|--------------------------------------------------------------------------
| LẤY TOÀN BỘ LỊCH SỬ THEO SỐ ĐIỆN THOẠI
|--------------------------------------------------------------------------
*/

if ($searched && empty($errors)) {

    try {
        $stmt = $pdo->prepare("
            SELECT
                r.*,
                a.name AS area_name,
                t.code AS table_code,
                t.name AS table_name
            FROM public_reservations r
            LEFT JOIN restaurant_areas a
                ON a.id = r.area_id
            LEFT JOIN dining_tables t
                ON t.id = r.table_id
            WHERE r.phone = :phone
            ORDER BY
                r.created_at DESC,
                r.id DESC
        ");

        $stmt->execute([
            ':phone' => $phone
        ]);

        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $errors[] = 'Không thể tải lịch sử đặt bàn. Vui lòng thử lại.';
    }
}

/*
|--------------------------------------------------------------------------
| THỐNG KÊ LỊCH SỬ
|--------------------------------------------------------------------------
*/

$totalBookings = count($bookings);
$pendingCount = 0;
$confirmedCount = 0;
$cancelledCount = 0;

foreach ($bookings as $item) {
    if ($item['status'] === 'pending') {
        $pendingCount++;
    } elseif ($item['status'] === 'confirmed') {
        $confirmedCount++;
    } elseif ($item['status'] === 'cancelled') {
        $cancelledCount++;
    }
}

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Lịch sử đặt bàn |
        <?= h($restaurant['name'] ?? 'SmartDine') ?>
    </title>

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

        /* HEADER */

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
        }

        .standalone-header nav a:hover,
        .standalone-header nav a.active {
            color: #f47b08;
        }

        .standalone-reserve {
            display: inline-block;
            background: #f47b08;
            color: #fff;
            padding: 12px 20px;
            border-radius: 9px;
            font-weight: 800;
            font-size: 14px;
        }

        /* MAIN */

        .history-container {
            width: min(1050px, calc(100% - 30px));
            margin: 50px auto 70px;
        }

        .history-intro {
            text-align: center;
            margin-bottom: 30px;
        }

        .history-intro span {
            color: #f47b08;
            font-weight: 800;
            letter-spacing: 2px;
            font-size: 13px;
        }

        .history-intro h1 {
            font-size: clamp(29px, 5vw, 42px);
            margin: 12px 0;
        }

        .history-intro p {
            color: #667085;
            line-height: 1.7;
            max-width: 650px;
            margin: 0 auto;
        }

        .history-card {
            background: #fff;
            padding: 30px;
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(24, 28, 38, .08);
        }

        /* SEARCH */

        .search-heading {
            margin: 0 0 10px;
            font-size: 20px;
        }

        .search-description {
            color: #667085;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 22px;
        }

        .search-form {
            display: flex;
            align-items: end;
            gap: 15px;
        }

        .field {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field label {
            font-size: 14px;
            font-weight: 700;
        }

        .field input {
            width: 100%;
            padding: 14px;
            border: 1px solid #dfe3e8;
            border-radius: 9px;
            font: inherit;
        }

        .field input:focus {
            outline: 2px solid rgba(244, 123, 8, .18);
            border-color: #f47b08;
        }

        .search-button {
            border: 0;
            border-radius: 9px;
            background: #f47b08;
            color: #fff;
            padding: 15px 25px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .search-button:hover {
            background: #e06d04;
        }

        /* ALERT */

        .alert {
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.7;
        }

        .alert.error {
            background: #fff0f0;
            color: #a32121;
        }

        .alert.success {
            background: #edf9f0;
            color: #176b35;
        }

        /* STATISTICS */

        .history-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 13px;
            margin-top: 28px;
        }

        .stat-card {
            padding: 17px;
            border: 1px solid #eee;
            border-radius: 11px;
            background: #fff;
        }

        .stat-card small {
            display: block;
            color: #667085;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .stat-card strong {
            font-size: 25px;
        }

        /* HISTORY LIST */

        .history-list-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin: 32px 0 18px;
        }

        .history-list-heading h2 {
            margin: 0;
            font-size: 21px;
        }

        .history-list-heading span {
            color: #667085;
            font-size: 13px;
        }

        .booking-item {
            border: 1px solid #e9e9e9;
            border-radius: 13px;
            margin-bottom: 17px;
            overflow: hidden;
        }

        .booking-header {
            padding: 19px 21px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            background: #fffaf4;
            border-bottom: 1px solid #eee;
        }

        .booking-code {
            color: #f47b08;
            font-size: 19px;
            font-weight: 900;
            letter-spacing: 1px;
        }

        .booking-created {
            display: block;
            color: #888;
            font-size: 12px;
            margin-top: 6px;
        }

        .status {
            display: inline-block;
            padding: 8px 13px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 800;
        }

        .status.pending {
            color: #946000;
            background: #fff0c2;
        }

        .status.confirmed {
            color: #176b35;
            background: #dff5e5;
        }

        .status.seated {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .status.cancelled {
            color: #a32121;
            background: #ffe2e2;
        }

        .status.rejected {
            color: #666;
            background: #eee;
        }

        .booking-body {
            padding: 21px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 19px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 0;
        }

        .detail-item small {
            color: #667085;
            font-size: 12px;
        }

        .detail-item strong {
            font-size: 14px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .booking-note {
            margin: 0 21px 20px;
            padding: 13px;
            border-radius: 8px;
            background: #f8f8f8;
            color: #555;
            font-size: 13px;
            line-height: 1.7;
        }

        .booking-actions {
            padding: 16px 21px;
            border-top: 1px solid #eee;
            background: #fffdfb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .booking-actions p {
            margin: 0;
            color: #667085;
            font-size: 12px;
            line-height: 1.6;
        }

        .cancel-button {
            border: 0;
            border-radius: 8px;
            background: #c83d3d;
            color: #fff;
            padding: 11px 17px;
            font-weight: 800;
            cursor: pointer;
        }

        .cancel-button:hover {
            background: #a92d2d;
        }

        .cancel-button:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        /* EMPTY */

        .empty-history {
            padding: 35px 20px;
            text-align: center;
            border: 1px dashed #ddd;
            border-radius: 12px;
            background: #fffdfb;
            margin-top: 28px;
        }

        .empty-history strong {
            display: block;
            font-size: 17px;
            margin-bottom: 9px;
        }

        .empty-history p {
            color: #667085;
            font-size: 14px;
            line-height: 1.7;
        }

        .empty-history a {
            display: inline-block;
            margin-top: 10px;
            background: #f47b08;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 800;
        }

        .history-help {
            margin-top: 25px;
            text-align: center;
            color: #667085;
            font-size: 13px;
        }

        .history-help a {
            color: #f47b08;
            font-weight: 800;
        }

        /* FOOTER */

        .reservation-footer {
            text-align: center;
            background: #181c26;
            color: #fff;
            padding: 30px 15px;
            line-height: 1.8;
        }

        @media (max-width: 750px) {
            .standalone-header {
                padding: 13px 15px;
                flex-wrap: wrap;
                gap: 12px;
            }

            .standalone-header nav {
                order: 3;
                width: 100%;
                justify-content: center;
                gap: 13px;
                flex-wrap: wrap;
            }

            .standalone-header nav a {
                font-size: 12px;
            }

            .standalone-reserve {
                padding: 10px 13px;
                font-size: 12px;
            }

            .history-container {
                margin: 35px auto 45px;
            }

            .history-card {
                padding: 22px 16px;
            }

            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-button {
                width: 100%;
            }

            .history-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .booking-body {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 450px) {
            .booking-body {
                grid-template-columns: 1fr;
            }

            .history-list-heading {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<header class="standalone-header">

    <a class="standalone-brand" href="index.php">
        <span class="brand-mark">SD</span>
        <strong>SmartDine</strong>
    </a>

    <nav>
        <a href="index.php">Trang chủ</a>
        <a href="index.php#gioi-thieu">Giới thiệu</a>
        <a href="menu.php">Thực đơn</a>
        <a href="reservation.php">Đặt bàn</a>
        <a class="active" href="booking-history.php">Lịch sử đặt bàn</a>
    </nav>

    <a class="standalone-reserve" href="reservation.php">
        Đặt bàn ngay
    </a>

</header>

<main class="history-container">

    <section class="history-intro">
        <span>SMARTDINE RESTAURANT</span>

        <h1>Lịch sử đặt bàn</h1>

        <p>
            Theo dõi tất cả yêu cầu đặt bàn của bạn.
            Chỉ cần nhập số điện thoại đã sử dụng khi đặt bàn.
        </p>
    </section>

    <section class="history-card">

        <?php if ($success): ?>
            <div class="alert success">
                <strong><?= h($success) ?></strong>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert error">
                <?= h($error) ?>
            </div>
        <?php endforeach; ?>

        <h2 class="search-heading">Tra cứu lịch sử</h2>

        <p class="search-description">
            Nhập số điện thoại đã sử dụng khi đặt bàn để xem
            tất cả các lượt đặt bàn tương ứng.
        </p>

        <form method="POST" action="booking-history.php" class="search-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="search"
            >

            <div class="field">
                <label for="phone">Số điện thoại *</label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    required
                    maxlength="20"
                    placeholder="Nhập số điện thoại đã đặt bàn"
                    value="<?= h($phone) ?>"
                >
            </div>

            <button type="submit" class="search-button">
                Tra cứu lịch sử
            </button>

        </form>

        <?php if ($searched && empty($errors)): ?>

            <?php if ($totalBookings > 0): ?>

                <div class="history-stats">

                    <div class="stat-card">
                        <small>Tổng lượt đặt</small>
                        <strong><?= $totalBookings ?></strong>
                    </div>

                    <div class="stat-card">
                        <small>Chờ xác nhận</small>
                        <strong><?= $pendingCount ?></strong>
                    </div>

                    <div class="stat-card">
                        <small>Đã xác nhận</small>
                        <strong><?= $confirmedCount ?></strong>
                    </div>

                    <div class="stat-card">
                        <small>Đã hủy</small>
                        <strong><?= $cancelledCount ?></strong>
                    </div>

                </div>

                <div class="history-list-heading">
                    <h2>Các lượt đặt bàn của bạn</h2>
                    <span><?= $totalBookings ?> lượt đặt bàn</span>
                </div>

                <?php foreach ($bookings as $booking): ?>

                    <?php
                    $bookingDateTime = new DateTime(
                        $booking['reservation_date'] . ' ' .
                        $booking['reservation_time']
                    );

                    $canCancel = in_array(
                        $booking['status'],
                        ['pending', 'confirmed'],
                        true
                    ) && $bookingDateTime > new DateTime();
                    ?>

                    <article class="booking-item">

                        <div class="booking-header">

                            <div>
                                <div class="booking-code">
                                    <?= h(formatBookingCode($booking['id'])) ?>
                                </div>

                                <small class="booking-created">
                                    Ngày gửi:
                                    <?= h(date(
                                        'd/m/Y H:i',
                                        strtotime($booking['created_at'])
                                    )) ?>
                                </small>
                            </div>

                            <span class="status <?= h(getBookingStatusClass($booking['status'])) ?>">
                                <?= h(getBookingStatusLabel($booking['status'])) ?>
                            </span>

                        </div>

                        <div class="booking-body">

                            <div class="detail-item">
                                <small>Họ và tên</small>
                                <strong>
                                    <?= h($booking['customer_name']) ?>
                                </strong>
                            </div>

                            <div class="detail-item">
                                <small>Ngày đặt</small>
                                <strong>
                                    <?= h(date(
                                        'd/m/Y',
                                        strtotime($booking['reservation_date'])
                                    )) ?>
                                </strong>
                            </div>

                            <div class="detail-item">
                                <small>Giờ đặt</small>
                                <strong>
                                    <?= h(substr($booking['reservation_time'], 0, 5)) ?>
                                </strong>
                            </div>

                            <div class="detail-item">
                                <small>Số lượng khách</small>
                                <strong>
                                    <?= (int)$booking['guests'] ?> người
                                </strong>
                            </div>

                            <div class="detail-item">
                                <small>Khu vực</small>
                                <strong>
                                    <?= h($booking['area_name'] ?: 'Không xác định') ?>
                                </strong>
                            </div>

                            <div class="detail-item">
                                <small>Bàn</small>
                                <strong>
                                    <?= h(
                                        $booking['table_name']
                                        ?: $booking['table_code']
                                        ?: 'Không xác định'
                                    ) ?>

                                    <?php if (!empty($booking['table_code'])): ?>
                                        (<?= h($booking['table_code']) ?>)
                                    <?php endif; ?>
                                </strong>
                            </div>

                            <?php if (!empty($booking['email'])): ?>
                                <div class="detail-item">
                                    <small>Email</small>
                                    <strong>
                                        <?= h($booking['email']) ?>
                                    </strong>
                                </div>
                            <?php endif; ?>

                        </div>

                        <?php if (!empty($booking['note'])): ?>

                            <div class="booking-note">
                                <strong>Ghi chú:</strong>
                                <?= nl2br(h($booking['note'])) ?>
                            </div>

                        <?php endif; ?>

                        <?php if ($canCancel): ?>

                            <div class="booking-actions">

                                <p>
                                    Bạn có thể hủy lượt đặt này
                                    nếu không thể đến nhà hàng.
                                </p>

                                <form
                                    method="POST"
                                    action="booking-history.php"
                                    onsubmit="return confirm('Bạn có chắc chắn muốn hủy lượt đặt bàn <?= h(formatBookingCode($booking['id'])) ?> không?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= h($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="cancel"
                                    >

                                    <input
                                        type="hidden"
                                        name="phone"
                                        value="<?= h($phone) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="booking_id"
                                        value="<?= (int)$booking['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="cancel-button"
                                    >
                                        Hủy đặt bàn
                                    </button>

                                </form>

                            </div>

                        <?php else: ?>

                            <div class="booking-actions">
                                <p>
                                    <?php if ($booking['status'] === 'cancelled'): ?>
                                        Lượt đặt bàn này đã được hủy.
                                    <?php elseif ($booking['status'] === 'rejected'): ?>
                                        Yêu cầu đặt bàn này đã bị từ chối.
                                    <?php else: ?>
                                        Lượt đặt bàn đã đến hoặc qua thời gian sử dụng.
                                    <?php endif; ?>
                                </p>
                            </div>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-history">
                    <strong>Chưa tìm thấy lịch sử đặt bàn</strong>

                    <p>
                        Số điện thoại này chưa có lượt đặt bàn nào.
                        Vui lòng kiểm tra lại hoặc tạo một yêu cầu đặt bàn mới.
                    </p>

                    <a href="reservation.php">
                        Đặt bàn ngay
                    </a>
                </div>

            <?php endif; ?>

        <?php endif; ?>

        <div class="history-help">
            Bạn muốn đặt bàn mới?
            <a href="reservation.php">Đặt bàn ngay</a>
        </div>

    </section>

</main>

<footer class="reservation-footer">

    <strong>
        <?= h($restaurant['name'] ?? 'SmartDine Restaurant') ?>
    </strong>

    <br>

    <?= h($restaurant['address'] ?? '') ?>

    <br>

    <?= h($restaurant['phone'] ?? '') ?>

</footer>

</body>
</html>
