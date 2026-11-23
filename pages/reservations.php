<?php
require_once __DIR__ . '/../includes/http.php';
require_once __DIR__ . '/../includes/reservations.php';
require_once __DIR__ . '/../includes/tables.php';

$filters = [
    'date' => trim((string) ($_GET['date'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? 'all')),
    'area' => trim((string) ($_GET['area'] ?? '')),
    'search' => trim((string) ($_GET['search'] ?? '')),
];
$filterQuery = ['page' => 'reservations'];
foreach ($filters as $key => $value) {
    if ($value !== '' && !($key === 'status' && $value === 'all')) {
        $filterQuery[$key] = $value;
    }
}
$reservationListUrl = 'index.php?' . http_build_query($filterQuery);
$isReservationAdmin = isAdmin();
if ($isReservationAdmin && empty($_SESSION['reservation_admin_csrf'])) {
    $_SESSION['reservation_admin_csrf'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['reservation_action'] ?? '') === 'update_status') {
    try {
        if (!$isReservationAdmin) {
            throw new DomainException('Chỉ quản trị viên mới được thay đổi trạng thái lịch đặt bàn.');
        }

        $token = (string) ($_POST['csrf_token'] ?? '');
        if (empty($_SESSION['reservation_admin_csrf']) || !hash_equals($_SESSION['reservation_admin_csrf'], $token)) {
            throw new InvalidArgumentException('Phiên thao tác đã hết hạn. Vui lòng tải lại trang.');
        }

        $reservationId = filter_var($_POST['reservation_id'] ?? null, FILTER_VALIDATE_INT);
        if ($reservationId === false) {
            throw new InvalidArgumentException('Lịch đặt bàn không hợp lệ.');
        }

        $targetStatus = trim((string) ($_POST['target_status'] ?? ''));
        updateReservationStatus($reservationId, $targetStatus);
        $_SESSION['flash_success'] = 'Đã cập nhật trạng thái lịch đặt bàn.';
    } catch (DomainException | InvalidArgumentException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    } catch (RuntimeException | PDOException $e) {
        error_log('reservation status update: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Không thể cập nhật lịch đặt bàn. Vui lòng thử lại.';
    }

    header('Location: ' . $reservationListUrl);
    exit;
}

$areas = [];
$reservations = [];
$error = '';

try {
    $areas = getAllAreas('active');
    $reservations = getReservationSchedule($filters);
} catch (InvalidArgumentException $e) {
    $error = $e->getMessage();
} catch (RuntimeException | PDOException $e) {
    error_log('reservation schedule page: ' . $e->getMessage());
    $error = 'Không tải được lịch đặt bàn. Vui lòng thử lại.';
}

$statusLabels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'seated' => 'Đang phục vụ',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy',
    'no_show' => 'Vắng mặt',
];
$upcomingCount = 0;
$now = new DateTime('now', new DateTimeZone(RESERVATION_TIMEZONE));
foreach ($reservations as $reservation) {
    $startsAt = DateTime::createFromFormat(
        '!Y-m-d H:i:s',
        $reservation['reservation_date'] . ' ' . $reservation['reservation_time'],
        new DateTimeZone(RESERVATION_TIMEZONE)
    );
    if (in_array($reservation['status'], ['pending', 'confirmed'], true) && $startsAt && $startsAt >= $now) {
        $upcomingCount++;
    }
}
?>

<section class="page-heading">
  <span class="eyebrow">QUẢN LÝ NHÀ HÀNG</span>
  <h1>Lịch đặt bàn</h1>
  <p>Theo dõi lịch hẹn, thông tin khách và bàn đã được giữ.</p>
</section>

<?php if ($error !== ''): ?>
  <div class="flash-alert flash-error"><span><?= escapeHtml($error) ?></span></div>
<?php endif; ?>

<div class="reservation-admin-stats">
  <div class="panel reservation-admin-stat"><span>Đang hiển thị</span><b><?= count($reservations) ?></b></div>
  <div class="panel reservation-admin-stat"><span>Sắp tới (trong kết quả)</span><b><?= $upcomingCount ?></b></div>
</div>

<form class="reservation-admin-filters" method="get" action="index.php">
  <input type="hidden" name="page" value="reservations">
  <label>Tìm khách / mã / bàn
    <input type="search" name="search" value="<?= escapeHtml($filters['search']) ?>" placeholder="Tên, số điện thoại, mã đặt bàn...">
  </label>
  <label>Ngày đặt
    <input type="date" name="date" value="<?= escapeHtml($filters['date']) ?>">
  </label>
  <label>Khu vực
    <select name="area">
      <option value="">Tất cả khu vực</option>
      <?php foreach ($areas as $area): ?>
        <option value="<?= escapeHtml($area['code']) ?>" <?= $filters['area'] === $area['code'] ? 'selected' : '' ?>>
          <?= escapeHtml($area['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Trạng thái
    <select name="status">
      <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>Tất cả trạng thái</option>
      <?php foreach ($statusLabels as $status => $label): ?>
        <option value="<?= escapeHtml($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="reservation-admin-filter-actions">
    <button class="btn" type="submit">Lọc lịch</button>
    <a class="btn btn-outline" href="index.php?page=reservations">Xóa lọc</a>
  </div>
</form>

<section class="panel reservation-admin-list">
  <?php if ($reservations === []): ?>
    <div class="reservation-admin-empty">
      <span aria-hidden="true">▦</span>
      <h2>Chưa có lịch đặt bàn phù hợp</h2>
      <p class="muted">Lịch khách đặt trực tuyến sẽ xuất hiện tại đây.</p>
    </div>
  <?php else: ?>
    <div class="reservation-admin-table-wrap">
      <table class="reservation-admin-table">
        <thead>
          <tr>
            <th>Thời gian</th>
            <th>Khách hàng</th>
            <th>Bàn / Khu vực</th>
            <th>Số khách</th>
            <th>Mã đặt bàn</th>
            <th>Trạng thái</th>
            <th>Ghi chú</th>
            <?php if ($isReservationAdmin): ?><th>Thao tác</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservations as $reservation): ?>
            <?php
            $status = $reservation['status'];
            $statusLabel = $statusLabels[$status] ?? $status;
            $reservationDate = DateTime::createFromFormat('!Y-m-d', $reservation['reservation_date']);
            $transitions = $isReservationAdmin ? getReservationStatusTransitions($reservation) : [];
            ?>
            <tr>
              <td class="reservation-admin-time">
                <b><?= escapeHtml($reservation['reservation_time']) ?></b>
                <small><?= $reservationDate ? $reservationDate->format('d/m/Y') : escapeHtml($reservation['reservation_date']) ?></small>
              </td>
              <td>
                <b><?= escapeHtml($reservation['customer_name'] ?: 'Khách') ?></b>
                <a class="reservation-admin-phone" href="tel:<?= escapeHtml(preg_replace('/\D+/', '', $reservation['customer_phone'])) ?>">
                  <?= escapeHtml($reservation['customer_phone'] ?: '—') ?>
                </a>
              </td>
              <td><b><?= escapeHtml($reservation['table_code'] ?: '—') ?></b><small><?= escapeHtml($reservation['area_name'] ?: '—') ?></small></td>
              <td><?= (int) $reservation['guest_count'] ?> người</td>
              <td><code><?= escapeHtml($reservation['reference']) ?></code></td>
              <td><span class="reservation-admin-status reservation-admin-status-<?= escapeHtml($status) ?>"><?= escapeHtml($statusLabel) ?></span></td>
              <td class="reservation-admin-note"><?= escapeHtml($reservation['note'] ?: '—') ?></td>
              <?php if ($isReservationAdmin): ?>
                <td>
                  <?php if ($transitions !== []): ?>
                    <div class="reservation-admin-actions">
                      <?php foreach ($transitions as $targetStatus => $actionLabel): ?>
                        <?php
                        $buttonClass = match ($targetStatus) {
                            'cancelled' => 'btn-outline-danger',
                            'no_show' => 'btn-danger',
                            'completed' => 'btn-success',
                            default => 'btn',
                        };
                        $confirmation = match ($targetStatus) {
                            'cancelled' => 'Bạn chắc chắn muốn hủy lịch này?',
                            'no_show' => 'Xác nhận khách đã không đến?',
                            default => 'Xác nhận cập nhật trạng thái lịch đặt bàn?',
                        };
                        ?>
                        <form method="post" action="<?= escapeHtml($reservationListUrl) ?>" onsubmit="return confirm('<?= escapeHtml($confirmation) ?>')">
                          <input type="hidden" name="reservation_action" value="update_status">
                          <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['reservation_admin_csrf']) ?>">
                          <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>">
                          <input type="hidden" name="target_status" value="<?= escapeHtml($targetStatus) ?>">
                          <button class="btn btn-small <?= $buttonClass ?>" type="submit"><?= escapeHtml($actionLabel) ?></button>
                        </form>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span class="reservation-admin-no-action">—</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="reservation-admin-limit">Hiển thị tối đa 200 lịch đặt gần nhất.</p>
  <?php endif; ?>
</section>
