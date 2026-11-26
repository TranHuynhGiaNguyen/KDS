
<?php
/**
 * SmartDine - QUẢN LÝ ĐẶT BÀN TỪ HOMEPAGE
 * Dành cho Admin và Nhân viên phục vụ (Staff) thông qua phân quyền tại index.php và auth.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tables.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

$pdo = getDbConnection();

if (!$pdo instanceof PDO) {
    die('Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra MySQL và XAMPP.');
}

ensurePublicReservationsSchema($pdo);

$currentUserRole = function_exists('currentRole') ? currentRole() : ($_SESSION['role'] ?? 'staff');
$isAdminUser     = in_array($currentUserRole, ['admin', 'shift_manager'], true);
$currentStaffId  = (int) ($_SESSION['user_id'] ?? 0);

if (!function_exists('reservationH')) {
    function reservationH($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// CSRF token
if (empty($_SESSION['reservation_admin_token'])) {
    $_SESSION['reservation_admin_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['reservation_admin_token'];

// ==========================================================
// XỬ LÝ XÁC NHẬN / ĐÃ VÀO BÀN / TỪ CHỐI / HỦY ĐẶT BÀN
// ==========================================================

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && isset($_POST['reservation_action'])) {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken)
        || !hash_equals($csrfToken, $submittedToken)) {

        $_SESSION['flash_error'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
        header('Location: index.php?page=reservations');
        exit;
    }

    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    $action = $_POST['reservation_action'] ?? '';

    if ($action === 'seat') {
        $seatResult = seatPublicReservation($pdo, $reservationId, $currentStaffId);
        if (!empty($seatResult['success'])) {
            $_SESSION['flash_success'] = $seatResult['message'];
        } else {
            $_SESSION['flash_error'] = $seatResult['message'] ?? 'Không thể xác nhận khách vào bàn.';
        }
        header('Location: index.php?page=reservations');
        exit;
    }

    if (!$isAdminUser) {
        $_SESSION['flash_error'] = 'Chỉ Quản trị viên hoặc Quản lý ca mới có quyền xác nhận, từ chối hoặc hủy yêu cầu đặt bàn.';
        header('Location: index.php?page=reservations');
        exit;
    }

    $statusMap = [
        'confirm' => 'confirmed',
        'reject'  => 'rejected',
        'cancel'  => 'cancelled',
    ];

    if ($reservationId <= 0 || !isset($statusMap[$action])) {
        $_SESSION['flash_error'] = 'Thông tin cập nhật không hợp lệ.';
        header('Location: index.php?page=reservations');
        exit;
    }

    try {
        $newStatus = $statusMap[$action];

        // Chỉ cho phép chuyển trạng thái phù hợp.
        if ($action === 'confirm' || $action === 'reject') {
            $oldStatus = 'pending';
        } else {
            $oldStatus = 'confirmed';
        }

        $stmt = $pdo->prepare("
            UPDATE public_reservations
            SET status = :new_status
            WHERE id = :id
              AND status = :old_status
        ");

        $stmt->execute([
            ':new_status' => $newStatus,
            ':id' => $reservationId,
            ':old_status' => $oldStatus,
        ]);

        if ($stmt->rowCount() > 0) {
            $messages = [
                'confirmed' => 'Đã xác nhận đặt bàn thành công.',
                'rejected'  => 'Đã từ chối yêu cầu đặt bàn.',
                'cancelled' => 'Đã hủy lượt đặt bàn.',
            ];

            $_SESSION['flash_success'] = $messages[$newStatus];
        } else {
            $_SESSION['flash_error'] =
                'Không tìm thấy lượt đặt bàn hoặc trạng thái đã được thay đổi.';
        }

    } catch (PDOException $e) {
        error_log('Update reservation status: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Không thể cập nhật trạng thái đặt bàn.';
    }

    header('Location: index.php?page=reservations');
    exit;
}

// ==========================================================
// BỘ LỌC
// ==========================================================

$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$dateFilter = $_GET['date'] ?? '';

$allowedStatuses = [
    'pending',
    'confirmed',
    'seated',
    'cancelled',
    'rejected'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

// Kiểm tra ngày hợp lệ
if ($dateFilter !== '') {
    $dateObject = DateTime::createFromFormat('!Y-m-d', $dateFilter);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $dateFilter) {
        $dateFilter = '';
    }
}

// ==========================================================
// LẤY DANH SÁCH ĐẶT BÀN
// ==========================================================

$where = [];
$params = [];

if ($keyword !== '') {
    $where[] = "(
        r.customer_name LIKE :keyword1
        OR r.phone LIKE :keyword2
        OR r.email LIKE :keyword3
        OR CONCAT('SD', LPAD(r.id, 6, '0')) LIKE :keyword4
        OR t.code LIKE :keyword5
        OR t.name LIKE :keyword6
    )";

    $searchTerm = '%' . $keyword . '%';

    $params[':keyword1'] = $searchTerm;
    $params[':keyword2'] = $searchTerm;
    $params[':keyword3'] = $searchTerm;
    $params[':keyword4'] = $searchTerm;
    $params[':keyword5'] = $searchTerm;
    $params[':keyword6'] = $searchTerm;
}

if ($statusFilter !== '') {
    $where[] = 'r.status = :status';
    $params[':status'] = $statusFilter;
}

if ($dateFilter !== '') {
    $where[] = 'r.reservation_date = :reservation_date';
    $params[':reservation_date'] = $dateFilter;
}

$sql = "
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
";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    ORDER BY
        r.reservation_date DESC,
        r.reservation_time DESC,
        r.id DESC
";

$reservations = [];
$stats = [];
$loadError = '';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Thống kê tổng lượt đặt bàn
    $countStmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'pending') AS pending_count,
            SUM(status = 'confirmed') AS confirmed_count,
            SUM(status = 'seated') AS seated_count,
            SUM(status = 'cancelled') AS cancelled_count,
            SUM(status = 'rejected') AS rejected_count
        FROM public_reservations
    ");

    $stats = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    error_log('Load reservations: ' . $e->getMessage());

    $loadError =
        'Không thể tải danh sách đặt bàn. Hãy kiểm tra bảng public_reservations và cấu trúc dữ liệu.';
}

// ==========================================================
// NHÃN TRẠNG THÁI
// ==========================================================

$statusLabels = [
    'pending'   => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'seated'    => 'Đã vào bàn',
    'cancelled' => 'Đã hủy',
    'rejected'  => 'Đã từ chối',
];

?>

<style>
.reservation-admin-page {
    padding-bottom: 30px;
}

.reservation-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 22px;
}

.reservation-admin-header h1 {
    margin: 0 0 6px;
    font-size: 25px;
    color: #172033;
}

.reservation-admin-header p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.reservation-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}

.reservation-stat {
    background: #fff;
    border: 1px solid #e8edf3;
    border-radius: 12px;
    padding: 17px;
}

.reservation-stat span {
    display: block;
    color: #64748b;
    font-size: 12px;
    margin-bottom: 8px;
}

.reservation-stat strong {
    display: block;
    font-size: 25px;
    color: #172033;
}

.reservation-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    background: #fff;
    border: 1px solid #e8edf3;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 18px;
}

.reservation-filter input,
.reservation-filter select {
    min-height: 39px;
    border: 1px solid #dbe2ea;
    border-radius: 7px;
    padding: 7px 10px;
    font: inherit;
    color: #334155;
}

.reservation-filter input[name="keyword"] {
    flex: 1;
    min-width: 210px;
}

.reservation-table-wrap {
    overflow-x: auto;
    background: #fff;
    border: 1px solid #e8edf3;
    border-radius: 12px;
}

.reservation-table {
    width: 100%;
    min-width: 1050px;
    border-collapse: collapse;
    font-size: 13px;
}

.reservation-table th {
    background: #f8fafc;
    color: #475569;
    text-align: left;
    padding: 13px 12px;
    white-space: nowrap;
    border-bottom: 1px solid #e8edf3;
}

.reservation-table td {
    padding: 13px 12px;
    border-bottom: 1px solid #eef2f6;
    color: #334155;
    vertical-align: middle;
}

.reservation-table tr:last-child td {
    border-bottom: 0;
}

.reservation-code {
    font-weight: 700;
    color: #ea580c;
}

.reservation-customer strong {
    display: block;
    color: #172033;
    margin-bottom: 4px;
}

.reservation-customer small {
    color: #64748b;
}

.reservation-status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.reservation-status.pending {
    background: #fff7ed;
    color: #c2410c;
}

.reservation-status.confirmed {
    background: #ecfdf5;
    color: #047857;
}

.reservation-status.seated {
    background: #dbeafe;
    color: #1d4ed8;
}

.reservation-status.cancelled {
    background: #f1f5f9;
    color: #475569;
}

.reservation-status.rejected {
    background: #fef2f2;
    color: #b91c1c;
}

.reservation-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.reservation-actions form {
    margin: 0;
}

.reservation-actions button {
    border: 0;
    border-radius: 6px;
    padding: 7px 9px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.reservation-btn-confirm {
    background: #dcfce7;
    color: #166534;
}

.reservation-btn-seat {
    background: #dbeafe;
    color: #1e40af;
}

.reservation-btn-open {
    display: inline-block;
    background: #ecfdf5;
    color: #047857;
    border-radius: 6px;
    padding: 7px 9px;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
}

.reservation-btn-reject {
    background: #fee2e2;
    color: #991b1b;
}

.reservation-btn-cancel {
    background: #e2e8f0;
    color: #334155;
}

.reservation-empty {
    text-align: center;
    padding: 45px 20px !important;
    color: #94a3b8 !important;
}

.reservation-note {
    max-width: 180px;
    white-space: normal;
    overflow-wrap: anywhere;
    color: #64748b;
}

@media (max-width: 650px) {
    .reservation-admin-header h1 {
        font-size: 21px;
    }

    .reservation-filter input[name="keyword"] {
        min-width: 100%;
    }
}
</style>

<div class="reservation-admin-page">

    <div class="reservation-admin-header">
        <div>
            <h1>Quản lý đặt bàn</h1>
            <p>Theo dõi và xử lý các yêu cầu đặt bàn từ Homepage SmartDine.</p>
        </div>
    </div>

    <?php if ($loadError !== ''): ?>
        <div class="flash-alert flash-error">
            <?= reservationH($loadError) ?>
        </div>
    <?php endif; ?>

    <div class="reservation-stat-grid">

        <div class="reservation-stat">
            <span>Tổng lượt đặt bàn</span>
            <strong>
                <?= number_format((int)($stats['total'] ?? 0)) ?>
            </strong>
        </div>

        <div class="reservation-stat">
            <span>Chờ xác nhận</span>
            <strong style="color:#ea580c">
                <?= number_format((int)($stats['pending_count'] ?? 0)) ?>
            </strong>
        </div>

        <div class="reservation-stat">
            <span>Đã xác nhận</span>
            <strong style="color:#059669">
                <?= number_format((int)($stats['confirmed_count'] ?? 0)) ?>
            </strong>
        </div>

        <div class="reservation-stat">
            <span>Đã vào bàn</span>
            <strong style="color:#2563eb">
                <?= number_format((int)($stats['seated_count'] ?? 0)) ?>
            </strong>
        </div>

        <div class="reservation-stat">
            <span>Đã hủy</span>
            <strong style="color:#64748b">
                <?= number_format((int)($stats['cancelled_count'] ?? 0)) ?>
            </strong>
        </div>

        <div class="reservation-stat">
            <span>Đã từ chối</span>
            <strong style="color:#dc2626">
                <?= number_format((int)($stats['rejected_count'] ?? 0)) ?>
            </strong>
        </div>

    </div>

    <form class="reservation-filter" method="get" action="index.php">

        <input type="hidden" name="page" value="reservations">

        <input
            type="text"
            name="keyword"
            placeholder="Tìm tên khách, số điện thoại, mã đặt bàn..."
            value="<?= reservationH($keyword) ?>"
        >

        <input
            type="date"
            name="date"
            value="<?= reservationH($dateFilter) ?>"
            aria-label="Lọc theo ngày đặt bàn"
        >

        <select name="status">
            <option value="">Tất cả trạng thái</option>

            <?php foreach ($statusLabels as $value => $label): ?>
                <option
                    value="<?= reservationH($value) ?>"
                    <?= $statusFilter === $value ? 'selected' : '' ?>
                >
                    <?= reservationH($label) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <button type="submit" class="btn btn-small btn-primary">
            Lọc dữ liệu
        </button>

        <a href="index.php?page=reservations" class="btn btn-small btn-outline">
            Xóa lọc
        </a>

    </form>

    <div class="reservation-table-wrap">

        <table class="reservation-table">

            <thead>
                <tr>
                    <th>Mã đặt bàn</th>
                    <th>Khách hàng</th>
                    <th>Ngày đặt</th>
                    <th>Giờ</th>
                    <th>Số người</th>
                    <th>Khu vực / Bàn</th>
                    <th>Ghi chú</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($reservations)): ?>

                    <tr>
                        <td colspan="9" class="reservation-empty">
                            Chưa tìm thấy lượt đặt bàn nào.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($reservations as $reservation): ?>

                        <?php
                        $status = $reservation['status'] ?? 'pending';

                        $statusText = $statusLabels[$status] ?? $status;

                        $bookingCode = 'SD' . str_pad(
                            (string)$reservation['id'],
                            6,
                            '0',
                            STR_PAD_LEFT
                        );
                        ?>

                        <tr>

                            <td>
                                <span class="reservation-code">
                                    <?= reservationH($bookingCode) ?>
                                </span>
                            </td>

                            <td class="reservation-customer">

                                <strong>
                                    <?= reservationH($reservation['customer_name']) ?>
                                </strong>

                                <small>
                                    <?= reservationH($reservation['phone']) ?>
                                </small>

                                <?php if (!empty($reservation['email'])): ?>
                                    <br>
                                    <small>
                                        <?= reservationH($reservation['email']) ?>
                                    </small>
                                <?php endif; ?>

                            </td>

                            <td>
                                <?= !empty($reservation['reservation_date'])
                                    ? date('d/m/Y', strtotime($reservation['reservation_date']))
                                    : '—' ?>
                            </td>

                            <td>
                                <?= !empty($reservation['reservation_time'])
                                    ? date('H:i', strtotime($reservation['reservation_time']))
                                    : '—' ?>
                            </td>

                            <td>
                                <?= (int)$reservation['guests'] ?> người
                            </td>

                            <td>

                                <strong>
                                    <?= reservationH($reservation['area_name'] ?? 'Chưa xác định') ?>
                                </strong>

                                <br>

                                <small>
                                    <?= reservationH(
                                        $reservation['table_code']
                                        ?: ($reservation['table_name'] ?? 'Chưa xác định')
                                    ) ?>
                                </small>

                            </td>

                            <td class="reservation-note">
                                <?= reservationH($reservation['note'] ?: 'Không có') ?>
                            </td>

                            <td>
                                <span class="reservation-status <?= reservationH($status) ?>">
                                    <?= reservationH($statusText) ?>
                                </span>
                                <?php if ($status === 'seated' && !empty($reservation['seated_at'])): ?>
                                    <br>
                                    <small style="color:#64748b;font-size:11px">
                                        Vào bàn: <?= date('H:i d/m', strtotime($reservation['seated_at'])) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td>

                                <div class="reservation-actions">

                                    <?php if ($status === 'pending'): ?>

                                        <?php if ($isAdminUser): ?>
                                            <form method="post" action="index.php?page=reservations">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= reservationH($csrfToken) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="reservation_id"
                                                    value="<?= (int)$reservation['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="reservation_action"
                                                    value="confirm"
                                                >

                                                <button
                                                    class="reservation-btn-confirm"
                                                    type="submit"
                                                >
                                                    Xác nhận
                                                </button>

                                            </form>
                                        <?php endif; ?>

                                        <form
                                            method="post"
                                            action="index.php?page=reservations"
                                            onsubmit="return confirm('Xác nhận khách đặt bàn này đã đến và vào bàn <?= reservationH($reservation['table_code'] ?: '') ?>?')"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= reservationH($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="reservation_id"
                                                value="<?= (int)$reservation['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="reservation_action"
                                                value="seat"
                                            >

                                            <button
                                                class="reservation-btn-seat"
                                                type="submit"
                                            >
                                                Đã vào bàn
                                            </button>

                                        </form>

                                        <?php if ($isAdminUser): ?>
                                            <form
                                                method="post"
                                                action="index.php?page=reservations"
                                                onsubmit="return confirm('Bạn có chắc muốn từ chối lượt đặt bàn này?')"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= reservationH($csrfToken) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="reservation_id"
                                                    value="<?= (int)$reservation['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="reservation_action"
                                                    value="reject"
                                                >

                                                <button
                                                    class="reservation-btn-reject"
                                                    type="submit"
                                                >
                                                    Từ chối
                                                </button>

                                            </form>
                                        <?php endif; ?>

                                    <?php elseif ($status === 'confirmed'): ?>

                                        <form
                                            method="post"
                                            action="index.php?page=reservations"
                                            onsubmit="return confirm('Xác nhận khách đặt bàn này đã đến và vào bàn <?= reservationH($reservation['table_code'] ?: '') ?>?')"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= reservationH($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="reservation_id"
                                                value="<?= (int)$reservation['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="reservation_action"
                                                value="seat"
                                            >

                                            <button
                                                class="reservation-btn-seat"
                                                type="submit"
                                            >
                                                Đã vào bàn
                                            </button>

                                        </form>

                                        <?php if ($isAdminUser): ?>
                                            <form
                                                method="post"
                                                action="index.php?page=reservations"
                                                onsubmit="return confirm('Bạn có chắc muốn hủy lượt đặt bàn đã xác nhận?')"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= reservationH($csrfToken) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="reservation_id"
                                                    value="<?= (int)$reservation['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="reservation_action"
                                                    value="cancel"
                                                >

                                                <button
                                                    class="reservation-btn-cancel"
                                                    type="submit"
                                                >
                                                    Hủy đặt bàn
                                                </button>

                                            </form>
                                        <?php endif; ?>

                                    <?php elseif ($status === 'seated'): ?>

                                        <?php if (!empty($reservation['table_code'])): ?>
                                            <a
                                                class="reservation-btn-open"
                                                href="index.php?page=table-order&amp;table=<?= urlencode($reservation['table_code']) ?>"
                                            >
                                                Mở bàn <?= reservationH($reservation['table_code']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#1d4ed8;font-size:11px;font-weight:700">
                                                Đã nhận bàn
                                            </span>
                                        <?php endif; ?>

                                    <?php else: ?>

                                        <span style="color:#94a3b8;font-size:11px">
                                            Đã xử lý
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>
